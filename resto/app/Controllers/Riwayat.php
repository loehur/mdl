<?php

class Riwayat extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Riwayat Pesanan'];
      $data['ref'] = $this->db($this->book)->get_where('ref', "step <> 0 ORDER BY id DESC LIMIT 100", 'id');

      $order = [];
      $total = [];
      foreach ($data['ref'] as $key => $r) {
         $order[$key] = $this->db($this->book)->get_where('pesanan', "ref = '" . $key . "'");
         $total[$key] = 0;
         foreach ($order[$key] as $dk) {
            $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
            $total[$key] += $subTotal;
         }
      }

      $data['order'] = $order;
      $data['total'] = $total;

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($ref = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data['menu'] = $_SESSION['resto_menu'];
      $data['order'] = $this->db($this->book)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db($this->book)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $this->view($viewData, $data);
   }
}
