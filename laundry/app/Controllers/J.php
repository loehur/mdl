<?php

/**
 * Customer PWA portal (experimental).
 * Parallel to Controller I — do not break existing /I routes.
 */
class J extends Controller
{
   private $dCabangPublic = [];

   public function index($pelanggan)
   {
      $this->shell($pelanggan, 'home');
   }

   public function tagihan($pelanggan)
   {
      $this->shell($pelanggan, 'tagihan');
   }

   public function saldo($pelanggan)
   {
      $this->shell($pelanggan, 'saldo');
   }

   public function paket($pelanggan)
   {
      $this->shell($pelanggan, 'paket');
   }

   public function paketDetail($pelanggan, $id_harga = 0)
   {
      $this->shell($pelanggan, 'paketDetail', $id_harga);
   }

   /** AJAX: HTML partial only */
   public function load($page, $pelanggan, $extra = null)
   {
      $pelanggan = $this->bootCustomer($pelanggan);
      $page = preg_replace('/[^a-zA-Z]/', '', (string) $page);
      $payload = [
         'base' => URL::BASE_URL,
         'assets' => URL::IN_ASSETS,
         'ex' => URL::EX_ASSETS,
         'data_pelanggan' => $this->pelanggan_p,
         'cabang' => $this->dCabangPublic,
      ];

      switch ($page) {
         case 'home':
            $payload['tagihan'] = $this->getTagihanSummary($pelanggan);
            $payload['saldoTunai'] = $this->getSaldoTunai($pelanggan);
            $payload['listPaket'] = $this->getListPaket($pelanggan);
            $this->view('j/partials/home', $payload);
            break;

         case 'tagihan':
            $full = $this->getTagihanFull($pelanggan, '', '');
            $payload['orders'] = $full['orders'];
            $payload['members'] = $full['members'];
            $payload['summary'] = $full['summary'];
            $payload['unpaid'] = $full['unpaid'];
            $payload['finance_history'] = $full['finance_history'];
            $payload['nonTunai'] = $full['nonTunai'];
            $payload['nonTunaiGuide'] = $full['nonTunaiGuide'];
            $payload['customer'] = $full['customer'];
            $this->view('j/partials/tagihan', $payload);
            break;

         case 'saldo':
            $payload = array_merge($payload, $this->buildSaldoPayload($pelanggan));
            $this->view('j/partials/saldo', $payload);
            break;

         case 'paket':
            $payload['listPaket'] = $this->buildPaketList($pelanggan);
            $this->view('j/partials/paket', $payload);
            break;

         case 'paketDetail':
            if (!is_numeric($extra)) {
               echo '<div class="j-empty"><b>Paket tidak ditemukan</b></div>';
               return;
            }
            $payload = array_merge($payload, $this->buildPaketDetailPayload($pelanggan, (int) $extra));
            $this->view('j/partials/paket_detail', $payload);
            break;

         default:
            http_response_code(404);
            echo '<div class="j-empty"><b>Halaman tidak ditemukan</b></div>';
      }
   }

   /** Customer-scoped PWA manifest */
   public function manifest($pelanggan)
   {
      if (!is_numeric($pelanggan)) exit();
      $pelanggan = (int) $pelanggan;
      $base = URL::BASE_URL;

      header('Content-Type: application/manifest+json; charset=utf-8');
      header('Cache-Control: public, max-age=3600');

      echo json_encode([
         'id' => $base . 'J/' . $pelanggan,
         'name' => 'MDL Customer',
         'short_name' => 'MDL',
         'description' => 'Cek tagihan, saldo, dan paket laundry Anda',
         'start_url' => $base . 'J/' . $pelanggan,
         'scope' => $base . 'J/',
         'display' => 'standalone',
         'background_color' => '#0B3D3A',
         'theme_color' => '#0B3D3A',
         'orientation' => 'portrait',
         'lang' => 'id',
         'icons' => [
            [
               'src' => $base . 'in_assets/icon/j-icon.svg',
               'sizes' => 'any',
               'type' => 'image/svg+xml',
               'purpose' => 'any',
            ],
         ],
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      exit;
   }

   private function shell($pelanggan, $page, $extra = null)
   {
      $pelanggan = $this->bootShell($pelanggan);
      $this->render('j/shell', [
         'active' => ($page === 'paketDetail') ? 'paket' : $page,
         'title' => 'MDL',
         'page' => $page,
         'extra' => $extra,
         'data_pelanggan' => $this->pelanggan_p,
         'cabang' => $this->dCabangPublic,
      ]);
   }

   private function bootShell($pelanggan)
   {
      if (!is_numeric($pelanggan)) {
         exit();
      }
      $pelanggan = (int) $pelanggan;
      $this->pelanggan_p = $this->db(0)->get_where_row('pelanggan', 'id_pelanggan = ' . $pelanggan);
      if (empty($this->pelanggan_p) || !isset($this->pelanggan_p['id_pelanggan'])) {
         exit();
      }
      $this->id_cabang_p = $this->pelanggan_p['id_cabang'];
      $cabang = $this->db(0)->get_where_row('cabang', 'id_cabang = ' . (int) $this->id_cabang_p);
      $this->dCabangPublic = is_array($cabang) ? $cabang : ['kode_cabang' => '00', 'nama_cabang' => 'MDL Laundry'];
      return $pelanggan;
   }

   private function bootCustomer($pelanggan)
   {
      if (!is_numeric($pelanggan)) {
         exit();
      }
      $pelanggan = (int) $pelanggan;
      $this->public_data($pelanggan);
      if (empty($this->pelanggan_p) || !isset($this->pelanggan_p['id_pelanggan'])) {
         exit();
      }
      $cabang = $this->db(0)->get_where_row('cabang', 'id_cabang = ' . (int) $this->id_cabang_p);
      $this->dCabangPublic = is_array($cabang) ? $cabang : ['kode_cabang' => '00', 'nama_cabang' => 'MDL Laundry'];
      return $pelanggan;
   }

   private function render($view, $data)
   {
      $data['base'] = URL::BASE_URL;
      $data['assets'] = URL::IN_ASSETS;
      $data['ex'] = URL::EX_ASSETS;
      $this->view($view, $data);
   }

   private function buildSaldoPayload($pelanggan)
   {
      $cols = 'id_kas, id_client, jumlah, metode_mutasi, note, insertTime, jenis_mutasi, jenis_transaksi';
      $where = "id_client = $pelanggan AND status_mutasi = 3 AND ((jenis_transaksi = 1 AND metode_mutasi = 3) OR (jenis_transaksi = 3 AND metode_mutasi = 3) OR jenis_transaksi = 6) ORDER BY insertTime ASC";
      $rows = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      if (!is_array($rows) || isset($rows['errno'])) $rows = [];

      $saldo = 0;
      $history = [];
      foreach ($rows as $v) {
         if ((int) $v['jenis_mutasi'] === 1) {
            $saldo += (float) $v['jumlah'];
         } else {
            $saldo -= (float) $v['jumlah'];
         }
         $v['saldo'] = $saldo;
         $history[] = $v;
      }

      $tampil = 30;
      $show = count($history) > $tampil ? array_slice($history, -$tampil) : $history;

      return [
         'saldoTunai' => $saldo,
         'history' => array_reverse($show),
         'tampil' => $tampil,
      ];
   }

   private function buildPaketList($pelanggan)
   {
      $list = $this->getListPaket($pelanggan);
      $enriched = [];
      foreach ($list as $lp) {
         $idHarga = (int) $lp['id_harga'];
         $info = $this->resolveHargaInfo($idHarga);
         $enriched[] = [
            'id_harga' => $idHarga,
            'label' => $info['label'],
            'satuan' => $info['satuan'],
            'saldo' => $this->calcPaketSaldo($pelanggan, $idHarga),
         ];
      }
      return $enriched;
   }

   private function buildPaketDetailPayload($pelanggan, $id_harga)
   {
      $this->dLayanan = $this->db(0)->get('layanan');
      $this->dDurasi = $this->db(0)->get('durasi');
      $this->dPenjualan = $this->db(0)->get('penjualan_jenis');
      $this->dSatuan = $this->db(0)->get('satuan');
      $this->harga = $this->db(0)->get_order('harga', 'sort ASC');
      $this->itemGroup = $this->db(0)->get('item_group');

      $sales = $this->db(0)->get_cols_where(
         'sale',
         'id_penjualan, id_penjualan_jenis, qty, min_order, insertTime',
         "id_pelanggan = $pelanggan AND id_harga = $id_harga AND bin = 0 AND member = 1 ORDER BY insertTime ASC, id_penjualan ASC"
      );
      if (!is_array($sales) || isset($sales['errno'])) $sales = [];

      $topups = $this->db(0)->get_cols_where(
         'member',
         'id_member, qty, insertTime',
         "id_pelanggan = $pelanggan AND id_harga = $id_harga AND bin = 0 AND lunas = 1 ORDER BY insertTime ASC, id_member ASC"
      );
      if (!is_array($topups) || isset($topups['errno'])) $topups = [];

      $info = $this->resolveHargaInfo($id_harga);
      $built = $this->buildPaketHistory(array_values($sales), array_values($topups), $info['satuan']);

      return [
         'id_harga' => $id_harga,
         'info' => $info,
         'history' => $built['tampil'],
         'lastSaldo' => $built['lastSaldo'],
         'satuan' => $built['satuan'],
      ];
   }

   private function getSaldoTunai($pelanggan)
   {
      $topup = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3") ?? 0;
      $topup_out = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3") ?? 0;
      $usage = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$pelanggan' AND metode_mutasi = 3 AND jenis_mutasi = 2") ?? 0;
      return (float) $topup - (float) $topup_out - (float) $usage;
   }

   private function getListPaket($pelanggan)
   {
      $rows = $this->db(0)->get_where('member', "id_pelanggan = $pelanggan AND bin = 0 AND lunas = 1 GROUP BY id_harga");
      return is_array($rows) ? $rows : [];
   }

   private function getTagihanSummary($pelanggan)
   {
      $full = $this->getTagihanFull($pelanggan, '', '');
      return $full['summary'];
   }

   private function getTagihanFull($pelanggan, $bulan, $tahun)
   {
      $filter = ['bulan' => $bulan, 'tahun' => $tahun, 'active' => ($bulan !== '' && $tahun !== '')];

      if ($filter['active']) {
         $bulannya = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT);
         $bulannya = $this->db(0)->escape($bulannya);
         $where = "id_pelanggan = $pelanggan AND insertTime LIKE '$bulannya%' AND bin = 0 AND tuntas = 0 ORDER BY id_penjualan DESC";
      } else {
         $where = "id_pelanggan = $pelanggan AND bin = 0 AND tuntas = 0 ORDER BY id_penjualan DESC";
      }

      $sales = $this->db(0)->get_where('sale', $where);
      if (!is_array($sales)) $sales = [];

      $ids = [];
      $refs = [];
      foreach ($sales as $dm) {
         $ids[] = $dm['id_penjualan'];
         $refs[$dm['no_ref']] = true;
      }

      $operasi = [];
      $kas = [];
      $surcas = [];

      if (!empty($ids)) {
         $ids_in = "'" . implode("','", array_map('strval', $ids)) . "'";
         $operasi = $this->db(0)->get_where('operasi', 'id_cabang = ' . (int) $this->id_cabang_p . " AND id_penjualan IN ($ids_in)");
         if (!is_array($operasi)) $operasi = [];
      }
      if (!empty($refs)) {
         $safe = [];
         foreach (array_keys($refs) as $rk) {
            $safe[] = "'" . str_replace("'", "''", $rk) . "'";
         }
         $refs_in = implode(',', $safe);
         $kas = $this->db(0)->get_where('kas', 'id_cabang = ' . (int) $this->id_cabang_p . " AND jenis_transaksi = 1 AND ref_transaksi IN ($refs_in)");
         $surcas = $this->db(0)->get_where('surcas', 'id_cabang = ' . (int) $this->id_cabang_p . " AND no_ref IN ($refs_in)");
         if (!is_array($kas)) $kas = [];
         if (!is_array($surcas)) $surcas = [];
      }

      // Map lookups
      $mapSatuan = [];
      foreach ($this->dPenjualan as $l) {
         $sat = '';
         foreach ($this->dSatuan as $sa) {
            if ($sa['id_satuan'] == $l['id_satuan']) {
               $sat = $sa['nama_satuan'];
               break;
            }
         }
         $mapSatuan[$l['id_penjualan_jenis']] = $sat;
      }
      $mapKategori = [];
      foreach ($this->itemGroup as $g) {
         $mapKategori[$g['id_item_group']] = $g['item_kategori'];
      }
      $mapDurasi = [];
      foreach ($this->dDurasi as $d) {
         $mapDurasi[$d['id_durasi']] = $d['durasi'];
      }
      $mapLayanan = [];
      foreach ($this->dLayanan as $c) {
         $mapLayanan[$c['id_layanan']] = $c['layanan'];
      }
      $mapSurcasJenis = [];
      foreach ($this->surcasPublic as $sc) {
         $mapSurcasJenis[$sc['id_surcas_jenis']] = $sc['surcas_jenis'];
      }

      $opsBySale = [];
      foreach ($operasi as $o) {
         $opsBySale[$o['id_penjualan']][] = $o;
      }
      $kasByRef = [];
      foreach ($kas as $k) {
         $kasByRef[$k['ref_transaksi']][] = $k;
      }
      $surcasByRef = [];
      foreach ($surcas as $s) {
         $surcasByRef[$s['no_ref']][] = $s;
      }

      $orders = [];
      $totalTagihan = 0;
      $totalTagihanAsli = 0;
      $totalDibayar = 0;

      foreach ($sales as $a) {
         $ref = $a['no_ref'];
         if (!isset($orders[$ref])) {
            $orders[$ref] = [
               'no_ref' => $ref,
               'insertTime' => $a['insertTime'],
               'items' => [],
               'subtotal' => 0,
               'subtotal_asli' => 0,
               'dibayar' => 0,
               'sisa' => 0,
               'letak' => $a['letak'] ?? '',
               'member' => (int) $a['member'],
               'status_ops' => [],
               'payments' => [],
               'surcas' => [],
            ];
         }

         $qty = round((float) $a['qty'], 2);
         $min = round(isset($a['min_order']) ? (float) $a['min_order'] : 0, 2);
         $qtyReal = $qty < $min ? $min : $qty;
         $harga = (float) $a['harga'];
         $member = (int) $a['member'];
         $diskonQty = (float) $a['diskon_qty'];
         $diskonPartner = (float) $a['diskon_partner'];

         $totalAsli = 0;
         $hasDiskon = false;
         if ($member === 0) {
            $totalAsli = round($harga * $qtyReal);
            $line = $harga * $qtyReal;
            if ($diskonQty > 0) $line -= $line * ($diskonQty / 100);
            if ($diskonPartner > 0) $line -= $line * ($diskonPartner / 100);
            $line = round($line);
            $hasDiskon = ($diskonQty > 0 || $diskonPartner > 0);
         } else {
            $line = 0;
         }

         $satuan = $mapSatuan[$a['id_penjualan_jenis']] ?? '';
         $kategori = $mapKategori[$a['id_item_group']] ?? '';
         $durasi = strtoupper($mapDurasi[$a['id_durasi']] ?? '');

         $layananList = [];
         $listLay = @unserialize($a['list_layanan']);
         if (is_array($listLay)) {
            $listLay = array_map('intval', $listLay);
            sort($listLay, SORT_NUMERIC);
            $maxDoneId = 0;
            foreach ($listLay as $lid) {
               $nama = $mapLayanan[$lid] ?? ('#' . $lid);
               $done = false;
               foreach ($opsBySale[$a['id_penjualan']] ?? [] as $op) {
                  if ((int) $op['jenis_operasi'] === (int) $lid) {
                     $done = true;
                     break;
                  }
               }
               if ($done && $lid > $maxDoneId) {
                  $maxDoneId = $lid;
               }
               $layananList[] = ['id' => $lid, 'nama' => $nama, 'done' => $done];
            }
            // Jika ID lebih besar sudah selesai, ID lebih kecil ikut terlihat tercentang
            if ($maxDoneId > 0) {
               foreach ($layananList as &$ly) {
                  if ($ly['id'] < $maxDoneId) {
                     $ly['done'] = true;
                  }
               }
               unset($ly);
            }
         }

         $orders[$ref]['items'][] = [
            'id' => $a['id_penjualan'],
            'kategori' => $kategori,
            'durasi' => $durasi,
            'qty_show' => $this->fmtDecMax2($qty) . $satuan . ($qty < $min ? ' (Min.' . $this->fmtDecMax2($min) . ')' : ''),
            'total' => $line,
            'total_asli' => $totalAsli,
            'has_diskon' => $hasDiskon,
            'member' => $member,
            'layanan' => $layananList,
            'ambil' => (int) $a['id_user_ambil'] > 0,
         ];
         $orders[$ref]['subtotal'] += $line;
         $orders[$ref]['subtotal_asli'] += ($member === 0 ? $totalAsli : 0);
         if (!empty($a['letak'])) $orders[$ref]['letak'] = $a['letak'];
      }

      foreach ($orders as $ref => &$ord) {
         foreach ($surcasByRef[$ref] ?? [] as $sc) {
            $nama = $mapSurcasJenis[$sc['id_jenis_surcas']] ?? 'Surcharge';
            $jumlah = (float) $sc['jumlah'];
            $ord['surcas'][] = ['nama' => $nama, 'jumlah' => $jumlah];
            $ord['subtotal'] += $jumlah;
            $ord['subtotal_asli'] += $jumlah;
         }
         $dibayar = 0;
         $hasActivePay = false;
         foreach ($kasByRef[$ref] ?? [] as $k) {
            $st = (int) $k['status_mutasi'];
            $ord['payments'][] = [
               'id_kas' => (int) ($k['id_kas'] ?? 0),
               'jumlah' => (float) $k['jumlah'],
               'status' => $st,
               'note' => $k['note'] ?? '',
               'time' => $k['insertTime'],
               'ref_finance' => $k['ref_finance'] ?? '',
               'id_user' => (int) ($k['id_user'] ?? 0),
            ];
            if ($st === 3) $dibayar += (float) $k['jumlah'];
            if ($st !== 4) $hasActivePay = true;
         }
         $ord['dibayar'] = $dibayar;
         $ord['sisa'] = max(0, (int) round($ord['subtotal']) - (int) $dibayar);
         $ord['has_payment'] = $hasActivePay;
         $ord['has_diskon'] = (int) round($ord['subtotal_asli']) > (int) round($ord['subtotal']);
         $totalTagihan += (int) round($ord['subtotal']);
         $totalTagihanAsli += (int) round($ord['subtotal_asli']);
         $totalDibayar += (int) $dibayar;
      }
      unset($ord);

      // Notif nota (tipe=1)
      $notifBon = [];
      if (!empty($refs)) {
         $safeRefs = [];
         foreach (array_keys($refs) as $rk) {
            $safeRefs[] = "'" . str_replace("'", "''", $rk) . "'";
         }
         $notifRows = $this->db(0)->get_where(
            'notif',
            'id_cabang = ' . (int) $this->id_cabang_p . ' AND tipe = 1 AND no_ref IN (' . implode(',', $safeRefs) . ')'
         );
         if (is_array($notifRows)) {
            foreach ($notifRows as $nr) {
               $notifBon[$nr['no_ref']] = $nr['state'] ?? 'sent';
            }
         }
      }
      foreach ($orders as $ref => &$ord) {
         $ord['can_send_nota'] = !isset($notifBon[$ref]);
         $ord['nota_state'] = $notifBon[$ref] ?? null;
      }
      unset($ord);

      // Unpaid member packages
      $data_member = $this->db(0)->get_where_order(
         'member',
         'id_cabang = ' . (int) $this->id_cabang_p . " AND bin = 0 AND id_pelanggan = $pelanggan AND lunas = 0",
         'id_member DESC'
      );
      if (!is_array($data_member)) $data_member = [];

      $membersOut = [];
      $kasM = [];
      if (!empty($data_member)) {
         $memberIds = array_column($data_member, 'id_member');
         $safeM = implode(',', array_map('intval', $memberIds));
         $kasM = $this->db(0)->get_where('kas', 'id_cabang = ' . (int) $this->id_cabang_p . " AND jenis_transaksi = 3 AND ref_transaksi IN ($safeM)");
         if (!is_array($kasM)) $kasM = [];
         $bayarMap = [];
         foreach ($kasM as $ck) {
            if ((int) $ck['status_mutasi'] === 3) {
               $bayarMap[$ck['ref_transaksi']] = ($bayarMap[$ck['ref_transaksi']] ?? 0) + (float) $ck['jumlah'];
            }
         }
         foreach ($data_member as $m) {
            $paid = $bayarMap[$m['id_member']] ?? 0;
            if ($paid >= (float) $m['harga']) continue;
            $info = $this->resolveHargaInfo((int) $m['id_harga']);
            $sisa = (float) $m['harga'] - $paid;
            $membersOut[] = [
               'id_member' => $m['id_member'],
               'id_harga' => $m['id_harga'],
               'label' => $info['label'],
               'qty' => $m['qty'],
               'harga' => (float) $m['harga'],
               'dibayar' => $paid,
               'sisa' => $sisa,
               'insertTime' => $m['insertTime'],
            ];
            $totalTagihan += (float) $m['harga'];
            $totalTagihanAsli += (float) $m['harga'];
            $totalDibayar += $paid;
         }
      }

      // Unpaid list for bayar modal
      $unpaid = [];
      foreach ($orders as $ord) {
         if ((float) $ord['sisa'] > 0) {
            $unpaid[] = [
               'ref' => 'T_' . $ord['no_ref'],
               'label' => 'REF #' . $ord['no_ref'],
               'amount' => (int) $ord['sisa'],
            ];
         }
      }
      foreach ($membersOut as $m) {
         if ((float) $m['sisa'] > 0) {
            $unpaid[] = [
               'ref' => 'M_' . $m['id_member'],
               'label' => 'Paket M' . $m['id_harga'] . ' #' . $m['id_member'],
               'amount' => (int) round($m['sisa']),
            ];
         }
      }

      // Pending finance (status_mutasi = 2)
      $finance_history = [];
      $allKasPending = array_merge($kas, $kasM);
      foreach ($allKasPending as $k) {
         if (empty($k['ref_finance'])) continue;
         if ((int) $k['status_mutasi'] !== 2) continue;
         $rf = $k['ref_finance'];
         if (!isset($finance_history[$rf])) {
            $finance_history[$rf] = [
               'ref_finance' => $rf,
               'total' => 0,
               'status' => (int) $k['status_mutasi'],
               'note' => $k['note'] ?? '',
               'insertTime' => $k['insertTime'],
               'id_user' => (int) ($k['id_user'] ?? 0),
            ];
         }
         $finance_history[$rf]['total'] += (int) $k['jumlah'];
         if (($k['insertTime'] ?? '') > $finance_history[$rf]['insertTime']) {
            $finance_history[$rf]['insertTime'] = $k['insertTime'];
            $finance_history[$rf]['note'] = $k['note'] ?? '';
            $finance_history[$rf]['id_user'] = (int) ($k['id_user'] ?? 0);
         }
      }

      $nonTunaiGuide = URL::NON_TUNAI_GUIDE;
      $nonTunai = URL::NON_TUNAI;

      return [
         'orders' => array_values($orders),
         'members' => $membersOut,
         'summary' => [
            'total_tagihan' => $totalTagihan,
            'total_tagihan_asli' => $totalTagihanAsli,
            'has_diskon' => (int) round($totalTagihanAsli) > (int) round($totalTagihan),
            'total_dibayar' => $totalDibayar,
            'sisa' => max(0, $totalTagihan - $totalDibayar),
            'count_order' => count($orders),
            'count_member' => count($membersOut),
         ],
         'filter' => $filter,
         'unpaid' => $unpaid,
         'finance_history' => array_values($finance_history),
         'nonTunai' => $nonTunai,
         'nonTunaiGuide' => $nonTunaiGuide,
         'customer' => [
            'id' => (int) $pelanggan,
            'nama' => $this->pelanggan_p['nama_pelanggan'] ?? '',
            'hp' => $this->pelanggan_p['nomor_pelanggan'] ?? '',
         ],
      ];
   }

   private function resolveHargaInfo($id_harga)
   {
      if (empty($this->harga)) {
         $this->harga = $this->db(0)->get_order('harga', 'sort ASC');
      }
      if (empty($this->dLayanan)) $this->dLayanan = $this->db(0)->get('layanan');
      if (empty($this->dDurasi)) $this->dDurasi = $this->db(0)->get('durasi');
      if (empty($this->itemGroup)) $this->itemGroup = $this->db(0)->get('item_group');
      if (empty($this->dPenjualan)) $this->dPenjualan = $this->db(0)->get('penjualan_jenis');
      if (empty($this->dSatuan)) $this->dSatuan = $this->db(0)->get('satuan');

      $kategori = '';
      $layanan = '';
      $durasi = '';
      $satuan = '';
      $idJenis = 0;

      foreach ($this->harga as $a) {
         if ((int) $a['id_harga'] !== (int) $id_harga) continue;
         $idJenis = (int) ($a['id_penjualan_jenis'] ?? 0);
         $list = @unserialize($a['list_layanan']);
         if (is_array($list)) {
            foreach ($list as $b) {
               foreach ($this->dLayanan as $c) {
                  if ($c['id_layanan'] == $b) $layanan .= ' ' . $c['layanan'];
               }
            }
         }
         foreach ($this->dDurasi as $c) {
            if ($c['id_durasi'] == $a['id_durasi']) $durasi .= ' ' . $c['durasi'];
         }
         foreach ($this->itemGroup as $c) {
            if ($c['id_item_group'] == $a['id_item_group']) $kategori .= ' ' . $c['item_kategori'];
         }
         break;
      }

      foreach ($this->dPenjualan as $l) {
         if ((int) $l['id_penjualan_jenis'] === $idJenis) {
            foreach ($this->dSatuan as $sa) {
               if ($sa['id_satuan'] == $l['id_satuan']) {
                  $satuan = $sa['nama_satuan'];
                  break;
               }
            }
         }
      }

      $label = trim($kategori . ',' . $layanan . ',' . $durasi, ' ,');
      return ['label' => $label !== '' ? $label : ('Paket M' . $id_harga), 'satuan' => $satuan, 'kategori' => trim($kategori)];
   }

   private function calcPaketSaldo($pelanggan, $id_harga)
   {
      $top = $this->db(0)->sum_col_where('member', 'qty', "id_pelanggan = $pelanggan AND id_harga = $id_harga AND bin = 0 AND lunas = 1") ?? 0;
      $use = $this->db(0)->sum_col_where('sale', 'qty', "id_pelanggan = $pelanggan AND id_harga = $id_harga AND bin = 0 AND member = 1") ?? 0;
      return round((float) $top - (float) $use, 2);
   }

   private function buildPaketHistory(array $sales, array $topups, $satuanDefault)
   {
      $mapSatuanByJenis = [];
      foreach ($this->dPenjualan as $l) {
         $satuan = '';
         foreach ($this->dSatuan as $sa) {
            if ($sa['id_satuan'] == $l['id_satuan']) {
               $satuan = $sa['nama_satuan'];
               break;
            }
         }
         $mapSatuanByJenis[$l['id_penjualan_jenis']] = $satuan;
      }

      $topups = $this->helper('PaketHistory')->withMergeTimes($topups, $sales);

      $iSale = 0;
      $iTop = 0;
      $nSale = count($sales);
      $nTop = count($topups);
      $saldo = 0;
      $arr = [];
      $satuan = $satuanDefault;

      // Merge ASC by waktu efektif, lalu reverse untuk tampil newest-first
      while ($iSale < $nSale || $iTop < $nTop) {
         $saleTime = ($iSale < $nSale) ? strtotime($sales[$iSale]['insertTime']) : PHP_INT_MAX;
         if ($saleTime === false) $saleTime = PHP_INT_MAX;
         $topTime = ($iTop < $nTop) ? (int) ($topups[$iTop]['_mergeTime'] ?? 0) : PHP_INT_MAX;

         // Topup yang lebih awal dari sale berikutnya ikut dulu
         if ($iTop < $nTop && $topTime < $saleTime) {
            $m = $topups[$iTop];
            $saldo += (float) $m['qty'];
            $ts = strtotime($m['insertTime'] ?? '');
            $arr[] = [
               'tipe' => 1,
               'id' => $m['id_member'],
               'tgl' => date('d M Y', $ts !== false ? $ts : time()),
               'qty' => $m['qty'],
               'saldo' => $saldo,
            ];
            $iTop++;
            continue;
         }

         if ($iSale < $nSale) {
            $a = $sales[$iSale];
            if ($satuan === '' && isset($mapSatuanByJenis[$a['id_penjualan_jenis']])) {
               $satuan = $mapSatuanByJenis[$a['id_penjualan_jenis']];
            }
            $qty = round((float) $a['qty'], 2);
            $min = round(isset($a['min_order']) ? (float) $a['min_order'] : 0, 2);
            $qtyReal = $qty < $min ? round($min, 2) : round($qty, 2);
            // Saldo debit tetap pakai qty (bukan qty_real) — perilaku klasik
            $saldo -= $qty;
            $ts = strtotime($a['insertTime']);
            $arr[] = [
               'tipe' => 0,
               'id' => $a['id_penjualan'],
               'tgl' => date('d M Y', $ts !== false ? $ts : time()),
               'qty' => $qtyReal,
               'saldo' => $saldo,
            ];
            $iSale++;
            continue;
         }

         // Sisa topup setelah semua sale
         $m = $topups[$iTop];
         $saldo += (float) $m['qty'];
         $ts = strtotime($m['insertTime'] ?? '');
         $arr[] = [
            'tipe' => 1,
            'id' => $m['id_member'],
            'tgl' => date('d M Y', $ts !== false ? $ts : time()),
            'qty' => $m['qty'],
            'saldo' => $saldo,
         ];
         $iTop++;
      }

      $tampilN = 20;
      $tampil = count($arr) > $tampilN ? array_slice($arr, -$tampilN) : $arr;
      return [
         'tampil' => array_reverse($tampil),
         'lastSaldo' => $saldo,
         'satuan' => $satuan,
      ];
   }
}
