<?php

class Pindah_Outlet extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $data_operasi = ['title' => 'Karyawan Pindah Outlet'];
      $viewData = __CLASS__ . '/form';
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData);
   }

   public function load()
   {
      $viewData = __CLASS__ . '/content';
      $data = $this->db(0)->get_where('user', 'id_cabang = ' . $_SESSION[URL::SESSID]['user']['id_cabang'] . " AND en = 1");
      $this->view($viewData, $data);
   }

   function pindah()
   {
      $hp = $_POST['karyawan'];
      $pin = $_POST['pin'];

      $username = $this->model("Enc")->username($hp);
      $otp = $this->model("Enc")->otp($pin);
      $user_pindah = $this->helper('User')->pin_today($username, $otp);

      if (!$user_pindah) {
         $cek_admin = $this->helper('User')->pin_admin_today($otp);
         if ($cek_admin > 0) {
            $user_pindah = $this->helper('User')->get_data_user($username);
         }
      }

      if ($user_pindah) {
         $up = $this->db(0)->update("user", "id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'], "id_user = " . $user_pindah['id_user']);
         if ($up['errno'] == 0) {
            $res = [
               'code' => 1,
               'msg' => "PINDAH OUTLET SUKSES"
            ];
            print_r(json_encode($res));
         } else {
            $res = [
               'code' => 0,
               'msg' => $up['error']
            ];
            print_r(json_encode($res));
         }
      } else {
         echo "PIN SALAH";
      }
   }
}
