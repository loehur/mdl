<?php

namespace App\Helpers\CRM;

/**
 * Simpan riwayat chat Fonnte ke tabel terpisah (wa_fonnte_messages_in/out).
 */
class FonnteMessageStore
{
    /** @var object */
    private $db;

    /** @var array{contact_name?:string,assigned_user_id?:int|string|null,code?:string|null,cust_id?:int|string|null} */
    private $customerContext = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param array{contact_name?:string,assigned_user_id?:int|string|null,code?:string|null,cust_id?:int|string|null} $context
     */
    public function setCustomerContext(array $context): void
    {
        $this->customerContext = array_merge($this->customerContext, $context);
    }

    /**
     * Simpan pesan masuk dari webhook Fonnte.
     *
     * @param array{contact_name?:string,assigned_user_id?:int|string|null,code?:string|null,cust_id?:int|string|null} $context
     * @return int|null ID baris baru, atau null jika duplikat / gagal
     */
    public function saveIncoming(string $waNumber, array $webhook, string $messageText, array $context = []): ?int
    {
        if ($waNumber === '') {
            return null;
        }

        if ($context !== []) {
            $this->setCustomerContext($context);
        }

        $inboxid = isset($webhook['inboxid']) && $webhook['inboxid'] !== '' && $webhook['inboxid'] !== null
            ? (int) $webhook['inboxid']
            : null;

        if ($inboxid !== null && $inboxid > 0) {
            $dupe = $this->db->get_where('wa_fonnte_messages_in', ['inboxid' => $inboxid])->row();
            if ($dupe) {
                return (int) ($dupe->id ?? 0) ?: null;
            }
        }

        $url = trim((string) ($webhook['url'] ?? ''));
        $filename = trim((string) ($webhook['filename'] ?? ''));
        $extension = trim((string) ($webhook['extension'] ?? ''));
        $location = trim((string) ($webhook['location'] ?? ''));
        $name = trim((string) ($webhook['name'] ?? ''));
        $device = trim((string) ($webhook['device'] ?? ''));
        $member = trim((string) ($webhook['member'] ?? ''));

        $contactName = $this->pickContactName($name);

        $type = $this->detectIncomingType($messageText, $url, $location, $extension);
        $createdAt = $this->timestampToDatetime($webhook['timestamp'] ?? null);

        $row = [
            'phone' => $waNumber,
            'contact_name' => $contactName,
            'type' => $type,
            'text' => $messageText !== '' ? $messageText : null,
            'media_url' => $url !== '' ? mb_substr($url, 0, 512) : null,
            'media_filename' => $filename !== '' ? mb_substr($filename, 0, 255) : null,
            'media_extension' => $extension !== '' ? mb_substr($extension, 0, 32) : null,
            'location' => $location !== '' ? mb_substr($location, 0, 64) : null,
            'inboxid' => ($inboxid !== null && $inboxid > 0) ? $inboxid : null,
            'fonnte_device' => $device !== '' ? mb_substr($device, 0, 32) : null,
            'member' => $member !== '' ? mb_substr($member, 0, 64) : null,
            'created_at' => $createdAt,
        ];

        $msgId = $this->db->insert('wa_fonnte_messages_in', $row);
        if (!$msgId) {
            if (class_exists('\Log')) {
                $err = $this->db->conn()->error ?? 'unknown';
                \Log::write('FonnteMessageStore: insert wa_fonnte_messages_in failed: ' . $err, 'webhook', 'Fonnte');
            }

            return null;
        }

        $this->touchConversationInbound($waNumber, $contactName, $messageText, $type, $createdAt);

        return (int) $msgId;
    }

    /**
     * Simpan pesan keluar (autoreply / human via Fonnte API).
     * Jika fonnte_message_id sudah ada → update baris itu (tanpa insert baru).
     *
     * @return int|null
     */
    public function saveOutgoing(string $waNumber, string $text, array $meta = []): ?int
    {
        if ($waNumber === '' || trim($text) === '') {
            return null;
        }

        if (!class_exists(SapaanStatsHelper::class)) {
            require_once __DIR__ . '/SapaanStatsHelper.php';
        }

        $fonnteId = !empty($meta['fonnte_message_id']) ? mb_substr((string) $meta['fonnte_message_id'], 0, 64) : null;
        $senderCode = array_key_exists('sender_code', $meta)
            ? (trim((string) $meta['sender_code']) !== '' ? mb_substr(trim((string) $meta['sender_code']), 0, 32) : null)
            : SapaanStatsHelper::SENDER_CODE_AUTOREPLY;
        $source = (string) ($meta['source'] ?? (
            SapaanStatsHelper::isHumanSenderCode($senderCode) ? 'human' : 'autoreply'
        ));

        $row = [
            'phone' => $waNumber,
            'type' => (string) ($meta['type'] ?? 'text'),
            'text' => $text,
            'media_url' => !empty($meta['media_url']) ? mb_substr((string) $meta['media_url'], 0, 512) : null,
            'fonnte_message_id' => $fonnteId,
            'reply_inboxid' => !empty($meta['reply_inboxid']) ? (int) $meta['reply_inboxid'] : null,
            'source' => $source,
            'sender_code' => $senderCode,
            'handler' => !empty($meta['handler']) ? mb_substr((string) $meta['handler'], 0, 64) : null,
            'status' => (string) ($meta['status'] ?? 'sent'),
            'error_text' => !empty($meta['error_text']) ? mb_substr((string) $meta['error_text'], 0, 255) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Match by fonnte_message_id: update existing, jangan insert duplikat
        if ($fonnteId !== null && $fonnteId !== '') {
            $existing = $this->db->get_where('wa_fonnte_messages_out', [
                'fonnte_message_id' => $fonnteId,
            ], 1)->row();
            if ($existing) {
                $update = [
                    'phone' => $waNumber,
                    'type' => $row['type'],
                    'text' => $text,
                    'media_url' => $row['media_url'],
                    'reply_inboxid' => $row['reply_inboxid'],
                    'source' => $source,
                    'sender_code' => $senderCode,
                    'handler' => $row['handler'],
                    'status' => $row['status'],
                    'error_text' => $row['error_text'],
                ];
                $ok = $this->db->update('wa_fonnte_messages_out', $update, ['id' => (int) $existing->id]);
                if (!$ok && class_exists('\Log')) {
                    $err = $this->db->conn()->error ?? 'unknown';
                    \Log::write('FonnteMessageStore: update by fonnte_message_id failed: ' . $err, 'webhook', 'Fonnte');
                }
                $this->touchConversationOutbound($waNumber, $text, date('Y-m-d H:i:s'));

                return (int) ($existing->id ?? 0) ?: null;
            }
        }

        $msgId = $this->db->insert('wa_fonnte_messages_out', $row);
        if (!$msgId) {
            if (class_exists('\Log')) {
                $err = $this->db->conn()->error ?? 'unknown';
                \Log::write('FonnteMessageStore: insert wa_fonnte_messages_out failed: ' . $err, 'webhook', 'Fonnte');
            }

            return null;
        }

        $this->touchConversationOutbound($waNumber, $text, $row['created_at']);
        // Sapaan hanya saat insert baru (update by fonnte_message_id tidak dihitung ulang)
        if (in_array($row['status'], ['sent', 'delivered', 'read'], true)) {
            SapaanStatsHelper::recordStatsIfHuman($this->db, $waNumber, $text, $senderCode);
        }

        return (int) $msgId;
    }

    /**
     * Update outbound by fonnte_message_id (webhook status). Tidak insert baru.
     * Mengisi sender_code=AR bila masih kosong (pesan API/autoreply).
     *
     * @param array{status?:string,state?:string,sender_code?:string} $fields
     */
    public function updateOutgoingByFonnteMessageId(string $fonnteMessageId, array $fields = []): bool
    {
        $fonnteMessageId = trim($fonnteMessageId);
        if ($fonnteMessageId === '') {
            return false;
        }

        $existing = $this->db->get_where('wa_fonnte_messages_out', [
            'fonnte_message_id' => mb_substr($fonnteMessageId, 0, 64),
        ], 1)->row();
        if (!$existing) {
            return false;
        }

        if (!class_exists(SapaanStatsHelper::class)) {
            require_once __DIR__ . '/SapaanStatsHelper.php';
        }

        $update = [];
        if (isset($fields['status']) && $fields['status'] !== '') {
            $update['status'] = mb_substr((string) $fields['status'], 0, 32);
        }
        if (isset($fields['error_text'])) {
            $update['error_text'] = $fields['error_text'] !== null && $fields['error_text'] !== ''
                ? mb_substr((string) $fields['error_text'], 0, 255)
                : null;
        }

        $currentCode = trim((string) ($existing->sender_code ?? ''));
        if ($currentCode === '') {
            $update['sender_code'] = isset($fields['sender_code']) && trim((string) $fields['sender_code']) !== ''
                ? mb_substr(trim((string) $fields['sender_code']), 0, 32)
                : SapaanStatsHelper::SENDER_CODE_AUTOREPLY;
            if (($existing->source ?? '') === 'autoreply' || ($existing->source ?? '') === '') {
                $update['source'] = 'autoreply';
            }
        } elseif (isset($fields['sender_code']) && trim((string) $fields['sender_code']) !== '') {
            $update['sender_code'] = mb_substr(trim((string) $fields['sender_code']), 0, 32);
        }

        if ($update === []) {
            return true;
        }

        $ok = $this->db->update('wa_fonnte_messages_out', $update, ['id' => (int) $existing->id]);
        if (!$ok && class_exists('\Log')) {
            $err = $this->db->conn()->error ?? 'unknown';
            \Log::write('FonnteMessageStore: updateOutgoingByFonnteMessageId failed: ' . $err, 'webhook', 'Fonnte');
        }

        return (bool) $ok;
    }

    private function touchConversationInbound(string $phone, ?string $contactName, string $messageText, string $type, string $createdAt): void
    {
        $preview = $this->buildPreview($messageText, $type, 'in');
        $this->upsertConversation($phone, $contactName, [
            'last_message' => $preview,
            'last_in_at' => $createdAt,
            'last_message_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function touchConversationOutbound(string $phone, string $text, string $createdAt): void
    {
        $preview = $this->buildPreview($text, 'text', 'out');
        $this->upsertConversation($phone, null, [
            'last_message' => $preview,
            'last_out_at' => $createdAt,
            'last_message_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function upsertConversation(string $phone, ?string $contactName, array $fields): void
    {
        try {
            $assignmentFields = $this->assignmentFieldsFromContext();

            $existing = $this->db->get_where('wa_fonnte_conversations', ['phone' => $phone])->row();
            if ($existing) {
                $update = array_merge($fields, $assignmentFields);
                if ($contactName !== null && $contactName !== '') {
                    $update['contact_name'] = $contactName;
                }
                $this->db->update('wa_fonnte_conversations', $update, ['phone' => $phone]);

                return;
            }

            $insert = array_merge([
                'phone' => $phone,
                'contact_name' => ($contactName !== null && $contactName !== '') ? $contactName : ($this->customerContext['contact_name'] ?? null),
                'created_at' => $fields['updated_at'] ?? date('Y-m-d H:i:s'),
            ], $assignmentFields, $fields);
            $this->db->insert('wa_fonnte_conversations', $insert);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('FonnteMessageStore: wa_fonnte_conversations upsert failed: ' . $e->getMessage(), 'webhook', 'Fonnte');
            }
        }
    }

    /**
     * @return array{assigned_user_id?:int,code?:string,cust_id?:int}
     */
    private function assignmentFieldsFromContext(): array
    {
        $fields = [];
        $assigned = $this->customerContext['assigned_user_id'] ?? null;
        if ($assigned !== null && $assigned !== '') {
            $fields['assigned_user_id'] = (int) $assigned;
        }
        $code = $this->customerContext['code'] ?? null;
        if ($code !== null && $code !== '') {
            $fields['code'] = mb_substr((string) $code, 0, 16);
        }
        $custId = $this->customerContext['cust_id'] ?? null;
        if ($custId !== null && $custId !== '') {
            $fields['cust_id'] = (int) $custId;
        }

        return $fields;
    }

    private function pickContactName(string $webhookName): ?string
    {
        $ctxName = trim((string) ($this->customerContext['contact_name'] ?? ''));
        if ($ctxName !== '') {
            return $ctxName;
        }
        $webhookName = trim($webhookName);

        return $webhookName !== '' ? $webhookName : null;
    }

    private function buildPreview(string $text, string $type, string $direction): string
    {
        $prefix = $direction === 'in' ? 'i- ' : 'o- ';
        if ($text !== '') {
            return $prefix . mb_substr($text, 0, 50);
        }
        $labels = [
            'location' => '📍 Lokasi',
            'image' => '🖼 Gambar',
            'audio' => '🎤 Audio',
            'video' => '🎬 Video',
            'document' => '📎 Dokumen',
            'sticker' => '🎨 Sticker',
            'media' => '📎 Media',
        ];
        $label = $labels[$type] ?? '📎 Media';

        return $prefix . $label;
    }

    private function detectIncomingType(string $messageText, string $url, string $location, string $extension): string
    {
        if ($location !== '' && preg_match('/^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$/', $location)) {
            return 'location';
        }
        if ($url === '') {
            return 'text';
        }

        $ext = strtolower(ltrim($extension, '.'));
        if ($ext === '') {
            $pathExt = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            $ext = strtolower((string) $pathExt);
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return 'image';
        }
        if (in_array($ext, ['mp3', 'ogg', 'opus', 'm4a', 'aac', 'wav'], true)) {
            return 'audio';
        }
        if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv'], true)) {
            return 'video';
        }
        if ($ext === 'webp' && $messageText === '') {
            return 'sticker';
        }
        if (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'], true)) {
            return 'document';
        }

        return 'media';
    }

    private function timestampToDatetime($timestamp): string
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
}
