<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * WhatsApp Cloud API (Meta) webhook.
 *
 * Endpoint: /Webhook/WhatsAppMeta
 * Event tervalidasi diteruskan ke inbox WaDesk.
 */
class WhatsAppMeta extends Controller
{
    public function index()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            $this->verify();
            return;
        }

        if ($method === 'POST') {
            $this->receive();
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    /** Meta calls this once when a callback URL is configured. */
    private function verify(): void
    {
        $mode = (string) ($_GET['hub_mode'] ?? '');
        $token = (string) ($_GET['hub_verify_token'] ?? '');
        $challenge = (string) ($_GET['hub_challenge'] ?? '');
        $verifyToken = $this->verifyToken();

        if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, $token)) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo $challenge;
            return;
        }

        \Log::write(
            'Verification failed: mode=' . $mode . ', token_present=' . ($token !== '' ? 'yes' : 'no'),
            'wa_meta',
            'webhook'
        );
        http_response_code(403);
    }

    /** Validate an event from Meta, log a compact summary, then hand it to WaDesk. */
    private function receive(): void
    {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        $signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');

        if (!$this->isValidSignature($rawBody, $signature)) {
            \Log::write('Rejected Meta WhatsApp webhook: invalid signature', 'wa_meta', 'webhook');
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
            return;
        }

        $summary = [
            'object' => is_array($payload) ? ($payload['object'] ?? 'unknown') : 'invalid_json',
            'entries' => is_array($payload) && isset($payload['entry']) && is_array($payload['entry'])
                ? count($payload['entry'])
                : 0,
            'signature_present' => $signature !== '',
            'payload' => is_array($payload) ? $payload : $rawBody,
        ];

        \Log::write(
            'Incoming Meta WhatsApp webhook: ' . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'wa_meta',
            'webhook'
        );

        (new WaDesk())->receiveMetaPayload($rawBody);
    }

    private function verifyToken(): string
    {
        // Dipisahkan dari token YCloud agar konfigurasi dua provider tidak saling memakai.
        return defined('Env::META_WA_VERIFY_TOKEN') ? (string) constant('Env::META_WA_VERIFY_TOKEN') : '';
    }

    private function isValidSignature(string $rawBody, string $signature): bool
    {
        $secret = defined('Env::META_WA_APP_SECRET') ? (string) constant('Env::META_WA_APP_SECRET') : '';
        if ($secret === '') {
            // Tetap kompatibel sementara saat App Secret belum dimasukkan ke environment.
            return true;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return $signature !== '' && hash_equals($expected, $signature);
    }
}
