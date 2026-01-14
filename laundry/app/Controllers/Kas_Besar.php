<?php

class Kas_Besar extends Controller
{
   public function __construct()
   {
      $this->session_cek(1); // Admin only
      $this->operating_data();
   }

   public function index()
   {
      $view = 'kas/kas_besar';
      $data_operasi = ['title' => 'Kas Besar'];

      // ====== SALDO KAS BESAR ======
      // Pemasukan: jenis_transaksi = 2 (Penarikan dari kas kasir ke kas besar)
      // + semua transaksi dengan metode_mutasi <> 1 (non-tunai: QRIS, Transfer, dll)
      
      $kredit = 0;
      
      // 1. Penarikan dari kas kasir 2 penarikan, 9 modal
      $where_penarikan = "jenis_transaksi IN (2, 9) AND status_mutasi <> 4";
      $cols = "SUM(jumlah) as jumlah";
      $jumlah_penarikan = $this->db(0)->get_cols_where('kas', $cols, $where_penarikan, 0);
      $kredit += isset($jumlah_penarikan['jumlah']) ? (int)$jumlah_penarikan['jumlah'] : 0;

      // 2. Pembayaran non-tunai, 1 jualan, 3 member, 7 sales
      $where_nontunai = "jenis_transaksi IN (1, 3, 6, 7) AND metode_mutasi = 2 AND status_mutasi <> 4";
      $jumlah_nontunai = $this->db(0)->get_cols_where('kas', $cols, $where_nontunai, 0);
      $kredit += isset($jumlah_nontunai['jumlah']) ? (int)$jumlah_nontunai['jumlah'] : 0;

      // ====== PENGELUARAN KAS BESAR ======
      // Pengeluaran dari kas besar
      $debit = 0;
      $where_debit = "(jenis_transaksi = 8 OR (jenis_transaksi = 5 AND metode_mutasi = 2)) AND status_mutasi <> 4";
      $jumlah_debit = $this->db(0)->get_cols_where('kas', $cols, $where_debit, 0);
      $debit += isset($jumlah_debit['jumlah']) ? (int)$jumlah_debit['jumlah'] : 0;

      // Saldo
      $saldo = $kredit - $debit;

      // ====== HISTORY TRANSAKSI KAS BESAR ======
      // Penarikan dari kas kasir + semua non-tunai
      $limit = 50;
      
      // Get penarikan history
      $where_history = $this->wCabang . " AND jenis_transaksi = 8 AND status_mutasi <> 4 ORDER BY insertTime DESC LIMIT $limit";
      $transaksi_list = $this->db(0)->get_where('kas', $where_history);

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, [
         'saldo' => $saldo,
         'kredit' => $kredit,
         'debit' => $debit,
         'transaksi_list' => $transaksi_list
      ]);
   }

   public function insert_pengeluaran()
   {
      $keterangan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $metode = $_POST['metode'] ?? 2; // Default non-tunai
      $jenis = $_POST['f1a'];

      $jenisEXP = explode("<explode>", $jenis);
      $id_jenis = $jenisEXP[0];
      $jenis_nama = $jenisEXP[1];

      // Kas Besar pengeluaran langsung disetujui admin
      $status_mutasi = 3;

      $data = [
         'id_kas' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
         'id_cabang' => $this->id_cabang,
         'jenis_mutasi' => 2, // Keluar
         'jenis_transaksi' => 8, // Pengeluaran
         'metode_mutasi' => $metode, // Non-tunai
         'note' => $keterangan,
         'note_primary' => $jenis_nama,
         'status_mutasi' => $status_mutasi,
         'jumlah' => $jumlah,
         'id_user' => $_SESSION[URL::SESSID]['user']['id_user'],
         'id_client' => 0,
         'ref_transaksi' => $id_jenis
      ];
      
      $do = $this->db(0)->insert('kas', $data);
      if ($do['errno'] <> 0) {
         $this->model('Log')->write("[Kas_Besar::insert_pengeluaran] Error: " . $do['error'] . " | Query: " . $do['query']);
         echo $do['error'];
      } else {
         echo 1;
      }
   }

   public function insert_modal()
   {
      // Tambah modal / pemasukan ke kas besar
      $keterangan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $metode = $_POST['metode'] ?? 2;

      $data = [
         'id_kas' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
         'id_cabang' => $this->id_cabang,
         'jenis_mutasi' => 1, // Masuk
         'jenis_transaksi' => 9, // Modal/Pemasukan lain
         'metode_mutasi' => $metode,
         'note' => $keterangan,
         'note_primary' => 'Modal',
         'status_mutasi' => 3, // Langsung approve
         'jumlah' => $jumlah,
         'id_user' => $_SESSION[URL::SESSID]['user']['id_user'],
         'id_client' => 0
      ];
      
      $do = $this->db(0)->insert('kas', $data);
      if ($do['errno'] <> 0) {
         $this->model('Log')->write("[Kas_Besar::insert_modal] Error: " . $do['error'] . " | Query: " . $do['query']);
         echo $do['error'];
      } else {
         echo 1;
      }
   }
}
