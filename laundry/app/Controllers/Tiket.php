<?php

/**
 * Modul Tiket — lintas cabang (tanpa filter wCabang).
 * status: 0 = proses, 1 = selesai
 * Pembuat / penyelesai: karyawan + Access Key (bukan user login).
 */
class Tiket extends Controller
{
    private $jenisList = ['Perbaikan', 'Pergantian', 'Perawatan', 'Penambahan'];

    public function __construct()
    {
        $this->operating_data();
    }

    public function i($mode = 0)
    {
        $this->session_cek();
        $mode = (int) $mode;
        if ($mode !== 0 && $mode !== 1) {
            $mode = 0;
        }

        $data_operasi = [
            'title' => $mode === 1 ? 'Tiket Selesai' : 'Tiket Proses',
        ];

        $viewData = $this->buildViewData($mode);

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('tiket/form', $viewData);
    }

    public function load($mode = 0)
    {
        $this->session_cek();
        $mode = (int) $mode;
        if ($mode !== 0 && $mode !== 1) {
            $mode = 0;
        }
        $this->view('tiket/view_load', $this->buildViewData($mode));
    }

    public function insert()
    {
        $this->session_cek();

        $judul = trim((string) ($_POST['judul'] ?? ''));
        $jenis = trim((string) ($_POST['jenis'] ?? ''));
        $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
        $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);
        $accessKey = trim((string) ($_POST['access_key'] ?? ''));

        if ($judul === '') {
            echo 'Judul wajib diisi';
            return;
        }
        if ($idKaryawan < 1) {
            echo 'Pilih karyawan pembuat';
            return;
        }
        if (!in_array($jenis, $this->jenisList, true)) {
            echo 'Jenis tiket tidak valid';
            return;
        }

        $karyawan = $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
        if (!$karyawan) {
            echo 'Access Key tidak cocok dengan karyawan yang dipilih';
            return;
        }

        $idCabang = (int) $this->id_cabang;
        if ($idCabang <= 0) {
            echo 'Sesi cabang tidak valid';
            return;
        }

        $namaKaryawan = strtoupper((string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan)));
        $data = [
            'id_cabang' => $idCabang,
            'id_karyawan' => $idKaryawan,
            'judul' => $judul,
            'jenis' => $jenis,
            'keterangan' => $keterangan,
            'karyawan' => $namaKaryawan,
            'status' => 0,
            'insertTime' => $GLOBALS['now'],
        ];

        $in = $this->db(0)->insert('tiket', $data);
        if ((int) ($in['errno'] ?? 1) === 0) {
            echo 0;
        } else {
            echo $in['error'] ?? 'Insert gagal';
        }
    }

    public function update()
    {
        $this->session_cek();

        $id = (int) ($_POST['id_tiket'] ?? 0);
        if ($id <= 0) {
            echo 'ID tiket tidak valid';
            return;
        }

        $row = $this->db(0)->get_where_row('tiket', 'id_tiket = ' . $id);
        if (!$row || empty($row['id_tiket'])) {
            echo 'Tiket tidak ditemukan';
            return;
        }
        if ((int) $row['status'] !== 0) {
            echo 'Tiket selesai tidak dapat diubah';
            return;
        }

        $judul = trim((string) ($_POST['judul'] ?? ''));
        $jenis = trim((string) ($_POST['jenis'] ?? ''));
        $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
        $accessKey = trim((string) ($_POST['access_key'] ?? ''));
        $idKaryawan = (int) ($row['id_karyawan'] ?? 0);

        if ($judul === '') {
            echo 'Judul wajib diisi';
            return;
        }
        if ($idKaryawan < 1) {
            echo 'Data pembuat tiket tidak valid';
            return;
        }
        if (!in_array($jenis, $this->jenisList, true)) {
            echo 'Jenis tiket tidak valid';
            return;
        }

        // Access Key wajib milik pembuat asli (tidak bisa diganti)
        if (!$this->helper('User')->by_id_access_key($idKaryawan, $accessKey)) {
            echo 'Access Key tidak cocok dengan pembuat tiket';
            return;
        }

        $set = [
            'judul' => $judul,
            'jenis' => $jenis,
            'keterangan' => $keterangan,
        ];
        $up = $this->db(0)->update('tiket', $set, 'id_tiket = ' . $id);
        if ((int) ($up['errno'] ?? 1) === 0) {
            echo 0;
        } else {
            echo $up['error'] ?? 'Update gagal';
        }
    }

    public function delete()
    {
        $this->session_cek();

        $id = (int) ($_POST['id_tiket'] ?? 0);
        $accessKey = trim((string) ($_POST['access_key'] ?? ''));
        if ($id <= 0) {
            echo 'ID tiket tidak valid';
            return;
        }

        $row = $this->db(0)->get_where_row('tiket', 'id_tiket = ' . $id);
        if (!$row || empty($row['id_tiket'])) {
            echo 'Tiket tidak ditemukan';
            return;
        }
        if ((int) $row['status'] !== 0) {
            echo 'Tiket selesai tidak dapat dihapus';
            return;
        }

        $idKaryawan = (int) ($row['id_karyawan'] ?? 0);
        if ($idKaryawan < 1) {
            echo 'Data pembuat tiket tidak valid';
            return;
        }
        if (!$this->helper('User')->by_id_access_key($idKaryawan, $accessKey)) {
            echo 'Access Key tidak cocok dengan pembuat tiket';
            return;
        }

        $del = $this->db(0)->delete('tiket', 'id_tiket = ' . $id);
        if ((int) ($del['errno'] ?? 1) === 0) {
            echo 0;
        } else {
            echo $del['error'] ?? 'Hapus gagal';
        }
    }

    public function selesai()
    {
        $this->session_cek();

        $id = (int) ($_POST['id_tiket'] ?? 0);
        $catatan = trim((string) ($_POST['catatan_selesai'] ?? ''));
        $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);
        $accessKey = trim((string) ($_POST['access_key'] ?? ''));

        if ($id <= 0) {
            echo 'ID tiket tidak valid';
            return;
        }
        if ($catatan === '') {
            echo 'Catatan Selesai wajib diisi';
            return;
        }
        if ($idKaryawan < 1) {
            echo 'Pilih karyawan yang menyelesaikan';
            return;
        }

        $karyawan = $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
        if (!$karyawan) {
            echo 'Access Key tidak cocok dengan karyawan yang dipilih';
            return;
        }

        $row = $this->db(0)->get_where_row('tiket', 'id_tiket = ' . $id);
        if (!$row || empty($row['id_tiket'])) {
            echo 'Tiket tidak ditemukan';
            return;
        }
        if ((int) $row['status'] !== 0) {
            echo 'Tiket sudah selesai';
            return;
        }

        $namaKaryawan = strtoupper((string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan)));
        $set = [
            'status' => 1,
            'catatan_selesai' => $catatan,
            'karyawan_selesai' => $namaKaryawan,
            'id_karyawan_selesai' => $idKaryawan,
            'selesaiTime' => $GLOBALS['now'],
        ];
        $up = $this->db(0)->update('tiket', $set, 'id_tiket = ' . $id . ' AND status = 0');
        if ((int) ($up['errno'] ?? 1) === 0) {
            echo 0;
        } else {
            echo $up['error'] ?? 'Gagal menandai selesai';
        }
    }

    private function buildViewData($mode)
    {
        $mode = (int) $mode;
        $cabangMap = $this->buildCabangMap();

        if ($mode === 1) {
            $idCabang = (int) $this->id_cabang;
            $whereSelesai = 'status = 1 AND id_cabang = ' . $idCabang . ' ORDER BY selesaiTime DESC, id_tiket DESC';
            $rows = $this->db(0)->get_where('tiket', $whereSelesai);
            $grouped = $this->groupByMonth($rows);
            return [
                'mode' => 1,
                'rows' => $rows,
                'grouped' => $grouped,
                'cabangMap' => $cabangMap,
                'jenisList' => $this->jenisList,
            ];
        }

        $rows = $this->db(0)->get_where('tiket', 'status = 0 ORDER BY insertTime DESC, id_tiket DESC');
        return [
            'mode' => 0,
            'rows' => $rows,
            'grouped' => [],
            'cabangMap' => $cabangMap,
            'jenisList' => $this->jenisList,
        ];
    }

    private function buildCabangMap()
    {
        $map = [];
        $all = $this->db(0)->get('cabang', 'id_cabang');
        if (is_array($all)) {
            foreach ($all as $id => $c) {
                if (!is_array($c)) {
                    continue;
                }
                $map[(int) ($c['id_cabang'] ?? $id)] = strtoupper((string) ($c['kode_cabang'] ?? ''));
            }
        }
        return $map;
    }

    private function groupByMonth($rows)
    {
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $grouped = [];
        if (!is_array($rows)) {
            return $grouped;
        }
        foreach ($rows as $row) {
            $ts = $row['selesaiTime'] ?? '';
            $ym = '0000-00';
            $label = 'Tanpa Tanggal';
            if ($ts && $ts !== '0000-00-00 00:00:00') {
                $t = strtotime($ts);
                if ($t) {
                    $ym = date('Y-m', $t);
                    $label = ($bulanIndo[(int) date('n', $t)] ?? date('F', $t)) . ' ' . date('Y', $t);
                }
            }
            if (!isset($grouped[$ym])) {
                $grouped[$ym] = ['label' => $label, 'items' => []];
            }
            $grouped[$ym]['items'][] = $row;
        }
        return $grouped;
    }
}
