<?php

/**
 * Admin Tools: CRUD lokasi pelanggan (cabang session aktif).
 * Koordinat hanya dari URL Google Maps (parse lokal / maps_server).
 */
class PelangganLokasi extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'Lokasi Pelanggan'];
        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('pelanggan_lokasi/index', [
            'id_cabang' => (int) $this->id_cabang,
            'nama_cabang' => (string) ($this->dCabang['nama'] ?? ''),
        ]);
    }

    /** POST q → JSON list pelanggan cabang aktif */
    public function searchPelanggan()
    {
        $this->session_cek(1);
        $this->jsonHeader();

        $q = trim((string) ($_POST['q'] ?? $_GET['q'] ?? ''));
        $idCabang = (int) $this->id_cabang;
        if ($idCabang <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Cabang session tidak valid', 'items' => []]);
            return;
        }

        $where = 'id_cabang = ' . $idCabang;
        if ($q !== '') {
            $esc = $this->db(0)->escape($q);
            $digits = preg_replace('/\D/', '', $q);
            $parts = [
                "nama_pelanggan LIKE '%{$esc}%'",
                "nomor_pelanggan LIKE '%{$esc}%'",
            ];
            if ($digits !== '') {
                $escD = $this->db(0)->escape($digits);
                $parts[] = "nomor_pelanggan LIKE '%{$escD}%'";
            }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }

        $rows = $this->db(0)->get_where_order('pelanggan', $where, 'nama_pelanggan ASC LIMIT 20');
        if (!is_array($rows)) {
            $rows = [];
        }

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id_pelanggan' => (int) ($r['id_pelanggan'] ?? 0),
                'nama_pelanggan' => (string) ($r['nama_pelanggan'] ?? ''),
                'nomor_pelanggan' => (string) ($r['nomor_pelanggan'] ?? ''),
            ];
        }

        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }

    /** GET/POST id_pelanggan → list lokasi */
    public function listLokasi()
    {
        $this->session_cek(1);
        $this->jsonHeader();

        $idPelanggan = (int) ($_POST['id_pelanggan'] ?? $_GET['id_pelanggan'] ?? 0);
        $pel = $this->requirePelangganCabang($idPelanggan);
        if ($pel === null) {
            return;
        }

        $rows = $this->db(0)->get_where_order(
            'pelanggan_lokasi',
            'id_pelanggan = ' . $idPelanggan,
            'id_lokasi DESC'
        );
        if (!is_array($rows)) {
            $rows = [];
        }

        $items = [];
        foreach ($rows as $r) {
            $latt = (float) ($r['latt'] ?? 0);
            $longt = (float) ($r['longt'] ?? 0);
            $items[] = [
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

        echo json_encode([
            'ok' => true,
            'pelanggan' => [
                'id_pelanggan' => (int) $pel['id_pelanggan'],
                'nama_pelanggan' => (string) ($pel['nama_pelanggan'] ?? ''),
                'nomor_pelanggan' => (string) ($pel['nomor_pelanggan'] ?? ''),
            ],
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE);
    }

    /** POST url → resolve coords */
    public function resolveMaps()
    {
        $this->session_cek(1);
        $this->jsonHeader();
        $this->loadMapsHelper();

        $url = trim((string) ($_POST['url'] ?? $_POST['gmaps_url'] ?? ''));
        $res = MapsServer::resolve($url);
        if (empty($res['ok'])) {
            echo json_encode([
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'Gagal membaca koordinat dari URL'),
                'error' => (string) ($res['error'] ?? 'resolve_failed'),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'latt' => (float) $res['latt'],
            'longt' => (float) $res['longt'],
            'source' => (string) ($res['source'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function insert()
    {
        $this->session_cek(1);
        $this->jsonHeader();
        $this->loadMapsHelper();

        $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
        if ($this->requirePelangganCabang($idPelanggan) === null) {
            return;
        }

        $nama = trim((string) ($_POST['nama'] ?? ''));
        $detail = trim((string) ($_POST['detail'] ?? ''));
        $gmapsUrl = trim((string) ($_POST['gmaps_url'] ?? $_POST['url'] ?? ''));

        $err = $this->validateNamaDetail($nama, $detail);
        if ($err !== null) {
            echo json_encode(['ok' => false, 'message' => $err], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($gmapsUrl === '') {
            echo json_encode(['ok' => false, 'message' => 'URL Google Maps wajib diisi'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $coords = MapsServer::resolve($gmapsUrl);
        if (empty($coords['ok'])) {
            echo json_encode([
                'ok' => false,
                'message' => (string) ($coords['message'] ?? 'Gagal membaca koordinat dari URL'),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
        $ins = $this->db(0)->insert('pelanggan_lokasi', [
            'id_pelanggan' => $idPelanggan,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => (float) $coords['latt'],
            'longt' => (float) $coords['longt'],
            'insertTime' => $now,
        ]);

        if (($ins['errno'] ?? 1) != 0) {
            echo json_encode([
                'ok' => false,
                'message' => (string) ($ins['error'] ?? 'Gagal menyimpan lokasi'),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Lokasi berhasil ditambahkan',
            'id_lokasi' => (int) ($ins['insert_id'] ?? 0),
            'latt' => (float) $coords['latt'],
            'longt' => (float) $coords['longt'],
        ], JSON_UNESCAPED_UNICODE);
    }

    public function update()
    {
        $this->session_cek(1);
        $this->jsonHeader();
        $this->loadMapsHelper();

        $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);
        if ($this->requirePelangganCabang($idPelanggan) === null) {
            return;
        }

        $nama = trim((string) ($_POST['nama'] ?? ''));
        $detail = trim((string) ($_POST['detail'] ?? ''));
        $gmapsUrl = trim((string) ($_POST['gmaps_url'] ?? $_POST['url'] ?? ''));

        // Tolak update koordinat manual tanpa URL
        $postedLatt = $_POST['latt'] ?? null;
        $postedLongt = $_POST['longt'] ?? $_POST['long'] ?? null;
        if ($gmapsUrl === '' && ($postedLatt !== null || $postedLongt !== null)) {
            // Hanya boleh kirim lat/lng jika sama dengan yang sudah tersimpan (preview); jangan terima sebagai sumber kebenaran
            // Jika URL kosong, koordinat tidak diubah — abaikan nilai POST lat/lng
        }

        $err = $this->validateNamaDetail($nama, $detail);
        if ($err !== null) {
            echo json_encode(['ok' => false, 'message' => $err], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($idLokasi <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Lokasi tidak valid'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $row = $this->db(0)->get_where_row(
            'pelanggan_lokasi',
            'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . $idPelanggan
        );
        if (!is_array($row) || empty($row['id_lokasi'])) {
            echo json_encode(['ok' => false, 'message' => 'Lokasi tidak ditemukan'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $set = [
            'nama' => $nama,
            'detail' => $detail,
        ];

        if ($gmapsUrl !== '') {
            $coords = MapsServer::resolve($gmapsUrl);
            if (empty($coords['ok'])) {
                echo json_encode([
                    'ok' => false,
                    'message' => (string) ($coords['message'] ?? 'Gagal membaca koordinat dari URL'),
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            $set['latt'] = (float) $coords['latt'];
            $set['longt'] = (float) $coords['longt'];
        }

        $up = $this->db(0)->update(
            'pelanggan_lokasi',
            $set,
            'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . $idPelanggan
        );

        if (($up['errno'] ?? 1) != 0) {
            echo json_encode([
                'ok' => false,
                'message' => (string) ($up['error'] ?? 'Gagal memperbarui lokasi'),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $fresh = $this->db(0)->get_where_row(
            'pelanggan_lokasi',
            'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . $idPelanggan
        );

        echo json_encode([
            'ok' => true,
            'message' => 'Lokasi berhasil diperbarui',
            'latt' => (float) ($fresh['latt'] ?? $row['latt'] ?? 0),
            'longt' => (float) ($fresh['longt'] ?? $row['longt'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function delete()
    {
        $this->session_cek(1);
        $this->jsonHeader();

        $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);
        if ($this->requirePelangganCabang($idPelanggan) === null) {
            return;
        }
        if ($idLokasi <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Lokasi tidak valid'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $row = $this->db(0)->get_where_row(
            'pelanggan_lokasi',
            'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . $idPelanggan
        );
        if (!is_array($row) || empty($row['id_lokasi'])) {
            echo json_encode(['ok' => false, 'message' => 'Lokasi tidak ditemukan'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $aktif = (int) ($this->db(0)->count_where(
            'delivery_request',
            'id_pelanggan = ' . $idPelanggan
                . ' AND id_lokasi = ' . $idLokasi
                . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
        ) ?? 0);
        if ($aktif > 0) {
            echo json_encode([
                'ok' => false,
                'message' => 'Lokasi tidak bisa dihapus karena masih ada permintaan kurir aktif.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $del = $this->db(0)->delete(
            'pelanggan_lokasi',
            'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . $idPelanggan
        );
        if (($del['errno'] ?? 1) != 0) {
            echo json_encode([
                'ok' => false,
                'message' => (string) ($del['error'] ?? 'Gagal menghapus lokasi'),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['ok' => true, 'message' => 'Lokasi dihapus'], JSON_UNESCAPED_UNICODE);
    }

    private function jsonHeader(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
    }

    private function loadMapsHelper(): void
    {
        if (!class_exists('MapsServer', false)) {
            require_once __DIR__ . '/../Helper/MapsServer.php';
        }
    }

    /**
     * @return array|null pelanggan row, atau null setelah echo error JSON
     */
    private function requirePelangganCabang(int $idPelanggan): ?array
    {
        $idCabang = (int) $this->id_cabang;
        if ($idCabang <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Cabang session tidak valid'], JSON_UNESCAPED_UNICODE);
            return null;
        }
        if ($idPelanggan <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Pilih pelanggan terlebih dahulu'], JSON_UNESCAPED_UNICODE);
            return null;
        }

        $row = $this->db(0)->get_where_row(
            'pelanggan',
            'id_pelanggan = ' . $idPelanggan . ' AND id_cabang = ' . $idCabang
        );
        if (!is_array($row) || empty($row['id_pelanggan'])) {
            echo json_encode([
                'ok' => false,
                'message' => 'Pelanggan tidak ditemukan di cabang aktif',
            ], JSON_UNESCAPED_UNICODE);
            return null;
        }

        return $row;
    }

    private function validateNamaDetail(string $nama, string $detail): ?string
    {
        if ($nama === '') {
            return 'Nama lokasi wajib diisi';
        }
        if (strlen($nama) > 50) {
            return 'Nama lokasi terlalu panjang (maks 50)';
        }
        if ($detail === '') {
            return 'Detail alamat wajib diisi';
        }
        if (strlen($detail) > 255) {
            return 'Detail alamat terlalu panjang (maks 255)';
        }
        return null;
    }
}
