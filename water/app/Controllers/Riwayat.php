<?php

class Riwayat extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index($mode = "=", $day = 0)
   {
      $layout = ['title' => 'Riwayat Pesanan'];
      $id_user = $_SESSION[URL::SESSID]['user']['id_user'];

      if ($day == 0) {
         $tgl = date("Y-m-d");
      } else {
         $tgl = date("Y-m-d", strtotime('-1 day'));
      }
      $data['ref'] = $this->db(0)->get_where('ref', "step <> 0 AND tgl = '" . $tgl . "' AND id_user " . $mode . " " . $id_user . " ORDER BY id DESC", 'id');

      $order = [];
      $total = [];
      foreach ($data['ref'] as $key => $r) {
         $order[$key] = $this->db(0)->get_where('pesanan', "ref = '" . $key . "'");
         $total[$key] = 0;
         foreach ($order[$key] as $dk) {
            $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
            $total[$key] += $subTotal;
         }
      }

      $data['pelanggan'] = $this->db(0)->get_where('pelanggan', "id_cabang = '" . $this->id_cabang . "'", 'id');
      $data['order'] = $order;
      $data['total'] = $total;
      $data['mode'] = $mode;
      $data['day'] = $day;

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($ref = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data['menu'] = $_SESSION[URL::SESSID]['menu'];
      $data['ref'] = $this->db(0)->get_where_row('ref', "id = '" . $ref . "'");
      $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $this->view($viewData, $data);
   }

   function vehicle()
   {
      $ref = $_POST['ref'];
      $v = $_POST['v'];

      $up_ref = $this->db(0)->update('ref', "v = " . $v, "id = '" . $ref . "'");
      if ($up_ref['errno'] == 0) {
         $up_p = $this->db(0)->update('pesanan', "v = " . $v, "ref = '" . $ref . "'");
         if ($up_p['errno'] == 0) {
            echo $v;
         } else {
            echo $up_p['errno'];
         }
      } else {
         echo $up_ref['errno'];
      }
   }
}
