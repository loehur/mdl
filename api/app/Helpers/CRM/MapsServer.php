<?php

namespace App\Helpers\CRM;

/**
 * Client ke node/maps_server — resolve URL Google Maps → lat/lng.
 * PHP & Node di VPS yang sama → default 127.0.0.1:3020.
 */
class MapsServer
{
    private const DEFAULT_URL = 'http://127.0.0.1:3020/resolve';
    private const DEFAULT_TIMEOUT = 22;

    public static function resolveUrl(): string
    {
        return self::envString('MAPS_SERVER_URL', self::DEFAULT_URL);
    }

    public static function token(): string
    {
        return self::envString('MAPS_SERVER_TOKEN', '');
    }

    /**
     * Resolve URL / teks berisi URL Maps.
     *
     * @return array{ok:bool,lat?:float,lng?:float,latt?:float,long?:float,error?:string,raw?:array}|null
     *         null hanya jika transport gagal total (server down / JSON invalid).
     */
    public static function resolve(string $urlOrText, ?int $timeoutSec = null): ?array
    {
        $urlOrText = trim($urlOrText);
        if ($urlOrText === '') {
            return ['ok' => false, 'error' => 'url_required'];
        }

        $endpoint = self::resolveUrl();
        $timeout = $timeoutSec !== null ? max(1, $timeoutSec) : self::DEFAULT_TIMEOUT;
        $payload = json_encode(['url' => $urlOrText], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return ['ok' => false, 'error' => 'json_encode_failed'];
        }

        $headers = ['Content-Type: application/json'];
        $token = self::token();
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
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            if (class_exists('\Log')) {
                \Log::write('MapsServer resolve curl: ' . $err, 'wa_error', 'MapsServer');
            }
            return null;
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            if (class_exists('\Log')) {
                \Log::write('MapsServer resolve bad json http=' . $http, 'wa_error', 'MapsServer');
            }
            return null;
        }

        if (!empty($json['ok']) && isset($json['lat'], $json['lng'])) {
            $lat = (float) $json['lat'];
            $lng = (float) $json['lng'];
            return [
                'ok' => true,
                'lat' => $lat,
                'lng' => $lng,
                'latt' => $lat,
                'long' => $lng,
                'source' => $json['source'] ?? null,
                'accuracy' => $json['accuracy'] ?? null,
                'final_url' => $json['final_url'] ?? null,
                'raw' => $json,
            ];
        }

        return [
            'ok' => false,
            'error' => (string) ($json['error'] ?? 'resolve_failed'),
            'message' => $json['message'] ?? null,
            'raw' => $json,
        ];
    }

    private static function envString(string $const, string $default): string
    {
        if (!class_exists('\Env', false)) {
            return $default;
        }
        try {
            $ref = new \ReflectionClass('\Env');
            if (!$ref->hasConstant($const)) {
                return $default;
            }
            $v = $ref->getConstant($const);
            if (!is_string($v) || $v === '') {
                return $default;
            }
            return $v;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
