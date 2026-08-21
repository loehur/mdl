<?php

namespace App\Helpers\CRM;

/**
 * Normalisasi URL media WA (CRM proxy / HTTPS upgrade).
 */
class WaMediaHelper
{
    private const HTTPS_UPGRADE_HOSTS = [
        'api.nalju.com',
        'ml.nalju.com',
        'www.nalju.com',
        'nalju.com',
    ];

    private const PROXY_ALLOWED_HOSTS = [
        'api.nalju.com',
        'ml.nalju.com',
        '127.0.0.1',
        'localhost',
    ];

    public static function normalizeMediaUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        if (stripos($url, 'data:') === 0) {
            return $url;
        }
        if (!preg_match('#^http://#i', $url)) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $host = strtolower((string) $parts['host']);
        if (self::hostMatchesList($host, self::HTTPS_UPGRADE_HOSTS)) {
            return preg_replace('#^http://#i', 'https://', $url, 1);
        }

        return $url;
    }

    public static function isProxyAllowedMediaHost(string $host): bool
    {
        return self::hostMatchesList(strtolower(trim($host)), self::PROXY_ALLOWED_HOSTS);
    }

    /** @param list<string> $allowedHosts */
    private static function hostMatchesList(string $host, array $allowedHosts): bool
    {
        if ($host === '') {
            return false;
        }
        foreach ($allowedHosts as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === '') {
                continue;
            }
            if ($host === $allowed || substr($host, -strlen('.' . $allowed)) === '.' . $allowed) {
                return true;
            }
        }

        return false;
    }
}
