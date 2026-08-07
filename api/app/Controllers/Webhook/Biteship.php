<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\Laundry\InstantKurir;

/**
 * Biteship order status webhook
 * URL: /Webhook/Biteship
 */
class Biteship extends Controller
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
            return;
        }

        // Optional shared secret (header X-Biteship-Secret or query)
        if (class_exists('Env') && defined('Env::BITESHIP_WEBHOOK_SECRET')) {
            $expected = trim((string) \Env::BITESHIP_WEBHOOK_SECRET);
            if ($expected !== '') {
                $provided = trim((string) ($_SERVER['HTTP_X_BITESHIP_SECRET'] ?? $_GET['secret'] ?? ''));
                if ($provided === '' || !hash_equals($expected, $provided)) {
                    http_response_code(401);
                    echo json_encode(['ok' => false, 'message' => 'Invalid secret']);
                    return;
                }
            }
        }

        $db = $this->db(1);
        if (!$db) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'DB error']);
            return;
        }

        \Log::write('Biteship webhook: ' . $json, 'webhook', 'Biteship');

        $result = InstantKurir::applyWebhook($db, $data);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
