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
    private const DEFAULT_FALLBACK_REPLY_YCLOUD = "Maaf, mohon menunggu. CS sedang melayani customer lain.\n\nUntuk balasan otomatis, silahkan ketik:\n- *BON* untuk info nota\n- *CEK* untuk info status\n- *BILL* untuk info tagihan\n\nUntuk pengaduan, kirimkan pesan ke *Madinah Laundry (Admin)*\n💬 wa.me/628117686252";

    /** Cooldown wa_auto_reply_log (handler DEFAULT, menyatu yCloud+Fonnte) sebelum fallback dikirim lagi ke nomor yang sama — 24 jam */
    private const DEFAULT_FALLBACK_COOLDOWN_MINUTES = 1440;

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

        \Log::write("Verification FAILED", 'wa_error', 'Webhook');
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
            \Log::write("Invalid JSON", 'wa_error', 'Webhook');
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
                    \Log::write("Unknown event: $eventType", 'wa_error', 'Webhook');
            }
        } catch (\Exception $e) {
            \Log::write("EXCEPTION: " . $e->getMessage(), 'wa_error', 'Webhook');
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
        
        // DEBUG LOG removed for performance

        $textBodyToCheck = $msg['text']['body'] ?? '';
        
        if (empty($msg)) {
            \Log::write("No whatsappInboundMessage", 'wa_error', 'Webhook');
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
        $quotedMessageBody = null;
        $quotedMessageFrom = null;
        
        if (isset($msg['context'])) {
            // Extract quoted message ID
            if (isset($msg['context']['message_id'])) {
                $quotedMessageId = $msg['context']['message_id'];
            } elseif (isset($msg['context']['id'])) {
                $quotedMessageId = $msg['context']['id'];
            }
            
            // Extract quoted message sender (from field in context)
            if (isset($msg['context']['from'])) {
                $quotedMessageFrom = $msg['context']['from'];
            }
            
            // Try to get quoted message content from database
            if ($quotedMessageId) {
                try {
                    // Try from wa_messages_in first
                    $result = $db->get_where('wa_messages_in', ['wamid' => $quotedMessageId]);
                    if ($result && $result->num_rows() > 0) {
                        $quotedMsg = $result->row();
                        $quotedMessageBody = $quotedMsg->text ?? $quotedMsg->media_caption ?? null;
                    } else {
                        // Try from wa_messages_out
                        $result = $db->get_where('wa_messages_out', ['wamid' => $quotedMessageId]);
                        if ($result && $result->num_rows() > 0) {
                            $quotedMsg = $result->row();
                            $quotedMessageBody = $quotedMsg->content ?? null;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::write("ERROR fetching quoted message - WAMID: $quotedMessageId | " . $e->getMessage(), 'wa_error', 'Quote');
                }
            }
        }

        if (!$waNumber) {
            \Log::write("No 'from' number", 'wa_error', 'Webhook');
            return;
        }

        // IDEMPOTENCY CHECK: Prevent duplicate processing of the same message
        if ($messageId) {
            $dupe = $db->get_where('wa_messages_in', ['message_id' => $messageId])->row();
            if ($dupe) {
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
            $assigned_user_id = $user_data ? ($user_data->assigned_user_id ?? null) : null;
            $code = $user_data ? ($user_data->code ?? null) : null;
            $cust_id = $user_data ? ($user_data->cust_id ?? null) : null;
            $contact_name = $user_data ? ($user_data->customer_name ?? $contactName) : $contactName;
            

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
            
            // Check if message is private (for last_message formatting, uses Env::WA_PRIVATE_WORDS)
            // IMPORTANT: Use \Env (global namespace) - class_exists('Env') in namespaced file looks for App\Controllers\Webhook\Env!
            $isPrivateForLastMessage = false;
            try {
                if (class_exists('\Env', false)) {
                    $isPrivateForLastMessage = \Env::textContainsPrivateWord($lastMessageSummary ?? '');
                }
            } catch (\Throwable $e) {
                // Jangan gagalkan simpan chat jika cek private error
            }

        } catch (\Exception $e) {
            \Log::write("Error processing user data: " . $e->getMessage(), 'wa_error', 'Webhook');
        }

        if (!isset($isPrivateForLastMessage)) {
            $isPrivateForLastMessage = false;
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
                            \Log::write("Media download failed for ID: $mediaId", 'wa_error', 'Webhook');
                            $mediaUrl = $mediaUrlDirect; // Use direct URL as fallback
                        }
                    } catch (\Throwable $e) {
                        // Catch ANY error (including PHP 8 errors) and continue
                        \Log::write("Media download exception: " . $e->getMessage(), 'wa_error', 'Webhook');
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
            'contact_name' => $contact_name,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        // Add quoted message fields only if they exist (safe for backward compatibility)
        // This prevents DB error if columns haven't been added yet
        if ($quotedMessageId !== null) {
            $messageData['quoted_message_id'] = $quotedMessageId;
        }
        if ($quotedMessageBody !== null) {
            $messageData['quoted_message_body'] = $quotedMessageBody;
        }

        $msgId = $db->insert('wa_messages_in', $messageData);

        if (!$msgId) {
            $error = $db->conn()->error;
            $errno = $db->conn()->errno;
            \Log::write("DB INSERT FAILED wa_messages_in - Error ($errno): $error | Data: " . json_encode($messageData), 'wa_error', 'Inbound');
        } else {
            $currentCase = null;
            $conversationId = 0;
            $activeCases = [];
            $notify = true;

            try {
                // ========================================
                // 🚀 BRILLIANT ARCHITECTURE (User's Idea!)
                // ========================================
                // Everything happens in WAReplies->process():
                // 1. Detect intent/pattern
                // 2. Create/update conversation (inbound message)
                // 3. Call handler method (send auto-reply)
                // 4. Auto-reply updates last_message via saveOutboundMessage()
                // Result: NO RACE CONDITION! Sequential & atomic!
                // ========================================
                
                if (!class_exists('\\App\\Models\\WAReplies')) {
                    require_once __DIR__ . '/../../Models/WAReplies.php';
                }
                
                // Format last_message based on private status
                if ($isPrivateForLastMessage) {
                    $lastMessage = 'i- 🔒 _Private Chat_';
                } else {
                    $lastMessage = 'i- ' . mb_substr($lastMessageSummary, 0, 50);
                }
                
                $replies = new \App\Models\WAReplies();
                $autoReplyResult = $replies->process(
                    $phoneIn, 
                    $messageText, 
                    $waNumber,
                    $contact_name,
                    $assigned_user_id,
                    $code,
                    $lastMessage,
                    $cust_id
                );

                if (!empty($autoReplyResult->no_handler) && mb_strlen(trim((string) ($messageText ?? ''))) > 20) {
                    $replies->trySendDefaultFallbackAutoreply(
                        $phoneIn,
                        $waNumber,
                        $messageText,
                        self::DEFAULT_FALLBACK_REPLY_YCLOUD,
                        self::DEFAULT_FALLBACK_COOLDOWN_MINUTES
                    );
                }
                
                $currentCase = $autoReplyResult->case;
                $conversationId = $autoReplyResult->conversation_id ?? 0;
                $notify = $autoReplyResult->notify ?? true;
                
                // Fetch active cases specific for Notification Logic (Driver needs to know if Case 2 active)
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

            } catch (\Exception $e) {
                \Log::write("Error in auto-reply/conversation: " . $e->getMessage() . " | " . $e->getTraceAsString(), 'wa_error', 'AutoReply');
            }

            // Push ke waserver (tetap jalan meski autoreply error)
            $notifyBool = (bool) $notify;
            $this->pushIncomingToWebSocket([
                'type' => 'wa_masuk',
                'conversation_id' => $conversationId,
                'phone' => $waNumber,
                'contact_name' => $contact_name,
                'case' => $currentCase,
                'active_cases' => $activeCases,
                'notify' => $notifyBool,
                'assignment_user_id' => $assigned_user_id,
                'message' => [
                    'id' => $msgId,
                    'text' => $textBody,
                    'type' => $messageType,
                    'media_id' => $mediaId,
                    'media_url' => $mediaUrl,
                    'caption' => $mediaCaption,
                    'quoted_message_id' => $quotedMessageId,
                    'quoted_message_body' => $quotedMessageBody,
                    'quoted_message_from' => $quotedMessageFrom,
                    'time' => date('Y-m-d H:i:s'),
                ],
                'target_id' => $assigned_user_id ? (string)$assigned_user_id : '0',
                'kode_cabang' => $code,
                'cust_id' => $cust_id
            ]);
        }
    }

    /**
     * Push incoming message to Node.js WebSocket Server
     */
    private function pushIncomingToWebSocket($data)
    {
        $url = \App\Helpers\WaServer::incomingUrl();

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            if (class_exists('\Log')) {
                \Log::write('WS PUSH ERROR: json_encode failed - ' . json_last_error_msg(), 'wa_error', 'WebSocket');
            }
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (class_exists('\Log')) {
            $notifyLog = isset($data['notify']) ? ($data['notify'] ? 'true' : 'false') : 'unset';
            if (curl_errno($ch)) {
                \Log::write('WS PUSH ERROR: ' . curl_error($ch) . " | url=$url | notify=$notifyLog", 'wa_error', 'WebSocket');
            } elseif ($httpCode >= 400) {
                \Log::write("WS PUSH HTTP $httpCode | notify=$notifyLog | " . substr((string) $result, 0, 500), 'wa_error', 'WebSocket');
            } else {
                \Log::write(
                    'WS PUSH OK HTTP ' . $httpCode
                    . ' | url=' . $url
                    . ' | target=' . ($data['target_id'] ?? '?')
                    . ' | phone=' . ($data['phone'] ?? '?')
                    . ' | notify=' . $notifyLog
                    . ' | response=' . substr((string) $result, 0, 300),
                    'webhook',
                    'WebSocket'
                );
            }
        }

        curl_close($ch);

        return $result;
    }

    /**
     * Baris wa_messages_out untuk anchor webhook (urutan: wamid, message_id, external_id).
     */
    private function findWaMessagesOutRowByAnchors($db, $wamid, $messageId, $externalId): ?object
    {
        try {
            if ($wamid) {
                $q = $db->query('SELECT * FROM wa_messages_out WHERE wamid = ? LIMIT 1', [$wamid]);
                if ($q && $q->num_rows() > 0) {
                    return $q->row();
                }
            }
            if ($messageId) {
                $q = $db->query('SELECT * FROM wa_messages_out WHERE message_id = ? LIMIT 1', [$messageId]);
                if ($q && $q->num_rows() > 0) {
                    return $q->row();
                }
            }
            if ($externalId) {
                $q = $db->query('SELECT * FROM wa_messages_out WHERE external_id = ? LIMIT 1', [$externalId]);
                if ($q && $q->num_rows() > 0) {
                    return $q->row();
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('findWaMessagesOutRowByAnchors: ' . $e->getMessage(), 'wa_error', 'Webhook');
            }
        }

        return null;
    }

    /**
     * Teks error yCloud / Meta yang mengindikasikan CSW / jendela 24 jam.
     */
    private function outboundErrorLooksLikeCsw(?string $errorMessage): bool
    {
        if ($errorMessage === null || $errorMessage === '') {
            return false;
        }
        $e = strtolower($errorMessage);
        if (strpos($e, '131047') !== false) {
            return true;
        }
        foreach (['outside', '24 hour', '24-hour', '24h window', 'customer service window', 'csw', 'session has expired'] as $kw) {
            if (strpos($e, $kw) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Jangan set wa_messages_out ke failed bila CSW / antre retry: tetap queue agar ResendWAQueue bisa mencoba lagi (≤24 jam).
     *
     * @param object|null $existingRow baris saat ini (jika ada)
     * @return array{status: mixed, error_message: mixed}
     */
    private function normalizeWaOutboundStatusForCswRetry($db, $incomingStatus, $errorMessage, ?string $phone, $existingRow): array
    {
        $st = strtolower((string) $incomingStatus);
        $failureLike = in_array($st, ['failed', 'undelivered', 'error'], true);
        if (!$failureLike) {
            return ['status' => $incomingStatus, 'error_message' => $errorMessage];
        }

        $current = $existingRow ? strtolower((string) ($existingRow->status ?? '')) : '';
        $keepQueue = false;
        if ($current === 'queue' || $current === 'processing') {
            $keepQueue = true;
        }
        if (!$keepQueue && $this->outboundErrorLooksLikeCsw(is_string($errorMessage) ? $errorMessage : '')) {
            $keepQueue = true;
        }
        if (!$keepQueue && $phone) {
            try {
                if (! class_exists('\\App\\Helpers\\WhatsAppService')) {
                    require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
                }
                $wa = new \App\Helpers\WhatsAppService();
                $conv = $db->get_where('wa_conversations', ['wa_number' => $phone])->row();
                $lastIn = $conv->last_in_at ?? null;
                if (! $wa->isWithinCsw($lastIn)) {
                    $keepQueue = true;
                }
            } catch (\Throwable $e) {
                // abaikan
            }
        }

        if ($keepQueue) {
            $err = trim((string) ($errorMessage ?? ''));
            if ($err !== '') {
                $err .= ' ';
            }
            $err .= '[tetap antre: CSW / retry cron]';

            return ['status' => 'queue', 'error_message' => $err];
        }

        return ['status' => $incomingStatus, 'error_message' => $errorMessage];
    }

    /**
     * Handle outbound message status update
     */
    private function handleStatusUpdate($db, $data)
    {
        $statusUpdate = $data['whatsappMessageStatusUpdate'] ?? [];
        if (empty($statusUpdate)) {
            \Log::write("No whatsappMessageStatusUpdate", 'wa_error', 'Webhook');
            return;
        }

        $wamid = $statusUpdate['wamid'] ?? null;
        $messageId = $statusUpdate['id'] ?? null; // YCloud Message ID
        $externalId = $statusUpdate['externalId'] ?? ($statusUpdate['external_id'] ?? null);
        $status = $statusUpdate['status'] ?? null;
        $errorMessage = $statusUpdate['errorMessage'] ?? null;

        if (!$wamid && !$messageId && !$externalId) {
            \Log::write("ERROR: No wamid/message_id/externalId in status update", 'webhook', 'WhatsApp');
            return;
        }

        $existing = $this->findWaMessagesOutRowByAnchors($db, $wamid, $messageId, $externalId);
        $phoneForCsw = $existing ? ($existing->phone ?? null) : null;
        $norm = $this->normalizeWaOutboundStatusForCswRetry($db, $status, $errorMessage, $phoneForCsw, $existing);
        $normalizedToQueue = ($norm['status'] === 'queue' && strtolower((string) $status) !== 'queue');

        // Update message status in wa_messages
        $updateData = [
            'status' => $norm['status'],
            'error_message' => $norm['error_message']
        ];

        if ($wamid) {
            $updateData['wamid'] = $wamid;
        }
        if ($messageId) {
            $updateData['message_id'] = $messageId;
        }

        // Update by best available anchor
        $updated = false;
        if ($wamid) {
            $updated = $db->update('wa_messages_out', $updateData, ['wamid' => $wamid]);
        }
        if (!$updated && $messageId) {
            $updated = $db->update('wa_messages_out', $updateData, ['message_id' => $messageId]);
        }
        if (!$updated && $externalId) {
            $updated = $db->update('wa_messages_out', $updateData, ['external_id' => $externalId]);
        }
        
        // Also check if in wa_messages_out (sometimes stored there differently?) - actually handled in handleMessageUpdated for out
        // But wa_messages is legacy? Or unified?
        // Let's assume handleMessageUpdated is the main one for OUTBOUND.
        
        if ($updated) {
            // \Log::write("✓ Status updated: $wamid -> $status", 'webhook', 'WhatsApp');

            // Find phone logic for frontend
            $msg = null;
            if ($wamid) {
                $msg = $db->query("SELECT phone, id FROM wa_messages_out WHERE wamid = ?", [$wamid])->row();
            } elseif ($messageId) {
                $msg = $db->query("SELECT phone, id FROM wa_messages_out WHERE message_id = ?", [$messageId])->row();
            } elseif ($externalId) {
                $msg = $db->query("SELECT phone, id FROM wa_messages_out WHERE external_id = ?", [$externalId])->row();
            }
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
                        'status' => $norm['status']
                    ],
                    'target_id' => $targetId
                ]);
            }
            
            // Update notif table logic...
            // id_api is likely the YCloud Message ID, not wamid
            // Jangan timpa notif laundry dengan 'queue' hasil normalisasi CSW — biarkan state dari provider atau skip
            if (!$normalizedToQueue) {
                $db1 = $this->db(1);
                if ($messageId) {
                    $db1->update('notif', ['state' => $status], ['id_api' => $messageId]);
                } elseif ($wamid) {
                    $db1->update('notif', ['state' => $status], ['id_api' => $wamid]);
                }
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
            \Log::write("No whatsappMessage in message.updated event", 'wa_error', 'Webhook');
            return;
        }

        $wamid = $message['wamid'] ?? null;
        $messageId = $message['id'] ?? null; // Provider message ID
        $externalId = $message['externalId'] ?? ($message['external_id'] ?? null);
        $status = $message['status'] ?? null;
        $errObj = $message['error'] ?? null;
        $errorFromMsg = is_array($errObj) ? ($errObj['message'] ?? $errObj['code'] ?? null) : ($message['errorMessage'] ?? null);
        if (is_scalar($errorFromMsg)) {
            $errorFromMsg = (string) $errorFromMsg;
        } else {
            $errorFromMsg = $errorFromMsg !== null ? json_encode($errorFromMsg) : null;
        }

        if (!$wamid && !$messageId && !$externalId) {
            \Log::write("No wamid/message_id/externalId in message.updated event", 'wa_error', 'Webhook');
            return;
        }

        $existing = $this->findWaMessagesOutRowByAnchors($db, $wamid, $messageId, $externalId);
        $phoneForCsw = $existing ? ($existing->phone ?? null) : null;
        $norm = $this->normalizeWaOutboundStatusForCswRetry($db, $status, $errorFromMsg, $phoneForCsw, $existing);
        $normalizedToQueue = ($norm['status'] === 'queue' && strtolower((string) $status) !== 'queue');

        // Build update data based on available fields
        $updateData = [
            'status' => $norm['status'],
            'error_message' => $norm['error_message'],
        ];

        // Add wamid if we have it (might be first time getting wamid from webhook)
        if ($wamid) {
            $updateData['wamid'] = $wamid;
        }
        if ($messageId) {
            $updateData['message_id'] = $messageId;
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
        // Fallback: by externalId (used when HTTP timed out before getting wamid/message_id)
        if (!$updated && $externalId) {
            $updated = $db->update('wa_messages_out', $updateData, ['external_id' => $externalId]);
        }

        if ($updated) {
            // Fetch phone and local ID for WebSocket push
            $checkSql = "SELECT id, phone FROM wa_messages_out WHERE ";
            $params = [];
            if ($messageId) {
                $checkSql .= "message_id = ?";
                $params[] = $messageId;
            } elseif ($wamid) {
                $checkSql .= "wamid = ?";
                $params[] = $wamid;
            } elseif ($externalId) {
                $checkSql .= "external_id = ?";
                $params[] = $externalId;
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
                        'status' => $norm['status'],
                    ],
                    'target_id' => $targetId,
                ]);

                if (! $normalizedToQueue) {
                    $db1 = $this->db(1);
                    if ($messageId) {
                        $db1->update('notif', ['state' => $status], ['id_api' => $messageId]);
                    } elseif ($wamid) {
                        $db1->update('notif', ['state' => $status], ['id_api' => $wamid]);
                    }
                }
            }
        } else {
            // Outbound message not found (no log - this can happen normally)
        }
    }

    // ========================================
    // 🗑️ REMOVED: getOrCreateConversationWithCase()
    // ========================================
    // Moved to WAReplies.php for better architecture
    // This allows atomic execution:
    // 1. Detect intent
    // 2. Create conversation (inbound)
    // 3. Send auto-reply (outbound overwrites)
    // Result: NO RACE CONDITION!
    // 
    // Credit: User's brilliant idea! 🏆
    // ========================================

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
            $return->cust_id = $customer->id_pelanggan; // id_pelanggan from pelanggan (same source as code)
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
