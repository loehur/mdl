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



      // Saldo tunai kasir (termasuk penarikan non-tunai sebagai debit)
      $saldo = $this->getSaldoKas();



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



   /** @deprecated use insert_penarikan_tunai */

   public function insert()

   {

      $this->insert_penarikan_tunai();

   }



   public function insert_penarikan_tunai()

   {

      $keterangan = $_POST['f1'] ?? '';

      $jumlah = intval($_POST['f2'] ?? 0);

      $penarik = intval($_POST['f3'] ?? 0);

      $status_mutasi = 2;



      if ($this->id_privilege == 100) {

         $status_mutasi = 3;

      }



      if ($jumlah < 1000) {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode(['error' => 'Minimal setoran Rp 1.000']);
         return;
      }

      if (trim((string) $keterangan) === '') {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode(['error' => 'Keterangan wajib diisi']);
         return;
      }

      $saldoTersedia = $this->getSaldoKas();
      if ($jumlah > $saldoTersedia) {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode([
            'error' => 'Saldo kas tidak cukup. Tersedia Rp ' . number_format($saldoTersedia, 0, ',', '.'),
         ]);
         return;
      }

      $note_esc = $this->db(0)->escape($keterangan);

      $where_dup = $this->wCabang . " AND jenis_transaksi = 2 AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jumlah = $jumlah AND id_user = $penarik AND note = '$note_esc' AND insertTime >= DATE_SUB(NOW(), INTERVAL 5 SECOND)";

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

            'note_primary' => 'Setoran'

         ];

         $do = $this->db(0)->insert('kas', $data);

         if ($do['errno'] == 0) {

            echo 1;

         } else {

            $this->model('Log')->write("[Kas::insert_penarikan_tunai] Error: " . $do['error'] . " | Query: " . $do['query']);

            echo 0;

         }

      } else {

         header('HTTP/1.1 409 Conflict');

         echo json_encode(['error' => 'Transaksi sudah terinput. Jangan double-click.']);

      }

   }



   public function insert_penarikan_nontunai()

   {

      $jumlah = intval($_POST['f2'] ?? 0);

      $penarik = intval($_POST['f3'] ?? 0);

      $note = strtoupper(trim((string) ($_POST['note'] ?? '')));
      $keterangan = trim((string) ($_POST['keterangan'] ?? ''));



      if (!in_array($note, ['BCA', 'QRIS'], true)) {

         header('HTTP/1.1 422 Unprocessable Entity');

         echo json_encode(['error' => 'Pilih tujuan BCA atau QRIS']);

         return;

      }



      if ($penarik < 1) {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode(['error' => 'Penarik kas wajib dipilih']);
         return;
      }

      if ($keterangan === '') {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode(['error' => 'Keterangan wajib diisi']);
         return;
      }

      if ($jumlah < 1000) {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode(['error' => 'Minimal setoran Rp 1.000']);
         return;
      }

      if ($note === 'QRIS' && $jumlah > 500000) {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode(['error' => 'Maksimal setoran QRIS Rp 500.000']);
         return;
      }

      $saldoTersedia = $this->getSaldoKas();
      if ($jumlah > $saldoTersedia) {
         header('HTTP/1.1 422 Unprocessable Entity');
         echo json_encode([
            'error' => 'Saldo kas tidak cukup. Tersedia Rp ' . number_format($saldoTersedia, 0, ',', '.'),
         ]);
         return;
      }

      $note_esc = $this->db(0)->escape($note);

      $where_dup = $this->wCabang

         . " AND jenis_transaksi = 2 AND jenis_mutasi = 2 AND metode_mutasi = 2"

         . " AND jumlah = $jumlah AND id_user = $penarik AND UPPER(note) = '$note_esc'"

         . " AND insertTime >= DATE_SUB(NOW(), INTERVAL 5 SECOND)";

      $data_main = $this->db(0)->count_where('kas', $where_dup);



      if ($data_main >= 1) {

         header('HTTP/1.1 409 Conflict');

         echo json_encode(['error' => 'Transaksi sudah terinput. Jangan double-click.']);

         return;

      }



      $refFinance = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);



      $data = [

         'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),

         'id_cabang' => $this->id_cabang,

         'jenis_mutasi' => 2,

         'jenis_transaksi' => 2,

         'metode_mutasi' => 2,

          'note' => $note,

          'keterangan' => $keterangan,

         'note_primary' => 'Setoran',

         'status_mutasi' => 2,

         'jumlah' => $jumlah,

         'id_user' => $penarik,

         'id_client' => 0,

         'ref_finance' => $refFinance,

      ];



      $do = $this->db(0)->insert('kas', $data);

      if ($do['errno'] == 0) {

         echo 1;

      } else {

         $this->model('Log')->write("[Kas::insert_penarikan_nontunai] Error: " . $do['error'] . " | Query: " . $do['query']);

         header('HTTP/1.1 500 Internal Server Error');

         echo json_encode(['error' => 'Gagal menyimpan setoran non tunai.']);

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

       } elseif ($this->isWajarPengeluaranGasLpg($id_jenis, $jumlah)
          || $this->isWajarPengeluaranAirGalon($id_jenis, $jumlah)
          || $this->isWajarPengeluaranMinyakKendaraan($id_jenis, $jumlah)
          || $this->isWajarPengeluaranPungutan($id_jenis, $jumlah)
       ) {

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

   /**
    * Gas LPG wajar jika rasio biaya per qty layanan setrika pada siklus
     * sebelumnya tidak melebihi rasio cabang paling hemat + 20%.
    */
   private function isWajarPengeluaranGasLpg(int $idJenis, int $jumlah): bool
   {
      if ($idJenis !== 1 || $jumlah <= 0) {
         return false;
      }

      $db = $this->db(0);
      $today = date('Y-m-d H:i:s');
      $cabang = (int) $this->id_cabang;
      if ($cabang < 1) {
         return false;
      }

      // Pembelian LPG sebelumnya menjadi awal siklus konsumsi yang akan dinilai.
      $prev = $db->get_where_row(
         'kas',
         "id_cabang = {$cabang} AND jenis_transaksi = 4 AND jenis_mutasi = 2 "
         . "AND status_mutasi <> 4 AND UPPER(note_primary) LIKE '%GAS LPG%' "
         . "AND insertTime < '" . $db->escape($today) . "' ORDER BY insertTime DESC LIMIT 1"
      );
      if (!is_array($prev) || empty($prev['insertTime'])) {
         return false;
      }

      $start = $db->escape((string) $prev['insertTime']);
      $qty = $this->sumQtySetrika("id_cabang = {$cabang} AND bin = 0 AND insertTime > '{$start}' AND insertTime <= NOW()");
      if ($qty <= 0) {
         return false;
      }

      $cycleGas = (int) ($prev['jumlah'] ?? 0);
      if ($cycleGas <= 0) {
         return false;
      }

       $benchmark = $this->minimumGasLpgRatioFromSnapshots();
      if ($benchmark === null) {
         return false;
      }

      return (($cycleGas + $jumlah) / $qty) <= ($benchmark * 1.20);
   }

    /** Air Galon: snapshot cabang dulu, lalu fallback ke aktual bulan lalu. */
   private function isWajarPengeluaranAirGalon(int $idJenis, int $jumlah): bool
   {
      if ($idJenis !== 3 || $jumlah <= 0) {
         return false;
      }

       $total = $this->getPengeluaranBaseline('air_galon');
      if ($total <= 0) {
         return false;
      }

      return $jumlah <= ($total * 1.20);
   }

    /** Minyak Kendaraan: rata-rata snapshot dulu, lalu fallback ke aktual bulan lalu. */
   private function isWajarPengeluaranMinyakKendaraan(int $idJenis, int $jumlah): bool
   {
      if ($idJenis !== 2 || $jumlah <= 0) {
         return false;
      }

       $average = $this->getPengeluaranBaseline('minyak_kendaraan', true);
       if ($average === null) {
          return false;
       }

      return $jumlah <= ($average * 1.20);
   }

    /** Pungutan: snapshot cabang dulu, lalu fallback ke aktual bulan lalu. */
    private function isWajarPengeluaranPungutan(int $idJenis, int $jumlah): bool
   {
      if ($idJenis !== 5 || $jumlah <= 0) {
         return false;
      }

       $total = $this->getPengeluaranBaseline('pungutan', false, trim($jenis));
      if ($total <= 0) {
         return false;
      }

       return $jumlah <= ($total * 1.20);
    }

    /** Cache semua baseline pengeluaran sekali per request; snapshot menjadi prioritas. */
    private function getPengeluaranBaseline(string $jenis, bool $averageAllBranches = false, string $exactName = ''): ?float
    {
       static $cache = null;
       if ($cache === null) {
          $cache = ['snapshot' => [], 'actual' => []];
          $db = $this->db(0);
          $periode = date('Y-m', strtotime('first day of last month'));
          $periodeEsc = $db->escape($periode);
          $rows = $db->query_array(
             "SELECT id_cabang, kas_keluar_json FROM rekap_snapshot "
             . "WHERE mode = 2 AND periode = '{$periodeEsc}' ORDER BY id_cabang ASC"
          );
          foreach ((array) $rows as $row) {
             $branch = (int) ($row['id_cabang'] ?? 0);
             if ($branch < 1) continue;
             foreach ((array) json_decode((string) ($row['kas_keluar_json'] ?? ''), true) as $item) {
                $name = mb_strtoupper((string) ($item['note_primary'] ?? ''), 'UTF-8');
                $key = $exactName !== '' && $name === mb_strtoupper($exactName, 'UTF-8') ? 'pungutan'
                   : (str_contains($name, 'MINYAK') && str_contains($name, 'KENDARAAN')
                   ? 'minyak_kendaraan'
                   : (str_contains($name, 'AIR GALON') ? 'air_galon' : (str_contains($name, 'PUNGUTAN') ? 'pungutan' : null)));
                if ($key !== null && (int) ($item['total'] ?? 0) > 0) {
                   $cache['snapshot'][$key][$branch] = ($cache['snapshot'][$key][$branch] ?? 0) + (int) $item['total'];
                }
             }
          }
          $actual = $db->query_array(
             "SELECT id_cabang, note_primary, SUM(jumlah) AS total FROM kas "
             . "WHERE jenis_transaksi = 4 AND jenis_mutasi = 2 AND status_mutasi <> 4 "
             . "AND DATE_FORMAT(insertTime, '%Y-%m') = '{$periodeEsc}' "
             . "GROUP BY id_cabang, note_primary"
          );
          foreach ((array) $actual as $row) {
             $name = mb_strtoupper((string) ($row['note_primary'] ?? ''), 'UTF-8');
             $key = str_contains($name, 'MINYAK') && str_contains($name, 'KENDARAAN')
                ? 'minyak_kendaraan' : (str_contains($name, 'AIR GALON') ? 'air_galon' : (str_contains($name, 'PUNGUTAN') ? 'pungutan' : null));
             if ($key !== null && (int) ($row['total'] ?? 0) > 0) {
                $cache['actual'][$key][(int) $row['id_cabang']] = ($cache['actual'][$key][(int) $row['id_cabang']] ?? 0) + (int) $row['total'];
             }
          }
       }
       $values = $cache['snapshot'][$jenis] ?? [];
       if ($averageAllBranches) {
          if ($values === []) $values = $cache['actual'][$jenis] ?? [];
          return $values === [] ? null : array_sum($values) / count($values);
       }
       $branch = (int) $this->id_cabang;
       $value = $values[$branch] ?? null;
       if ($value === null) $value = $cache['actual'][$jenis][$branch] ?? null;
       return $value !== null && $value > 0 ? (float) $value : null;
    }

   private function minimumGasLpgRatioFromSnapshots(): ?float
   {
      $bulanLalu = date('Y-m', strtotime('first day of last month'));
      $bulanEsc = $this->db(0)->escape($bulanLalu);
      $rows = $this->db(0)->query_array(
         "SELECT kas_keluar_json, qty_json FROM rekap_snapshot "
         . "WHERE mode = 2 AND id_cabang > 0 AND periode = '{$bulanEsc}' "
         . "ORDER BY id_cabang ASC"
      );
      if (!is_array($rows)) {
         return null;
      }

      $byBranch = [];
      foreach ($rows as $row) {
         $kas = json_decode((string) ($row['kas_keluar_json'] ?? ''), true);
         $qtyData = json_decode((string) ($row['qty_json'] ?? ''), true);
         $gas = 0;
         foreach ((array) $kas as $item) {
            if (stripos((string) ($item['note_primary'] ?? ''), 'GAS LPG') !== false) {
               $gas += (int) ($item['total'] ?? 0);
            }
         }
         $qty = 0;
         foreach ((array) ($qtyData['detail'] ?? []) as $item) {
            if (stripos((string) ($item['layanan'] ?? ''), 'SETRIKA') !== false) {
               $qty += (float) ($item['qty'] ?? 0);
            }
         }
         $branch = (int) ($row['id_cabang'] ?? 0);
         if ($branch > 0 && $gas > 0 && $qty > 0) {
            $byBranch[$branch][] = $gas / $qty;
         }
      }

      $averages = [];
      foreach ($byBranch as $ratios) {
         $averages[] = array_sum($ratios) / count($ratios);
      }

      return $averages === [] ? null : min($averages);
   }

   private function sumQtySetrika(string $where): float
   {
      $rows = $this->db(0)->get_where('sale', $where);
      $total = 0.0;
      foreach ((array) $rows as $sale) {
         $ids = @unserialize((string) ($sale['list_layanan'] ?? ''), ['allowed_classes' => false]);
         if (!is_array($ids)) {
            continue;
         }
         $hasSetrika = false;
         foreach ($ids as $id) {
            foreach ((array) $this->dLayanan as $layanan) {
               if ((int) ($layanan['id_layanan'] ?? 0) === (int) $id
                  && stripos((string) ($layanan['layanan'] ?? ''), 'SETRIKA') !== false) {
                  $hasSetrika = true;
                  break 2;
               }
            }
         }
         if ($hasSetrika) {
            $total += (float) ($sale['qty'] ?? 0);
         }
      }
      return $total;
   }

   /**
    * Saldo kas kasir: tunai masuk/keluar + penarikan non-tunai (jt=2) mengurangi batas yang sama.
    */
   private function getSaldoKas(): int
   {
      $saldoSql = "SELECT
         COALESCE(SUM(CASE
            WHEN jenis_mutasi = 1 AND metode_mutasi = 1
                 AND jenis_transaksi IN (1,3,6,7) THEN jumlah
            ELSE 0
         END), 0) AS kredit,
         COALESCE(SUM(CASE
            WHEN jenis_mutasi = 2 AND status_mutasi <> 4 AND (
               (metode_mutasi = 1 AND jenis_transaksi IN (2,4,5))
               OR (metode_mutasi = 2 AND jenis_transaksi = 2)
            ) THEN jumlah
            ELSE 0
         END), 0) AS debit
         FROM kas
         WHERE {$this->wCabang}";
      $saldoRow = $this->db(0)->query_array($saldoSql);
      $row = (is_array($saldoRow) && isset($saldoRow[0])) ? $saldoRow[0] : [];
      $kredit = (int) ($row['kredit'] ?? 0);
      $debit = (int) ($row['debit'] ?? 0);

      return $kredit - $debit;
   }
}
