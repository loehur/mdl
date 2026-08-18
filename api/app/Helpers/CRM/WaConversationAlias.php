<?php

namespace App\Helpers\CRM;

/**
 * Alias identitas (phone / LID / yCloud user / username) → wa_conversations.id.
 * Conversation CRM tidak pecah saat WhatsApp ganti identifier.
 */
class WaConversationAlias
{
    public const TYPE_PHONE = 'phone';
    public const TYPE_LID = 'lid';
    public const TYPE_YCLOUD_USER = 'ycloud_user_id';
    public const TYPE_YCLOUD_PARENT = 'ycloud_parent_user_id';
    public const TYPE_USERNAME = 'wa_username';

    /** Urutan lookup: ID stabil dulu, phone terakhir. */
    private const LOOKUP_ORDER = [
        self::TYPE_YCLOUD_USER,
        self::TYPE_YCLOUD_PARENT,
        self::TYPE_LID,
        self::TYPE_USERNAME,
        self::TYPE_PHONE,
    ];

    public static function tableReady($db): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $row = $db->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'wa_conversation_aliases'
                 LIMIT 1"
            )->row();
            $ready = (bool) $row;
        } catch (\Throwable $e) {
            $ready = false;
        }

        return $ready;
    }

    /**
     * @param array{phone?:string,lid?:string,ycloud_user_id?:string,ycloud_parent_user_id?:string,wa_username?:string} $hints
     */
    public static function findConversationRow($db, array $hints): ?object
    {
        $id = self::findConversationId($db, $hints);
        if ($id === null || $id <= 0) {
            return null;
        }
        try {
            $row = $db->get_where('wa_conversations', ['id' => $id], 1)->row();

            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array{phone?:string,lid?:string,ycloud_user_id?:string,ycloud_parent_user_id?:string,wa_username?:string} $hints
     */
    public static function findConversationId($db, array $hints): ?int
    {
        if (!self::tableReady($db)) {
            return null;
        }
        $pairs = self::normalizedPairs($hints);
        if ($pairs === []) {
            return null;
        }
        foreach (self::LOOKUP_ORDER as $type) {
            if (!isset($pairs[$type])) {
                continue;
            }
            $id = self::lookupId($db, $type, $pairs[$type]);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Simpan / refresh alias untuk conversation yang sudah ada.
     * Alias yang sudah milik conversation lain tidak di-steal.
     *
     * @param array{phone?:string,lid?:string,ycloud_user_id?:string,ycloud_parent_user_id?:string,wa_username?:string} $hints
     */
    public static function remember($db, int $conversationId, array $hints, string $source = ''): void
    {
        if ($conversationId <= 0 || !self::tableReady($db)) {
            return;
        }
        $pairs = self::normalizedPairs($hints);
        if ($pairs === []) {
            return;
        }
        $src = mb_substr(trim($source), 0, 16);
        foreach ($pairs as $type => $value) {
            try {
                $db->query(
                    "INSERT INTO wa_conversation_aliases
                        (conversation_id, alias_type, alias_value, source, created_at, last_seen_at)
                     VALUES (?, ?, ?, ?, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE
                        last_seen_at = IF(conversation_id = VALUES(conversation_id), NOW(), last_seen_at)",
                    [$conversationId, $type, $value, $src !== '' ? $src : null]
                );
            } catch (\Throwable $e) {
                if (class_exists('\Log')) {
                    \Log::write(
                        'WaConversationAlias remember failed type=' . $type . ' ' . $e->getMessage(),
                        'webhook',
                        'Alias'
                    );
                }
            }
        }
        self::promotePhoneIfNeeded($db, $conversationId, $hints);
    }

    /**
     * Jika conversation masih +lid… dan kita sudah punya nomor HP, naikkan ke +62…
     *
     * @param array{phone?:string} $hints
     */
    private static function promotePhoneIfNeeded($db, int $conversationId, array $hints): void
    {
        if (empty($hints['phone']) || self::looksLikeLidFallback((string) $hints['phone'])) {
            return;
        }
        $digits = self::normalizePhoneDigits((string) $hints['phone']);
        if ($digits === '') {
            return;
        }
        try {
            $row = $db->get_where('wa_conversations', ['id' => $conversationId], 1)->row();
            if (!$row || empty($row->wa_number) || !self::looksLikeLidFallback((string) $row->wa_number)) {
                return;
            }
            $db->update('wa_conversations', [
                'wa_number' => '+' . $digits,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $conversationId]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{phone?:string,lid?:string,ycloud_user_id?:string,ycloud_parent_user_id?:string,wa_username?:string}
     */
    public static function hintsFromYcloudMessage(array $msg, ?string $waNumber): array
    {
        $hints = [];
        if ($waNumber !== null && $waNumber !== '' && !self::looksLikeLidFallback($waNumber)) {
            $hints['phone'] = $waNumber;
        }
        $userId = trim((string) ($msg['fromUserId'] ?? ''));
        if ($userId !== '') {
            $hints['ycloud_user_id'] = $userId;
        }
        $parentId = trim((string) ($msg['fromParentUserId'] ?? ''));
        if ($parentId !== '') {
            $hints['ycloud_parent_user_id'] = $parentId;
        }
        $username = trim((string) ($msg['customerProfile']['username'] ?? ''));
        if ($username !== '') {
            $hints['wa_username'] = $username;
        }

        return $hints;
    }

    /**
     * @return array{phone?:string,lid?:string,ycloud_user_id?:string,ycloud_parent_user_id?:string,wa_username?:string}
     */
    public static function hintsFromFonnteWebhook(array $data, ?string $waNumber): array
    {
        $hints = [];
        if ($waNumber !== null && $waNumber !== '' && !self::looksLikeLidFallback($waNumber)) {
            $hints['phone'] = $waNumber;
        }
        $lid = trim((string) ($data['senderlid'] ?? ''));
        if ($lid !== '' && stripos($lid, 'broadcast') === false) {
            $hints['lid'] = $lid;
        } elseif ($waNumber !== null && self::looksLikeLidFallback($waNumber)) {
            $hints['lid'] = $waNumber;
        }

        return $hints;
    }

    public static function looksLikeLidFallback(string $value): bool
    {
        $v = strtolower(trim($value));

        return str_starts_with($v, '+lid')
            || str_starts_with($v, 'lid:')
            || str_contains($v, '@lid');
    }

    /**
     * @param array<string,string> $hints
     * @return array<string,string>
     */
    private static function normalizedPairs(array $hints): array
    {
        $out = [];
        if (!empty($hints['ycloud_user_id'])) {
            $v = self::normalizeOpaqueId((string) $hints['ycloud_user_id']);
            if ($v !== '') {
                $out[self::TYPE_YCLOUD_USER] = $v;
            }
        }
        if (!empty($hints['ycloud_parent_user_id'])) {
            $v = self::normalizeOpaqueId((string) $hints['ycloud_parent_user_id']);
            if ($v !== '') {
                $out[self::TYPE_YCLOUD_PARENT] = $v;
            }
        }
        if (!empty($hints['lid'])) {
            $v = self::normalizeLid((string) $hints['lid']);
            if ($v !== '') {
                $out[self::TYPE_LID] = $v;
            }
        }
        if (!empty($hints['wa_username'])) {
            $v = self::normalizeUsername((string) $hints['wa_username']);
            if ($v !== '') {
                $out[self::TYPE_USERNAME] = $v;
            }
        }
        if (!empty($hints['phone']) && !self::looksLikeLidFallback((string) $hints['phone'])) {
            $v = self::normalizePhoneDigits((string) $hints['phone']);
            if ($v !== '') {
                $out[self::TYPE_PHONE] = $v;
            }
        }

        return $out;
    }

    private static function lookupId($db, string $type, string $value): ?int
    {
        try {
            $row = $db->query(
                'SELECT conversation_id FROM wa_conversation_aliases WHERE alias_type = ? AND alias_value = ? LIMIT 1',
                [$type, $value]
            )->row();
            if ($row && !empty($row->conversation_id)) {
                return (int) $row->conversation_id;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    public static function normalizeLid(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (str_starts_with(strtolower($raw), 'lid:')) {
            $raw = substr($raw, 4);
        } elseif (str_starts_with(strtolower($raw), '+lid')) {
            $raw = substr($raw, 4);
        }
        $digits = preg_replace('/[^0-9]/', '', explode('@', $raw)[0]);
        if ($digits === '' || strlen($digits) < 6) {
            return '';
        }

        return $digits . '@lid';
    }

    public static function normalizePhoneDigits(string $raw): string
    {
        $clean = preg_replace('/[^0-9]/', '', $raw);
        if ($clean === '' || strlen($clean) < 8) {
            return '';
        }
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (!str_starts_with($clean, '62') && str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }

        return $clean;
    }

    public static function normalizeUsername(string $raw): string
    {
        $v = strtolower(trim($raw));
        $v = ltrim($v, '@');
        $v = preg_replace('/\s+/', '', $v);

        return ($v !== null && $v !== '' && strlen($v) >= 2) ? mb_substr($v, 0, 128) : '';
    }

    public static function normalizeOpaqueId(string $raw): string
    {
        $v = trim($raw);

        return ($v !== '' && strlen($v) >= 3) ? mb_substr($v, 0, 128) : '';
    }
}
