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

    public static function findWaConversation($db, string $phone): ?object
    {
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
     */
    public static function ensureShellFromFonnte($db, string $phone, array $ctx, string $lastMessage, string $lastMessageAt): int
    {
        $waNumber = self::normalizeWaNumber($phone);
        $conv = self::findWaConversation($db, $phone);
        if ($conv) {
            $convId = (int) ($conv->id ?? 0);
            $existingAt = (string) ($conv->last_message_at ?? '');
            if ($lastMessageAt !== '' && ($existingAt === '' || $lastMessageAt >= $existingAt)) {
                $update = [
                    'last_message' => $lastMessage,
                    'last_message_at' => $lastMessageAt,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if (!empty($ctx['contact_name'])) {
                    $update['contact_name'] = $ctx['contact_name'];
                }
                $db->update('wa_conversations', $update, ['id' => $convId]);
            }

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
        if ($db->insert('wa_conversations', $insert)) {
            return (int) $db->insert_id();
        }

        return 0;
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
