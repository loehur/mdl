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
        $verifyToken = 'madinah_laundry_webhook_token';

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
     * Receive and log messages from WhatsApp
     */
    private function receive()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Log the raw JSON data
        \Log::write("WhatsApp Webhook Data Received: " . $json, 'webhook', 'WhatsApp');

        // You can add more specific logging or processing here
        if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
            $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
            $from = $message['from'] ?? 'unknown';
            $text = $message['text']['body'] ?? '(not a text message)';
            
            \Log::write("Message from $from: $text", 'webhook', 'WhatsApp');
        }

        // Always return 200 OK to Meta
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'received']);
        exit;
    }
}
