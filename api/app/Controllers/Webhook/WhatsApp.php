<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

class WhatsApp extends Controller
{
    /**
     * Handle incoming webhook from WhatsApp Official (Meta)
     * URL: /Webhook/WhatsApp
     */
    public function index()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            return $this->verify();
        }

        if ($method === 'POST') {
            return $this->receive();
        }

        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    /**
     * Webhook Verification (Meta requires this)
     */
    private function verify()
    {
        $mode = $_GET['hub_mode'] ?? null;
        $token = $_GET['hub_verify_token'] ?? null;
        $challenge = $_GET['hub_challenge'] ?? null;

        // Log the verification attempt
        \Log::write("WhatsApp Verification Attempt - Mode: $mode, Token: $token", 'webhook', 'WhatsApp');

        // Replace 'YOUR_VERIFY_TOKEN' with your actual token configured in Meta Developer Portal
        $verifyToken = \Env::WA_VERIFY_TOKEN;

        if ($mode === 'subscribe' && $token === $verifyToken) {
            \Log::write("WhatsApp Verification SUCCESS", 'webhook', 'WhatsApp');
            header('Content-Type: text/plain');
            echo $challenge;
            exit;
        }

        \Log::write("WhatsApp Verification FAILED", 'webhook', 'WhatsApp');
        http_response_code(403);
        echo "Verification failed";
        exit;
    }

    /**
     * Receive, store, and log messages from WhatsApp
     */
    private function receive()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Log the raw JSON data using the helper
        \Log::write("WhatsApp Hook Received: " . $json, 'webhook', 'WhatsApp');

        if (!$data) {
            return;
        }

        $db = $this->db(0);

        // 1. Process Messages (Incoming from users)
        if (isset($data['entry'][0]['changes'][0]['value']['messages'])) {
            $contacts = $data['entry'][0]['changes'][0]['value']['contacts'] ?? [];
            $contactName = $contacts[0]['profile']['name'] ?? null;

            foreach ($data['entry'][0]['changes'][0]['value']['messages'] as $msg) {
                $insertData = [
                    'wa_id'       => $msg['id'] ?? null,
                    'phone'       => $msg['from'] ?? null,
                    'sender_name' => $contactName,
                    'type'        => 'message',
                    'body'        => $msg['text']['body'] ?? ($msg['type'] ?? 'other'),
                    'status'      => 'received',
                    'timestamp'   => $msg['timestamp'] ?? null,
                    'raw_data'    => $json
                ];
                
                $db->insert('wh_whatsapp', $insertData);
                \Log::write("Saved Message from " . ($msg['from'] ?? 'unknown'), 'webhook', 'WhatsApp');
            }
        }

        // 2. Process Status Updates (Sent, Delivered, Read)
        if (isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
            foreach ($data['entry'][0]['changes'][0]['value']['statuses'] as $status) {
                $wa_id = $status['id'] ?? null;
                $st = $status['status'] ?? null;
                
                $insertData = [
                    'wa_id'       => $wa_id,
                    'phone'       => $status['recipient_id'] ?? null,
                    'type'        => 'status',
                    'body'        => null, // body is empty for status updates
                    'status'      => $st,
                    'timestamp'   => $status['timestamp'] ?? null,
                    'raw_data'    => $json
                ];
                
                $db->insert('wh_whatsapp', $insertData);
                \Log::write("Saved Status Update: $wa_id -> $st", 'webhook', 'WhatsApp');
            }
        }

        // Always return 200 OK to Meta
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'received']);
        exit;
    }
}
