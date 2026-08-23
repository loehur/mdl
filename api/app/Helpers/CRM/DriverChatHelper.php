<?php

namespace App\Helpers\CRM;

use App\Core\DB;

/**
 * Driver CRM — login via no_user, balas chat case kuning/merah, sender_code DR.
 */
class DriverChatHelper
{
    private const SESSION_KEY = 'mdl_crm_session';
    private const POLISH_TTL = 600;

    /**
     * @return array{id_user:int,nama_user:string,id_cabang:int,login_key:string}|null
     */
    public static function resolveDriverUser($loginId): ?array
    {
        if ($loginId === null || $loginId === '') {
            $sessionUser = $_SESSION[self::SESSION_KEY]['user'] ?? null;
            if (is_array($sessionUser) && strtolower((string) ($sessionUser['role'] ?? '')) === 'driver') {
                $loginId = $sessionUser['username'] ?? $sessionUser['id'] ?? null;
            }
        }

        if ($loginId === null || $loginId === '') {
            return null;
        }

        if (!class_exists(WaSenderContext::class)) {
            require_once __DIR__ . '/WaSenderContext.php';
        }

        $nomor = WaSenderContext::toNomorNasional((string) $loginId);
        if ($nomor === null) {
            return null;
        }

        $db = DB::getInstance(1);
        $expr = WaSenderContext::sqlDigitsExpr('no_user');
        $db->query(
            "SELECT id_user, nama_user, id_cabang FROM user WHERE en = 1 AND {$expr} LIKE ? LIMIT 1",
            ['%' . $nomor]
        );

        if ($db->num_rows() === 0) {
            return null;
        }

        $row = $db->row();
        $loginKey = strtoupper(WaSenderContext::key((string) $loginId));

        return [
            'id_user' => (int) ($row->id_user ?? 0),
            'nama_user' => (string) ($row->nama_user ?? ''),
            'id_cabang' => (int) ($row->id_cabang ?? 0),
            'login_key' => $loginKey !== '' ? $loginKey : strtoupper((string) $loginId),
        ];
    }

    public static function isDriverUser($loginId): bool
    {
        if (CrewChatHelper::isCrewUser($loginId)) {
            return false;
        }

        return self::resolveDriverUser($loginId) !== null;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,status?:bool,new_words?:string,reason?:string,sapaan?:string,message?:string,field?:string}
     */
    public static function polishMessage(array $input): array
    {
        $driver = self::resolveDriverUser($input['user_id'] ?? null);
        if ($driver === null || $driver['id_user'] <= 0) {
            return ['ok' => false, 'message' => 'Akses driver tidak valid'];
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        $draft = trim((string) ($input['draft'] ?? $input['message'] ?? ''));
        if ($phone === '' || $draft === '') {
            return ['ok' => false, 'message' => 'phone dan pesan wajib'];
        }

        $accessDeny = self::validateDriverAccessKey($driver['id_user'], (string) ($input['access_key'] ?? ''));
        if ($accessDeny !== null) {
            return $accessDeny;
        }

        $deny = self::assertDriverConversation($phone);
        if ($deny !== null) {
            return $deny;
        }

        $cswDeny = self::assertCswOpen($phone);
        if ($cswDeny !== null) {
            return $cswDeny;
        }

        $sapaan = SapaanResolver::resolve($phone);
        $polisher = new CrewMessagePolisher();
        $result = $polisher->polish($draft, $sapaan);

        $polishToken = '';
        if (!empty($result['status']) && !empty($result['new_words'])) {
            $approvedText = trim((string) $result['new_words']);
            try {
                self::storePolishApproval($phone, $approvedText);
                $polishToken = self::createPolishToken($phone, $approvedText);
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Gagal membuat token persetujuan: ' . $e->getMessage(),
                ];
            }
            if ($polishToken === '') {
                return [
                    'ok' => false,
                    'message' => 'Gagal membuat token persetujuan pesan',
                ];
            }
        } else {
            self::clearPolishApproval($phone);
        }

        return [
            'ok' => true,
            'status' => !empty($result['status']),
            'new_words' => (string) ($result['new_words'] ?? ''),
            'reason' => (string) ($result['reason'] ?? ''),
            'sapaan' => (string) ($result['sapaan'] ?? $sapaan),
            'polish_token' => $polishToken,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,message?:string,data?:array<string,mixed>}
     */
    public static function sendReply(array $input): array
    {
        $driver = self::resolveDriverUser($input['user_id'] ?? null);
        if ($driver === null || $driver['id_user'] <= 0) {
            return ['ok' => false, 'message' => 'Akses driver tidak valid'];
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        $idUser = $driver['id_user'];

        if ($phone === '' || $message === '') {
            return ['ok' => false, 'message' => 'phone dan pesan wajib'];
        }

        $accessDeny = self::validateDriverAccessKey($idUser, (string) ($input['access_key'] ?? ''));
        if ($accessDeny !== null) {
            if (class_exists('\\Log')) {
                \Log::write(
                    'driverReply access_key reject id_user=' . $idUser,
                    'crm_driver',
                    'Chat'
                );
            }
            return $accessDeny;
        }

        $deny = self::assertDriverConversation($phone);
        if ($deny !== null) {
            return $deny;
        }

        $cswDeny = self::assertCswOpen($phone);
        if ($cswDeny !== null) {
            return $cswDeny;
        }

        if (!self::verifyPolishApproval($phone, $message, (string) ($input['polish_token'] ?? ''))) {
            return ['ok' => false, 'message' => 'Pesan belum dirapikan — klik Rapikan Pesan terlebih dahulu'];
        }

        $db = DB::getInstance(0);
        $pendingOutbound = CrmChatMergeHelper::findUnansweredOutboundTexts($db, $phone);
        if ($pendingOutbound !== []) {
            if (!class_exists(CrewOutboundSpamGuard::class)) {
                require_once __DIR__ . '/CrewOutboundSpamGuard.php';
            }
            $pendingBodies = array_column($pendingOutbound, 'body');
            $spam = (new CrewOutboundSpamGuard())->check($pendingBodies, $message);
            if (!empty($spam['duplicate_spam'])) {
                return [
                    'ok' => false,
                    'message' => $spam['message'] ?: CrewOutboundSpamGuard::REJECT_MESSAGE,
                ];
            }
        }

        $csw = CrmChatMergeHelper::getCswStatus($db, $phone);
        $lineKey = CrmChatMergeHelper::resolveReplyLine($csw, 'auto');
        if ($lineKey === null) {
            return ['ok' => false, 'message' => 'CSW sudah tutup — tidak bisa kirim pesan'];
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WhatsAppService')) {
            require_once __DIR__ . '/WhatsAppService.php';
        }

        $wa = new WhatsAppService();
        $res = $wa->sendFreeText($phone, $message, null, 'DR', null, $lineKey, $idUser);

        if (empty($res['success'])) {
            return [
                'ok' => false,
                'message' => 'Gagal kirim WhatsApp: ' . ($res['error'] ?? 'Unknown error'),
            ];
        }

        $conv = CrmChatMergeHelper::findWaConversation($db, $phone);
        if ($conv && !empty($conv->wa_number)) {
            $db->update('wa_conversations', [
                'last_message' => 'o- ' . mb_substr($message, 0, 50),
                'last_message_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['wa_number' => $conv->wa_number]);
        }

        self::clearPolishApproval($phone);

        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $data['local_id'] = $res['local_id'] ?? null;
        $data['line_key'] = $lineKey;
        $data['channel'] = $lineKey;
        $data['provider'] = $lineKey;
        if (!class_exists('\\App\\Helpers\\CRM\\WaLineResolver')) {
            require_once __DIR__ . '/WaLineResolver.php';
        }
        $data = array_merge($data, WaLineResolver::messageApiFields($lineKey));

        return [
            'ok' => true,
            'message' => 'Pesan terkirim',
            'data' => $data,
        ];
    }

    /** Apakah conversation punya case kuning (2) atau merah (3) yang open. */
    public static function conversationHasYellowRedCase($db, object $conv): bool
    {
        foreach (self::normalizeCaseList($conv->conv_case ?? null) as $item) {
            $caseId = (int) ($item['case'] ?? 0);
            if (($caseId === 2 || $caseId === 3) && (($item['status'] ?? 'open') !== 'closed')) {
                return true;
            }
        }

        if (self::hasActiveDelivery($conv)) {
            return true;
        }

        return self::hasActivePermintaan($db, (string) ($conv->wa_number ?? ''));
    }

    /** @return array{ok:bool,message?:string}|null */
    private static function assertDriverConversation(string $phone): ?array
    {
        $db = DB::getInstance(0);
        [, $variants] = CrmChatMergeHelper::phoneInClause($phone);
        if ($variants === []) {
            return ['ok' => false, 'message' => 'Nomor tidak valid'];
        }

        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $db->query(
            "SELECT assigned_user_id, conv_case, cust_id, wa_number FROM wa_conversations WHERE wa_number IN ({$placeholders}) LIMIT 1",
            $variants
        );
        if ($db->num_rows() === 0) {
            return ['ok' => false, 'message' => 'Percakapan tidak ditemukan'];
        }

        $conv = $db->row();
        if ($conv->assigned_user_id === null || (int) $conv->assigned_user_id <= 0) {
            return ['ok' => false, 'message' => 'Percakapan tidak tersedia untuk driver'];
        }

        if (!self::conversationHasYellowRedCase($db, $conv)) {
            return ['ok' => false, 'message' => 'Hanya chat case kuning/merah yang bisa dibalas driver'];
        }

        return null;
    }

    /** @return array{ok:false,message:string,field:string}|null */
    private static function validateDriverAccessKey(int $idUser, string $accessKey): ?array
    {
        $accessKey = trim($accessKey);
        if ($accessKey === '') {
            return ['ok' => false, 'message' => 'Access key wajib diisi', 'field' => 'access_key'];
        }

        if (!preg_match('/^\d{4}$/', $accessKey)) {
            return ['ok' => false, 'message' => 'Access key harus 4 digit', 'field' => 'access_key'];
        }

        $dbLaundry = DB::getInstance(1);
        $dbLaundry->query(
            'SELECT id_user FROM user WHERE id_user = ? AND access_key = ? AND en = 1 LIMIT 1',
            [$idUser, $accessKey]
        );
        if ($dbLaundry->num_rows() === 0) {
            return ['ok' => false, 'message' => 'Access key salah', 'field' => 'access_key'];
        }

        return null;
    }

    /** @return array{ok:bool,message?:string}|null */
    private static function assertCswOpen(string $phone): ?array
    {
        $csw = CrmChatMergeHelper::getCswStatus(DB::getInstance(0), $phone);
        $lineKey = CrmChatMergeHelper::resolveReplyLine($csw, 'auto');
        if ($lineKey === null) {
            return ['ok' => false, 'message' => 'CSW sudah tutup — tidak bisa kirim pesan'];
        }

        return null;
    }

    private static function hasActiveDelivery(object $conv): bool
    {
        if (!class_exists(WaSenderContext::class)) {
            require_once __DIR__ . '/WaSenderContext.php';
        }

        $custId = (int) ($conv->cust_id ?? 0);
        $phoneKey = WaSenderContext::key((string) ($conv->wa_number ?? ''));

        if ($custId <= 0 && $phoneKey === '') {
            return false;
        }

        $dbLaundry = DB::getInstance(1);
        $clauses = [];
        $params = [];
        if ($custId > 0) {
            $clauses[] = 'id_pelanggan = ?';
            $params[] = $custId;
        }
        if ($phoneKey !== '') {
            $clauses[] = 'phone_tail = ?';
            $params[] = $phoneKey;
        }

        try {
            $dbLaundry->query(
                'SELECT id FROM delivery_request
                 WHERE delivery_status IN (\'berjalan\',\'menunggu_pembayaran\')
                   AND (' . implode(' OR ', $clauses) . ')
                 LIMIT 1',
                $params
            );

            return $dbLaundry->num_rows() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function hasActivePermintaan($db, string $phone): bool
    {
        $key = self::permintaanPhoneKey($phone);
        if ($key === '') {
            return false;
        }

        try {
            $db->query(
                "SELECT id FROM wa_permintaan_session
                 WHERE status = 'open'
                   AND notify_expires_at > NOW()
                   AND REPLACE(REPLACE(phone, '+', ''), ' ', '') = ?
                 LIMIT 1",
                [$key]
            );

            return $db->num_rows() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function permintaanPhoneKey(string $phone): string
    {
        $d = preg_replace('/[^0-9]/', '', $phone);
        if ($d === '') {
            return '';
        }
        if ($d[0] === '0') {
            return '62' . substr($d, 1);
        }
        if (substr($d, 0, 2) !== '62' && $d[0] === '8') {
            return '62' . $d;
        }

        return $d;
    }

    /** @return list<array{case:int,status:string}> */
    private static function normalizeCaseList($rawCase): array
    {
        if (is_string($rawCase)) {
            $trimmed = trim($rawCase);
            if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
                $parsed = json_decode($trimmed, true);
                if (is_array($parsed)) {
                    if (isset($parsed[0])) {
                        return $parsed;
                    }
                    if (isset($parsed['case'])) {
                        return [$parsed];
                    }
                }
            }
        }

        if (is_numeric($rawCase)) {
            return [['case' => (int) $rawCase, 'status' => 'open']];
        }

        return [];
    }

    private static function storePolishApproval(string $phone, string $message): void
    {
        $message = trim($message);
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        if (!isset($_SESSION[self::SESSION_KEY]['driver_polish']) || !is_array($_SESSION[self::SESSION_KEY]['driver_polish'])) {
            $_SESSION[self::SESSION_KEY]['driver_polish'] = [];
        }
        $_SESSION[self::SESSION_KEY]['driver_polish'][$phone] = [
            'message' => $message,
            'expires' => time() + self::POLISH_TTL,
        ];
    }

    private static function verifyPolishApproval(string $phone, string $message, string $polishToken = ''): bool
    {
        $message = trim($message);
        if ($polishToken !== '' && self::verifyPolishToken($phone, $message, $polishToken)) {
            return true;
        }

        $entry = $_SESSION[self::SESSION_KEY]['driver_polish'][$phone] ?? null;
        if (!is_array($entry)) {
            return false;
        }
        if (time() > (int) ($entry['expires'] ?? 0)) {
            return false;
        }

        return hash_equals((string) ($entry['message'] ?? ''), $message);
    }

    public static function createPolishToken(string $phone, string $message): string
    {
        $message = trim($message);
        $phoneKey = self::phoneKey($phone);
        if ($phoneKey === '' || $message === '') {
            return '';
        }

        $exp = time() + self::POLISH_TTL;
        $msgHash = hash('sha256', $message);
        $payload = $phoneKey . '|' . $exp . '|' . $msgHash;
        $sig = hash_hmac('sha256', $payload, self::polishTokenSecret());

        return rtrim(strtr(base64_encode($payload . '|' . $sig), '+/', '-_'), '=');
    }

    private static function verifyPolishToken(string $phone, string $message, string $token): bool
    {
        $message = trim($message);
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if (!is_string($raw) || $raw === '') {
            return false;
        }

        $parts = explode('|', $raw);
        if (count($parts) !== 4) {
            return false;
        }

        [$phoneKey, $expStr, $msgHash, $sig] = $parts;
        if ($phoneKey !== self::phoneKey($phone)) {
            return false;
        }

        $exp = (int) $expStr;
        if ($exp <= 0 || time() > $exp) {
            return false;
        }

        if (!hash_equals($msgHash, hash('sha256', $message))) {
            return false;
        }

        $payload = $phoneKey . '|' . $expStr . '|' . $msgHash;
        $expected = hash_hmac('sha256', $payload, self::polishTokenSecret());

        return hash_equals($expected, $sig);
    }

    private static function phoneKey(string $phone): string
    {
        if (!class_exists(WaSenderContext::class)) {
            require_once __DIR__ . '/WaSenderContext.php';
        }

        return WaSenderContext::key($phone);
    }

    private static function polishTokenSecret(): string
    {
        if (class_exists('\\Env', false)) {
            return (string) \Env::CRON_SECRET;
        }

        return 'mdl_crm_driver_polish';
    }

    private static function clearPolishApproval(string $phone): void
    {
        unset($_SESSION[self::SESSION_KEY]['driver_polish'][$phone]);
    }
}
