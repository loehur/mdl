<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * Fonnte WhatsApp Webhook Handler
 * Alur intent & balasan sama seperti Webhook\WhatsApp (WAReplies::process), tetapi:
 * - Riwayat chat disimpan ke wa_fonnte_messages_in/out (terpisah dari yCloud)
 * - Tidak mengubah / membuat wa_conversations (setSkipConversationPersist)
 * - Balasan keluar via FonnteReplyAdapter (Fonnte API), termasuk inboxid bila ada
 * - wa_fonnte_csw tetap di-update untuk CSW Fonnte
 *
 * @see https://docs.fonnte.com/
 * URL: /Webhook/WA_Fonnte
 */
class WA_Fonnte extends Controller
{
    private const DEFAULT_FALLBACK_REPLY_FONNTE = "Maaf, mohon menunggu. Admin sedang melayani customer lain.\n\nUntuk balasan otomatis, silahkan ketik:\n- *BON* untuk info nota\n- *CEK* untuk info status\n- *BILL* untuk info tagihan\n\nUntuk informasi lainnya, kirimkan pesan ke *Madinah Laundry (CS)*\n💬 wa.me/6281170706611";

    /** Cooldown wa_auto_reply_log (handler DEFAULT) sebelum fallback dikirim lagi ke nomor yang sama — 24 jam */
    private const DEFAULT_FALLBACK_COOLDOWN_MINUTES = 1440;

    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            echo json_encode(['status' => 'ok', 'message' => 'Fonnte webhook endpoint']);

            return;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

            return;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data) {
            \Log::write('WA_Fonnte: Invalid JSON', 'webhook', 'Fonnte');
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);

            return;
        }

        // Webhook status outbound (API send): update by fonnte_message_id, tanpa insert baru
        if ($this->isFonnteStatusWebhook($data)) {
            $this->handleFonnteStatusWebhook($data);
            echo json_encode(['status' => 'ok']);

            return;
        }

        $sender = $data['sender'] ?? null;
        $message = $data['message'] ?? null;
        $text = $data['text'] ?? null;
        $name = $data['name'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
        $inboxid = $data['inboxid'] ?? null;
        $url = $data['url'] ?? null;
        $filename = $data['filename'] ?? null;
        // Pin lokasi WhatsApp: Fonnte kirim "latitude,longitude" di field location (bukan di message)
        $location = trim((string) ($data['location'] ?? ''));

        $replyText = '';

        $rawText = trim((string) ($message ?? $text ?? ''));
        // Gambar/file/voice dari Fonnte biasanya punya url; tanpa caption = anggap panjang teks 0 (tidak intent AI, tidak DEFAULT_FALLBACK_REPLY_FONNTE)
        $isMediaWithoutCaption = ($rawText === '' && ! empty($url) && $location === '');

        if ($rawText === '' && empty($url) && $location === '') {
            echo json_encode(['status' => 'ok', 'reply' => $replyText]);

            return;
        }

        $waNumber = $this->normalizeWaNumber($sender);
        if (! $waNumber) {
            echo json_encode(['status' => 'ok', 'reply' => $replyText]);

            return;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($cleanPhone, 2);
        if (! class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../../Helpers/CRM/WaSenderContext.php';
        }
        $senderCtx = \App\Helpers\CRM\WaSenderContext::resolve($waNumber);
        $isKaryawan = !empty($senderCtx['is_karyawan']);
        $customerCtx = [
            'contact_name' => $senderCtx['contact_name'] ?: ($name !== null && $name !== '' ? (string) $name : null),
            'assigned_user_id' => $isKaryawan ? null : \App\Helpers\CRM\WaSenderContext::cswAssignedUserId($senderCtx),
            'code' => $senderCtx['code'],
            'cust_id' => $senderCtx['cust_id'] ?: null,
            'is_karyawan' => $isKaryawan,
        ];
        if (!$isKaryawan && $customerCtx['assigned_user_id'] === null) {
            $customerCtx = $this->mergeAssignmentFromExistingConversations($waNumber, $customerCtx);
        }

        if ($isMediaWithoutCaption) {
            $this->recordFonnteIncoming($waNumber, $timestamp);
            $msgId = $this->saveFonnteIncomingMessage($waNumber, $data, '', null, $customerCtx);
            if ($msgId) {
                $lastMessage = 'i- 📎 Media';
                $createdAt = $this->fonnteTimestampToLastInAt($timestamp);
                $this->pushCrmFonnteInbound(
                    $waNumber,
                    $customerCtx,
                    $lastMessage,
                    $createdAt,
                    (int) $msgId,
                    $inboxid,
                    '',
                    'image',
                    $url !== '' ? (string) $url : null
                );
            }
            echo json_encode(['status' => 'ok', 'reply' => $replyText]);

            return;
        }

        // Gabungkan koordinat pin ke teks agar kurirExtractCoords / Maps flow bisa baca
        $messageText = $rawText;
        if ($location !== '' && preg_match('/^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$/', $location)) {
            $messageText = trim($messageText . ' ' . $location);
            if ($messageText === $location || $rawText === '') {
                $messageText = "📍 Shared Location {$location}";
            }
        }

        $phonePlus = '+' . $cleanPhone;
        $phoneNoPrefix = substr($cleanPhone, 2);
        $phones = ["'$cleanPhone'", "'$phone0'", "'$phonePlus'", "'$phoneNoPrefix'"];
        $phoneIn = implode(',', $phones);

        $assigned_user_id = $customerCtx['assigned_user_id'] ?? null;
        $code = $customerCtx['code'] ?? null;
        $cust_id = $customerCtx['cust_id'] ?? null;
        $contact_name = $customerCtx['contact_name'] ?? $name;

        try {
            $lastMessageSummary = $messageText;
            $isPrivateForLastMessage = false;
            try {
                $isPrivateForLastMessage = \EnvHelper::textContainsPrivateWord($lastMessageSummary ?? '');
            } catch (\Throwable $e) {
                // Jangan gagalkan simpan chat jika cek private error
            }
            $lastMessage = $isPrivateForLastMessage
                ? 'i- 🔒 _Private Chat_'
                : 'i- ' . mb_substr($lastMessageSummary, 0, 50);

            if (! class_exists('\\App\\Models\\WAReplies')) {
                require_once __DIR__ . '/../../Models/WAReplies.php';
            }
            if (! class_exists('\\App\\Helpers\\FonnteReplyAdapter')) {
                require_once __DIR__ . '/../../Helpers/CRM/FonnteReplyAdapter.php';
            }
            if (! class_exists('\\App\\Helpers\\CRM\\FonnteMessageStore')) {
                require_once __DIR__ . '/../../Helpers/CRM/FonnteMessageStore.php';
            }

            $messageStore = new \App\Helpers\CRM\FonnteMessageStore($this->db(0));
            $messageStore->setCustomerContext($customerCtx);

            // CSW Fonnte harus ter-commit dulu; baru simpan chat + handle intent.
            $this->recordFonnteIncoming($waNumber, $timestamp);
            $savedMsgId = $this->saveFonnteIncomingMessage($waNumber, $data, $messageText, $messageStore, $customerCtx);
            if ($messageStore->lastIncomingWasDuplicate()) {
                \Log::write('WA_Fonnte: skip process duplicate inboxid=' . (string) ($inboxid ?? ''), 'webhook', 'Fonnte');
                echo json_encode(['status' => 'ok', 'reply' => $replyText, 'duplicate' => true]);

                return;
            }

            if ($savedMsgId) {
                $createdAt = $this->fonnteTimestampToLastInAt($timestamp);
                $this->pushCrmFonnteInbound(
                    $waNumber,
                    $customerCtx,
                    $lastMessage,
                    $createdAt,
                    (int) $savedMsgId,
                    $inboxid,
                    $messageText,
                    'text',
                    null
                );
            }

            $replies = new \App\Models\WAReplies();
            $fonnteAdapter = new \App\Helpers\CRM\FonnteReplyAdapter($inboxid, $messageStore);
            $replies->setCustomSender($fonnteAdapter);
            $replies->setSkipConversationPersist(true);
            $replies->setAutoReplyProvider('B');
            $replies->setSenderContext($senderCtx);
            if ($inboxid !== null && is_numeric($inboxid) && (int) $inboxid > 0) {
                $replies->setInboundReplyToMessageId((int) $inboxid);
            }

            $processResult = $replies->process(
                $phoneIn,
                $messageText,
                $waNumber,
                $contact_name,
                $assigned_user_id,
                $code,
                $lastMessage,
                $cust_id
            );
            // DEFAULT fallback: no_handler dari process() = intent FALSE + ask true (atau jalur CS lain tanpa handler).
            // Pesan pendek (≤20 karakter) tidak dibalas fallback CS agar tidak mengganggu salam/stiker singkat.
            if (!empty($processResult->no_handler) && mb_strlen(trim((string) ($messageText ?? ''))) > 20) {
                $replies->trySendDefaultFallbackAutoreply(
                    $phoneIn,
                    $waNumber,
                    $messageText,
                    self::DEFAULT_FALLBACK_REPLY_FONNTE,
                    self::DEFAULT_FALLBACK_COOLDOWN_MINUTES
                );
            }

            $currentCase = $processResult->case ?? null;
            if ($currentCase !== null && (int) $currentCase !== 0) {
                $this->pushCrmFonnteCaseUpdated(
                    $waNumber,
                    (int) $currentCase,
                    $assigned_user_id,
                    $code,
                    $cust_id,
                    $processResult->notify ?? false
                );
            }
        } catch (\Throwable $e) {
            \Log::write('WA_Fonnte WAReplies: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine(), 'webhook', 'Fonnte');
        }

        echo json_encode(['status' => 'ok', 'reply' => $replyText]);
    }

    /**
     * Simpan pesan masuk ke wa_fonnte_messages_in (+ ringkasan conversation).
     *
     * @param \App\Helpers\CRM\FonnteMessageStore|null $store
     * @param array{contact_name?:string,assigned_user_id?:int|string|null,code?:string|null,cust_id?:int|string|null} $customerCtx
     * @return int|null
     */
    private function saveFonnteIncomingMessage(string $waNumber, array $data, string $messageText, $store = null, array $customerCtx = []): ?int
    {
        try {
            if ($store === null) {
                if (! class_exists('\\App\\Helpers\\CRM\\FonnteMessageStore')) {
                    require_once __DIR__ . '/../../Helpers/CRM/FonnteMessageStore.php';
                }
                $store = new \App\Helpers\CRM\FonnteMessageStore($this->db(0));
            }

            return $store->saveIncoming($waNumber, $data, $messageText, $customerCtx);
        } catch (\Throwable $e) {
            \Log::write('WA_Fonnte: save incoming message failed: ' . $e->getMessage(), 'webhook', 'Fonnte');

            return null;
        }
    }

    /**
     * Push inbound Fonnte ke CRM (WS only, OneSignal selalu false).
     *
     * @param array{contact_name?:?string,assigned_user_id?:int|string|null,code?:?string,cust_id?:int|string|null} $customerCtx
     */
    private function pushCrmFonnteInbound(
        string $waNumber,
        array $customerCtx,
        string $lastMessage,
        string $createdAt,
        int $msgId,
        $inboxid,
        string $messageText,
        string $messageType,
        ?string $mediaUrl
    ): void {
        if (! class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
            require_once __DIR__ . '/../../Helpers/CRM/CrmChatMergeHelper.php';
        }
        $db = $this->db(0);
        $conversationId = \App\Helpers\CRM\CrmChatMergeHelper::ensureShellFromFonnte(
            $db,
            $waNumber,
            $customerCtx,
            $lastMessage,
            $createdAt
        );

        \App\Helpers\CRM\CrmChatMergeHelper::pushWebSocket([
            'type' => 'wa_masuk',
            'conversation_id' => $conversationId,
            'phone' => $waNumber,
            'contact_name' => $customerCtx['contact_name'] ?? null,
            'case' => null,
            'active_cases' => [],
            'notify' => false,
            'assignment_user_id' => $customerCtx['assigned_user_id'] ?? null,
            'status' => 'open',
            'target_id' => '0',
            'kode_cabang' => $customerCtx['code'] ?? '00',
            'cust_id' => $customerCtx['cust_id'] ?? null,
            'message' => [
                'id' => 'F-' . $msgId,
                'wamid' => ($inboxid !== null && is_numeric($inboxid)) ? (string) (int) $inboxid : null,
                'text' => $messageText,
                'type' => $messageType,
                'media_url' => $mediaUrl,
                'time' => $createdAt,
                'sender' => 'customer',
                'provider' => 'F',
            ],
        ]);
    }

    private function pushCrmFonnteCaseUpdated(
        string $waNumber,
        int $case,
        $assignedUserId,
        ?string $code,
        $custId,
        bool $intentNotify
    ): void {
        if (! class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
            require_once __DIR__ . '/../../Helpers/CRM/CrmChatMergeHelper.php';
        }
        $db = $this->db(0);
        $conv = \App\Helpers\CRM\CrmChatMergeHelper::findWaConversation($db, $waNumber);
        $activeCases = [];
        if ($conv && !empty($conv->conv_case)) {
            $decoded = json_decode((string) $conv->conv_case, true);
            if (is_array($decoded)) {
                $list = isset($decoded[0]) ? $decoded : [$decoded];
                foreach ($list as $c) {
                    if (isset($c['case'], $c['status']) && $c['status'] === 'open') {
                        $activeCases[] = (int) $c['case'];
                    }
                }
            }
        }
        if ($activeCases === []) {
            $activeCases = [$case];
        }

        \App\Helpers\CRM\CrmChatMergeHelper::pushWebSocket([
            'type' => 'case_updated',
            'phone' => $waNumber,
            'conversation_id' => $conv ? (int) ($conv->id ?? 0) : 0,
            'case' => $case,
            'active_cases' => $activeCases,
            'notify' => false,
            'assignment_user_id' => $assignedUserId,
            'target_id' => '0',
            'kode_cabang' => $code ?? '00',
            'cust_id' => $custId,
        ]);
        unset($intentNotify);
    }

    /**
     * Resolve cabang/pelanggan — sama logika yCloud (getUserData + fallback conversation).
     *
     * @return array{contact_name:?string,assigned_user_id:?int,code:?string,cust_id:?int}
     */
    private function resolveFonnteCustomerContext(string $phone0, string $waNumber, $fonnteName = null): array
    {
        $ctx = [
            'contact_name' => $fonnteName !== null && $fonnteName !== '' ? (string) $fonnteName : null,
            'assigned_user_id' => null,
            'code' => null,
            'cust_id' => null,
        ];

        try {
            $wh = new WhatsApp();
            $userData = $wh->getUserData($phone0);
            if ($userData) {
                if (!empty($userData->customer_name)) {
                    $ctx['contact_name'] = $userData->customer_name;
                }
                if (!empty($userData->assigned_user_id)) {
                    $ctx['assigned_user_id'] = (int) $userData->assigned_user_id;
                }
                if (!empty($userData->code)) {
                    $ctx['code'] = (string) $userData->code;
                }
                if (!empty($userData->cust_id)) {
                    $ctx['cust_id'] = (int) $userData->cust_id;
                }
            }
        } catch (\Throwable $e) {
            // lanjut fallback conversation
        }

        if ($ctx['assigned_user_id'] === null) {
            $senderIsKaryawan = false;
            try {
                if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
                    require_once __DIR__ . '/../../Helpers/CRM/WaSenderContext.php';
                }
                $senderIsKaryawan = \App\Helpers\CRM\WaSenderContext::isKaryawanNumber($waNumber);
            } catch (\Throwable $e) {
                // ignore
            }
            if (!$senderIsKaryawan) {
                $ctx = $this->mergeAssignmentFromExistingConversations($waNumber, $ctx);
            }
        }

        return $ctx;
    }

    /**
     * @param array{contact_name:?string,assigned_user_id:?int,code:?string,cust_id:?int} $ctx
     * @return array{contact_name:?string,assigned_user_id:?int,code:?string,cust_id:?int}
     */
    private function mergeAssignmentFromExistingConversations(string $waNumber, array $ctx): array
    {
        $db = $this->db(0);
        $variants = $this->phoneVariantsForLookup($waNumber);

        foreach (['wa_fonnte_conversations', 'wa_conversations'] as $table) {
            foreach ($variants as $phone) {
                $row = $db->query(
                    "SELECT contact_name, assigned_user_id, code, cust_id
                     FROM {$table}
                     WHERE " . ($table === 'wa_conversations' ? 'wa_number' : 'phone') . " = ?
                     LIMIT 1",
                    [$phone]
                )->row();
                if (!$row) {
                    continue;
                }
                if (empty($ctx['contact_name']) && !empty($row->contact_name)) {
                    $ctx['contact_name'] = $row->contact_name;
                }
                if ($ctx['assigned_user_id'] === null && !empty($row->assigned_user_id)) {
                    $ctx['assigned_user_id'] = (int) $row->assigned_user_id;
                }
                if (empty($ctx['code']) && !empty($row->code)) {
                    $ctx['code'] = (string) $row->code;
                }
                if ($ctx['cust_id'] === null && !empty($row->cust_id)) {
                    $ctx['cust_id'] = (int) $row->cust_id;
                }
                if ($ctx['assigned_user_id'] !== null) {
                    return $ctx;
                }
            }
        }

        return $ctx;
    }

    /** @return string[] */
    private function phoneVariantsForLookup(string $waNumber): array
    {
        $clean = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($clean, 2);

        return array_values(array_unique(array_filter([
            $waNumber,
            '+' . $clean,
            $clean,
            $phone0,
            substr($clean, 2),
        ])));
    }

    /**
     * Payload status Fonnte: id + status/state, tanpa sender inbound.
     * @see https://docs.fonnte.com/webhook-update-message-status/
     */
    private function isFonnteStatusWebhook(array $data): bool
    {
        $hasId = isset($data['id']) && $data['id'] !== '' && $data['id'] !== null;
        $hasStatusOrState = (isset($data['status']) && $data['status'] !== '')
            || (isset($data['state']) && $data['state'] !== '')
            || (isset($data['stateid']) && $data['stateid'] !== '');
        $looksInbound = isset($data['sender']) && $data['sender'] !== '';

        return $hasId && $hasStatusOrState && !$looksInbound;
    }

    /**
     * Update wa_fonnte_messages_out by fonnte_message_id; set sender_code=AR bila kosong.
     */
    private function handleFonnteStatusWebhook(array $data): void
    {
        try {
            $rawId = $data['id'] ?? null;
            $ids = is_array($rawId) ? $rawId : [$rawId];
            $status = isset($data['status']) ? (string) $data['status'] : (isset($data['state']) ? (string) $data['state'] : '');

            if (! class_exists('\\App\\Helpers\\CRM\\FonnteMessageStore')) {
                require_once __DIR__ . '/../../Helpers/CRM/FonnteMessageStore.php';
            }
            if (! class_exists('\\App\\Helpers\\CRM\\SapaanStatsHelper')) {
                require_once __DIR__ . '/../../Helpers/CRM/SapaanStatsHelper.php';
            }

            $store = new \App\Helpers\CRM\FonnteMessageStore($this->db(0));
            foreach ($ids as $id) {
                $idStr = trim((string) $id);
                if ($idStr === '') {
                    continue;
                }
                $ok = $store->updateOutgoingByFonnteMessageId($idStr, [
                    'status' => $status !== '' ? $status : 'sent',
                    'sender_code' => \App\Helpers\CRM\SapaanStatsHelper::SENDER_CODE_AUTOREPLY,
                ]);
                if (!$ok && class_exists('\Log')) {
                    \Log::write("WA_Fonnte status: no out row for fonnte_message_id={$idStr}", 'webhook', 'Fonnte');
                }
            }
        } catch (\Throwable $e) {
            \Log::write('WA_Fonnte status webhook: ' . $e->getMessage(), 'webhook', 'Fonnte');
        }
    }

    /**
     * Simpan waktu pesan masuk terakhir per nomor (db 0) untuk CSW Fonnte.
     * Dipanggil dengan $waNumber yang sama persis dengan alur process() (sudah dinormalisasi).
     * Transaksi singkat: commit sebelum WAReplies::process agar tidak ada race dengan pembaca last_in_at.
     */
    private function recordFonnteIncoming(string $waNumber, $timestamp = null): void
    {
        if ($waNumber === '') {
            return;
        }
        $lastInAt = $this->fonnteTimestampToLastInAt($timestamp);
        try {
            $db = $this->db(0);
            if (! $db->beginTransaction()) {
                \Log::write('WA_Fonnte: wa_fonnte_csw beginTransaction failed', 'webhook', 'Fonnte');

                return;
            }
            try {
                $check = $db->query(
                    'SELECT id FROM wa_fonnte_csw WHERE phone = ? LIMIT 1',
                    [$waNumber]
                );
                if ($check->num_rows() > 0) {
                    $db->query(
                        'UPDATE wa_fonnte_csw SET last_in_at = ? WHERE phone = ?',
                        [$lastInAt, $waNumber]
                    );
                } else {
                    $db->query(
                        'INSERT INTO wa_fonnte_csw (phone, last_in_at) VALUES (?, ?)',
                        [$waNumber, $lastInAt]
                    );
                }
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            \Log::write('WA_Fonnte: wa_fonnte_csw update failed: ' . $e->getMessage(), 'webhook', 'Fonnte');
        }
    }

    private function fonnteTimestampToLastInAt($timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return date('Y-m-d H:i:s');
        }
        if (is_numeric($timestamp)) {
            $ts = (int) $timestamp;
            if ($ts > 9999999999) {
                $ts = (int) ($ts / 1000);
            }

            return date('Y-m-d H:i:s', $ts);
        }
        $s = substr((string) $timestamp, 0, 40);

        return $s !== '' ? $s : date('Y-m-d H:i:s');
    }

    private function sendFallbackReply($target, $message, $inboxid = null): void
    {
        if (empty($target) || empty($message)) {
            return;
        }

        if (! class_exists('\\App\\Helpers\\FonnteService')) {
            require_once __DIR__ . '/../../Helpers/CRM/FonnteService.php';
        }

        $fonnte = new \App\Helpers\CRM\FonnteService();
        $options = [];
        if ($inboxid) {
            $options['inboxid'] = (int) $inboxid;
        }
        $fonnte->sendMessage($target, $message, $options);
    }

    /**
     * Normalize sender ke format wa_number (+62xxx)
     */
    private function normalizeWaNumber($sender)
    {
        if (empty($sender)) {
            return null;
        }
        $clean = preg_replace('/[^0-9]/', '', $sender);
        if (strlen($clean) < 8) {
            return null;
        }
        if (substr($clean, 0, 1) === '0') {
            $clean = '62' . substr($clean, 1);
        } elseif (substr($clean, 0, 2) !== '62') {
            $clean = '62' . $clean;
        }

        return '+' . $clean;
    }
}
