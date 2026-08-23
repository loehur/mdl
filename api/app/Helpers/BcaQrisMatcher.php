<?php

namespace App\Helpers;

/**
 * Cari transaksi QRIS merchant BCA yang cocok dengan kas pending, bind, fetch on-demand.
 */
class BcaQrisMatcher
{
    /**
     * Transaksi QRIS unlinked — nominal ±CRON_NOMINAL_TOLERANCE, ambil selisih terkecil.
     *
     * @return array|null row bca_qris_transaksi
     */
    public static function findUnlinkedMatch($mainDb, string $nominal, string $startYmd, string $endYmd): ?array
    {
        $bounds = BcaScrapper::cronNominalBounds($nominal);

        $row = $mainDb->query(
            'SELECT t.id, t.tanggal, t.waktu, t.rrn, t.nominal, t.status, t.keterangan
             FROM bca_qris_transaksi t
             LEFT JOIN bca_qris_link l ON l.bca_qris_id = t.id
             WHERE l.id IS NULL
               AND t.nominal >= ?
               AND t.nominal <= ?
               AND t.tanggal >= ?
               AND t.tanggal <= ?
             ORDER BY ABS(t.nominal - ?) ASC, t.tanggal DESC, t.waktu DESC, t.id ASC
             LIMIT 1',
            [$bounds['min'], $bounds['max'], $startYmd, $endYmd, $nominal]
        )->row_array();

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    /**
     * @return array{ok:bool,fetched?:int,inserted?:int,skipped_scrape?:bool,error?:string,start?:string,end?:string}
     */
    public static function fetchAndStoreRange($mainDb, string $startYmd, string $endYmd): array
    {
        return BcaScrapper::fetchAndStoreQrisRange($mainDb, $startYmd, $endYmd);
    }

    public static function bindQris(
        $mainDb,
        int $qrisId,
        string $entityType,
        string $entityRef,
        $billNominal = null,
        $bindNominal = null
    ): bool {
        if ($qrisId < 1 || $entityType === '' || $entityRef === '') {
            return false;
        }

        if (!$mainDb->beginTransaction()) {
            return false;
        }

        try {
            $free = $mainDb->query(
                'SELECT t.id, t.nominal
                 FROM bca_qris_transaksi t
                 LEFT JOIN bca_qris_link l ON l.bca_qris_id = t.id
                 WHERE t.id = ? AND l.id IS NULL
                 FOR UPDATE',
                [$qrisId]
            )->row_array();

            if (empty($free['id'])) {
                $mainDb->rollback();
                return false;
            }

            $entityUsed = $mainDb->query(
                'SELECT id FROM bca_qris_link WHERE entity_type = ? AND entity_ref = ? LIMIT 1 FOR UPDATE',
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

            $insertId = $mainDb->insert('bca_qris_link', [
                'bca_qris_id' => $qrisId,
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
            error_log('[BcaQrisMatcher] bind fail: ' . $e->getMessage());
            return false;
        }
    }

    public static function unbindEntity($mainDb, string $entityType, string $entityRef): bool
    {
        return (bool) $mainDb->delete('bca_qris_link', [
            'entity_type' => $entityType,
            'entity_ref' => $entityRef,
        ]);
    }

    /**
     * Rentang cek DB untuk matching kas — 6 hari inklusif dari hari ini.
     *
     * @return array{start:string,end:string}
     */
    public static function computeKasQrisRange(string $insertTime = ''): array
    {
        return BcaScrapper::listRange();
    }

    /**
     * @param array<string,mixed> $kasRow
     * @return array{ok:bool,matched?:bool,scraped?:bool,qris_id?:int,message?:string,ref_finance?:string,nominal?:string,rrn?:string,range_start?:string,range_end?:string,scrape_start?:string,scrape_end?:string}
     */
    public static function matchAndBindForKas($mainDb, array $kasRow): array
    {
        $refFinance = trim((string) ($kasRow['ref_finance'] ?? ''));
        $insertTime = trim((string) ($kasRow['insertTime'] ?? ''));
        $nominalRaw = $kasRow['total'] ?? $kasRow['jumlah'] ?? 0;
        $nominal = BcaScrapper::formatNominal($nominalRaw);

        if ($refFinance === '' || $insertTime === '' || (float) $nominal <= 0) {
            return ['ok' => false, 'message' => 'invalid_kas_row'];
        }

        $range = self::computeKasQrisRange($insertTime);
        $start = (string) $range['start'];
        $end = (string) $range['end'];

        $qris = self::findUnlinkedMatch($mainDb, $nominal, $start, $end);
        $scraped = false;
        $scrapeWindow = BcaScrapper::qrisScrapeWindow();

        if ($qris === null) {
            $fetch = self::fetchAndStoreRange(
                $mainDb,
                $scrapeWindow['start'],
                $scrapeWindow['end']
            );
            if (empty($fetch['ok'])) {
                return [
                    'ok' => false,
                    'scraped' => false,
                    'message' => (string) ($fetch['error'] ?? 'fetch_failed'),
                    'range_start' => $start,
                    'range_end' => $end,
                    'scrape_start' => $scrapeWindow['start'],
                    'scrape_end' => $scrapeWindow['end'],
                ];
            }
            $scraped = empty($fetch['skipped_scrape']);
            $qris = self::findUnlinkedMatch($mainDb, $nominal, $start, $end);
        }

        if ($qris === null) {
            return [
                'ok' => true,
                'matched' => false,
                'scraped' => $scraped,
                'range_start' => $start,
                'range_end' => $end,
                'scrape_start' => $scrapeWindow['start'],
                'scrape_end' => $scrapeWindow['end'],
            ];
        }

        $qrisId = (int) $qris['id'];
        $bound = self::bindQris(
            $mainDb,
            $qrisId,
            BcaScrapper::ENTITY_KAS_LAUNDRY,
            $refFinance,
            $nominal,
            $qris['nominal'] ?? null
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
            'qris_id' => $qrisId,
            'ref_finance' => $refFinance,
            'nominal' => $nominal,
            'rrn' => (string) ($qris['rrn'] ?? ''),
            'range_start' => $start,
            'range_end' => $end,
        ];
    }
}
