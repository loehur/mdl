<?php

/**
 * Laundry → https://api.nalju.com/Laundry/MapsConfig
 * Proxy Google Maps config & Places untuk portal J (same-origin).
 */
class MapsConfigApi
{
    private static $apiUrl = 'https://api.nalju.com/Laundry/MapsConfig';

    public static function get(): array
    {
        return self::request('get', null, false);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function autocomplete(array $payload): array
    {
        return self::request('autocomplete', $payload, true);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function placeDetails(array $payload): array
    {
        return self::request('placeDetails', $payload, true);
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    private static function request(string $path, ?array $payload, bool $post): array
    {
        $url = rtrim(self::$apiUrl, '/') . '/' . ltrim($path, '/');
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
        if ($post) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return ['ok' => false, 'message' => 'Koneksi API Maps gagal: ' . $curlErr];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'message' => 'Respons API Maps tidak valid'];
        }

        return $decoded;
    }
}
