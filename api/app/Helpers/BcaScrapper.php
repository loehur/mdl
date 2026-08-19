<?php

namespace App\Helpers;

/**
 * Client HTTP ke node/bca_scrapper + sync mutasi ke mdl_main.
 */
class BcaScrapper
{
    public const MAX_RANGE_DAYS = 6;
    public const MAX_LOOKBACK_DAYS = 30;
    public const MAX_SYNC_CHUNKS = 10;

    public const ENTITY_KAS_LAUNDRY = 'kas_laundry';

    private const DEFAULT_MUTASI_URL = 'http://127.0.0.1:3021/mutasi';
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
     * Hitung rentang fetch berdasarkan tanggal terakhir di DB.
     *
     * @return array{skip:bool,start?:string,end?:string,has_more?:bool,reason?:string}
     */
    public static function computeFetchRange($db): array
    {
        $today = date('Y-m-d');
        $minStart = date('Y-m-d', strtotime('-' . self::MAX_LOOKBACK_DAYS . ' days'));

        $row = $db->query(
            'SELECT MAX(tanggal_iso) AS latest FROM bca_mutasi WHERE tanggal_iso IS NOT NULL'
        )->row_array();

        $latest = isset($row['latest']) && $row['latest'] !== '' ? (string) $row['latest'] : null;

        if ($latest !== null) {
            $start = date('Y-m-d', strtotime($latest . ' +1 day'));
        } else {
            $start = $minStart;
        }

        if ($start < $minStart) {
            $start = $minStart;
        }

        $end = $today;

        // Sudah ada data hari ini → tetap fetch hari ini (transaksi baru), dedup per baris
        if ($start > $end) {
            $start = $today;
            $end = $today;
        }

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
     * Rentang mutasi untuk matching kas BCA — dipaksa mengikuti aturan server:
     * end <= hari ini, end <= tanggal kas, rentang max 6 hari, lookback max 30 hari.
     *
     * @return array{valid:bool,start?:string,end?:string,reason?:string}
     */
    public static function computeKasMutasiRange(string $insertTime): array
    {
        $ts = strtotime($insertTime);
        if ($ts === false) {
            return ['valid' => false, 'reason' => 'invalid_insert_time'];
        }

        $today = date('Y-m-d');
        $kasDate = date('Y-m-d', $ts);
        $end = $kasDate <= $today ? $kasDate : $today;

        $idealStart = date('Y-m-d', strtotime($end . ' -' . (self::MAX_RANGE_DAYS - 1) . ' days'));
        $minStart = date('Y-m-d', strtotime($today . ' -' . self::MAX_LOOKBACK_DAYS . ' days'));
        $start = $idealStart >= $minStart ? $idealStart : $minStart;

        if ($start > $end) {
            return ['valid' => false, 'reason' => 'kas_too_old_for_lookback', 'start' => $start, 'end' => $end];
        }

        $clamped = self::clampDateRange($start, $end);
        if (empty($clamped['valid'])) {
            return $clamped;
        }

        return $clamped;
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
     * @param array<int,array<string,mixed>> $rows
     * @return array{inserted:int,skipped_dup:int}
     */
    public static function saveMutasiRows($db, array $rows): array
    {
        $inserted = 0;
        $skippedDup = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tanggal = trim((string) ($row['tanggal'] ?? ''));
            $keterangan = trim((string) ($row['keterangan'] ?? ''));
            $mutasi = strtoupper(trim((string) ($row['mutasi'] ?? '')));
            $nominal = self::normalizeNominal($row['nominal'] ?? 0);

            if ($tanggal === '' || $keterangan === '' || !in_array($mutasi, ['CR', 'DB'], true)) {
                continue;
            }

            $tanggalIso = self::parseTanggalIso($tanggal);
            $fingerprint = self::fingerprint($tanggal, $keterangan, $nominal, $mutasi);

            $db->insertIgnore('bca_mutasi', [
                'tanggal' => $tanggal,
                'tanggal_iso' => $tanggalIso,
                'keterangan' => $keterangan,
                'nominal' => $nominal,
                'mutasi' => $mutasi,
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
