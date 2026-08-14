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
            // Quote body lookup deferred until AFTER WS push (avoid DB latency on hot path)
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

        $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber); // 628...
        $phone0 = '0' . substr($cleanPhone, 2); // 08...
        $phonePlus = '+' . $cleanPhone; // +62...
        $phoneNoPrefix = substr($cleanPhone, 2);
        $phones = ["'$cleanPhone'", "'$phone0'", "'$phonePlus'", "'$phoneNoPrefix'"];
        $phoneIn = implode(',', $phones);

        // Hot path: jangan panggil getUserData dulu (LIKE pelanggan bisa lambat). Enrich setelah WS push.
        $assigned_user_id = null;
        $code = null;
        $cust_id = null;
        $contact_name = $contactName;

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
            $reactionEmoji = $msg['reaction']['emoji'] ?? null;
            $messageText = $reactionEmoji ? "Reacted $reactionEmoji" : "Removed reaction";
        } elseif ($messageType === 'sticker') {
            // Sticker tanpa caption → label agar intent PENUTUP (lainnya) bisa match
            $messageText = '🎨 Sticker';
        } elseif ($messageType === 'location') {
            $locName = $msg['location']['name'] ?? null;
            $locAddr = $msg['location']['address'] ?? null;
            $locLat = $msg['location']['latitude'] ?? null;
            $locLng = $msg['location']['longitude'] ?? null;
            $messageText = '📍 ' . ($locName ?: ($locAddr ?: 'Shared Location'));
            // Sertakan koordinat di teks intent (process hanya terima string text)
            if ($locLat !== null && $locLng !== null && $locLat !== '' && $locLng !== '') {
                $messageText .= " {$locLat},{$locLng}";
                $messageText .= " https://maps.google.com/maps?q={$locLat},{$locLng}";
            }
        } elseif (isset($msg[$messageType]['caption'])) {
            $messageText = $msg[$messageType]['caption'];
        }

        $lastMessageSummary = $messageText;
        if ($lastMessageSummary === '' && $messageType !== 'text') {
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

        $isPrivateForLastMessage = false;
        try {
            $isPrivateForLastMessage = \EnvHelper::textContainsPrivateWord($lastMessageSummary ?? '');
        } catch (\Throwable $e) {
            // ignore
        }

        $textBody = null;
        $mediaId = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaUrlDirect = null;
        $mediaCaption = null;
        $needsMediaDownload = false;

        // Initialize Metadata
        $messageId = $msg['id'] ?? null;
        $wamid = $msg['wamid'] ?? null;
        $status = 'unread'; // Default status for new messages

        switch ($messageType) {
            case 'text':
                $textBody = $msg['text']['body'] ?? null;
                break;

            case 'button':
                $textBody = $msg['button']['text'] ?? ($msg['button']['payload'] ?? null);
                break;

            case 'interactive':
                if (isset($msg['interactive']['button_reply'])) {
                    $textBody = $msg['interactive']['button_reply']['title'] ?? null;
                } elseif (isset($msg['interactive']['list_reply'])) {
                    $textBody = $msg['interactive']['list_reply']['title'] ?? null;
                }
                break;

            case 'reaction':
                $reactionEmoji = $msg['reaction']['emoji'] ?? null;
                $reactionMessageId = $msg['reaction']['message_id'] ?? null;
                $textBody = $reactionEmoji ? "Reacted: $reactionEmoji" : "Removed reaction";
                $mediaCaption = $reactionMessageId;
                break;

            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'voice':
            case 'sticker':
                $mediaId = $msg[$messageType]['id'] ?? null;
                $mediaMimeType = $msg[$messageType]['mimeType'] ?? $msg[$messageType]['mime_type'] ?? null;
                $mediaUrlDirect = $msg[$messageType]['link'] ?? null;
                $mediaCaption = $msg[$messageType]['caption'] ?? null;
                // Pakai URL langsung dulu — download file ditunda setelah WS push
                $mediaUrl = $mediaUrlDirect;
                $needsMediaDownload = ($mediaId || $mediaUrlDirect) ? true : false;
                break;

            case 'location':
                $latitude = $msg['location']['latitude'] ?? null;
                $longitude = $msg['location']['longitude'] ?? null;
                $locationName = $msg['location']['name'] ?? null;
                $locationAddress = $msg['location']['address'] ?? null;
                $locationParts = [];
                if ($locationName) {
                    $locationParts[] = $locationName;
                }
                if ($locationAddress) {
                    $locationParts[] = $locationAddress;
                }
                $locationLabel = !empty($locationParts) ? implode(', ', $locationParts) : 'Shared Location';
                $textBody = "📍 $locationLabel";
                if ($latitude && $longitude) {
                    $mediaUrl = "https://maps.google.com/maps?q={$latitude},{$longitude}";
                    $mediaCaption = "{$latitude},{$longitude}";
                    // Pastikan teks intent ikut bawa lat/lng + URL (process() hanya dapat 1 string)
                    $textBody .= " {$latitude},{$longitude} {$mediaUrl}";
                    $messageText = $textBody;
                }
                break;
        }

        // Reuse assignment from existing conversation for WS targeting (tanpa load WAReplies dulu —
        // load WAReplies bisa fatal jika trait belum ada; jangan blok insert/WS)
        try {
            $existingQuick = $db->query(
                "SELECT id, assigned_user_id, contact_name, code, cust_id FROM wa_conversations WHERE wa_number = ? LIMIT 1",
                [$waNumber]
            )->row();
            if (!$existingQuick) {
                $existingQuick = $db->query(
                    "SELECT id, assigned_user_id, contact_name, code, cust_id FROM wa_conversations WHERE wa_number = ? LIMIT 1",
                    [$phonePlus]
                )->row();
            }
            if (!$existingQuick) {
                $existingQuick = $db->query(
                    "SELECT id, assigned_user_id, contact_name, code, cust_id FROM wa_conversations WHERE wa_number = ? LIMIT 1",
                    [$cleanPhone]
                )->row();
            }
            if ($existingQuick) {
                $assigned_user_id = $existingQuick->assigned_user_id ?? null;
                $code = $existingQuick->code ?? null;
                $cust_id = $existingQuick->cust_id ?? null;
                if (empty($contact_name) && !empty($existingQuick->contact_name)) {
                    $contact_name = $existingQuick->contact_name;
                }
            }
        } catch (\Throwable $e) {
            // ignore — push tetap jalan
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
                if (!class_exists('\\App\\Models\\WAReplies')) {
                    require_once __DIR__ . '/../../Models/WAReplies.php';
                }

                if ($isPrivateForLastMessage) {
                    $lastMessage = 'i- 🔒 _Private Chat_';
                } else {
                    $lastMessage = 'i- ' . mb_substr($lastMessageSummary, 0, 50);
                }

                $replies = new \App\Models\WAReplies();

                // 1) Open CSW + push WS segera (mirip WaDesk) — intent/AI belakangan
                $conversationId = (int) $replies->getOrCreateConversationWithCase(
                    $db,
                    $waNumber,
                    $contact_name,
                    $assigned_user_id,
                    $code,
                    $cust_id,
                    $lastMessage,
                    null
                );

                // Broadcast cepat ke UI — tanpa OneSignal (notify=false).
                // Push HP baru dikirim setelah intent jika notify=true.
                $this->pushIncomingToWebSocket([
                    'type' => 'wa_masuk',
                    'conversation_id' => $conversationId,
                    'phone' => $waNumber,
                    'contact_name' => $contact_name,
                    'case' => null,
                    'active_cases' => [],
                    'notify' => false,
                    'assignment_user_id' => $assigned_user_id,
                    'status' => 'open',
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
                        'sender' => 'customer',
                    ],
                    'target_id' => '0',
                    'kode_cabang' => $code,
                    'cust_id' => $cust_id,
                ]);

                // 2) Enrich setelah UI sudah update: pelanggan, quote body, media file, intent
                if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
                    require_once __DIR__ . '/../../Helpers/CRM/WaSenderContext.php';
                }
                $senderCtx = \App\Helpers\CRM\WaSenderContext::resolve($waNumber);
                $replies->setSenderContext($senderCtx);
                if (!empty($senderCtx['is_pelanggan'])) {
                    $assigned_user_id = $senderCtx['assigned_user_id'] ?? $assigned_user_id;
                    $code = $senderCtx['code'] ?? $code;
                    $cust_id = $senderCtx['cust_id'] ?? $cust_id;
                    if (!empty($senderCtx['contact_name'])) {
                        $contact_name = $senderCtx['contact_name'];
                    }
                    $replies->getOrCreateConversationWithCase(
                        $db,
                        $waNumber,
                        $contact_name,
                        $assigned_user_id,
                        $code,
                        $cust_id,
                        $lastMessage,
                        null
                    );
                }

                if ($quotedMessageId && $quotedMessageBody === null) {
                    try {
                        $result = $db->get_where('wa_messages_in', ['wamid' => $quotedMessageId]);
                        if ($result && $result->num_rows() > 0) {
                            $quotedMsg = $result->row();
                            $quotedMessageBody = $quotedMsg->text ?? $quotedMsg->media_caption ?? null;
                        } else {
                            $result = $db->get_where('wa_messages_out', ['wamid' => $quotedMessageId]);
                            if ($result && $result->num_rows() > 0) {
                                $quotedMsg = $result->row();
                                $quotedMessageBody = $quotedMsg->content ?? null;
                            }
                        }
                        if ($quotedMessageBody !== null) {
                            $db->update('wa_messages_in', [
                                'quoted_message_body' => $quotedMessageBody,
                            ], ['id' => $msgId]);
                        }
                    } catch (\Throwable $e) {
                        \Log::write("ERROR fetching quoted message - WAMID: $quotedMessageId | " . $e->getMessage(), 'wa_error', 'Quote');
                    }
                }

                if ($needsMediaDownload) {
                    try {
                        if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                            require_once __DIR__ . '/../../Helpers/CRM/WhatsAppService.php';
                        }
                        $waService = new \App\Helpers\CRM\WhatsAppService();
                        $savedUrl = $waService->downloadAndSaveMedia($mediaId, $mediaUrlDirect, $mediaMimeType);
                        if ($savedUrl) {
                            $mediaUrl = $savedUrl;
                            $db->update('wa_messages_in', ['media_url' => $mediaUrl], ['id' => $msgId]);
                        }
                    } catch (\Throwable $e) {
                        \Log::write("Media download exception (deferred): " . $e->getMessage(), 'wa_error', 'Webhook');
                    }
                }

                // 3) Intent detect + auto-reply (bisa lambat: OpenAI/Groq)
                // Gabungkan text + media_url + caption agar pin lokasi / link maps terbaca di kurir flow
                $processText = trim((string) ($messageText !== '' ? $messageText : ($textBody ?? '')));
                if (!empty($mediaCaption) && stripos($processText, (string) $mediaCaption) === false) {
                    $processText = trim($processText . ' ' . $mediaCaption);
                }
                if (!empty($mediaUrl) && stripos($processText, (string) $mediaUrl) === false) {
                    $processText = trim($processText . ' ' . $mediaUrl);
                }
                $autoReplyResult = $replies->process(
                    $phoneIn,
                    $processText !== '' ? $processText : $messageText,
                    $waNumber,
                    $contact_name,
                    $assigned_user_id,
                    $code,
                    $lastMessage,
                    $cust_id
                );

                // DEFAULT fallback: no_handler dari process() = intent FALSE + ask true (atau jalur CS lain tanpa handler)
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
                if (!empty($autoReplyResult->conversation_id)) {
                    $conversationId = (int) $autoReplyResult->conversation_id;
                }
                $notify = $autoReplyResult->notify ?? true;

                $freshConv = $db->get_where('wa_conversations', ['id' => $conversationId])->row();
                if ($freshConv && !empty($freshConv->conv_case)) {
                    $casesDecoded = json_decode($freshConv->conv_case, true);
                    if (is_array($casesDecoded)) {
                        if (!isset($casesDecoded[0])) {
                            $casesDecoded = [$casesDecoded];
                        }
                        foreach ($casesDecoded as $c) {
                            if (isset($c['case']) && isset($c['status']) && $c['status'] === 'open') {
                                $activeCases[] = (int) $c['case'];
                            }
                        }
                    } elseif (is_numeric($freshConv->conv_case)) {
                        $activeCases[] = (int) $freshConv->conv_case;
                    }
                }

                if ($currentCase !== null && (int) $currentCase !== 0) {
                    $this->pushIncomingToWebSocket([
                        'type' => 'case_updated',
                        'phone' => $waNumber,
                        'conversation_id' => $conversationId,
                        'case' => (int) $currentCase,
                        'active_cases' => $activeCases,
                        'notify' => (bool) $notify,
                        'assignment_user_id' => $assigned_user_id,
                        'target_id' => '0',
                    ]);
                }

                // OneSignal hanya jika intent meminta notify=true (sama seperti perilaku lama).
                // Pakai message id yang sama → CRM UI anggap duplikat, tidak dobel bubble.
                if ($notify) {
                    $this->pushIncomingToWebSocket([
                        'type' => 'wa_masuk',
                        'conversation_id' => $conversationId,
                        'phone' => $waNumber,
                        'contact_name' => $contact_name,
                        'case' => $currentCase,
                        'active_cases' => $activeCases,
                        'notify' => true,
                        'assignment_user_id' => $assigned_user_id,
                        'status' => 'open',
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
                            'sender' => 'customer',
                        ],
                        'target_id' => '0',
                        'kode_cabang' => $code,
                        'cust_id' => $cust_id,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::write(
                    "Error in auto-reply/conversation: " . $e->getMessage()
                    . " | " . $e->getFile() . ':' . $e->getLine()
                    . " | " . $e->getTraceAsString(),
                    'wa_error',
                    'AutoReply'
                );
            }
        }
    }

    /**
     * Push incoming message to Node.js WebSocket Server
     */
    private function pushIncomingToWebSocket($data)
    {
        $url = \App\Helpers\CRM\WaServer::incomingUrl();

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            if (class_exists('\Log')) {
                \Log::write('WS PUSH ERROR: json_encode failed - ' . json_last_error_msg(), 'wa_error', 'WebSocket');
            }
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        // Match WaDesk: fail fast — jangan tahan hot path inbound
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        // Log hanya saat gagal (sukses tiap pesan = I/O disk yang memperlambat)
        if (class_exists('\Log') && ($curlErr !== '' || $httpCode >= 400 || $httpCode === 0)) {
            $notifyLog = isset($data['notify']) ? ($data['notify'] ? 'true' : 'false') : 'unset';
            if ($curlErr !== '') {
                \Log::write('WS PUSH ERROR: ' . $curlErr . " | url=$url | notify=$notifyLog", 'wa_error', 'WebSocket');
            } else {
                \Log::write("WS PUSH HTTP $httpCode | notify=$notifyLog | " . substr((string) $result, 0, 500), 'wa_error', 'WebSocket');
            }
            return false;
        }

        return true;
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
                    require_once __DIR__ . '/../../Helpers/CRM/WhatsAppService.php';
                }
                $wa = new \App\Helpers\CRM\WhatsAppService();
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
                    // ALWAYS broadcast status to all agents viewing any chat
                    // (assigned_user_id targeting caused other viewers to miss ticks)
                    'target_id' => '0'
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
                        'wamid' => $wamid,
                        'status' => $norm['status'],
                    ],
                    // ALWAYS broadcast — status ticks must reach every open CRM client
                    'target_id' => '0',
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
        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../../Helpers/CRM/WaSenderContext.php';
        }
        $ctx = \App\Helpers\CRM\WaSenderContext::resolveFromPhone($phone0);
        if (empty($ctx['is_pelanggan'])) {
            return null;
        }
        $return = new \stdClass();
        $return->customer_name = $ctx['contact_name'];
        $return->cust_id = $ctx['cust_id'] ?: $ctx['id_pelanggan'];
        $return->assigned_user_id = $ctx['assigned_user_id'];
        $return->code = $ctx['code'];

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
