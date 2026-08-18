<?php

class Operasi extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function i($modeOperasi, $id_pelanggan)
   {
      $viewData = 'operasi/form_proses';
      
      // Year data for tuntas mode
      $currentYear = intval(date('Y'));
      $minYear = 2021;
      $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;
      if ($selectedYear < $minYear) $selectedYear = $minYear;
      if ($selectedYear > $currentYear) $selectedYear = $currentYear;
      
      $formData = array(
         'id_pelanggan' => $id_pelanggan, 
         'mode' => $modeOperasi,
         'currentYear' => $currentYear,
         'selectedYear' => $selectedYear,
         'minYear' => $minYear
      );
      
      switch ($modeOperasi) {
         case 0:
            //DALAM PROSES
            $data_operasi = ['title' => 'Operasi Order Proses'];
            break;
         case 1:
            //TUNTAS
            $data_operasi = ['title' => 'Operasi Order Tuntas'];
            break;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData, $formData);
   }

   public function loadData($id_pelanggan, $mode = 0)
   {
      $pelanggan = $this->pelanggan[$id_pelanggan] ?? 0;
      if ($pelanggan == 0) {
         echo "<div class='text-center mt-5'>
            <i class='fa-solid fa-circle-check text-success mb-3' style='font-size: 4rem;'></i>
            <h5 class='text-secondary'>Semua data pelanggan sudah tuntas!</h5>
         </div>";
         exit;
      }

      // Year handling
      $currentYear = intval(date('Y'));
      $minYear = 2021;
      $year = isset($_GET['year']) ? max($minYear, min($currentYear, intval($_GET['year']))) : $currentYear;

      $modeView = ($mode == 1) ? 2 : 1;
      $whereSale = $this->wCabang . " AND id_pelanggan = $id_pelanggan AND bin = 0 AND tuntas = " . ($mode == 1 ? "1 AND YEAR(insertTime) = $year" : "0") . " ORDER BY id_penjualan DESC";

      $data_main2 = $this->db(0)->get_where('sale', $whereSale, 'no_ref', 1);

      // Extract IDs and refs in single loop
      $sale_ids = [];
      $sale_refs = [];
      foreach ($data_main2 as $key_ref => $dm_group) {
         $sale_refs[] = $key_ref;
         foreach ($dm_group as $dm) {
            $sale_ids[] = $dm['id_penjualan'];
         }
      }

      // Batch queries for sale-related data
      $operasi = $notifSelesai = $kas = $notifBon = $surcas = [];
      $delivery_badge = [];
      $delivery_done = [];
      $deliveryRows = [];
      if (!empty($sale_ids)) {
         $ids_in = "'" . implode("','", $sale_ids) . "'";
         $operasi = $this->db(0)->get_where('operasi', $this->wCabang . " AND id_penjualan IN ($ids_in)");
         $notifSelesai = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 2 AND no_ref IN ($ids_in)");

         // Badge J/A/JA dari riwayat delivery (jemput / antar)
         $deliveryFlags = [];
         $deliveryRows = $this->db(0)->get_where(
            'delivery_riwayat',
            $this->wCabang . " AND id_penjualan IN ($ids_in)"
         );
         foreach ((array) $deliveryRows as $dr) {
            $sid = (string) ($dr['id_penjualan'] ?? '');
            $jenis = strtolower((string) ($dr['jenis'] ?? ''));
            if ($sid === '') {
               continue;
            }
            if ($jenis === 'jemput') {
               $deliveryFlags[$sid]['j'] = true;
            } elseif ($jenis === 'antar') {
               $deliveryFlags[$sid]['a'] = true;
            }
         }
         foreach ($deliveryFlags as $sid => $flag) {
            $hasJ = !empty($flag['j']);
            $hasA = !empty($flag['a']);
            if ($hasJ && $hasA) {
               $delivery_badge[$sid] = 'JA';
            } elseif ($hasJ) {
               $delivery_badge[$sid] = 'J';
            } elseif ($hasA) {
               $delivery_badge[$sid] = 'A';
            }
         }
      }
      if (!empty($sale_ids) && !empty($deliveryRows)) {
         $saleIdToRef = [];
         foreach ($data_main2 as $key_ref => $dm_group) {
            foreach ((array) $dm_group as $dm) {
               $saleIdToRef[(string) ($dm['id_penjualan'] ?? '')] = (string) $key_ref;
            }
         }
         foreach ((array) $deliveryRows as $dr) {
            $sid = (string) ($dr['id_penjualan'] ?? '');
            $jenis = strtolower((string) ($dr['jenis'] ?? ''));
            if ($sid === '' || !in_array($jenis, ['jemput', 'antar'], true)) {
               continue;
            }
            $refKey = $saleIdToRef[$sid] ?? (string) ($dr['no_ref'] ?? '');
            if ($refKey === '') {
               continue;
            }
            $time = (string) ($dr['insertTime'] ?? '');
            $nama = strtoupper(trim((string) ($dr['nama_karyawan'] ?? '')));
            if ($nama === '') {
               $idKar = (int) ($dr['id_karyawan'] ?? $dr['id_user'] ?? 0);
               $nama = $idKar > 0 ? ('#' . $idKar) : 'Crew';
            }
            if (!isset($delivery_done[$refKey][$jenis])) {
               $delivery_done[$refKey][$jenis] = [
                  'nama' => $nama,
                  'time' => $time,
                  'ids' => [],
               ];
            }
            $delivery_done[$refKey][$jenis]['ids'][$sid] = true;
            if (strcmp($time, (string) ($delivery_done[$refKey][$jenis]['time'] ?? '')) >= 0) {
               $delivery_done[$refKey][$jenis]['time'] = $time;
               $delivery_done[$refKey][$jenis]['nama'] = $nama;
            }
         }
      }
      if (!empty($sale_refs)) {
         $refs_in = "'" . implode("','", $sale_refs) . "'";
         $kas = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi IN ($refs_in)");
         $notifBon = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 1 AND no_ref IN ($refs_in)");
         $surcas = $this->db(0)->get_where('surcas', $this->wCabang . " AND no_ref IN ($refs_in)");
      }

      // MEMBER - OPTIMIZED: batch query instead of N+1
      $data_member = $this->db(0)->get_where('member', $this->wCabang . " AND bin = 0 AND id_pelanggan = $id_pelanggan AND lunas = 0");
      
      $member_ids = array_column($data_member, 'id_member');
      $notif_member = $kas_member = [];
      
      if (!empty($member_ids)) {
         $member_in = "'" . implode("','", $member_ids) . "'";
         
         // Batch query kas_member
         $all_kas_member = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 3 AND ref_transaksi IN ($member_in)");
         foreach ($all_kas_member as $km) {
            $idm = $km['ref_transaksi'];
            $kas_member[$idm][] = $km;
         }
         
         // Batch query notif_member
         $notif_member = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 3 AND no_ref IN ($member_in)");
         
         // Check lunas status
         foreach ($data_member as $dme) {
            $idm = $dme['id_member'];
            $harga = $dme['harga'];
            $totalBayar = 0;
            
            if (isset($kas_member[$idm])) {
               foreach ($kas_member[$idm] as $k) {
                  if ($k['status_mutasi'] == 3) $totalBayar += $k['jumlah'];
               }
               if ($totalBayar >= $harga) {
                  $lunas = $this->db(0)->update('member', ['lunas' => 1], 'id_member = ' . $idm);
                  if ($lunas['errno'] <> 0) {
                     $this->model('Log')->write("[loadData] ERROR UPDATE PAID, MEMBER ID $idm Error: " . $lunas['error']);
                  }
               }
            }
         }
      }

      // Finance history - optimized merge
      $finance_history = [];
      $all_kas = array_merge($kas, array_merge(...array_values($kas_member ?: [[]])));
      foreach ($all_kas as $k) {
         if (empty($k['ref_finance'])) continue;
         $rf = $k['ref_finance'];
         if (!isset($finance_history[$rf])) {
            $finance_history[$rf] = ['ref_finance' => $rf, 'total' => 0, 'status' => $k['status_mutasi'], 'metode' => $k['metode_mutasi'], 'note' => $k['note'], 'insertTime' => $k['insertTime']];
         }
         $finance_history[$rf]['total'] += intval($k['jumlah']);
         if ($k['insertTime'] > $finance_history[$rf]['insertTime']) {
            $finance_history[$rf] = array_merge($finance_history[$rf], ['insertTime' => $k['insertTime'], 'status' => $k['status_mutasi'], 'metode' => $k['metode_mutasi'], 'note' => $k['note']]);
         }
      }
      $finance_history = array_filter($finance_history, fn($item) => $item['status'] == 2);

      // Saldo deposit - 3 queries combined concept (kept as is for accuracy)
      $topup = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3") ?? 0;
      $topup_out = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3") ?? 0;
      $usage = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND metode_mutasi = 3 AND jenis_mutasi = 2") ?? 0;

      $this->view('operasi/view_load', [
         'modeView' => $modeView, 'pelanggan' => $pelanggan, 'data_main' => $data_main2,
         'operasi' => $operasi, 'kas' => $kas, 'notif_bon' => $notifBon, 'notif_selesai' => $notifSelesai,
         'notif_member' => $notif_member, 'formData' => [], 'idOperan' => '', 'surcas' => $surcas,
         'data_member' => $data_member, 'kas_member' => $kas_member, 'saldoTunai' => $topup - $topup_out - $usage,
         'users' => $this->db(0)->get('user', 'id_user'), 'finance_history' => $finance_history,
         'delivery_badge' => $delivery_badge,
         'delivery_done' => $delivery_done,
         'selectedYear' => $year, 'currentYear' => $currentYear, 'minYear' => $minYear
      ]);
   }

   /**
    * Detail timeline nota (proses & tuntas).
    * GET/POST Operasi/nota_detail — param: ref (no_ref)
    */
   public function nota_detail()
   {
      header('Content-Type: application/json; charset=utf-8');

      $ref = trim((string) ($_POST['ref'] ?? $_GET['ref'] ?? ''));
      if ($ref === '') {
         echo json_encode(['status' => 'error', 'message' => 'Ref nota wajib diisi']);
         return;
      }

      $refEsc = $this->db(0)->escape($ref);
      $sales = $this->db(0)->get_where(
         'sale',
         $this->wCabang . " AND no_ref = '$refEsc' AND bin = 0 ORDER BY id_penjualan ASC"
      );
      if (!is_array($sales) || empty($sales)) {
         echo json_encode(['status' => 'error', 'message' => 'Nota tidak ditemukan']);
         return;
      }

      $users = $this->db(0)->get('user', 'id_user');
      if (!is_array($users)) {
         $users = [];
      }
      $userName = function ($id) use ($users) {
         $id = (string) $id;
         if ($id === '' || $id === '0') {
            return '';
         }
         $nama = trim((string) ($users[$id]['nama_user'] ?? ''));
         return $nama !== '' ? strtoupper($nama) : '';
      };

      $mapMetode = [];
      foreach ((array) ($this->dMetodeMutasi ?? []) as $mm) {
         $mapMetode[(int) ($mm['id_metode_mutasi'] ?? 0)] = (string) ($mm['metode_mutasi'] ?? '');
      }
      $mapStatus = [];
      foreach ((array) ($this->dStatusMutasi ?? []) as $st) {
         $mapStatus[(int) ($st['id_status_mutasi'] ?? 0)] = (string) ($st['status_mutasi'] ?? '');
      }
      $mapLayanan = [];
      foreach ((array) ($this->dLayanan ?? []) as $ly) {
         $mapLayanan[(int) ($ly['id_layanan'] ?? 0)] = [
            'nama' => (string) ($ly['layanan'] ?? ''),
            'kode' => (string) ($ly['kode'] ?? ''),
         ];
      }
      $mapKategori = [];
      foreach ((array) ($this->itemGroup ?? []) as $g) {
         $mapKategori[(int) ($g['id_item_group'] ?? 0)] = (string) ($g['item_kategori'] ?? '');
      }
      $mapDurasi = [];
      foreach ((array) ($this->dDurasi ?? []) as $d) {
         $mapDurasi[(int) ($d['id_durasi'] ?? 0)] = strtoupper((string) ($d['durasi'] ?? ''));
      }
      $mapSatuan = [];
      foreach ((array) ($this->dPenjualan ?? []) as $l) {
         $sat = '';
         foreach ((array) ($this->dSatuan ?? []) as $sa) {
            if (($sa['id_satuan'] ?? null) == ($l['id_satuan'] ?? null)) {
               $sat = (string) ($sa['nama_satuan'] ?? '');
               break;
            }
         }
         $mapSatuan[(int) ($l['id_penjualan_jenis'] ?? 0)] = $sat;
      }
      $mapSurcas = [];
      foreach ((array) ($this->surcas ?? []) as $sc) {
         $mapSurcas[(int) ($sc['id_surcas_jenis'] ?? 0)] = (string) ($sc['surcas_jenis'] ?? '');
      }

      $first = $sales[0];
      $idPelanggan = (int) ($first['id_pelanggan'] ?? 0);
      $pelangganNama = '';
      if ($idPelanggan > 0 && isset($this->pelanggan[$idPelanggan])) {
         $pelangganNama = strtoupper(trim((string) ($this->pelanggan[$idPelanggan]['nama_pelanggan'] ?? '')));
      }

      $saleIds = [];
      foreach ($sales as $s) {
         $sid = $this->normalizeSaleId($s['id_penjualan'] ?? '');
         if ($sid !== '') {
            $saleIds[$sid] = $sid;
         }
      }
      $idsIn = "'" . implode("','", array_map(function ($id) {
         return $this->db(0)->escape($id);
      }, array_values($saleIds))) . "'";

      $operasiRows = $this->db(0)->get_where('operasi', $this->wCabang . " AND id_penjualan IN ($idsIn)");
      if (!is_array($operasiRows)) {
         $operasiRows = [];
      }
      $operasiMap = [];
      foreach ($operasiRows as $o) {
         $sid = $this->normalizeSaleId($o['id_penjualan'] ?? '');
         $jenis = (string) ($o['jenis_operasi'] ?? '');
         if ($sid === '' || $jenis === '') {
            continue;
         }
         $operasiMap[$sid][$jenis] = $o;
      }

      $deliveryRows = $this->db(0)->get_where('delivery_riwayat', "id_penjualan IN ($idsIn)");
      if (!is_array($deliveryRows)) {
         $deliveryRows = [];
      }
      $deliveryMap = [];
      foreach ($deliveryRows as $dr) {
         $sid = $this->normalizeSaleId($dr['id_penjualan'] ?? '');
         $jenis = strtolower((string) ($dr['jenis'] ?? ''));
         if ($sid === '' || !in_array($jenis, ['jemput', 'antar'], true)) {
            continue;
         }
         // Ambil riwayat terbaru per jenis
         if (!isset($deliveryMap[$sid][$jenis])
            || strcmp((string) ($dr['insertTime'] ?? ''), (string) ($deliveryMap[$sid][$jenis]['insertTime'] ?? '')) > 0
         ) {
            $deliveryMap[$sid][$jenis] = $dr;
         }
      }

      $kasRows = $this->db(0)->get_where(
         'kas',
         $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi = '$refEsc' ORDER BY insertTime ASC, id_kas ASC"
      );
      if (!is_array($kasRows)) {
         $kasRows = [];
      }

      $surcasRows = $this->db(0)->get_where(
         'surcas',
         $this->wCabang . " AND no_ref = '$refEsc' ORDER BY insertTime ASC, id_surcas ASC"
      );
      if (!is_array($surcasRows)) {
         $surcasRows = [];
      }

      $fmt = function ($time) {
         $time = trim((string) $time);
         if ($time === '' || $time === '0000-00-00 00:00:00') {
            return '';
         }
         $ts = strtotime($time);
         return $ts ? date('d/m/Y H:i', $ts) : $time;
      };

      $subTotal = 0;
      $items = [];
      foreach ($sales as $s) {
         $sid = $this->normalizeSaleId($s['id_penjualan'] ?? '');
         $itemTotal = $this->calcSaleItemTotal($s);
         $subTotal += $itemTotal;

         $qty = round((float) ($s['qty'] ?? 0), 2);
         $min = round((float) ($s['min_order'] ?? 0), 2);
         $satuan = $mapSatuan[(int) ($s['id_penjualan_jenis'] ?? 0)] ?? '';
         $qtyShow = rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') . $satuan;
         if ($qty < $min && $min > 0) {
            $qtyShow .= ' (Min.' . rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ',') . ')';
         }

         $timeline = [];
         $jemput = $deliveryMap[$sid]['jemput'] ?? null;
         if ($jemput) {
            $timeline[] = [
               'type' => 'jemput',
               'label' => 'Jemput',
               'time' => $fmt($jemput['insertTime'] ?? ''),
               'time_raw' => (string) ($jemput['insertTime'] ?? ''),
               'user' => strtoupper(trim((string) ($jemput['nama_karyawan'] ?? ''))) ?: $userName($jemput['id_karyawan'] ?? $jemput['id_user'] ?? ''),
               'inferred' => false,
               'done' => true,
            ];
         }

         $listLayanan = [];
         $rawList = $s['list_layanan'] ?? '';
         if (is_string($rawList) && $rawList !== '') {
            $un = @unserialize($rawList);
            if (is_array($un)) {
               $listLayanan = $un;
            }
         }

         $endLayananId = !empty($listLayanan) ? (string) end($listLayanan) : '';
         $endOp = ($endLayananId !== '' && isset($operasiMap[$sid][$endLayananId]))
            ? $operasiMap[$sid][$endLayananId]
            : null;
         $endDone = is_array($endOp);
         $endTime = $endDone ? (string) ($endOp['insertTime'] ?? '') : '';

         foreach ($listLayanan as $layananId) {
            $layananId = (string) $layananId;
            $meta = $mapLayanan[(int) $layananId] ?? ['nama' => 'Layanan', 'kode' => ''];
            $label = trim(($meta['kode'] !== '' ? $meta['kode'] . ' · ' : '') . $meta['nama']);
            if (isset($operasiMap[$sid][$layananId])) {
               $op = $operasiMap[$sid][$layananId];
               $timeline[] = [
                  'type' => 'layanan',
                  'label' => $label !== '' ? $label : 'Layanan',
                  'time' => $fmt($op['insertTime'] ?? ''),
                  'time_raw' => (string) ($op['insertTime'] ?? ''),
                  'user' => $userName($op['id_user_operasi'] ?? ''),
                  'inferred' => false,
                  'done' => true,
               ];
            } elseif ($endDone) {
               $timeline[] = [
                  'type' => 'layanan',
                  'label' => $label !== '' ? $label : 'Layanan',
                  'time' => $fmt($endTime),
                  'time_raw' => $endTime,
                  'user' => '',
                  'inferred' => true,
                  'done' => true,
               ];
            } else {
               $timeline[] = [
                  'type' => 'layanan',
                  'label' => $label !== '' ? $label : 'Layanan',
                  'time' => '',
                  'time_raw' => '',
                  'user' => '',
                  'inferred' => false,
                  'done' => false,
               ];
            }
         }

         $antar = $deliveryMap[$sid]['antar'] ?? null;
         if ($antar) {
            $timeline[] = [
               'type' => 'antar',
               'label' => 'Antar',
               'time' => $fmt($antar['insertTime'] ?? ''),
               'time_raw' => (string) ($antar['insertTime'] ?? ''),
               'user' => strtoupper(trim((string) ($antar['nama_karyawan'] ?? ''))) ?: $userName($antar['id_karyawan'] ?? $antar['id_user'] ?? ''),
               'inferred' => false,
               'done' => true,
            ];
         }

         $idAmbil = (int) ($s['id_user_ambil'] ?? 0);
         $tglAmbil = (string) ($s['tgl_ambil'] ?? '');
         if ($idAmbil > 0 || ($tglAmbil !== '' && $tglAmbil !== '0000-00-00 00:00:00')) {
            $timeline[] = [
               'type' => 'ambil',
               'label' => 'Ambil',
               'time' => $fmt($tglAmbil),
               'time_raw' => $tglAmbil,
               'user' => $userName($idAmbil),
               'inferred' => false,
               'done' => true,
            ];
         }

         $items[] = [
            'id' => $sid,
            'kategori' => $mapKategori[(int) ($s['id_item_group'] ?? 0)] ?? '',
            'durasi' => $mapDurasi[(int) ($s['id_durasi'] ?? 0)] ?? '',
            'qty_show' => $qtyShow,
            'total' => $itemTotal,
            'member' => (int) ($s['member'] ?? 0),
            'note' => trim((string) ($s['note'] ?? '')),
            'letak' => strtoupper(trim((string) ($s['letak'] ?? ''))),
            'tuntas' => (int) ($s['tuntas'] ?? 0),
            'timeline' => $timeline,
         ];
      }

      $surcasOut = [];
      foreach ($surcasRows as $sca) {
         $jumlah = (int) ($sca['jumlah'] ?? 0);
         $subTotal += $jumlah;
         $surcasOut[] = [
            'id' => (int) ($sca['id_surcas'] ?? 0),
            'nama' => $mapSurcas[(int) ($sca['id_jenis_surcas'] ?? 0)] ?? 'Surcas',
            'jumlah' => $jumlah,
            'time' => $fmt($sca['insertTime'] ?? ''),
            'user' => $userName($sca['id_user'] ?? ''),
         ];
      }

      $payments = [];
      $totalBayar = 0;
      $dibayar = 0;
      foreach ($kasRows as $ka) {
         $st = (int) ($ka['status_mutasi'] ?? 0);
         $jumlah = (int) ($ka['jumlah'] ?? 0);
         if ($st === 3) {
            $totalBayar += $jumlah;
         }
         if ($st !== 4) {
            $dibayar += $jumlah;
         }
         $metodeId = (int) ($ka['metode_mutasi'] ?? 0);
         $metode = $mapMetode[$metodeId] ?? '';
         $note = trim((string) ($ka['note'] ?? ''));
         $payments[] = [
            'id_kas' => (int) ($ka['id_kas'] ?? 0),
            'time' => $fmt($ka['insertTime'] ?? ''),
            'user' => $userName($ka['id_user'] ?? ''),
            'method' => $metode,
            'note' => $note,
            'amount' => $jumlah,
            'status' => $st,
            'status_label' => $mapStatus[$st] ?? '',
         ];
      }

      $subTotal = (int) round($subTotal);
      $sisa = $subTotal - $dibayar;
      $sisaFinal = $subTotal - $totalBayar;
      $lunas = $sisaFinal < 1;

      echo json_encode([
         'status' => 'success',
         'data' => [
            'no_ref' => $ref,
            'pelanggan' => $pelangganNama,
            'id_pelanggan' => $idPelanggan,
            'created_at' => $fmt($first['insertTime'] ?? ''),
            'created_by' => $userName($first['id_user'] ?? ''),
            'total' => $subTotal,
            'dibayar' => $dibayar,
            'total_bayar' => $totalBayar,
            'sisa' => max(0, $sisa),
            'lunas' => $lunas,
            'payments' => $payments,
            'surcas' => $surcasOut,
            'items' => $items,
         ],
      ]);
   }

   public function payment_gateway_order($ref_finance)
   {
      $this->payment_gateway_logic($ref_finance, false);
   }

   public function payment_gateway_check_status($ref_finance)
   {
      $this->payment_gateway_status_logic($ref_finance, false);
   }

   public function payment_gateway_status_poll($ref_finance)
   {
      $this->payment_gateway_status_db($ref_finance, false);
   }

   /**
    * Soft-delete one item from a multi-item nota.
    * The checks are intentionally repeated server-side; UI eligibility is only a convenience.
    */
   public function hapusItem()
   {
      header('Content-Type: application/json; charset=utf-8');

      $idPenjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      $note = trim((string) ($_POST['note'] ?? ''));
      if ($idPenjualan === '' || $note === '') {
         echo json_encode(['status' => 'error', 'message' => 'Item dan alasan hapus wajib diisi.']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($idPenjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $ref = trim((string) ($sale['no_ref'] ?? ''));
      if ($ref === '') {
         echo json_encode(['status' => 'error', 'message' => 'Nota item tidak ditemukan.']);
         return;
      }
      $refEsc = $this->db(0)->escape($ref);
      $saleWhere = $this->wCabang . " AND no_ref = '$refEsc' AND bin = 0";
      $items = $this->db(0)->get_where('sale', $saleWhere);
      if (!is_array($items) || count($items) <= 1) {
         echo json_encode(['status' => 'error', 'message' => 'Item tunggal tidak dapat dihapus. Gunakan tombol hapus nota untuk menghapus seluruh nota.']);
         return;
      }

      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      if ($dibayar > $currentSubTotal) {
         echo json_encode(['status' => 'error', 'message' => 'Item tidak dapat dihapus karena order overpay.']);
         return;
      }

      // Setelah hapus, total baru tidak boleh < pembayaran Cek/Berhasil
      $newSubTotal = $this->getRefSubTotal($ref, [
         $idPenjualan => ['member' => 1],
      ]);
      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      $update = $this->db(0)->update('sale', ['bin' => 1, 'bin_note' => $note], $this->whereSaleById($idPenjualan) . ' AND bin = 0');
      if (($update['errno'] ?? 1) != 0) {
         $this->model('Log')->write("[Operasi::hapusItem] Gagal hapus item id=$idPenjualan: " . ($update['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus item. Silakan coba lagi.']);
         return;
      }

      $this->resetBonNotif($ref);
      $this->model('Log')->write("[Operasi::hapusItem] Item id=$idPenjualan dari nota $ref dihapus. Alasan: $note");
      echo json_encode(['status' => 'success', 'message' => 'Item berhasil dihapus.']);
   }

   /**
    * Hapus surcas Antar/Jemput dari nota Operasi.
    * Syarat: belum tuntas, tidak terikat delivery_request, tidak overpay.
    */
   public function hapusSurcasKurir()
   {
      header('Content-Type: application/json; charset=utf-8');

      $idSurcas = (int) ($_POST['id_surcas'] ?? 0);
      $note = trim((string) ($_POST['note'] ?? ''));
      if ($idSurcas <= 0 || $note === '') {
         echo json_encode(['status' => 'error', 'message' => 'Surcas dan alasan hapus wajib diisi.']);
         return;
      }

      $this->helper('AntarTarif');
      $jenisAntar = (int) AntarTarif::SURCAS_JENIS_PENGANTARAN;
      $jenisJemput = (int) AntarTarif::SURCAS_JENIS_PENJEMPUTAN;

      $sc = $this->db(0)->get_where_row('surcas', $this->wCabang . ' AND id_surcas = ' . $idSurcas);
      if (!$sc || !is_array($sc)) {
         echo json_encode(['status' => 'error', 'message' => 'Surcas tidak ditemukan.']);
         return;
      }

      $jenisId = (int) ($sc['id_jenis_surcas'] ?? 0);
      if ($jenisId !== $jenisAntar && $jenisId !== $jenisJemput) {
         echo json_encode(['status' => 'error', 'message' => 'Hanya surcas Antar/Jemput yang dapat dihapus dari Operasi.']);
         return;
      }

      if ((int) ($sc['id_delivery_request'] ?? 0) > 0) {
         echo json_encode(['status' => 'error', 'message' => 'Surcas terikat delivery request — tidak dapat dihapus.']);
         return;
      }

      $ref = trim((string) ($sc['no_ref'] ?? ''));
      if ($ref === '') {
         echo json_encode(['status' => 'error', 'message' => 'Nota surcas tidak valid.']);
         return;
      }
      $refEsc = $this->db(0)->escape($ref);

      $sales = $this->db(0)->get_where('sale', $this->wCabang . " AND no_ref = '$refEsc' AND bin = 0");
      if (!is_array($sales) || empty($sales)) {
         echo json_encode(['status' => 'error', 'message' => 'Nota tidak ditemukan atau sudah dihapus.']);
         return;
      }

      $err = $this->validateOrderModifiable($sales[0]);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      if ($dibayar > $currentSubTotal) {
         echo json_encode(['status' => 'error', 'message' => 'Surcas tidak dapat dihapus karena order overpay.']);
         return;
      }

      $newSubTotal = $this->getRefSubTotal($ref, [], [$idSurcas]);
      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      try {
         $this->db(0)->delete('surcas_item', 'id_surcas = ' . $idSurcas);
      } catch (\Throwable $e) {
         // tabel surcas_item opsional
      }

      $del = $this->db(0)->delete('surcas', $this->wCabang . ' AND id_surcas = ' . $idSurcas);
      if (($del['errno'] ?? 1) != 0) {
         $this->model('Log')->write("[Operasi::hapusSurcasKurir] Gagal hapus id=$idSurcas: " . ($del['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus surcas. Silakan coba lagi.']);
         return;
      }

      $this->resetBonNotif($ref);
      $this->model('Log')->write("[Operasi::hapusSurcasKurir] Surcas id=$idSurcas (jenis=$jenisId) dari nota $ref dihapus. Alasan: $note");
      echo json_encode(['status' => 'success', 'message' => 'Surcas berhasil dihapus.']);
   }

   public function bayarMulti($karyawan, $idPelanggan, $metode = 2, $note = "")
   {
      $rekap = isset($_POST['rekap']) ? $_POST['rekap'][0] : [];
      $dibayar = isset($_POST['dibayar']) ? $_POST['dibayar'] : 0;
      $idPenanggung = isset($_POST['id_penanggung_bayar']) ? (int) $_POST['id_penanggung_bayar'] : 0;
      $idSaldoClient = 0;

      $note = str_replace('_SPACE_', ' ', (string) $note);

      if ((int) $metode === 3 && $idPenanggung > 0) {
         if ($idPenanggung === (int) $idPelanggan) {
            echo 'Penanggung bayar harus berbeda dari pelanggan order';
            return;
         }
         if (!isset($this->pelanggan[$idPenanggung])) {
            echo 'Penanggung bayar tidak ditemukan di cabang ini';
            return;
         }
         $saldoPenanggung = $this->helper('Saldo')->getSaldoTunai($idPenanggung);
         if ($saldoPenanggung <= 0) {
            echo 'Saldo penanggung bayar tidak mencukupi';
            return;
         }
         $idSaldoClient = $idPenanggung;
         $namaOrder = $this->pelanggan[$idPelanggan]['nama_pelanggan'] ?? $idPelanggan;
         $namaPenanggung = $this->pelanggan[$idPenanggung]['nama_pelanggan'] ?? $idPenanggung;
         $note = 'TB:' . strtoupper($namaOrder);
         $userName = $_SESSION[URL::SESSID]['user']['nama_user'] ?? $karyawan;
         $this->model('Log')->write(
            "[TanggungBayar] Order#$idPelanggan ($namaOrder) saldo dari #$idPenanggung ($namaPenanggung) oleh user $karyawan ($userName)"
         );
      }

      $res = $this->model('KasModel')->bayarMulti(
         $rekap,
         $dibayar,
         $idPelanggan,
         $this->id_cabang,
         $karyawan,
         $metode,
         $note,
         1,
         $idSaldoClient
      );

      echo $res;
   }

   /**
    * Daftar pelanggan cabang yang memiliki saldo tunai > 0 (untuk modal Tanggung Bayar).
    */
   public function listPenanggungBayar($excludePelanggan = 0)
   {
      header('Content-Type: application/json; charset=utf-8');

      $excludePelanggan = (int) $excludePelanggan;

      $where = $this->wCabang . " AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
      $where2 = $this->wCabang . " AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
      $cols = "id_client, SUM(jumlah) as saldo";

      $data = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      $data3 = $this->db(0)->get_cols_where('kas', $cols, $where2, 1);

      $saldo = [];
      $pakai = [];

      foreach ($data as $a) {
         $saldo[$a['id_client']] = $a['saldo'];
         $pakai[$a['id_client']] = 0;
      }

      if (count($saldo) > 0) {
         $wherePakai = $this->wCabang . " AND metode_mutasi = 3 AND jenis_mutasi = 2";
         $colsPakai = "id_client, SUM(jumlah) as pakai";
         $dataPakai = $this->db(0)->get_cols_where('kas', $colsPakai, $wherePakai . " GROUP BY id_client", 1);
         foreach ($dataPakai as $dp) {
            if (isset($saldo[$dp['id_client']])) {
               $pakai[$dp['id_client']] = $dp['pakai'];
            }
         }
      }

      foreach ($data3 as $a2) {
         $idClient = $a2['id_client'];
         if (isset($pakai[$idClient])) {
            $pakai[$idClient] += $a2['saldo'];
         } else {
            $pakai[$idClient] = $a2['saldo'];
         }
      }

      $result = [];
      foreach ($saldo as $idClient => $topupAmt) {
         $sisa = (int) round($topupAmt - ($pakai[$idClient] ?? 0));
         if ($sisa <= 0) {
            continue;
         }
         $idClient = (int) $idClient;
         if ($excludePelanggan > 0 && $idClient === $excludePelanggan) {
            continue;
         }
         if (!isset($this->pelanggan[$idClient])) {
            continue;
         }
         $p = $this->pelanggan[$idClient];
         $result[] = [
            'id_pelanggan' => $idClient,
            'nama_pelanggan' => $p['nama_pelanggan'],
            'nomor_pelanggan' => $p['nomor_pelanggan'] ?? '',
            'saldo' => $sisa,
         ];
      }

      usort($result, function ($a, $b) {
         return strcasecmp($a['nama_pelanggan'], $b['nama_pelanggan']);
      });

      echo json_encode(['ok' => 1, 'data' => $result]);
   }

   public function ganti_operasi()
   {
      $karyawan = $_POST['f1'] ?? '';
      $id = $_POST['id'] ?? '';
      $accessKey = $_POST['access_key'] ?? '';

      // id_operasi bisa alfanumerik (mis. 51681407012 atau A168070), harus di-quote agar tidak error "Truncated incorrect DOUBLE value"
      $idEsc = $this->db(0)->escape(trim((string) $id));
      $where = $this->wCabang . " AND id_operasi = '" . $idEsc . "'";

      $row = $this->db(0)->get_where_row('operasi', $where);
      if (!isset($row['id_operasi'])) {
         echo 'Data operasi tidak ditemukan.';
         return;
      }

      // Wajib Access Key milik penyelesai sebelumnya
      $prevId = (int) ($row['id_user_operasi'] ?? 0);
      if (!$this->helper('User')->by_id_access_key($prevId, $accessKey)) {
         echo 'Access Key tidak cocok dengan penyelesai sebelumnya.';
         return;
      }

      $idPenjualanRaw = trim((string) ($row['id_penjualan'] ?? ''));
      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($idPenjualanRaw) . ' AND bin = 0');

      // Kosong = hapus penyelesai (baris operasi) + hapus notif selesai (tipe 2)
      // Hanya diizinkan jika order belum tuntas
      if ((string) $karyawan === '0' || $karyawan === '') {
         $err = $this->validateOrderModifiable($sale);
         if ($err !== null) {
            echo $err === 'Order sudah tuntas'
               ? 'Tidak dapat mengosongkan penyelesai: order sudah tuntas.'
               : $err;
            return;
         }

         $idPenjualan = $this->db(0)->escape($this->normalizeSaleId($idPenjualanRaw));
         $del = $this->db(0)->delete('operasi', $where);
         if ($del['errno'] <> 0) {
            $this->model('Log')->write("[ganti_operasi] Delete Operasi Error: " . $del['error']);
            echo $del['error'];
            return;
         }

         if ($idPenjualan !== '') {
            $whereNotif = $this->wCabang . " AND no_ref = '" . $idPenjualan . "' AND tipe = 2";
            $delNotif = $this->db(0)->delete('notif', $whereNotif);
            if ($delNotif['errno'] <> 0) {
               $this->model('Log')->write("[ganti_operasi] Delete Notif Selesai Error: " . $delNotif['error']);
               echo $delNotif['error'];
               return;
            }
         }

         echo 0;
         return;
      }

      // Ubah ke karyawan lain: wajib bulan order = bulan saat ini
      if (!$sale || !is_array($sale)) {
         echo 'Order tidak ditemukan.';
         return;
      }
      $bulanOrder = date('Y-m', strtotime((string) ($sale['insertTime'] ?? '')));
      $bulanSekarang = date('Y-m');
      if ($bulanOrder !== $bulanSekarang) {
         echo 'Ubah penyelesai hanya untuk order bulan ini.';
         return;
      }

      $set = ['id_user_operasi' => $karyawan];
      $in = $this->db(0)->update('operasi', $set, $where); // Changed to db(0)
      if ($in['errno'] <> 0) {
         $this->model('Log')->write("[ganti_operasi] Update Operasi Error: " . $in['error']);
         echo $in['error'];
      } else {
         echo 0;
      }
   }

   public function cancel_payment($ref_finance)
   {
      $this->cancel_payment_logic($ref_finance, false);
   }

   public function durasi_options()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? $_GET['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      if (($sale['member'] ?? 0) != 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item member tidak dapat diubah durasinya']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      $currentItemTotal = $this->calcSaleItemTotal($sale);

      $kategori = '';
      foreach ($this->itemGroup as $ig) {
         if ($ig['id_item_group'] == $sale['id_item_group']) {
            $kategori = $ig['item_kategori'];
         }
      }

      $options = [];
      foreach ($this->harga as $h) {
         if (($h['is_active'] ?? 1) != 1) {
            continue;
         }
         if ($h['id_penjualan_jenis'] != $sale['id_penjualan_jenis']) {
            continue;
         }
         if ($h['id_item_group'] != $sale['id_item_group']) {
            continue;
         }
         if ($h['list_layanan'] != $sale['list_layanan']) {
            continue;
         }

         $durasiName = '';
         foreach ($this->dDurasi as $d) {
            if ($d['id_durasi'] == $h['id_durasi']) {
               $durasiName = strtoupper($d['durasi']);
            }
         }

         $unitPrice = $this->hargaUnitPrice($h);
         $minOrder = round((float) ($h['min_order'] ?? $sale['min_order'] ?? 0), 2);
         $newSubTotal = $this->getRefSubTotal($ref, [
            $id_penjualan => [
               'harga' => $unitPrice,
               'min_order' => $minOrder,
            ],
         ]);
         $newItemTotal = $this->calcSaleItemTotal(array_merge($sale, [
            'harga' => $unitPrice,
            'min_order' => $minOrder,
         ]));

         $canSelect = ($dibayar <= 0) || ($newSubTotal >= $dibayar);

         $options[] = [
            'id_harga' => (int) $h['id_harga'],
            'id_durasi' => (int) $h['id_durasi'],
            'durasi' => $durasiName,
            'hari' => (int) ($h['hari'] ?? 0),
            'jam' => (int) ($h['jam'] ?? 0),
            'harga' => $unitPrice,
            'item_total' => $newItemTotal,
            'ref_total' => $newSubTotal,
            'can_select' => $canSelect,
            'selected' => ((int) $h['id_durasi'] === (int) $sale['id_durasi']),
         ];
      }

      if (empty($options)) {
         echo json_encode(['status' => 'error', 'message' => 'Tidak ada pilihan durasi untuk item ini']);
         return;
      }

      $currentDurasi = '';
      foreach ($this->dDurasi as $d) {
         if ($d['id_durasi'] == $sale['id_durasi']) {
            $currentDurasi = strtoupper($d['durasi']);
         }
      }

      echo json_encode([
         'status' => 'success',
         'id_penjualan' => $id_penjualan,
         'ref' => $ref,
         'kategori' => $kategori,
         'current_durasi' => $currentDurasi,
         'current_item_total' => $currentItemTotal,
         'current_ref_total' => $currentSubTotal,
         'dibayar' => $dibayar,
         'options' => $options,
      ]);
   }

   public function ubah_durasi()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      $id_harga = (int) ($_POST['id_harga'] ?? 0);

      if ($id_penjualan === '' || $id_harga <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      if (($sale['member'] ?? 0) != 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item member tidak dapat diubah durasinya']);
         return;
      }

      $hargaRow = null;
      foreach ($this->harga as $h) {
         if ((int) $h['id_harga'] === $id_harga) {
            $hargaRow = $h;
            break;
         }
      }

      if (!$hargaRow || ($hargaRow['is_active'] ?? 1) != 1) {
         echo json_encode(['status' => 'error', 'message' => 'Harga tidak ditemukan']);
         return;
      }

      if ($hargaRow['id_penjualan_jenis'] != $sale['id_penjualan_jenis']
         || $hargaRow['id_item_group'] != $sale['id_item_group']
         || $hargaRow['list_layanan'] != $sale['list_layanan']) {
         echo json_encode(['status' => 'error', 'message' => 'Durasi tidak sesuai dengan item order']);
         return;
      }

      if ((int) $hargaRow['id_durasi'] === (int) $sale['id_durasi']) {
         echo json_encode(['status' => 'error', 'message' => 'Durasi sama dengan yang sekarang']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $unitPrice = $this->hargaUnitPrice($hargaRow);
      $minOrder = round((float) ($hargaRow['min_order'] ?? $sale['min_order'] ?? 0), 2);
      $newSubTotal = $this->getRefSubTotal($ref, [
         $id_penjualan => [
            'harga' => $unitPrice,
            'min_order' => $minOrder,
         ],
      ]);

      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      $set = [
         'id_durasi' => (int) $hargaRow['id_durasi'],
         'hari' => (int) ($hargaRow['hari'] ?? 0),
         'jam' => (int) ($hargaRow['jam'] ?? 0),
         'harga' => $unitPrice,
         'min_order' => $minOrder,
         'id_harga' => $id_harga,
      ];
      $where = $this->whereSaleById($id_penjualan);
      $up = $this->db(0)->update('sale', $set, $where);
      if ($up['errno'] != 0) {
         $this->model('Log')->write("[ubah_durasi] Update sale error id=$id_penjualan: " . ($up['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . ($up['error'] ?? 'Unknown error')]);
         return;
      }

      $this->resetBonNotif($ref);

      echo json_encode(['status' => 'success', 'message' => 'Durasi berhasil diubah']);
   }

   /**
    * Info item untuk modal ubah qty.
    */
   public function qty_info()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? $_GET['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      if ($dibayar > $currentSubTotal) {
         echo json_encode(['status' => 'error', 'message' => 'Order overpay — quantity tidak dapat diubah']);
         return;
      }

      $kategori = '';
      foreach ($this->itemGroup as $ig) {
         if ($ig['id_item_group'] == $sale['id_item_group']) {
            $kategori = $ig['item_kategori'];
         }
      }

      $satuan = '';
      foreach ($this->dPenjualan as $l) {
         if ($l['id_penjualan_jenis'] == $sale['id_penjualan_jenis']) {
            foreach ($this->dSatuan as $sa) {
               if ($sa['id_satuan'] == $l['id_satuan']) {
                  $satuan = $sa['nama_satuan'];
               }
            }
         }
      }

      $qty = round((float) ($sale['qty'] ?? 0), 2);
      $minOrder = round((float) ($sale['min_order'] ?? 0), 2);

      echo json_encode([
         'status' => 'success',
         'id_penjualan' => $id_penjualan,
         'ref' => $ref,
         'kategori' => $kategori,
         'satuan' => $satuan,
         'qty' => $qty,
         'min_order' => $minOrder,
         'harga' => (float) ($sale['harga'] ?? 0),
         'diskon_qty' => (float) ($sale['diskon_qty'] ?? 0),
         'diskon_partner' => (float) ($sale['diskon_partner'] ?? 0),
         'current_item_total' => $this->calcSaleItemTotal($sale),
         'current_ref_total' => $currentSubTotal,
         'dibayar' => $dibayar,
         'member' => (int) ($sale['member'] ?? 0),
      ]);
   }

   /**
    * Simpan qty baru. Syarat: belum tuntas, tidak overpay, total baru >= dibayar.
    */
   public function ubah_qty()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      $qtyRaw = str_replace(',', '.', trim((string) ($_POST['qty'] ?? '')));
      $qty = round((float) $qtyRaw, 2);

      if ($id_penjualan === '' || $qty <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Quantity harus lebih dari 0']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      if ($dibayar > $currentSubTotal) {
         echo json_encode(['status' => 'error', 'message' => 'Order overpay — quantity tidak dapat diubah']);
         return;
      }

      $oldQty = round((float) ($sale['qty'] ?? 0), 2);
      if (abs($oldQty - $qty) < 0.001) {
         echo json_encode(['status' => 'error', 'message' => 'Quantity sama dengan yang sekarang']);
         return;
      }

      $newSubTotal = $this->getRefSubTotal($ref, [
         $id_penjualan => ['qty' => $qty],
      ]);
      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      $up = $this->db(0)->update(
         'sale',
         ['qty' => $qty],
         $this->whereSaleById($id_penjualan)
      );
      if ($up['errno'] != 0) {
         $this->model('Log')->write("[ubah_qty] Update sale error id=$id_penjualan: " . ($up['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . ($up['error'] ?? 'Unknown error')]);
         return;
      }

      $this->resetBonNotif($ref);
      echo json_encode([
         'status' => 'success',
         'message' => 'Quantity berhasil diubah',
         'qty' => $qty,
         'ref_total' => $newSubTotal,
      ]);
   }

   public function layanan_options()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? $_GET['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateLayananChangeable($sale, $id_penjualan);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      $currentItemTotal = $this->calcSaleItemTotal($sale);

      $kategori = '';
      foreach ($this->itemGroup as $ig) {
         if ($ig['id_item_group'] == $sale['id_item_group']) {
            $kategori = $ig['item_kategori'];
         }
      }

      $options = [];
      foreach ($this->harga as $h) {
         if (($h['is_active'] ?? 1) != 1) {
            continue;
         }
         if ($h['id_penjualan_jenis'] != $sale['id_penjualan_jenis']) {
            continue;
         }
         if ($h['id_item_group'] != $sale['id_item_group']) {
            continue;
         }
         if ((int) $h['id_durasi'] !== (int) $sale['id_durasi']) {
            continue;
         }
         if ($h['list_layanan'] === $sale['list_layanan']) {
            continue;
         }

         $layananLabel = $this->layananLabelFromSerialized($h['list_layanan']);
         $unitPrice = $this->hargaUnitPrice($h);
         $minOrder = round((float) str_replace(',', '.', (string) ($h['min_order'] ?? $sale['min_order'] ?? 0)), 2);
         $newSubTotal = $this->getRefSubTotal($ref, [
            $id_penjualan => [
               'harga' => $unitPrice,
               'min_order' => $minOrder,
            ],
         ]);
         $newItemTotal = $this->calcSaleItemTotal(array_merge($sale, [
            'harga' => $unitPrice,
            'min_order' => $minOrder,
         ]));

         $canSelect = ($dibayar <= 0) || ($newSubTotal >= $dibayar);

         $options[] = [
            'id_harga' => (int) $h['id_harga'],
            'layanan' => $layananLabel,
            'harga' => $unitPrice,
            'item_total' => $newItemTotal,
            'ref_total' => $newSubTotal,
            'can_select' => $canSelect,
            'selected' => ($h['list_layanan'] === $sale['list_layanan']),
         ];
      }

      if (empty($options)) {
         echo json_encode(['status' => 'error', 'message' => 'Tidak ada pilihan layanan lain untuk item ini']);
         return;
      }

      $currentDurasi = '';
      foreach ($this->dDurasi as $d) {
         if ($d['id_durasi'] == $sale['id_durasi']) {
            $currentDurasi = strtoupper($d['durasi']);
         }
      }

      echo json_encode([
         'status' => 'success',
         'id_penjualan' => $id_penjualan,
         'ref' => $ref,
         'kategori' => $kategori,
         'current_layanan' => $this->layananLabelFromSerialized($sale['list_layanan']),
         'current_durasi' => $currentDurasi,
         'current_item_total' => $currentItemTotal,
         'current_ref_total' => $currentSubTotal,
         'dibayar' => $dibayar,
         'options' => $options,
      ]);
   }

   public function ubah_layanan()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      $id_harga = (int) ($_POST['id_harga'] ?? 0);

      if ($id_penjualan === '' || $id_harga <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateLayananChangeable($sale, $id_penjualan);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $hargaRow = null;
      foreach ($this->harga as $h) {
         if ((int) $h['id_harga'] === $id_harga) {
            $hargaRow = $h;
            break;
         }
      }

      if (!$hargaRow || ($hargaRow['is_active'] ?? 1) != 1) {
         echo json_encode(['status' => 'error', 'message' => 'Harga tidak ditemukan']);
         return;
      }

      if ($hargaRow['id_penjualan_jenis'] != $sale['id_penjualan_jenis']
         || $hargaRow['id_item_group'] != $sale['id_item_group']
         || (int) $hargaRow['id_durasi'] !== (int) $sale['id_durasi']) {
         echo json_encode(['status' => 'error', 'message' => 'Layanan tidak sesuai dengan item order']);
         return;
      }

      if ($hargaRow['list_layanan'] === $sale['list_layanan']) {
         echo json_encode(['status' => 'error', 'message' => 'Layanan sama dengan yang sekarang']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $unitPrice = $this->hargaUnitPrice($hargaRow);
      $minOrder = round((float) str_replace(',', '.', (string) ($hargaRow['min_order'] ?? $sale['min_order'] ?? 0)), 2);
      $newSubTotal = $this->getRefSubTotal($ref, [
         $id_penjualan => [
            'harga' => $unitPrice,
            'min_order' => $minOrder,
         ],
      ]);

      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      $set = [
         'list_layanan' => $hargaRow['list_layanan'],
         'harga' => $unitPrice,
         'min_order' => $minOrder,
         'id_harga' => $id_harga,
      ];
      $where = $this->whereSaleById($id_penjualan);
      $up = $this->db(0)->update('sale', $set, $where);
      if ($up['errno'] != 0) {
         $this->model('Log')->write("[ubah_layanan] Update sale error id=$id_penjualan: " . ($up['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . ($up['error'] ?? 'Unknown error')]);
         return;
      }

      $this->resetBonNotif($ref);

      echo json_encode(['status' => 'success', 'message' => 'Layanan berhasil diubah']);
   }

   private function hargaUnitPrice($hargaRow)
   {
      return $this->resolveHargaUnit($hargaRow);
   }

   private function calcSaleItemTotal($sale)
   {
      if (($sale['member'] ?? 0) != 0) {
         return 0;
      }

      $qty = round((float) ($sale['qty'] ?? 0), 2);
      $minOrder = round((float) ($sale['min_order'] ?? 0), 2);
      $qtyReal = ($qty < $minOrder) ? $minOrder : $qty;
      $total = (float) ($sale['harga'] ?? 0) * $qtyReal;

      $diskonQty = (float) ($sale['diskon_qty'] ?? 0);
      $diskonPartner = (float) ($sale['diskon_partner'] ?? 0);

      if ($diskonQty > 0) {
         $total -= $total * ($diskonQty / 100);
      }
      if ($diskonPartner > 0) {
         $total -= $total * ($diskonPartner / 100);
      }

      return (int) round($total);
   }

   private function getRefSubTotal($ref, $saleOverrides = [], $excludeSurcasIds = [])
   {
      $refEsc = $this->db(0)->escape($ref);
      $sales = $this->db(0)->get_where('sale', $this->wCabang . " AND no_ref = '$refEsc' AND bin = 0");
      if (!is_array($sales)) {
         $sales = [];
      }

      $normalizedOverrides = [];
      foreach ($saleOverrides as $oid => $override) {
         $normalizedOverrides[$this->normalizeSaleId($oid)] = $override;
      }

      $excludeSurcas = [];
      foreach ($excludeSurcasIds as $sid) {
         $sid = (int) $sid;
         if ($sid > 0) {
            $excludeSurcas[$sid] = true;
         }
      }

      $subTotal = 0;
      foreach ($sales as $s) {
         $id = $this->normalizeSaleId($s['id_penjualan']);
         if (isset($normalizedOverrides[$id])) {
            $s = array_merge($s, $normalizedOverrides[$id]);
         }
         $subTotal += $this->calcSaleItemTotal($s);
      }

      $surcasList = $this->db(0)->get_where('surcas', $this->wCabang . " AND no_ref = '$refEsc'");
      if (is_array($surcasList)) {
         foreach ($surcasList as $sc) {
            $sid = (int) ($sc['id_surcas'] ?? 0);
            if ($sid > 0 && isset($excludeSurcas[$sid])) {
               continue;
            }
            $subTotal += (int) ($sc['jumlah'] ?? 0);
         }
      }

      return (int) round($subTotal);
   }

   private function getRefDibayar($ref)
   {
      $refEsc = $this->db(0)->escape($ref);
      $kas = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi = '$refEsc'");
      if (!is_array($kas)) {
         return 0;
      }

      $dibayar = 0;
      foreach ($kas as $ka) {
         $st = (int) ($ka['status_mutasi'] ?? 0);
         if ($st === 2 || $st === 3) {
            $dibayar += (int) ($ka['jumlah'] ?? 0);
         }
      }

      return $dibayar;
   }

   private function validateOrderModifiable($sale)
   {
      if (!$sale || !is_array($sale)) {
         return 'Order tidak ditemukan';
      }
      if (($sale['bin'] ?? 0) != 0) {
         return 'Order tidak dapat diubah';
      }
      if (($sale['tuntas'] ?? 0) != 0) {
         return 'Order sudah tuntas';
      }
      return null;
   }

   private function validatePaymentAfterChange($ref, $newSubTotal)
   {
      $dibayar = $this->getRefDibayar($ref);
      if ($dibayar > 0 && (int) round($newSubTotal) < $dibayar) {
         return 'Total order setelah perubahan (' . number_format($newSubTotal) . ') kurang dari pembayaran Cek/Berhasil (' . number_format($dibayar) . ')';
      }
      return null;
   }

   private function validateLayananChangeable($sale, $id_penjualan)
   {
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         return $err;
      }
      if (($sale['member'] ?? 0) != 0) {
         return 'Item member tidak dapat diubah layanannya';
      }
      if (($sale['id_user_ambil'] ?? 0) > 0) {
         return 'Layanan tidak dapat diubah karena sudah diambil';
      }
      if ($this->saleHasOperasi($id_penjualan)) {
         return 'Layanan tidak dapat diubah karena sudah ada layanan yang diselesaikan';
      }
      return null;
   }

   private function saleHasOperasi($id_penjualan)
   {
      $idEsc = $this->db(0)->escape($this->normalizeSaleId($id_penjualan));
      $count = $this->db(0)->count_where('operasi', $this->wCabang . " AND id_penjualan = '$idEsc'");
      return is_numeric($count) && (int) $count > 0;
   }

   private function layananLabelFromSerialized($listLayanan)
   {
      $arr = @unserialize((string) $listLayanan);
      if (!is_array($arr)) {
         return '';
      }

      $label = '';
      foreach ($arr as $lid) {
         foreach ($this->dLayanan as $c) {
            if ($c['id_layanan'] == $lid) {
               $label .= ($label === '' ? '' : ' + ') . $c['layanan'];
            }
         }
      }

      return $label;
   }

   public function member_options()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? $_GET['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      if (($sale['member'] ?? 0) != 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item sudah menggunakan member']);
         return;
      }

      $idPelanggan = (int) ($sale['id_pelanggan'] ?? 0);
      $idHarga = (int) ($sale['id_harga'] ?? 0);
      if ($idPelanggan <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Pelanggan tidak ditemukan pada order ini']);
         return;
      }
      if ($idHarga <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item tidak memiliki paket member yang sesuai']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $qty = round((float) ($sale['qty'] ?? 0), 2);
      $saldo = $this->getMemberSaldo($idPelanggan, $idHarga);
      $unit = $this->helper('Saldo')->unit_by_idHarga($idHarga);
      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      $newSubTotal = $this->getRefSubTotal($ref, [$id_penjualan => ['member' => 1]]);

      $kategori = '';
      foreach ($this->itemGroup as $ig) {
         if ($ig['id_item_group'] == $sale['id_item_group']) {
            $kategori = $ig['item_kategori'];
         }
      }

      $durasi = '';
      foreach ($this->dDurasi as $d) {
         if ($d['id_durasi'] == $sale['id_durasi']) {
            $durasi = strtoupper($d['durasi']);
         }
      }

      $canConvert = ($saldo >= $qty);
      $message = '';
      if (!$canConvert) {
         $message = 'Saldo member M' . $idHarga . ' tidak cukup (tersedia ' . $this->fmtDecMax2($saldo) . $unit . ', dibutuhkan ' . $this->fmtDecMax2($qty) . $unit . ')';
      } else {
         $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
         if ($payErr !== null) {
            $canConvert = false;
            $message = $payErr;
         }
      }

      echo json_encode([
         'status' => 'success',
         'id_penjualan' => $id_penjualan,
         'ref' => $ref,
         'id_harga' => $idHarga,
         'kategori' => $kategori,
         'durasi' => $durasi,
         'qty' => $qty,
         'qty_fmt' => $this->fmtDecMax2($qty),
         'unit' => $unit,
         'saldo' => $saldo,
         'saldo_fmt' => $this->fmtDecMax2($saldo),
         'current_ref_total' => $currentSubTotal,
         'new_ref_total' => $newSubTotal,
         'dibayar' => $dibayar,
         'can_convert' => $canConvert,
         'message' => $message,
      ]);
   }

   public function ubah_member()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      if (($sale['member'] ?? 0) != 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item sudah menggunakan member']);
         return;
      }

      $idPelanggan = (int) ($sale['id_pelanggan'] ?? 0);
      $idHarga = (int) ($sale['id_harga'] ?? 0);
      if ($idPelanggan <= 0 || $idHarga <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Data order tidak lengkap untuk ubah member']);
         return;
      }

      $qty = round((float) ($sale['qty'] ?? 0), 2);
      $saldo = $this->getMemberSaldo($idPelanggan, $idHarga);
      if ($saldo < $qty) {
         echo json_encode(['status' => 'error', 'message' => 'Saldo member tidak cukup']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $newSubTotal = $this->getRefSubTotal($ref, [$id_penjualan => ['member' => 1]]);
      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      $set = [
         'member' => 1,
         'total' => 0,
      ];
      $where = $this->whereSaleById($id_penjualan);
      $up = $this->db(0)->update('sale', $set, $where);
      if ($up['errno'] != 0) {
         $this->model('Log')->write("[ubah_member] Update sale error id=$id_penjualan: " . ($up['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . ($up['error'] ?? 'Unknown error')]);
         return;
      }

      $this->resetBonNotif($ref);

      echo json_encode(['status' => 'success', 'message' => 'Berhasil diubah ke member']);
   }

   private function normalizeSaleId($id)
   {
      return trim((string) $id);
   }

   private function whereSaleById($id_penjualan)
   {
      $idEsc = $this->db(0)->escape($this->normalizeSaleId($id_penjualan));
      return $this->wCabang . " AND id_penjualan = '$idEsc'";
   }

   /**
    * Hapus notif bon (tipe=1) agar nota WA bisa dikirim ulang setelah perubahan order.
    */
   private function resetBonNotif($ref)
   {
      $ref = trim((string) $ref);
      if ($ref === '') {
         return;
      }

      $refEsc = $this->db(0)->escape($ref);
      $where = $this->wCabang . " AND no_ref = '$refEsc' AND tipe = 1";
      $del = $this->db(0)->delete('notif', $where);
      if (isset($del['errno']) && $del['errno'] != 0) {
         $this->model('Log')->write("[resetBonNotif] Delete notif error ref=$ref: " . ($del['error'] ?? ''));
      }
   }

   private function getMemberSaldo($idPelanggan, $idHarga)
   {
      $idPelanggan = (int) $idPelanggan;
      $idHarga = (int) $idHarga;

      $whereMember = "bin = 0 AND lunas = 1 AND id_pelanggan = $idPelanggan AND id_harga = $idHarga";
      $saldoManual = $this->db(0)->get_cols_where('member', 'SUM(qty) as saldo', $whereMember, 0)['saldo'] ?? 0;

      $whereSale = $this->wCabang . " AND id_pelanggan = $idPelanggan AND member = 1 AND bin = 0 AND id_harga = $idHarga";
      $saldoPengurangan = $this->db(0)->get_cols_where('sale', 'SUM(qty) as saldo', $whereSale, 0)['saldo'] ?? 0;

      return round((float) $saldoManual - (float) $saldoPengurangan, 2);
   }
}
