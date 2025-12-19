<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * YCloud WhatsApp API Webhook Handler
 * Handles all webhook events from YCloud WhatsApp API
 * 
 * Supported Events:
 * - whatsapp.inbound_message.received (incoming messages)
 * - whatsapp.message.status.updated (message status updates)
 */
class WhatsApp extends Controller
{
    /**
     * Handle incoming webhook from YCloud WhatsApp API
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
     * Webhook Verification
     */
    private function verify()
    {
        $mode = $_GET['hub_mode'] ?? null;
        $token = $_GET['hub_verify_token'] ?? null;
        $challenge = $_GET['hub_challenge'] ?? null;

        \Log::write("YCloud Verification Attempt - Mode: $mode", 'webhook', 'WhatsApp');

        $verifyToken = \Env::WA_VERIFY_TOKEN;

        if ($mode === 'subscribe' && $token === $verifyToken) {
            \Log::write("YCloud Verification SUCCESS", 'webhook', 'WhatsApp');
            header('Content-Type: text/plain');
            echo $challenge;
            exit;
        }

        \Log::write("YCloud Verification FAILED", 'webhook', 'WhatsApp');
        http_response_code(403);
        echo "Verification failed";
        exit;
    }

    /**
     * Receive and process webhook events from YCloud
     */
    private function receive()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        \Log::write("YCloud Webhook Received: " . substr($json, 0, 500), 'webhook', 'WhatsApp');

        if (!$data) {
            \Log::write("ERROR: Invalid JSON", 'webhook', 'WhatsApp');
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
            exit;
        }

        $db = $this->db(0);
        $eventType = $data['type'] ?? null;
        $eventId = $data['id'] ?? null;

        // Check if event already processed (avoid duplicates)
        if ($eventId) {
            $existing = $db->get_where('wh_whatsapp', ['event_id' => $eventId]);
            if ($existing->num_rows() > 0) {
                \Log::write("Event $eventId already processed - skipping", 'webhook', 'WhatsApp');
                http_response_code(200);
                echo json_encode(['status' => 'ok', 'message' => 'Already processed']);
                exit;
            }
        }

        try {
            switch ($eventType) {
                case 'whatsapp.inbound_message.received':
                    $this->handleInboundMessage($data, $db);
                    break;

                case 'whatsapp.message.status.updated':
                    $this->handleStatusUpdate($data, $db);
                    break;

                default:
                    \Log::write("Unknown event type: $eventType", 'webhook', 'WhatsApp');
            }
        } catch (\Exception $e) {
            \Log::write("EXCEPTION: " . $e->getMessage(), 'webhook', 'WhatsApp');
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    /**
     * Handle incoming message from customer
     */
    private function handleInboundMessage($data, $db)
    {
        $msg = $data['whatsappInboundMessage'] ?? [];
        
        if (empty($msg)) {
            \Log::write("ERROR: No whatsappInboundMessage in data", 'webhook', 'WhatsApp');
            return;
        }

        $insertData = [
            'event_id'      => $data['id'] ?? null,
            'event_type'    => $data['type'] ?? null,
            'api_version'   => $data['apiVersion'] ?? null,
            'event_time'    => $this->convertTime($data['createTime'] ?? null),
            
            'message_id'    => $msg['id'] ?? null,
            'wamid'         => $msg['wamid'] ?? null,
            'waba_id'       => $msg['wabaId'] ?? null,
            
            'phone_from'    => $msg['from'] ?? null,
            'phone_to'      => $msg['to'] ?? null,
            'contact_name'  => $msg['customerProfile']['name'] ?? null,
            
            'message_type'  => $msg['type'] ?? null,
            'send_time'     => $this->convertTime($msg['sendTime'] ?? null),
            'raw_json'      => json_encode($data, JSON_UNESCAPED_UNICODE)
        ];

        // Extract message content based on type
        switch ($msg['type'] ?? '') {
            case 'text':
                $insertData['text_body'] = $msg['text']['body'] ?? null;
                break;
            
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
                $mediaType = $msg['type'];
                $insertData['media_id'] = $msg[$mediaType]['id'] ?? null;
                $insertData['media_mime_type'] = $msg[$mediaType]['mimeType'] ?? null;
                $insertData['media_caption'] = $msg[$mediaType]['caption'] ?? null;
                break;
        }

        $inserted = $db->insert('wh_whatsapp', $insertData);
        
        if ($inserted) {
            \Log::write("✓ Saved inbound message ID: $inserted from " . ($msg['from'] ?? 'unknown'), 'webhook', 'WhatsApp');
        } else {
            $error = $db->conn()->error;
            \Log::write("✗ DB ERROR: $error", 'webhook', 'WhatsApp');
        }
    }

    /**
     * Handle message status update (for outbound messages)
     */
    private function handleStatusUpdate($data, $db)
    {
        $statusUpdate = $data['whatsappMessageStatusUpdate'] ?? [];
        
        if (empty($statusUpdate)) {
            \Log::write("ERROR: No whatsappMessageStatusUpdate in data", 'webhook', 'WhatsApp');
            return;
        }

        $wamid = $statusUpdate['wamid'] ?? null;
        $status = $statusUpdate['status'] ?? null;

        // Try to update existing record first
        $updateData = [
            'status' => $status,
            'error_code' => $statusUpdate['errorCode'] ?? null,
            'error_message' => $statusUpdate['errorMessage'] ?? null
        ];

        $updated = $db->update('wh_whatsapp', $updateData, ['wamid' => $wamid]);

        if ($updated) {
            \Log::write("✓ Updated message status: $wamid -> $status", 'webhook', 'WhatsApp');
        } else {
            // If no existing record, create new one
            $insertData = [
                'event_id'      => $data['id'] ?? null,
                'event_type'    => $data['type'] ?? null,
                'api_version'   => $data['apiVersion'] ?? null,
                'event_time'    => $this->convertTime($data['createTime'] ?? null),
                
                'wamid'         => $wamid,
                'phone_to'      => $statusUpdate['to'] ?? null,
                'status'        => $status,
                'error_code'    => $statusUpdate['errorCode'] ?? null,
                'error_message' => $statusUpdate['errorMessage'] ?? null,
                'raw_json'      => json_encode($data, JSON_UNESCAPED_UNICODE)
            ];

            $inserted = $db->insert('wh_whatsapp', $insertData);
            
            if ($inserted) {
                \Log::write("✓ Created new status record: $wamid -> $status", 'webhook', 'WhatsApp');
            } else {
                $error = $db->conn()->error;
                \Log::write("✗ DB ERROR: $error", 'webhook', 'WhatsApp');
            }
        }
    }

    /**
     * Convert ISO 8601 timestamp to MySQL datetime
     */
    private function convertTime($isoTime)
    {
        if (!$isoTime) return null;
        
        try {
            $dt = new \DateTime($isoTime);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
