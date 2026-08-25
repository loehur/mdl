<?php

/**
 * Laundry → https://api.nalju.com/Laundry/Pelanggan
 * Sumber tunggal CRUD pelanggan (CRM + laundry) — lihat api/app/Helpers/Laundry/PelangganStore.php.
 *
 * Semua request POST menyertakan X-Cron-Secret (URL::API_CRON_SECRET / env API_CRON_SECRET).
 * Response dinormalisasi ke kontrak lama: {ok:1|0, msg?, ...}.
 */
class PelangganApi
{
    private static $apiUrl = 'https://api.nalju.com/Laundry/Pelanggan';

    /** @return array{ok:int,exists?:bool,items?:list<array{id:int,nama:string,hp:string}>} */
    public static function cekHp(string $hp, int $idCabang): array
    {
        return self::request('cekHp', [
            'hp' => $hp,
            'id_cabang' => $idCabang,
        ]);
    }

    /**
     * @param array<string,mixed> $payload nama/f1, hp/f2, hp2/f3, cek_mirip
     * @return array{ok:int,msg?:string,id?:int,nama?:string,hp?:string}
     */
    public static function tambah(array $payload, int $idCabang): array
    {
        return self::request('tambah', array_merge($payload, ['id_cabang' => $idCabang]));
    }

    /** @return array{ok:int,msg?:string,id?:int,nama?:string,hp?:string} */
    public static function pilih(int $id, string $nama, int $idCabang): array
    {
        return self::request('pilih', [
            'id' => $id,
            'nama' => $nama,
            'id_cabang' => $idCabang,
        ]);
    }

    /** @return array{ok:int,msg?:string} */
    public static function update(array $payload, int $idCabang, bool $canEditDisc): array
    {
        return self::request('update', array_merge($payload, [
            'id_cabang' => $idCabang,
            'can_edit_disc' => $canEditDisc ? 1 : 0,
        ]));
    }

    /** @return array{ok:int,nama_dup:?array,ada_konflik:bool,hasil:list<array>} */
    public static function cekEdit(array $payload, int $idCabang): array
    {
        return self::request('cekEdit', array_merge($payload, ['id_cabang' => $idCabang]));
    }

    /** @return array{ok:int,msg?:string} */
    public static function updateCell(int $id, string $mode, $value, int $idCabang, bool $canEditDisc): array
    {
        return self::request('updateCell', [
            'id' => $id,
            'mode' => $mode,
            'value' => $value,
            'id_cabang' => $idCabang,
            'can_edit_disc' => $canEditDisc ? 1 : 0,
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function request(string $path, array $payload): array
    {
        $url = rtrim(self::$apiUrl, '/') . '/' . ltrim($path, '/');

        $secret = (string) (getenv('API_CRON_SECRET') ?: '');
        if ($secret === '') {
            $secret = (string) (defined('URL::API_CRON_SECRET') ? URL::API_CRON_SECRET : '');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($secret !== '' && $secret !== 'change-me-cron-secret') {
            $headers[] = 'X-Cron-Secret: ' . $secret;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return ['ok' => 0, 'msg' => 'Koneksi API pelanggan gagal: ' . $curlErr];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['ok' => 0, 'msg' => 'Respon API pelanggan tidak valid (HTTP ' . $http . ')'];
        }

        // Normalisasi: pastikan ada ok (1/0) dan msg.
        if (!array_key_exists('ok', $decoded)) {
            $decoded['ok'] = ($http >= 200 && $http < 300) ? 1 : 0;
        }
        if ($http >= 400 && empty($decoded['ok'])) {
            $decoded['ok'] = 0;
            if (empty($decoded['msg']) && !empty($decoded['message'])) {
                $decoded['msg'] = $decoded['message'];
            }
            if (empty($decoded['msg'])) {
                $decoded['msg'] = 'HTTP ' . $http;
            }
        }

        return $decoded;
    }
}
