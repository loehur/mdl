<?php

class Kinerja extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index($mode = 1)
   {
      $dataTanggal = [];
      $mode = (int)$mode; // Sanitasi mode

      $data_operasi = ['title' => $mode == 1 ? 'Karyawan - Kinerja Bulanan' : 'Karyawan - Kinerja Harian'];

      // Sanitasi dan validasi input tanggal
      if (isset($_POST['m'])) {
         $year = isset($_POST['Y']) ? preg_replace('/[^0-9]/', '', $_POST['Y']) : date('Y');
         $month = preg_replace('/[^0-9]/', '', $_POST['m']);
         
         if ($mode == 1) {
            $date = $year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT);
            $dataTanggal = ['bulan' => $month, 'tahun' => $year];
         } else {
            $day = isset($_POST['d']) ? preg_replace('/[^0-9]/', '', $_POST['d']) : '01';
            $date = $year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $dataTanggal = ['tanggal' => $day, 'bulan' => $month, 'tahun' => $year];
         }
      } else {
         $date = $mode == 1 ? date('Y-m') : date('Y-m-d');
      }

      // Escape date untuk query
      $escapedDate = addslashes($date);

      // ABSEN - dengan date yang sudah di-escape
      $absen = $this->db(0)->get_cols_where(
         'absen',
         'id_karyawan, SUM(jenis = 0) as cuci, SUM(jenis IN (2,3)) as harian, SUM(jenis = 1) as malam',
         "tanggal LIKE '{$escapedDate}%' GROUP BY id_karyawan",
         1,
         'id_karyawan'
      );

      // OPERASI JOIN - query $ops_data dihapus karena tidak terpakai
      $join_where = "operasi.id_penjualan = sale.id_penjualan";
      $where = "sale.bin = 0 AND operasi.insertTime LIKE '{$escapedDate}%'";
      $data_main = $this->db(0)->innerJoin1_where('operasi', 'sale', $join_where, $where);

      // PENERIMAAN
      $data_terima = $this->db(0)->get_cols_where(
         'sale',
         'id_user, id_cabang, COUNT(id_user) as terima',
         "insertTime LIKE '{$escapedDate}%' GROUP BY id_user, id_cabang",
         1
      );

      // PENGAMBILAN
      $data_kembali = $this->db(0)->get_cols_where(
         'sale',
         'id_user_ambil, id_cabang, COUNT(id_user_ambil) as kembali',
         "tgl_ambil LIKE '{$escapedDate}%' GROUP BY id_user_ambil, id_cabang",
         1
      );

      // Sanitasi id_cabang dari session
      $id_cabang = (int)($_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0);
      $karyawan = $this->db(0)->get_where("user", "en = 1 AND id_cabang = {$id_cabang}");

      $this->view('layout', ['data_operasi' => $data_operasi]);

      $this->view('kinerja/content', [
         'mode' => $mode,
         'karyawan' => $karyawan,
         'data_main' => $data_main,
         'dataTanggal' => $dataTanggal,
         'dTerima' => $data_terima,
         'dKembali' => $data_kembali,
         'absen' => $absen
      ]);
   }
}
