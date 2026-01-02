<?php

class Galon extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Data Galon'];

      $data['pelanggan'] = $this->db(0)->get_where('pelanggan', "id_cabang = '" . $this->id_cabang . "' AND titip <> 0 ORDER BY last_order ASC");

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($ref = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data['menu'] = $_SESSION[URL::SESSID]['menu'];
      $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $this->view($viewData, $data);
   }

   function ambilGalon()
   {
      $id_pelanggan = $_POST['id_pelanggan'];

      $up = $this->db(0)->update('pelanggan', 'titip = 0', "id = '" . $id_pelanggan . "'");
      if ($up['errno'] != 0) {
         $response = [
            'status' => false,
            'message' => 'Gagal mengambil galon: ' . $up['error']
         ];
         echo json_encode($response, JSON_PRETTY_PRINT);
         return;
      }
      $response = [
         'status' => true,
         'message' => 'Galon berhasil diambil'
      ];

      echo json_encode($response, JSON_PRETTY_PRINT);
   }
}
