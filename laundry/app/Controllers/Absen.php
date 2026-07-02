<?php

class Absen extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $data_operasi = ['title' => 'Karyawan Absen'];
      $viewData = __CLASS__ . '/form';
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData);
   }

   public function load()
   {
      $viewData = __CLASS__ . '/content';
      $tgl = date('Y-m-d');
      $data['hari_ini'] = $this->db(0)->get_where('absen', 'id_cabang = ' . $_SESSION[URL::SESSID]['user']['id_cabang'] . " AND tanggal LIKE '" . $tgl . "%'");

      $tgl_kemarin = date('Y-m-d', strtotime("-1 day"));
      $data['kemarin'] = $this->db(0)->get_where('absen', 'id_cabang = ' . $_SESSION[URL::SESSID]['user']['id_cabang'] . " AND tanggal LIKE '" . $tgl_kemarin . "%'");

      $this->view($viewData, $data);
   }

   function absen()
   {
      $id_karyawan = (int) $_POST['karyawan'];
      $jenis = (int) $_POST['jenis'];
      $tgl_post = $_POST['tgl'];

      $user_absen = $this->db(0)->get_where_row('user', "id_user = " . $id_karyawan . " AND en = 1");

      $tgl = date('Y-m-d');
      if ($tgl_post == 1) {
         $tgl = date('Y-m-d', strtotime("-1 days"));
      }

      $jam = date('H:i');

      if ($user_absen) {
         $cols = "id_karyawan,jenis,tanggal,jam,id_cabang";
         $vals = $user_absen['id_user'] . "," . $jenis . ",'" . $tgl . "','" . $jam . "'," . $_SESSION[URL::SESSID]['user']['id_cabang'];


         if (!in_array($jenis, [0, 1, 2, 3], true)) {
            $res = [
               'code' => 0,
               'msg' => "GAGAL - JENIS TUGAS TIDAK VALID"
            ];
            print_r(json_encode($res));
            exit();
         }

         // Grup absen per karyawan per hari (lintas cabang):
         // - jenis 1 (Jaga Malam): maks 1x per hari
         // - jenis 0, 2, 3 (Cuci/Delivery/Maintenance): maks 1x gabungan per hari
         if ($jenis === 1) {
            $where_user = "id_karyawan = " . $user_absen['id_user'] . " AND jenis = 1 AND tanggal = '" . $tgl . "'";
            $msg_duplikat = "GAGAL - SUDAH ABSEN JAGA MALAM PADA TANGGAL TERSEBUT";
         } else {
            $where_user = "id_karyawan = " . $user_absen['id_user'] . " AND jenis IN (0, 2, 3) AND tanggal = '" . $tgl . "'";
            $msg_duplikat = "GAGAL - SUDAH ABSEN CUCI/DELIVERY/MAINTENANCE PADA TANGGAL TERSEBUT";
         }

         $cek_user = $this->db(0)->count_where('absen', $where_user);
         if ($cek_user > 0) {
            $res = [
               'code' => 0,
               'msg' => $msg_duplikat
            ];
            print_r(json_encode($res));
            exit();
         }

         //CEK MAX PER CABANG
         if ($jenis == 0) {
            $where = "id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'] . " AND jenis = " . $jenis . " AND tanggal = '" . $tgl . "'";
            $max = $_SESSION[URL::SESSID]['data']['cabang'][$jenis . '_max'];
         } else if ($jenis == 1) {
            $where = "id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'] . " AND jenis = " . $jenis . " AND tanggal = '" . $tgl . "'";
            $max = $_SESSION[URL::SESSID]['data']['cabang'][$jenis . '_max'];
         } else if ($jenis == 2 || $jenis == 3) {
            $where = "jenis = " . $jenis . " AND tanggal = '" . $tgl . "'";
            $max = 1;
         }
         $cek = $this->db(0)->count_where('absen', $where);

         if ($cek < $max) {
            $data = [
               'id_karyawan' => $user_absen['id_user'],
               'jenis' => $jenis,
               'tanggal' => $tgl,
               'jam' => $jam,
               'id_cabang' => $_SESSION[URL::SESSID]['user']['id_cabang']
            ];
            $in = $this->db(0)->insert('absen', $data);
            if ($in['errno'] == 0) {
               $res = [
                  'code' => 1,
                  'msg' => "ABSEN SUKSES"
               ];
               print_r(json_encode($res));
            } else {
               $res = [
                  'code' => 0,
                  'msg' => $in['error']
               ];
               print_r(json_encode($res));
            }
         } else {
            $res = [
               'code' => 0,
               'msg' => "GAGAL - MELEBIHI BATAS ABSEN HARIAN"
            ];
            print_r(json_encode($res));
         }
      } else {
         $res = [
            'code' => 0,
            'msg' => "GAGAL - KARYAWAN TIDAK DITEMUKAN"
         ];
         print_r(json_encode($res));
      }
   }
}
