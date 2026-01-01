<?php

class Riwayat_Bayar extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Riwayat Bayar'];
      $data['bayar'] = $this->db($this->book)->get_cols_where('kas', "ref_bayar, SUM(jumlah) as jumlah, id_client", "ref_bayar <> '' GROUP BY ref_bayar ORDER BY ref_bayar DESC", 1);
      $data['pelanggan'] = $this->db(0)->get("pelanggan", "id");

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart_riwayat_bayar($ref_bayar = 0)
   {
      $viewData = __CLASS__ . '/cart';

      $data['ref_bayar'] = $this->db($this->book)->get_where('kas', "ref_bayar = '" . $ref_bayar . "'", "ref", 1);
      $ref_bayar_keys = array_keys($data['ref_bayar']);

      $refs = "";
      if (count($ref_bayar_keys) > 0) {
         foreach ($ref_bayar_keys as $rb) {
            $refs .= $rb . ",";
         }
         $refs = rtrim($refs, ',');
         $where = "id IN (" . $refs . ")";
      } else {
         echo "Tidak ada data";
         exit();
      }

      $data['order'] = $this->db($this->book)->get_where('ref', $where, "tgl", 1);
      $data['order_ref'] = $this->db($this->book)->get_where('ref', $where, "id");

      foreach ($data['order_ref'] as $key => $r) {
         $pelanggan = $r['pelanggan'];

         if (!isset($total[$r['tgl']])) {
            $total[$r['tgl']] = 0;
         }

         $cek_dibayar[$key] = $this->db($this->book)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref_bayar <> '' AND ref = '" . $key . "'");
         foreach ($cek_dibayar[$key] as $b) {
            $total[$r['tgl']] += $b['jumlah'];
         }
      }

      $data['total'] = $total;
      $data['pelanggan'] = $pelanggan;

      $data['refbayar'] = $ref_bayar;
      $this->view($viewData, $data);
   }

   function batalkan()
   {
      $ref_bayar = $_POST['ref'];
      $data['ref_bayar'] = $this->db($this->book)->get_where('kas', "ref_bayar = '" . $ref_bayar . "'", "ref", 1);
      $ref_bayar_keys = array_keys($data['ref_bayar']);

      $refs = "";
      if (count($ref_bayar_keys) > 0) {
         foreach ($ref_bayar_keys as $rb) {
            $refs .= $rb . ",";
         }
         $refs = rtrim($refs, ',');
         $where = "id IN (" . $refs . ")";
      } else {
         echo "Tidak ada data";
         exit();
      }

      $up = $this->db($this->book)->update('ref', 'step = 3', $where);
      if ($up['errno'] == 0) {
         $del = $this->db($this->book)->delete_where('kas', "ref_bayar = '" . $ref_bayar . "'");
         echo $del['errno'] == 0 ? 0 : "Gagal membatalkan pembayaran: " . $del['error'];
      } else {
         echo "Gagal membatalkan pembayaran: " . $up['error'];
      }
   }
}
