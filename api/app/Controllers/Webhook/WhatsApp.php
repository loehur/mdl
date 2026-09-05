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

                case 'whatsapp.smb.message.echoes':
                    $this->handleSmbMessageEchoes($db, $data);
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

        if (empty($msg)) {
            \Log::write("No whatsappInboundMessage", 'wa_error', 'Webhook');
            return;
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WaLineResolver')) {
            require_once __DIR__ . '/../../Helpers/CRM/WaLineResolver.php';
        }
        if (!class_exists('\\App\\Helpers\\CRM\\WaCswLine')) {
            require_once __DIR__ . '/../../Helpers/CRM/WaCswLine.php';
        }
        $inboundLine = \App\Helpers\CRM\WaLineResolver::fromBusinessPhoneOrDefault($msg['to'] ?? null);
        $businessPhone = $inboundLine['phone'];
        $inboundLineKey = $inboundLine['key'];

        $textBodyToCheck = $msg['text']['body'] ?? '';
        
        $waNumber = $this->normalizePhoneNumber($msg['from'] ?? null);
        if (!class_exists('\\App\\Helpers\\CRM\\WaConversationAlias')) {
            require_once __DIR__ . '/../../Helpers/CRM/WaConversationAlias.php';
        }
        $identityHints = \App\Helpers\CRM\WaConversationAlias::hintsFromYcloudMessage($msg, $waNumber);
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
        $senderCtxEarly = null;
        try {
            if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
                require_once __DIR__ . '/../../Helpers/CRM/WaSenderContext.php';
            }
            $senderCtxEarly = \App\Helpers\CRM\WaSenderContext::resolve($waNumber);
            $isKaryawanEarly = !empty($senderCtxEarly['is_karyawan']);
            if (!empty($senderCtxEarly['contact_name'])) {
                $contact_name = $senderCtxEarly['contact_name'];
            }

            if (!$isKaryawanEarly) {
                $existingQuick = \App\Helpers\CRM\WaConversationAlias::findConversationRow($db, $identityHints);
                if (!$existingQuick) {
                    $existingQuick = $db->query(
                        "SELECT id, assigned_user_id, contact_name, code, cust_id FROM wa_conversations WHERE wa_number = ? LIMIT 1",
                        [$waNumber]
                    )->row();
                }
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
                $fromCtx = \App\Helpers\CRM\WaSenderContext::cswAssignedUserId($senderCtxEarly);
                if ($fromCtx !== null) {
                    $assigned_user_id = $fromCtx;
                }
                if (!empty($senderCtxEarly['code'])) {
                    $code = $senderCtxEarly['code'];
                }
                if (!empty($senderCtxEarly['cust_id'])) {
                    $cust_id = $senderCtxEarly['cust_id'];
                }
            } else {
                $assigned_user_id = null;
                $code = null;
                $cust_id = null;
            }
        } catch (\Throwable $e) {
            // ignore — push tetap jalan
        }

        // Step 4: Save message to wa_messages_in
        $messageData = [
            'phone' => $waNumber,
            'business_phone' => $businessPhone,
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

        if ($msgId) {
            \App\Helpers\CRM\WaCswLine::touch($db, $waNumber, $businessPhone);
        }

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
                $replies->setInboundReplyToMessageId($wamid ?: $messageId);
                $replies->setInboundLine($inboundLineKey, $businessPhone);
                if (is_array($senderCtxEarly)) {
                    $replies->setSenderContext($senderCtxEarly);
                }

                $convWaNumber = $waNumber;
                $aliasConv = \App\Helpers\CRM\WaConversationAlias::findConversationRow($db, $identityHints);
                if ($aliasConv && !empty($aliasConv->wa_number)) {
                    $convWaNumber = (string) $aliasConv->wa_number;
                }

                // 1) Open CSW + push WS segera (mirip WaDesk) — intent/AI belakangan
                $conversationId = (int) $replies->getOrCreateConversationWithCase(
                    $db,
                    $convWaNumber,
                    $contact_name,
                    $assigned_user_id,
                    $code,
                    $cust_id,
                    $lastMessage,
                    null
                );
                if ($conversationId > 0) {
                    \App\Helpers\CRM\WaConversationAlias::remember($db, $conversationId, $identityHints, 'ycloud');
                }

                $freshConvEarly = $conversationId > 0
                    ? $db->get_where('wa_conversations', ['id' => $conversationId])->row()
                    : null;
                $activeCasesEarly = $this->extractOpenCaseIds($freshConvEarly);

                // Broadcast cepat ke UI — tanpa OneSignal (notify=false).
                // Push HP baru dikirim setelah intent jika notify=true.
                // id harus Y-{db} agar cocok getMessages; tanpa itu polling CRM dobelkan bubble.
                $this->pushIncomingToWebSocket([
                    'type' => 'wa_masuk',
                    'conversation_id' => $conversationId,
                    'phone' => $waNumber,
                    'contact_name' => $contact_name,
                    'case' => null,
                    'active_cases' => $activeCasesEarly,
                    'notify' => false,
                    'assignment_user_id' => $assigned_user_id,
                    'status' => 'open',
                    'message' => [
                        'id' => $inboundLineKey . '-' . $msgId,
                        'wamid' => $wamid ?: $messageId,
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
                        'line_key' => $inboundLineKey,
                        'business_phone' => $businessPhone,
                        'line_label' => $inboundLine['short_label'],
                        'provider' => $inboundLineKey,
                    ],
                    'target_id' => '0',
                    'kode_cabang' => $code,
                    'cust_id' => $cust_id,
                ]);

                // 2) Enrich setelah UI sudah update: pelanggan, quote body, media file, intent
                if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
                    require_once __DIR__ . '/../../Helpers/CRM/WaSenderContext.php';
                }
                $senderCtx = is_array($senderCtxEarly)
                    ? $senderCtxEarly
                    : \App\Helpers\CRM\WaSenderContext::resolve($waNumber);
                $replies->setSenderContext($senderCtx);
                $isPelanggan = !empty($senderCtx['is_pelanggan']);
                $isKaryawan = !empty($senderCtx['is_karyawan']);
                if ($isKaryawan) {
                    $assigned_user_id = null;
                    $code = null;
                    $cust_id = null;
                } elseif ($isPelanggan) {
                    $fromCtx = \App\Helpers\CRM\WaSenderContext::cswAssignedUserId($senderCtx);
                    if ($fromCtx !== null) {
                        $assigned_user_id = $fromCtx;
                    }
                    $code = $senderCtx['code'] ?? $code;
                    $cust_id = $senderCtx['cust_id'] ?? $cust_id;
                }
                if ($isPelanggan || $isKaryawan) {
                    if (!empty($senderCtx['contact_name'])) {
                        $contact_name = $senderCtx['contact_name'];
                    }
                    $replies->getOrCreateConversationWithCase(
                        $db,
                        $convWaNumber,
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

                // Jawaban singkat seperti "yang ini"/"iya" harus dibaca sebagai respons atas quote.
                $replies->setInboundQuotedMessage($quotedMessageId, $quotedMessageBody, $quotedMessageFrom);

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

                // Bukti pembayaran hanya dianalisis untuk pelanggan yang belum memiliki transaksi aktif.
                \Log::write(
                    'Vision image diagnostic phone=' . $waNumber
                    . ' message_id=' . ($messageId ?: '-')
                    . ' message_type=' . $messageType
                    . ' is_pelanggan=' . ($isPelanggan ? 'true' : 'false')
                    . ' media_id=' . ($mediaId ?: '-')
                    . ' media_url=' . ($mediaUrl ? 'yes' : 'no')
                    . ' mime=' . ($mediaMimeType ?: '-'),
                    'vision_image',
                    'Webhook'
                );

                if ($messageType === 'image' && $isPelanggan && $mediaUrl) {
                    $this->logVisionImage('guard_start phone=' . $waNumber . ' message_id=' . ($messageId ?: '-'));
                    $hasPendingPayment = $this->customerHasPendingTransaction($phoneIn, $waNumber, $messageId);
                    $hasEligibleOrder = $this->customerHasEligibleOrder($phoneIn, $waNumber, $messageId);
                    $this->logVisionImage(
                        'guard_result pending_payment=' . ($hasPendingPayment ? 'true' : 'false')
                        . ' eligible_order=' . ($hasEligibleOrder ? 'true' : 'false')
                    );
                    if (!$hasPendingPayment && $hasEligibleOrder) {
                        $this->sendVisionThanksReply($waNumber);
                        $this->logVisionImage('ai_start media_url=' . $mediaUrl . ' mime=' . ($mediaMimeType ?: '-'));
                        $paymentResult = $this->analyzePaymentImage($mediaUrl, $mediaMimeType, $waNumber);
                        $this->logVisionImage('ai_result parsed=' . ($paymentResult !== null ? 'true' : 'false'));
                        if ($paymentResult !== null) {
                            \Log::write(
                                'Payment image result phone=' . $waNumber
                                . ' message_id=' . ($messageId ?: '-')
                                . ' result=' . json_encode($paymentResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'vision_image',
                                'Webhook'
                            );
                            $allocation = $this->allocateVisionPayment(
                                $waNumber,
                                $paymentResult,
                                $messageId
                            );
                            $this->logVisionImage(
                                'allocation_result=' . json_encode($allocation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            );
                        }
                    } else {
                        $this->logVisionImage(
                            'skip_vision guard_failed pending_payment=' . ($hasPendingPayment ? 'true' : 'false')
                            . ' eligible_order=' . ($hasEligibleOrder ? 'true' : 'false')
                        );
                        \Log::write(
                            'Skip payment image analysis: customer has pending transaction phone=' . $waNumber,
                            'wa_info',
                            'Webhook'
                        );
                    }
                }

                // 3) Intent detect + auto-reply (bisa lambat: OpenAI/DeepSeek)
                // Caption/teks untuk intent; URL maps hanya untuk lokasi (bukan image/video — URL panjang memblokir PENUTUP maxlength)
                $processText = trim((string) ($messageText !== '' ? $messageText : ($textBody ?? '')));
                if (!empty($mediaCaption) && stripos($processText, (string) $mediaCaption) === false) {
                    $processText = trim($processText . ' ' . $mediaCaption);
                }
                if ($messageType === 'location'
                    && !empty($mediaUrl)
                    && stripos($processText, (string) $mediaUrl) === false
                ) {
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

                // Pesan admin/karyawan dapat tetap di-autoreply, namun tidak boleh membawa case ke CRM/websocket.
                if ($replies->senderMustUseNullCase()) {
                    $autoReplyResult->case = null;
                }

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
                $activeCases = $this->extractOpenCaseIds($freshConv);

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
                // Pakai message id yang sama (Y-{db}) → CRM UI anggap duplikat, tidak dobel bubble.
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
                            'id' => $inboundLineKey . '-' . $msgId,
                            'wamid' => $wamid ?: $messageId,
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
                            'line_key' => $inboundLineKey,
                            'business_phone' => $businessPhone,
                            'line_label' => $inboundLine['short_label'],
                            'provider' => $inboundLineKey,
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
     * Ambil daftar case ID yang masih open dari wa_conversations.conv_case.
     *
     * @param object|string|null $conv Row wa_conversations atau JSON conv_case
     * @return list<int>
     */
    private function extractOpenCaseIds($conv): array
    {
        if ($conv === null || $conv === '') {
            return [];
        }

        if (!class_exists('\\App\\Helpers\\CRM\\CrmCaseHelper')) {
            require_once __DIR__ . '/../../Helpers/CRM/CrmCaseHelper.php';
        }

        $raw = is_object($conv) ? ($conv->conv_case ?? null) : $conv;

        return \App\Helpers\CRM\CrmCaseHelper::extractOpenCaseIds($raw);
    }

    private function logVisionImage(string $message): void
    {
        \Log::write($message, 'vision_image', 'Webhook');
    }

    private function sendVisionThanksReply(string $waNumber): void
    {
        try {
            if (!class_exists('\\App\\Models\\WAReplies')) {
                require_once __DIR__ . '/../../Models/WAReplies.php';
            }
            $replies = new \App\Models\WAReplies();
            $ctxMethod = new \ReflectionMethod($replies, 'getGreetingContext');
            $ctxMethod->setAccessible(true);
            $ctx = $ctxMethod->invoke($replies, $waNumber);
            $sapaan = (string) ($ctx['sapaan'] ?? 'kak');

            $replyMethod = new \ReflectionMethod($replies, 'pickPenutupThanksReply');
            $replyMethod->setAccessible(true);
            $reply = (string) $replyMethod->invoke($replies, $sapaan);

            $sendMethod = new \ReflectionMethod($replies, 'sendAutoreplyText');
            $sendMethod->setAccessible(true);
            $sendMethod->invoke($replies, $waNumber, $reply);
            $this->logVisionImage('thanks_reply_sent text=' . $reply);
        } catch (\Throwable $e) {
            $this->logVisionImage('thanks_reply_exception class=' . get_class($e) . ' message=' . $e->getMessage());
        }
    }

    private function customerHasPendingTransaction(string $phoneIn, string $waNumber, ?string $messageId = null): bool
    {
        try {
            $this->logVisionImage('pending_check_start phone=' . $waNumber . ' message_id=' . ($messageId ?: '-'));
            $db1 = \App\Core\DB::getInstance(1);
            $this->logVisionImage('pending_check_db_connected');
            $rows = $this->findCustomerRowsByWaNumber($db1, $waNumber);
            $ids = array_values(array_filter(array_map('intval', array_column($rows, 'id_pelanggan'))));
            $this->logVisionImage('pending_check_customer_ids=' . ($ids ? implode(',', $ids) : '-'));
            if ($ids === []) {
                $this->logVisionImage('pending_check_no_customer_ids');
                return false;
            }

            $idsIn = implode(',', $ids);
            $this->logVisionImage('pending_check_payment_query_start');
            $result = $db1->query(
            "SELECT 1
             FROM kas k
             INNER JOIN sale s
               ON s.no_ref = k.ref_transaksi
              AND s.id_cabang = k.id_cabang
             WHERE s.id_pelanggan IN ($idsIn)
               AND k.jenis_transaksi = 1
               AND k.status_mutasi = 2
               AND k.jenis_mutasi = 1
               AND k.metode_mutasi IN (2, 3)
             LIMIT 1"
            );

            if (!$result) {
                $error = $db1->conn()->error ?? 'unknown database error';
                $this->logVisionImage('pending_check_query_failed error=' . $error);
                return false;
            }

            $pending = $result->num_rows() > 0;
            $this->logVisionImage('pending_check_query_result rows=' . $result->num_rows() . ' pending=' . ($pending ? 'true' : 'false'));
            return $pending;
        } catch (\Throwable $e) {
            $this->logVisionImage('pending_check_exception class=' . get_class($e) . ' message=' . $e->getMessage());
            return false;
        }
    }

    private function findCustomerRowsByWaNumber($db, string $waNumber): array
    {
        $this->logVisionImage('customer_lookup_start phone=' . $waNumber);
        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../../Helpers/CRM/WaSenderContext.php';
        }

        $number = \App\Helpers\CRM\WaSenderContext::toNomorNasional($waNumber);
        $this->logVisionImage('customer_lookup_national_number=' . ($number ?: '-'));
        if ($number === null || strlen($number) < 8) {
            return [];
        }

        $digits = \App\Helpers\CRM\WaSenderContext::sqlDigitsExpr('nomor_pelanggan');
        $digits2 = \App\Helpers\CRM\WaSenderContext::sqlDigitsExpr('nomor_pelanggan_2');
        $result = $db->query(
            'SELECT id_pelanggan FROM pelanggan WHERE ' . $digits . ' LIKE ? OR ' . $digits2 . ' LIKE ? ORDER BY id_pelanggan ASC',
            ['%' . $number, '%' . $number]
        );
        $this->logVisionImage('customer_lookup_query_done rows=' . ($result ? $result->num_rows() : 0));

        return $result ? ($result->result_array() ?: []) : [];
    }

    private function allocateVisionPayment(string $waNumber, array $paymentResult, ?string $messageId): array
    {
        $customerId = 0;
        try {
            $db = \App\Core\DB::getInstance(1);
            $rows = $this->findCustomerRowsByWaNumber($db, $waNumber);
            $customerId = (int) ($rows[0]['id_pelanggan'] ?? 0);
            if ($customerId <= 0) {
                return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
            }

            if (empty($paymentResult['is_receipt']) || !in_array($paymentResult['type'] ?? null, ['BCA', 'QRIS'], true)) {
                return ['ok' => false, 'message' => 'Gambar bukan bukti pembayaran yang valid'];
            }
            if (!is_numeric($paymentResult['nominal'] ?? null) || (int) $paymentResult['nominal'] <= 0) {
                return ['ok' => false, 'message' => 'Nominal pembayaran tidak valid'];
            }

            if (!class_exists('\\App\\Helpers\\Laundry\\PaymentAllocator')) {
                require_once __DIR__ . '/../../Helpers/Laundry/PaymentAllocator.php';
            }
            return (new \App\Helpers\Laundry\PaymentAllocator())->allocate(
                $customerId,
                (int) $paymentResult['nominal'],
                (string) $paymentResult['type'],
                $paymentResult['receipt_date'] ?? null,
                $messageId
            );
        } catch (\Throwable $e) {
            $this->logVisionImage('allocation_exception customer_id=' . $customerId . ' class=' . get_class($e) . ' message=' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function customerHasEligibleOrder(string $phoneIn, string $waNumber, ?string $messageId = null): bool
    {
        try {
            $db1 = \App\Core\DB::getInstance(1);
            $rows = $this->findCustomerRowsByWaNumber($db1, $waNumber);
            $ids = array_values(array_filter(array_map('intval', array_column($rows, 'id_pelanggan'))));
            if ($ids === []) {
                $this->logVisionImage('eligible_order_no_customer_ids');
                return false;
            }

            $idsIn = implode(',', $ids);
            $sales = $db1->query(
                "SELECT 1 FROM sale WHERE id_pelanggan IN ($idsIn) AND tuntas = 0 AND bin = 0 LIMIT 1"
            );
            if ($sales && $sales->num_rows() > 0) {
                $this->logVisionImage('eligible_order_sale=true');
                return true;
            }

            $members = $db1->query(
                "SELECT 1 FROM member WHERE id_pelanggan IN ($idsIn) AND bin = 0 AND lunas = 0 LIMIT 1"
            );
            $eligible = $members && $members->num_rows() > 0;
            $this->logVisionImage('eligible_order_sale=false member=' . ($eligible ? 'true' : 'false'));
            return $eligible;
        } catch (\Throwable $e) {
            $this->logVisionImage('eligible_order_exception class=' . get_class($e) . ' message=' . $e->getMessage());
            return false;
        }
    }

    private function analyzePaymentImage(string $mediaUrl, ?string $mimeType, string $waNumber): ?array
    {
        try {
            $this->logVisionImage('ai_method_start phone=' . $waNumber);
            if (!class_exists('\\App\\Models\\WAReplies')) {
                require_once __DIR__ . '/../../Models/WAReplies.php';
            }

            $replies = new \App\Models\WAReplies();
            $messages = [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Analisis gambar bukti pembayaran. Kembalikan HANYA JSON valid dengan format {"is_receipt":true|false,"type":"BCA"|"QRIS"|null,"nominal":number|null,"receipt_date":"YYYY-MM-DD"|null}. Tentukan type berdasarkan penerima/tujuan: gunakan "BCA" HANYA jika penerima/tujuan adalah "LUHUR GUNAWAN"; gunakan "QRIS" HANYA jika penerima/tujuan adalah "MADINAH LAUNDRY". receipt_date adalah tanggal transaksi pada bukti pembayaran dalam format YYYY-MM-DD, atau null jika tidak ditemukan. Jika penerima/tujuan tidak terlihat, berbeda, atau tidak dapat dipastikan, gunakan is_receipt=false, type=null, nominal=null, receipt_date=null. Jangan menebak nominal atau tanggal.',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => $mediaUrl],
                    ],
                ],
            ]];
            $raw = $this->invokeVisionAi($messages, $waNumber);
            $this->logVisionImage('ai_raw_response=' . substr((string) $raw, 0, 1000));
            $json = json_decode(trim((string) $raw), true);
            if (!is_array($json)) {
                $this->logVisionImage('ai_json_decode_failed error=' . json_last_error_msg());
                return null;
            }

            $type = $json['type'] ?? null;
            return [
                'is_receipt' => (bool) ($json['is_receipt'] ?? false),
                'type' => in_array($type, ['BCA', 'QRIS'], true) ? $type : null,
                'nominal' => isset($json['nominal']) && is_numeric($json['nominal']) ? (float) $json['nominal'] : null,
                'receipt_date' => $this->normalizeReceiptDate($json['receipt_date'] ?? null),
            ];
        } catch (\Throwable $e) {
            $this->logVisionImage('ai_exception class=' . get_class($e) . ' message=' . $e->getMessage());
            \Log::write('Payment image AI failed: ' . $e->getMessage(), 'wa_error', 'Webhook');
            return null;
        }
    }

    private function normalizeReceiptDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function invokeVisionAi(array $messages, string $waNumber): string
    {
        $apiKey = trim((string) (defined('Env::AI_VISION_API_KEY') ? \Env::AI_VISION_API_KEY : ''));
        $model = trim((string) (defined('Env::AI_VISION_MODEL') ? \Env::AI_VISION_MODEL : ''));
        $url = trim((string) (defined('Env::AI_VISION_URL') ? \Env::AI_VISION_URL : 'https://api.openai.com/v1/chat/completions'));
        if ($apiKey === '' || $model === '') {
            $this->logVisionImage('api_config_missing key=' . ($apiKey !== '' ? 'yes' : 'no') . ' model=' . ($model !== '' ? $model : '-'));
            throw new \RuntimeException('AI vision model/key is not configured');
        }
        $this->logVisionImage('api_request_start model=' . $model . ' url=' . $url);

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];
        if (preg_match('/^gpt-5(?:[.-]|$)/i', $model)) {
            $payload['max_completion_tokens'] = 120;
        } else {
            $payload['temperature'] = 0;
            $payload['max_tokens'] = 120;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->logVisionImage('api_request_curl_failed error=' . $curlError);
            throw new \RuntimeException('Vision API cURL error: ' . $curlError);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logVisionImage('api_response_http_error code=' . $httpCode . ' body=' . substr((string) $response, 0, 500));
            throw new \RuntimeException('Vision API HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 300));
        }

        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $this->logVisionImage('api_response_empty_content http=' . $httpCode);
            throw new \RuntimeException('Vision API returned empty content');
        }

        $this->logVisionImage('api_response_success http=' . $httpCode . ' content_length=' . strlen($content));
        return trim($content);
    }

    private function sendPaymentImageResult(string $waNumber, array $result): void
    {
        $text = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($text === false) {
            return;
        }

        try {
            if (!class_exists('\\App\\Models\\WAReplies')) {
                require_once __DIR__ . '/../../Models/WAReplies.php';
            }
            $replies = new \App\Models\WAReplies();
            $method = new \ReflectionMethod($replies, 'sendQuotedFreeText');
            $method->setAccessible(true);
            $method->invoke($replies, $waNumber, $text);
        } catch (\Throwable $e) {
            \Log::write('Payment image result send failed: ' . $e->getMessage(), 'wa_error', 'Webhook');
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
     * Anchor pesan asli dari payload revoke (HP hapus pesan).
     * YCloud smb echo sering tidak kirim revoke.original_message_id — wamid/id pada whatsappMessage = pesan yang dihapus.
     */
    private function extractRevokedOriginalAnchor(array $msg): ?string
    {
        $candidates = [
            $msg['revoke']['original_message_id'] ?? null,
            $msg['revoke']['originalMessageId'] ?? null,
            $msg['context']['message_id'] ?? null,
            $msg['context']['id'] ?? null,
            $msg['wamid'] ?? null,
            $msg['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Hapus baris wa_messages_out + broadcast message_deleted ke CRM.
     */
    private function deleteOutboundMessageByAnchors($db, ?string $wamid, ?string $messageId, ?string $externalId, ?string $phoneHint = null, string $logPrefix = 'Outbound revoke'): bool
    {
        $existing = $this->findWaMessagesOutRowByAnchors($db, $wamid, $messageId, $externalId);
        if (!$existing) {
            \Log::write(
                $logPrefix . ': not found wamid=' . ($wamid ?: '-')
                . ' message_id=' . ($messageId ?: '-')
                . ' phone=' . ($phoneHint ?: '-'),
                'webhook',
                'WhatsApp'
            );

            return false;
        }

        $localId = (int) ($existing->id ?? 0);
        $phone = $existing->phone ?? $phoneHint;

        try {
            $db->delete('wa_messages_out', ['id' => $localId]);
        } catch (\Throwable $e) {
            \Log::write(
                $logPrefix . ' delete failed id=' . $localId . ': ' . $e->getMessage(),
                'wa_error',
                'Webhook'
            );

            return false;
        }

        \Log::write(
            $logPrefix . ' deleted outbound id=' . $localId
            . ' wamid=' . ($existing->wamid ?? '-')
            . ' phone=' . ($phone ?: '-'),
            'webhook',
            'WhatsApp'
        );

        $conv = $db->get_where('wa_conversations', ['wa_number' => $phone])->row();
        $conversationId = $conv ? (int) ($conv->id ?? 0) : 0;

        $this->pushIncomingToWebSocket([
            'type' => 'message_deleted',
            'target_id' => '0',
            'phone' => $phone,
            'conversation_id' => $conversationId,
            'message' => [
                'id' => $localId,
                'wamid' => $existing->wamid ?? $wamid,
            ],
        ]);

        return true;
    }

    /**
     * Hapus baris wa_messages_out saat staff revoke pesan dari WhatsApp Business app.
     * Revoke echo tidak disimpan sebagai outbound.
     */
    private function handleSmbOutboundRevoke($db, array $msg, ?string $waNumber): void
    {
        $anchor = $this->extractRevokedOriginalAnchor($msg);
        if (!$anchor) {
            \Log::write(
                'SMB revoke missing anchor | phone=' . ($waNumber ?: '-')
                . ' keys=' . implode(',', array_keys($msg)),
                'wa_error',
                'Webhook'
            );

            return;
        }

        $this->deleteOutboundMessageByAnchors($db, $anchor, $anchor, null, $waNumber, 'SMB revoke');
    }

    /**
     * Pesan outbound dikirim dari WhatsApp Business app (HP) — sync via YCloud smb.message.echoes.
     * Insert ke wa_messages_out jika belum ada (API/CRM send sudah insert → update status saja).
     */
    private function handleSmbMessageEchoes($db, $data)
    {
        $msg = $data['whatsappMessage'] ?? [];
        if (empty($msg)) {
            \Log::write('No whatsappMessage in smb.message.echoes', 'wa_error', 'Webhook');
            return;
        }

        $messageId = $msg['id'] ?? null;
        $wamid = $msg['wamid'] ?? null;
        $waNumber = $this->normalizePhoneNumber($msg['to'] ?? null);
        $messageType = $msg['type'] ?? 'text';

        if ($messageType === 'revoke') {
            if (!$waNumber) {
                \Log::write('SMB revoke missing to', 'wa_error', 'Webhook');
                return;
            }
            $this->handleSmbOutboundRevoke($db, $msg, $waNumber);

            return;
        }

        if (!$waNumber || (!$messageId && !$wamid)) {
            \Log::write('SMB echo missing to/messageId/wamid', 'wa_error', 'Webhook');
            return;
        }

        $existing = $this->findWaMessagesOutRowByAnchors($db, $wamid, $messageId, null);
        if ($existing) {
            $this->handleMessageUpdated($db, $data);
            return;
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WaLineResolver')) {
            require_once __DIR__ . '/../../Helpers/CRM/WaLineResolver.php';
        }
        $outboundLine = \App\Helpers\CRM\WaLineResolver::fromBusinessPhoneOrDefault($msg['from'] ?? null);
        $businessPhone = $outboundLine['phone'];
        $lineKey = $outboundLine['key'];

        $content = null;
        $mediaUrl = null;

        if ($messageType === 'text') {
            $content = $msg['text']['body'] ?? null;
        } elseif (isset($msg[$messageType]['link'])) {
            $mediaUrl = $msg[$messageType]['link'];
            $content = $msg[$messageType]['caption'] ?? null;
        } elseif ($messageType === 'location') {
            $lat = $msg['location']['latitude'] ?? null;
            $lng = $msg['location']['longitude'] ?? null;
            $locName = $msg['location']['name'] ?? null;
            $locAddr = $msg['location']['address'] ?? null;
            $content = '📍 ' . ($locName ?: ($locAddr ?: 'Shared Location'));
            if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
                $mediaUrl = "https://maps.google.com/maps?q={$lat},{$lng}";
            }
        } else {
            $typeLabels = [
                'image' => '📷 Image',
                'video' => '🎥 Video',
                'audio' => '🎵 Audio',
                'voice' => '🎤 Voice',
                'document' => '📄 Document',
                'sticker' => '🎨 Sticker',
            ];
            $content = $typeLabels[$messageType] ?? "[$messageType]";
            if (isset($msg[$messageType]['link'])) {
                $mediaUrl = $msg[$messageType]['link'];
            }
        }

        $quotedMessageId = null;
        if (isset($msg['context']['message_id'])) {
            $quotedMessageId = $msg['context']['message_id'];
        } elseif (isset($msg['context']['id'])) {
            $quotedMessageId = $msg['context']['id'];
        }

        $status = $msg['status'] ?? 'sent';
        // Pakai waktu server (WIB) — sama seperti WhatsAppService::saveOutboundMessage.
        // sendTime YCloud ISO (UTC) bikin bubble tampil di urutan salah di CRM getMessages.
        $sendTime = date('Y-m-d H:i:s');

        $isPrivate = false;
        try {
            $isPrivate = \EnvHelper::textContainsPrivateWord($content ?? '');
        } catch (\Throwable $e) {
            // ignore
        }

        $lastMessageText = $content ?: ($messageType === 'text' ? '' : ucfirst($messageType));
        if ($isPrivate) {
            $lastMessageDisplay = 'o- 🔒 _Private Chat_';
        } else {
            $lastMessageDisplay = 'o- ' . mb_substr((string) $lastMessageText, 0, 50);
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($cleanPhone, 2);
        $userData = $this->getUserData($phone0);
        $contactName = $msg['customerProfile']['name'] ?? null;
        $code = null;
        $custId = null;
        $assignedUserId = null;
        if ($userData) {
            if ($contactName === null && !empty($userData->customer_name)) {
                $contactName = $userData->customer_name;
            }
            $code = $userData->code ?? null;
            $custId = $userData->cust_id ?? null;
            $assignedUserId = $userData->assigned_user_id ?? null;
        }

        $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
        if ($conv && $conv->num_rows() > 0) {
            $convRow = $conv->row();
            $updateData = [
                'last_message' => $lastMessageDisplay,
                'last_message_at' => $sendTime,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($contactName) {
                $updateData['contact_name'] = $contactName;
            }
            if ($code) {
                $updateData['code'] = $code;
            }
            if ($custId !== null) {
                $updateData['cust_id'] = $custId;
            }
            $existingAssigned = $convRow->assigned_user_id ?? null;
            if (
                $assignedUserId !== null && $assignedUserId !== ''
                && ($existingAssigned === null || $existingAssigned === '')
            ) {
                $updateData['assigned_user_id'] = $assignedUserId;
            }
            $db->update('wa_conversations', $updateData, ['wa_number' => $waNumber]);
        } else {
            $convData = [
                'wa_number' => $waNumber,
                'status' => 'closed',
                'last_message' => $lastMessageDisplay,
                'last_message_at' => $sendTime,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if ($contactName) {
                $convData['contact_name'] = $contactName;
            }
            if ($code) {
                $convData['code'] = $code;
            }
            if ($custId !== null) {
                $convData['cust_id'] = $custId;
            }
            if ($assignedUserId !== null && $assignedUserId !== '') {
                $convData['assigned_user_id'] = $assignedUserId;
            }
            $db->insert('wa_conversations', $convData);
        }

        $messageData = [
            'phone' => $waNumber,
            'business_phone' => $businessPhone,
            'wamid' => $wamid,
            'message_id' => $messageId,
            'type' => $messageType,
            'content' => $content,
            'media_url' => $mediaUrl,
            // smb.message.echoes berasal dari WhatsApp Business app (HP), bukan autoreply.
            // Kode ini menjadi penanda human-active agar DEFAULT fallback tidak terkirim.
            'sender_code' => 'HP',
            'status' => $status,
            'private' => $isPrivate ? 1 : 0,
            'created_at' => $sendTime,
        ];
        if ($quotedMessageId !== null) {
            $messageData['quoted_message_id'] = $quotedMessageId;
        }

        $msgId = $db->insert('wa_messages_out', $messageData);
        if (!$msgId) {
            \Log::write(
                'SMB echo insert failed | phone=' . $waNumber . ' message_id=' . ($messageId ?: '-'),
                'wa_error',
                'Webhook'
            );
            return;
        }

        \Log::write(
            'SMB echo saved id=' . $msgId . ' phone=' . $waNumber . ' line=' . $businessPhone
            . ' preview=' . mb_substr((string) ($content ?? ''), 0, 40),
            'webhook',
            'WhatsApp'
        );

        $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
        $conversationId = 0;
        $convStatus = 'closed';
        $wsAssignedUserId = $assignedUserId;
        $kodeCabang = $code ?? '00';
        if ($conv && $conv->num_rows() > 0) {
            $convRow = $conv->row();
            $conversationId = (int) ($convRow->id ?? 0);
            $convStatus = $convRow->status ?? 'closed';
            $existingAssigned = $convRow->assigned_user_id ?? null;
            if ($existingAssigned !== null && $existingAssigned !== '') {
                $wsAssignedUserId = $existingAssigned;
            }
            if (!empty($convRow->code)) {
                $kodeCabang = $convRow->code;
            }
        }

        $wsLineMeta = \App\Helpers\CRM\WaLineResolver::messageApiFields($lineKey);
        $this->pushIncomingToWebSocket([
            'type' => 'agent_message_sent',
            'target_id' => '0',
            'conversation_id' => $conversationId,
            'phone' => $waNumber,
            'contact_name' => $contactName,
            'kode_cabang' => $kodeCabang,
            'cust_id' => $custId,
            'status' => $convStatus,
            'assignment_user_id' => $wsAssignedUserId,
            'sender_id' => 0,
            'message' => [
                'id' => $lineKey . '-' . $msgId,
                'wamid' => $wamid,
                'text' => $content,
                'type' => $messageType,
                'media_url' => $mediaUrl,
                'sender_code' => null,
                'quoted_message_id' => $quotedMessageId,
                'time' => $sendTime,
                'status' => $status,
                'line_key' => $lineKey,
                'business_phone' => $businessPhone,
                'line_label' => $wsLineMeta['line_label'] ?? null,
                'provider' => $lineKey,
            ],
        ]);
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

        if (strtolower((string) $status) === 'revoked') {
            $phoneHint = null;
            $existing = $this->findWaMessagesOutRowByAnchors($db, $wamid, $messageId, $externalId);
            if ($existing) {
                $phoneHint = $existing->phone ?? null;
            }
            $this->deleteOutboundMessageByAnchors(
                $db,
                $wamid,
                $messageId,
                $externalId,
                $phoneHint,
                'Status update revoked'
            );

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

        if (strtolower((string) $status) === 'revoked') {
            $waNumber = $this->normalizePhoneNumber($message['to'] ?? null);
            $this->deleteOutboundMessageByAnchors(
                $db,
                $wamid,
                $messageId,
                $externalId,
                $waNumber,
                'Message updated revoked'
            );

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
        if (!$isoTime) {
            return date('Y-m-d H:i:s');
        }

        try {
            $dt = new \DateTime($isoTime);
            $dt->setTimezone(new \DateTimeZone('Asia/Jakarta'));

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
        $return->assigned_user_id = \App\Helpers\CRM\WaSenderContext::cswAssignedUserId($ctx);
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
