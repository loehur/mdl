<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * YCloud WhatsApp Webhook Handler
 * Updated to use new 3-table structure:
 * - wa_webhooks: raw webhook logs
 * - wa_conversations: conversation tracking
 * - wa_messages: individual messages
 */
class WhatsApp extends Controller
{
    /**
     * Handle incoming webhook
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

        \Log::write("YCloud Verification: mode=$mode", 'webhook', 'WhatsApp');

        $verifyToken = \Env::WA_VERIFY_TOKEN;

        if ($mode === 'subscribe' && $token === $verifyToken) {
            \Log::write("✓ Verification SUCCESS", 'webhook', 'WhatsApp');
            header('Content-Type: text/plain');
            echo $challenge;
            exit;
        }

        \Log::write("✗ Verification FAILED", 'webhook', 'WhatsApp');
        http_response_code(403);
        exit;
    }

    /**
     * Receive and process webhook
     */
    private function receive()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data) {
            \Log::write("ERROR: Invalid JSON", 'webhook', 'WhatsApp');
            http_response_code(200);
            exit;
        }

        $db = $this->db(0);
        $eventType = $data['type'] ?? 'unknown';

        // Step 1: Save raw webhook to wa_webhooks
        $webhookId = $this->saveWebhookLog($db, $eventType, $json);
        \Log::write("Webhook logged: ID=$webhookId, Type=$eventType", 'webhook', 'WhatsApp');

        // Step 2: Process based on event type
        try {
            switch ($eventType) {
                case 'whatsapp.inbound_message.received':
                    $this->handleInboundMessage($db, $data);
                    break;

                case 'whatsapp.message.status.updated':
                    $this->handleStatusUpdate($db, $data);
                    break;

                default:
                    \Log::write("Unknown event: $eventType", 'webhook', 'WhatsApp');
            }
        } catch (\Exception $e) {
            \Log::write("EXCEPTION: " . $e->getMessage(), 'webhook', 'WhatsApp');
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    /**
     * Save raw webhook to wa_webhooks table
     */
    private function saveWebhookLog($db, $eventType, $json)
    {
        $data = [
            'provider' => 'ycloud',
            'event_type' => $eventType,
            'payload' => $json,
            'received_at' => date('Y-m-d H:i:s')
        ];

        return $db->insert('wa_webhooks', $data);
    }

    /**
     * Handle inbound message from customer
     */
    private function handleInboundMessage($db, $data)
    {
        $msg = $data['whatsappInboundMessage'] ?? [];
        if (empty($msg)) {
            \Log::write("ERROR: No whatsappInboundMessage", 'webhook', 'WhatsApp');
            return;
        }

        $waNumber = $msg['from'] ?? null;
        $contactName = $msg['customerProfile']['name'] ?? null;
        $messageType = $msg['type'] ?? 'text';
        $messageId = $msg['id'] ?? null;
        $wamid = $msg['wamid'] ?? null;

        if (!$waNumber) {
            \Log::write("ERROR: No 'from' number", 'webhook', 'WhatsApp');
            return;
        }

        // Step 1: Get or create conversation
        $conversationId = $this->getOrCreateConversation($db, $waNumber, $contactName);

        // Step 2: Extract message content
        $textBody = null;
        $mediaId = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaCaption = null;

        switch ($messageType) {
            case 'text':
                $textBody = $msg['text']['body'] ?? null;
                break;

            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'voice':
                $mediaId = $msg[$messageType]['id'] ?? null;
                $mediaMimeType = $msg[$messageType]['mimeType'] ?? null;
                $mediaCaption = $msg[$messageType]['caption'] ?? null;
                break;
        }

        // Step 3: Save message to wa_messages
        $messageData = [
            'conversation_id' => $conversationId,
            'direction' => 'in',
            'message_type' => $messageType,
            'text' => $textBody,
            'media_id' => $mediaId,
            'media_mime_type' => $mediaMimeType,
            'media_caption' => $mediaCaption,
            'provider_message_id' => $messageId,
            'wamid' => $wamid,
            'created_at' => $this->convertTime($msg['sendTime'] ?? null)
        ];

        $msgId = $db->insert('wa_messages', $messageData);

        if ($msgId) {
            \Log::write("✓ Message saved: ID=$msgId, Conv=$conversationId, From=$waNumber", 'webhook', 'WhatsApp');
            
            // Step 4: Update conversation last_message
            $this->updateConversationLastMessage($db, $conversationId, $textBody ?? "[{$messageType}]");
        } else {
            $error = $db->conn()->error;
            \Log::write("✗ DB ERROR: $error", 'webhook', 'WhatsApp');
        }
    }

    /**
     * Handle outbound message status update
     */
    private function handleStatusUpdate($db, $data)
    {
        $statusUpdate = $data['whatsappMessageStatusUpdate'] ?? [];
        if (empty($statusUpdate)) {
            \Log::write("ERROR: No whatsappMessageStatusUpdate", 'webhook', 'WhatsApp');
            return;
        }

        $wamid = $statusUpdate['wamid'] ?? null;
        $status = $statusUpdate['status'] ?? null;
        $errorMessage = $statusUpdate['errorMessage'] ?? null;

        if (!$wamid) {
            \Log::write("ERROR: No wamid in status update", 'webhook', 'WhatsApp');
            return;
        }

        // Update message status in wa_messages
        $updateData = [
            'status' => $status,
            'error_message' => $errorMessage
        ];

        $updated = $db->update('wa_messages', $updateData, ['wamid' => $wamid]);

        if ($updated) {
            \Log::write("✓ Status updated: $wamid -> $status", 'webhook', 'WhatsApp');
        } else {
            \Log::write("⚠ Message not found for status update: $wamid", 'webhook', 'WhatsApp');
        }
    }

    /**
     * Get existing conversation or create new one
     */
    private function getOrCreateConversation($db, $waNumber, $contactName = null)
    {
        // Try to find existing conversation
        $existing = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
        
        if ($existing->num_rows() > 0) {
            $conv = $existing->row();
            
            // Update contact name if provided and different
            if ($contactName && $contactName !== $conv->contact_name) {
                $db->update('wa_conversations', 
                    ['contact_name' => $contactName], 
                    ['id' => $conv->id]
                );
            }
            
            return $conv->id;
        }

        // Create new conversation
        $convData = [
            'wa_number' => $waNumber,
            'contact_name' => $contactName,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $db->insert('wa_conversations', $convData);
    }

    /**
     * Update conversation's last message
     */
    private function updateConversationLastMessage($db, $conversationId, $messageText)
    {
        $updateData = [
            'last_message' => substr($messageText, 0, 200), // limit to 200 chars
            'last_message_at' => date('Y-m-d H:i:s')
        ];

        $db->update('wa_conversations', $updateData, ['id' => $conversationId]);
    }

    /**
     * Convert ISO 8601 to MySQL datetime
     */
    private function convertTime($isoTime)
    {
        if (!$isoTime) return date('Y-m-d H:i:s');
        
        try {
            $dt = new \DateTime($isoTime);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return date('Y-m-d H:i:s');
        }
    }
}
