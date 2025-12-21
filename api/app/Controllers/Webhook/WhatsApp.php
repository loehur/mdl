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
        // LOG ACCESS
        $logData = date('Y-m-d H:i:s') . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN') . " | Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . "\n";
        @file_put_contents(__DIR__ . '/../../../logs/wa_webhook_access.log', $logData, FILE_APPEND);
        
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

                case 'whatsapp.message.updated':
                    $this->handleMessageUpdated($db, $data);
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

        $waNumber = $this->normalizePhoneNumber($msg['from'] ?? null);
        $contactName = $msg['customerProfile']['name'] ?? null;
        $messageType = $msg['type'] ?? 'text';
        $messageId = $msg['id'] ?? null;
        $wamid = $msg['wamid'] ?? null;
        $status = $msg['status'] ?? 'received'; // Default status for inbound
        $sendTime = $this->convertTime($msg['sendTime'] ?? null);

        if (!$waNumber) {
            \Log::write("ERROR: No 'from' number", 'webhook', 'WhatsApp');
            return;
        }

        // Step 1: Update or create customer (for 24h window tracking)
        $customerId = $this->updateOrCreateCustomer($db, $waNumber, $contactName, $sendTime);

        // Logic: Check pending notifications in DB(1) (Resend Table)
        // If pending notif exists within 24h for this phone, send it now (CSW Open)
        // MOVED HERE: so CSW is valid before sending
        try {
            $db1 = $this->db(1);
            $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber); // 628...
            $phone0 = '0' . substr($cleanPhone, 2); // 08...
            $phonePlus = '+' . $cleanPhone; // +62...
            $limitTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
            
            $phones = ["'$cleanPhone'", "'$phone0'", "'$phonePlus'"];
            $phoneIn = implode(',', $phones);
            
            // get data pelanggan
            $where = "nomor_pelanggan IN ($phoneIn)";
            $pelanggan = $db1->query("SELECT id_pelanggan FROM pelanggan WHERE $where")->result_array();
            $id_pelanggans = array_column($pelanggan, 'id_pelanggan');

            if (!empty($id_pelanggans)) {
                $ids_in = implode(',', $id_pelanggans);
                $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan")->result_array();

                $noRefs = array_column($sales, 'no_ref');                
            }

            // Get pending notifs
            $sql = "SELECT * FROM notif 
                    WHERE state = 'pending' 
                    AND insertTime >= '$limitTime' 
                    AND phone IN ($phoneIn)
                    ORDER BY insertTime ASC";
            
            $pendingNotifs = $db1->query($sql)->result_array();
            
            if (!empty($pendingNotifs)) {
                 \Log::write("Found " . count($pendingNotifs) . " pending notifs for $waNumber. Sending...", 'webhook', 'WhatsApp');
                 
                 // Instantiate service on the fly
                 if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                     require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
                 }
                 $waService = new \App\Helpers\WhatsAppService();
                 
                 foreach ($pendingNotifs as $notif) {
                     // Send message (Free text is allowed now since customer just messaged us)
                     $res = $waService->sendFreeText($waNumber, $notif['text']);
                     
                     $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                     $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null);
                     
                     // Update state immediately
                     $updateData = ['state' => $status];
                     if ($msgId) {
                         $updateData['id_api'] = $msgId;
                     }
                     
                     $db1->update('notif', $updateData, ['id_notif' => $notif['id_notif']]);
                     
                     \Log::write("Resent Notif ID {$notif['id_notif']} -> $status", 'webhook', 'WhatsApp');
                 }
            }

        } catch (\Exception $e) {
            \Log::write("Error processing pending notifs: " . $e->getMessage(), 'webhook', 'WhatsApp');
        }

        // Step 2: Get or create conversation
        $conversationId = $this->getOrCreateConversation($db, $customerId, $waNumber, $contactName);

        // Step 3: Extract message content
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

        // Step 4: Save message to wa_messages_in
        $messageData = [
            'conversation_id' => $conversationId,
            'customer_id' => $customerId,
            'phone' => $waNumber,
            'type' => $messageType,
            'text' => $textBody,
            'media_id' => $mediaId,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'media_caption' => $mediaCaption,
            'message_id' => $messageId,
            'wamid' => $wamid,
            'contact_name' => $contactName,
            'status' => $status,
            'received_at' => $sendTime
        ];

        \Log::write("Attempting to insert inbound message: Conv=$conversationId, Cust=$customerId, Type=$messageType", 'webhook', 'WhatsApp');
        
        $msgId = $db->insert('wa_messages_in', $messageData);

        if ($msgId) {
            \Log::write("✓ Inbound message saved: ID=$msgId, Cust=$customerId, Conv=$conversationId, From=$waNumber", 'webhook', 'WhatsApp');
            
            // Step 5: Update conversation last_in_at
            $db->update('wa_conversations', ['last_in_at' => $sendTime], ['id' => $conversationId]);
        } else {
            $error = $db->conn()->error;
            \Log::write("✗ DB ERROR (insert inbound message): $error", 'webhook', 'WhatsApp');
            \Log::write("Data attempted: " . json_encode($messageData), 'webhook', 'WhatsApp');
        }
    }

    /**
     * Update or create customer record
     * This tracks last_message_at for 24h window rule
     */
    private function updateOrCreateCustomer($db, $waNumber, $contactName, $messageTime)
    {
        \Log::write("updateOrCreateCustomer: Number=$waNumber, Name=$contactName", 'webhook', 'WhatsApp');
        
        // Try to find existing customer
        $existing = $db->get_where('wa_customers', ['wa_number' => $waNumber]);
        
        if ($existing->num_rows() > 0) {
            $customer = $existing->row();
            
            // Update existing customer
            $updateData = [
                'last_message_at' => $messageTime,
                'total_messages' => $customer->total_messages + 1
            ];
            
            // Update contact name if changed
            if ($contactName && $contactName !== $customer->contact_name) {
                $updateData['contact_name'] = $contactName;
            }
            
            $updated = $db->update('wa_customers', $updateData, ['id' => $customer->id]);
            
            if ($updated) {
                \Log::write("✓ Customer updated: ID={$customer->id}, Last message at: $messageTime", 'webhook', 'WhatsApp');
            } else {
                $error = $db->conn()->error;
                \Log::write("✗ Customer update failed: $error", 'webhook', 'WhatsApp');
            }
            
            return $customer->id;
        }

        // Create new customer
        $customerData = [
            'wa_number' => $waNumber,
            'contact_name' => $contactName,
            'last_message_at' => $messageTime,
            'first_contact_at' => $messageTime,
            'total_messages' => 1,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $customerId = $db->insert('wa_customers', $customerData);
        
        if ($customerId) {
            \Log::write("✓ New customer created: ID=$customerId, Number=$waNumber", 'webhook', 'WhatsApp');
        } else {
            $error = $db->conn()->error;
            \Log::write("✗ Customer insert failed: $error", 'webhook', 'WhatsApp');
            \Log::write("Data: " . json_encode($customerData), 'webhook', 'WhatsApp');
        }
        
        return $customerId;
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
        $messageId = $statusUpdate['id'] ?? null; // YCloud Message ID
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

            // Update notif table in db(1)
            // id_api is likely the YCloud Message ID, not wamid
            $db1 = $this->db(1);
            if ($messageId) {
                $db1->update('notif', ['state' => $status], ['id_api' => $messageId]);
            } elseif ($wamid) {
                $db1->update('notif', ['state' => $status], ['id_api' => $wamid]);
            }
        } else {
            \Log::write("⚠ Message not found for status update: $wamid", 'webhook', 'WhatsApp');
        }
    }



    /**
     * Handle message updated event (for read, delivered, sent status)
     * This is for OUTBOUND messages (yang kita kirim)
     */
    private function handleMessageUpdated($db, $data)
    {
        $message = $data['whatsappMessage'] ?? [];
        if (empty($message)) {
            \Log::write("ERROR: No whatsappMessage in message.updated event", 'webhook', 'WhatsApp');
            return;
        }

        $wamid = $message['wamid'] ?? null;
        $messageId = $message['id'] ?? null; // Provider message ID
        $status = $message['status'] ?? null;

        if (!$wamid && !$messageId) {
            \Log::write("ERROR: No wamid or message_id in message.updated event", 'webhook', 'WhatsApp');
            return;
        }

        // Build update data based on available fields
        $updateData = [
            'status' => $status
        ];

        // Add wamid if we have it (might be first time getting wamid from webhook)
        if ($wamid) {
            $updateData['wamid'] = $wamid;
        }

        // Add timestamps if available
        if (isset($message['sendTime'])) {
            $updateData['sent_at'] = $this->convertTime($message['sendTime']);
        }
        if (isset($message['deliverTime'])) {
            $updateData['delivered_at'] = $this->convertTime($message['deliverTime']);
        }
        if (isset($message['readTime'])) {
            $updateData['read_at'] = $this->convertTime($message['readTime']);
        }

        $updated = false;

        // CRITICAL FIX: Try to update by message_id FIRST (This is the most reliable anchor)
        if ($messageId) {
            $updated = $db->update('wa_messages_out', $updateData, ['message_id' => $messageId]);
        }

        // If not updated (or no messageId), try by wamid as fallback
        if (!$updated && $wamid) {
            $updated = $db->update('wa_messages_out', $updateData, ['wamid' => $wamid]);
        }

        if ($updated) {
            \Log::write("✓ Outbound message updated: {$messageId} -> $status", 'webhook', 'WhatsApp');
        } else {
            \Log::write("⚠ Outbound message not found: wamid=$wamid, id=$messageId", 'webhook', 'WhatsApp');
        }
    }

    /**
     * Get existing conversation or create new one
     */
    private function getOrCreateConversation($db, $customerId, $waNumber, $contactName = null)
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
            'customer_id' => $customerId,
            'wa_number' => $waNumber,
            'contact_name' => $contactName,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $db->insert('wa_conversations', $convData);
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

    /**
     * Normalize phone number to +62 format
     */
    private function normalizePhoneNumber($phone)
    {
        if (!$phone) return null;
        
        // Remove non-numeric except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Handle 08... -> +628...
        if (substr($phone, 0, 1) === '0') {
            return '+62' . substr($phone, 1);
        }
        
        // Handle 628... -> +628...
        if (substr($phone, 0, 2) === '62') {
            return '+' . $phone;
        }
        
        // Handle 8... -> +628... (just in case)
        if (substr($phone, 0, 1) === '8') {
            return '+62' . $phone;
        }

        // If starts with +, return it
        if (substr($phone, 0, 1) === '+') {
            return $phone;
        }

        // Default: add +
        return '+' . $phone;
    }
}
