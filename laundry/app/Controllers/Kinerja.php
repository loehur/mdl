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
      $operasi = [];
      $dataTanggal = [];
      $data_main = [];
      $data_terima = [];
      $data_kembali = [];

      $view = "content";
      if ($mode == 1) {
         $data_operasi = ['title' => 'Karyawan - Kinerja Bulanan'];
      } else {
         $data_operasi = ['title' => 'Karyawan - Kinerja Harian'];
      }

      //KINERJA
      if (isset($_POST['m'])) {
         if ($mode == 1) {
            $date = $_POST['Y'] . "-" . $_POST['m'];
            $dataTanggal = array('bulan' => $_POST['m'], 'tahun' => $_POST['Y']);
         } else {
            $date = $_POST['Y'] . "-" . $_POST['m'] . "-" . $_POST['d'];
            $dataTanggal = array('tanggal' => $_POST['d'], 'bulan' => $_POST['m'], 'tahun' => $_POST['Y']);
         }
      } else {
         if ($mode == 1) {
            $date = date('Y-m');
         } else {
            $date = date('Y-m-d');
         }
      }

      //ABSEN
      $absen = $this->db(0)->get_cols_where('absen', 'id_karyawan, SUM(jenis IN (0,2,3)) as harian, SUM(jenis = 1) as malam', "tanggal LIKE '" . $date . "%' GROUP BY id_karyawan", 1, 'id_karyawan');

      //OPERASI
      $where = "insertTime LIKE '" . $date . "%'";
      $ops_data = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('operasi', $where, 'id_operasi');

      //OPERASI JOIN
      $join_where = "operasi.id_penjualan = sale.id_penjualan";
      $where = "sale.bin = 0 AND operasi.insertTime LIKE '" . $date . "%'";
      $data_lain1 = $this->db($_SESSION[URL::SESSID]['user']['book'])->innerJoin1_where('operasi', 'sale', $join_where, $where);
      foreach ($data_lain1 as $dl1) {
         unset($ops_data[$dl1['id_operasi']]);
         array_push($data_main, $dl1);
      }

      if (count($ops_data) > 0) {
         //PENJUALAN TAHUN LALU
         foreach ($ops_data as $od) {
            $where = "id_penjualan = " . $od['id_penjualan'];
            $data_lalu = $this->db($_SESSION[URL::SESSID]['user']['book'] - 1)->get_where_row('sale', $where);
            $new_data = array_merge($data_lalu, $od);
            array_push($data_main, $new_data);
         }
      }

      //PENERIMAAN
      $cols = "id_user, id_cabang, COUNT(id_user) as terima";
      $where = "insertTime LIKE '" . $date . "%' GROUP BY id_user, id_cabang";
      $data_lain2 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('sale', $cols, $where, 1);
      foreach ($data_lain2 as $dl2) {
         array_push($data_terima, $dl2);
      }

      //PENGAMBILAN
      $cols = "id_user_ambil, id_cabang, COUNT(id_user_ambil) as kembali";
      $where = "tgl_ambil LIKE '" . $date . "%' GROUP BY id_user_ambil, id_cabang";
      $data_lain3 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('sale', $cols, $where, 1);
      foreach ($data_lain3 as $dl3) {
         array_push($data_kembali, $dl3);
      }

      $karyawan = $this->db(0)->get_where("user", "en = 1 AND id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang']);

      $this->view('layout', ['data_operasi' => $data_operasi]);

      $this->view('kinerja/' . $view, [
         'mode' => $mode,
         'karyawan' => $karyawan,
         'data_main' => $data_main,
         'operasi' => $operasi,
         'dataTanggal' => $dataTanggal,
         'dTerima' => $data_terima,
         'dKembali' => $data_kembali,
         'absen' => $absen
      ]);
   }
}
