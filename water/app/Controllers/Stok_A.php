<?php

class Stok_A extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Sales All'];
      $data['tgl'] = [];
      for ($i = 0; $i >= -6; $i--) {
         $sales = 0;
         $tgl = date('Ymd', strtotime($i . ' days', strtotime(date('Y-m-d'))));
         array_push($data['tgl'], $tgl);
         $tgl_pesan = date('Y-m-d', strtotime($i . ' days', strtotime(date('Y-m-d'))));
         $terjual = $this->db(0)->get_cols_where('pesanan', 'id_menu, SUM(qty) as qty', "insertTime LIKE '" . $tgl_pesan . "%' GROUP BY id_menu", 1, 'id_menu'); //sale

         foreach ($terjual as $d) {
            $sales += $d['qty'];
         }

         $data['qty'][$tgl]['t'] = $sales;
      }
      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }
}
