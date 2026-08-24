<?php

namespace App\Helpers;

use App\Helpers\Payment\BcaMutasiUnbind;

/**
 * Cari mutasi BCA yang cocok dengan kas pending, bind ke entitas, fetch on-demand.
 */
class BcaMutasiMatcher
{
    /**
     * Cari mutasi CR unlinked — exact bill, atau ±CRON_NOMINAL_TOLERANCE.
     * Posted: tanggal_iso dalam 6 hari terakhir dari hari ini.
     * PEND: created_at dalam lookback 30 hari (tidak terikat rentang posted).
     *
     * @return array|null row bca_mutasi
     */
    public static function findUnlinkedMatch($mainDb, string $nominal, string $startYmd, string $endYmd, bool $exact = false): ?array
    {
        $today = date('Y-m-d');
        $pendStart = BcaScrapper::lookbackMinStart();

        if ($exact) {
            $row = $mainDb->query(
                'SELECT m.id, m.tanggal, m.tanggal_iso, m.keterangan, m.nominal, m.mutasi
                 FROM bca_mutasi m
                 LEFT JOIN bca_mutasi_link l ON l.bca_mutasi_id = m.id
                 WHERE l.id IS NULL
                   AND m.mutasi = ?
                   AND m.nominal = ?
                   AND (
                     (m.tanggal_iso IS NOT NULL AND m.tanggal_iso >= ? AND m.tanggal_iso <= ?)
                     OR (
                       UPPER(m.tanggal) = ?
                       AND DATE(m.created_at) >= ?
                       AND DATE(m.created_at) <= ?
                     )
                   )
                 ORDER BY
                   CASE WHEN UPPER(m.tanggal) = ? THEN 0 ELSE 1 END,
                   m.tanggal_iso ASC,
                   m.id ASC
                 LIMIT 1',
                [
                    'CR',
                    $nominal,
                    $startYmd,
                    $endYmd,
                    'PEND',
                    $pendStart,
                    $today,
                    'PEND',
                ]
            )->row_array();

            return is_array($row) && !empty($row['id']) ? $row : null;
        }

        $bounds = BcaScrapper::cronNominalBounds($nominal);

        $row = $mainDb->query(
            'SELECT m.id, m.tanggal, m.tanggal_iso, m.keterangan, m.nominal, m.mutasi
             FROM bca_mutasi m
             LEFT JOIN bca_mutasi_link l ON l.bca_mutasi_id = m.id
             WHERE l.id IS NULL
               AND m.mutasi = ?
               AND (
                 m.nominal = ?
                 OR (m.nominal >= ? AND m.nominal <= ?)
               )
               AND (
                 (m.tanggal_iso IS NOT NULL AND m.tanggal_iso >= ? AND m.tanggal_iso <= ?)
                 OR (
                   UPPER(m.tanggal) = ?
                   AND DATE(m.created_at) >= ?
                   AND DATE(m.created_at) <= ?
                 )
               )
             ORDER BY
               ABS(m.nominal - ?) ASC,
               CASE WHEN UPPER(m.tanggal) = ? THEN 0 ELSE 1 END,
               m.tanggal_iso ASC,
               m.id ASC
             LIMIT 1',
            [
                'CR',
                $nominal,
                $bounds['min'],
                $bounds['max'],
                $startYmd,
                $endYmd,
                'PEND',
                $pendStart,
                $today,
                $nominal,
                'PEND',
            ]
        )->row_array();

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    /**
     * Fetch mutasi dari bca_scrapper untuk rentang tertentu dan simpan ke DB.
     * Pangkas tanggal yang sudah ada di DB; hari ini selalu di-scrape jika dalam rentang.
     *
     * @return array{ok:bool,fetched?:int,inserted?:int,updated?:int,skipped_dup?:int,skipped_scrape?:bool,error?:string,start?:string,end?:string}
     */
    public static function fetchAndStoreRange($mainDb, string $startYmd, string $endYmd): array
    {
        $trimmed = BcaScrapper::trimFetchRangeByDb($mainDb, $startYmd, $endYmd);
        if (!empty($trimmed['skip'])) {
            return [
                'ok' => true,
                'fetched' => 0,
                'inserted' => 0,
                'updated' => 0,
                'skipped_dup' => 0,
                'skipped_scrape' => true,
                'start' => (string) ($trimmed['start'] ?? $startYmd),
                'end' => (string) ($trimmed['end'] ?? $endYmd),
            ];
        }

        $fetchStart = (string) $trimmed['start'];
        $fetchEnd = (string) $trimmed['end'];

        $remote = BcaScrapper::mutasi($fetchStart, $fetchEnd);
        if (empty($remote['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($remote['message'] ?? $remote['error'] ?? 'scrape_failed'),
            ];
        }

        $rows = is_array($remote['mutasi'] ?? null) ? $remote['mutasi'] : [];
        $save = BcaScrapper::saveMutasiRows($mainDb, $rows);

        return [
            'ok' => true,
            'fetched' => count($rows),
            'inserted' => (int) ($save['inserted'] ?? 0),
            'updated' => (int) ($save['updated'] ?? 0),
            'skipped_dup' => (int) ($save['skipped_dup'] ?? 0),
            'start' => $fetchStart,
            'end' => $fetchEnd,
            'trimmed' => !empty($trimmed['trimmed']),
        ];
    }

    /**
     * Bind mutasi ke entitas (atomik). Satu mutasi = satu entitas.
     *
     * @param mixed $billNominal nominal tagihan/kas saat bind
     * @param mixed $bindNominal nominal mutasi saat bind; fallback dari row DB
     */
    public static function bindMutasi(
        $mainDb,
        int $mutasiId,
        string $entityType,
        string $entityRef,
        $billNominal = null,
        $bindNominal = null
    ): bool {
        if ($mutasiId < 1 || $entityType === '' || $entityRef === '') {
            return false;
        }

        if (BcaMutasiUnbind::isBindBlocked($mainDb, $entityType, $entityRef)) {
            return false;
        }

        if (!$mainDb->beginTransaction()) {
            return false;
        }

        try {
            $free = $mainDb->query(
                'SELECT m.id, m.nominal
                 FROM bca_mutasi m
                 LEFT JOIN bca_mutasi_link l ON l.bca_mutasi_id = m.id
                 WHERE m.id = ? AND l.id IS NULL
                 FOR UPDATE',
                [$mutasiId]
            )->row_array();

            if (empty($free['id'])) {
                $mainDb->rollback();
                return false;
            }

            $entityUsed = $mainDb->query(
                'SELECT id FROM bca_mutasi_link WHERE entity_type = ? AND entity_ref = ? LIMIT 1 FOR UPDATE',
                [$entityType, $entityRef]
            )->row_array();

            if (!empty($entityUsed['id'])) {
                $mainDb->rollback();
                return false;
            }

            $bindNominalStored = BcaScrapper::formatNominal(
                $bindNominal !== null && $bindNominal !== '' ? $bindNominal : ($free['nominal'] ?? 0)
            );
            $billNominalStored = null;
            if ($billNominal !== null && $billNominal !== '') {
                $billNominalStored = BcaScrapper::formatNominal($billNominal);
            }

            $insertId = $mainDb->insert('bca_mutasi_link', [
                'bca_mutasi_id' => $mutasiId,
                'entity_type' => $entityType,
                'entity_ref' => $entityRef,
                'bill_nominal' => $billNominalStored,
                'bind_nominal' => $bindNominalStored,
            ]);

            if (!$insertId) {
                $mainDb->rollback();
                return false;
            }

            $mainDb->commit();
            return true;
        } catch (\Throwable $e) {
            $mainDb->rollback();
            error_log('[BcaMutasiMatcher] bind fail: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lepas binding jika update kas gagal setelah bind.
     */
    public static function unbindEntity($mainDb, string $entityType, string $entityRef): bool
    {
        return (bool) $mainDb->delete('bca_mutasi_link', [
            'entity_type' => $entityType,
            'entity_ref' => $entityRef,
        ]);
    }

    /**
     * Cari mutasi CR unlinked — exact bill, atau ±CRON_NOMINAL_TOLERANCE.
     */
    public static function findUnlinkedMatchExact($mainDb, string $nominal, string $startYmd, string $endYmd): ?array
    {
        return self::findUnlinkedMatch($mainDb, $nominal, $startYmd, $endYmd, true);
    }

    /**
     * Proses satu entitas BCA pending: cari di DB → scrape → bind.
     *
     * @param array<string,mixed> $row grouped row (ref/total/insertTime)
     * @return array{ok:bool,matched?:bool,scraped?:bool,mutasi_id?:int,message?:string,nominal?:string,range_start?:string,range_end?:string}
     */
    public static function matchAndBindForEntity(
        $mainDb,
        array $row,
        string $entityType,
        string $entityRef,
        bool $exact = false
    ): array {
        $entityRef = trim($entityRef);
        $insertTime = trim((string) ($row['insertTime'] ?? $row['created_at'] ?? ''));
        $nominalRaw = $row['total'] ?? $row['jumlah'] ?? $row['amount'] ?? 0;
        $nominal = BcaScrapper::formatNominal($nominalRaw);

        if ($entityRef === '' || (float) $nominal <= 0) {
            return ['ok' => false, 'message' => 'invalid_entity_row'];
        }

        $range = BcaScrapper::listRange();
        $start = (string) $range['start'];
        $end = (string) $range['end'];

        $mutasi = self::findUnlinkedMatch($mainDb, $nominal, $start, $end, $exact);
        $scraped = false;

        if ($mutasi === null) {
            $fetch = self::fetchAndStoreRange($mainDb, $start, $end);
            if (empty($fetch['ok'])) {
                return [
                    'ok' => false,
                    'scraped' => false,
                    'message' => (string) ($fetch['error'] ?? 'fetch_failed'),
                    'range_start' => $start,
                    'range_end' => $end,
                ];
            }
            $scraped = empty($fetch['skipped_scrape']);
            $mutasi = self::findUnlinkedMatch($mainDb, $nominal, $start, $end, $exact);
        }

        if ($mutasi === null) {
            return [
                'ok' => true,
                'matched' => false,
                'scraped' => $scraped,
                'range_start' => $start,
                'range_end' => $end,
            ];
        }

        $mutasiId = (int) $mutasi['id'];
        $bound = self::bindMutasi(
            $mainDb,
            $mutasiId,
            $entityType,
            $entityRef,
            $nominal,
            $mutasi['nominal'] ?? null
        );

        if (!$bound) {
            return [
                'ok' => false,
                'matched' => true,
                'scraped' => $scraped,
                'message' => 'bind_failed',
            ];
        }

        return [
            'ok' => true,
            'matched' => true,
            'scraped' => $scraped,
            'mutasi_id' => $mutasiId,
            'entity_ref' => $entityRef,
            'nominal' => $nominal,
            'range_start' => $start,
            'range_end' => $end,
        ];
    }

    /**
     * Proses satu kas BCA pending: cari di DB → scrape 6 hari terakhir jika perlu → bind.
     * PEND mutasi: lookback 30 hari (created_at), tidak terikat rentang 6 hari posted.
     *
     * @param array<string,mixed> $kasRow grouped row (ref_finance, total/jumlah, insertTime)
     * @return array{ok:bool,matched?:bool,confirmed?:bool,scraped?:bool,mutasi_id?:int,message?:string}
     */
    public static function matchAndBindForKas($mainDb, array $kasRow): array
    {
        $refFinance = trim((string) ($kasRow['ref_finance'] ?? ''));
        if ($refFinance === '') {
            return ['ok' => false, 'message' => 'invalid_kas_row'];
        }

        return self::matchAndBindForEntity(
            $mainDb,
            $kasRow,
            BcaScrapper::ENTITY_KAS_LAUNDRY,
            $refFinance,
            false
        );
    }
}
