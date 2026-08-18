<?php

/**
 * CRUD lokasi pelanggan (cabang session aktif) — akses kasir & admin.
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
        $this->session_cek();
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
        $this->session_cek();
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
                $this->helper('PelangganByPhone');
                $nomor = PelangganByPhone::toNomorNasional($digits) ?? $digits;
                $escD = $this->db(0)->escape($nomor);
                $parts[] = PelangganByPhone::likeSql($escD);
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
        $this->session_cek();
        $this->jsonHeader();

        $idPelanggan = (int) ($_POST['id_pelanggan'] ?? $_GET['id_pelanggan'] ?? 0);
        $pel = $this->requirePelangganCabang($idPelanggan);
        if ($pel === null) {
            return;
        }

        $this->helper('PelangganLokasiApi');
        $res = PelangganLokasiApi::list($idPelanggan);
        if (empty($res['ok'])) {
            echo json_encode([
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'Gagal memuat lokasi'),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        $res['pelanggan'] = [
            'id_pelanggan' => (int) $pel['id_pelanggan'],
            'nama_pelanggan' => (string) ($pel['nama_pelanggan'] ?? ''),
            'nomor_pelanggan' => (string) ($pel['nomor_pelanggan'] ?? ''),
        ];
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    /** POST url → resolve coords */
    public function resolveMaps()
    {
        $this->session_cek();
        $this->jsonHeader();

        $url = trim((string) ($_POST['url'] ?? $_POST['gmaps_url'] ?? ''));
        $this->helper('PelangganLokasiApi');
        echo json_encode(PelangganLokasiApi::resolveMaps($url), JSON_UNESCAPED_UNICODE);
    }

    public function insert()
    {
        $this->session_cek();
        $this->jsonHeader();

        $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
        if ($this->requirePelangganCabang($idPelanggan) === null) {
            return;
        }

        $this->helper('PelangganLokasiApi');
        echo json_encode(PelangganLokasiApi::add([
            'id_pelanggan' => $idPelanggan,
            'nama' => trim((string) ($_POST['nama'] ?? '')),
            'detail' => trim((string) ($_POST['detail'] ?? '')),
            'gmaps_url' => trim((string) ($_POST['gmaps_url'] ?? $_POST['url'] ?? '')),
        ]), JSON_UNESCAPED_UNICODE);
    }

    public function update()
    {
        $this->session_cek();
        $this->jsonHeader();

        $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);
        if ($this->requirePelangganCabang($idPelanggan) === null) {
            return;
        }

        $this->helper('PelangganLokasiApi');
        echo json_encode(PelangganLokasiApi::update([
            'id_pelanggan' => $idPelanggan,
            'id_lokasi' => $idLokasi,
            'nama' => trim((string) ($_POST['nama'] ?? '')),
            'detail' => trim((string) ($_POST['detail'] ?? '')),
            'gmaps_url' => trim((string) ($_POST['gmaps_url'] ?? $_POST['url'] ?? '')),
        ]), JSON_UNESCAPED_UNICODE);
    }

    public function delete()
    {
        $this->session_cek();
        $this->jsonHeader();

        $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);
        if ($this->requirePelangganCabang($idPelanggan) === null) {
            return;
        }

        $this->helper('PelangganLokasiApi');
        echo json_encode(PelangganLokasiApi::delete($idPelanggan, $idLokasi), JSON_UNESCAPED_UNICODE);
    }

    private function jsonHeader(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
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
}
