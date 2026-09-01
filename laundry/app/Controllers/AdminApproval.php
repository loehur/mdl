<?php

class AdminApproval extends Controller
{

   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index($mode)
   {
      $data_operasi = ['title' => 'Approval'];
      $db = $this->db(0);
      $wc = $this->wCabangAll('id_cabang', true);

      // Badge hanya butuh count — hindari SELECT * (data isi dimuat AJAX di controller masing-masing)
      $kasAgg = $db->query_array(
         "SELECT
            SUM(CASE WHEN jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 2 THEN 1 ELSE 0 END) AS setoran,
            SUM(CASE WHEN jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 4 THEN 1 ELSE 0 END) AS pengeluaran,
            COUNT(DISTINCT CASE WHEN metode_mutasi = 2 AND ref_finance <> '' THEN CONCAT(id_cabang, ':', ref_finance) END) AS non_tunai
          FROM kas
          WHERE {$wc}
            AND status_mutasi = 2
            AND (
              (jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi IN (2, 4))
              OR metode_mutasi = 2
            )"
      );
      $kasRow = (is_array($kasAgg) && isset($kasAgg[0])) ? $kasAgg[0] : [];

      $counts = [
         'Setoran' => (int) ($kasRow['setoran'] ?? 0),
         'Pengeluaran' => (int) ($kasRow['pengeluaran'] ?? 0),
         'NonTunai' => (int) ($kasRow['non_tunai'] ?? 0),
         'HapusOrder' => (int) $db->count_where('sale', $wc . ' AND id_pelanggan <> 0 AND bin = 1'),
         'HapusDeposit' => (int) $db->count_where('member', $wc . ' AND bin = 1'),
      ];

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view(
         'admin_approval/admin_approval_main',
         [
            'counts' => $counts,
            'mode' => $mode
         ]
      );
   }
}
