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
        
        // Extract context (quoted/reply-to message)
        $quotedMessageId = null;
        if (isset($msg['context']['message_id'])) {
            $quotedMessageId = $msg['context']['message_id'];
        } elseif (isset($msg['context']['id'])) {
            $quotedMessageId = $msg['context']['id'];
        }

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
            
            $phoneNoPrefix = substr($cleanPhone, 2);
            $phones = ["'$cleanPhone'", "'$phone0'", "'$phonePlus'", "'$phoneNoPrefix'"];
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
            } elseif ($messageType === 'reaction') {
                // Extract reaction emoji
                $reactionEmoji = $msg['reaction']['emoji'] ?? null;
                $messageText = $reactionEmoji ? "Reacted $reactionEmoji" : "Removed reaction";
            } elseif ($messageType === 'location') {
                // Extract location name/address for preview
                $locName = $msg['location']['name'] ?? null;
                $locAddr = $msg['location']['address'] ?? null;
                $messageText = '📍 ' . ($locName ?: ($locAddr ?: 'Shared Location'));
            } elseif (isset($msg[$messageType]['caption'])) {
                $messageText = $msg[$messageType]['caption'];
            }
            
            // Build lastMessageSummary
            $lastMessageSummary = $messageText;
            if (empty($lastMessageSummary) && $messageType !== 'text') {
                // Use emoji for better UX
                $typeLabels = [
                    'image' => '📷 Image',
                    'video' => '🎥 Video',
                    'audio' => '🎵 Audio',
                    'voice' => '🎤 Voice',
                    'document' => '📄 Document',
                    'sticker' => '🎨 Sticker',
                    'location' => '📍 Location',
                ];
                 $lastMessageSummary = $typeLabels[$messageType] ?? "[$messageType]";
            }

        } catch (\Exception $e) {
            \Log::write("Error processing user data: " . $e->getMessage(), 'webhook', 'WhatsApp');
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
            
            case 'reaction':
                // Handle reaction (emoji react to a message)
                $reactionEmoji = $msg['reaction']['emoji'] ?? null;
                $reactionMessageId = $msg['reaction']['message_id'] ?? null;
                
                if ($reactionEmoji) {
                    $textBody = "Reacted: $reactionEmoji";
                } else {
                    $textBody = "Removed reaction"; // Unreact
                }
                
                // Store reaction metadata in media fields (creative reuse)
                $mediaCaption = $reactionMessageId; // Store which message was reacted to
                break;

            case 'image':
                // Process image (no verbose log)
            case 'video':
            case 'audio':
            case 'document':
            case 'voice':
            case 'sticker':
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
            
            case 'location':
                // Handle location message
                $latitude = $msg['location']['latitude'] ?? null;
                $longitude = $msg['location']['longitude'] ?? null;
                $locationName = $msg['location']['name'] ?? null;
                $locationAddress = $msg['location']['address'] ?? null;
                
                // Build text representation of location
                $locationParts = [];
                if ($locationName) $locationParts[] = $locationName;
                if ($locationAddress) $locationParts[] = $locationAddress;
                
                $locationLabel = !empty($locationParts) ? implode(', ', $locationParts) : 'Shared Location';
                $textBody = "📍 $locationLabel";
                
                // Store coordinates in media_url as Google Maps link for easy access
                if ($latitude && $longitude) {
                    $mediaUrl = "https://maps.google.com/maps?q={$latitude},{$longitude}";
                    $mediaCaption = "{$latitude},{$longitude}"; // Raw coordinates
                }
                break;
        }

        // Step 4: Save message to wa_messages_in

        $messageData = [
            'phone' => $waNumber,
            'type' => $messageType,
            'text' => $textBody,
            'media_id' => $mediaId,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'media_caption' => $mediaCaption,
            'message_id' => $messageId,
            'wamid' => $wamid,
            'quoted_message_id' => $quotedMessageId, // Reply-to message reference
            'contact_name' => $contact_name,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        

        $msgId = $db->insert('wa_messages_in', $messageData);

        if (!$msgId) {
            $error = $db->conn()->error;
            $errno = $db->conn()->errno;
            \Log::write("✗ DB INSERT FAILED - Error ($errno): $error", 'webhook', 'inbound_error');
            \Log::write("Failed data: " . json_encode($messageData), 'webhook', 'inbound_error');
            \Log::write("Table: wa_messages_in", 'webhook', 'inbound_error');
        } else {
            // Auto Reply Processed Here (Async-ish)
            try {

                if (!class_exists('\\App\\Models\\WAReplies')) {
                    require_once __DIR__ . '/../../Models/WAReplies.php';
                }
                $autoReplyResult = (new \App\Models\WAReplies())->process($phoneIn, $messageText, $waNumber);
                
                // Extract values from result object
                $currentCase = $autoReplyResult->case;
                $autoReplied = $autoReplyResult->auto_replied ?? false;
                if ($currentCase === 0){
                    $currentCase = null;
                }
                
                // Case logic based on customer registration status
                // If customer registered -> keep auto-reply case or set to 0
                if($code === null){
                    $currentCase = 0;
                }
                
                // Get or create conversation with all updates in one call
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, 
                    $waNumber, 
                    $contact_name, 
                    $assigned_user_id, 
                    $code, 
                    $lastMessageSummary,
                    $currentCase
                );

                // DIAGNOSTIC LOG: Use Standard Log Class so it appears in logs/DATE folder
                // File will be: api/logs/{DATE}/wa_ws_debug_check.log
                if (class_exists('\Log')) {
                    \Log::write("Code: " . var_export($code, true) . " | AI Case: " . var_export($currentCase, true) . " | AutoReplied: " . ($autoReplied ? 'true' : 'false'), 'wa_ws_debug', 'check');
                }
                
                // Fetch active cases specific for Notification Logic (Driver needs to know if Case 2 active)
                $activeCases = [];
                $freshConv = $db->get_where('wa_conversations', ['id' => $conversationId])->row();
                if ($freshConv && !empty($freshConv->conv_case)) {
                    $casesDecoded = json_decode($freshConv->conv_case, true);
                    if (is_array($casesDecoded)) {
                         // Normalize List vs Object
                         if (!isset($casesDecoded[0])) { $casesDecoded = [$casesDecoded]; }
                         
                         foreach ($casesDecoded as $c) {
                             if (isset($c['case']) && isset($c['status']) && $c['status'] === 'open') {
                                 $activeCases[] = (int)$c['case'];
                             }
                         }
                    } else if (is_numeric($freshConv->conv_case)) {
                         // Legacy single int
                         $activeCases[] = (int)$freshConv->conv_case;
                    }
                }

                $this->pushIncomingToWebSocket([
                    'conversation_id' => $conversationId,
                    'phone' => $waNumber,
                    'contact_name' => $contact_name,
                    'case' => $currentCase, 
                    'active_cases' => $activeCases, // Send active cases list
                    'auto_replied' => $autoReplied, // Flag for push notification logic
                    'message' => [
                        'id' => $msgId, // local DB ID
                        'text' => $textBody,
                        'type' => $messageType,
                        'media_id' => $mediaId,
                        'media_url' => $mediaUrl,
                        'caption' => $mediaCaption,
                        'quoted_message_id' => $quotedMessageId, // Reply-to reference
                        'time' => date('Y-m-d H:i:s'),
                    ],
                    // Target ID logic: if assigned, send to agent. Else '0' (Broadcast)? 
                    // Using '0' guarantees it pops up for everyone (Realtime solution)
                    // But let's stick to original logic: if assigned, target specific.
                    'target_id' => $assigned_user_id ? (string)$assigned_user_id : '0',
                    'kode_cabang' => $code
                ]);
                

            } catch (\Exception $e) {
                \Log::write("✗ Error in auto-reply/conversation: " . $e->getMessage(), 'webhook', 'error');
                \Log::write("Stack trace: " . $e->getTraceAsString(), 'webhook', 'error');
            }
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
     * Get existing conversation or create new one with case update
     * This combines conversation creation/update with case in one DB operation
     */
    private function getOrCreateConversationWithCase($db, $waNumber, $contactName = null, $assigned_user_id = null, $code = null, $lastMessage = null, $case = null)
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
                'updated_at' => date('Y-m-d H:i:s'),
                'last_message' => 'i- ' . mb_substr($lastMessage, 0, 50),
            ];
            
            // Only update case if not null (Append to existing list)
            if ($case !== null) {
                $caseList = [];
                
                // 1. Retrieve & Decode existing content
                if (!empty($conv->conv_case)) {
                    $decoded = json_decode($conv->conv_case, true);
                    
                    if (is_array($decoded)) {
                        // Check if it's already a List (Numerical keys) or Single Object (Assoc)
                        // If keys are 0,1,2... it's a list. If 'case','status'... it's an object.
                        $isList = isset($decoded[0]);
                        
                        if ($isList) {
                            $caseList = $decoded;
                        } else {
                            // Convert single legacy object to list
                            if (!empty($decoded)) {
                                $caseList[] = $decoded;
                            }
                        }
                    } elseif (is_numeric($conv->conv_case)) {
                        // Handle strict legacy integer data
                        $caseList[] = ['case' => (int)$conv->conv_case, 'status' => 'unknown'];
                    }
                }
                
                // 2. Check if this case already exists (OPEN OR CLOSED)
                $caseExists = false;
                
                // NEW: Check if there are other open cases (for Case 4 logic)
                $hasOtherOpenCases = false;
                foreach ($caseList as $c) {
                    if (isset($c['case']) && (int)$c['case'] !== 4 && ($c['status'] ?? '') === 'open') {
                        $hasOtherOpenCases = true;
                        break;
                    }
                }
                
                // NEW RULE: If trying to add/open Case 4 but other cases are open, SKIP
                if ((int)$case === 4 && $hasOtherOpenCases) {
                    // Don't add or update Case 4 - just skip case update entirely
                    // But still update other fields (contact_name, last_message, etc)
                } else {
                    // Normal case processing
                    foreach ($caseList as &$existingCase) {
                        if (isset($existingCase['case']) && (int)$existingCase['case'] === (int)$case) {
                            // UPDATE existing case status to open
                            $existingCase['status'] = 'open';
                            
                            // Clean up extra fields (no history/metadata needed)
                            if(isset($existingCase['timestamp'])) unset($existingCase['timestamp']);
                            if(isset($existingCase['resolved_at'])) unset($existingCase['resolved_at']);
                            if(isset($existingCase['resolved_by'])) unset($existingCase['resolved_by']);
                            
                            $caseExists = true;
                            break;
                        }
                    }
                    unset($existingCase); 
                    
                    // 3. Only append if case doesn't exist
                    if (!$caseExists) {
                        $caseList[] = [
                            'case' => $case,
                            'status' => 'open'
                        ];
                    }
                    
                    // RULE: If updating any case OTHER than 4, auto-close Case 4 (Follow Up)
                    if ((int)$case !== 4) {
                        foreach ($caseList as &$c) {
                            if (isset($c['case']) && (int)$c['case'] === 4) {
                                $c['status'] = 'closed';
                                // Cleanup any extra fields
                                if(isset($c['timestamp'])) unset($c['timestamp']);
                                if(isset($c['resolved_at'])) unset($c['resolved_at']);
                                if(isset($c['resolved_by'])) unset($c['resolved_by']);
                            }
                        }
                        unset($c);
                    }
                    
                    $updateData['conv_case'] = json_encode($caseList);
                }
            }

            
            $db->update('wa_conversations', 
                $updateData, 
                ['wa_number' => $waNumber]
            );
            
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
            'last_in_at' => date('Y-m-d H:i:s'),
            'last_message_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'last_message' => 'i- ' . mb_substr($lastMessage, 0, 50),
        ];
        
        // Only set case if not null (Store as JSON List)
        if ($case !== null) {
            // Initialize as Array containing the first case
            $convData['conv_case'] = json_encode([[
                'case' => $case,
                'status' => 'open',
                'timestamp' => date('Y-m-d H:i:s')
            ]]);
        }

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
