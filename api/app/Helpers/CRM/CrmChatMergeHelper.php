<?php

namespace App\Helpers\CRM;

/**
 * CRM chat YCloud multi-line (business_phone) + CSW per line.
 */
class CrmChatMergeHelper
{
    /** @return string[] */
    public static function phoneVariants(string $phone): array
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if ($clean === '') {
            return [];
        }
        if (!str_starts_with($clean, '62') && str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        } elseif (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }
        $rest = str_starts_with($clean, '62') ? substr($clean, 2) : $clean;

        return array_values(array_unique(array_filter([
            $phone,
            '+' . $clean,
            $clean,
            '0' . $rest,
            $rest,
        ])));
    }

    public static function normalizeWaNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if ($clean === '') {
            return $phone;
        }
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (!str_starts_with($clean, '62') && str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }

        return '+' . $clean;
    }

    /**
     * @param array{phone?:string,lid?:string,ycloud_user_id?:string,ycloud_parent_user_id?:string,wa_username?:string} $hints
     */
    public static function findWaConversation($db, string $phone, array $hints = []): ?object
    {
        if (!class_exists(WaConversationAlias::class)) {
            require_once __DIR__ . '/WaConversationAlias.php';
        }
        $aliasHints = $hints;
        if ($phone !== '' && !isset($aliasHints['phone']) && !WaConversationAlias::looksLikeLidFallback($phone)) {
            $aliasHints['phone'] = $phone;
        }
        if ($phone !== '' && WaConversationAlias::looksLikeLidFallback($phone) && empty($aliasHints['lid'])) {
            $aliasHints['lid'] = $phone;
        }
        $byAlias = WaConversationAlias::findConversationRow($db, $aliasHints);
        if ($byAlias) {
            return $byAlias;
        }

        foreach (self::phoneVariants($phone) as $variant) {
            $row = $db->get_where('wa_conversations', ['wa_number' => $variant])->row();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public static function getLegacyConversationLastInAt($db, string $phone): ?string
    {
        $conv = self::findWaConversation($db, $phone);
        if (!$conv || empty($conv->last_in_at)) {
            return null;
        }

        return (string) $conv->last_in_at;
    }

    /** @deprecated use WaCswLine */
    public static function getYcloudLastInAt($db, string $phone): ?string
    {
        return self::getLegacyConversationLastInAt($db, $phone);
    }

    public static function getLegacyFonnteCswLastInAt($db, string $phone): ?string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if ($clean === '') {
            return null;
        }
        if (!str_starts_with($clean, '62') && str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        } elseif (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }
        try {
            $q = $db->query(
                'SELECT last_in_at FROM wa_fonnte_csw WHERE phone IN (?, ?) ORDER BY id DESC LIMIT 1',
                ['+' . $clean, $clean]
            );
            if ($q && $q->num_rows() > 0) {
                $row = $q->row();
                if ($row && !empty($row->last_in_at)) {
                    return (string) $row->last_in_at;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /** @deprecated use WaCswLine */
    public static function getFonnteLastInAt($db, string $phone): ?string
    {
        return self::getLegacyFonnteCswLastInAt($db, $phone);
    }

    public static function getLineLastInAt($db, string $phone, string $lineKey): ?string
    {
        if (!class_exists(WaCswLine::class)) {
            require_once __DIR__ . '/WaCswLine.php';
        }
        if (!class_exists(\App\Config\WaLines::class)) {
            require_once __DIR__ . '/../../Config/WaLines.php';
        }
        $line = \App\Config\WaLines::get($lineKey);
        if (!$line) {
            return null;
        }

        return WaCswLine::getLastInAt($db, $phone, $line['phone']);
    }

    public static function isWithinCsw(?string $lastInAt): bool
    {
        if ($lastInAt === null || $lastInAt === '') {
            return false;
        }
        if (!class_exists(WhatsAppService::class)) {
            require_once __DIR__ . '/WhatsAppService.php';
        }
        $wa = new WhatsAppService();

        return $wa->isWithinCsw($lastInAt);
    }

    /**
     * @return array{
     *   line_csw: array<string, array{open:bool,last_in_at:?string,line_label:string,line_name:string,business_phone:string}>,
     *   default_reply_line: ?string,
     *   can_reply: bool,
     *   ycloud_open: bool,
     *   fonnte_open: bool,
     *   last_in_at_ycloud: ?string,
     *   last_in_at_fonnte: ?string,
     *   default_reply_channel: ?string
     * }
     */
    public static function getCswStatus($db, string $phone): array
    {
        if (!class_exists(\App\Config\WaLines::class)) {
            require_once __DIR__ . '/../../Config/WaLines.php';
        }
        if (!class_exists(WaLineResolver::class)) {
            require_once __DIR__ . '/WaLineResolver.php';
        }

        $lineCsw = [];
        $defaultLine = null;
        $defaultTs = null;
        $canReply = false;

        foreach (\App\Config\WaLines::all() as $lineKey => $line) {
            $lastIn = self::getLineLastInAt($db, $phone, $lineKey);
            $open = self::isWithinCsw($lastIn);
            $lineCsw[$lineKey] = [
                'open' => $open,
                'last_in_at' => $lastIn,
                'line_label' => $line['short_label'],
                'line_name' => $line['display_name'],
                'business_phone' => $line['phone'],
            ];
            if ($open) {
                $canReply = true;
            }
            if ($open && ($defaultTs === null || ($lastIn !== null && strtotime($lastIn) >= $defaultTs))) {
                $defaultTs = $lastIn !== null ? strtotime($lastIn) : time();
                $defaultLine = $lineKey;
            }
        }

        if ($defaultLine === null && $canReply) {
            foreach ($lineCsw as $key => $row) {
                if (!empty($row['open'])) {
                    $defaultLine = $key;
                    break;
                }
            }
        }

        $admin = $lineCsw[\App\Config\WaLines::KEY_ADMIN] ?? null;
        $cs = $lineCsw[\App\Config\WaLines::KEY_CS] ?? null;

        return [
            'line_csw' => $lineCsw,
            'default_reply_line' => $defaultLine,
            'can_reply' => $canReply,
            // Legacy aliases for gradual UI migration
            'ycloud_open' => (bool) ($cs['open'] ?? false),
            'fonnte_open' => (bool) ($admin['open'] ?? false),
            'last_in_at_ycloud' => $cs['last_in_at'] ?? null,
            'last_in_at_fonnte' => $admin['last_in_at'] ?? null,
            'default_reply_channel' => $defaultLine === \App\Config\WaLines::KEY_ADMIN ? 'fonnte'
                : ($defaultLine === \App\Config\WaLines::KEY_CS ? 'ycloud' : null),
        ];
    }

    /**
     * @param 'auto'|string|null $requested line_key, business_phone, or legacy ycloud/fonnte
     * @return string|null line_key
     */
    public static function resolveReplyLine(array $csw, ?string $requested): ?string
    {
        if (!class_exists(WaLineResolver::class)) {
            require_once __DIR__ . '/WaLineResolver.php';
        }

        $req = $requested !== null ? trim($requested) : 'auto';
        if ($req === '' || strtolower($req) === 'auto') {
            $def = $csw['default_reply_line'] ?? null;

            return is_string($def) && $def !== '' ? $def : null;
        }

        $line = WaLineResolver::fromRequest($req);
        if (!$line) {
            return null;
        }

        $row = $csw['line_csw'][$line['key']] ?? null;
        if (empty($row['open'])) {
            return null;
        }

        return $line['key'];
    }

    /** @deprecated use resolveReplyLine */
    public static function resolveReplyChannel(array $csw, ?string $requested): ?string
    {
        $lineKey = self::resolveReplyLine($csw, $requested);
        if ($lineKey === \App\Config\WaLines::KEY_ADMIN) {
            return 'fonnte';
        }
        if ($lineKey === \App\Config\WaLines::KEY_CS) {
            return 'ycloud';
        }

        return null;
    }

    /** @param array<string, mixed> $msgRow from getMessages */
    public static function enrichMessageLineFields(array $msgRow): array
    {
        if (!class_exists(\App\Config\WaLines::class)) {
            require_once __DIR__ . '/../../Config/WaLines.php';
        }
        if (!class_exists(WaLineResolver::class)) {
            require_once __DIR__ . '/WaLineResolver.php';
        }

        $businessPhone = (string) ($msgRow['business_phone'] ?? '');
        $line = WaLineResolver::fromBusinessPhoneOrDefault($businessPhone !== '' ? $businessPhone : null);
        $lineKey = $line['key'];
        $rawId = $msgRow['id'] ?? 0;
        if (is_string($rawId) && str_contains($rawId, '-')) {
            $msgRow['id'] = $rawId;
        } else {
            $msgRow['id'] = $lineKey . '-' . $rawId;
        }
        $msgRow['line_key'] = $lineKey;
        $msgRow['business_phone'] = $line['phone'];
        $msgRow['line_label'] = $line['short_label'];
        $msgRow['line_name'] = $line['display_name'];
        $msgRow['provider'] = $lineKey;

        return $msgRow;
    }

    /**
     * Row CRM induk untuk chat Fonnte-only (tanpa membuka CSW yCloud).
     *
     * @param array{contact_name?:?string,assigned_user_id?:int|string|null,code?:?string,cust_id?:int|string|null} $ctx
     * @param array{phone?:string,lid?:string,ycloud_user_id?:string,ycloud_parent_user_id?:string,wa_username?:string} $hints
     */
    public static function ensureShellFromFonnte($db, string $phone, array $ctx, string $lastMessage, string $lastMessageAt, array $hints = []): int
    {
        if (!class_exists(WaConversationAlias::class)) {
            require_once __DIR__ . '/WaConversationAlias.php';
        }
        $waNumber = WaConversationAlias::looksLikeLidFallback($phone)
            ? $phone
            : self::normalizeWaNumber($phone);
        $conv = self::findWaConversation($db, $phone, $hints);
        if ($conv) {
            $convId = (int) ($conv->id ?? 0);
            $existingAt = (string) ($conv->last_message_at ?? '');
            $update = [
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($lastMessageAt !== '' && ($existingAt === '' || $lastMessageAt >= $existingAt)) {
                $update['last_message'] = $lastMessage;
                $update['last_message_at'] = $lastMessageAt;
            }
            if (!empty($ctx['contact_name'])) {
                $update['contact_name'] = $ctx['contact_name'];
            }
            if (!empty($ctx['is_karyawan'])) {
                $update['assigned_user_id'] = null;
                $update['code'] = null;
                $update['cust_id'] = null;
            } else {
                if (!empty($ctx['assigned_user_id'])) {
                    $update['assigned_user_id'] = (int) $ctx['assigned_user_id'];
                }
                if (!empty($ctx['code'])) {
                    $update['code'] = mb_substr((string) $ctx['code'], 0, 16);
                }
                if (!empty($ctx['cust_id'])) {
                    $update['cust_id'] = (int) $ctx['cust_id'];
                }
            }
            $db->update('wa_conversations', $update, ['id' => $convId]);
            self::rememberConversationAliases($db, $convId, $phone, $hints, 'fonnte');

            return $convId;
        }

        $insert = [
            'wa_number' => $waNumber,
            'contact_name' => $ctx['contact_name'] ?? null,
            'assigned_user_id' => !empty($ctx['is_karyawan']) ? null : ($ctx['assigned_user_id'] ?? null),
            'code' => !empty($ctx['is_karyawan']) ? null : ($ctx['code'] ?? null),
            'cust_id' => !empty($ctx['is_karyawan']) ? null : ($ctx['cust_id'] ?? null),
            'status' => 'closed',
            'last_message' => $lastMessage,
            'last_message_at' => $lastMessageAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $insertId = $db->insert('wa_conversations', $insert);
        $newId = $insertId ? (int) $insertId : 0;
        if ($newId > 0) {
            self::rememberConversationAliases($db, $newId, $phone, $hints, 'fonnte');
        }

        return $newId;
    }

    /**
     * @param array{phone?:string,lid?:string,ycloud_user_id?:string,ycloud_parent_user_id?:string,wa_username?:string} $hints
     */
    public static function rememberConversationAliases($db, int $conversationId, string $phone, array $hints = [], string $source = ''): void
    {
        if ($conversationId <= 0) {
            return;
        }
        if (!class_exists(WaConversationAlias::class)) {
            require_once __DIR__ . '/WaConversationAlias.php';
        }
        if ($phone !== '' && !isset($hints['phone']) && !WaConversationAlias::looksLikeLidFallback($phone)) {
            $hints['phone'] = $phone;
        }
        if ($phone !== '' && WaConversationAlias::looksLikeLidFallback($phone) && empty($hints['lid'])) {
            $hints['lid'] = $phone;
        }
        WaConversationAlias::remember($db, $conversationId, $hints, $source);
    }

    public static function pushWebSocket(array $payload): bool
    {
        $url = WaServer::incomingUrl();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        return $err === '' && $httpCode > 0 && $httpCode < 400;
    }

    /** Tabel riwayat Fonnte sudah dimigrasi di db CRM (mdl_main). */
    public static function fonnteMessageTablesReady($db): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            foreach (['wa_fonnte_messages_in', 'wa_fonnte_messages_out'] as $table) {
                $q = $db->query(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                    [$table]
                );
                if (!$q || $q->num_rows() === 0) {
                    $cache = false;

                    return false;
                }
            }
            $cache = true;
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    /** Kolom status di wa_fonnte_messages_in (migration 027). */
    public static function fonnteInboundStatusReady($db): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (!self::fonnteMessageTablesReady($db)) {
            $cache = false;

            return false;
        }
        try {
            $q = $db->query(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
                ['wa_fonnte_messages_in', 'status']
            );
            $cache = $q && $q->num_rows() > 0;
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    /**
     * Placeholder IN (?,?) untuk daftar variant phone.
     *
     * @param string[] $variants
     * @return array{0:string,1:string[]}
     */
    public static function variantsInClause(array $variants): array
    {
        $variants = array_values(array_unique(array_filter(array_map('strval', $variants))));
        if ($variants === []) {
            return ['', []];
        }

        return [implode(',', array_fill(0, count($variants), '?')), $variants];
    }

    /**
     * Placeholder IN (?,?) untuk phoneVariants.
     *
     * @return array{0:string,1:string[]}
     */
    public static function phoneInClause(string $phone): array
    {
        return self::variantsInClause(self::phoneVariants($phone));
    }

    /**
     * @param string[] $phones
     * @return array{variants:string[],keyByVariant:array<string,string>}
     */
    private static function buildPhoneKeyMaps(array $phones): array
    {
        if (!class_exists(WaSenderContext::class)) {
            require_once __DIR__ . '/WaSenderContext.php';
        }

        $allVariants = [];
        $keyByVariant = [];
        foreach ($phones as $phone) {
            $phone = (string) $phone;
            if ($phone === '') {
                continue;
            }
            $key = WaSenderContext::key($phone);
            if ($key === '') {
                continue;
            }
            foreach (self::phoneVariants($phone) as $variant) {
                $allVariants[$variant] = true;
                $keyByVariant[$variant] = $key;
            }
        }

        return [
            'variants' => array_keys($allVariants),
            'keyByVariant' => $keyByVariant,
        ];
    }

    /**
     * @param string[] $variants
     * @return object[]
     */
    private static function fetchLatestRowsPerPhoneFromTable(
        $db,
        string $table,
        string $bodyColumn,
        string $sender,
        array $variants
    ): array {
        static $allowed = [
            'wa_messages_in' => 'text',
            'wa_messages_out' => 'content',
            'wa_fonnte_messages_in' => 'text',
            'wa_fonnte_messages_out' => 'text',
        ];
        if (!isset($allowed[$table]) || $allowed[$table] !== $bodyColumn) {
            return [];
        }

        [$inSql, $params] = self::variantsInClause($variants);
        if ($inSql === '') {
            return [];
        }

        $bodyExpr = $bodyColumn === 'content'
            ? 'COALESCE(m.content, \'\')'
            : 'COALESCE(m.text, \'\')';
        $sql = "SELECT m.phone, {$bodyExpr} AS body, m.type, m.created_at AS ts
                FROM {$table} m
                INNER JOIN (
                    SELECT phone, MAX(created_at) AS max_ts
                    FROM {$table}
                    WHERE phone IN ({$inSql})
                    GROUP BY phone
                ) latest ON m.phone = latest.phone AND m.created_at = latest.max_ts
                WHERE m.phone IN ({$inSql})";

        try {
            $q = $db->query($sql, array_merge($params, $params));
            if (!$q || $q->num_rows() === 0) {
                return [];
            }

            $rows = [];
            foreach ($q->result() as $row) {
                $row->sender = $sender;
                $rows[] = $row;
            }

            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Batch: preview pesan terakhir per phone key (yCloud + Fonnte), max 4 query/halaman.
     *
     * @param string[] $phones
     * @return array<string, array{last_message:string,last_message_time:string}>
     */
    public static function fetchLatestMessageMetaBatch($db, array $phones): array
    {
        $maps = self::buildPhoneKeyMaps($phones);
        $variants = $maps['variants'];
        $keyByVariant = $maps['keyByVariant'];
        if ($variants === []) {
            return [];
        }

        $best = [];
        $sources = [
            ['wa_messages_in', 'text', 'customer'],
            ['wa_messages_out', 'content', 'me'],
        ];

        foreach ($sources as [$table, $bodyColumn, $sender]) {
            foreach (self::fetchLatestRowsPerPhoneFromTable($db, $table, $bodyColumn, $sender, $variants) as $row) {
                $key = $keyByVariant[(string) ($row->phone ?? '')] ?? '';
                if ($key === '' && class_exists(WaSenderContext::class)) {
                    $key = WaSenderContext::key((string) ($row->phone ?? ''));
                }
                if ($key === '') {
                    continue;
                }

                $ts = (string) ($row->ts ?? '');
                if ($ts === '') {
                    continue;
                }

                if (!isset($best[$key]) || $ts > $best[$key]['ts']) {
                    $best[$key] = [
                        'body' => (string) ($row->body ?? ''),
                        'type' => (string) ($row->type ?? 'text'),
                        'sender' => (string) ($row->sender ?? $sender),
                        'ts' => $ts,
                    ];
                }
            }
        }

        $result = [];
        foreach ($best as $key => $row) {
            $result[$key] = [
                'last_message' => self::formatMessagePreview($row['body'], $row['type'], $row['sender']),
                'last_message_time' => $row['ts'],
            ];
        }

        return $result;
    }

    /** Unread yCloud (wa_messages_in) + Fonnte (status=received). */
    public static function countUnreadForPhone($db, string $phone): int
    {
        [$inSql, $variants] = self::phoneInClause($phone);
        if ($inSql === '') {
            return 0;
        }

        $total = 0;
        try {
            $q = $db->query(
                "SELECT COUNT(*) AS c FROM wa_messages_in WHERE phone IN ({$inSql}) AND (status != 'read' OR status IS NULL)",
                $variants
            );
            if ($q && $q->num_rows() > 0) {
                $total += (int) ($q->row()->c ?? 0);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (self::fonnteInboundStatusReady($db)) {
            try {
                $q = $db->query(
                    "SELECT COUNT(*) AS c FROM wa_fonnte_messages_in WHERE phone IN ({$inSql}) AND status = 'received'",
                    $variants
                );
                if ($q && $q->num_rows() > 0) {
                    $total += (int) ($q->row()->c ?? 0);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $total;
    }

    /** Tandai semua inbound Fonnte untuk nomor ini sebagai read. */
    public static function markFonnteInboundRead($db, string $phone): int
    {
        if (!self::fonnteInboundStatusReady($db)) {
            return 0;
        }
        [$inSql, $variants] = self::phoneInClause($phone);
        if ($inSql === '') {
            return 0;
        }
        try {
            $db->query(
                "UPDATE wa_fonnte_messages_in SET status = 'read' WHERE phone IN ({$inSql}) AND status = 'received'",
                $variants
            );

            return (int) $db->affected_rows();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Tandai semua inbound yCloud untuk nomor ini sebagai read (semua varian phone). */
    public static function markYcloudInboundRead($db, string $phone): int
    {
        [$inSql, $variants] = self::phoneInClause($phone);
        if ($inSql === '') {
            return 0;
        }
        try {
            $db->query(
                "UPDATE wa_messages_in SET status = 'read' WHERE phone IN ({$inSql})",
                $variants
            );

            return (int) $db->affected_rows();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Ambil preview pesan terakhir dari semua tabel pesan (yCloud + Fonnte).
     *
     * @return array{last_message:?string,last_message_time:?string}|null
     */
    public static function fetchLatestMessageMeta($db, string $phone): ?array
    {
        if (!class_exists(WaSenderContext::class)) {
            require_once __DIR__ . '/WaSenderContext.php';
        }

        $key = WaSenderContext::key($phone);
        if ($key === '') {
            return null;
        }

        $batch = self::fetchLatestMessageMetaBatch($db, [$phone]);

        return $batch[$key] ?? null;
    }

    private static function formatMessagePreview(string $body, string $type, string $sender): string
    {
        $body = trim($body);
        $isOut = $sender === 'me';
        $prefix = $isOut ? 'o- ' : '';

        if ($body !== '' && !preg_match('/^\[[a-z_]+\]$/i', $body)) {
            return $prefix . mb_substr($body, 0, 50);
        }

        $labels = [
            'location' => '📍 Lokasi',
            'image' => '🖼 Gambar',
            'audio' => '🎤 Audio',
            'voice' => '🎤 Audio',
            'video' => '🎬 Video',
            'document' => '📎 Dokumen',
            'sticker' => '🎨 Sticker',
            'template' => 'Template',
        ];
        $label = $labels[$type] ?? '📎 Media';

        return $prefix . $label;
    }

    /**
     * Fallback metadata wa_conversations vs wa_fonnte_conversations (tanpa scan pesan).
     *
     * @return array{last_message:?string,last_message_time:?string}
     */
    public static function mergeLastMessageMetaFromMetadata($db, string $phone, ?object $conv): array
    {
        $yMsg = $conv ? ($conv->last_message ?? null) : null;
        $yTime = $conv ? ($conv->last_message_at ?? null) : null;

        $fonnteConv = null;
        foreach (self::phoneVariants($phone) as $variant) {
            $row = $db->get_where('wa_fonnte_conversations', ['phone' => $variant])->row();
            if ($row) {
                $fonnteConv = $row;
                break;
            }
        }

        if (!$fonnteConv) {
            return [
                'last_message' => $yMsg,
                'last_message_time' => $yTime,
            ];
        }

        $fTime = $fonnteConv->last_message_at ?? null;
        $yTs = ($yTime !== null && $yTime !== '') ? strtotime((string) $yTime) : false;
        $fTs = ($fTime !== null && $fTime !== '') ? strtotime((string) $fTime) : false;
        if ($fTs !== false && ($yTs === false || $fTs >= $yTs)) {
            return [
                'last_message' => $fonnteConv->last_message ?? $yMsg,
                'last_message_time' => $fTime,
            ];
        }

        return [
            'last_message' => $yMsg,
            'last_message_time' => $yTime,
        ];
    }

    /**
     * @param array<string, array{last_message:?string,last_message_time:?string}>|null $batchCache
     * @return array{last_message:?string,last_message_time:?string}
     */
    public static function resolveLastMessageMeta($db, string $phone, ?object $conv, ?array $batchCache = null): array
    {
        if (!class_exists(WaSenderContext::class)) {
            require_once __DIR__ . '/WaSenderContext.php';
        }

        $key = WaSenderContext::key($phone);
        if ($batchCache !== null && $key !== '' && isset($batchCache[$key])) {
            return $batchCache[$key];
        }

        if ($batchCache === null) {
            $fromMessages = self::fetchLatestMessageMeta($db, $phone);
            if ($fromMessages !== null) {
                return $fromMessages;
            }
        }

        return self::mergeLastMessageMetaFromMetadata($db, $phone, $conv);
    }

    /**
     * @return array{last_message:?string,last_message_time:?string}
     */
    public static function mergeLastMessageMeta($db, string $phone, ?object $conv): array
    {
        return self::resolveLastMessageMeta($db, $phone, $conv, null);
    }
}
