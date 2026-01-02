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
      $layout = ['title' => 'Karyawan Absen'];
      $viewData = __CLASS__ . '/form';
      $this->view('layout', $layout);
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
      $hp = $_POST['karyawan'];
      $jenis = $_POST['jenis'];
      $tgl_post = $_POST['tgl'];

      $username = $this->model("Enc")->username($hp);
      $user_absen = $this->data('User')->get_data_user($username);

      $tgl = date('Y-m-d');
      if ($tgl_post == 1) {
         $tgl = date('Y-m-d', strtotime("-1 days"));
      }

      $jam = date('H:i');

      if ($user_absen) {
         $cols = "id_karyawan,jenis,tanggal,jam,id_cabang";
         $vals = $user_absen['id_user'] . "," . $jenis . ",'" . $tgl . "','" . $jam . "'," . $_SESSION[URL::SESSID]['user']['id_cabang'];

         //CEK MAX PER ORANG
         $where_user = "id_karyawan = " . $user_absen['id_user'] . " AND jenis in(0,2) AND tanggal = '" . $tgl . "'";
         $cek_user = $this->db(0)->count_where('absen', $where_user);
         if ($cek_user > 0) {
            $res = [
               'code' => 0,
               'msg' => "GAGAL - MELEBIHI BATAS ABSEN HARIAN"
            ];
            print_r(json_encode($res));
            exit();
         }


         //CEK MAX PER CABANG

         $where = "id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'] . " AND jenis = " . $jenis . " AND tanggal = '" . $tgl . "'";
         $max = $_SESSION[URL::SESSID]['cabang'][$jenis . '_max'];
         $cek = $this->db(0)->count_where('absen', $where);

         if ($cek < $max) {
            $in = $this->db(0)->insertCols('absen', $cols, $vals);
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
         echo "PIN SALAH";
      }
   }
}
