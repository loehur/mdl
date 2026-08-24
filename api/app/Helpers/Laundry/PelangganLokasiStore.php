<?php

namespace App\Helpers\Laundry;

use App\Core\DB;
use App\Helpers\CRM\MapsServer;

/**
 * CRUD pelanggan_lokasi (mdl_laundry) — sumber tunggal untuk CRM, laundry kasir, dan J.
 */
class PelangganLokasiStore
{
    /** Radius pencarian alamat dari pusat kota cabang pelanggan (meter). */
    public const KOTA_SEARCH_RADIUS_M = 30000;

    public static function laundryDb(): DB
    {
        return DB::getInstance(1);
    }

    public static function findPelanggan(int $idPelanggan): ?array
    {
        if ($idPelanggan <= 0) {
            return null;
        }
        $row = self::laundryDb()
            ->query(
                'SELECT id_pelanggan, id_cabang, nama_pelanggan, nomor_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$idPelanggan]
            )
            ->row_array();
        if (!is_array($row) || empty($row['id_pelanggan'])) {
            return null;
        }
        return $row;
    }

    public static function formatItem(array $r): array
    {
        $latt = (float) ($r['latt'] ?? 0);
        $longt = (float) ($r['longt'] ?? 0);
        return [
            'id_lokasi' => (int) ($r['id_lokasi'] ?? 0),
            'nama' => (string) ($r['nama'] ?? ''),
            'detail' => (string) ($r['detail'] ?? ''),
            'latt' => $latt,
            'longt' => $longt,
            'maps_url' => ($latt != 0.0 || $longt != 0.0)
                ? ('https://www.google.com/maps?q=' . $latt . ',' . $longt)
                : '',
            'insertTime' => (string) ($r['insertTime'] ?? ''),
        ];
    }

    /**
     * @return array{ok:bool,message?:string,pelanggan?:array,items?:array}
     */
    public static function list(int $idPelanggan): array
    {
        $pel = self::findPelanggan($idPelanggan);
        if ($pel === null) {
            return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
        }

        $rows = self::laundryDb()
            ->query(
                'SELECT id_lokasi, id_pelanggan, nama, detail, latt, longt, insertTime
                 FROM pelanggan_lokasi
                 WHERE id_pelanggan = ?
                 ORDER BY id_lokasi DESC',
                [$idPelanggan]
            )
            ->result_array();
        if (!is_array($rows)) {
            $rows = [];
        }

        $items = [];
        foreach ($rows as $r) {
            $items[] = self::formatItem($r);
        }

        return [
            'ok' => true,
            'pelanggan' => [
                'id_pelanggan' => (int) $pel['id_pelanggan'],
                'nama_pelanggan' => (string) ($pel['nama_pelanggan'] ?? ''),
                'nomor_pelanggan' => (string) ($pel['nomor_pelanggan'] ?? ''),
            ],
            'items' => $items,
            'default_map' => self::getDefaultMapCoords($idPelanggan),
        ];
    }

    /**
     * Titik peta default: koordinat kota cabang pelanggan (kota.latt / kota.longt).
     *
     * @return array{latt:float,longt:float,nama_kota:string,source:string}
     */
    public static function getDefaultMapCoords(int $idPelanggan): array
    {
        $fallback = [
            'latt' => 0.507068,
            'longt' => 101.447779,
            'nama_kota' => 'PEKANBARU',
            'source' => 'fallback',
        ];

        $pel = self::findPelanggan($idPelanggan);
        if ($pel === null) {
            return $fallback;
        }

        $idCabang = (int) ($pel['id_cabang'] ?? 0);
        if ($idCabang <= 0) {
            return $fallback;
        }

        $cabang = self::laundryDb()
            ->query(
                'SELECT id_kota FROM cabang WHERE id_cabang = ? LIMIT 1',
                [$idCabang]
            )
            ->row_array();
        if (!is_array($cabang)) {
            return $fallback;
        }

        $idKota = (int) ($cabang['id_kota'] ?? 0);
        if ($idKota <= 0) {
            return $fallback;
        }

        $kota = self::laundryDb()
            ->query(
                'SELECT nama_kota, latt, longt FROM kota WHERE id_kota = ? LIMIT 1',
                [$idKota]
            )
            ->row_array();
        if (!is_array($kota)) {
            return $fallback;
        }

        $latt = (float) ($kota['latt'] ?? 0);
        $longt = (float) ($kota['longt'] ?? 0);
        if ($latt == 0.0 && $longt == 0.0) {
            return $fallback;
        }

        return [
            'latt' => $latt,
            'longt' => $longt,
            'nama_kota' => (string) ($kota['nama_kota'] ?? ''),
            'source' => 'kota',
        ];
    }

    /**
     * Batasi autocomplete/placeDetails ke radius kota cabang pelanggan.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public static function applyPelangganSearchRestrict(array $body): array
    {
        $idPel = (int) ($body['cust_id'] ?? $body['id_pelanggan'] ?? 0);
        if ($idPel <= 0) {
            return $body;
        }

        $kota = self::getDefaultMapCoords($idPel);
        $lat = (float) ($kota['latt'] ?? 0);
        $lng = (float) ($kota['longt'] ?? 0);
        if ($lat == 0.0 && $lng == 0.0) {
            return $body;
        }

        $body['lat'] = $lat;
        $body['lng'] = $lng;
        $body['hard_restrict'] = true;
        $body['restrict_radius'] = self::KOTA_SEARCH_RADIUS_M;
        $body['restrict_lat'] = $lat;
        $body['restrict_lng'] = $lng;

        return $body;
    }

    /**
     * @return array{ok:bool,message?:string,latt?:float,longt?:float,source?:string}
     */
    public static function resolveMaps(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['ok' => false, 'message' => 'URL Google Maps wajib diisi'];
        }

        $res = MapsServer::resolve($url);
        $coords = self::coordsFromResolve($res);
        if ($coords === null) {
            $msg = is_array($res)
                ? (string) ($res['message'] ?? $res['error'] ?? 'Gagal membaca koordinat dari URL')
                : 'Gagal membaca koordinat dari URL';
            return ['ok' => false, 'message' => $msg];
        }

        return [
            'ok' => true,
            'latt' => $coords['latt'],
            'longt' => $coords['longt'],
            'source' => is_array($res) ? (string) ($res['source'] ?? '') : '',
        ];
    }

    /**
     * @param array{id_pelanggan:int,nama:string,detail:string,gmaps_url?:string,latt?:float,longt?:float} $input
     * @return array{ok:bool,message?:string,id_lokasi?:int,latt?:float,longt?:float,lokasi?:array,items?:array}
     */
    public static function add(array $input): array
    {
        $idPelanggan = (int) ($input['id_pelanggan'] ?? 0);
        $pel = self::findPelanggan($idPelanggan);
        if ($pel === null) {
            return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
        }

        $nama = trim((string) ($input['nama'] ?? ''));
        $detail = trim((string) ($input['detail'] ?? ''));
        $err = self::validateNamaDetail($nama, $detail);
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }

        $coords = self::resolveInputCoords($input);
        if ($coords === null) {
            return ['ok' => false, 'message' => 'Koordinat belum valid. Isi URL Google Maps atau titik peta.'];
        }

        $now = date('Y-m-d H:i:s');
        $idLokasi = self::laundryDb()->insert('pelanggan_lokasi', [
            'id_pelanggan' => $idPelanggan,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => $coords['latt'],
            'longt' => $coords['longt'],
            'insertTime' => $now,
        ]);
        if (!$idLokasi) {
            return ['ok' => false, 'message' => 'Gagal menyimpan lokasi'];
        }

        $lokasi = self::formatItem([
            'id_lokasi' => (int) $idLokasi,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => $coords['latt'],
            'longt' => $coords['longt'],
            'insertTime' => $now,
        ]);

        $listed = self::list($idPelanggan);
        return [
            'ok' => true,
            'message' => 'Lokasi berhasil ditambahkan',
            'id_lokasi' => (int) $idLokasi,
            'latt' => $coords['latt'],
            'longt' => $coords['longt'],
            'lokasi' => $lokasi,
            'items' => $listed['items'] ?? [$lokasi],
        ];
    }

    /**
     * @param array{id_pelanggan:int,id_lokasi:int,nama:string,detail:string,gmaps_url?:string,latt?:float,longt?:float} $input
     */
    public static function update(array $input): array
    {
        $idPelanggan = (int) ($input['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($input['id_lokasi'] ?? 0);
        if (self::findPelanggan($idPelanggan) === null) {
            return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
        }
        if ($idLokasi <= 0) {
            return ['ok' => false, 'message' => 'Lokasi tidak valid'];
        }

        $nama = trim((string) ($input['nama'] ?? ''));
        $detail = trim((string) ($input['detail'] ?? ''));
        $err = self::validateNamaDetail($nama, $detail);
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }

        $row = self::laundryDb()
            ->query(
                'SELECT id_lokasi, nama, detail, latt, longt, insertTime FROM pelanggan_lokasi WHERE id_lokasi = ? AND id_pelanggan = ? LIMIT 1',
                [$idLokasi, $idPelanggan]
            )
            ->row_array();
        if (!is_array($row) || empty($row['id_lokasi'])) {
            return ['ok' => false, 'message' => 'Lokasi tidak ditemukan'];
        }

        $set = [
            'nama' => $nama,
            'detail' => $detail,
        ];
        // Koordinat dari peta (posted) lebih diutamakan daripada resolve URL —
        // URL opsional bisa gagal parse meski titik peta sudah valid.
        $coords = self::resolveInputCoords($input);
        if ($coords !== null) {
            $set['latt'] = $coords['latt'];
            $set['longt'] = $coords['longt'];
        }

        $ok = self::laundryDb()->update(
            'pelanggan_lokasi',
            $set,
            ['id_lokasi' => $idLokasi, 'id_pelanggan' => $idPelanggan]
        );
        if ($ok === false) {
            return ['ok' => false, 'message' => 'Gagal memperbarui lokasi'];
        }

        $fresh = self::laundryDb()
            ->query(
                'SELECT id_lokasi, nama, detail, latt, longt, insertTime FROM pelanggan_lokasi WHERE id_lokasi = ? AND id_pelanggan = ? LIMIT 1',
                [$idLokasi, $idPelanggan]
            )
            ->row_array();
        $item = self::formatItem(is_array($fresh) ? $fresh : array_merge($row, $set));
        $listed = self::list($idPelanggan);

        return [
            'ok' => true,
            'message' => 'Lokasi berhasil diperbarui',
            'latt' => $item['latt'],
            'longt' => $item['longt'],
            'lokasi' => $item,
            'items' => $listed['items'] ?? [],
        ];
    }

    public static function delete(int $idPelanggan, int $idLokasi): array
    {
        if (self::findPelanggan($idPelanggan) === null) {
            return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
        }
        if ($idLokasi <= 0) {
            return ['ok' => false, 'message' => 'Lokasi tidak valid'];
        }

        $row = self::laundryDb()
            ->query(
                'SELECT id_lokasi FROM pelanggan_lokasi WHERE id_lokasi = ? AND id_pelanggan = ? LIMIT 1',
                [$idLokasi, $idPelanggan]
            )
            ->row_array();
        if (!is_array($row) || empty($row['id_lokasi'])) {
            return ['ok' => false, 'message' => 'Lokasi tidak ditemukan'];
        }

        $aktif = self::laundryDb()
            ->query(
                "SELECT COUNT(*) AS n FROM delivery_request
                 WHERE id_pelanggan = ? AND id_lokasi = ?
                 AND delivery_status IN ('berjalan','menunggu_pembayaran')",
                [$idPelanggan, $idLokasi]
            )
            ->row_array();
        if ((int) ($aktif['n'] ?? 0) > 0) {
            return [
                'ok' => false,
                'message' => 'Lokasi tidak bisa dihapus karena masih ada permintaan kurir aktif.',
            ];
        }

        $ok = self::laundryDb()->delete('pelanggan_lokasi', [
            'id_lokasi' => $idLokasi,
            'id_pelanggan' => $idPelanggan,
        ]);
        if ($ok === false) {
            return ['ok' => false, 'message' => 'Gagal menghapus lokasi'];
        }

        $listed = self::list($idPelanggan);
        return [
            'ok' => true,
            'message' => 'Lokasi dihapus',
            'items' => $listed['items'] ?? [],
        ];
    }

    private static function validateNamaDetail(string $nama, string $detail): ?string
    {
        if ($nama === '') {
            return 'Nama lokasi wajib diisi';
        }
        if (strlen($nama) > 50) {
            return 'Nama lokasi terlalu panjang';
        }
        if ($detail === '') {
            return 'Detail alamat wajib diisi';
        }
        if (strlen($detail) > 255) {
            return 'Detail alamat terlalu panjang';
        }
        return null;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{latt:float,longt:float}|null
     */
    private static function resolveInputCoords(array $input): ?array
    {
        $posted = self::postedCoords($input);
        if ($posted !== null) {
            return $posted;
        }
        $gmapsUrl = trim((string) ($input['gmaps_url'] ?? $input['url'] ?? ''));
        if ($gmapsUrl !== '') {
            return self::coordsFromResolve(MapsServer::resolve($gmapsUrl));
        }
        return null;
    }

    /**
     * @param array<string,mixed>|null $res
     * @return array{latt:float,longt:float}|null
     */
    private static function coordsFromResolve(?array $res): ?array
    {
        if (!is_array($res) || empty($res['ok'])) {
            return null;
        }
        $latt = (float) ($res['latt'] ?? $res['lat'] ?? 0);
        $longt = (float) ($res['longt'] ?? $res['long'] ?? $res['lng'] ?? 0);
        if ($latt == 0.0 && $longt == 0.0) {
            return null;
        }
        if ($latt < -90 || $latt > 90 || $longt < -180 || $longt > 180) {
            return null;
        }
        return [
            'latt' => round($latt, 7),
            'longt' => round($longt, 7),
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    private static function hasPostedCoords(array $input): bool
    {
        return isset($input['latt']) || isset($input['lat']) || isset($input['longt']) || isset($input['long']) || isset($input['lng']);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{latt:float,longt:float}|null
     */
    private static function postedCoords(array $input): ?array
    {
        $latt = (float) ($input['latt'] ?? $input['lat'] ?? 0);
        $longt = (float) ($input['longt'] ?? $input['long'] ?? $input['lng'] ?? 0);
        if ($latt == 0.0 && $longt == 0.0) {
            return null;
        }
        if ($latt < -90 || $latt > 90 || $longt < -180 || $longt > 180) {
            return null;
        }
        return [
            'latt' => round($latt, 7),
            'longt' => round($longt, 7),
        ];
    }
}
