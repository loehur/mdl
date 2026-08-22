<?php

namespace App\Helpers;

/**
 * Client HTTP ke node/bca_scrapper + sync mutasi ke mdl_main.
 */
class BcaScrapper
{
    public const MAX_RANGE_DAYS = 6;
    public const MAX_LOOKBACK_DAYS = 30;
    public const QRIS_MAX_RANGE_DAYS = 2; // scrape portal: hari ini + kemarin
    public const QRIS_SCRAPE_LOOKBACK_DAYS = 1; // lookback max kemarin (bukan kemarin lusa)
    public const QRIS_DB_MATCH_RANGE_DAYS = 6; // cek DB saat matching kas (sama mutasi BCA)
    public const MAX_SYNC_CHUNKS = 10;

    public const ENTITY_KAS_LAUNDRY = 'kas_laundry';
    public const CRON_NOMINAL_TOLERANCE = 5000;

    private const DEFAULT_MUTASI_URL = 'http://127.0.0.1:3021/mutasi';
    private const DEFAULT_QRIS_URL = 'http://127.0.0.1:3021/qris/transactions';
    private const DEFAULT_TIMEOUT = 90;

    /**
     * Sync mutasi BCA ke database main (db index 0).
     * Pangkas rentang tanggal yang sudah ada, fetch per chunk max 6 hari.
     *
     * @param object|null $db CodeIgniter-style DB (db(0))
     * @return array{ok:bool,fetched?:int,inserted?:int,skipped_dup?:int,chunks?:int,errors?:array,message?:string}
     */
    public static function syncMutasi($db = null): array
    {
        if ($db === null) {
            if (!class_exists('\\App\\Core\\DB', false)) {
                require_once __DIR__ . '/../Core/DB.php';
            }
            $db = \App\Core\DB::getInstance(0);
        }

        if (!$db) {
            return [
                'ok' => false,
                'message' => 'Database mdl_main tidak tersedia',
            ];
        }

        try {
            $db->query('SELECT 1 FROM bca_mutasi LIMIT 1');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Tabel bca_mutasi belum ada. Jalankan migration api/database/main/migrations/001_bca_mutasi.sql',
            ];
        }

        $stats = [
            'fetched' => 0,
            'inserted' => 0,
            'skipped_dup' => 0,
            'chunks' => 0,
            'errors' => [],
        ];

        for ($i = 0; $i < self::MAX_SYNC_CHUNKS; $i++) {
            $range = self::computeFetchRange($db);
            if (!empty($range['skip'])) {
                break;
            }

            $remote = self::mutasi($range['start'], $range['end']);
            if (empty($remote['ok'])) {
                $stats['errors'][] = (string) ($remote['message'] ?? $remote['error'] ?? 'scrape_failed');
                break;
            }

            $rows = is_array($remote['mutasi'] ?? null) ? $remote['mutasi'] : [];
            $stats['fetched'] += count($rows);
            $stats['chunks']++;

            $save = self::saveMutasiRows($db, $rows);
            $stats['inserted'] += (int) ($save['inserted'] ?? 0);
            $stats['skipped_dup'] += (int) ($save['skipped_dup'] ?? 0);

            if (empty($range['has_more'])) {
                break;
            }
        }

        $stats['ok'] = empty($stats['errors']);
        if (!$stats['ok'] && $stats['chunks'] > 0) {
            $stats['ok'] = true;
            $stats['partial'] = true;
        }

        return $stats;
    }

    /**
     * Pangkas rentang scrape: lewati tanggal yang sudah punya data di DB, kecuali hari ini.
     *
     * @return array{skip:bool,start?:string,end?:string,trimmed?:bool,reason?:string}
     */
    public static function trimFetchRangeByDb($db, string $startYmd, string $endYmd): array
    {
        $clamped = self::clampDateRange($startYmd, $endYmd);
        if (empty($clamped['valid'])) {
            return [
                'skip' => true,
                'reason' => (string) ($clamped['reason'] ?? 'invalid_range'),
            ];
        }

        $start = (string) $clamped['start'];
        $end = (string) $clamped['end'];
        $today = date('Y-m-d');

        $fetchStart = null;
        for ($d = $start; $d <= $end; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            if ($d === $today) {
                $fetchStart = $today;
                break;
            }
            if (!self::hasMutasiForDate($db, $d)) {
                $fetchStart = $d;
                break;
            }
        }

        if ($fetchStart === null) {
            return [
                'skip' => true,
                'reason' => 'range_already_synced',
                'start' => $start,
                'end' => $end,
            ];
        }

        $fetchEnd = $fetchStart === $today ? $today : $end;

        return [
            'skip' => false,
            'start' => $fetchStart,
            'end' => $fetchEnd,
            'trimmed' => $fetchStart > $start || $fetchEnd < $end,
        ];
    }

    /**
     * Apakah tanggal sudah punya minimal satu mutasi tersimpan (tanggal_iso).
     */
    public static function hasMutasiForDate($db, string $ymd): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return false;
        }

        $row = $db->query(
            'SELECT 1 FROM bca_mutasi WHERE tanggal_iso = ? LIMIT 1',
            [$ymd]
        )->row_array();

        return is_array($row) && !empty($row);
    }

    /**
     * Tanggal awal lookback (30 hari) untuk PEND / scrape penuh.
     */
    public static function lookbackMinStart(): string
    {
        return date('Y-m-d', strtotime('-' . self::MAX_LOOKBACK_DAYS . ' days'));
    }

    /**
     * Pecah rentang YYYY-MM-DD jadi chunk inklusif max N hari (untuk batas KlikBCA 6 hari).
     *
     * @return array<int,array{start:string,end:string}>
     */
    public static function splitDateRangeIntoChunks(
        string $startYmd,
        string $endYmd,
        int $maxDays = self::MAX_RANGE_DAYS
    ): array {
        $today = date('Y-m-d');
        $minStart = self::lookbackMinStart();

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startYmd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endYmd)) {
            return [];
        }

        $start = $startYmd;
        $end = $endYmd;
        if ($end > $today) {
            $end = $today;
        }
        if ($start < $minStart) {
            $start = $minStart;
        }
        if ($start > $end || $maxDays < 1) {
            return [];
        }

        $chunks = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $chunkEnd = date('Y-m-d', strtotime($cursor . ' +' . ($maxDays - 1) . ' days'));
            if ($chunkEnd > $end) {
                $chunkEnd = $end;
            }
            $chunks[] = [
                'start' => $cursor,
                'end' => $chunkEnd,
            ];
            $cursor = date('Y-m-d', strtotime($chunkEnd . ' +1 day'));
        }

        return $chunks;
    }

    /**
     * Hitung rentang fetch berdasarkan tanggal terakhir di DB.
     *
     * @return array{skip:bool,start?:string,end?:string,has_more?:bool,reason?:string}
     */
    public static function computeFetchRange($db): array
    {
        $today = date('Y-m-d');
        $minStart = self::lookbackMinStart();

        $trimmed = self::trimFetchRangeByDb($db, $minStart, $today);
        if (!empty($trimmed['skip'])) {
            return [
                'skip' => true,
                'reason' => (string) ($trimmed['reason'] ?? 'already_synced'),
            ];
        }

        $start = (string) $trimmed['start'];
        $end = (string) $trimmed['end'];

        $totalDays = self::daysBetween($start, $end) + 1;
        $hasMore = $totalDays > self::MAX_RANGE_DAYS;

        if ($hasMore) {
            $end = date('Y-m-d', strtotime($start . ' +' . (self::MAX_RANGE_DAYS - 1) . ' days'));
        }

        return [
            'skip' => false,
            'start' => $start,
            'end' => $end,
            'has_more' => $hasMore && $end < $today,
        ];
    }

    /**
     * Rentang 6 hari inklusif dari hari ini (sumber tunggal untuk matching & scrape posted).
     * Mutasi PEND tidak memakai rentang ini — tetap lookback 30 hari via lookbackMinStart().
     *
     * @return array{start:string,end:string}
     */
    public static function listRange(): array
    {
        $end = date('Y-m-d');
        $start = date('Y-m-d', strtotime('-' . (self::MAX_RANGE_DAYS - 1) . ' days'));

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{valid:bool,start?:string,end?:string,reason?:string}
     */
    public static function computeKasMutasiRange(string $insertTime = ''): array
    {
        $range = self::listRange();

        return [
            'valid' => true,
            'start' => $range['start'],
            'end' => $range['end'],
        ];
    }

    /**
     * Clamp rentang YYYY-MM-DD ke aturan server (6 hari, 30 lookback, end <= today).
     *
     * @return array{valid:bool,start?:string,end?:string,reason?:string}
     */
    public static function clampDateRange(string $start, string $end): array
    {
        $today = date('Y-m-d');
        $minStart = date('Y-m-d', strtotime($today . ' -' . self::MAX_LOOKBACK_DAYS . ' days'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return ['valid' => false, 'reason' => 'invalid_date_format'];
        }

        if ($end > $today) {
            $end = $today;
        }
        if ($start < $minStart) {
            $start = $minStart;
        }
        if ($start > $end) {
            return ['valid' => false, 'reason' => 'empty_range_after_clamp', 'start' => $start, 'end' => $end];
        }

        $days = self::daysBetween($start, $end) + 1;
        if ($days > self::MAX_RANGE_DAYS) {
            $start = date('Y-m-d', strtotime($end . ' -' . (self::MAX_RANGE_DAYS - 1) . ' days'));
            if ($start < $minStart) {
                $start = $minStart;
            }
        }

        if ($start > $end) {
            return ['valid' => false, 'reason' => 'empty_range_after_clamp'];
        }

        return [
            'valid' => true,
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * @param mixed $nominal
     */
    public static function formatNominal($nominal): string
    {
        return self::normalizeNominal($nominal);
    }

    /**
     * Batas bawah/atas nominal untuk matching cron (±CRON_NOMINAL_TOLERANCE).
     *
     * @return array{min:string,max:string}
     */
    public static function cronNominalBounds(string $nominal): array
    {
        $n = (float) self::formatNominal($nominal);
        $tol = (float) self::CRON_NOMINAL_TOLERANCE;

        return [
            'min' => number_format(max(0, $n - $tol), 2, '.', ''),
            'max' => number_format($n + $tol, 2, '.', ''),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array{inserted:int,updated:int,skipped_dup:int}
     */
    public static function saveMutasiRows($db, array $rows): array
    {
        $inserted = 0;
        $updated = 0;
        $skippedDup = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tanggal = trim((string) ($row['tanggal'] ?? ''));
            $keterangan = self::normalizeKeterangan((string) ($row['keterangan'] ?? ''));
            $mutasi = strtoupper(trim((string) ($row['mutasi'] ?? '')));
            $nominal = self::normalizeNominal($row['nominal'] ?? 0);

            if ($tanggal === '' || $keterangan === '' || !in_array($mutasi, ['CR', 'DB'], true)) {
                continue;
            }

            $tanggalIso = self::parseTanggalIso($tanggal);
            $fingerprint = self::fingerprint($tanggal, $keterangan, $nominal, $mutasi);
            $reconcileKey = self::reconcileKey($keterangan, $nominal, $mutasi);
            $isPosted = strtoupper($tanggal) !== 'PEND' && $tanggalIso !== null;

            if ($isPosted && self::upgradePendingMutasi(
                $db,
                $reconcileKey,
                $tanggal,
                $tanggalIso,
                $fingerprint,
                $keterangan,
                $nominal,
                $mutasi
            )) {
                $updated++;
                continue;
            }

            $db->insertIgnore('bca_mutasi', [
                'tanggal' => $tanggal,
                'tanggal_iso' => $tanggalIso,
                'keterangan' => $keterangan,
                'nominal' => $nominal,
                'mutasi' => $mutasi,
                'fingerprint' => $fingerprint,
                'reconcile_key' => $reconcileKey,
            ]);

            if ($db->conn()->affected_rows > 0) {
                $inserted++;
                continue;
            }

            $skippedDup++;
            if ($isPosted) {
                self::purgeOrphanPendingMutasi($db, $reconcileKey);
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped_dup' => $skippedDup,
        ];
    }

    /**
     * Upgrade baris PEND → posted (tanggal pasti) jika reconcile_key cocok.
     */
    private static function upgradePendingMutasi(
        $db,
        string $reconcileKey,
        string $tanggal,
        string $tanggalIso,
        string $fingerprint,
        string $keterangan,
        string $nominal,
        string $mutasi
    ): bool {
        $pending = $db->query(
            'SELECT id FROM bca_mutasi
             WHERE reconcile_key = ?
               AND UPPER(tanggal) = ?
             ORDER BY id ASC
             LIMIT 1',
            [$reconcileKey, 'PEND']
        )->row_array();

        if (empty($pending['id'])) {
            $pending = $db->query(
                'SELECT id FROM bca_mutasi
                 WHERE UPPER(tanggal) = ?
                   AND keterangan = ?
                   AND nominal = ?
                   AND mutasi = ?
                 ORDER BY id ASC
                 LIMIT 1',
                ['PEND', $keterangan, $nominal, $mutasi]
            )->row_array();
        }

        if (empty($pending['id'])) {
            return false;
        }

        return (bool) $db->update('bca_mutasi', [
            'tanggal' => $tanggal,
            'tanggal_iso' => $tanggalIso,
            'keterangan' => $keterangan,
            'fingerprint' => $fingerprint,
            'reconcile_key' => $reconcileKey,
        ], ['id' => (int) $pending['id']]);
    }

    /**
     * Hapus PEND orphan jika baris posted dengan reconcile_key sama sudah ada di DB.
     */
    private static function purgeOrphanPendingMutasi($db, string $reconcileKey): void
    {
        $db->query(
            'DELETE p FROM bca_mutasi p
             INNER JOIN bca_mutasi d
               ON d.reconcile_key = p.reconcile_key
              AND UPPER(d.tanggal) <> ?
             LEFT JOIN bca_mutasi_link l ON l.bca_mutasi_id = p.id
             WHERE p.reconcile_key = ?
               AND UPPER(p.tanggal) = ?
               AND l.id IS NULL',
            ['PEND', $reconcileKey, 'PEND']
        );
    }

    public static function normalizeKeterangan(string $keterangan): string
    {
        return trim($keterangan);
    }

    /**
     * Business key stabil: keterangan + nominal + mutasi (tanpa tanggal).
     */
    public static function reconcileKey(string $keterangan, $nominal, string $mutasi): string
    {
        $nominalStr = self::normalizeNominal($nominal);
        $payload = self::normalizeKeterangan($keterangan) . '|' . $nominalStr . '|' . strtoupper(trim($mutasi));

        return hash('sha256', $payload);
    }

    /**
     * Ambil mutasi rekening BCA dari node service.
     *
     * @param array{username?:string,password?:string} $credentials
     * @return array{ok:bool,method?:string,mutasi?:array,count?:int,error?:string,message?:string}
     */
    public static function mutasi(?string $startDate = null, ?string $endDate = null, array $credentials = []): array
    {
        $payload = self::buildPayload($credentials);
        if ($startDate !== null && $startDate !== '') {
            $payload['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $payload['end_date'] = $endDate;
        }

        $remote = self::callService(
            self::configString('BCA_SCRAPPER_MUTASI_URL', self::DEFAULT_MUTASI_URL),
            $payload
        );

        if ($remote === null) {
            return [
                'ok' => false,
                'error' => 'bca_scrapper_unreachable',
                'message' => 'Gagal menghubungi bca_scrapper. Pastikan service berjalan.',
            ];
        }

        if (!empty($remote['ok'])) {
            return [
                'ok' => true,
                'method' => (string) ($remote['method'] ?? 'unknown'),
                'start_date' => (string) ($remote['start_date'] ?? ''),
                'end_date' => (string) ($remote['end_date'] ?? ''),
                'mutasi' => is_array($remote['mutasi'] ?? null) ? $remote['mutasi'] : [],
                'count' => (int) ($remote['count'] ?? 0),
                'http_error' => $remote['http_error'] ?? null,
            ];
        }

        return self::failFromRemote($remote);
    }

    /**
     * Sync transaksi QRIS merchant ke DB — pangkas hari yang sudah ada, scrape sisanya.
     *
     * @param object|null $db
     * @return array{ok:bool,fetched?:int,inserted?:int,skipped_dup?:int,skipped_scrape?:bool,errors?:array,message?:string}
     */
    public static function syncQrisTransactions($db = null, ?string $startDate = null, ?string $endDate = null): array
    {
        if ($db === null) {
            if (!class_exists('\\App\\Core\\DB', false)) {
                require_once __DIR__ . '/../Core/DB.php';
            }
            $db = \App\Core\DB::getInstance(0);
        }

        if (!$db) {
            return ['ok' => false, 'message' => 'Database mdl_main tidak tersedia'];
        }

        try {
            $db->query('SELECT 1 FROM bca_qris_transaksi LIMIT 1');
            $db->query('SELECT 1 FROM bca_qris_hari LIMIT 1');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Tabel bca_qris belum ada. Jalankan migration 004_bca_qris_transaksi.sql',
            ];
        }

        $today = date('Y-m-d');
        if ($startDate === null && $endDate === null) {
            $window = self::qrisScrapeWindow();
            $startDate = $window['start'];
            $endDate = $window['end'];
        }

        $clamped = self::clampQrisDateRange(
            $startDate !== null && $startDate !== '' ? $startDate : $today,
            $endDate !== null && $endDate !== '' ? $endDate : ($startDate ?: $today)
        );
        if (empty($clamped['valid'])) {
            return [
                'ok' => false,
                'message' => (string) ($clamped['reason'] ?? 'invalid_date_range'),
            ];
        }

        return self::fetchAndStoreQrisRange($db, (string) $clamped['start'], (string) $clamped['end']);
    }

    /**
     * Pangkas rentang QRIS: lewati hari yang sudah di-sync, kecuali hari ini.
     *
     * @return array{skip:bool,start?:string,end?:string,trimmed?:bool,reason?:string}
     */
    public static function trimQrisFetchRangeByDb($db, string $startYmd, string $endYmd): array
    {
        $clamped = self::clampQrisDateRange($startYmd, $endYmd);
        if (empty($clamped['valid'])) {
            return [
                'skip' => true,
                'reason' => (string) ($clamped['reason'] ?? 'invalid_range'),
            ];
        }

        $start = (string) $clamped['start'];
        $end = (string) $clamped['end'];
        $today = date('Y-m-d');

        $fetchStart = null;
        for ($d = $start; $d <= $end; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            if ($d === $today) {
                $fetchStart = $today;
                break;
            }
            if (!self::hasQrisForDate($db, $d)) {
                $fetchStart = $d;
                break;
            }
        }

        if ($fetchStart === null) {
            return [
                'skip' => true,
                'reason' => 'range_already_synced',
                'start' => $start,
                'end' => $end,
            ];
        }

        $fetchEnd = $fetchStart === $today ? $today : $end;
        $rangeDays = self::daysBetween($fetchStart, $fetchEnd) + 1;
        if ($rangeDays > self::QRIS_MAX_RANGE_DAYS) {
            $fetchEnd = date('Y-m-d', strtotime($fetchStart . ' +' . (self::QRIS_MAX_RANGE_DAYS - 1) . ' days'));
            if ($fetchEnd > $end) {
                $fetchEnd = $end;
            }
        }

        return [
            'skip' => false,
            'start' => $fetchStart,
            'end' => $fetchEnd,
            'trimmed' => $fetchStart > $start || $fetchEnd < $end,
        ];
    }

    public static function hasQrisForDate($db, string $ymd): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return false;
        }

        $row = $db->query(
            'SELECT 1 FROM bca_qris_hari WHERE tanggal = ? LIMIT 1',
            [$ymd]
        )->row_array();

        return is_array($row) && !empty($row);
    }

    /**
     * @return array{valid:bool,start?:string,end?:string,reason?:string}
     */
    public static function clampQrisDateRange(string $start, string $end): array
    {
        $today = date('Y-m-d');
        $minStart = date('Y-m-d', strtotime($today . ' -' . self::QRIS_SCRAPE_LOOKBACK_DAYS . ' days'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return ['valid' => false, 'reason' => 'invalid_date_format'];
        }

        if ($end > $today) {
            $end = $today;
        }
        if ($start < $minStart) {
            $start = $minStart;
        }
        if ($start > $end) {
            return ['valid' => false, 'reason' => 'empty_range_after_clamp'];
        }

        $days = self::daysBetween($start, $end) + 1;
        if ($days > self::QRIS_MAX_RANGE_DAYS) {
            return ['valid' => false, 'reason' => 'date_range_too_large'];
        }

        return [
            'valid' => true,
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Rentang scrape portal QRMS — kemarin s/d hari ini (lookback max 1 hari).
     *
     * @return array{start:string,end:string}
     */
    public static function qrisScrapeWindow(): array
    {
        $today = date('Y-m-d');

        return [
            'start' => date('Y-m-d', strtotime($today . ' -1 day')),
            'end' => $today,
        ];
    }

    /**
     * Fetch dari bca_scrapper + simpan ke DB (dengan pangkas).
     *
     * @return array{ok:bool,fetched?:int,inserted?:int,skipped_dup?:int,skipped_scrape?:bool,error?:string,start?:string,end?:string,trimmed?:bool}
     */
    public static function fetchAndStoreQrisRange($db, string $startYmd, string $endYmd): array
    {
        $trimmed = self::trimQrisFetchRangeByDb($db, $startYmd, $endYmd);
        if (!empty($trimmed['skip'])) {
            return [
                'ok' => true,
                'fetched' => 0,
                'inserted' => 0,
                'skipped_dup' => 0,
                'skipped_scrape' => true,
                'start' => (string) ($trimmed['start'] ?? $startYmd),
                'end' => (string) ($trimmed['end'] ?? $endYmd),
                'reason' => (string) ($trimmed['reason'] ?? 'already_synced'),
            ];
        }

        $fetchStart = (string) $trimmed['start'];
        $fetchEnd = (string) $trimmed['end'];

        $scrapeWindow = self::qrisScrapeWindow();
        if ($fetchStart < $scrapeWindow['start']) {
            $fetchStart = $scrapeWindow['start'];
        }
        if ($fetchEnd > $scrapeWindow['end']) {
            $fetchEnd = $scrapeWindow['end'];
        }
        if ($fetchStart > $fetchEnd) {
            return [
                'ok' => true,
                'fetched' => 0,
                'inserted' => 0,
                'skipped_dup' => 0,
                'skipped_scrape' => true,
                'start' => $fetchStart,
                'end' => $fetchEnd,
                'reason' => 'outside_scrape_window',
            ];
        }

        $remote = self::qrisTransactions($fetchStart, $fetchEnd);
        if (empty($remote['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($remote['message'] ?? $remote['error'] ?? 'scrape_failed'),
            ];
        }

        $rows = is_array($remote['transactions'] ?? null) ? $remote['transactions'] : [];
        $outlets = is_array($remote['outlets'] ?? null) ? $remote['outlets'] : [];
        $mid = trim((string) ($outlets[0]['mid'] ?? ''));
        $outletName = trim((string) ($outlets[0]['name'] ?? ''));

        $save = self::saveQrisRows($db, $rows, $mid, $outletName);
        self::markQrisDaysSynced($db, $fetchStart, $fetchEnd);

        return [
            'ok' => true,
            'fetched' => count($rows),
            'inserted' => (int) ($save['inserted'] ?? 0),
            'skipped_dup' => (int) ($save['skipped_dup'] ?? 0),
            'start' => $fetchStart,
            'end' => $fetchEnd,
            'trimmed' => !empty($trimmed['trimmed']),
            'method' => (string) ($remote['method'] ?? 'unknown'),
            'from_cache' => !empty($remote['from_cache']),
            'auth_method' => (string) ($remote['auth_method'] ?? ''),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function getQrisRowsFromDb($db, string $startYmd, string $endYmd): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startYmd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endYmd)) {
            return [];
        }

        $rows = $db->query(
            'SELECT id, tanggal, waktu, rrn, nominal, status, keterangan, mid, outlet_name, created_at
             FROM bca_qris_transaksi
             WHERE tanggal >= ? AND tanggal <= ?
             ORDER BY tanggal DESC, waktu DESC, id DESC',
            [$startYmd, $endYmd]
        )->result_array();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array{inserted:int,skipped_dup:int}
     */
    public static function saveQrisRows($db, array $rows, string $mid = '', string $outletName = ''): array
    {
        $inserted = 0;
        $skippedDup = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tanggal = self::parseQrisTanggal($row);
            $rrn = trim((string) ($row['rrn'] ?? ''));
            $nominal = self::normalizeNominal($row['nominal'] ?? 0);
            $waktu = trim((string) ($row['waktu'] ?? ''));
            $status = trim((string) ($row['status'] ?? ''));
            $keterangan = trim((string) ($row['keterangan'] ?? ''));

            if ($tanggal === null || $rrn === '' || (float) $nominal <= 0) {
                continue;
            }

            $fingerprint = self::qrisFingerprint($tanggal, $rrn, $nominal, $waktu);

            $db->insertIgnore('bca_qris_transaksi', [
                'tanggal' => $tanggal,
                'waktu' => $waktu !== '' ? $waktu : null,
                'rrn' => $rrn,
                'nominal' => $nominal,
                'status' => $status !== '' ? $status : null,
                'keterangan' => $keterangan !== '' ? $keterangan : null,
                'mid' => $mid !== '' ? $mid : null,
                'outlet_name' => $outletName !== '' ? $outletName : null,
                'fingerprint' => $fingerprint,
            ]);

            if ($db->conn()->affected_rows > 0) {
                $inserted++;
            } else {
                $skippedDup++;
            }
        }

        return [
            'inserted' => $inserted,
            'skipped_dup' => $skippedDup,
        ];
    }

    public static function qrisFingerprint(string $tanggal, string $rrn, $nominal, string $waktu = ''): string
    {
        $payload = $tanggal . '|' . $rrn . '|' . self::normalizeNominal($nominal) . '|' . $waktu;

        return hash('sha256', $payload);
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function parseQrisTanggal(array $row): ?string
    {
        $raw = trim((string) ($row['tanggal'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        $iso = self::parseTanggalIso($raw);
        if ($iso !== null) {
            return $iso;
        }

        return null;
    }

    /**
     * Ambil transaksi QRIS dari node service (tanpa DB).
     *
     * @param array{email?:string,password?:string} $credentials
     * @return array{ok:bool,method?:string,transactions?:array,count?:int,error?:string,message?:string}
     */
    public static function qrisTransactions(
        ?string $startDate = null,
        ?string $endDate = null,
        array $credentials = []
    ): array {
        $payload = self::buildQrisPayload($credentials);
        if ($startDate !== null && $startDate !== '') {
            $payload['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $payload['end_date'] = $endDate;
        }

        $remote = self::callService(
            self::configString('BCA_SCRAPPER_QRIS_URL', self::DEFAULT_QRIS_URL),
            $payload
        );

        if ($remote === null) {
            return [
                'ok' => false,
                'error' => 'bca_scrapper_unreachable',
                'message' => 'Gagal menghubungi bca_scrapper. Pastikan service berjalan.',
            ];
        }

        if (!empty($remote['ok'])) {
            return [
                'ok' => true,
                'method' => (string) ($remote['method'] ?? 'unknown'),
                'auth_method' => (string) ($remote['auth_method'] ?? ''),
                'from_cache' => !empty($remote['from_cache']),
                'start_date' => (string) ($remote['start_date'] ?? ''),
                'end_date' => (string) ($remote['end_date'] ?? ''),
                'transactions' => is_array($remote['transactions'] ?? null) ? $remote['transactions'] : [],
                'outlets' => is_array($remote['outlets'] ?? null) ? $remote['outlets'] : [],
                'count' => (int) ($remote['count'] ?? 0),
            ];
        }

        return self::failFromRemote($remote);
    }

    private static function markQrisDaysSynced($db, string $startYmd, string $endYmd): void
    {
        for ($d = $startYmd; $d <= $endYmd; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $countRow = $db->query(
                'SELECT COUNT(*) AS c FROM bca_qris_transaksi WHERE tanggal = ?',
                [$d]
            )->row_array();
            $count = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

            $db->query(
                'INSERT INTO bca_qris_hari (tanggal, tx_count, synced_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE tx_count = VALUES(tx_count), synced_at = NOW()',
                [$d, $count]
            );
        }
    }

    /**
     * @param array{email?:string,password?:string} $credentials
     * @return array<string,string>
     */
    private static function buildQrisPayload(array $credentials): array
    {
        $payload = [];
        $email = trim((string) ($credentials['email'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        if ($email !== '') {
            $payload['email'] = $email;
        }
        if ($password !== '') {
            $payload['password'] = $password;
        }

        return $payload;
    }

    public static function parseTanggalIso(string $tanggal): ?string
    {
        $raw = trim($tanggal);
        if ($raw === '' || strtoupper($raw) === 'PEND') {
            return null;
        }

        $dt = \DateTime::createFromFormat('d/m/Y', $raw);
        if ($dt instanceof \DateTime) {
            return $dt->format('Y-m-d');
        }

        return null;
    }

    public static function fingerprint(string $tanggal, string $keterangan, $nominal, string $mutasi): string
    {
        $nominalStr = number_format((float) $nominal, 2, '.', '');
        $payload = $tanggal . '|' . $keterangan . '|' . $nominalStr . '|' . strtoupper($mutasi);

        return hash('sha256', $payload);
    }

    /**
     * @param mixed $value
     */
    private static function normalizeNominal($value): string
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private static function daysBetween(string $startYmd, string $endYmd): int
    {
        $start = strtotime($startYmd);
        $end = strtotime($endYmd);
        if ($start === false || $end === false) {
            return 0;
        }

        return (int) floor(($end - $start) / 86400);
    }

    /**
     * @param array{username?:string,password?:string} $credentials
     * @return array<string,string>
     */
    private static function buildPayload(array $credentials): array
    {
        $payload = [];
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        if ($username !== '') {
            $payload['username'] = $username;
        }
        if ($password !== '') {
            $payload['password'] = $password;
        }

        return $payload;
    }

    /**
     * @return array|null null = transport gagal
     */
    private static function callService(string $endpoint, array $payload): ?array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'error' => 'json_encode_failed'];
        }

        $headers = ['Content-Type: application/json'];
        $token = self::configString('BCA_SCRAPPER_TOKEN', '');
        if ($token !== '') {
            $headers[] = 'X-Bca-Token: ' . $token;
        }

        $timeout = max(30, (int) self::configString('BCA_SCRAPPER_TIMEOUT', (string) self::DEFAULT_TIMEOUT));

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $timeout));
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            error_log('[BcaScrapper] curl fail errno=' . $errno . ' err=' . $cerr . ' url=' . $endpoint);
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            error_log('[BcaScrapper] bad json http=' . $http . ' body=' . substr((string) $body, 0, 300));
            return null;
        }

        $decoded['_http'] = $http;
        if ($http === 401 && empty($decoded['error'])) {
            $decoded['error'] = 'unauthorized';
        }

        return $decoded;
    }

    /**
     * @param array $remote
     * @return array{ok:false,error:string,message:string,http?:int|null}
     */
    private static function failFromRemote(array $remote): array
    {
        $err = (string) ($remote['error'] ?? 'scrape_failed');
        $msg = (string) ($remote['message'] ?? '');
        if ($msg === '') {
            $msg = self::defaultMessageForError($err);
        }

        return [
            'ok' => false,
            'error' => $err,
            'message' => $msg,
            'http' => isset($remote['_http']) ? (int) $remote['_http'] : null,
            'http_error' => $remote['http_error'] ?? null,
            'puppeteer_error' => $remote['puppeteer_error'] ?? null,
        ];
    }

    private static function defaultMessageForError(string $error): string
    {
        switch ($error) {
            case 'unauthorized':
                return 'Token bca_scrapper tidak cocok (401). Samakan BCA_SCRAPPER_TOKEN lalu restart service.';
            case 'credentials_required':
                return 'Username/password KlikBCA wajib diisi.';
            case 'scraper_busy':
                return 'bca_scrapper sedang sibuk. Coba lagi sebentar.';
            case 'cooldown':
                return 'bca_scrapper cooldown aktif. Coba lagi beberapa menit.';
            case 'timeout':
                return 'Timeout saat mengambil data dari KlikBCA.';
            case 'invalid_date':
            case 'date_range_too_large':
            case 'end_date_future':
            case 'start_date_too_old':
                return 'Rentang tanggal mutasi tidak valid (' . $error . ').';
            default:
                return 'Gagal mengambil data BCA (' . $error . ')';
        }
    }

    private static function configString(string $key, string $default): string
    {
        if (class_exists('Env', false) && defined('Env::' . $key)) {
            $v = constant('Env::' . $key);
            if (is_string($v) && $v !== '') {
                return $v;
            }
            if ($key === 'BCA_SCRAPPER_TOKEN' && is_string($v)) {
                return '';
            }
        }

        $v = getenv($key);
        if (is_string($v) && $v !== '') {
            return $v;
        }
        if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return $default;
    }
}
