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
      if ($this->isTrainingMode()) {
         $this->view('layout', ['data_operasi' => ['title' => 'Kas Besar']]);
         echo '<div class="container mt-4"><div class="alert alert-warning">Mode Training: Kas Besar tidak tersedia (bukan data real).</div></div>';
         return;
      }

      $view = 'kas/kas_besar';
      $data_operasi = ['title' => 'Kas Besar'];

      // Transaksi cabang training tidak masuk Kas Besar (bukan data real)
      $exTrain = $this->sqlExcludeTrainingCabang('id_cabang');

      // Saldo: 1 query (sama pola Kas) — kredit (penarikan/modal + nontunai) vs debit (pengeluaran/kasbon nontunai)
      $saldoSql = "SELECT
         COALESCE(SUM(CASE
            WHEN jenis_transaksi IN (2, 9) THEN jumlah
            WHEN jenis_transaksi IN (1, 3, 6, 7) AND metode_mutasi = 2 THEN jumlah
            ELSE 0
         END), 0) AS kredit,
         COALESCE(SUM(CASE
            WHEN jenis_transaksi = 8 THEN jumlah
            WHEN jenis_transaksi = 5 AND metode_mutasi = 2 THEN jumlah
            ELSE 0
         END), 0) AS debit
         FROM kas
         WHERE {$exTrain}status_mutasi <> 4
         AND (
            jenis_transaksi IN (2, 9)
            OR (jenis_transaksi IN (1, 3, 6, 7) AND metode_mutasi = 2)
            OR jenis_transaksi = 8
            OR (jenis_transaksi = 5 AND metode_mutasi = 2)
         )";
      $saldoRow = $this->db(0)->query_array($saldoSql);
      $row = (is_array($saldoRow) && isset($saldoRow[0])) ? $saldoRow[0] : [];
      $kredit = (int) ($row['kredit'] ?? 0);
      $debit = (int) ($row['debit'] ?? 0);
      $saldo = $kredit - $debit;

      // Riwayat pengeluaran Kas Besar
      $limit = 50;
      $where_history = $this->wCabang . " AND jenis_transaksi = 8 AND status_mutasi <> 4 ORDER BY insertTime DESC LIMIT $limit";
      $transaksi_list = $this->db(0)->get_where('kas', $where_history);
      if (!is_array($transaksi_list)) {
         $transaksi_list = $transaksi_list ? iterator_to_array($transaksi_list) : [];
      }

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
      if ($this->isTrainingMode() || $this->isTrainingCabangId($this->id_cabang)) {
         echo 'Mode Training: tidak boleh mengubah Kas Besar';
         return;
      }

      $keterangan = $_POST['f1'] ?? '';
      $jumlah = $_POST['f2'] ?? 0;
      $metode = $_POST['metode'] ?? 2; // Default non-tunai
      $jenisRaw = $_POST['f1a'] ?? '';

      $jenisEXP = explode("<explode>", $jenisRaw);
      $id_jenis = isset($jenisEXP[0]) ? (int) $jenisEXP[0] : 0;
      $jenis_nama = $jenisEXP[1] ?? '';

      require_once 'app/Helper/PengeluaranKendaraan.php';
      $resolved = PengeluaranKendaraan::resolveKeteranganFromPost($_POST, $jenis_nama, $this->db(0));
      if (empty($resolved['ok'])) {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode(['error' => $resolved['message'] ?? 'Keterangan tidak valid.']);
         return;
      }
      $keterangan = (string) ($resolved['note'] ?? '');

      // Kas Besar pengeluaran langsung disetujui admin
      $status_mutasi = 3;

      $data = [
         'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
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
         $this->bumpItemPengeluaranFreq($id_jenis);
         if (!empty($resolved['id_kendaraan'])) {
            PengeluaranKendaraan::bumpFreq($this->db(0), (int) $resolved['id_kendaraan']);
         }
         echo 1;
      }
   }

   public function insert_modal()
   {
      if ($this->isTrainingMode() || $this->isTrainingCabangId($this->id_cabang)) {
         echo 'Mode Training: tidak boleh mengubah Kas Besar';
         return;
      }

      // Tambah modal / pemasukan ke kas besar
      $keterangan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $metode = $_POST['metode'] ?? 2;

      $data = [
         'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
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
