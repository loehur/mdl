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
      $data['ref'] = $this->db(0)->get_where('ref', "step <> 0 ORDER BY id DESC LIMIT 50", 'id');

      $total = [];
      
      if (!empty($data['ref'])) {
         $ids = implode(',', array_keys($data['ref']));
         
         // Query optimasi: Hitung total langsung di database
         $q_total = $this->db(0)->run("
            SELECT ref, SUM((harga * qty) - diskon) as total_belanja 
            FROM pesanan 
            WHERE ref IN ($ids) 
            GROUP BY ref
         ");

         foreach ($q_total as $t) {
            $total[$t['ref']] = $t['total_belanja'];
         }
      }

      $data['total'] = $total;
      // $order tidak perlu diload semua di sini jika hanya butuh total di list
      // Detail order bisa diload via AJAX/klik (cart function)
      $data['order'] = [];

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($ref = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data['menu'] = $_SESSION['resto_menu'];
      $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $this->view($viewData, $data);
   }
}
