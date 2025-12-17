<?php

class PackLabel extends Controller
{
   function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   function index($cetak = [])
   {
      $data_operasi = ['title' => __CLASS__];
      $this->view('layout', ['data_operasi' => $data_operasi]);

      $data['cetak'] = $cetak;
      $this->view(__CLASS__ . '/content', $data);
   }

   function cetak()
   {
      $post = explode("_EXP_", $_POST['pelanggan']);
      $data['pelanggan'] = $post[0];
      $data['cabang'] = $post[1];
      $this->index($data);
   }

   /**
    * Get pelanggan by kode cabang via AJAX
    */
   function getPelangganByCabang()
   {
      header('Content-Type: application/json');
      
      // Pastikan ini adalah request POST
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
         echo json_encode(['success' => false, 'message' => 'Invalid request method']);
         return;
      }
      
      $kode_cabang = $_POST['kode_cabang'] ?? '';
      
      if (empty($kode_cabang)) {
         echo json_encode(['success' => false, 'message' => 'Kode cabang tidak ditemukan']);
         return;
      }

      // Pastikan listCabang tersedia
      if (empty($this->listCabang)) {
         echo json_encode(['success' => false, 'message' => 'Data cabang tidak tersedia']);
         return;
      }

      // Cari cabang berdasarkan kode_cabang atau id_cabang
      $cabang = null;
      foreach ($this->listCabang as $c) {
         // Coba cocokkan dengan kode_cabang dulu
         if ($c['kode_cabang'] == $kode_cabang) {
            $cabang = $c;
            break;
         }
         // Fallback: cocokkan dengan id_cabang
         if ($c['id_cabang'] == $kode_cabang) {
            $cabang = $c;
            break;
         }
      }

      if (!$cabang) {
         // Debug: tampilkan kode cabang yang tersedia
         $available = array_map(function($c) { return $c['kode_cabang'] . '(id:' . $c['id_cabang'] . ')'; }, $this->listCabang);
         echo json_encode(['success' => false, 'message' => 'Cabang tidak ditemukan: ' . $kode_cabang . '. Available: ' . implode(', ', $available)]);
         return;
      }

      // Get pelanggan berdasarkan id_cabang
      $pelanggan = $this->db(0)->get_where('pelanggan', 'id_cabang = ' . $cabang['id_cabang']);

      // Kumpulkan id_pelanggan yang memiliki sale dengan tuntas = 0 dari tahun 2021 sampai sekarang
      $pelangganWithPendingSales = [];
      $currentYear = (int) date('Y');
      
      for ($year = 2021; $year <= $currentYear; $year++) {
         // Query untuk mendapatkan pelanggan yang punya sale tuntas = 0 di cabang ini
         $pendingSales = $this->db($year)->get_where(
            'sale', 
            'tuntas = 0 AND id_cabang = ' . $cabang['id_cabang']
         );
         
         foreach ($pendingSales as $sale) {
            if (isset($sale['id_pelanggan'])) {
               $pelangganWithPendingSales[$sale['id_pelanggan']] = true;
            }
         }
      }

      $result = [];
      foreach ($pelanggan as $p) {
         // Hanya tampilkan pelanggan yang memiliki sale tidak tuntas
         if (isset($pelangganWithPendingSales[$p['id_pelanggan']])) {
            $result[] = [
               'value' => strtoupper($p['nama_pelanggan']) . '_EXP_' . $kode_cabang,
               'text' => strtoupper($p['nama_pelanggan'])
            ];
         }
      }

      echo json_encode(['success' => true, 'data' => $result]);
   }
}
