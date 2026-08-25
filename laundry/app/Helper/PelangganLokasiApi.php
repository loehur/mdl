<?php

/**
 * Laundry → https://api.nalju.com/Laundry/PelangganLokasi
 * Sumber tunggal lokasi pelanggan (CRM + laundry).
 */
class PelangganLokasiApi
{
    private static $apiUrl;

    private static function base(): string
    {
        return ApiLoopback::baseUrl() . '/Laundry/PelangganLokasi';
    }

    public static function list(int $idPelanggan): array
    {
        return self::request('listLokasi?id_pelanggan=' . $idPelanggan, null, false);
    }

    /**
     * @param array<string,mixed> $payload id_pelanggan, nama, detail, gmaps_url? latt? longt?
     */
    public static function add(array $payload): array
    {
        return self::request('add', $payload, true);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function update(array $payload): array
    {
        return self::request('update', $payload, true);
    }

    public static function delete(int $idPelanggan, int $idLokasi): array
    {
        return self::request('delete', [
            'id_pelanggan' => $idPelanggan,
            'id_lokasi' => $idLokasi,
        ], true);
    }

    public static function resolveMaps(string $url): array
    {
        return self::request('resolveMaps', ['url' => $url], true);
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    private static function request(string $path, ?array $payload, bool $post): array
    {
        $url = rtrim(self::base(), '/') . '/' . ltrim($path, '/');
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $headers = ApiLoopback::headers($url, $headers);

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
        $opts = ApiLoopback::curlOpts($url, $opts);
        if ($post) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return ['ok' => false, 'message' => 'Koneksi API lokasi gagal: ' . $curlErr];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Respon API lokasi tidak valid (HTTP ' . $http . ')',
            ];
        }

        if ($http >= 400 && empty($decoded['ok'])) {
            $decoded['ok'] = false;
            if (empty($decoded['message'])) {
                $decoded['message'] = 'HTTP ' . $http;
            }
        }

        return $decoded;
    }
}
