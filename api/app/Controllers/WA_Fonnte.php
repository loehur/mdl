<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Config\Fonnte;

/**
 * WA_Fonnte Controller
 * Endpoint untuk mengirim pesan WhatsApp via Fonnte API
 * @see https://docs.fonnte.com/api-send-message/
 *
 * URL: /WA_Fonnte/send (POST)
 */
class WA_Fonnte extends Controller
{
    private $apiUrl;
    private $token;

    public function __construct()
    {
        $this->handleCors();
        $this->apiUrl = Fonnte::getBaseUrl() . '/send';
        $this->token = Fonnte::getToken();
    }

    /**
     * Default - info endpoint
     */
    public function index()
    {
        $this->success([
            'name' => 'WA Fonnte API',
            'version' => '1.0',
            'docs' => 'https://docs.fonnte.com/api-send-message/',
            'endpoints' => [
                'POST /WA_Fonnte/send' => 'Send WhatsApp message via Fonnte',
            ],
        ], 'WA Fonnte API Ready');
    }

    /**
     * Send message via Fonnte API
     * POST /WA_Fonnte/send
     *
     * Body (JSON):
     * - target (required): string - nomor WA, bisa multiple dipisah koma. Contoh: 08123456789 atau 08123456789,08234567890
     * - message (optional): string - teks pesan
     * - url (optional): string - URL file/gambar/audio/video untuk attachment
     * - filename (optional): string - nama file custom
     * - schedule (optional): int - unix timestamp untuk jadwal kirim
     * - delay (optional): string - delay antar target (contoh: "2" atau "2-10" untuk random)
     * - countryCode (optional): string - default 62
     * - location (optional): string - format: "latitude,longitude"
     * - typing: always true (typing indicator)
     * - preview (optional): bool - preview link, default true
     * - connectOnly (optional): bool - hanya kirim jika device terhubung
     */
    public function send()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['target']);

        $token = $this->token ?: ($body['token'] ?? null);
        if (empty($token)) {
            $this->error('Fonnte token not configured. Set FONNTE_TOKEN in Env.php or pass "token" in request body.', 400);
        }

        $target = (string) trim($body['target']);
        $targets = array_map('trim', explode(',', $target));
        $targets = array_filter($targets);

        if (empty($targets)) {
            $this->error('Invalid target. Provide at least one valid phone number.', 400);
        }

        // Format target: pastikan string, boleh pakai format 0812xxx atau 62812xxx
        $countryCode = (string) ($body['countryCode'] ?? Fonnte::getCountryCode());
        $formattedTargets = [];
        foreach ($targets as $t) {
            $digits = preg_replace('/[^0-9]/', '', $t);
            if (strlen($digits) < 8) {
                continue; // skip invalid
            }
            if ($countryCode !== '0') {
                if (substr($digits, 0, 1) === '0') {
                    $digits = $countryCode . substr($digits, 1);
                } elseif (strlen($digits) <= 10 && substr($digits, 0, 2) !== $countryCode) {
                    $digits = $countryCode . $digits;
                }
            }
            $formattedTargets[] = $digits;
        }

        if (empty($formattedTargets)) {
            $this->error('No valid target numbers found.', 400);
        }

        $targetStr = implode(',', $formattedTargets);

        $postFields = [
            'target' => $targetStr,
        ];

        if (!empty($body['message'])) {
            $postFields['message'] = (string) $body['message'];
        }
        if (!empty($body['url'])) {
            $postFields['url'] = (string) $body['url'];
        }
        if (isset($body['filename'])) {
            $postFields['filename'] = (string) $body['filename'];
        }
        if (isset($body['schedule']) && is_numeric($body['schedule'])) {
            $postFields['schedule'] = (int) $body['schedule'];
        }
        $postFields['delay'] = isset($body['delay']) ? (string) $body['delay'] : '3-20';
        if (isset($body['countryCode'])) {
            $postFields['countryCode'] = (string) $body['countryCode'];
        }
        if (!empty($body['location'])) {
            $postFields['location'] = (string) $body['location'];
        }
        $postFields['typing'] = true; // Always show typing indicator
        if (isset($body['preview'])) {
            $postFields['preview'] = (bool) $body['preview'];
        }
        if (isset($body['connectOnly'])) {
            $postFields['connectOnly'] = (bool) $body['connectOnly'];
        }
        if (isset($body['inboxid']) && is_numeric($body['inboxid'])) {
            $postFields['inboxid'] = (int) $body['inboxid'];
        }
        if (isset($body['duration']) && is_numeric($body['duration'])) {
            $postFields['duration'] = (int) $body['duration'];
        }
        if (!empty($body['choices'])) {
            $postFields['choices'] = is_array($body['choices']) ? implode(',', $body['choices']) : (string) $body['choices'];
        }
        if (!empty($body['select'])) {
            $postFields['select'] = in_array(strtolower($body['select']), ['single', 'multiple']) ? strtolower($body['select']) : 'single';
        }
        if (!empty($body['pollname'])) {
            $postFields['pollname'] = (string) $body['pollname'];
        }

        $response = $this->callFonnteApi($postFields, $token);

        if (isset($response['status']) && $response['status'] === true) {
            $this->success([
                'requestid' => $response['requestid'] ?? null,
                'id' => $response['id'] ?? null,
                'target' => $response['target'] ?? $targetStr,
                'process' => $response['process'] ?? 'pending',
                'detail' => $response['detail'] ?? 'Message in queue',
            ], $response['detail'] ?? 'Message sent successfully');
        }

        $reason = $response['reason'] ?? 'Unknown error';
        $this->error("Fonnte API error: {$reason}", 502, $response);
    }

    /**
     * Call Fonnte Send API
     * @param array $postFields
     * @param string $token
     * @return array Decoded JSON response
     */
    private function callFonnteApi(array $postFields, string $token)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $token,
            ],
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'status' => false,
                'reason' => 'cURL error: ' . $curlError,
            ];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => false,
                'reason' => 'Invalid JSON response',
                'raw' => substr($raw, 0, 500),
            ];
        }

        // Fonnte returns "Status" (capital S) sometimes
        if (isset($decoded['Status'])) {
            $decoded['status'] = $decoded['Status'];
        }

        return $decoded;
    }
}
