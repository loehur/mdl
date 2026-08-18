<?php

namespace App\Helpers\Laundry;

use App\Config\Fonnte as FonnteConfig;
use App\Core\DB;
use App\Helpers\CRM\FonnteService;
use App\Helpers\CRM\WaSenderContext;

/**
 * Buat delivery_request sameday + notif group Fonnte.
 * Dipakai CRM Customer Panel (sama seperti hasil akhir intent MINTA_JEMPUT_ANTAR).
 */
class DeliveryRequestStore
{
    /**
     * @param array{cust_id?:int,id_pelanggan?:int,id_lokasi:int,jenis:string,wa_number?:string} $input
     * @return array{ok:bool,message?:string,id_request?:int,jenis?:string,sekalian_jemput?:int,group_sent?:bool}
     */
    public static function create(array $input): array
    {
        $idPelanggan = (int) ($input['id_pelanggan'] ?? $input['cust_id'] ?? 0);
        $idLokasi = (int) ($input['id_lokasi'] ?? 0);
        $parsed = self::parseJenis((string) ($input['jenis'] ?? ''));
        if ($parsed === null) {
            return ['ok' => false, 'message' => 'Pilih Jemput, Antar, atau Antar & Jemput'];
        }
        $jenis = $parsed['jenis'];
        $sekalianJemput = $parsed['sekalian_jemput'];

        $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
        if ($pel === null) {
            return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
        }
        $idCabang = (int) ($pel['id_cabang'] ?? 0);
        if ($idCabang <= 0) {
            return ['ok' => false, 'message' => 'Cabang pelanggan belum lengkap'];
        }
        if ($idLokasi <= 0) {
            return ['ok' => false, 'message' => 'Pilih lokasi dulu'];
        }

        $db = PelangganLokasiStore::laundryDb();
        $lokasi = $db->query(
            'SELECT id_lokasi, nama, detail, latt, longt FROM pelanggan_lokasi
             WHERE id_lokasi = ? AND id_pelanggan = ? LIMIT 1',
            [$idLokasi, $idPelanggan]
        )->row_array();
        if (!is_array($lokasi) || empty($lokasi['id_lokasi'])) {
            return ['ok' => false, 'message' => 'Lokasi tidak ditemukan'];
        }

        $locLat = (float) ($lokasi['latt'] ?? 0);
        $locLon = (float) ($lokasi['longt'] ?? 0);
        if ($locLat == 0.0 && $locLon == 0.0) {
            return ['ok' => false, 'message' => 'Koordinat lokasi pelanggan belum lengkap'];
        }

        $cab = $db->query(
            'SELECT id_cabang, kode_cabang, latt, `long` FROM cabang WHERE id_cabang = ? LIMIT 1',
            [$idCabang]
        )->row_array();
        if (!is_array($cab) || empty($cab['id_cabang'])) {
            return ['ok' => false, 'message' => 'Cabang tidak ditemukan'];
        }
        $cabLat = (float) ($cab['latt'] ?? 0);
        $cabLon = (float) ($cab['long'] ?? 0);
        if ($cabLat == 0.0 && $cabLon == 0.0) {
            return ['ok' => false, 'message' => 'Lokasi cabang belum diatur'];
        }

        $phoneTail = self::phoneTail($pel, (string) ($input['wa_number'] ?? ''));
        if (strlen($phoneTail) < 8) {
            return ['ok' => false, 'message' => 'Nomor pelanggan belum lengkap'];
        }

        $catatanNorm = self::normalizeCatatanKurir($input['catatan'] ?? $input['catatan_kurir'] ?? '');
        if (!$catatanNorm['ok']) {
            return ['ok' => false, 'message' => $catatanNorm['message']];
        }
        $catatanKurir = $catatanNorm['value'];

        if ($jenis === 'jemput') {
            $pendingLokasi = $db->query(
                "SELECT COUNT(*) AS n FROM delivery_request
                 WHERE id_pelanggan = ? AND jenis = 'jemput'
                   AND delivery_status IN ('berjalan','menunggu_pembayaran')
                   AND id_lokasi = ?",
                [$idPelanggan, $idLokasi]
            )->row_array();
            if ((int) ($pendingLokasi['n'] ?? 0) > 0) {
                return [
                    'ok' => false,
                    'message' => 'Sudah ada jemput berjalan di lokasi ini. Tunggu selesai dulu.',
                ];
            }
        }

        $eligibleIds = [];
        if ($jenis === 'antar') {
            $eligibleIds = self::eligibleSaleIds($idPelanggan);
            if ($eligibleIds === []) {
                return [
                    'ok' => false,
                    'message' => 'Belum ada item laundry yang bisa diantar saat ini.',
                ];
            }
        }

        $calc = AntarTarif::tarifFromCoords($cabLat, $cabLon, $locLat, $locLon);
        $tarif = (int) ($calc['tarif'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $existingId = $jenis === 'antar' ? self::findPendingAntar($idPelanggan) : 0;

        try {
            if ($existingId > 0) {
                $set = [
                    'jenis' => $jenis,
                    'sekalian_jemput' => $sekalianJemput,
                    'id_cabang' => $idCabang,
                    'id_lokasi' => $idLokasi,
                    'lokasi_nama' => (string) ($lokasi['nama'] ?? ''),
                    'lokasi_detail' => (string) ($lokasi['detail'] ?? ''),
                    'lokasi_latt' => $locLat,
                    'lokasi_longt' => $locLon,
                    'tarif_surcas' => $tarif,
                    'delivery_status' => 'berjalan',
                ];
                if ($catatanKurir !== '') {
                    $set['catatan_kurir'] = $catatanKurir;
                }
                $ok = $db->update('delivery_request', $set, ['id_request' => $existingId]);
                if ($ok === false) {
                    return ['ok' => false, 'message' => 'Gagal memperbarui permintaan'];
                }
                $idRequest = $existingId;
                self::ensureRequestItems($db, $idRequest, $eligibleIds);
            } else {
                $insData = [
                    'sumber' => 'customer',
                    'jenis' => $jenis,
                    'sekalian_jemput' => $sekalianJemput,
                    'layanan' => 'sameday',
                    'delivery_status' => 'berjalan',
                    'id_pelanggan' => $idPelanggan,
                    'phone_tail' => $phoneTail,
                    'id_cabang' => $idCabang,
                    'id_lokasi' => $idLokasi,
                    'lokasi_nama' => (string) ($lokasi['nama'] ?? ''),
                    'lokasi_detail' => (string) ($lokasi['detail'] ?? ''),
                    'lokasi_latt' => $locLat,
                    'lokasi_longt' => $locLon,
                    'insertTime' => $now,
                    'tarif_surcas' => $tarif,
                ];
                if ($catatanKurir !== '') {
                    $insData['catatan_kurir'] = $catatanKurir;
                }
                $idRequest = $db->insert('delivery_request', $insData);
                $idRequest = $idRequest ? (int) $idRequest : 0;
                if ($idRequest <= 0) {
                    return ['ok' => false, 'message' => 'Gagal membuat permintaan'];
                }
                self::ensureRequestItems($db, $idRequest, $eligibleIds);
            }

            if ($jenis === 'antar' && $tarif > 0 && $eligibleIds !== []) {
                self::tryAttachSurcasPengantaran($db, $idPelanggan, $idCabang, $eligibleIds, $tarif, $idRequest);
            }

            $groupSent = self::notifyDriverGroup(
                $pel,
                $cab,
                $jenis,
                $sekalianJemput,
                $catatanKurir,
                (string) ($lokasi['detail'] ?? ''),
                $existingId > 0
            );

            $label = $sekalianJemput ? 'Antar & Jemput' : ($jenis === 'antar' ? 'Antar' : 'Jemput');
            $listed = self::listAktif($idPelanggan);
            return [
                'ok' => true,
                'message' => "Permintaan {$label} terkirim.",
                'id_request' => $idRequest,
                'jenis' => $jenis,
                'sekalian_jemput' => $sekalianJemput,
                'group_sent' => $groupSent,
                'items' => $listed['items'] ?? [],
            ];
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('DeliveryRequestStore create: ' . $e->getMessage(), 'wa_error', 'DeliveryRequest');
            }
            return ['ok' => false, 'message' => 'Gagal membuat permintaan'];
        }
    }

    /**
     * Request masih aktif: berjalan / menunggu pembayaran.
     * @return array{ok:bool,message?:string,items?:array}
     */
    public static function listAktif(int $idPelanggan): array
    {
        $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
        if ($pel === null) {
            return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
        }

        $rows = PelangganLokasiStore::laundryDb()->query(
            "SELECT id_request, jenis, sekalian_jemput, layanan, delivery_status,
                    id_lokasi, lokasi_nama, lokasi_detail, catatan_kurir, insertTime, tarif_surcas
             FROM delivery_request
             WHERE id_pelanggan = ?
               AND delivery_status IN ('berjalan','menunggu_pembayaran')
             ORDER BY id_request DESC",
            [$idPelanggan]
        )->result_array();

        $items = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $items[] = self::formatAktifItem($r);
        }

        return ['ok' => true, 'items' => $items];
    }

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private static function formatAktifItem(array $r): array
    {
        $jenis = strtolower((string) ($r['jenis'] ?? ''));
        $sekalian = !empty($r['sekalian_jemput']);
        $status = strtolower((string) ($r['delivery_status'] ?? ''));
        $layanan = strtolower((string) ($r['layanan'] ?? 'sameday'));
        $jenisLabel = $sekalian ? 'Antar & Jemput' : ($jenis === 'antar' ? 'Antar' : 'Jemput');
        $statusLabel = $status === 'menunggu_pembayaran' ? 'Menunggu pembayaran' : 'Berjalan';

        return [
            'id_request' => (int) ($r['id_request'] ?? 0),
            'jenis' => $jenis,
            'sekalian_jemput' => $sekalian ? 1 : 0,
            'jenis_label' => $jenisLabel,
            'layanan' => $layanan,
            'delivery_status' => $status,
            'status_label' => $statusLabel,
            'id_lokasi' => (int) ($r['id_lokasi'] ?? 0),
            'lokasi_nama' => (string) ($r['lokasi_nama'] ?? ''),
            'lokasi_detail' => (string) ($r['lokasi_detail'] ?? ''),
            'catatan_kurir' => (string) ($r['catatan_kurir'] ?? ''),
            'insertTime' => (string) ($r['insertTime'] ?? ''),
            'tarif_surcas' => (int) ($r['tarif_surcas'] ?? 0),
        ];
    }

    /**
     * @return array{jenis:string,sekalian_jemput:int}|null
     */
    private static function parseJenis(string $jenis): ?array
    {
        $jenis = strtolower(trim($jenis));
        if ($jenis === 'jemput') {
            return ['jenis' => 'jemput', 'sekalian_jemput' => 0];
        }
        if ($jenis === 'antar') {
            return ['jenis' => 'antar', 'sekalian_jemput' => 0];
        }
        if (in_array($jenis, ['antar_jemput', 'antar-jemput', 'antar & jemput', 'jemput_antar'], true)) {
            return ['jenis' => 'antar', 'sekalian_jemput' => 1];
        }
        return null;
    }

    private static function phoneTail(array $pel, string $waNumber): string
    {
        $fromPel = WaSenderContext::key((string) ($pel['nomor_pelanggan'] ?? ''));
        if (strlen($fromPel) >= 8) {
            return $fromPel;
        }
        return WaSenderContext::key($waNumber);
    }

    private static function findPendingAntar(int $idPelanggan): int
    {
        $row = PelangganLokasiStore::laundryDb()->query(
            "SELECT id_request FROM delivery_request
             WHERE id_pelanggan = ?
               AND jenis = 'antar'
               AND delivery_status = 'pending'
               AND layanan = 'sameday'
             ORDER BY id_request DESC
             LIMIT 1",
            [$idPelanggan]
        )->row_array();

        return (int) ($row['id_request'] ?? 0);
    }

    /**
     * @return int[]
     */
    private static function eligibleSaleIds(int $idPelanggan): array
    {
        $rows = PelangganLokasiStore::laundryDb()->query(
            "SELECT s.id_penjualan
             FROM sale s
             WHERE s.bin = 0 AND s.id_pelanggan = ?
               AND (
                 s.tuntas = 0
                 OR (s.tuntas = 1 AND s.tuntasTime IS NOT NULL AND s.tuntasTime >= (NOW() - INTERVAL 2 DAY))
               )
               AND NOT EXISTS (
                 SELECT 1 FROM delivery_riwayat dr
                 WHERE dr.id_penjualan = s.id_penjualan AND dr.jenis = 'antar'
               )
               AND NOT EXISTS (
                 SELECT 1 FROM delivery_request_item dri
                 INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
                 WHERE dri.id_penjualan = s.id_penjualan
                   AND drq.jenis = 'antar'
                   AND drq.delivery_status IN ('berjalan','menunggu_pembayaran')
               )
             ORDER BY s.insertTime DESC
             LIMIT 50",
            [$idPelanggan]
        )->result_array();

        $ids = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $id = (int) ($r['id_penjualan'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * @param int[] $eligibleIds
     */
    private static function ensureRequestItems(DB $db, int $idRequest, array $eligibleIds): void
    {
        if ($idRequest <= 0 || $eligibleIds === []) {
            return;
        }
        $existing = $db->query(
            'SELECT id_penjualan FROM delivery_request_item WHERE id_request = ?',
            [$idRequest]
        )->result_array();
        $have = [];
        foreach (is_array($existing) ? $existing : [] as $r) {
            $sid = (int) ($r['id_penjualan'] ?? 0);
            if ($sid > 0) {
                $have[$sid] = true;
            }
        }
        foreach ($eligibleIds as $idSale) {
            $idSale = (int) $idSale;
            if ($idSale <= 0 || isset($have[$idSale])) {
                continue;
            }
            $sale = $db->query(
                'SELECT no_ref FROM sale WHERE id_penjualan = ? LIMIT 1',
                [$idSale]
            )->row_array();
            $db->insert('delivery_request_item', [
                'id_request' => $idRequest,
                'id_penjualan' => $idSale,
                'no_ref' => (string) ($sale['no_ref'] ?? ''),
            ]);
        }
    }

    /**
     * @param int[] $eligibleIds
     */
    private static function tryAttachSurcasPengantaran(
        DB $db,
        int $idPelanggan,
        int $idCabang,
        array $eligibleIds,
        int $tarif,
        int $idRequest
    ): void {
        if ($idPelanggan <= 0 || $idCabang <= 0 || $tarif <= 0 || $eligibleIds === []) {
            return;
        }
        $safeIds = [];
        foreach ($eligibleIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $safeIds[$id] = $id;
            }
        }
        if ($safeIds === []) {
            return;
        }
        $idsIn = implode(',', array_values($safeIds));
        $rows = $db->query(
            "SELECT no_ref, MIN(id_penjualan) AS min_id
             FROM sale
             WHERE id_pelanggan = ?
               AND bin = 0
               AND tuntas = 0
               AND id_user_ambil = 0
               AND id_penjualan IN ($idsIn)
               AND no_ref IS NOT NULL
               AND TRIM(no_ref) <> ''
             GROUP BY no_ref
             ORDER BY min_id ASC
             LIMIT 1",
            [$idPelanggan]
        )->result_array();
        $noRef = trim((string) ($rows[0]['no_ref'] ?? ''));
        if ($noRef === '') {
            return;
        }

        $jenis = AntarTarif::SURCAS_JENIS_PENGANTARAN;
        $existing = $db->query(
            'SELECT id_surcas FROM surcas
             WHERE id_cabang = ?
               AND transaksi_jenis = 1
               AND id_jenis_surcas = ?
               AND no_ref = ?
             LIMIT 1',
            [$idCabang, $jenis, $noRef]
        )->row_array();
        if (is_array($existing) && !empty($existing['id_surcas'])) {
            return;
        }

        $row = [
            'id_cabang' => $idCabang,
            'transaksi_jenis' => 1,
            'id_jenis_surcas' => $jenis,
            'jumlah' => $tarif,
            'id_user' => 0,
            'no_ref' => is_numeric($noRef) ? (0 + $noRef) : $noRef,
            'dari_delivery' => 1,
            'id_delivery_request' => $idRequest,
        ];
        $db->insert('surcas', $row);
    }

    /**
     * @return array{ok:bool,value:string,message:string}
     */
    private static function normalizeCatatanKurir($raw): array
    {
        $val = trim((string) $raw);
        $val = preg_replace("/[\r\n]+/", ' ', $val);
        $val = preg_replace('/\s+/u', ' ', (string) $val);
        $val = trim((string) $val);
        $len = function_exists('mb_strlen') ? mb_strlen($val, 'UTF-8') : strlen($val);
        if ($len > 150) {
            return [
                'ok' => false,
                'value' => '',
                'message' => 'Catatan maksimal 150 karakter',
            ];
        }

        return ['ok' => true, 'value' => $val, 'message' => ''];
    }

    private static function notifyDriverGroup(
        array $pel,
        array $cab,
        string $jenis,
        int $sekalianJemput,
        string $catatanKurir,
        string $lokasiDetail,
        bool $isUpdate
    ): bool {
        try {
            $nama = mb_strtoupper(trim((string) ($pel['nama_pelanggan'] ?? '')), 'UTF-8');
            if ($nama === '') {
                $nama = 'PELANGGAN';
            }
            $kode = mb_strtoupper(trim((string) ($cab['kode_cabang'] ?? '')), 'UTF-8');
            if ($kode === '') {
                $kode = (string) ((int) ($cab['id_cabang'] ?? 0) ?: '-');
            }
            $jenisUpper = $sekalianJemput ? 'ANTAR -JEMPUT' : ($jenis === 'antar' ? 'ANTAR' : 'JEMPUT');
            $lines = [
                "*{$nama}*",
                "-{$kode} -{$jenisUpper}",
            ];
            $catatanKurir = trim($catatanKurir);
            if ($catatanKurir !== '') {
                $lines[] = $catatanKurir;
            }
            $lines[] = '';
            $lines[] = 'Lokasi:';
            $lokasiDetail = trim($lokasiDetail);
            $lines[] = $lokasiDetail !== '' ? $lokasiDetail : '-';
            if ($isUpdate) {
                $lines[] = '(update)';
            }

            $groupId = FonnteConfig::getDriverGroupId();
            if ($groupId === '') {
                return false;
            }
            $fonnte = new FonnteService();
            $send = $fonnte->sendToGroup($groupId, implode("\n", $lines), ['delay' => '0']);
            return !empty($send['success']);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('DeliveryRequestStore notify: ' . $e->getMessage(), 'wa_error', 'DeliveryRequest');
            }
            return false;
        }
    }
}
