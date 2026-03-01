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
      $whereCabang = $config['useCabang'] ? $this->wCabang . " AND " : "";

      // Parse date from POST or use current date
      if (isset($_POST['m'])) {
         $year = $_POST['y'];
         $month = $_POST['m'];
         $day = $_POST['d'] ?? '01';
         
         if ($isDaily) {
            $today = "$year-$month-$day";
            $dataTanggal = ['tanggal' => $day, 'bulan' => $month, 'tahun' => $year];
         } else {
            $today = "$year-$month";
            $dataTanggal = ['bulan' => $month, 'tahun' => $year];
         }
      } else {
         if ($isDaily) {
            $today = date('Y-m-d');
            $dataTanggal = ['tanggal' => date('d'), 'bulan' => date('m'), 'tahun' => date('Y')];
         } else {
            $today = date('Y-m');
            $dataTanggal = ['bulan' => date('m'), 'tahun' => date('Y')];
         }
      }

      $data_operasi = ['title' => $config['title']];
      if (isset($config['vLaundry'])) {
         $data_operasi['vLaundry'] = true;
      }

      // OPTIMIZED: Single query for sale data with date range
      $dateCondition = $isDaily 
         ? "DATE(insertTime) = '$today'" 
         : "DATE_FORMAT(insertTime, '%Y-%m') = '$today'";
      
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
            $listCabang = $this->db(0)->get('cabang');
            if (!is_array($listCabang)) $listCabang = [];
         }

         foreach ($listCabang as $cabang) {
            $id_cabang = $cabang['id_cabang'];
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

      // OPTIMIZED: Combined KAS query - single query for all jenis_transaksi
      $kasDateCondition = $isDaily 
         ? "DATE(insertTime) = '$today'" 
         : "DATE_FORMAT(insertTime, '%Y-%m') = '$today'";
      
      $kasSql = "SELECT jenis_transaksi, note_primary, SUM(jumlah) as total 
                 FROM kas 
                 WHERE {$whereCabang} status_mutasi <> 4 AND $kasDateCondition
                 GROUP BY jenis_transaksi, note_primary";
      $kasResult = $this->db(0)->query_array($kasSql);

      // Parse combined result
      $kas_laundry = 0;
      $kas_member = 0;
      $kas_keluar = [];
      $kas_tarik = [];

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
               $kas_keluar[] = ['note_primary' => $row['note_primary'], 'total' => $row['total']];
               break;
            case 8: // Pengeluaran Kas Besar (termasuk rent id 102)
            case '8': // MySQL bisa return string
               $kas_keluar[] = ['note_primary' => $row['note_primary'], 'total' => $row['total']];
               break;
         }
      }

      // Fallback: rent dari cabang jika belum ada di kas_keluar (bulanan) - jamin tampil meski insert gagal
      if (!$isDaily && isset($listCabang) && isset($jenis_nama)) {
         $total_rent = 0;
         foreach ($listCabang as $c) {
            $total_rent += intval($c['rent'] ?? 0);
         }
         if ($total_rent > 0) {
            $ada_rent = false;
            foreach ($kas_keluar as $kk) {
               if (isset($kk['note_primary']) && trim($kk['note_primary']) === trim($jenis_nama)) {
                  $ada_rent = true;
                  break;
               }
            }
            if (!$ada_rent) {
               $kas_keluar[] = ['note_primary' => $jenis_nama, 'total' => $total_rent];
            }
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
      
      if ($whereCabang == '') {
         // All branches
         $gajiSql = "SELECT SUM(jumlah) as total FROM gaji_result WHERE tipe = 1 AND $gajiDateCondition";
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
      if ($whereCabang != '') {
         $barangPakaiWhere = "source_id = " . $this->id_cabang . " AND " . $barangPakaiWhere;
      }
      $barangPakaiSql = "SELECT COALESCE(SUM(price * qty), 0) as total FROM barang_mutasi WHERE $barangPakaiWhere";
      $barangPakaiResult = $this->db(0)->query_array($barangPakaiSql);
      $barang_pakai = 0;
      if ($barangPakaiResult && is_array($barangPakaiResult) && count($barangPakaiResult) > 0) {
         $barang_pakai = intval($barangPakaiResult[0]['total'] ?? 0);
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('rekap/rekap', [
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
         'barang_pakai' => $barang_pakai
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
}
