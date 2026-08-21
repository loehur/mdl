<?php

class Kas extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $view = 'kas/kas_main';
      $data_operasi = ['title' => 'Kas Kasir'];

      // Saldo tunai: 1 query (kredit 1/3/6/7 masuk + debit 2/4/5 keluar)
      $saldoSql = "SELECT
         COALESCE(SUM(CASE WHEN jenis_mutasi = 1 THEN jumlah ELSE 0 END), 0) AS kredit,
         COALESCE(SUM(CASE WHEN jenis_mutasi = 2 THEN jumlah ELSE 0 END), 0) AS debit
         FROM kas
         WHERE {$this->wCabang}
         AND metode_mutasi = 1
         AND status_mutasi <> 4
         AND (
            (jenis_transaksi IN (1,3,6,7) AND jenis_mutasi = 1)
            OR (jenis_transaksi IN (2,4,5) AND jenis_mutasi = 2)
         )";
      $saldoRow = $this->db(0)->query_array($saldoSql);
      $row = (is_array($saldoRow) && isset($saldoRow[0])) ? $saldoRow[0] : [];
      $kredit = (int) ($row['kredit'] ?? 0);
      $debit = (int) ($row['debit'] ?? 0);
      $saldo = $kredit - $debit;

      $limit = 10;
      if ($this->id_privilege == 100) {
         $limit = 25;
      }

      $where = $this->wCabang . " AND jenis_transaksi IN (2, 4) AND jenis_mutasi = 2 ORDER BY insertTime DESC LIMIT $limit";
      $debit_list = $this->db(0)->get_where('kas', $where);
      if (!is_array($debit_list)) {
         $debit_list = [];
      }

      //KASBON
      $where = $this->wCabang . " AND jenis_transaksi = 5 AND jenis_mutasi = 2 AND status_mutasi = 3 ORDER BY insertTime DESC LIMIT 25";
      $kasbon = $this->db(0)->get_where('kas', $where);
      if (!is_array($kasbon)) {
         $kasbon = [];
      }

      // Status potong gaji: 1 query batch (bukan N+1 per kasbon)
      $dataPotong = [];
      $refs = [];
      foreach ($kasbon as $k) {
         $ref = $k['id_kas'];
         $dataPotong[$ref] = 0;
         $refs[] = "'" . $this->db(0)->escape($ref) . "'";
      }

      if (count($refs) > 0) {
         $potongSql = "SELECT ref, tgl, id_karyawan FROM gaji_result WHERE tipe = 2 AND ref IN (" . implode(',', $refs) . ")";
         $potongRows = $this->db(0)->query_array($potongSql);
         if (!is_array($potongRows)) {
            $potongRows = [];
         }

         $potongMap = [];
         foreach ($potongRows as $cp) {
            $key = $cp['ref'] . '|' . $cp['tgl'] . '|' . $cp['id_karyawan'];
            $potongMap[$key] = true;
         }

         foreach ($kasbon as $k) {
            $key = $k['id_kas'] . '|' . substr($k['insertTime'], 0, 7) . '|' . $k['id_client'];
            if (isset($potongMap[$key])) {
               $dataPotong[$k['id_kas']] = 1;
            }
         }
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, [
         'saldo' => $saldo,
         'debit_list' => $debit_list,
         'kasbon' => $kasbon,
         'dataPotong' => $dataPotong
      ]);
   }

   public function insert()
   {
      //PENARIKAN
      $keterangan = $_POST['f1'] ?? '';
      $jumlah = intval($_POST['f2'] ?? 0);
      $penarik = intval($_POST['f3'] ?? 0);
      $today = date('Y-m-d');
      $status_mutasi = 2;

      if ($this->id_privilege == 100) {
         $status_mutasi = 3;
      }

      // Cek duplicate (double-click) - transaksi sama dalam 5 detik terakhir
      $note_esc = $this->db(0)->escape($keterangan);
      $where_dup = $this->wCabang . " AND jenis_transaksi = 2 AND jenis_mutasi = 2 AND jumlah = $jumlah AND id_user = $penarik AND note = '$note_esc' AND insertTime >= DATE_SUB(NOW(), INTERVAL 5 SECOND)";
      $data_main = $this->db(0)->count_where('kas', $where_dup);

      if ($data_main < 1) {
         $data = [
            'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
            'id_cabang' => $this->id_cabang,
            'jenis_mutasi' => 2,
            'jenis_transaksi' => 2,
            'metode_mutasi' => 1,
            'note' => $keterangan,
            'status_mutasi' => $status_mutasi,
            'jumlah' => $jumlah,
            'id_user' => $penarik,
            'id_client' => 0,
            'note_primary' => 'Penarikan'
         ];
         $do = $this->db(0)->insert('kas', $data);
         if ($do['errno'] == 0) {
            echo 1;
         } else {
            $this->model('Log')->write("[Kas::insert] Error: " . $do['error'] . " | Query: " . $do['query']);
            echo 0;
         }
      } else {
         header('HTTP/1.1 409 Conflict');
         echo json_encode(['error' => 'Transaksi sudah terinput. Jangan double-click.']);
      }
   }

   public function insert_pengeluaran()
   {
      $jumlah = intval($_POST['f2'] ?? 0);
      $penarik = intval($_POST['f3'] ?? 0);
      $jenisRaw = $_POST['f1a'] ?? '';

      $jenisEXP = explode("<explode>", $jenisRaw);
      $id_jenis = isset($jenisEXP[0]) ? intval($jenisEXP[0]) : 0;
      $jenis = $jenisEXP[1] ?? '';

      require_once 'app/Helper/PengeluaranKendaraan.php';
      $resolved = PengeluaranKendaraan::resolveKeteranganFromPost($_POST, $jenis, $this->db(0));
      if (empty($resolved['ok'])) {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode(['error' => $resolved['message'] ?? 'Keterangan tidak valid.']);
         return;
      }
      $keterangan = (string) ($resolved['note'] ?? '');

      $status_mutasi = 2;
      if ($this->id_privilege == 100) {
         $status_mutasi = 3;
      }

      // Cek duplicate (double-click) - transaksi sama dalam 5 detik terakhir
      $note_esc = $this->db(0)->escape($keterangan);
      $where_dup = $this->wCabang . " AND jenis_transaksi = 4 AND jenis_mutasi = 2 AND jumlah = $jumlah AND id_user = $penarik AND ref_transaksi = '$id_jenis' AND note = '$note_esc' AND insertTime >= DATE_SUB(NOW(), INTERVAL 5 SECOND)";
      $data_main = $this->db(0)->count_where('kas', $where_dup);

      if ($data_main < 1) {
         $data = [
            'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
            'id_cabang' => $this->id_cabang,
            'jenis_mutasi' => 2,
            'jenis_transaksi' => 4,
            'metode_mutasi' => 1,
            'note' => $keterangan,
            'note_primary' => $jenis,
            'status_mutasi' => $status_mutasi,
            'jumlah' => $jumlah,
            'id_user' => $penarik,
            'id_client' => 0,
            'ref_transaksi' => $id_jenis
         ];
         $do = $this->db(0)->insert('kas', $data);
         if ($do['errno'] == 0) {
            $this->bumpItemPengeluaranFreq($id_jenis);
            if (!empty($resolved['id_kendaraan'])) {
               PengeluaranKendaraan::bumpFreq($this->db(0), (int) $resolved['id_kendaraan']);
            }
            echo 1;
         } else {
            $this->model('Log')->write("[Kas::insert_pengeluaran] Error: " . $do['error'] . " | Query: " . $do['query']);
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Gagal menyimpan pengeluaran.']);
         }
      } else {
         header('HTTP/1.1 409 Conflict');
         echo json_encode(['error' => 'Transaksi sudah terinput. Jangan double-click.']);
      }
   }
}
