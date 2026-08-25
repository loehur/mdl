<?php

/**
 * Bantu panggilan Laundry → API saat base URL memakai loopback (127.0.0.1 / localhost).
 * Satu VPS: API_BASE_URL=http://127.0.0.1 menghindari DNS + TLS (lebih cepat),
 * tapi Host header harus tetap domain asli agar vhost Apache cocok.
 */
class ApiLoopback
{
    /**
     * Base URL API terpusat.
     * Urutan: env API_BASE_URL → konstanta URL::API_BASE_URL (jika didefinisikan di
     * URL.php lokal, file ini di-gitignore) → default https://api.nalju.com.
     * Untuk satu VPS set API_BASE_URL=http://127.0.0.1 agar panggilan Laundry → API
     * tidak lewat DNS/TLS (lebih cepat).
     */
    public static function baseUrl(): string
    {
        $env = trim((string) (getenv('API_BASE_URL') ?: ''));
        if ($env !== '') {
            return rtrim($env, '/');
        }
        if (defined('URL::API_BASE_URL')) {
            $const = trim((string) URL::API_BASE_URL);
            if ($const !== '') {
                return rtrim($const, '/');
            }
        }
        return 'https://api.nalju.com';
    }

    /** Host asli API (untuk header Host saat loopback). */
    public static function apiHost(): string
    {
        return 'api.nalju.com';
    }

    /** Apakah URL base memakai host loopback? */
    public static function isLoopback(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        return $host === '127.0.0.1' || $host === 'localhost' || $host === '::1';
    }

    /**
     * Header tambahan untuk request loopback (Host domain asli).
     * @param list<string> $existing
     * @return list<string>
     */
    public static function headers(string $url, array $existing = []): array
    {
        if (!self::isLoopback($url)) {
            return $existing;
        }
        $existing[] = 'Host: ' . self::apiHost();
        return $existing;
    }

    /** Opsi curl tambahan untuk loopback (matikan verify TLS bila base http). */
    public static function curlOpts(string $url, array $opts = []): array
    {
        if (!self::isLoopback($url)) {
            return $opts;
        }
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if ($scheme === 'http') {
            // http loopback — tidak perlu TLS verify (tidak relevan, tapi aman)
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        return $opts;
    }
}
