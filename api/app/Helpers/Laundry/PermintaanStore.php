<?php

namespace App\Helpers\Laundry;

use App\Core\DB;

/**
 * Permintaan pelanggan (wa_permintaan_session) — dipakai CRM Customer Panel.
 */
class PermintaanStore
{
    /**
     * @return array{ok:bool,message?:string,items?:list<array<string,mixed>>}
     */
    public static function listOpen(int $idPelanggan, string $waNumber = ''): array
    {
        $phoneKey = self::normalizePhoneKey($waNumber);

        if ($idPelanggan > 0) {
            $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
            if ($pel !== null) {
                $fromPel = self::normalizePhoneKey((string) ($pel['nomor_pelanggan'] ?? ''));
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

        $row['summary'] = $summary;
        $row['updated_at'] = date('Y-m-d H:i:s');

        return [
            'ok' => true,
            'message' => 'Permintaan diperbarui',
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
                $fromPel = self::normalizePhoneKey((string) ($pel['nomor_pelanggan'] ?? ''));
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
        $summary = trim((string) ($row['summary'] ?? ''));
        $summary = preg_replace('/^[io]\-\s+/iu', '', $summary) ?? $summary;
        $summary = trim($summary);
        if ($summary !== '' && mb_strlen($summary) > 8) {
            return $summary;
        }

        $raw = trim((string) ($row['raw_log'] ?? ''));
        if ($raw !== '') {
            $lines = preg_split('/\r?\n/', $raw) ?: [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                $line = preg_replace('/^[io]\-\s+/iu', '', $line) ?? $line;
                $line = trim($line);
                if ($line !== '') {
                    return mb_substr($line, 0, 240);
                }
            }
        }

        return 'Permintaan pelanggan';
    }
}
