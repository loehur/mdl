<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * Fonnte webhook — DISABLED (group-send only via fonnte_server, no personal chat inbound).
 * URL: /Webhook/WA_Fonnte — kept so old WEBHOOK_URL calls get 200 without processing.
 */
class WA_Fonnte extends Controller
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode(['status' => 'ok', 'message' => 'Fonnte webhook disabled — group send only']);

            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

            return;
        }

        echo json_encode(['status' => 'ok', 'ignored' => true, 'reason' => 'personal_chat_webhook_disabled']);
    }
}
