<?php

/**
 * Resolve URL Google Maps → lat/lng.
 * Parse lokal dulu; jika gagal → POST ke maps_server /resolve.
 */
class MapsServer
{
    private const DEFAULT_URL = 'http://127.0.0.1:3020/resolve';
    private const DEFAULT_TIMEOUT = 12;

    /**
     * Parse koordinat dari teks/URL tanpa memanggil maps_server.
     *
     * @return array{latt:float,longt:float}|null
     */
    public static function extractCoordsFromText(string $urlOrText): ?array
    {
        $msg = trim($urlOrText);
        if ($msg === '') {
            return null;
        }

        if (preg_match('/(-?\d{1,2}\.\d{3,})\s*,\s*(-?\d{1,3}\.\d{3,})/', $msg, $m)
            || preg_match('/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', $msg, $m)) {
            $coords = self::normalizePair((float) $m[1], (float) $m[2]);
            if ($coords !== null) {
                return $coords;
            }
        }

        if (preg_match('/[?&]q=(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/i', $msg, $m)
            || preg_match('/@(-?\d+\.?\d+),(-?\d+\.?\d+)/', $msg, $m)
            || preg_match('/maps\/place\/(-?\d+\.?\d+),(-?\d+\.?\d+)/i', $msg, $m)) {
            return self::normalizePair((float) $m[1], (float) $m[2]);
        }

        return null;
    }

    /**
     * Resolve URL/teks: lokal dulu, lalu maps_server.
     *
     * @return array{ok:bool,latt?:float,longt?:float,lat?:float,lng?:float,source?:string,error?:string,message?:string}
     */
    public static function resolve(string $urlOrText, ?int $timeoutSec = null): array
    {
        $urlOrText = trim($urlOrText);
        if ($urlOrText === '') {
            return ['ok' => false, 'error' => 'url_required', 'message' => 'URL Google Maps wajib diisi'];
        }

        $local = self::extractCoordsFromText($urlOrText);
        if ($local !== null) {
            return [
                'ok' => true,
                'latt' => $local['latt'],
                'longt' => $local['longt'],
                'lat' => $local['latt'],
                'lng' => $local['longt'],
                'source' => 'local',
            ];
        }

        $remote = self::callMapsServer($urlOrText, $timeoutSec);
        if ($remote === null) {
            return [
                'ok' => false,
                'error' => 'maps_server_unreachable',
                'message' => 'Gagal menghubungi maps_server. Pastikan service berjalan.',
            ];
        }

        if (!empty($remote['ok']) && isset($remote['lat'], $remote['lng'])) {
            $latt = (float) $remote['lat'];
            $longt = (float) $remote['lng'];
            $norm = self::normalizePair($latt, $longt);
            if ($norm === null) {
                return [
                    'ok' => false,
                    'error' => 'invalid_coords',
                    'message' => 'Koordinat dari maps_server tidak valid',
                ];
            }
            return [
                'ok' => true,
                'latt' => $norm['latt'],
                'longt' => $norm['longt'],
                'lat' => $norm['latt'],
                'lng' => $norm['longt'],
                'source' => (string) ($remote['source'] ?? 'maps_server'),
            ];
        }

        $err = (string) ($remote['error'] ?? 'resolve_failed');
        $msg = (string) ($remote['message'] ?? '');
        if ($msg === '') {
            $msg = self::defaultMessageForError($err);
        }

        return [
            'ok' => false,
            'error' => $err,
            'message' => $msg,
            'http' => isset($remote['_http']) ? (int) $remote['_http'] : null,
        ];
    }

    private static function defaultMessageForError(string $error): string
    {
        switch ($error) {
            case 'unauthorized':
                return 'Token maps_server tidak cocok (401). Samakan MAPS_SERVER_TOKEN laundry/api dengan maps_server lalu restart keduanya.';
            case 'url_required':
                return 'URL Google Maps tidak terdeteksi / kosong.';
            case 'timeout':
                return 'Timeout membaca URL Maps. Coba lagi atau paste link yang sudah terbuka penuh.';
            case 'no_coords':
                return 'Tidak bisa membaca koordinat dari URL';
            default:
                return 'Tidak bisa membaca koordinat dari URL (' . $error . ')';
        }
    }

    /**
     * @return array{latt:float,longt:float}|null
     */
    private static function normalizePair(float $lat, float $lng): ?array
    {
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return null;
        }
        return ['latt' => $lat, 'longt' => $lng];
    }

    /**
     * @return array|null null = transport gagal
     */
    private static function callMapsServer(string $urlOrText, ?int $timeoutSec = null): ?array
    {
        $endpoint = self::configString('MAPS_SERVER_URL', self::DEFAULT_URL);
        $timeout = $timeoutSec !== null ? max(1, $timeoutSec) : self::DEFAULT_TIMEOUT;
        $payload = json_encode(['url' => $urlOrText], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return ['ok' => false, 'error' => 'json_encode_failed'];
        }

        $headers = ['Content-Type: application/json'];
        $token = self::configString('MAPS_SERVER_TOKEN', '');
        if ($token !== '') {
            $headers[] = 'X-Maps-Token: ' . $token;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            error_log('[MapsServer] curl fail errno=' . $errno . ' err=' . $cerr . ' url=' . $endpoint);
            return null;
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            error_log('[MapsServer] bad json http=' . $http . ' body=' . substr((string) $body, 0, 300));
            return null;
        }
        $json['_http'] = $http;
        if ($http === 401 && empty($json['error'])) {
            $json['error'] = 'unauthorized';
        }
        return $json;
    }

    /**
     * Laundry: baca dari URL:: dulu, lalu getenv / $_ENV.
     */
    private static function configString(string $key, string $default): string
    {
        if (class_exists('URL', false) && defined('URL::' . $key)) {
            $v = constant('URL::' . $key);
            if (is_string($v) && $v !== '') {
                return $v;
            }
            // Konstanta ada tapi kosong (mis. TOKEN='') → anggap sengaja kosong, jangan fallback ke env lain
            if ($key === 'MAPS_SERVER_TOKEN' && is_string($v)) {
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
