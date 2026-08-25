<?php

namespace App\Helpers\Laundry;

use App\Core\DB;

/**
 * Permintaan pelanggan (wa_permintaan_session) — dipakai CRM Customer Panel.
 */
class PermintaanStore
{
    private const SESSION_TTL_MINUTES = 60;
    private const NOTIFY_TTL_HOURS = 24;

    /**
     * @return array{ok:bool,message?:string,items?:list<array<string,mixed>>}
     */
    public static function listOpen(int $idPelanggan, string $waNumber = ''): array
    {
        $phoneKey = self::normalizePhoneKey($waNumber);

        if ($idPelanggan > 0) {
            $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
            if ($pel !== null) {
                $fromPel = self::normalizePhoneKey(PelangganLokasiStore::primaryPhone($pel));
                if ($fromPel !== '') {
                    $phoneKey = $phoneKey !== '' ? $phoneKey : $fromPel;
                }
            } elseif ($phoneKey === '') {
                return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
            }
        }

        if ($idPelanggan <= 0 && $phoneKey === '') {
            return ['ok' => false, 'message' => 'id_pelanggan / cust_id atau wa_number wajib'];
        }

        try {
            $db = DB::getInstance(0);
            if ($idPelanggan > 0 && $phoneKey !== '') {
                $rows = $db->query(
                    "SELECT phone, id_pelanggan, id_cabang, status, summary, raw_log, updated_at, expires_at, notify_expires_at
                     FROM wa_permintaan_session
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND (
                         id_pelanggan = ?
                         OR REPLACE(REPLACE(phone, '+', ''), ' ', '') = ?
                       )
                     ORDER BY updated_at DESC
                     LIMIT 5",
                    [$idPelanggan, $phoneKey]
                )->result_array();
            } elseif ($idPelanggan > 0) {
                $rows = $db->query(
                    "SELECT phone, id_pelanggan, id_cabang, status, summary, raw_log, updated_at, expires_at, notify_expires_at
                     FROM wa_permintaan_session
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND id_pelanggan = ?
                     ORDER BY updated_at DESC
                     LIMIT 5",
                    [$idPelanggan]
                )->result_array();
            } else {
                $rows = $db->query(
                    "SELECT phone, id_pelanggan, id_cabang, status, summary, raw_log, updated_at, expires_at, notify_expires_at
                     FROM wa_permintaan_session
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND REPLACE(REPLACE(phone, '+', ''), ' ', '') = ?
                     ORDER BY updated_at DESC
                     LIMIT 5",
                    [$phoneKey]
                )->result_array();
            }
        } catch (\Throwable $e) {
            return ['ok' => true, 'items' => []];
        }

        if (!is_array($rows)) {
            $rows = [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = self::formatItem($row);
        }

        return ['ok' => true, 'items' => $items];
    }

    /**
     * Tandai permintaan selesai — sama seperti Estimasi/selesaiPermintaan di laundry.
     *
     * @param array{phone?:string,wa_number?:string,cust_id?:int,id_pelanggan?:int,user_id?:string} $input
     * @return array{ok:bool,message?:string,session_closed?:bool,case_closed?:bool,phone?:string}
     */
    public static function complete(array $input): array
    {
        $idPelanggan = (int) ($input['id_pelanggan'] ?? $input['cust_id'] ?? 0);
        $waNumber = trim((string) ($input['phone'] ?? $input['wa_number'] ?? ''));

        if ($idPelanggan <= 0 && $waNumber === '') {
            return ['ok' => false, 'message' => 'wa_number atau cust_id wajib'];
        }

        $sessionClosed = self::markOpenSessionsFulfilled($idPelanggan, $waNumber);
        $caseResult = self::closePermintaanCase($idPelanggan, $waNumber, $input['user_id'] ?? null);
        $caseClosed = !empty($caseResult['closed']);

        if (!$sessionClosed && !$caseClosed) {
            return ['ok' => false, 'message' => 'Permintaan tidak ditemukan atau sudah selesai'];
        }

        return [
            'ok' => true,
            'message' => 'Permintaan ditandai selesai',
            'session_closed' => $sessionClosed,
            'case_closed' => $caseClosed,
            'phone' => (string) ($caseResult['wa_number'] ?? ''),
        ];
    }

    /**
     * Ubah isi ringkasan permintaan (field summary saja).
     *
     * @param array{phone?:string,wa_number?:string,cust_id?:int,id_pelanggan?:int,summary:string} $input
     * @return array{ok:bool,message?:string,item?:array<string,mixed>}
     */
    public static function updateSummary(array $input): array
    {
        $summary = trim((string) ($input['summary'] ?? ''));
        if ($summary === '') {
            return ['ok' => false, 'message' => 'Isi permintaan wajib diisi'];
        }
        $summary = PermintaanSummaryHelper::finalize($summary, 2000);
        if ($summary === '') {
            return ['ok' => false, 'message' => 'Isi permintaan wajib diisi'];
        }
        if (mb_strlen($summary) > 2000) {
            return ['ok' => false, 'message' => 'Isi permintaan maks. 2000 karakter'];
        }

        $idPelanggan = (int) ($input['id_pelanggan'] ?? $input['cust_id'] ?? 0);
        $waNumber = trim((string) ($input['phone'] ?? $input['wa_number'] ?? ''));

        $row = self::findOpenSessionRow($idPelanggan, $waNumber);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Permintaan open tidak ditemukan'];
        }

        try {
            $db = DB::getInstance(0);
            $db->query(
                'UPDATE wa_permintaan_session SET summary = ?, updated_at = NOW() WHERE phone = ?',
                [$summary, (string) ($row['phone'] ?? '')]
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal memperbarui permintaan'];
        }

        $idCabang = (int) ($row['id_cabang'] ?? 0);
        PermintaanNotifyHelper::forwardToCabangGroup(
            (string) ($row['phone'] ?? ''),
            $idCabang > 0 ? $idCabang : null,
            $summary,
            true
        );

        $row['summary'] = $summary;
        $row['updated_at'] = date('Y-m-d H:i:s');

        return [
            'ok' => true,
            'message' => 'Permintaan diperbarui',
            'item' => self::formatItem($row, $summary),
        ];
    }

    /**
     * Buat session permintaan open manual dari CRM.
     *
     * @param array{phone?:string,wa_number?:string,cust_id?:int,id_pelanggan?:int,summary:string} $input
     * @return array{ok:bool,message?:string,item?:array<string,mixed>}
     */
    public static function create(array $input): array
    {
        $summary = trim((string) ($input['summary'] ?? ''));
        if ($summary === '') {
            return ['ok' => false, 'message' => 'Isi permintaan wajib diisi'];
        }
        $summary = PermintaanSummaryHelper::finalize($summary, 2000);
        if ($summary === '') {
            return ['ok' => false, 'message' => 'Isi permintaan wajib diisi'];
        }
        if (mb_strlen($summary) > 2000) {
            return ['ok' => false, 'message' => 'Isi permintaan maks. 2000 karakter'];
        }

        $idPelanggan = (int) ($input['id_pelanggan'] ?? $input['cust_id'] ?? 0);
        $waNumber = trim((string) ($input['phone'] ?? $input['wa_number'] ?? ''));

        $phoneStorage = self::resolvePhoneStorage($idPelanggan, $waNumber);
        if ($phoneStorage === '') {
            return ['ok' => false, 'message' => 'Nomor WA wajib'];
        }

        if (self::findOpenSessionRow($idPelanggan, $phoneStorage) !== null) {
            return ['ok' => false, 'message' => 'Permintaan open sudah ada — gunakan Edit'];
        }

        $idCabang = 0;
        if ($idPelanggan > 0) {
            $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
            if ($pel !== null) {
                $idCabang = (int) ($pel['id_cabang'] ?? 0);
            }
        }

        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + (self::SESSION_TTL_MINUTES * 60));
        $notifyExpires = date('Y-m-d H:i:s', time() + (self::NOTIFY_TTL_HOURS * 3600));
        $rawLog = '[CRM manual] ' . $summary;

        try {
            $db = DB::getInstance(0);
            $db->query(
                'INSERT INTO wa_permintaan_session
                 (phone, id_pelanggan, id_cabang, status, summary, raw_log,
                  reject_reason, reject_alt, reply_text, updated_at, expires_at, notify_expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    id_pelanggan = VALUES(id_pelanggan),
                    id_cabang = VALUES(id_cabang),
                    status = VALUES(status),
                    summary = VALUES(summary),
                    raw_log = VALUES(raw_log),
                    reject_reason = NULL,
                    reject_alt = NULL,
                    reply_text = NULL,
                    updated_at = VALUES(updated_at),
                    expires_at = VALUES(expires_at),
                    notify_expires_at = VALUES(notify_expires_at)',
                [
                    $phoneStorage,
                    $idPelanggan > 0 ? $idPelanggan : null,
                    $idCabang > 0 ? $idCabang : null,
                    'open',
                    $summary,
                    $rawLog,
                    $now,
                    $expires,
                    $notifyExpires,
                ]
            );

            self::openPermintaanCase($db, $phoneStorage);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal membuat permintaan'];
        }

        PermintaanNotifyHelper::forwardToCabangGroup(
            $phoneStorage,
            $idCabang > 0 ? $idCabang : null,
            $summary,
            false
        );

        $row = [
            'phone' => $phoneStorage,
            'id_pelanggan' => $idPelanggan > 0 ? $idPelanggan : null,
            'summary' => $summary,
            'updated_at' => $now,
            'notify_expires_at' => $notifyExpires,
            'expires_at' => $expires,
        ];

        return [
            'ok' => true,
            'message' => 'Permintaan dibuat',
            'item' => self::formatItem($row, $summary),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function findOpenSessionRow(int $idPelanggan, string $waNumber): ?array
    {
        $phoneKey = self::normalizePhoneKey($waNumber);

        if ($idPelanggan > 0) {
            $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
            if ($pel !== null) {
                $fromPel = self::normalizePhoneKey(PelangganLokasiStore::primaryPhone($pel));
                if ($fromPel !== '') {
                    $phoneKey = $phoneKey !== '' ? $phoneKey : $fromPel;
                }
            } elseif ($phoneKey === '') {
                return null;
            }
        }

        if ($idPelanggan <= 0 && $phoneKey === '') {
            return null;
        }

        try {
            $db = DB::getInstance(0);
            if ($idPelanggan > 0 && $phoneKey !== '') {
                $row = $db->query(
                    "SELECT phone, id_pelanggan, id_cabang, status, summary, raw_log, updated_at, expires_at, notify_expires_at
                     FROM wa_permintaan_session
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND (
                         id_pelanggan = ?
                         OR REPLACE(REPLACE(phone, '+', ''), ' ', '') = ?
                       )
                     ORDER BY updated_at DESC
                     LIMIT 1",
                    [$idPelanggan, $phoneKey]
                )->row_array();
            } elseif ($idPelanggan > 0) {
                $row = $db->query(
                    "SELECT phone, id_pelanggan, id_cabang, status, summary, raw_log, updated_at, expires_at, notify_expires_at
                     FROM wa_permintaan_session
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND id_pelanggan = ?
                     ORDER BY updated_at DESC
                     LIMIT 1",
                    [$idPelanggan]
                )->row_array();
            } else {
                $row = $db->query(
                    "SELECT phone, id_pelanggan, id_cabang, status, summary, raw_log, updated_at, expires_at, notify_expires_at
                     FROM wa_permintaan_session
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND REPLACE(REPLACE(phone, '+', ''), ' ', '') = ?
                     ORDER BY updated_at DESC
                     LIMIT 1",
                    [$phoneKey]
                )->row_array();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($row) && !empty($row['phone']) ? $row : null;
    }

    private static function resolvePhoneStorage(int $idPelanggan, string $waNumber): string
    {
        $phoneStorage = self::normalizePhoneStorage($waNumber);
        if ($phoneStorage !== '') {
            return $phoneStorage;
        }

        if ($idPelanggan > 0) {
            $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
            if ($pel !== null) {
                return self::normalizePhoneStorage(PelangganLokasiStore::primaryPhone($pel));
            }
        }

        return '';
    }

    private static function normalizePhoneStorage(string $phone): string
    {
        $key = self::normalizePhoneKey($phone);
        if ($key === '') {
            return '';
        }

        return '+' . $key;
    }

    private static function openPermintaanCase(DB $db, string $phoneStorage): void
    {
        $phoneKey = self::normalizePhoneKey($phoneStorage);
        if ($phoneKey === '') {
            return;
        }

        try {
            $conv = $db->query(
                "SELECT id, conv_case FROM wa_conversations
                 WHERE REPLACE(REPLACE(wa_number, '+', ''), ' ', '') = ?
                 LIMIT 1",
                [$phoneKey]
            )->row_array();
            if (!is_array($conv) || empty($conv['id'])) {
                return;
            }

            $caseList = json_decode((string) ($conv['conv_case'] ?? '[]'), true);
            if (!is_array($caseList)) {
                $caseList = [];
            }

            if (!class_exists('\\App\\Helpers\\CRM\\CrmCaseHelper')) {
                require_once __DIR__ . '/../CRM/CrmCaseHelper.php';
            }
            $merged = \App\Helpers\CRM\CrmCaseHelper::mergeOpenCase($caseList, 3);
            if (empty($merged['changed'])) {
                return;
            }

            $db->query(
                'UPDATE wa_conversations SET conv_case = ? WHERE id = ?',
                [\App\Helpers\CRM\CrmCaseHelper::encodeList($merged['list']), (int) $conv['id']]
            );
        } catch (\Throwable $e) {
            // conversation opsional — session permintaan tetap dibuat
        }
    }

    /**
     * @return array{closed:bool,wa_number:?string,all_closed:bool}
     */
    private static function closePermintaanCase(int $idPelanggan, string $waNumber, $userId = null): array
    {
        $phoneKey = self::resolvePhoneKeyForLookup($idPelanggan, $waNumber);
        if ($phoneKey === '') {
            return ['closed' => false, 'wa_number' => null, 'all_closed' => false];
        }

        try {
            $db = DB::getInstance(0);
            $conv = $db->query(
                "SELECT id, wa_number, conv_case FROM wa_conversations
                 WHERE REPLACE(REPLACE(wa_number, '+', ''), ' ', '') = ?
                 LIMIT 1",
                [$phoneKey]
            )->row_array();
            if (!is_array($conv) || empty($conv['id'])) {
                return ['closed' => false, 'wa_number' => null, 'all_closed' => false];
            }

            $caseList = json_decode((string) ($conv['conv_case'] ?? '[]'), true);
            if (!is_array($caseList)) {
                $caseList = [];
            }

            $changed = false;
            foreach ($caseList as &$item) {
                if ((int) ($item['case'] ?? 0) === 3 && ($item['status'] ?? 'open') !== 'closed') {
                    $item['status'] = 'closed';
                    $changed = true;
                }
            }
            unset($item);

            if (!$changed) {
                return [
                    'closed' => false,
                    'wa_number' => (string) ($conv['wa_number'] ?? ''),
                    'all_closed' => false,
                ];
            }

            $db->query(
                'UPDATE wa_conversations SET conv_case = ? WHERE id = ?',
                [json_encode(array_values($caseList), JSON_UNESCAPED_UNICODE), (int) $conv['id']]
            );

            $hasOpenCases = false;
            foreach ($caseList as $c) {
                if (($c['status'] ?? 'open') === 'open') {
                    $hasOpenCases = true;
                    break;
                }
            }

            $waNumberOut = (string) ($conv['wa_number'] ?? '');
            if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
                require_once __DIR__ . '/../CRM/CrmChatMergeHelper.php';
            }
            \App\Helpers\CRM\CrmChatMergeHelper::pushWebSocket([
                'type' => 'case_resolved',
                'phone' => $waNumberOut,
                'case' => 3,
                'target_id' => '0',
                'sender_id' => $userId !== null && $userId !== '' ? (string) $userId : 'crm',
                'all_closed' => !$hasOpenCases,
            ]);

            return [
                'closed' => true,
                'wa_number' => $waNumberOut,
                'all_closed' => !$hasOpenCases,
            ];
        } catch (\Throwable $e) {
            return ['closed' => false, 'wa_number' => null, 'all_closed' => false];
        }
    }

    private static function markOpenSessionsFulfilled(int $idPelanggan, string $waNumber): bool
    {
        $phoneKey = self::resolvePhoneKeyForLookup($idPelanggan, $waNumber);
        if ($idPelanggan <= 0 && $phoneKey === '') {
            return false;
        }

        try {
            $db = DB::getInstance(0);
            if ($idPelanggan > 0 && $phoneKey !== '') {
                $db->query(
                    "UPDATE wa_permintaan_session
                     SET status = 'fulfilled', updated_at = NOW()
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND (
                         id_pelanggan = ?
                         OR REPLACE(REPLACE(phone, '+', ''), ' ', '') = ?
                       )",
                    [$idPelanggan, $phoneKey]
                );
            } elseif ($idPelanggan > 0) {
                $db->query(
                    "UPDATE wa_permintaan_session
                     SET status = 'fulfilled', updated_at = NOW()
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND id_pelanggan = ?",
                    [$idPelanggan]
                );
            } else {
                $db->query(
                    "UPDATE wa_permintaan_session
                     SET status = 'fulfilled', updated_at = NOW()
                     WHERE status = 'open'
                       AND notify_expires_at > NOW()
                       AND REPLACE(REPLACE(phone, '+', ''), ' ', '') = ?",
                    [$phoneKey]
                );
            }

            return $db->affected_rows() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function resolvePhoneKeyForLookup(int $idPelanggan, string $waNumber): string
    {
        $phoneKey = self::normalizePhoneKey($waNumber);

        if ($idPelanggan > 0) {
            $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
            if ($pel !== null) {
                $fromPel = self::normalizePhoneKey(PelangganLokasiStore::primaryPhone($pel));
                if ($fromPel !== '') {
                    $phoneKey = $phoneKey !== '' ? $phoneKey : $fromPel;
                }
            }
        }

        return $phoneKey;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function formatItem(array $row, ?string $summaryOverride = null): array
    {
        $summary = $summaryOverride ?? self::formatSummary($row);

        return [
            'phone' => (string) ($row['phone'] ?? ''),
            'id_pelanggan' => (int) ($row['id_pelanggan'] ?? 0) ?: null,
            'summary' => $summary,
            'status' => 'open',
            'status_label' => 'Open',
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'expires_at' => (string) ($row['notify_expires_at'] ?? $row['expires_at'] ?? ''),
        ];
    }

    private static function normalizePhoneKey(string $phone): string
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

    /**
     * @param array<string,mixed> $row
     */
    private static function formatSummary(array $row): string
    {
        $summary = PermintaanSummaryHelper::finalize((string) ($row['summary'] ?? ''), 500);
        if ($summary !== '' && mb_strlen($summary) > 8) {
            return $summary;
        }

        $raw = trim((string) ($row['raw_log'] ?? ''));
        if ($raw !== '') {
            $fromRaw = PermintaanSummaryHelper::fallbackFromRawLog($raw);
            if ($fromRaw !== '') {
                return $fromRaw;
            }
        }

        return 'Permintaan atau pertanyaan pelanggan.';
    }
}
