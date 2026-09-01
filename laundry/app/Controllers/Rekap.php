<?php

class Rekap extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function i($mode)
   {
      // Mode configuration
      $modeConfig = [
         1 => ['title' => 'Harian Cabang - Rekap', 'type' => 'daily', 'useCabang' => true],
         2 => ['title' => 'Bulanan Cabang - Rekap', 'type' => 'monthly', 'useCabang' => true],
         3 => ['title' => 'Bulanan Laundry - Rekap', 'type' => 'monthly', 'useCabang' => false, 'vLaundry' => true],
         4 => ['title' => 'Harian Laundry - Rekap', 'type' => 'daily', 'useCabang' => false, 'vLaundry' => true],
      ];

      if (!isset($modeConfig[$mode])) {
         return; // Invalid mode
      }

      $config = $modeConfig[$mode];
      $isDaily = $config['type'] === 'daily';

      // Parse date from POST/GET or use current date
      $year = (int) ($_POST['y'] ?? $_GET['y'] ?? date('Y'));
      $month = str_pad((string) ($_POST['m'] ?? $_GET['m'] ?? date('m')), 2, '0', STR_PAD_LEFT);
      $day = str_pad((string) ($_POST['d'] ?? $_GET['d'] ?? date('d')), 2, '0', STR_PAD_LEFT);

      if ($isDaily) {
         $today = "$year-$month-$day";
         $dataTanggal = ['tanggal' => $day, 'bulan' => $month, 'tahun' => $year];
      } else {
         $today = "$year-$month";
         $dataTanggal = ['bulan' => $month, 'tahun' => $year];
      }

      $data_operasi = ['title' => $config['title']];
      if (isset($config['vLaundry'])) {
         $data_operasi['vLaundry'] = true;
      }

      // AJAX: hitung data & render partial (tanpa layout) — dipakai lazy load.
      if (!empty($_GET['ajax'])) {
         header('Content-Type: text/html; charset=utf-8');
         $data = $this->hitungDataRekap((int) $mode, $today, $isDaily, $config, $dataTanggal);
         $this->view('rekap/rekap_data', $data);
         return;
      }

      // Halaman utama: filter + skeleton. Data di-fetch via AJAX setelah load.
      $data = [
         'dataTanggal' => $dataTanggal,
         'rekap_mode' => (int) $mode,
         'data_main' => [],
         'kasLaundry' => 0,
         'kasMember' => 0,
         'kas_keluar' => [],
         'kas_tarik' => [],
         'prepost_cost' => 0,
         'gaji' => 0,
         'barang_pakai' => 0,
         'margin_penjualan' => 0,
         'snapshot' => null,
         'snapshot_meta' => null,
      ];
      if (!$isDaily && in_array((int) $mode, [2, 3], true)) {
         $data['snapshot_meta'] = $this->getRekapSnapshotStatus((int) $mode, $today);
         $data['snapshot'] = !empty($data['snapshot_meta']['complete']) ? ($data['snapshot_meta']['row'] ?? ['id' => 1]) : null;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('rekap/rekap', $data);
   }

   /**
    * Hitung seluruh angka rekap untuk periode tertentu (dipakai AJAX lazy load).
    * Logika sama dengan versi server-render lama.
    */
   private function hitungDataRekap(int $mode, string $today, bool $isDaily, array $config, array $dataTanggal): array
   {
      $whereCabang = $config['useCabang']
         ? $this->wCabang . " AND "
         : $this->sqlExcludeTrainingCabang('id_cabang');

      // OPTIMIZED: Single query for sale data with date range
      // Sargable range (pakai index insertTime) — DATE()/DATE_FORMAT() non-sargable bikin full scan.
      if ($isDaily) {
         $dateCondition = "insertTime >= '" . $today . " 00:00:00' AND insertTime < '" . $today . " 23:59:59'";
      } else {
         $nextMonth = date('Y-m', strtotime($today . '-01 +1 month'));
         $dateCondition = "insertTime >= '" . $today . "-01 00:00:00' AND insertTime < '" . $nextMonth . "-01 00:00:00'";
      }
      
      $where = $whereCabang . "bin = 0 AND $dateCondition";
      $data_main = $this->db(0)->get_where('sale', $where);
      // Convert to array if needed (for non-cabang mode consistency)
      if (!is_array($data_main)) {
         $data_main = iterator_to_array($data_main);
      }

      // Auto-isi pengeluaran Kas Besar (id 102) saat cek Rekap bulanan - HARUS sebelum query kas
      if (!$isDaily) {
         $id_pengeluaran = 102;
         $tgl_pertama = $today . '-01';
         $itemPengeluaran = $this->db(0)->get_where_row('item_pengeluaran', "id_item_pengeluaran = '$id_pengeluaran'");
         $jenis_nama = $itemPengeluaran['item_pengeluaran'] ?? 'Rekap Bulanan';

         $listCabang = [];
         if ($config['useCabang']) {
            $cabang = $this->db(0)->get_where_row('cabang', "id_cabang = " . $this->id_cabang);
            $listCabang = $cabang ? [$cabang] : [];
         } else {
            $listCabang = $this->getCabangOperasional();
            if (!is_array($listCabang)) $listCabang = [];
         }

         foreach ($listCabang as $cabang) {
            $id_cabang = $cabang['id_cabang'];
            // Cabang training tidak boleh auto-insert ke Kas Besar
            if (!empty($cabang['is_training']) || $this->isTrainingCabangId($id_cabang)) {
               continue;
            }
            $rent = intval($cabang['rent'] ?? 0);
            if ($rent <= 0) continue;

            $whereCek = "jenis_transaksi = 8 AND ref_transaksi = '$id_pengeluaran' AND id_cabang = $id_cabang AND DATE_FORMAT(insertTime, '%Y-%m') = '$today'";
            $ada = $this->db(0)->count_where('kas', $whereCek);
            if ($ada < 1) {
               $dataKas = [
                  'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
                  'id_cabang' => $id_cabang,
                  'jenis_mutasi' => 2,
                  'jenis_transaksi' => 8,
                  'metode_mutasi' => 2,
                  'note' => 'Auto dari Rekap ' . $today . ' (rent)',
                  'note_primary' => $jenis_nama,
                  'status_mutasi' => 3,
                  'jumlah' => $rent,
                  'id_user' => $_SESSION[URL::SESSID]['user']['id_user'],
                  'id_client' => 0,
                  'ref_transaksi' => $id_pengeluaran,
                  'insertTime' => $tgl_pertama . ' 00:00:00'
               ];
               $do = $this->db(0)->insert('kas', $dataKas);
               if ($do['errno'] != 0) {
                  $this->model('Log')->write("[Rekap::i] Auto insert Kas Besar error: " . ($do['error'] ?? '') . " | Query: " . ($do['query'] ?? ''));
               }
            }
         }
      }

      // OPTIMIZED: Combined KAS query - single query for all jenis_transaksi (sargable range)
      if ($isDaily) {
         $kasDateCondition = "insertTime >= '" . $today . " 00:00:00' AND insertTime < '" . $today . " 23:59:59'";
      } else {
         $nextMonth = date('Y-m', strtotime($today . '-01 +1 month'));
         $kasDateCondition = "insertTime >= '" . $today . "-01 00:00:00' AND insertTime < '" . $nextMonth . "-01 00:00:00'";
      }
      
      $kasSql = "SELECT jenis_transaksi, ref_transaksi, note_primary, SUM(jumlah) as total 
                 FROM kas 
                 WHERE {$whereCabang} status_mutasi <> 4 AND $kasDateCondition
                 GROUP BY jenis_transaksi, ref_transaksi, note_primary";
      $kasResult = $this->db(0)->query_array($kasSql);

      // Item pengeluaran Non-Biaya (is_expense = 0): tidak ikut ke tabel/total Pengeluaran Rekap
      $nonExpenseIds = [];
      $neRows = $this->db(0)->get_where('item_pengeluaran', 'is_expense = 0');
      if (!is_array($neRows)) {
         $neRows = $neRows ? iterator_to_array($neRows) : [];
      }
      foreach ($neRows as $r) {
         $idNe = (int) ($r['id_item_pengeluaran'] ?? 0);
         if ($idNe > 0) {
            $nonExpenseIds[$idNe] = true;
         }
      }

      // Parse combined result
      $kas_laundry = 0;
      $kas_member = 0;
      $kas_keluar = [];
      $kas_tarik = [];
      $rent_total = 0; // Gabungkan semua rent (ref 102) jadi satu

      // Ensure $kasResult is an array before iterating
      if (!is_array($kasResult)) {
         $kasResult = [];
      }

      foreach ($kasResult as $row) {
         switch ($row['jenis_transaksi']) {
            case 1: // Pendapatan Laundry
               $kas_laundry += $row['total'];
               break;
            case 2: // Penarikan
               $kas_tarik[] = ['note_primary' => $row['note_primary'], 'total' => $row['total']];
               break;
            case 3: // Member
               $kas_member += $row['total'];
               break;
            case 4: // Pengeluaran kasir
               $ref4 = (int) ($row['ref_transaksi'] ?? 0);
               if ($ref4 > 0 && isset($nonExpenseIds[$ref4])) {
                  break;
               }
               $kas_keluar[] = ['note_primary' => $row['note_primary'], 'total' => $row['total']];
               break;
            case 8: // Pengeluaran Kas Besar (termasuk rent id 102)
            case '8': // MySQL bisa return string
               $ref = intval($row['ref_transaksi'] ?? 0);
               if ($ref > 0 && isset($nonExpenseIds[$ref])) {
                  break;
               }
               if ($ref == 102) {
                  $rent_total += $row['total'];
               } else {
                  $kas_keluar[] = ['note_primary' => $row['note_primary'], 'total' => $row['total']];
               }
               break;
         }
      }

      // Rent (ref 102): satu entri dengan nama dari item_pengeluaran
      if ($rent_total > 0) {
         $itemRent = $this->db(0)->get_where_row('item_pengeluaran', "id_item_pengeluaran = '102'");
         $rent_nama = $itemRent['item_pengeluaran'] ?? 'Rekap Bulanan';
         $kas_keluar[] = ['note_primary' => $rent_nama, 'total' => $rent_total];
      }

      // Fallback: rent dari cabang jika belum ada (bulanan) - jamin tampil meski insert gagal
      if (!$isDaily && isset($listCabang) && $rent_total == 0 && !isset($nonExpenseIds[102])) {
         $total_rent = 0;
         foreach ($listCabang as $c) {
            $total_rent += intval($c['rent'] ?? 0);
         }
         if ($total_rent > 0) {
            $itemRent = $this->db(0)->get_where_row('item_pengeluaran', "id_item_pengeluaran = '102'");
            $rent_nama = $itemRent['item_pengeluaran'] ?? 'Rekap Bulanan';
            $kas_keluar[] = ['note_primary' => $rent_nama, 'total' => $total_rent];
         }
      }

      // Build where conditions for detail links (keep backward compatibility)
      $where_umum = $whereCabang . "jenis_transaksi = 1 AND status_mutasi <> 4 AND insertTime LIKE '%" . $today . "%'";
      $where_member = $whereCabang . "jenis_transaksi = 3 AND status_mutasi <> 4 AND insertTime LIKE '%" . $today . "%'";
      $where_keluar = $whereCabang . "jenis_transaksi = 4 AND status_mutasi <> 3 AND insertTime LIKE '%" . $today . "%'";
      $where_tarik = $whereCabang . "jenis_transaksi = 2 AND status_mutasi <> 3 AND insertTime LIKE '%" . $today . "%'";

      // PREPAID/POSTPAID - Combined query
      $prepostDateCondition = $isDaily 
         ? "DATE(insertTime) = '$today'" 
         : "DATE_FORMAT(insertTime, '%Y-%m') = '$today'";
      $where_prepost = $whereCabang . "tr_status = 1 AND bisnis = 'laundry' AND $prepostDateCondition";
      
      $cost_pre = $this->db(100)->sum_col_where('prepaid', 'price', $where_prepost);
      $cost_post = $this->db(100)->sum_col_where('postpaid', 'price', $where_prepost);
      $prepost_cost = $cost_pre + $cost_post;

      // Gaji Karyawan - single query with JOIN
      // Note: tgl in gaji_result stores 'YYYY-MM' format
      $gaji = 0;
      $gajiDateCondition = "tgl = '$today'";
      
      if (!$config['useCabang']) {
         // Semua cabang operasional (abaikan karyawan home-cabang training)
         $exTrainUser = $this->sqlExcludeTrainingCabang('u.id_cabang');
         $gajiSql = "SELECT SUM(gr.jumlah) as total 
                     FROM gaji_result gr 
                     INNER JOIN user u ON gr.id_karyawan = u.id_user 
                     WHERE {$exTrainUser} gr.tipe = 1 AND $gajiDateCondition";
      } else {
         // Specific branch - use JOIN instead of N+1 queries
         $gajiSql = "SELECT SUM(gr.jumlah) as total 
                     FROM gaji_result gr 
                     INNER JOIN user u ON gr.id_karyawan = u.id_user 
                     WHERE gr.tipe = 1 AND $gajiDateCondition AND u." . str_replace(' AND ', '', $this->wCabang);
      }
      
      $gajiResult = $this->db(0)->query_array($gajiSql);
      if ($gajiResult && is_array($gajiResult) && count($gajiResult) > 0) {
         $gaji = $gajiResult[0]['total'] ?? 0;
      }

      // Barang Pakai - dari Sales (barang_mutasi type=3), hanya modal (price*qty) tanpa margin
      $barangPakaiDateCondition = $isDaily 
         ? "DATE(created_at) = '$today'" 
         : "DATE_FORMAT(created_at, '%Y-%m') = '$today'";
      $barangPakaiWhere = "type = 3 AND $barangPakaiDateCondition";
      if ($config['useCabang']) {
         $barangPakaiWhere = "source_id = " . $this->id_cabang . " AND " . $barangPakaiWhere;
      } else {
         $barangPakaiWhere = $this->sqlExcludeTrainingCabang('source_id') . $barangPakaiWhere;
      }
      $barangPakaiSql = "SELECT COALESCE(SUM(price * qty), 0) as total FROM barang_mutasi WHERE $barangPakaiWhere";
      $barangPakaiResult = $this->db(0)->query_array($barangPakaiSql);
      $barang_pakai = 0;
      if ($barangPakaiResult && is_array($barangPakaiResult) && count($barangPakaiResult) > 0) {
         $barang_pakai = intval($barangPakaiResult[0]['total'] ?? 0);
      }

      // Margin Penjualan Barang - dari Sales (barang_mutasi type=1, state=1), total margin = SUM(margin * qty)
      $marginPenjualanDateCondition = $isDaily
         ? "DATE(created_at) = '$today'"
         : "DATE_FORMAT(created_at, '%Y-%m') = '$today'";
      $marginPenjualanWhere = "type = 1 AND state = 1 AND $marginPenjualanDateCondition";
      if ($config['useCabang']) {
         $marginPenjualanWhere = "source_id = " . $this->id_cabang . " AND " . $marginPenjualanWhere;
      } else {
         $marginPenjualanWhere = $this->sqlExcludeTrainingCabang('source_id') . $marginPenjualanWhere;
      }
      $marginPenjualanSql = "SELECT COALESCE(SUM(margin * qty), 0) as total FROM barang_mutasi WHERE $marginPenjualanWhere";
      $marginPenjualanResult = $this->db(0)->query_array($marginPenjualanSql);
      $margin_penjualan = 0;
      if ($marginPenjualanResult && is_array($marginPenjualanResult) && count($marginPenjualanResult) > 0) {
         $margin_penjualan = intval(round($marginPenjualanResult[0]['total'] ?? 0));
      }

      $snapshot = null;
      $snapshotMeta = null;
      if (!$isDaily && in_array((int) $mode, [2, 3], true)) {
         $snapshotMeta = $this->getRekapSnapshotStatus((int) $mode, $today);
         $snapshot = !empty($snapshotMeta['complete']) ? ($snapshotMeta['row'] ?? ['id' => 1]) : null;
      }

      return [
         'data_main' => $data_main,
         'dataTanggal' => $dataTanggal,
         'kasLaundry' => $kas_laundry,
         'whereUmum' => $where_umum,
         'whereKeluar' => $where_keluar,
         'whereMember' => $where_member,
         'whereTarik' => $where_tarik,
         'kasMember' => $kas_member,
         'kas_keluar' => $kas_keluar,
         'kas_tarik' => $kas_tarik,
         'prepost_cost' => $prepost_cost,
         'gaji' => $gaji,
         'barang_pakai' => $barang_pakai,
         'margin_penjualan' => $margin_penjualan,
         'snapshot' => $snapshot,
         'snapshot_meta' => $snapshotMeta,
         'rekap_mode' => (int) $mode,
      ];
   }

   /**
    * Simpan snapshot rekap bulanan. Hanya bulan yang telah berlalu.
    * Mode 2: satu cabang aktif. Mode 3: satu baris per cabang operasional (selalu mode=2 di DB).
    * POST: y, m — skip cabang yang sudah punya snapshot (silent).
    */
   public function snapshot($mode)
   {
      header('Content-Type: application/json; charset=utf-8');

      $mode = (int) $mode;
      if (!in_array($mode, [2, 3], true)) {
         echo json_encode(['ok' => false, 'msg' => 'Snapshot hanya untuk rekap bulanan']);
         return;
      }

      $year = isset($_POST['y']) ? (int) $_POST['y'] : 0;
      $monthNum = isset($_POST['m']) ? (int) $_POST['m'] : 0;
      if ($year < 2021 || $year > 2100 || $monthNum < 1 || $monthNum > 12) {
         echo json_encode(['ok' => false, 'msg' => 'Periode tidak valid']);
         return;
      }

      $periode = sprintf('%04d-%02d', $year, $monthNum);
      if ($periode >= date('Y-m')) {
         echo json_encode(['ok' => false, 'msg' => 'Hanya bulan yang telah berlalu yang bisa di-snapshot']);
         return;
      }

      $id_user = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
      $targets = [];

      if ($mode === 2) {
         $idCabang = (int) $this->id_cabang;
         if ($idCabang < 1) {
            echo json_encode(['ok' => false, 'msg' => 'Cabang tidak valid']);
            return;
         }
         $targets[] = $idCabang;
      } else {
         $list = $this->getCabangOperasional();
         if (!is_array($list)) {
            $list = [];
         }
         foreach ($list as $c) {
            $idC = (int) ($c['id_cabang'] ?? 0);
            if ($idC > 0) {
               $targets[] = $idC;
            }
         }
      }

      if (count($targets) < 1) {
         echo json_encode(['ok' => false, 'msg' => 'Tidak ada cabang untuk di-snapshot']);
         return;
      }

      $created = 0;
      $skipped = 0;
      $errors = 0;

      foreach ($targets as $idCabang) {
         $existing = $this->getRekapSnapshotRowForCabang($idCabang, $periode);
         if ($existing) {
            $skipped++;
            continue;
         }

         $agg = $this->hitungRekapBulananSnapshot($idCabang, $periode);
         if ($agg === null) {
            $errors++;
            continue;
         }

         $payload = [
            'periode' => $periode,
            'mode' => 2,
            'id_cabang' => (int) $idCabang,
            'kas_laundry' => (int) $agg['kas_laundry'],
            'kas_member' => (int) $agg['kas_member'],
            'margin_penjualan' => (int) $agg['margin_penjualan'],
            'total_pendapatan' => (int) $agg['total_pendapatan'],
            'kas_keluar_json' => json_encode($agg['kas_keluar'], JSON_UNESCAPED_UNICODE),
            'gaji' => (int) $agg['gaji'],
            'prepost_cost' => (int) $agg['prepost_cost'],
            'barang_pakai' => (int) $agg['barang_pakai'],
            'total_pengeluaran' => (int) $agg['total_pengeluaran'],
            'laba_rugi' => (int) $agg['laba_rugi'],
            'qty_json' => json_encode($agg['qty'], JSON_UNESCAPED_UNICODE),
            'id_user' => $id_user,
         ];

         $do = $this->db(0)->insert('rekap_snapshot', $payload);
         if (($do['errno'] ?? 1) != 0) {
            $this->model('Log')->write('[Rekap::snapshot] Insert error cabang ' . $idCabang . ': ' . ($do['error'] ?? ''));
            $errors++;
            continue;
         }
         $created++;
      }

      $status = $this->getRekapSnapshotStatus($mode, $periode);
      $allDone = !empty($status['complete']);

      if ($created < 1 && $skipped > 0 && $errors < 1) {
         echo json_encode([
            'ok' => false,
            'exists' => true,
            'msg' => 'Snapshot periode ini sudah ada',
            'created' => $created,
            'skipped' => $skipped,
         ]);
         return;
      }

      if ($created < 1 && $errors > 0) {
         echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan snapshot']);
         return;
      }

      echo json_encode([
         'ok' => true,
         'msg' => $mode === 3
            ? ("Snapshot cabang: {$created} baru, {$skipped} dilewati")
            : 'Snapshot berhasil disimpan',
         'periode' => $periode,
         'created' => $created,
         'skipped' => $skipped,
         'errors' => $errors,
         'complete' => $allDone,
      ]);
   }

   function detail($where, $mode = 1)
   {
      $viewData = 'rekap/rekap_bulanan_detail';
      $data_operasi = ['title' => 'Bulanan Cabang - Rekap'];
      $this->view('layout', ['data_operasi' => $data_operasi]);

      $where = base64_decode($where);
      $data = $this->db(0)->get_where('kas', $where);

      $this->view($viewData, [
         'data' => $data,
         'mode' => $mode
      ]);
   }

   /**
    * JSON: rincian Pre/Post Paid per cabang (GET y, m, d sama filter Rekap).
    */
   public function prepost_detail($mode = 1)
   {
      $this->session_cek(1);
      $this->operating_data();

      $modeConfig = [
         1 => ['type' => 'daily', 'useCabang' => true],
         2 => ['type' => 'monthly', 'useCabang' => true],
         3 => ['type' => 'monthly', 'useCabang' => false],
         4 => ['type' => 'daily', 'useCabang' => false],
      ];

      $mode = (int) $mode;
      if (!isset($modeConfig[$mode])) {
         header('Content-Type: application/json; charset=utf-8');
         echo json_encode(['ok' => false, 'msg' => 'Invalid mode']);
         return;
      }

      $config = $modeConfig[$mode];
      $isDaily = $config['type'] === 'daily';

      $year = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
      if ($year < 2000 || $year > 2100) {
         $year = (int) date('Y');
      }
      $monthNum = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m');
      if ($monthNum < 1 || $monthNum > 12) {
         $monthNum = (int) date('m');
      }
      $month = str_pad((string) $monthNum, 2, '0', STR_PAD_LEFT);

      if ($isDaily) {
         $dayNum = isset($_GET['d']) ? (int) $_GET['d'] : (int) date('d');
         if ($dayNum < 1 || $dayNum > 31) {
            $dayNum = (int) date('d');
         }
         $day = str_pad((string) $dayNum, 2, '0', STR_PAD_LEFT);
         $today = "$year-$month-$day";
         $dateCondition = "DATE(insertTime) = '$today'";
         $periodLabel = "$day/$month/$year";
      } else {
         $today = "$year-$month";
         $dateCondition = "DATE_FORMAT(insertTime, '%Y-%m') = '$today'";
         $periodLabel = "$month/$year";
      }

      $whereCabang = $config['useCabang']
         ? 'id_cabang = ' . (int) $this->id_cabang . ' AND '
         : $this->sqlExcludeTrainingCabang('id_cabang');
      $baseWhere = "{$whereCabang}tr_status = 1 AND bisnis = 'laundry' AND {$dateCondition}";

      $preSql = "SELECT id_cabang, COALESCE(SUM(price),0) AS total FROM prepaid WHERE {$baseWhere} GROUP BY id_cabang";
      $postSql = "SELECT id_cabang, COALESCE(SUM(price),0) AS total FROM postpaid WHERE {$baseWhere} GROUP BY id_cabang";

      $preRows = $this->db(100)->query_array($preSql);
      $postRows = $this->db(100)->query_array($postSql);
      if (!is_array($preRows)) {
         $preRows = [];
      }
      if (!is_array($postRows)) {
         $postRows = [];
      }

      $byBranch = [];
      foreach ($preRows as $r) {
         $id = (int) ($r['id_cabang'] ?? 0);
         if ($id < 1) {
            continue;
         }
         if (!isset($byBranch[$id])) {
            $byBranch[$id] = ['pre' => 0, 'post' => 0];
         }
         $byBranch[$id]['pre'] = (int) $r['total'];
      }
      foreach ($postRows as $r) {
         $id = (int) ($r['id_cabang'] ?? 0);
         if ($id < 1) {
            continue;
         }
         if (!isset($byBranch[$id])) {
            $byBranch[$id] = ['pre' => 0, 'post' => 0];
         }
         $byBranch[$id]['post'] = (int) $r['total'];
      }

      $cabangRows = $this->getCabangOperasional();
      if (!is_array($cabangRows)) {
         $cabangRows = [];
      }
      $cabangMap = [];
      foreach ($cabangRows as $c) {
         $cabangMap[(int) $c['id_cabang']] = $c;
      }

      $detailsByCabang = $this->rekapPrepostDetailsByCabang($baseWhere);

      $rows = [];
      foreach ($byBranch as $id => $tot) {
         $pre = $tot['pre'];
         $post = $tot['post'];
         $total = $pre + $post;
         if ($total <= 0) {
            continue;
         }
         $c = $cabangMap[$id] ?? null;
         $nama = '';
         if ($c) {
            $nama = trim((string) ($c['kode_cabang'] ?? ''));
         }
         if ($nama === '') {
            $nama = 'Cabang #' . $id;
         }
         $rows[] = [
            'id_cabang' => $id,
            'nama' => $nama,
            'prepaid' => $pre,
            'postpaid' => $post,
            'total' => $total,
            'details' => $detailsByCabang[$id] ?? [],
         ];
      }

      usort($rows, function ($a, $b) {
         return $b['total'] <=> $a['total'];
      });

      $grandPre = 0;
      $grandPost = 0;
      foreach ($rows as $r) {
         $grandPre += $r['prepaid'];
         $grandPost += $r['postpaid'];
      }

      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
         'ok' => true,
         'rows' => $rows,
         'grand' => [
            'prepaid' => $grandPre,
            'postpaid' => $grandPost,
            'total' => $grandPre + $grandPost,
         ],
         'period_label' => $periodLabel,
      ]);
   }

   /** JSON rincian transaksi untuk satu jenis pengeluaran kas di Rekap. */
   public function pengeluaran_detail($mode = 1)
   {
      $this->session_cek(1);
      $this->operating_data();
      $period = $this->rekapPeriodFromMode($mode);
      if (!$period['ok']) { $this->jsonRekapDetailError($period['msg']); return; }
      $jenis = trim((string) ($_GET['jenis'] ?? ''));
      if ($jenis === '') { $this->jsonRekapDetailError('Jenis pengeluaran tidak valid'); return; }
      $dateCondition = $period['isDaily'] ? "DATE(insertTime) = '{$period['today']}'" : "DATE_FORMAT(insertTime, '%Y-%m') = '{$period['today']}'";
      $whereCabang = $period['source_id'] !== null
         ? 'id_cabang = ' . (int) $period['source_id'] . ' AND '
         : $this->sqlExcludeTrainingCabang('id_cabang');
      $jenisEsc = $this->db(0)->escape($jenis);
      $summaryOnly = !empty($period['show_cabang']);
      $rows = $this->db(0)->query_array($summaryOnly
         ? "SELECT id_cabang, note_primary, SUM(jumlah) AS jumlah FROM kas WHERE {$whereCabang}status_mutasi <> 4
              AND jenis_transaksi IN (4,8) AND note_primary = '$jenisEsc' AND {$dateCondition}
              GROUP BY id_cabang, note_primary ORDER BY jumlah DESC, id_cabang ASC"
         : "SELECT id_cabang, note, jumlah, insertTime, jenis_transaksi FROM kas WHERE {$whereCabang}status_mutasi <> 4
              AND jenis_transaksi IN (4,8) AND note_primary = '$jenisEsc' AND {$dateCondition}
              ORDER BY insertTime DESC, id_kas DESC"
      );
      if (!is_array($rows)) { $rows = []; }
      $cabangMap = $this->rekapCabangMap();
      $total = 0; $out = [];
      foreach ($rows as $r) {
         $amount = (int) ($r['jumlah'] ?? 0); $total += $amount;
         $out[] = ['tanggal' => (string) ($r['insertTime'] ?? ''), 'note' => (string) ($r['note'] ?? ''), 'jumlah' => $amount, 'cabang' => $this->rekapCabangLabel((int) ($r['id_cabang'] ?? 0), $cabangMap)];
      }
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => true, 'jenis' => $jenis, 'rows' => $out, 'total' => $total, 'show_cabang' => !empty($period['show_cabang']), 'summary_only' => $summaryOnly, 'period_label' => $period['period_label']]);
   }

   /**
    * JSON: rincian Margin Penjualan Barang (barang_mutasi type=1, state=1).
    */
   public function margin_penjualan_detail($mode = 1)
   {
      $this->session_cek(1);
      $this->operating_data();

      $period = $this->rekapPeriodFromMode($mode);
      if (!$period['ok']) {
         $this->jsonRekapDetailError($period['msg']);
         return;
      }

      $dateCondition = $period['isDaily']
         ? "DATE(created_at) = '{$period['today']}'"
         : "DATE_FORMAT(created_at, '%Y-%m') = '{$period['today']}'";
      $where = "type = 1 AND state = 1 AND {$dateCondition}";
      if ($period['source_id'] !== null) {
         $where = 'source_id = ' . (int) $period['source_id'] . ' AND ' . $where;
      } else {
         $where = $this->sqlExcludeTrainingCabang('source_id') . $where;
      }

      $items = $this->db(0)->get_where('barang_mutasi', $where . ' ORDER BY created_at DESC, ref ASC, id ASC');
      if (!is_array($items)) {
         $items = $items ? iterator_to_array($items) : [];
      }

      $cabangMap = $this->rekapCabangMap();
      $rows = [];
      $grand = 0;
      foreach ($items as $item) {
         $qty = floatval($item['qty'] ?? 0);
         $margin = floatval($item['margin'] ?? 0);
         $subtotal = (int) round($margin * $qty);
         if ($subtotal <= 0) {
            continue;
         }
         $grand += $subtotal;
         $rows[] = [
            'ref' => (string) ($item['ref'] ?? ''),
            'tanggal' => $this->rekapFormatDateTime($item['created_at'] ?? ''),
            'cabang' => $period['show_cabang'] ? $this->rekapCabangLabel((int) ($item['source_id'] ?? 0), $cabangMap) : '',
            'barang' => $this->rekapNamaBarang((int) ($item['id_barang'] ?? 0)),
            'qty' => $qty,
            'margin' => (int) round($margin),
            'subtotal' => $subtotal,
         ];
      }

      $rekap = $this->rekapAgregatPerBarang($rows);
      $this->jsonRekapDetailOk($rows, $grand, $period['period_label'], $rekap);
   }

   /**
    * JSON: rincian Barang Pakai (barang_mutasi type=3, modal = price * qty).
    */
   public function barang_pakai_detail($mode = 1)
   {
      $this->session_cek(1);
      $this->operating_data();

      $period = $this->rekapPeriodFromMode($mode);
      if (!$period['ok']) {
         $this->jsonRekapDetailError($period['msg']);
         return;
      }

      $dateCondition = $period['isDaily']
         ? "DATE(created_at) = '{$period['today']}'"
         : "DATE_FORMAT(created_at, '%Y-%m') = '{$period['today']}'";
      $where = "type = 3 AND {$dateCondition}";
      if ($period['source_id'] !== null) {
         $where = 'source_id = ' . (int) $period['source_id'] . ' AND ' . $where;
      } else {
         $where = $this->sqlExcludeTrainingCabang('source_id') . $where;
      }

      $items = $this->db(0)->get_where('barang_mutasi', $where . ' ORDER BY created_at DESC, ref ASC, id ASC');
      if (!is_array($items)) {
         $items = $items ? iterator_to_array($items) : [];
      }

      $cabangMap = $this->rekapCabangMap();
      $rows = [];
      $grand = 0;
      foreach ($items as $item) {
         $qty = floatval($item['qty'] ?? 0);
         $price = floatval($item['price'] ?? 0);
         $subtotal = (int) round($price * $qty);
         if ($subtotal <= 0) {
            continue;
         }
         $grand += $subtotal;
         $rows[] = [
            'ref' => (string) ($item['ref'] ?? ''),
            'tanggal' => $this->rekapFormatDateTime($item['created_at'] ?? ''),
            'cabang' => $period['show_cabang'] ? $this->rekapCabangLabel((int) ($item['source_id'] ?? 0), $cabangMap) : '',
            'barang' => $this->rekapNamaBarang((int) ($item['id_barang'] ?? 0)),
            'qty' => $qty,
            'harga' => (int) round($price),
            'subtotal' => $subtotal,
         ];
      }

      $rekap = $this->rekapAgregatPerBarang($rows);
      $this->jsonRekapDetailOk($rows, $grand, $period['period_label'], $rekap);
   }

   /**
    * Rincian transaksi Pre/Post Paid per cabang (untuk expand di modal Rekap).
    */
   private function rekapPrepostDetailsByCabang($baseWhere)
   {
      $byCabang = [];

      $preItems = $this->db(100)->get_where('prepaid', $baseWhere . ' ORDER BY insertTime DESC');
      if (!is_array($preItems)) {
         $preItems = $preItems ? iterator_to_array($preItems) : [];
      }
      foreach ($preItems as $item) {
         $id = (int) ($item['id_cabang'] ?? 0);
         if ($id < 1) {
            continue;
         }
         $code = strtoupper(trim((string) ($item['product_code'] ?? '')));
         $customer = trim((string) ($item['customer_id'] ?? ''));
         $keterangan = $code;
         if ($customer !== '') {
            $keterangan = ($keterangan !== '' ? $keterangan . ' · ' : '') . $customer;
         }
         if ($keterangan === '') {
            $keterangan = 'Prepaid #' . ($item['id'] ?? '');
         }
         if (!isset($byCabang[$id])) {
            $byCabang[$id] = [];
         }
         $byCabang[$id][] = [
            'tipe' => 'Prepaid',
            'keterangan' => $keterangan,
            'tanggal' => $this->rekapFormatDateTime($item['insertTime'] ?? ''),
            'jumlah' => (int) ($item['price'] ?? 0),
            '_sort' => (string) ($item['insertTime'] ?? ''),
         ];
      }

      $postItems = $this->db(100)->get_where('postpaid', $baseWhere . ' ORDER BY insertTime DESC');
      if (!is_array($postItems)) {
         $postItems = $postItems ? iterator_to_array($postItems) : [];
      }
      foreach ($postItems as $item) {
         $id = (int) ($item['id_cabang'] ?? 0);
         if ($id < 1) {
            continue;
         }
         $code = strtoupper(trim((string) ($item['code'] ?? '')));
         $trName = trim((string) ($item['tr_name'] ?? ''));
         $customer = trim((string) ($item['customer_id'] ?? ''));
         $parts = array_filter([$code, $trName, $customer], function ($p) {
            return $p !== '';
         });
         $keterangan = implode(' · ', $parts);
         if ($keterangan === '') {
            $keterangan = 'Postpaid #' . ($item['id'] ?? '');
         }
         if (!isset($byCabang[$id])) {
            $byCabang[$id] = [];
         }
         $byCabang[$id][] = [
            'tipe' => 'Postpaid',
            'keterangan' => $keterangan,
            'tanggal' => $this->rekapFormatDateTime($item['insertTime'] ?? ''),
            'jumlah' => (int) ($item['price'] ?? 0),
            '_sort' => (string) ($item['insertTime'] ?? ''),
         ];
      }

      foreach ($byCabang as $id => &$list) {
         usort($list, function ($a, $b) {
            return strcmp($b['_sort'] ?? '', $a['_sort'] ?? '');
         });
         foreach ($list as &$row) {
            unset($row['_sort']);
         }
         unset($row);
      }
      unset($list);

      return $byCabang;
   }

   /**
    * Agregat qty & jumlah per nama barang dari baris rincian.
    */
   private function rekapAgregatPerBarang(array $rows)
   {
      $by = [];
      foreach ($rows as $r) {
         $nama = trim((string) ($r['barang'] ?? ''));
         if ($nama === '') {
            $nama = '-';
         }
         if (!isset($by[$nama])) {
            $by[$nama] = ['barang' => $nama, 'qty' => 0.0, 'total' => 0];
         }
         $by[$nama]['qty'] += floatval($r['qty'] ?? 0);
         $by[$nama]['total'] += (int) ($r['subtotal'] ?? 0);
      }

      $rekap = array_values($by);
      usort($rekap, function ($a, $b) {
         $cmp = $b['total'] <=> $a['total'];
         if ($cmp !== 0) {
            return $cmp;
         }
         return strcmp($a['barang'], $b['barang']);
      });

      foreach ($rekap as &$item) {
         $item['qty'] = round($item['qty'], 4);
         $item['total'] = (int) $item['total'];
      }
      unset($item);

      return $rekap;
   }

   private function rekapPeriodFromMode($mode)
   {
      $modeConfig = [
         1 => ['type' => 'daily', 'useCabang' => true],
         2 => ['type' => 'monthly', 'useCabang' => true],
         3 => ['type' => 'monthly', 'useCabang' => false],
         4 => ['type' => 'daily', 'useCabang' => false],
      ];

      $mode = (int) $mode;
      if (!isset($modeConfig[$mode])) {
         return ['ok' => false, 'msg' => 'Invalid mode'];
      }

      $config = $modeConfig[$mode];
      $isDaily = $config['type'] === 'daily';

      $year = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
      if ($year < 2000 || $year > 2100) {
         $year = (int) date('Y');
      }
      $monthNum = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m');
      if ($monthNum < 1 || $monthNum > 12) {
         $monthNum = (int) date('m');
      }
      $month = str_pad((string) $monthNum, 2, '0', STR_PAD_LEFT);

      if ($isDaily) {
         $dayNum = isset($_GET['d']) ? (int) $_GET['d'] : (int) date('d');
         if ($dayNum < 1 || $dayNum > 31) {
            $dayNum = (int) date('d');
         }
         $day = str_pad((string) $dayNum, 2, '0', STR_PAD_LEFT);
         $today = "$year-$month-$day";
         $periodLabel = "$day/$month/$year";
      } else {
         $today = "$year-$month";
         $periodLabel = "$month/$year";
      }

      return [
         'ok' => true,
         'isDaily' => $isDaily,
         'today' => $today,
         'period_label' => $periodLabel,
         'source_id' => $config['useCabang'] ? (int) $this->id_cabang : null,
         'show_cabang' => !$config['useCabang'],
      ];
   }

   private function rekapCabangMap()
   {
      $cabangRows = $this->getCabangOperasional();
      if (!is_array($cabangRows)) {
         $cabangRows = [];
      }
      $map = [];
      foreach ($cabangRows as $c) {
         $map[(int) $c['id_cabang']] = $c;
      }
      return $map;
   }

   private function rekapCabangLabel($id, array $cabangMap)
   {
      if ($id < 1) {
         return '';
      }
      $c = $cabangMap[$id] ?? null;
      if (!$c) {
         return 'Cabang #' . $id;
      }
      $nama = trim((string) ($c['kode_cabang'] ?? ''));
      return $nama !== '' ? $nama : 'Cabang #' . $id;
   }

   private function rekapNamaBarang($id_barang)
   {
      if ($id_barang < 1) {
         return '-';
      }
      $idEsc = $this->db(0)->escape((string) $id_barang);
      $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '$idEsc'");
      if (!is_array($barang)) {
         return 'Barang #' . $id_barang;
      }
      $nama = trim((string) ($barang['nama'] ?? ''));
      if ($nama !== '') {
         return $nama;
      }
      return strtoupper(trim(($barang['brand'] ?? '') . ' ' . ($barang['model'] ?? '')));
   }

   private function rekapFormatDateTime($dt)
   {
      if ($dt === '' || $dt === null) {
         return '-';
      }
      $ts = strtotime((string) $dt);
      if ($ts === false) {
         return '-';
      }
      return date('d/m/Y H:i', $ts);
   }

   private function jsonRekapDetailError($msg)
   {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => false, 'msg' => $msg]);
   }

   private function jsonRekapDetailOk(array $rows, $grandTotal, $periodLabel, array $rekap = [])
   {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
         'ok' => true,
         'rows' => $rows,
         'rekap' => $rekap,
         'grand' => ['total' => (int) $grandTotal],
         'period_label' => $periodLabel,
      ]);
   }

   private function getRekapSnapshotRowForCabang($id_cabang, $periode)
   {
      $id_cabang = (int) $id_cabang;
      $periodeEsc = $this->db(0)->escape($periode);
      $row = $this->db(0)->get_where_row(
         'rekap_snapshot',
         "periode = '$periodeEsc' AND mode = 2 AND id_cabang = $id_cabang"
      );
      return is_array($row) && !empty($row['id']) ? $row : null;
   }

   /**
    * Status snapshot untuk UI.
    * Mode 2: cabang aktif. Mode 3: semua cabang operasional harus punya baris mode=2.
    */
   private function getRekapSnapshotStatus($mode, $periode)
   {
      $mode = (int) $mode;
      $periodeEsc = $this->db(0)->escape($periode);

      if ($mode === 2) {
         $row = $this->getRekapSnapshotRowForCabang((int) $this->id_cabang, $periode);
         return [
            'complete' => $row !== null,
            'count' => $row ? 1 : 0,
            'total' => 1,
            'row' => $row,
         ];
      }

      $list = $this->getCabangOperasional();
      if (!is_array($list)) {
         $list = [];
      }
      $ids = [];
      foreach ($list as $c) {
         $idC = (int) ($c['id_cabang'] ?? 0);
         if ($idC > 0) {
            $ids[] = $idC;
         }
      }
      $total = count($ids);
      if ($total < 1) {
         return ['complete' => false, 'count' => 0, 'total' => 0, 'row' => null];
      }

      $idList = implode(',', $ids);
      $rows = $this->db(0)->query_array(
         "SELECT id, id_cabang, created_at, updated_at FROM rekap_snapshot
          WHERE periode = '$periodeEsc' AND mode = 2 AND id_cabang IN ($idList)"
      );
      if (!is_array($rows)) {
         $rows = [];
      }
      $count = count($rows);
      $latest = null;
      foreach ($rows as $r) {
         $ts = $r['updated_at'] ?: ($r['created_at'] ?? '');
         if ($latest === null || $ts > ($latest['updated_at'] ?: ($latest['created_at'] ?? ''))) {
            $latest = $r;
         }
      }

      return [
         'complete' => $count >= $total,
         'count' => $count,
         'total' => $total,
         'row' => $latest,
      ];
   }

   /**
    * Hitung angka rekap bulanan untuk satu cabang (disimpan sebagai mode=2).
    */
   private function hitungRekapBulananSnapshot($id_cabang, $periode)
   {
      $id_cabang = (int) $id_cabang;
      if ($id_cabang < 1) {
         return null;
      }
      $whereCabang = "id_cabang = $id_cabang AND ";

      $id_pengeluaran = 102;
      $tgl_pertama = $periode . '-01';
      $itemPengeluaran = $this->db(0)->get_where_row('item_pengeluaran', "id_item_pengeluaran = '$id_pengeluaran'");
      $jenis_nama = $itemPengeluaran['item_pengeluaran'] ?? 'Rekap Bulanan';

      $cabang = $this->db(0)->get_where_row('cabang', "id_cabang = $id_cabang");
      $listCabang = $cabang ? [$cabang] : [];

      foreach ($listCabang as $cabangRow) {
         // Cabang training tidak boleh auto-insert rent ke Kas Besar
         if (!empty($cabangRow['is_training']) || $this->isTrainingCabangId($id_cabang)) {
            continue;
         }
         $rent = intval($cabangRow['rent'] ?? 0);
         if ($rent <= 0) {
            continue;
         }
         $whereCek = "jenis_transaksi = 8 AND ref_transaksi = '$id_pengeluaran' AND id_cabang = $id_cabang AND DATE_FORMAT(insertTime, '%Y-%m') = '$periode'";
         $ada = $this->db(0)->count_where('kas', $whereCek);
         if ($ada < 1) {
            $dataKas = [
               'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
               'id_cabang' => $id_cabang,
               'jenis_mutasi' => 2,
               'jenis_transaksi' => 8,
               'metode_mutasi' => 2,
               'note' => 'Auto dari Rekap ' . $periode . ' (rent)',
               'note_primary' => $jenis_nama,
               'status_mutasi' => 3,
               'jumlah' => $rent,
               'id_user' => $_SESSION[URL::SESSID]['user']['id_user'] ?? 0,
               'id_client' => 0,
               'ref_transaksi' => $id_pengeluaran,
               'insertTime' => $tgl_pertama . ' 00:00:00',
            ];
            $do = $this->db(0)->insert('kas', $dataKas);
            if (($do['errno'] ?? 0) != 0) {
               $this->model('Log')->write('[Rekap::snapshot] Auto insert Kas Besar error: ' . ($do['error'] ?? ''));
            }
         }
      }

      $kasSql = "SELECT jenis_transaksi, ref_transaksi, note_primary, SUM(jumlah) as total
                 FROM kas
                 WHERE {$whereCabang} status_mutasi <> 4 AND DATE_FORMAT(insertTime, '%Y-%m') = '$periode'
                 GROUP BY jenis_transaksi, ref_transaksi, note_primary";
      $kasResult = $this->db(0)->query_array($kasSql);
      if (!is_array($kasResult)) {
         $kasResult = [];
      }

      $nonExpenseIds = [];
      $neRows = $this->db(0)->get_where('item_pengeluaran', 'is_expense = 0');
      if (!is_array($neRows)) {
         $neRows = $neRows ? iterator_to_array($neRows) : [];
      }
      foreach ($neRows as $r) {
         $idNe = (int) ($r['id_item_pengeluaran'] ?? 0);
         if ($idNe > 0) {
            $nonExpenseIds[$idNe] = true;
         }
      }

      $kas_laundry = 0;
      $kas_member = 0;
      $kas_keluar = [];
      $rent_total = 0;

      foreach ($kasResult as $row) {
         switch ($row['jenis_transaksi']) {
            case 1:
               $kas_laundry += $row['total'];
               break;
            case 3:
               $kas_member += $row['total'];
               break;
            case 4:
               $ref4 = (int) ($row['ref_transaksi'] ?? 0);
               if ($ref4 > 0 && isset($nonExpenseIds[$ref4])) {
                  break;
               }
               $kas_keluar[] = ['note_primary' => $row['note_primary'], 'total' => (int) $row['total']];
               break;
            case 8:
            case '8':
               $ref = intval($row['ref_transaksi'] ?? 0);
               if ($ref > 0 && isset($nonExpenseIds[$ref])) {
                  break;
               }
               if ($ref == 102) {
                  $rent_total += $row['total'];
               } else {
                  $kas_keluar[] = ['note_primary' => $row['note_primary'], 'total' => (int) $row['total']];
               }
               break;
         }
      }

      if ($rent_total > 0) {
         $itemRent = $this->db(0)->get_where_row('item_pengeluaran', "id_item_pengeluaran = '102'");
         $rent_nama = $itemRent['item_pengeluaran'] ?? 'Rekap Bulanan';
         $kas_keluar[] = ['note_primary' => $rent_nama, 'total' => (int) $rent_total];
      } elseif (!isset($nonExpenseIds[102])) {
         $total_rent = 0;
         foreach ($listCabang as $c) {
            $total_rent += intval($c['rent'] ?? 0);
         }
         if ($total_rent > 0) {
            $itemRent = $this->db(0)->get_where_row('item_pengeluaran', "id_item_pengeluaran = '102'");
            $rent_nama = $itemRent['item_pengeluaran'] ?? 'Rekap Bulanan';
            $kas_keluar[] = ['note_primary' => $rent_nama, 'total' => (int) $total_rent];
         }
      }

      $where_prepost = $whereCabang . "tr_status = 1 AND bisnis = 'laundry' AND DATE_FORMAT(insertTime, '%Y-%m') = '$periode'";
      $cost_pre = $this->db(100)->sum_col_where('prepaid', 'price', $where_prepost);
      $cost_post = $this->db(100)->sum_col_where('postpaid', 'price', $where_prepost);
      $prepost_cost = (int) $cost_pre + (int) $cost_post;

      $gajiSql = "SELECT SUM(gr.jumlah) as total
                  FROM gaji_result gr
                  INNER JOIN user u ON gr.id_karyawan = u.id_user
                  WHERE gr.tipe = 1 AND gr.tgl = '$periode' AND u.id_cabang = $id_cabang";
      $gajiResult = $this->db(0)->query_array($gajiSql);
      $gaji = 0;
      if ($gajiResult && is_array($gajiResult) && count($gajiResult) > 0) {
         $gaji = (int) ($gajiResult[0]['total'] ?? 0);
      }

      $barangPakaiSql = "SELECT COALESCE(SUM(price * qty), 0) as total FROM barang_mutasi
                         WHERE source_id = $id_cabang AND type = 3 AND DATE_FORMAT(created_at, '%Y-%m') = '$periode'";
      $barangPakaiResult = $this->db(0)->query_array($barangPakaiSql);
      $barang_pakai = 0;
      if ($barangPakaiResult && is_array($barangPakaiResult) && count($barangPakaiResult) > 0) {
         $barang_pakai = intval($barangPakaiResult[0]['total'] ?? 0);
      }

      $marginPenjualanSql = "SELECT COALESCE(SUM(margin * qty), 0) as total FROM barang_mutasi
                             WHERE source_id = $id_cabang AND type = 1 AND state = 1 AND DATE_FORMAT(created_at, '%Y-%m') = '$periode'";
      $marginPenjualanResult = $this->db(0)->query_array($marginPenjualanSql);
      $margin_penjualan = 0;
      if ($marginPenjualanResult && is_array($marginPenjualanResult) && count($marginPenjualanResult) > 0) {
         $margin_penjualan = intval(round($marginPenjualanResult[0]['total'] ?? 0));
      }

      $whereSale = $whereCabang . "bin = 0 AND DATE_FORMAT(insertTime, '%Y-%m') = '$periode'";
      $data_main = $this->db(0)->get_where('sale', $whereSale);
      if (!is_array($data_main)) {
         $data_main = $data_main ? iterator_to_array($data_main) : [];
      }

      $rekapQty = [];
      $rekap = [];
      foreach ($data_main as $a) {
         $jenisId = (int) ($a['id_penjualan_jenis'] ?? 0);
         $serLayanan = $a['list_layanan'] ?? '';
         $qty = (float) ($a['qty'] ?? 0);
         if (isset($rekap[$jenisId][$serLayanan])) {
            $rekap[$jenisId][$serLayanan] += $qty;
         } else {
            $rekap[$jenisId][$serLayanan] = $qty;
         }
         if (isset($rekapQty[$jenisId])) {
            $rekapQty[$jenisId] += $qty;
         } else {
            $rekapQty[$jenisId] = $qty;
         }
      }

      $penjualanMap = [];
      foreach ($this->dPenjualan as $b) {
         $penjualanMap[(int) $b['id_penjualan_jenis']] = $b;
      }
      $satuanMap = [];
      foreach ($this->dSatuan as $sa) {
         $satuanMap[(int) $sa['id_satuan']] = $sa['nama_satuan'] ?? '';
      }
      $layananMap = [];
      foreach ($this->dLayanan as $e) {
         $layananMap[(int) $e['id_layanan']] = $e['layanan'] ?? '';
      }

      $qtySummary = [];
      foreach ($rekapQty as $jenisId => $qty) {
         $b = $penjualanMap[(int) $jenisId] ?? null;
         $unit = '';
         $jenisNama = 'Jenis #' . $jenisId;
         if ($b) {
            $jenisNama = $b['penjualan_jenis'] ?? $jenisNama;
            $unit = $satuanMap[(int) ($b['id_satuan'] ?? 0)] ?? '';
         }
         $qtySummary[] = [
            'id_penjualan_jenis' => (int) $jenisId,
            'jenis' => $jenisNama,
            'qty' => $qty,
            'unit' => $unit,
         ];
      }

      $qtyDetail = [];
      foreach ($rekap as $jenisId => $byLayanan) {
         $b = $penjualanMap[(int) $jenisId] ?? null;
         $unit = '';
         $jenisNama = 'Jenis #' . $jenisId;
         if ($b) {
            $jenisNama = $b['penjualan_jenis'] ?? $jenisNama;
            $unit = $satuanMap[(int) ($b['id_satuan'] ?? 0)] ?? '';
         }
         foreach ($byLayanan as $serLayanan => $qty) {
            $arrLayanan = @unserialize($serLayanan);
            $layananNama = '';
            if (is_array($arrLayanan)) {
               foreach ($arrLayanan as $d) {
                  $layananNama .= ' ' . ($layananMap[(int) $d] ?? '');
               }
            }
            $qtyDetail[] = [
               'id_penjualan_jenis' => (int) $jenisId,
               'jenis' => $jenisNama,
               'layanan' => trim($layananNama),
               'qty' => $qty,
               'unit' => $unit,
            ];
         }
      }

      $total_keluar = 0;
      foreach ($kas_keluar as $a) {
         $total_keluar += (int) $a['total'];
      }
      $total_keluar += $gaji + $prepost_cost + $barang_pakai;
      $total_pendapatan = (int) $kas_laundry + (int) $kas_member + (int) $margin_penjualan;

      return [
         'kas_laundry' => (int) $kas_laundry,
         'kas_member' => (int) $kas_member,
         'margin_penjualan' => (int) $margin_penjualan,
         'total_pendapatan' => $total_pendapatan,
         'kas_keluar' => $kas_keluar,
         'gaji' => $gaji,
         'prepost_cost' => $prepost_cost,
         'barang_pakai' => $barang_pakai,
         'total_pengeluaran' => $total_keluar,
         'laba_rugi' => $total_pendapatan - $total_keluar,
         'qty' => [
            'summary' => $qtySummary,
            'detail' => $qtyDetail,
         ],
      ];
   }
}
