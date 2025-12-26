<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * YCloud WhatsApp Webhook Handler
 * Updated to use new 3-table structure:
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
        $verifyToken = \Env::WA_VERIFY_TOKEN;

        if ($mode === 'subscribe' && $token === $verifyToken) {
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


        // Process based on event type
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
     * Handle inbound message from customer
     */
    private function handleInboundMessage($db, $data)
    {
        $msg = $data['whatsappInboundMessage'] ?? [];
        
        // DEBUG LOG: Inbound Message Start
        if (class_exists('\Log')) {
             \Log::write("INBOUND MSG: " . json_encode($msg), 'wa_inbound_debug', 'start');
        }

        $textBodyToCheck = $msg['text']['body'] ?? '';
        
        if (empty($msg)) {
            \Log::write("ERROR: No whatsappInboundMessage", 'wa_inbound', 'error');
            return;
        }

        $waNumber = $this->normalizePhoneNumber($msg['from'] ?? null);
        $contactName = $msg['customerProfile']['name'] ?? null;
        $messageType = $msg['type'] ?? 'text';
        $messageId = $msg['id'] ?? null;
        $wamid = $msg['wamid'] ?? null;
        $status = $msg['status'] ?? 'received'; // Default status for inbound
        $sendTime = date('Y-m-d H:i:s');

        if (!$waNumber) {
            \Log::write("ERROR: No 'from' number", 'wa_inbound', 'error');
            return;
        }

        // IDEMPOTENCY CHECK: Prevent duplicate processing of the same message
        if ($messageId) {
            $dupe = $db->get_where('wa_messages_in', ['message_id' => $messageId])->row();
            if ($dupe) {
                if ($messageType === 'button') {
                    \Log::write("SKIP: Duplicate button message $messageId", 'wa_inbound', 'debug');
                }
                return;
            }
        }

        try {
            $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber); // 628...
            $phone0 = '0' . substr($cleanPhone, 2); // 08...
            $phonePlus = '+' . $cleanPhone; // +62...
            
            $phones = ["'$cleanPhone'", "'$phone0'", "'$phonePlus'"];
            $phoneIn = implode(',', $phones);

            //cari assigned_user_id
            $user_data = $this->getUserData($phone0);
            $assigned_user_id = $user_data->assigned_user_id ?? null;
            $code = $user_data->code ?? null;
            $contact_name = $user_data->customer_name ?? $cleanPhone;
            
            // Extract message text EARLY for lastMessageSummary
            $messageText = '';
            if ($messageType === 'text') {
                $messageText = $msg['text']['body'] ?? '';
            } elseif ($messageType === 'button') {
                $messageText = $msg['button']['text'] ?? ($msg['button']['payload'] ?? '');
            } elseif ($messageType === 'interactive') {
                if (isset($msg['interactive']['button_reply'])) {
                    $messageText = $msg['interactive']['button_reply']['title'] ?? '';
                } elseif (isset($msg['interactive']['list_reply'])) {
                    $messageText = $msg['interactive']['list_reply']['title'] ?? '';
                }
            } elseif (isset($msg[$messageType]['caption'])) {
                $messageText = $msg[$messageType]['caption'];
            }
            
            // Build lastMessageSummary
            $lastMessageSummary = $messageText;
            if (empty($lastMessageSummary) && $messageType !== 'text') {
                 $lastMessageSummary = "[$messageType]";
            }

            $conversationId = $this->getOrCreateConversation($db, $waNumber, $contact_name, $assigned_user_id, $code, $lastMessageSummary);
        } catch (\Exception $e) {
            \Log::write("Error processing pending notifs: " . $e->getMessage(), 'webhook', 'WhatsApp');
        }

        $textBody = null;
        $mediaId = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaUrlDirect = null;
        $mediaCaption = null;
        
        // Initialize Metadata
        $messageId = $msg['id'] ?? null;
        $wamid = $msg['wamid'] ?? null;
        $status = 'unread'; // Default status for new messages

        switch ($messageType) {
            case 'text':
                $textBody = $msg['text']['body'] ?? null;
                break;
            
            case 'button':
                // Extract text from button response
                $textBody = $msg['button']['text'] ?? ($msg['button']['payload'] ?? null);
                break;
            
            case 'interactive':
                // Handle interactive message (list reply, button reply)
                if (isset($msg['interactive']['button_reply'])) {
                    $textBody = $msg['interactive']['button_reply']['title'] ?? null;
                } elseif (isset($msg['interactive']['list_reply'])) {
                    $textBody = $msg['interactive']['list_reply']['title'] ?? null;
                }
                break;

            case 'image':
                // Process image (no verbose log)
            case 'video':
            case 'audio':
            case 'document':
            case 'voice':
                $mediaId = $msg[$messageType]['id'] ?? null;
                $mediaMimeType = $msg[$messageType]['mimeType'] ?? $msg[$messageType]['mime_type'] ?? null;
                $mediaUrlDirect = $msg[$messageType]['link'] ?? null;
                $mediaCaption = $msg[$messageType]['caption'] ?? null;
                
                // Auto Download Media to Local Server
                if ($mediaId || $mediaUrlDirect) {
                    try {
                        if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                            require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
                        }
                        $waService = new \App\Helpers\WhatsAppService();
                        $savedUrl = $waService->downloadAndSaveMedia($mediaId, $mediaUrlDirect, $mediaMimeType);
                        if ($savedUrl) {
                            $mediaUrl = $savedUrl;
                        } else {
                            // Download failed but don't block message save
                            \Log::write("Media download failed for ID: $mediaId, using direct URL fallback", 'webhook', 'WhatsApp');
                            $mediaUrl = $mediaUrlDirect; // Use direct URL as fallback
                        }
                    } catch (\Throwable $e) {
                        // Catch ANY error (including PHP 8 errors) and continue
                        \Log::write("Media download exception: " . $e->getMessage(), 'webhook', 'WhatsApp');
                        $mediaUrl = $mediaUrlDirect; // Use direct URL as fallback
                    }
                }
                break;
        }

        // Step 4: Save message to wa_messages_in
        $messageData = [
            // 'conversation_id' => $conversationId, // REMOVED: Table/Field deleted by user
            // 'customer_id' => $customerId, // REMOVED: Table/Field deleted by user
            'phone' => $waNumber,
            'type' => $messageType,
            'text' => $textBody,
            'media_id' => $mediaId,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'media_caption' => $mediaCaption,
            'message_id' => $messageId,
            'wamid' => $wamid,
            'contact_name' => $contact_name,
            'status' => $status,
        ];
        
        $msgId = $db->insert('wa_messages_in', $messageData);

        if (!$msgId) {
            $error = $db->conn()->error;
            \Log::write("✗ DB ERROR (insert inbound message): $error", 'webhook', 'WhatsApp');
            \Log::write("Data attempted: " . json_encode($messageData), 'webhook', 'WhatsApp');
        } else {
            // Auto Reply Processed Here (After DB Save)
            $currentPriority = 0; // Default priority
            try {
                if (!class_exists('\\App\\Models\\WAReplies')) {
                    require_once __DIR__ . '/../../Models/WAReplies.php';
                }
                $autoReplyTriggered = (new \App\Models\WAReplies())->process($phoneIn, $messageText, $waNumber);
                
                if ($autoReplyTriggered) {                    
                    // Update message status to 'read' since it was processed by auto-reply
                    $updated = $db->update('wa_messages_in', 
                        ['status' => 'read'], 
                        ['id' => $msgId]
                    );
                } else {
                    // No keyword match - needs CS attention, but only if customer is identified
                    if (!empty($code)) {
                        $currentPriority = 4; // High priority, needs CS
                        $db->update('wa_conversations', 
                            ['priority' => $currentPriority], 
                            ['wa_number' => $waNumber]
                        );
                    } else {
                         $currentPriority = 0; 
                    }
                }
            } catch (\Exception $e) {
                \Log::write("Error processing auto-reply: " . $e->getMessage(), 'webhook', 'WhatsApp');
            }
            
            // Push to WebSocket Server AFTER priority is determined
            $this->pushIncomingToWebSocket([
                'conversation_id' => $conversationId,
                'phone' => $waNumber,
                'contact_name' => $contact_name,
                'priority' => $currentPriority, // ✅ Include priority!
                'message' => [
                    'id' => $msgId, // local DB ID
                    'text' => $textBody,
                    'type' => $messageType,
                    'media_id' => $mediaId,
                    'media_url' => $mediaUrl,
                    'caption' => $mediaCaption,
                    'time' => date('Y-m-d H:i:s'),
                ],
                'target_id' => $assigned_user_id ? (string)$assigned_user_id : '0',
                'kode_cabang' => $code
            ]);
        }
    }

    /**
     * Push incoming message to Node.js WebSocket Server
     */
    private function pushIncomingToWebSocket($data)
    {
        $url = 'https://waserver.nalju.com/incoming';
        
        // DEBUG LOG: WS Push Start
        if (class_exists('\Log')) {
             \Log::write("WS PUSH START: " . json_encode($data), 'wa_ws_debug', 'push');
        }
        
        // Use curl to post
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Fast timeout, don't block php
        
        $result = curl_exec($ch);
        
        // DEBUG LOG: WS Push Result
        if (class_exists('\Log')) {
             if (curl_errno($ch)) {
                  \Log::write("WS PUSH ERROR: " . curl_error($ch), 'wa_ws_debug', 'error');
             } else {
                  \Log::write("WS PUSH RESULT: " . $result, 'wa_ws_debug', 'result');
             }
        }
        
        curl_close($ch);
        
        return $result;
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

        $updated = $db->update('wa_messages_out', $updateData, ['wamid' => $wamid]);
        
        // Also check if in wa_messages_out (sometimes stored there differently?) - actually handled in handleMessageUpdated for out
        // Wait, handleStatusUpdate is generally for OUTBOUND messages status from YCloud (sent, delivered, read)
        // But wa_messages is legacy? Or unified?
        // Let's assume handleMessageUpdated is the main one for OUTBOUND.
        
        if ($updated) {
            // \Log::write("✓ Status updated: $wamid -> $status", 'webhook', 'WhatsApp');

            // Find phone logic for frontend
            $msg = $db->query("SELECT phone, id FROM wa_messages_out WHERE wamid = '$wamid'")->row();
            if ($msg) {
                // Get assigned_user_id
                $conv = $db->get_where('wa_conversations', ['wa_number' => $msg->phone])->row();
                $targetId = $conv && $conv->assigned_user_id ? (string)$conv->assigned_user_id : '0';

                $this->pushIncomingToWebSocket([
                    'type' => 'status_update',
                    'phone' => $msg->phone,
                    'conversation_id' => $conv->id ?? 0,
                    'message' => [
                        'id' => $msg->id,
                        'wamid' => $wamid,
                        'status' => $status
                    ],
                    'target_id' => $targetId
                ]);
            }
            
            // Update notif table logic...
            // id_api is likely the YCloud Message ID, not wamid
            $db1 = $this->db(1);
            if ($messageId) {
                $db1->update('notif', ['state' => $status], ['id_api' => $messageId]);
            } elseif ($wamid) {
                $db1->update('notif', ['state' => $status], ['id_api' => $wamid]);
            }
        } else {
            // Message not found (no log - this can happen normally)
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
            // \Log::write("✓ Outbound message updated: wamid=$wamid, id=$messageId, status=$status", 'webhook', 'WhatsApp');
            
            // Fetch phone and local ID for WebSocket push
            $checkSql = "SELECT id, phone FROM wa_messages_out WHERE "; // Changed from conversation_id to phone
            $params = [];
            if ($messageId) {
                $checkSql .= "message_id = ?";
                $params[] = $messageId;
            } elseif ($wamid) {
                $checkSql .= "wamid = ?";
                $params[] = $wamid;
            }
            
            $msg = $db->query($checkSql, $params)->row();
            
            if ($msg) {
                // Get assigned_user_id
                $conv = $db->get_where('wa_conversations', ['wa_number' => $msg->phone])->row();
                $targetId = $conv && $conv->assigned_user_id ? (string)$conv->assigned_user_id : '0';

                $this->pushIncomingToWebSocket([
                    'type' => 'status_update',
                    'phone' => $msg->phone,
                    'conversation_id' => $conv->id ?? 0,
                    'message' => [
                        'id' => $msg->id, // Local DB ID
                        'status' => $status
                    ],
                    'target_id' => $targetId
                ]);
                
                // Update notif table state
                $db1 = $this->db(1);
                if ($messageId) {
                    $db1->update('notif', ['state' => $status], ['id_api' => $messageId]);
                } elseif ($wamid) {
                    $db1->update('notif', ['state' => $status], ['id_api' => $wamid]);
                }
            }

        } else {
            // Outbound message not found (no log - this can happen normally)
        }
    }

    /**
     * Get existing conversation or create new one
     */
    private function getOrCreateConversation($db, $waNumber, $contactName = null, $assigned_user_id = null, $code = null, $lastMessage = null)
    {
        // Try to find existing conversation
        $existing = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
        
        if ($existing->num_rows() > 0) {
            $conv = $existing->row();           
            $updateData = [
                'contact_name' => $contactName,
                'assigned_user_id' => $assigned_user_id,
                'code' => $code,
                'status' => 'open',
                'last_in_at' => date('Y-m-d H:i:s'),
                'last_message_at' => date('Y-m-d H:i:s'),
                'last_message' => $lastMessage,
            ];
            $db->update('wa_conversations', 
                $updateData, 
                ['wa_number' => $waNumber] // Link by wa_number
            );
            
            // Mark all previous messages as read using phone
            $db->update('wa_messages_in', ['status' => 'read'], ['phone' => $waNumber]);
            
            return $conv->id ?? 0;
        }

        // Create new conversation
        $convData = [
            'assigned_user_id' => $assigned_user_id,
            'wa_number' => $waNumber,
            'contact_name' => $contactName,
            'code' => $code,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
            'last_message_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'last_message' => $lastMessage,
        ];

        if($db->insert('wa_conversations', $convData)) {
             return $db->insert_id();
        }
        return 0;
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

    function getUserData($phone0)
    {
        $db = $this->db(1);
        $return = new \stdClass();
        
        // cek nomor di data pelanggan limit 1 order by updated_at desc
        $customer = $db->query("SELECT * FROM pelanggan WHERE nomor_pelanggan LIKE '%" . substr($phone0, 2) . "%' ORDER BY updated_at DESC LIMIT 1")->row();
        
        if ($customer) {
            $return->customer_name = $customer->nama_pelanggan;
        } else {
            return null;
        }

        $last_sale = $db->query("SELECT * FROM sale WHERE id_pelanggan = " . $customer->id_pelanggan . " ORDER BY insertTime DESC LIMIT 1")->row();
        if ($last_sale) {
            $return->assigned_user_id = $last_sale->id_cabang;
            
            // Get kode_cabang for this id_cabang
            $cabang = $db->query("SELECT kode_cabang FROM cabang WHERE id_cabang = " . $last_sale->id_cabang)->row();
            if ($cabang) {
                $return->code = $cabang->kode_cabang;
            }
        } else {
            return null;
        }

        return $return;
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
