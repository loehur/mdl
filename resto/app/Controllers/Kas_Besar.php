<?php

class Kas_Besar extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
      
      // Hanya admin yang bisa akses
      if ($this->id_privilege < 100) {
         header("Location: " . URL::BASE_URL);
         exit();
      }
   }

   public function index()
   {
      $layout = ['title' => 'Kas Besar'];

      // Total pembayaran dengan metode 2 (QRIS) atau 4 (Transfer)
      $cols_pembayaran = "SUM(jumlah) as total";
      $where_pembayaran = "jenis_mutasi = 1 AND jenis_transaksi = 1 AND (metode_mutasi = 2 OR metode_mutasi = 4) AND status_mutasi <> 2";
      $pembayaran = $this->db(0)->get_cols_where('kas', $cols_pembayaran, $where_pembayaran, 0);
      $total_pembayaran = isset($pembayaran['total']) ? (int)$pembayaran['total'] : 0;

      // Total penarikan (jenis_transaksi = 2, status_mutasi = 1 approved)
      $cols_penarikan = "SUM(jumlah) as total";
      $where_penarikan = "jenis_mutasi = 2 AND jenis_transaksi = 2 AND status_mutasi = 1";
      $penarikan = $this->db(0)->get_cols_where('kas', $cols_penarikan, $where_penarikan, 0);
      $total_penarikan = isset($penarikan['total']) ? (int)$penarikan['total'] : 0;

      // Total pengeluaran kas besar (jenis_transaksi = 3, status_mutasi = 1 approved)
      $cols_keluar = "SUM(jumlah) as total";
      $where_keluar = "jenis_mutasi = 2 AND jenis_transaksi = 3 AND status_mutasi = 1";
      $pengeluaran = $this->db(0)->get_cols_where('kas', $cols_keluar, $where_keluar, 0);
      $total_pengeluaran = isset($pengeluaran['total']) ? (int)$pengeluaran['total'] : 0;

      // Saldo Kas Besar = Masuk - Keluar
      $saldo_kas_besar = $total_pembayaran + $total_penarikan - $total_pengeluaran;

      // Jenis pengeluaran (GROUP BY untuk menghindari duplikat)
      $jenis_pengeluaran = $this->db(0)->get_where("item_pengeluaran", "1=1 GROUP BY item_pengeluaran ORDER BY freq DESC");

      // Riwayat pengeluaran kas besar (20 terakhir)
      $riwayat = $this->db(0)->get_where('kas', "jenis_transaksi = 3 ORDER BY id DESC LIMIT 20");

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", [
         'saldo_kas_besar' => $saldo_kas_besar,
         'pengeluaran_jenis' => $jenis_pengeluaran,
         'riwayat' => $riwayat
      ]);
   }

   public function insert_pengeluaran()
   {
      $keterangan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $jenis = $_POST['f1a'];
      
      // Tanggal manual (format datetime-local: 2026-01-07T10:00)
      $tgl_input = $_POST['tgl'];
      $insertTime = date('Y-m-d H:i:s', strtotime($tgl_input));

      $jenisEXP = explode("<explode>", $jenis);
      $id_jenis = $jenisEXP[0];
      $jenis_nama = $jenisEXP[1];

      // Ambil is_expense dari item_pengeluaran
      $item = $this->db(0)->get_where_row('item_pengeluaran', "id_item_pengeluaran = " . $id_jenis);
      $is_expense = isset($item['is_expense']) ? $item['is_expense'] : 1;

      // Admin langsung approved
      $status_mutasi = 1;

      // jenis_transaksi = 3 untuk pengeluaran dari Kas Besar
      $cols = 'id_cabang, jenis_mutasi, jenis_transaksi, metode_mutasi, note, note_primary, status_mutasi, jumlah, id_user, id_client, ref, is_expense, insertTime';
      $vals = $this->id_cabang . ",2,3,1,'" . $keterangan . "','" . $jenis_nama . "'," . $status_mutasi . "," . $jumlah . "," . $this->id_user . ",0," . $id_jenis . "," . $is_expense . ",'" . $insertTime . "'";
      $in = $this->db(0)->insertCols('kas', $cols, $vals);
      
      if ($in['errno'] == 0) {
         // Update freq jenis pengeluaran
         $this->db(0)->update('item_pengeluaran', "freq = freq + 1", "id_item_pengeluaran = " . $id_jenis);
         Log::write("Pengeluaran Kas Besar: " . $jenis_nama . " - Rp " . number_format($jumlah) . " - Tgl: " . $insertTime);
         echo 0;
      } else {
         echo $in['error'];
      }
   }
}
