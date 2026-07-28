<?php

class PackLabel extends Controller
{
   function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   function index()
   {
      $data_operasi = ['title' => __CLASS__];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view(__CLASS__ . '/content');
   }

   /**
    * Resolve cabang dari kode outlet (REFXX#) atau id_cabang.
    */
   private function resolveCabang($idOutlet)
   {
      $idOutlet = trim((string) $idOutlet);
      if ($idOutlet === '') {
         return null;
      }
      foreach ($this->listCabang as $c) {
         if ((string) ($c['kode_cabang'] ?? '') === $idOutlet) {
            return $c;
         }
         if ((string) ($c['id_cabang'] ?? '') === $idOutlet) {
            return $c;
         }
      }
      return null;
   }

   /**
    * Cari sale by ID Outlet + ID Item (3 digit terakhir), seperti Operan.
    * Menampilkan preview nota/label yang akan dicetak.
    */
   function load($idItem = '', $idOutlet = '')
   {
      $idItem = trim((string) $idItem);
      $idOutlet = trim((string) $idOutlet);

      if ($idItem === '' || $idOutlet === '') {
         echo 'Lengkapi ID Outlet dan ID Item';
         return;
      }

      if (strlen($idItem) < 3) {
         echo 'Minimal 3 digit ID Item';
         return;
      }

      $cabang = $this->resolveCabang($idOutlet);
      if (!$cabang) {
         echo 'Outlet tidak ditemukan: ' . $idOutlet;
         return;
      }

      $idCabang = (int) $cabang['id_cabang'];
      $kodeCabang = (string) ($cabang['kode_cabang'] ?? $idOutlet);

      // Escape for LIKE
      $idItemEsc = $this->db(0)->escape($idItem);
      $where = "id_penjualan LIKE '%" . $idItemEsc . "' AND tuntas = 0 AND bin = 0 AND id_cabang = " . $idCabang;
      $data_main = $this->db(0)->get_where('sale', $where);

      if (empty($data_main)) {
         echo 'Data tidak ditemukan';
         return;
      }

      // Ambil pelanggan unik dari sale yang cocok
      $pelangganMap = [];
      foreach ($data_main as $sale) {
         $idPel = (int) ($sale['id_pelanggan'] ?? 0);
         if ($idPel <= 0 || isset($pelangganMap[$idPel])) {
            continue;
         }
         $nama = '';
         foreach ($this->pelangganLaundry as $p) {
            if ((int) $p['id_pelanggan'] === $idPel) {
               $nama = (string) $p['nama_pelanggan'];
               break;
            }
         }
         if ($nama === '') {
            $row = $this->db(0)->get_where_row('pelanggan', 'id_pelanggan = ' . $idPel);
            $nama = $row['nama_pelanggan'] ?? ('#' . $idPel);
         }
         $pelangganMap[$idPel] = [
            'id_pelanggan' => $idPel,
            'nama_pelanggan' => strtoupper($nama),
         ];
      }

      if (empty($pelangganMap)) {
         echo 'Pelanggan tidak ditemukan pada order ini';
         return;
      }

      $this->view(__CLASS__ . '/result', [
         'data_main' => $data_main,
         'pelanggan_list' => array_values($pelangganMap),
         'cabang' => $cabang,
         'kode_cabang' => $kodeCabang,
         'id_item' => $idItem,
         'id_outlet' => $idOutlet,
      ]);
   }
}
