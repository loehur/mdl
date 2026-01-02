<?php

class Riwayat_A extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Riwayat All'];
      $data['ref'] = $this->db(0)->get_where('ref', "step <> 0 AND tgl = '" . date("Y-m-d") . "' ORDER BY id DESC", 'id');

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

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($ref = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data['ref'] = $ref;
      $data['menu'] = $_SESSION[URL::SESSID]['menu'];
      $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $this->view($viewData, $data);
   }

   function hapus($ref)
   {
      $where = "ref = '" . $ref . "'";
      $del = $this->db(0)->delete_where("pesanan", $where);
      if ($del['errno'] == 0) {
         $hitung_menu = $this->db(0)->count_where("pesanan", "ref = '" . $ref . "'");
         if ($hitung_menu == 0) {
            $del = $this->db(0)->delete_where("kas", "ref = '" . $ref . "'");
            if ($del['errno'] == 0) {
               $del = $this->db(0)->delete_where("ref", "id = '" . $ref . "'");
               echo $del['errno'] == 0 ? 0 : $del['error'];
            } else {
               echo $del['error'];
            }
         } else {
            echo 0;
         }
      } else {
         echo $del['error'];
      }
   }
}
