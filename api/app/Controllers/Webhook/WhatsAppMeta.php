<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * WhatsApp Cloud API (Meta) webhook.
 *
 * Endpoint: /Webhook/WhatsAppMeta
 * Untuk tahap awal setiap event hanya dicatat ke log; tidak ada pemrosesan
 * pesan, penyimpanan database, maupun balasan WhatsApp.
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

    /** Receive an event from Meta and log it without any further action. */
    private function receive(): void
    {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        $signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');

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

        // Meta hanya membutuhkan respons 2xx. Event sengaja belum diproses.
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'EVENT_RECEIVED']);
    }

    private function verifyToken(): string
    {
        // Dipisahkan dari token YCloud agar konfigurasi dua provider tidak saling memakai.
        return defined('Env::META_WA_VERIFY_TOKEN') ? (string) constant('Env::META_WA_VERIFY_TOKEN') : '';
    }
}
