<?php

namespace App\Helpers\CRM;

use App\Core\DB;

/**
 * Kirim pesan crew → pelanggan (CSW open) dengan verifikasi access_key karyawan.
 */
class CrewChatHelper
{
    private const SESSION_KEY = 'mdl_crm_session';
    private const POLISH_TTL = 600;

    /**
     * @return array{ok:bool,message?:string,items?:list<array{id_user:int,nama_user:string}>}
     */
    public static function listKaryawan(int $idCabang): array
    {
        if ($idCabang <= 0) {
            return ['ok' => false, 'message' => 'Cabang tidak valid'];
        }

        $db = DB::getInstance(1);
        $rows = $db->query(
            'SELECT id_user, nama_user FROM user WHERE id_cabang = ? AND en = 1 ORDER BY nama_user ASC',
            [$idCabang]
        )->result_array();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id_user' => (int) ($row['id_user'] ?? 0),
                'nama_user' => (string) ($row['nama_user'] ?? ''),
            ];
        }

        return ['ok' => true, 'items' => $items];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,status?:bool,new_words?:string,reason?:string,sapaan?:string,message?:string}
     */
    public static function polishMessage(array $input): array
    {
        $crewCabang = self::resolveCrewCabangId($input['user_id'] ?? null);
        if ($crewCabang <= 0) {
            return ['ok' => false, 'message' => 'Akses crew tidak valid'];
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        $draft = trim((string) ($input['draft'] ?? $input['message'] ?? ''));
        if ($phone === '' || $draft === '') {
            return ['ok' => false, 'message' => 'phone dan pesan wajib'];
        }

        $deny = self::assertCrewConversation($phone, $crewCabang);
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

        if (!empty($result['status']) && !empty($result['new_words'])) {
            self::storePolishApproval($phone, (string) $result['new_words']);
        } else {
            self::clearPolishApproval($phone);
        }

        return [
            'ok' => true,
            'status' => !empty($result['status']),
            'new_words' => (string) ($result['new_words'] ?? ''),
            'reason' => (string) ($result['reason'] ?? ''),
            'sapaan' => (string) ($result['sapaan'] ?? $sapaan),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,message?:string,data?:array<string,mixed>}
     */
    public static function sendReply(array $input): array
    {
        $crewCabang = self::resolveCrewCabangId($input['user_id'] ?? null);
        if ($crewCabang <= 0) {
            return ['ok' => false, 'message' => 'Akses crew tidak valid'];
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        $idKaryawan = (int) ($input['id_karyawan'] ?? $input['id_user'] ?? 0);
        $accessKey = trim((string) ($input['access_key'] ?? ''));

        if ($phone === '' || $message === '' || $idKaryawan <= 0 || $accessKey === '') {
            return ['ok' => false, 'message' => 'phone, message, karyawan, dan access_key wajib'];
        }

        if (!preg_match('/^\d{4}$/', $accessKey)) {
            return ['ok' => false, 'message' => 'Access key harus 4 digit'];
        }

        $deny = self::assertCrewConversation($phone, $crewCabang);
        if ($deny !== null) {
            return $deny;
        }

        $cswDeny = self::assertCswOpen($phone);
        if ($cswDeny !== null) {
            return $cswDeny;
        }

        if (!self::verifyPolishApproval($phone, $message)) {
            return ['ok' => false, 'message' => 'Pesan belum disetujui AI — klik Cek AI terlebih dahulu'];
        }

        $dbLaundry = DB::getInstance(1);
        $dbLaundry->query(
            'SELECT id_user, nama_user FROM user WHERE id_user = ? AND id_cabang = ? AND access_key = ? AND en = 1 LIMIT 1',
            [$idKaryawan, $crewCabang, $accessKey]
        );
        if ($dbLaundry->num_rows() === 0) {
            return ['ok' => false, 'message' => 'Karyawan atau access key tidak valid'];
        }

        $csw = CrmChatMergeHelper::getCswStatus(DB::getInstance(0), $phone);
        $lineKey = CrmChatMergeHelper::resolveReplyLine($csw, 'auto');
        if ($lineKey === null) {
            return ['ok' => false, 'message' => 'CSW sudah tutup — tidak bisa kirim pesan'];
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WhatsAppService')) {
            require_once __DIR__ . '/WhatsAppService.php';
        }

        $wa = new WhatsAppService();
        $res = $wa->sendFreeText($phone, $message, null, 'CR', null, $lineKey, $idKaryawan);

        if (empty($res['success'])) {
            return [
                'ok' => false,
                'message' => 'Gagal kirim WhatsApp: ' . ($res['error'] ?? 'Unknown error'),
            ];
        }

        $db = DB::getInstance(0);
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

    public static function resolveCrewCabangId($userId): int
    {
        if ($userId === null || $userId === '') {
            $sessionUser = $_SESSION[self::SESSION_KEY]['user'] ?? null;
            if (is_array($sessionUser) && strtolower((string) ($sessionUser['role'] ?? '')) === 'crew') {
                $userId = $sessionUser['username'] ?? $sessionUser['id'] ?? null;
            }
        }

        if ($userId === null || $userId === '') {
            return 0;
        }

        if (is_numeric($userId)) {
            $idCabang = (int) $userId;
            $db = DB::getInstance(1);
            $db->query('SELECT id_cabang FROM cabang WHERE id_cabang = ? LIMIT 1', [$idCabang]);
            if ($db->num_rows() > 0) {
                return $idCabang;
            }
        }

        return 0;
    }

    public static function isCrewUser($userId): bool
    {
        return self::resolveCrewCabangId($userId) > 0;
    }

    /** @return array{ok:bool,message?:string}|null */
    private static function assertCrewConversation(string $phone, int $crewCabang): ?array
    {
        $db = DB::getInstance(0);
        [, $variants] = CrmChatMergeHelper::phoneInClause($phone);
        if ($variants === []) {
            return ['ok' => false, 'message' => 'Nomor tidak valid'];
        }

        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $db->query(
            "SELECT assigned_user_id FROM wa_conversations WHERE wa_number IN ({$placeholders}) LIMIT 1",
            $variants
        );
        if ($db->num_rows() === 0) {
            return ['ok' => false, 'message' => 'Percakapan tidak ditemukan'];
        }

        $assigned = (int) ($db->row()->assigned_user_id ?? 0);
        if ($assigned !== $crewCabang) {
            return ['ok' => false, 'message' => 'Percakapan bukan cabang Anda'];
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

    private static function storePolishApproval(string $phone, string $message): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        if (!isset($_SESSION[self::SESSION_KEY]['crew_polish']) || !is_array($_SESSION[self::SESSION_KEY]['crew_polish'])) {
            $_SESSION[self::SESSION_KEY]['crew_polish'] = [];
        }
        $_SESSION[self::SESSION_KEY]['crew_polish'][$phone] = [
            'message' => $message,
            'expires' => time() + self::POLISH_TTL,
        ];
    }

    private static function verifyPolishApproval(string $phone, string $message): bool
    {
        $entry = $_SESSION[self::SESSION_KEY]['crew_polish'][$phone] ?? null;
        if (!is_array($entry)) {
            return false;
        }
        if (time() > (int) ($entry['expires'] ?? 0)) {
            return false;
        }

        return hash_equals((string) ($entry['message'] ?? ''), $message);
    }

    private static function clearPolishApproval(string $phone): void
    {
        unset($_SESSION[self::SESSION_KEY]['crew_polish'][$phone]);
    }
}
