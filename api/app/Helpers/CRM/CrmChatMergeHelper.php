<?php

namespace App\Helpers\CRM;

/**
 * Gabung CRM chat yCloud + Fonnte (fase 1): CSW ganda, bubble merge, routing balasan.
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

    public static function getYcloudLastInAt($db, string $phone): ?string
    {
        $conv = self::findWaConversation($db, $phone);
        if (!$conv || empty($conv->last_in_at)) {
            return null;
        }

        return (string) $conv->last_in_at;
    }

    public static function getFonnteLastInAt($db, string $phone): ?string
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
     *   ycloud_open: bool,
     *   fonnte_open: bool,
     *   last_in_at_ycloud: ?string,
     *   last_in_at_fonnte: ?string,
     *   default_reply_channel: ?string,
     *   can_reply: bool
     * }
     */
    public static function getCswStatus($db, string $phone): array
    {
        $lastY = self::getYcloudLastInAt($db, $phone);
        $lastF = self::getFonnteLastInAt($db, $phone);
        $ycloudOpen = self::isWithinCsw($lastY);
        $fonnteOpen = self::isWithinCsw($lastF);

        $default = null;
        if ($ycloudOpen && !$fonnteOpen) {
            $default = 'ycloud';
        } elseif (!$ycloudOpen && $fonnteOpen) {
            $default = 'fonnte';
        } elseif ($ycloudOpen && $fonnteOpen) {
            if ($lastF !== null && ($lastY === null || strtotime($lastF) >= strtotime($lastY))) {
                $default = 'fonnte';
            } else {
                $default = 'ycloud';
            }
        }

        return [
            'ycloud_open' => $ycloudOpen,
            'fonnte_open' => $fonnteOpen,
            'last_in_at_ycloud' => $lastY,
            'last_in_at_fonnte' => $lastF,
            'default_reply_channel' => $default,
            'can_reply' => $ycloudOpen || $fonnteOpen,
        ];
    }

    /**
     * @param 'auto'|'ycloud'|'fonnte'|null $requested
     * @return 'ycloud'|'fonnte'|null
     */
    public static function resolveReplyChannel(array $csw, ?string $requested): ?string
    {
        $req = $requested !== null ? strtolower(trim($requested)) : 'auto';
        if ($req === '' || $req === 'auto') {
            $ch = $csw['default_reply_channel'] ?? null;

            return ($ch === 'ycloud' || $ch === 'fonnte') ? $ch : null;
        }
        if ($req === 'ycloud' && !empty($csw['ycloud_open'])) {
            return 'ycloud';
        }
        if ($req === 'fonnte' && !empty($csw['fonnte_open'])) {
            return 'fonnte';
        }

        return null;
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
            } elseif (!empty($ctx['assigned_user_id'])) {
                $update['assigned_user_id'] = (int) $ctx['assigned_user_id'];
            }
            if (!empty($ctx['code'])) {
                $update['code'] = mb_substr((string) $ctx['code'], 0, 16);
            }
            if (!empty($ctx['cust_id'])) {
                $update['cust_id'] = (int) $ctx['cust_id'];
            }
            $db->update('wa_conversations', $update, ['id' => $convId]);
            self::rememberConversationAliases($db, $convId, $phone, $hints, 'fonnte');

            return $convId;
        }

        $insert = [
            'wa_number' => $waNumber,
            'contact_name' => $ctx['contact_name'] ?? null,
            'assigned_user_id' => $ctx['assigned_user_id'] ?? null,
            'code' => $ctx['code'] ?? null,
            'cust_id' => $ctx['cust_id'] ?? null,
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
     * Placeholder IN (?,?) untuk phoneVariants.
     *
     * @return array{0:string,1:string[]}
     */
    public static function phoneInClause(string $phone): array
    {
        $variants = self::phoneVariants($phone);
        if ($variants === []) {
            return ['', []];
        }

        return [implode(',', array_fill(0, count($variants), '?')), $variants];
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
     * @return array{last_message:?string,last_message_time:?string}
     */
    public static function mergeLastMessageMeta($db, string $phone, ?object $conv): array
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
        if ($fTime !== null && $fTime !== '' && ($yTime === null || $yTime === '' || $fTime >= $yTime)) {
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
}
