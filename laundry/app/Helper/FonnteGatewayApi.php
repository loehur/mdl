<?php

/**
 * Proxy admin → api.nalju.com/Laundry/Fonnte (status, qr, logout fonnte_server)
 */
class FonnteGatewayApi
{
    private static $apiUrl;

    private static function base(): string
    {
        return ApiLoopback::baseUrl() . '/Laundry/Fonnte';
    }

    /** @return array<string,mixed> */
    public static function status(): array
    {
        return self::callApi('/status', 'GET');
    }

    /** @return array<string,mixed> */
    public static function qr(): array
    {
        return self::callApi('/qr', 'GET');
    }

    /** @return array<string,mixed> */
    public static function logout(): array
    {
        return self::callApi('/logout', 'POST');
    }

    /** @return array<string,mixed> */
    private static function callApi(string $path, string $method = 'GET'): array
    {
        $secret = self::resolveCronSecret();
        $url = rtrim(self::base(), '/') . $path;
        if ($secret !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'secret=' . rawurlencode($secret);
        }

        $headers = ['Accept: application/json'];
        $headers = ApiLoopback::headers($url, $headers);
        if ($secret !== '') {
            $headers[] = 'X-Cron-Secret: ' . $secret;
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        $opts = ApiLoopback::curlOpts($url, $opts);
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = '{}';
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return [
                'ok' => false,
                'message' => 'Tidak dapat hubungi API: ' . $curlErr,
            ];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Respons API tidak valid (HTTP ' . $httpCode . ')',
            ];
        }

        if (!isset($decoded['ok']) && $httpCode === 404) {
            $decoded['ok'] = false;
        }

        return $decoded;
    }

    private static function resolveCronSecret(): string
    {
        if (class_exists('URL') && defined('URL::API_CRON_SECRET')) {
            $s = trim((string) URL::API_CRON_SECRET);
            if ($s !== '' && $s !== 'change-me-cron-secret') {
                return $s;
            }
        }
        foreach (['API_CRON_SECRET', 'CRON_SECRET'] as $envKey) {
            $s = trim((string) (getenv($envKey) ?: ''));
            if ($s !== '') {
                return $s;
            }
        }
        return '';
    }
}
