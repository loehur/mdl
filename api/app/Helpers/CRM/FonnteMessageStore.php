<?php

namespace App\Helpers\CRM;

/**
 * Simpan riwayat chat Fonnte ke tabel terpisah (wa_fonnte_messages_in/out).
 */
class FonnteMessageStore
{
    /** Placeholder teks dari Fonnte saat pesan media tanpa caption (bukan teks user). */
    private const MEDIA_PLACEHOLDER_TEXTS = [
        'non-text message',
        'non text message',
        'nontext message',
    ];

    /** @var object */
    private $db;

    /** @var array{contact_name?:string,assigned_user_id?:int|string|null,code?:string|null,cust_id?:int|string|null} */
    private $customerContext = [];

    /** True jika saveIncoming terakhir ketemu inboxid yang sudah tersimpan. */
    private $lastIncomingDuplicate = false;

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
     * True jika saveIncoming terakhir adalah inboxid yang sudah ada.
     */
    public function lastIncomingWasDuplicate(): bool
    {
        return $this->lastIncomingDuplicate;
    }

    /**
     * Simpan pesan masuk dari webhook Fonnte.
     *
     * @param array{contact_name?:string,assigned_user_id?:int|string|null,code?:string|null,cust_id?:int|string|null} $context
     * @return int|null ID baris baru, atau null jika duplikat / gagal
     */
    public function saveIncoming(string $waNumber, array $webhook, string $messageText, array $context = []): ?int
    {
        $this->lastIncomingDuplicate = false;
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
                $this->lastIncomingDuplicate = true;
                return (int) ($dupe->id ?? 0) ?: null;
            }
        }

        $attachment = self::extractAttachmentFields($webhook);
        $url = $attachment['url'];
        $filename = $attachment['filename'];
        $extension = $attachment['extension'];
        $location = trim((string) ($webhook['location'] ?? ''));
        $name = trim((string) ($webhook['name'] ?? ''));
        $device = trim((string) ($webhook['device'] ?? ''));
        $member = trim((string) ($webhook['member'] ?? ''));

        if (self::isMediaPlaceholder($messageText)) {
            $messageText = '';
            $wasMediaPlaceholder = true;
        } else {
            $wasMediaPlaceholder = false;
        }

        $contactName = $this->pickContactName($name);

        $type = $this->detectIncomingType($messageText, $url, $location, $extension, $wasMediaPlaceholder);
        $createdAt = $this->timestampToDatetime($webhook['timestamp'] ?? null);

        if ($url !== '') {
            $persistedUrl = $this->downloadAndPersistMedia($url, $extension, $filename, $inboxid);
            if ($persistedUrl !== null) {
                $url = $persistedUrl;
            }
        }

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
        if (CrmChatMergeHelper::fonnteInboundStatusReady($this->db)) {
            $row['status'] = 'received';
        }

        $msgId = $this->db->insert('wa_fonnte_messages_in', $row);
        if (!$msgId) {
            if ($inboxid !== null && $inboxid > 0) {
                $dupe = $this->db->get_where('wa_fonnte_messages_in', ['inboxid' => $inboxid])->row();
                if ($dupe) {
                    $this->lastIncomingDuplicate = true;
                    return (int) ($dupe->id ?? 0) ?: null;
                }
            }
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
        if ($waNumber === '') {
            return null;
        }
        $hasText = trim($text) !== '';
        $hasMedia = !empty($meta['media_url']);
        if (!$hasText && !$hasMedia) {
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
     * Mengisi sender_code=AR bila masih kosong.
     *
     * @param array{status?:string,state?:string,sender_code?:string,fonnte_stateid?:string|null} $fields
     * @return object|null Baris setelah update (untuk push WS), null jika tidak ketemu
     */
    public function updateOutgoingByFonnteMessageId(string $fonnteMessageId, array $fields = [])
    {
        $fonnteMessageId = trim($fonnteMessageId);
        if ($fonnteMessageId === '') {
            return null;
        }

        $existing = $this->db->get_where('wa_fonnte_messages_out', [
            'fonnte_message_id' => mb_substr($fonnteMessageId, 0, 64),
        ], 1)->row();
        if (!$existing) {
            return null;
        }

        return $this->applyOutgoingStatusUpdate($existing, $fields);
    }

    /**
     * Update outbound by Fonnte stateid (delivered/read tanpa field id).
     *
     * @param array{status?:string,sender_code?:string} $fields
     * @return object|null
     */
    public function updateOutgoingByFonnteStateId(string $stateId, array $fields = [])
    {
        $stateId = trim($stateId);
        if ($stateId === '' || !$this->hasFonnteStateIdColumn()) {
            return null;
        }

        $existing = $this->db->get_where('wa_fonnte_messages_out', [
            'fonnte_stateid' => mb_substr($stateId, 0, 64),
        ], 1)->row();
        if (!$existing) {
            return null;
        }

        return $this->applyOutgoingStatusUpdate($existing, $fields);
    }

    /**
     * @param object $existing
     * @param array{status?:string,sender_code?:string,fonnte_stateid?:string|null} $fields
     * @return object|null
     */
    private function applyOutgoingStatusUpdate($existing, array $fields)
    {
        if (!class_exists(SapaanStatsHelper::class)) {
            require_once __DIR__ . '/SapaanStatsHelper.php';
        }

        $update = [];
        if (isset($fields['status']) && $fields['status'] !== '') {
            $next = mb_substr((string) $fields['status'], 0, 32);
            $current = (string) ($existing->status ?? '');
            if ($this->statusRank($next) >= $this->statusRank($current)) {
                $update['status'] = $next;
            }
        }
        if ($this->hasFonnteStateIdColumn() && !empty($fields['fonnte_stateid'])) {
            $update['fonnte_stateid'] = mb_substr((string) $fields['fonnte_stateid'], 0, 64);
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
        }

        if ($update === []) {
            return $existing;
        }

        $ok = $this->db->update('wa_fonnte_messages_out', $update, ['id' => (int) $existing->id]);
        if (!$ok && class_exists('\Log')) {
            $err = $this->db->conn()->error ?? 'unknown';
            \Log::write('FonnteMessageStore: applyOutgoingStatusUpdate failed: ' . $err, 'webhook', 'Fonnte');
        }

        if (!$ok) {
            return null;
        }

        $row = $this->db->get_where('wa_fonnte_messages_out', ['id' => (int) $existing->id], 1)->row();

        return $row ?: null;
    }

    private function statusRank(string $status): int
    {
        $map = [
            'failed' => -1,
            'error' => -1,
            'pending' => 0,
            'processing' => 0,
            'waiting' => 0,
            'sent' => 1,
            'delivered' => 2,
            'read' => 3,
        ];

        return $map[strtolower(trim($status))] ?? 0;
    }

    private function hasFonnteStateIdColumn(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $row = $this->db->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'wa_fonnte_messages_out'
                   AND COLUMN_NAME = 'fonnte_stateid'
                 LIMIT 1"
            )->row();
            $ready = (bool) $row;
        } catch (\Throwable $e) {
            $ready = false;
        }

        return $ready;
    }

    /**
     * Map status/state webhook Fonnte ke sent|delivered|read|pending|failed.
     * Fonnte: status = pipeline kirim (sent/processing/…); state = receipt WA (delivered/read).
     */
    public static function normalizeFonnteOutboundStatus(?string $status, $state = null): string
    {
        $statusStr = strtolower(trim((string) ($status ?? '')));
        $stateRaw = $state;
        $stateStr = is_scalar($stateRaw) ? strtolower(trim((string) $stateRaw)) : '';

        if (in_array($stateStr, ['read', 'readed', 'seen', '3'], true)) {
            return 'read';
        }
        if (in_array($stateStr, ['delivered', 'delivery', 'received', '2'], true)) {
            return 'delivered';
        }
        if (in_array($stateStr, ['sent', '1'], true)) {
            return 'sent';
        }

        if (in_array($statusStr, ['read'], true)) {
            return 'read';
        }
        if (in_array($statusStr, ['delivered'], true)) {
            return 'delivered';
        }
        if (in_array($statusStr, ['sent', 'success'], true)) {
            return 'sent';
        }
        if (in_array($statusStr, ['processing', 'pending', 'waiting'], true)) {
            return 'pending';
        }
        if (in_array($statusStr, ['invalid', 'failed', 'expired', 'url unreachable'], true)) {
            return 'failed';
        }

        if ($stateStr !== '' && is_numeric($stateStr)) {
            $n = (int) $stateStr;
            if ($n >= 3) {
                return 'read';
            }
            if ($n === 2) {
                return 'delivered';
            }
            if ($n === 1) {
                return 'sent';
            }
        }

        return $statusStr !== '' ? $statusStr : 'sent';
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
                $this->clearAssignedUserIdIfKaryawan($phone);

                return;
            }

            $insert = array_merge([
                'phone' => $phone,
                'contact_name' => ($contactName !== null && $contactName !== '') ? $contactName : ($this->customerContext['contact_name'] ?? null),
                'created_at' => $fields['updated_at'] ?? date('Y-m-d H:i:s'),
            ], $assignmentFields, $fields);
            if (!empty($this->customerContext['is_karyawan'])) {
                unset($insert['assigned_user_id']);
            }
            $this->db->insert('wa_fonnte_conversations', $insert);
            $this->clearAssignedUserIdIfKaryawan($phone);
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
        if (empty($this->customerContext['is_karyawan'])) {
            $assigned = $this->customerContext['assigned_user_id'] ?? null;
            if ($assigned !== null && $assigned !== '') {
                $fields['assigned_user_id'] = (int) $assigned;
            }
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

    private function clearAssignedUserIdIfKaryawan(string $phone): void
    {
        if (empty($this->customerContext['is_karyawan']) || $phone === '') {
            return;
        }
        try {
            $this->db->query(
                'UPDATE wa_fonnte_conversations SET assigned_user_id = NULL WHERE phone = ? LIMIT 1',
                [$phone]
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('FonnteMessageStore: clear assigned_user_id failed: ' . $e->getMessage(), 'webhook', 'Fonnte');
            }
        }
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

    /**
     * @return array{url:string,filename:string,extension:string}
     */
    public static function extractAttachmentFields(array $webhook): array
    {
        $url = '';
        foreach (['url', 'Url', 'URL', 'media_url', 'attachment', 'file', 'link'] as $key) {
            $candidate = trim((string) ($webhook[$key] ?? ''));
            if ($candidate !== '' && preg_match('#^https?://#i', $candidate)) {
                $url = $candidate;
                break;
            }
        }

        $filename = trim((string) ($webhook['filename'] ?? $webhook['Filename'] ?? ''));
        $extension = trim((string) ($webhook['extension'] ?? $webhook['Extension'] ?? ''));

        return [
            'url' => $url,
            'filename' => $filename,
            'extension' => $extension,
        ];
    }

    public static function isMediaPlaceholder(string $text): bool
    {
        $normalized = strtolower(trim($text));

        return in_array($normalized, self::MEDIA_PLACEHOLDER_TEXTS, true);
    }

    private function detectIncomingType(string $messageText, string $url, string $location, string $extension, bool $wasMediaPlaceholder = false): string
    {
        if ($location !== '' && preg_match('/^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$/', $location)) {
            return 'location';
        }

        $ext = strtolower(ltrim($extension, '.'));
        if ($ext === '' && $url !== '') {
            $pathExt = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            $ext = strtolower((string) $pathExt);
        }

        if ($url !== '' || $ext !== '' || $wasMediaPlaceholder) {
            return $this->typeFromExtension($ext, $messageText, $url !== '');
        }

        return 'text';
    }

    private function typeFromExtension(string $ext, string $messageText, bool $hasUrl): string
    {
        if ($ext === '') {
            return 'image';
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            return 'image';
        }
        if ($ext === 'webp') {
            return $messageText === '' && $hasUrl ? 'sticker' : 'image';
        }
        if (in_array($ext, ['mp3', 'ogg', 'opus', 'm4a', 'aac', 'wav'], true)) {
            return 'audio';
        }
        if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv', '3gp'], true)) {
            return 'video';
        }
        if (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'], true)) {
            return 'document';
        }

        return 'media';
    }

    /**
     * Unduh attachment Fonnte ke storage lokal (URL Fonnte bisa expire).
     */
    private function downloadAndPersistMedia(string $sourceUrl, string $extension, string $filename, $inboxid): ?string
    {
        $sourceUrl = trim($sourceUrl);
        if ($sourceUrl === '' || ! preg_match('#^https?://#i', $sourceUrl)) {
            return null;
        }

        $mediaData = @file_get_contents($sourceUrl);
        if ($mediaData === false || $mediaData === '') {
            $ch = curl_init($sourceUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MdL-Backend/1.0');
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $mediaData = curl_exec($ch);
            curl_close($ch);
        }

        if ($mediaData === false || $mediaData === '') {
            if (class_exists('\Log')) {
                \Log::write(
                    'FonnteMessageStore: media download failed inboxid=' . (string) ($inboxid ?? '') . ' url=' . mb_substr($sourceUrl, 0, 120),
                    'webhook',
                    'Fonnte'
                );
            }

            return null;
        }

        $ext = strtolower(ltrim($extension, '.'));
        if ($ext === '') {
            $pathExt = pathinfo(parse_url($sourceUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            $ext = strtolower((string) $pathExt);
        }
        if ($ext === '' && $filename !== '') {
            $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        }
        if ($ext === '') {
            $ext = 'bin';
        }

        $relativePath = '/uploads/whatsapp/' . date('Y/m');
        $baseDir = __DIR__ . '/../../../uploads/whatsapp/' . date('Y/m');
        if (! is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }

        $prefix = $inboxid !== null && (int) $inboxid > 0 ? 'fonnte_' . (int) $inboxid : 'fonnte_' . uniqid();
        $saveFilename = $prefix . '.' . $ext;
        $savePath = $baseDir . '/' . $saveFilename;

        if (@file_put_contents($savePath, $mediaData) === false) {
            if (class_exists('\Log')) {
                \Log::write('FonnteMessageStore: media save failed path=' . $savePath, 'webhook', 'Fonnte');
            }

            return null;
        }

        $baseUrl = 'https://api.nalju.com';
        if (class_exists('\App\Config\Env') && defined('\App\Config\Env::BASE_URL')) {
            $baseUrl = rtrim(\App\Config\Env::BASE_URL, '/');
        }

        return $baseUrl . $relativePath . '/' . $saveFilename;
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
