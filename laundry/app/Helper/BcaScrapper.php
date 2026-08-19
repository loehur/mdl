<?php

/**
 * Client HTTP ke node/bca_scrapper (saldo & mutasi KlikBCA).
 * Strategi di server: HTTP dulu, Puppeteer fallback.
 */
class BcaScrapper
{
    private const DEFAULT_MUTASI_URL = 'http://127.0.0.1:3021/mutasi';
    private const DEFAULT_BALANCE_URL = 'http://127.0.0.1:3021/balance';
    private const DEFAULT_TIMEOUT = 90;

    /**
     * Ambil mutasi rekening BCA.
     *
     * @param string|null $startDate YYYY-MM-DD
     * @param string|null $endDate YYYY-MM-DD
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
     * Ambil saldo terakhir rekening BCA.
     *
     * @param array{username?:string,password?:string} $credentials
     * @return array{ok:bool,method?:string,balance?:array,error?:string,message?:string}
     */
    public static function balance(array $credentials = []): array
    {
        $remote = self::callService(
            self::configString('BCA_SCRAPPER_BALANCE_URL', self::DEFAULT_BALANCE_URL),
            self::buildPayload($credentials)
        );

        if ($remote === null) {
            return [
                'ok' => false,
                'error' => 'bca_scrapper_unreachable',
                'message' => 'Gagal menghubungi bca_scrapper. Pastikan service berjalan.',
            ];
        }

        if (!empty($remote['ok']) && is_array($remote['balance'] ?? null)) {
            return [
                'ok' => true,
                'method' => (string) ($remote['method'] ?? 'unknown'),
                'balance' => $remote['balance'],
                'http_error' => $remote['http_error'] ?? null,
            ];
        }

        return self::failFromRemote($remote);
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
            default:
                return 'Gagal mengambil data BCA (' . $error . ')';
        }
    }

    private static function configString(string $key, string $default): string
    {
        if (class_exists('URL', false) && defined('URL::' . $key)) {
            $v = constant('URL::' . $key);
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
