<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\Laundry\InstantKurir;

/**
 * Biteship order status webhook
 * URL: /Webhook/Biteship
 *
 * Instalasi Biteship mengirim body kosong / application/json kosong —
 * harus HTTP 200 + ok (tanpa validasi secret).
 */
class Biteship extends Controller
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        // Terima GET/HEAD (cek URL) dan POST
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'OPTIONS') {
            http_response_code(200);
            echo json_encode(['ok' => true]);
            return;
        }

        $json = file_get_contents('php://input');
        $jsonTrim = is_string($json) ? trim($json) : '';

        // Instalasi / healthcheck: body kosong atau {} → OK tanpa proses
        if ($jsonTrim === '' || $jsonTrim === '{}' || $jsonTrim === 'null') {
            http_response_code(200);
            echo json_encode(['ok' => true]);
            return;
        }

        $data = json_decode($jsonTrim, true);
        if (!is_array($data)) {
            // Tetap 200 agar retry Biteship tidak spam; log saja
            \Log::write('Biteship webhook invalid JSON: ' . $jsonTrim, 'webhook', 'Biteship');
            http_response_code(200);
            echo json_encode(['ok' => true, 'message' => 'ignored invalid json']);
            return;
        }

        // Tanpa event/order_id meaningful → anggap ping instalasi
        $orderId = (string) ($data['order_id'] ?? $data['id'] ?? '');
        $event = (string) ($data['event'] ?? '');
        if ($orderId === '' && $event === '' && empty($data['status'])) {
            http_response_code(200);
            echo json_encode(['ok' => true]);
            return;
        }

        // Secret opsional — hanya setelah instalasi (ada payload nyata)
        if (class_exists('Env') && defined('Env::BITESHIP_WEBHOOK_SECRET')) {
            $expected = trim((string) \Env::BITESHIP_WEBHOOK_SECRET);
            if ($expected !== '') {
                $provided = trim((string) ($_SERVER['HTTP_X_BITESHIP_SECRET'] ?? $_GET['secret'] ?? ''));
                if ($provided === '' || !hash_equals($expected, $provided)) {
                    // Jangan 401 saat instalasi sudah lewat tapi header salah:
                    // tetap log; return 200 ok false agar dashboard tidak "URL doesn't respond"
                    // Setelah instalasi sukses, user bisa enforce via log.
                    \Log::write('Biteship webhook bad secret', 'webhook', 'Biteship');
                    http_response_code(200);
                    echo json_encode(['ok' => false, 'message' => 'Invalid secret']);
                    return;
                }
            }
        }

        $db = $this->db(1);
        if (!$db) {
            \Log::write('Biteship webhook DB error', 'webhook', 'Biteship');
            http_response_code(200);
            echo json_encode(['ok' => true, 'message' => 'db unavailable, acknowledged']);
            return;
        }

        \Log::write('Biteship webhook: ' . $jsonTrim, 'webhook', 'Biteship');

        try {
            $result = InstantKurir::applyWebhook($db, $data);
            // Selalu acknowledge dengan ok agar Biteship anggap sukses
            $result['ok'] = !empty($result['ok']);
            http_response_code(200);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            \Log::write('Biteship webhook exc: ' . $e->getMessage(), 'webhook', 'Biteship');
            http_response_code(200);
            echo json_encode(['ok' => true, 'message' => 'acknowledged with error logged']);
        }
    }
}
