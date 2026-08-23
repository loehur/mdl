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
            $items[] = [
                'phone' => (string) ($row['phone'] ?? ''),
                'id_pelanggan' => (int) ($row['id_pelanggan'] ?? 0) ?: null,
                'summary' => self::formatSummary($row),
                'status' => 'open',
                'status_label' => 'Open',
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'expires_at' => (string) ($row['notify_expires_at'] ?? $row['expires_at'] ?? ''),
            ];
        }

        return ['ok' => true, 'items' => $items];
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
