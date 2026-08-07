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

    public function i($mode = 0, $bulan = '')
    {
        $this->session_cek();
        $mode = (int) $mode;
        if ($mode !== 0 && $mode !== 1) {
            $mode = 0;
        }

        $data_operasi = [
            'title' => $mode === 1 ? 'Tiket Selesai' : 'Tiket Proses',
        ];

        $viewData = $this->buildViewData($mode, $bulan);

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('tiket/form', $viewData);
    }

    public function load($mode = 0, $bulan = '')
    {
        $this->session_cek();
        $mode = (int) $mode;
        if ($mode !== 0 && $mode !== 1) {
            $mode = 0;
        }
        $this->view('tiket/view_load', $this->buildViewData($mode, $bulan));
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

        if ($judul === '') {
            echo 'Judul wajib diisi';
            return;
        }
        if (!in_array($jenis, $this->jenisList, true)) {
            echo 'Jenis tiket tidak valid';
            return;
        }

        $pembuat = $this->verifyPembuatAccessKey($row, $accessKey);
        if (!$pembuat) {
            echo 'Access Key tidak cocok dengan pembuat tiket';
            return;
        }

        $set = [
            'judul' => $judul,
            'jenis' => $jenis,
            'keterangan' => $keterangan,
        ];
        // Backfill id_karyawan bila tiket lama masih 0/null
        if ((int) ($row['id_karyawan'] ?? 0) < 1 && !empty($pembuat['id_user'])) {
            $set['id_karyawan'] = (int) $pembuat['id_user'];
            $set['karyawan'] = strtoupper((string) ($pembuat['nama_user'] ?? ($row['karyawan'] ?? '')));
        }
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

        if (!$this->verifyPembuatAccessKey($row, $accessKey)) {
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

    /**
     * Verifikasi Access Key milik pembuat tiket.
     * Fallback: jika id_karyawan kosong (data lama), cocokkan nama + access_key.
     */
    private function verifyPembuatAccessKey(array $row, string $accessKey)
    {
        $idKaryawan = (int) ($row['id_karyawan'] ?? 0);
        if ($idKaryawan > 0) {
            return $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
        }

        $accessKey = trim($accessKey);
        if (!preg_match('/^\d{4}$/', $accessKey)) {
            return null;
        }
        $nama = strtoupper(trim((string) ($row['karyawan'] ?? '')));
        if ($nama === '') {
            return null;
        }
        $namaEsc = $this->db(0)->escape($nama);
        $keyEsc = $this->db(0)->escape($accessKey);
        $found = $this->db(0)->get_where_row(
            'user',
            "UPPER(TRIM(nama_user)) = '" . $namaEsc . "' AND access_key = '" . $keyEsc . "' AND en = 1"
        );
        if (!is_array($found) || empty($found['id_user'])) {
            return null;
        }
        return $found;
    }

    private function buildViewData($mode, $bulan = '')
    {
        $mode = (int) $mode;
        $cabangMap = $this->buildCabangMap();

        if ($mode === 1) {
            $idCabang = (int) $this->id_cabang;
            $ym = $this->normalizeBulan($bulan);
            $start = $ym . '-01 00:00:00';
            $endTs = strtotime($ym . '-01 +1 month');
            $end = date('Y-m-d', $endTs) . ' 00:00:00';
            $startEsc = $this->db(0)->escape($start);
            $endEsc = $this->db(0)->escape($end);

            $whereSelesai = 'status = 1 AND id_cabang = ' . $idCabang
                . " AND selesaiTime >= '" . $startEsc . "' AND selesaiTime < '" . $endEsc . "'"
                . ' ORDER BY selesaiTime DESC, id_tiket DESC';
            $rows = $this->db(0)->get_where('tiket', $whereSelesai);

            return [
                'mode' => 1,
                'rows' => $rows,
                'grouped' => [],
                'cabangMap' => $cabangMap,
                'jenisList' => $this->jenisList,
                'selectedBulan' => $ym,
                'bulanLabel' => $this->labelBulan($ym),
                'canNextBulan' => ($ym < date('Y-m')),
            ];
        }

        $rows = $this->db(0)->get_where('tiket', 'status = 0 ORDER BY insertTime DESC, id_tiket DESC');
        return [
            'mode' => 0,
            'rows' => $rows,
            'grouped' => [],
            'cabangMap' => $cabangMap,
            'jenisList' => $this->jenisList,
            'selectedBulan' => '',
            'bulanLabel' => '',
            'canNextBulan' => false,
        ];
    }

    /** @return string Y-m */
    private function normalizeBulan($bulan)
    {
        $bulan = trim((string) $bulan);
        if (preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $y = (int) substr($bulan, 0, 4);
            $m = (int) substr($bulan, 5, 2);
            if ($y >= 2020 && $m >= 1 && $m <= 12) {
                $ym = sprintf('%04d-%02d', $y, $m);
                $nowYm = date('Y-m');
                return ($ym > $nowYm) ? $nowYm : $ym;
            }
        }
        return date('Y-m');
    }

    private function labelBulan($ym)
    {
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $t = strtotime($ym . '-01');
        if (!$t) {
            return $ym;
        }
        $n = (int) date('n', $t);
        return ($bulanIndo[$n] ?? date('F', $t)) . ' ' . date('Y', $t);
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
}