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

   public function riwayat($pelanggan, $ym = null)
   {
      $this->shell($pelanggan, 'riwayat', $ym);
   }

   public function saldo($pelanggan)
   {
      $this->shell($pelanggan, 'saldo');
   }

   public function paket($pelanggan)
   {
      $this->shell($pelanggan, 'paket');
   }

   public function kurir($pelanggan)
   {
      $this->shell($pelanggan, 'kurir');
   }

   public function paketDetail($pelanggan, $id_harga = 0)
   {
      $this->shell($pelanggan, 'paketDetail', $id_harga);
   }

   public function topup($pelanggan, $id_harga = 0)
   {
      $this->shell($pelanggan, 'topup', $id_harga);
   }

   /** POST: create unpaid member topup, then pay via Tagihan */
   public function topupSubmit($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $idHargaPaket = isset($_POST['id_harga_paket']) ? (int) $_POST['id_harga_paket'] : 0;
      if ($idHargaPaket <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Pilih paket terlebih dahulu']);
         return;
      }

      $paket = $this->db(0)->get_where_row('harga_paket', 'id_harga_paket = ' . $idHargaPaket);
      if (!is_array($paket) || empty($paket['id_harga_paket'])) {
         echo json_encode(['ok' => false, 'message' => 'Paket tidak ditemukan']);
         return;
      }

      $idHarga = (int) $paket['id_harga'];
      $qty = is_numeric($paket['qty']) ? $paket['qty'] : 0;
      $harga = $this->resolveHargaPaketUnit($paket);
      $idCabang = (int) $this->id_cabang_p;

      // Limit spam: max 2 topup belum lunas (paket berbayar)
      if ((float) $harga > 0) {
         $unpaidCount = (int) ($this->db(0)->count_where(
            'member',
            "id_cabang = $idCabang AND id_pelanggan = $pelanggan AND bin = 0 AND lunas = 0"
         ) ?? 0);
         if ($unpaidCount >= 2) {
            echo json_encode([
               'ok' => false,
               'message' => 'Maksimal 2 topup belum lunas. Bayar atau batalkan dulu di Tagihan.',
               'go' => 'tagihan',
            ]);
            return;
         }
      }

      $today = date('Y-m-d');
      $dupWhere = "id_cabang = $idCabang AND id_pelanggan = $pelanggan AND id_harga = $idHarga AND qty = $qty AND insertTime LIKE '" . $today . "%' AND bin = 0";
      $dupCount = (int) ($this->db(0)->count_where('member', $dupWhere) ?? 0);

      if ($dupCount < 1) {
         $do = $this->db(0)->insert('member', [
            'id_cabang' => $idCabang,
            'id_pelanggan' => $pelanggan,
            'id_harga' => $idHarga,
            'qty' => $qty,
            'harga' => $harga,
            'id_user' => 0,
            'lunas' => ((float) $harga <= 0) ? 1 : 0,
         ]);
         if (isset($do['errno']) && (int) $do['errno'] !== 0) {
            $this->model('Log')->write(__CLASS__ . '->' . __FUNCTION__ . '() ' . ($do['error'] ?? ''));
            echo json_encode(['ok' => false, 'message' => 'Gagal membuat topup']);
            return;
         }
      }

      $go = ((float) $harga <= 0) ? 'paket' : 'tagihan';
      $message = ((float) $harga <= 0)
         ? 'Topup berhasil. Saldo paket sudah bertambah.'
         : 'Topup dibuat. Silakan bayar di Tagihan.';

      echo json_encode(['ok' => true, 'go' => $go, 'message' => $message]);
   }

   /** POST: batalkan topup paket belum lunas (hapus permanen — tanpa pembayaran) */
   public function topupCancel($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $idMember = isset($_POST['id_member']) ? (int) $_POST['id_member'] : 0;
      if ($idMember <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Topup tidak valid']);
         return;
      }

      $row = $this->db(0)->get_where_row(
         'member',
         'id_member = ' . $idMember
            . ' AND id_pelanggan = ' . $pelanggan
            . ' AND id_cabang = ' . (int) $this->id_cabang_p
            . ' AND bin = 0'
      );
      if (!is_array($row) || empty($row['id_member'])) {
         echo json_encode(['ok' => false, 'message' => 'Topup tidak ditemukan']);
         return;
      }
      if ((int) ($row['lunas'] ?? 0) === 1) {
         echo json_encode(['ok' => false, 'message' => 'Topup sudah lunas, tidak bisa dibatalkan']);
         return;
      }

      $kasRows = $this->db(0)->get_where(
         'kas',
         'id_cabang = ' . (int) $this->id_cabang_p
            . ' AND jenis_transaksi = 3 AND (ref_transaksi = \'' . $idMember . '\' OR CAST(ref_transaksi AS UNSIGNED) = ' . $idMember . ')'
      );
      if (!is_array($kasRows)) $kasRows = [];
      foreach ($kasRows as $k) {
         $st = (int) ($k['status_mutasi'] ?? 0);
         if ($st === 3) {
            echo json_encode(['ok' => false, 'message' => 'Sudah ada pembayaran, tidak bisa dibatalkan']);
            return;
         }
         if ($st === 2) {
            echo json_encode(['ok' => false, 'message' => 'Batalkan dulu pembayaran yang menunggu']);
            return;
         }
      }

      $whereDel = 'id_member = ' . $idMember
         . ' AND id_pelanggan = ' . $pelanggan
         . ' AND id_cabang = ' . (int) $this->id_cabang_p
         . ' AND bin = 0 AND lunas = 0';
      $do = $this->db(0)->delete('member', $whereDel);
      if (isset($do['errno']) && (int) $do['errno'] !== 0) {
         $this->model('Log')->write(__CLASS__ . '->' . __FUNCTION__ . '() ' . ($do['error'] ?? ''));
         echo json_encode(['ok' => false, 'message' => 'Gagal membatalkan topup']);
         return;
      }

      echo json_encode(['ok' => true, 'message' => 'Topup paket dihapus']);
   }

   /**
    * GET/JSON: list lokasi pelanggan + Tarif antar (jarak ke cabang).
    * Untuk UI Request Antar di J.
    */
   public function lokasiList($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $built = $this->buildLokasiListWithTarif($pelanggan);
      if (!$built['ok']) {
         echo json_encode(['ok' => false, 'message' => $built['message'], 'data' => []]);
         return;
      }

      echo json_encode([
         'ok' => true,
         'data' => $built['list'],
         'cabang' => $built['cabang'],
      ]);
   }

   /**
    * POST: Request Antar - insert surcas Pengantaran (id_jenis_surcas=2)
    * ke satu no_ref belum tuntas dari item terpilih.
    * Body: id_lokasi, ids[] (id_penjualan)
    */
   public function requestAntar($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $idLokasi = isset($_POST['id_lokasi']) ? (int) $_POST['id_lokasi'] : 0;
      $idsRaw = $_POST['ids'] ?? [];
      if (!is_array($idsRaw)) {
         $idsRaw = [$idsRaw];
      }
      $ids = [];
      foreach ($idsRaw as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $ids[$id] = $id;
         }
      }
      $ids = array_values($ids);

      if ($idLokasi <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Pilih lokasi pengantaran']);
         return;
      }
      if (empty($ids)) {
         echo json_encode(['ok' => false, 'message' => 'Pilih minimal satu item']);
         return;
      }

      $lokasi = $this->db(0)->get_where_row(
         'pelanggan_lokasi',
         'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . (int) $pelanggan
      );
      if (!is_array($lokasi) || empty($lokasi['id_lokasi'])) {
         echo json_encode(['ok' => false, 'message' => 'Lokasi tidak ditemukan']);
         return;
      }

      $cabLat = (float) ($this->dCabangPublic['latt'] ?? 0);
      $cabLon = (float) ($this->dCabangPublic['long'] ?? 0);
      if ($cabLat == 0.0 && $cabLon == 0.0) {
         echo json_encode(['ok' => false, 'message' => 'Lokasi cabang belum diatur']);
         return;
      }

      $locLat = (float) ($lokasi['latt'] ?? 0);
      $locLon = (float) ($lokasi['longt'] ?? 0);
      if ($locLat == 0.0 && $locLon == 0.0) {
         echo json_encode(['ok' => false, 'message' => 'Koordinat lokasi pelanggan belum lengkap']);
         return;
      }

      $tarifHelper = $this->helper('AntarTarif');
      $calc = $tarifHelper->tarifFromCoordsForPelanggan($cabLat, $cabLon, $locLat, $locLon, (int) $pelanggan);
      $jumlah = (int) $calc['tarif'];
      $km = (float) $calc['km'];

      $noRef = $this->pickBelumTuntasRef($pelanggan, $ids);
      if ($noRef === null || $noRef === '') {
         echo json_encode([
            'ok' => false,
            'message' => 'Tidak ada item belum tuntas. Surcas hanya bisa ke ref yang masih proses.',
         ]);
         return;
      }

      $inserted = $this->insertSurcasPengantaran($noRef, $jumlah, 0, $ids);
      if ($inserted === false) {
         echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan surcas pengantaran']);
         return;
      }

      echo json_encode([
         'ok' => true,
         'message' => $inserted === 'exists'
            ? 'Item yang dipilih sudah terikat surcas pengantaran'
            : 'Surcas pengantaran ditambahkan',
         'data' => [
            'no_ref' => $noRef,
            'jumlah' => $jumlah,
            'km' => $km,
            'id_lokasi' => $idLokasi,
            'id_jenis_surcas' => AntarTarif::SURCAS_JENIS_PENGANTARAN,
            'already_exists' => $inserted === 'exists',
         ],
      ]);
   }

   /** POST: topup saldo tunai — non-tunai pending (jt=6), bayar via gateway seperti Tagihan */
   public function saldoTopup($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $jumlah = isset($_POST['jumlah']) ? (int) round((float) $_POST['jumlah']) : 0;
      $note = isset($_POST['metode']) ? trim((string) $_POST['metode']) : '';
      $note = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $note);
      $allowed = URL::NON_TUNAI;
      if ($note === '' || !in_array($note, $allowed, true)) {
         echo json_encode(['ok' => false, 'message' => 'Pilih metode pembayaran']);
         return;
      }
      if ($jumlah <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Nominal tidak valid']);
         return;
      }
      if (strtoupper($note) === 'QRIS' && $jumlah < 1000) {
         echo json_encode(['ok' => false, 'message' => 'Minimal topup QRIS Rp1.000']);
         return;
      }
      if (strtoupper($note) !== 'QRIS' && $jumlah < 10000) {
         echo json_encode(['ok' => false, 'message' => 'Minimal topup transfer Rp10.000']);
         return;
      }

      $idCabang = (int) $this->id_cabang_p;
      $maxPending = 1;
      $pendingWhere = "id_cabang = $idCabang AND id_client = $pelanggan AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 2 AND id_user = 0";
      $pendingCount = (int) ($this->db(0)->count_where('kas', $pendingWhere) ?? 0);
      if ($pendingCount >= $maxPending) {
         echo json_encode([
            'ok' => false,
            'message' => 'Masih ada topup saldo menunggu pembayaran. Bayar atau batalkan dulu.',
         ]);
         return;
      }

      $maxSaldo = 5000000;
      $saldoNow = $this->getSaldoTunai($pelanggan);
      $pendingSum = (float) ($this->db(0)->sum_col_where('kas', 'jumlah', $pendingWhere) ?? 0);
      if (($saldoNow + $pendingSum + $jumlah) > $maxSaldo) {
         $sisa = max(0, $maxSaldo - $saldoNow - $pendingSum);
         echo json_encode([
            'ok' => false,
            'message' => 'Saldo maksimal Rp' . number_format($maxSaldo) . '. Sisa kapasitas Rp' . number_format($sisa),
         ]);
         return;
      }

      $refFinance = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      $idKas = (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
      $do = $this->db(0)->insert('kas', [
         'id_kas' => $idKas,
         'id_cabang' => $idCabang,
         'jenis_mutasi' => 1,
         'jenis_transaksi' => 6,
         'metode_mutasi' => 2,
         'note' => $note,
         'status_mutasi' => 2,
         'jumlah' => $jumlah,
         'id_user' => 0,
         'id_client' => $pelanggan,
         'ref_finance' => $refFinance,
      ]);
      if (isset($do['errno']) && (int) $do['errno'] !== 0) {
         $this->model('Log')->write(__CLASS__ . '->' . __FUNCTION__ . '() ' . ($do['error'] ?? ''));
         echo json_encode(['ok' => false, 'message' => 'Gagal membuat topup saldo']);
         return;
      }

      echo json_encode([
         'ok' => true,
         'ref_finance' => $refFinance,
         'total' => $jumlah,
         'note' => $note,
         'message' => 'Topup dibuat. Lanjutkan pembayaran.',
      ]);
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
            $payload['saldoTunai'] = $full['saldoTunai'] ?? 0;
            $payload['customer'] = $full['customer'];
            $this->view('j/partials/tagihan', $payload);
            break;

         case 'riwayat':
            $ym = trim((string) ($extra ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
               $ym = date('Y-m');
            }
            $parts = explode('-', $ym);
            $tahun = (int) $parts[0];
            $bulan = (int) $parts[1];
            $full = $this->getTagihanFull($pelanggan, $bulan, $tahun, 1);
            $payload['orders'] = $full['orders'];
            $payload['customer'] = $full['customer'];
            $payload['filter_ym'] = $ym;
            $payload['month_options'] = $this->buildRiwayatMonthOptions($tahun, $bulan);
            $this->view('j/partials/riwayat', $payload);
            break;

         case 'saldo':
            $payload = array_merge($payload, $this->buildSaldoPayload($pelanggan));
            $this->view('j/partials/saldo', $payload);
            break;

         case 'paket':
            $payload['listPaket'] = $this->buildPaketList($pelanggan);
            $this->view('j/partials/paket', $payload);
            break;

         case 'kurir':
            $payload['pendingKurir'] = $this->getPendingKurirRequests($pelanggan);
            $payload['riwayatKurir'] = $this->getKurirRiwayat($pelanggan);
            $payload['saldoTunai'] = $this->getSaldoTunai($pelanggan);
            $payload['instantWindow'] = $this->helper('OperatingHours')->instantOrderStatus();
            $this->view('j/partials/kurir', $payload);
            break;

         case 'lokasiAntar':
            $built = $this->buildLokasiListWithTarif($pelanggan);
            $payload['lokasi'] = $built['ok'] ? $built['list'] : [];
            $payload['lokasi_error'] = $built['ok'] ? '' : ($built['message'] ?? '');
            $this->view('j/partials/lokasi_antar', $payload);
            break;

         case 'paketDetail':
            if (!is_numeric($extra)) {
               echo '<div class="j-empty"><b>Paket tidak ditemukan</b></div>';
               return;
            }
            $payload = array_merge($payload, $this->buildPaketDetailPayload($pelanggan, (int) $extra));
            $this->view('j/partials/paket_detail', $payload);
            break;

         case 'topup':
            $filter = (is_numeric($extra) && (int) $extra > 0) ? (int) $extra : 0;
            $payload = array_merge($payload, $this->buildTopupCatalog($pelanggan, $filter));
            $this->view('j/partials/topup', $payload);
            break;

         default:
            http_response_code(404);
            echo '<div class="j-empty"><b>Halaman tidak ditemukan</b></div>';
      }
   }

   /**
    * Detail timeline nota untuk Portal J (tanpa nama petugas).
    * GET J/nota_detail/{id_pelanggan}?ref=
    */
   public function nota_detail($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);
      $idCabang = (int) $this->id_cabang_p;

      $ref = trim((string) ($_GET['ref'] ?? $_POST['ref'] ?? ''));
      if ($ref === '') {
         echo json_encode(['ok' => false, 'message' => 'Ref nota wajib diisi']);
         return;
      }

      $refEsc = $this->db(0)->escape($ref);
      $sales = $this->db(0)->get_where(
         'sale',
         "id_cabang = $idCabang AND id_pelanggan = $pelanggan AND no_ref = '$refEsc' AND bin = 0 ORDER BY id_penjualan ASC"
      );
      if (!is_array($sales) || empty($sales)) {
         echo json_encode(['ok' => false, 'message' => 'Nota tidak ditemukan']);
         return;
      }

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
      foreach ((array) ($this->surcasPublic ?? []) as $sc) {
         $mapSurcas[(int) ($sc['id_surcas_jenis'] ?? 0)] = (string) ($sc['surcas_jenis'] ?? '');
      }

      $first = $sales[0];
      $pelangganNama = strtoupper(trim((string) ($this->pelanggan_p['nama_pelanggan'] ?? '')));

      $saleIds = [];
      foreach ($sales as $s) {
         $sid = trim((string) ($s['id_penjualan'] ?? ''));
         if ($sid !== '') {
            $saleIds[$sid] = $sid;
         }
      }
      $idsIn = "'" . implode("','", array_map(function ($id) {
         return $this->db(0)->escape($id);
      }, array_values($saleIds))) . "'";

      $operasiRows = $this->db(0)->get_where('operasi', "id_cabang = $idCabang AND id_penjualan IN ($idsIn)");
      if (!is_array($operasiRows)) {
         $operasiRows = [];
      }
      $operasiMap = [];
      foreach ($operasiRows as $o) {
         $sid = trim((string) ($o['id_penjualan'] ?? ''));
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
         $sid = trim((string) ($dr['id_penjualan'] ?? ''));
         $jenis = strtolower((string) ($dr['jenis'] ?? ''));
         if ($sid === '' || !in_array($jenis, ['jemput', 'antar'], true)) {
            continue;
         }
         if (!isset($deliveryMap[$sid][$jenis])
            || strcmp((string) ($dr['insertTime'] ?? ''), (string) ($deliveryMap[$sid][$jenis]['insertTime'] ?? '')) > 0
         ) {
            $deliveryMap[$sid][$jenis] = $dr;
         }
      }

      $kasRows = $this->db(0)->get_where(
         'kas',
         "id_cabang = $idCabang AND jenis_transaksi = 1 AND ref_transaksi = '$refEsc' ORDER BY insertTime ASC, id_kas ASC"
      );
      if (!is_array($kasRows)) {
         $kasRows = [];
      }

      $surcasRows = $this->db(0)->get_where(
         'surcas',
         "id_cabang = $idCabang AND no_ref = '$refEsc' ORDER BY insertTime ASC, id_surcas ASC"
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

      $calcItemTotal = function ($s) {
         if ((int) ($s['member'] ?? 0) !== 0) {
            return 0;
         }
         $qty = round((float) ($s['qty'] ?? 0), 2);
         $minOrder = round((float) ($s['min_order'] ?? 0), 2);
         $qtyReal = ($qty < $minOrder) ? $minOrder : $qty;
         $total = (float) ($s['harga'] ?? 0) * $qtyReal;
         $diskonQty = (float) ($s['diskon_qty'] ?? 0);
         $diskonPartner = (float) ($s['diskon_partner'] ?? 0);
         if ($diskonQty > 0) {
            $total -= $total * ($diskonQty / 100);
         }
         if ($diskonPartner > 0) {
            $total -= $total * ($diskonPartner / 100);
         }
         return (int) round($total);
      };

      $subTotal = 0;
      $items = [];
      foreach ($sales as $s) {
         $sid = trim((string) ($s['id_penjualan'] ?? ''));
         $itemTotal = $calcItemTotal($s);
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
            if ($label === '') {
               $label = 'Layanan';
            }
            if (isset($operasiMap[$sid][$layananId])) {
               $op = $operasiMap[$sid][$layananId];
               $timeline[] = [
                  'type' => 'layanan',
                  'label' => $label,
                  'time' => $fmt($op['insertTime'] ?? ''),
                  'inferred' => false,
                  'done' => true,
               ];
            } elseif ($endDone) {
               $timeline[] = [
                  'type' => 'layanan',
                  'label' => $label,
                  'time' => $fmt($endTime),
                  'inferred' => true,
                  'done' => true,
               ];
            } else {
               $timeline[] = [
                  'type' => 'layanan',
                  'label' => $label,
                  'time' => '',
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
         $payments[] = [
            'id_kas' => (int) ($ka['id_kas'] ?? 0),
            'time' => $fmt($ka['insertTime'] ?? ''),
            'method' => $mapMetode[$metodeId] ?? '',
            'note' => trim((string) ($ka['note'] ?? '')),
            'amount' => $jumlah,
            'status' => $st,
            'status_label' => $mapStatus[$st] ?? '',
         ];
      }

      $subTotal = (int) round($subTotal);
      $sisa = max(0, $subTotal - $dibayar);
      $lunas = ($subTotal - $totalBayar) < 1;

      echo json_encode([
         'ok' => true,
         'data' => [
            'no_ref' => $ref,
            'pelanggan' => $pelangganNama,
            'created_at' => $fmt($first['insertTime'] ?? ''),
            'total' => $subTotal,
            'dibayar' => $dibayar,
            'sisa' => $sisa,
            'lunas' => $lunas,
            'payments' => $payments,
            'surcas' => $surcasOut,
            'items' => $items,
         ],
      ], JSON_UNESCAPED_UNICODE);
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
               'src' => $base . 'in_assets/icon/j-icon-192.png',
               'sizes' => '192x192',
               'type' => 'image/png',
               'purpose' => 'any',
            ],
            [
               'src' => $base . 'in_assets/icon/j-icon-512.png',
               'sizes' => '512x512',
               'type' => 'image/png',
               'purpose' => 'any maskable',
            ],
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
         'active' => ($page === 'paketDetail' || $page === 'topup')
            ? 'paket'
            : (($page === 'riwayat') ? 'tagihan' : $page),
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
      $cols = 'id_kas, id_client, jumlah, metode_mutasi, note, insertTime, jenis_mutasi, jenis_transaksi, status_mutasi, id_user, ref_finance';
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

      $pendingWhere = 'id_cabang = ' . (int) $this->id_cabang_p
         . " AND id_client = $pelanggan AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 2 ORDER BY insertTime DESC";
      $pendingRows = $this->db(0)->get_where('kas', $pendingWhere);
      if (!is_array($pendingRows) || isset($pendingRows['errno'])) $pendingRows = [];

      $finance = [];
      $pendingSum = 0;
      $selfPendingCount = 0;
      foreach ($pendingRows as $k) {
         $rf = $k['ref_finance'] ?? '';
         if ($rf === '') continue;
         $jumlah = (float) ($k['jumlah'] ?? 0);
         $pendingSum += $jumlah;
         if ((int) ($k['id_user'] ?? 0) === 0) $selfPendingCount++;
         if (!isset($finance[$rf])) {
            $finance[$rf] = [
               'ref_finance' => $rf,
               'total' => 0,
               'note' => $k['note'] ?? '',
               'insertTime' => $k['insertTime'] ?? '',
               'id_user' => (int) ($k['id_user'] ?? 0),
            ];
         }
         $finance[$rf]['total'] += $jumlah;
         if (($k['insertTime'] ?? '') > ($finance[$rf]['insertTime'] ?? '')) {
            $finance[$rf]['insertTime'] = $k['insertTime'];
            $finance[$rf]['note'] = $k['note'] ?? '';
            $finance[$rf]['id_user'] = (int) ($k['id_user'] ?? 0);
         }
      }

      $maxSaldo = 5000000;
      $maxPending = 1;
      $room = max(0, $maxSaldo - $saldo - $pendingSum);

      return [
         'saldoTunai' => $saldo,
         'history' => array_reverse($show),
         'tampil' => $tampil,
         'finance_history' => array_values($finance),
         'nonTunai' => URL::NON_TUNAI,
         'nonTunaiGuide' => URL::NON_TUNAI_GUIDE,
         'maxSaldo' => $maxSaldo,
         'maxPending' => $maxPending,
         'pendingSum' => $pendingSum,
         'selfPendingCount' => $selfPendingCount,
         'topupRoom' => $room,
         'topupBlocked' => $selfPendingCount >= $maxPending || $room < 1000,
         'customer' => [
            'id' => (int) $pelanggan,
            'nama' => $this->pelanggan_p['nama_pelanggan'] ?? '',
            'hp' => $this->pelanggan_p['nomor_pelanggan'] ?? '',
         ],
      ];
   }

   private function buildTopupCatalog($pelanggan, $idHargaFilter = 0)
   {
      $idHargaFilter = (int) $idHargaFilter;
      if ($idHargaFilter > 0) {
         $rows = $this->db(0)->get_where('harga_paket', 'id_harga = ' . $idHargaFilter);
      } else {
         $rows = $this->db(0)->get('harga_paket');
      }
      if (!is_array($rows) || isset($rows['errno'])) {
         $rows = [];
      }

      $items = [];
      foreach ($rows as $z) {
         if (!is_array($z) || empty($z['id_harga_paket'])) {
            continue;
         }
         $idHarga = (int) $z['id_harga'];
         $info = $this->resolveHargaInfo($idHarga);
         $harga = $this->resolveHargaPaketUnit($z);
         $items[] = [
            'id_harga_paket' => (int) $z['id_harga_paket'],
            'id_harga' => $idHarga,
            'label' => $info['label'],
            'qty' => $z['qty'],
            'satuan' => $info['satuan'],
            'harga' => $harga,
         ];
      }

      $maxUnpaid = 2;
      $unpaidCount = (int) ($this->db(0)->count_where(
         'member',
         'id_cabang = ' . (int) $this->id_cabang_p
            . ' AND id_pelanggan = ' . (int) $pelanggan
            . ' AND bin = 0 AND lunas = 0'
      ) ?? 0);

      return [
         'filterIdHarga' => $idHargaFilter,
         'catalog' => $items,
         'pelangganId' => (int) $pelanggan,
         'maxUnpaid' => $maxUnpaid,
         'unpaidCount' => $unpaidCount,
         'topupBlocked' => $unpaidCount >= $maxUnpaid,
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

   private function getTagihanFull($pelanggan, $bulan, $tahun, $tuntas = 0)
   {
      $tuntas = ((int) $tuntas === 1) ? 1 : 0;
      $filter = ['bulan' => $bulan, 'tahun' => $tahun, 'active' => ($bulan !== '' && $tahun !== '')];

      if ($tuntas === 1) {
         $bulan = (int) ($bulan !== '' ? $bulan : date('n'));
         $tahun = (int) ($tahun !== '' ? $tahun : date('Y'));
         if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) date('n');
         }
         if ($tahun < 2021 || $tahun > ((int) date('Y') + 1)) {
            $tahun = (int) date('Y');
         }
         $bulannya = $tahun . '-' . str_pad((string) $bulan, 2, '0', STR_PAD_LEFT);
         $bulannya = $this->db(0)->escape($bulannya);
         $where = "id_pelanggan = $pelanggan AND insertTime LIKE '$bulannya%' AND bin = 0 AND tuntas = 1 ORDER BY id_penjualan DESC";
         $filter = ['bulan' => $bulan, 'tahun' => $tahun, 'active' => true];
      } elseif ($filter['active']) {
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
         $hari = (int) ($a['hari'] ?? 0);
         $jam = (int) ($a['jam'] ?? 0);
         // Durasi layanan di bawah 2 hari → highlight merah
         $durasiUrgent = (($hari * 24) + $jam) < 48;

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
            'durasi_urgent' => $durasiUrgent,
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

      $membersOut = [];
      $kasM = [];
      $unpaid = [];
      $finance_history = [];

      if ($tuntas === 0) {
         // Unpaid member packages
         $data_member = $this->db(0)->get_where_order(
            'member',
            'id_cabang = ' . (int) $this->id_cabang_p . " AND bin = 0 AND id_pelanggan = $pelanggan AND lunas = 0",
            'id_member DESC'
         );
         if (!is_array($data_member)) $data_member = [];

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
         'saldoTunai' => $this->getSaldoTunai($pelanggan),
         'customer' => [
            'id' => (int) $pelanggan,
            'nama' => $this->pelanggan_p['nama_pelanggan'] ?? '',
            'hp' => $this->pelanggan_p['nomor_pelanggan'] ?? '',
         ],
      ];
   }

   private function buildRiwayatMonthOptions($selectedYear, $selectedMonth)
   {
      $months = [
         1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
         5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
         9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
      ];
      $out = [];
      $cursor = strtotime(date('Y-m-01'));
      for ($i = 0; $i < 24; $i++) {
         $y = (int) date('Y', $cursor);
         $m = (int) date('n', $cursor);
         $ym = date('Y-m', $cursor);
         $out[] = [
            'value' => $ym,
            'label' => ($months[$m] ?? $m) . ' ' . $y,
            'selected' => ($y === (int) $selectedYear && $m === (int) $selectedMonth),
         ];
         $cursor = strtotime('-1 month', $cursor);
      }
      return $out;
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

   /** GET JSON: item sale eligible untuk Antar (Sameday / Instant) */
   public function kurirSalesOptions($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);
      $this->ensureKurirLookups();
      $layanan = strtolower(trim((string) ($_GET['layanan'] ?? $_POST['layanan'] ?? 'sameday')));
      if ($layanan !== 'instant') {
         $layanan = 'sameday';
      }
      // Instant Antar: hanya item yang sudah selesai (ada notif tipe=2)
      $requireSelesai = ($layanan === 'instant');
      $orders = $this->buildKurirEligibleOrders($pelanggan, 'antar', $requireSelesai);
      echo json_encode([
         'ok' => true,
         'orders' => $orders,
         'layanan' => $layanan,
         'require_selesai' => $requireSelesai,
      ], JSON_UNESCAPED_UNICODE);
   }

   /** GET JSON: daftar lokasi pelanggan + default map (kota cabang) */
   public function kurirLokasiList($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);
      echo json_encode([
         'ok' => true,
         'lokasi' => $this->listPelangganLokasi($pelanggan),
         'default_map' => $this->getDefaultMapCoords(),
      ], JSON_UNESCAPED_UNICODE);
   }

   /** POST: tambah lokasi pelanggan */
   public function kurirLokasiAdd($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $this->helper('PelangganLokasiApi');
      $res = PelangganLokasiApi::add([
         'id_pelanggan' => (int) $pelanggan,
         'nama' => trim((string) ($_POST['nama'] ?? '')),
         'detail' => trim((string) ($_POST['detail'] ?? '')),
         'latt' => (float) ($_POST['latt'] ?? 0),
         'longt' => (float) ($_POST['longt'] ?? ($_POST['long'] ?? 0)),
      ]);
      if (empty($res['ok'])) {
         echo json_encode(['ok' => false, 'message' => $res['message'] ?? 'Gagal menyimpan lokasi']);
         return;
      }

      echo json_encode([
         'ok' => true,
         'message' => 'Lokasi ditambahkan',
         'lokasi' => $res['lokasi'] ?? [
            'id_lokasi' => (int) ($res['id_lokasi'] ?? 0),
            'nama' => trim((string) ($_POST['nama'] ?? '')),
            'detail' => trim((string) ($_POST['detail'] ?? '')),
            'latt' => (float) ($res['latt'] ?? 0),
            'longt' => (float) ($res['longt'] ?? 0),
         ],
         'list' => $this->listPelangganLokasi($pelanggan),
      ], JSON_UNESCAPED_UNICODE);
   }

   /** POST: ubah lokasi pelanggan */
   public function kurirLokasiUpdate($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);
      $nama = trim((string) ($_POST['nama'] ?? ''));
      $detail = trim((string) ($_POST['detail'] ?? ''));
      $latt = (float) ($_POST['latt'] ?? 0);
      $longt = (float) ($_POST['longt'] ?? ($_POST['long'] ?? 0));

      $this->helper('PelangganLokasiApi');
      $res = PelangganLokasiApi::update([
         'id_pelanggan' => (int) $pelanggan,
         'id_lokasi' => $idLokasi,
         'nama' => $nama,
         'detail' => $detail,
         'latt' => $latt,
         'longt' => $longt,
      ]);
      if (empty($res['ok'])) {
         echo json_encode(['ok' => false, 'message' => $res['message'] ?? 'Gagal mengubah lokasi']);
         return;
      }

      echo json_encode([
         'ok' => true,
         'message' => 'Lokasi diperbarui',
         'lokasi' => $res['lokasi'] ?? [
            'id_lokasi' => $idLokasi,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => (float) ($res['latt'] ?? $latt),
            'longt' => (float) ($res['longt'] ?? $longt),
         ],
         'list' => $this->listPelangganLokasi($pelanggan),
      ], JSON_UNESCAPED_UNICODE);
   }

   /** POST: hapus lokasi pelanggan */
   public function kurirLokasiDelete($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);
      $this->helper('PelangganLokasiApi');
      $res = PelangganLokasiApi::delete((int) $pelanggan, $idLokasi);
      if (empty($res['ok'])) {
         echo json_encode(['ok' => false, 'message' => $res['message'] ?? 'Gagal menghapus lokasi']);
         return;
      }

      echo json_encode([
         'ok' => true,
         'message' => 'Lokasi dihapus',
         'list' => $this->listPelangganLokasi($pelanggan),
      ], JSON_UNESCAPED_UNICODE);
   }

   /** POST: buat request Sameday (antar|jemput) */
   public function kurirSamedaySubmit($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $jenis = strtolower(trim((string) ($_POST['jenis'] ?? '')));
      if (!in_array($jenis, ['antar', 'jemput'], true)) {
         echo json_encode(['ok' => false, 'message' => 'Jenis tidak valid']);
         return;
      }

      $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);
      if ($idLokasi <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Pilih lokasi dulu']);
         return;
      }
      $catatanKurir = $this->normalizeCatatanKurir($_POST['catatan'] ?? '');
      if ($catatanKurir['ok'] === false) {
         echo json_encode(['ok' => false, 'message' => $catatanKurir['message']]);
         return;
      }
      $lokasi = $this->db(0)->get_where_row(
         'pelanggan_lokasi',
         'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . (int) $pelanggan
      );
      if (!is_array($lokasi) || empty($lokasi['id_lokasi'])) {
         echo json_encode(['ok' => false, 'message' => 'Lokasi tidak ditemukan']);
         return;
      }

      $idsRaw = $_POST['ids'] ?? [];
      if (!is_array($idsRaw)) {
         $idsRaw = [$idsRaw];
      }
      $ids = [];
      foreach ($idsRaw as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $ids[$id] = $id;
         }
      }
      $ids = array_values($ids);

      if ($jenis === 'antar' && empty($ids)) {
         echo json_encode(['ok' => false, 'message' => 'Pilih minimal satu item untuk diantar']);
         return;
      }
      if ($jenis === 'jemput' && !empty($ids)) {
         // Jemput tidak butuh item di awal — abaikan ids jika ada
         $ids = [];
      }

      if ($jenis === 'jemput') {
         $pendingLokasi = (int) ($this->db(0)->count_where(
            'delivery_request',
            'id_pelanggan = ' . (int) $pelanggan
               . " AND jenis = 'jemput'"
               . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
               . ' AND id_lokasi = ' . $idLokasi
         ) ?? 0);
         if ($pendingLokasi > 0) {
            echo json_encode([
               'ok' => false,
               'message' => 'Sudah ada jemput berjalan di lokasi ini. Tunggu selesai dulu.',
            ]);
            return;
         }
      }

      $eligibleMap = [];
      if ($jenis === 'antar') {
         foreach ($this->fetchKurirEligibleSaleRows($pelanggan, 'antar') as $row) {
            $eligibleMap[(int) $row['id_penjualan']] = $row;
         }
         foreach ($ids as $idSale) {
            if (!isset($eligibleMap[$idSale])) {
               $reason = $this->antarItemBlockReason($pelanggan, $idSale);
               echo json_encode([
                  'ok' => false,
                  'message' => $reason !== ''
                     ? $reason
                     : "Item #$idSale tidak bisa diantar",
               ]);
               return;
            }
         }
      }

      $phoneTail = $this->phoneTailFromPelanggan($this->pelanggan_p);
      if (strlen($phoneTail) < 8) {
         echo json_encode(['ok' => false, 'message' => 'Nomor pelanggan belum lengkap']);
         return;
      }

      $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');

      $tarifSurcas = null;
      $cabLat = (float) ($this->dCabangPublic['latt'] ?? 0);
      $cabLon = (float) ($this->dCabangPublic['long'] ?? 0);
      $locLat = (float) ($lokasi['latt'] ?? 0);
      $locLon = (float) ($lokasi['longt'] ?? 0);
      if ($jenis === 'jemput' || $jenis === 'antar') {
         if ($cabLat == 0.0 && $cabLon == 0.0) {
            echo json_encode(['ok' => false, 'message' => 'Lokasi cabang belum diatur']);
            return;
         }
         if ($locLat == 0.0 && $locLon == 0.0) {
            echo json_encode(['ok' => false, 'message' => 'Koordinat lokasi pelanggan belum lengkap']);
            return;
         }
      }
      if ($jenis === 'jemput') {
         $calcJemput = $this->helper('AntarTarif')->tarifFromCoordsForPelanggan(
            $cabLat,
            $cabLon,
            $locLat,
            $locLon,
            (int) $pelanggan
         );
         $tarifSurcas = (int) $calcJemput['tarif'];
      }

      $insData = [
         'sumber' => 'customer',
         'jenis' => $jenis,
         'layanan' => 'sameday',
         'delivery_status' => 'berjalan',
         'id_pelanggan' => (int) $pelanggan,
         'phone_tail' => $phoneTail,
         'id_cabang' => (int) $this->id_cabang_p,
         'id_lokasi' => $idLokasi,
         'lokasi_nama' => (string) ($lokasi['nama'] ?? ''),
         'lokasi_detail' => (string) ($lokasi['detail'] ?? ''),
         'lokasi_latt' => $locLat,
         'lokasi_longt' => $locLon,
         'insertTime' => $now,
      ];
      if ($catatanKurir['value'] !== '') {
         $insData['catatan_kurir'] = $catatanKurir['value'];
      }
      if ($tarifSurcas !== null) {
         $insData['tarif_surcas'] = $tarifSurcas;
      }
      $ins = $this->db(0)->insert('delivery_request', $insData);
      if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
         echo json_encode(['ok' => false, 'message' => $ins['error'] ?? 'Gagal membuat permintaan']);
         return;
      }
      $idRequest = (int) ($ins['insert_id'] ?? 0);
      if ($idRequest <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Gagal membuat permintaan']);
         return;
      }

      $surcasInfo = null;
      if ($jenis === 'antar') {
         foreach ($ids as $idSale) {
            $sale = $eligibleMap[$idSale];
            $itemIns = $this->db(0)->insert('delivery_request_item', [
               'id_request' => $idRequest,
               'id_penjualan' => $idSale,
               'no_ref' => (string) ($sale['no_ref'] ?? ''),
            ]);
            if (is_array($itemIns) && isset($itemIns['errno']) && (int) $itemIns['errno'] !== 0) {
               echo json_encode(['ok' => false, 'message' => $itemIns['error'] ?? 'Gagal menyimpan item']);
               return;
            }
         }

         // Surcas Pengantaran (jenis 2) ke satu ref belum tuntas; jumlah = tarif jarak
         $tarifHelper = $this->helper('AntarTarif');
         $calc = $tarifHelper->tarifFromCoordsForPelanggan($cabLat, $cabLon, $locLat, $locLon, (int) $pelanggan);
         $jumlahSurcas = (int) $calc['tarif'];
         $noRefSurcas = $this->pickBelumTuntasRef($pelanggan, $ids);
         if ($noRefSurcas !== null && $noRefSurcas !== '') {
            $insertedSurcas = $this->insertSurcasPengantaran($noRefSurcas, $jumlahSurcas, $idRequest, $ids);
            if ($insertedSurcas !== false) {
               $surcasInfo = [
                  'no_ref' => $noRefSurcas,
                  'jumlah' => $jumlahSurcas,
                  'km' => $calc['km'],
                  'already_exists' => $insertedSurcas === 'exists',
               ];
            }
         }
      }

      $label = $jenis === 'antar' ? 'Antar' : 'Jemput';
      echo json_encode([
         'ok' => true,
         'message' => "Permintaan $label Sameday dikirim. Driver akan memproses.",
         'id_request' => $idRequest,
         'surcas' => $surcasInfo,
      ], JSON_UNESCAPED_UNICODE);
   }

   /**
    * GET rates Instant dari Biteship via API proxy.
    */
   public function kurirInstantRates($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $window = $this->helper('OperatingHours')->instantOrderStatus();
      if (empty($window['ok'])) {
         echo json_encode([
            'ok' => false,
            'message' => $window['message'] ?? 'Kurir Instant di luar jam operasional',
            'instantWindow' => $window,
         ], JSON_UNESCAPED_UNICODE);
         return;
      }

      $idLokasi = (int) ($_GET['id_lokasi'] ?? $_POST['id_lokasi'] ?? 0);
      $jenis = strtolower(trim((string) ($_GET['jenis'] ?? $_POST['jenis'] ?? 'antar')));
      if (!in_array($jenis, ['antar', 'jemput'], true)) {
         echo json_encode(['ok' => false, 'message' => 'Jenis tidak valid']);
         return;
      }
      if ($idLokasi <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Lokasi wajib dipilih']);
         return;
      }

      $lokasi = $this->db(0)->get_where_row(
         'pelanggan_lokasi',
         'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . (int) $pelanggan
      );
      if (!is_array($lokasi) || empty($lokasi['id_lokasi'])) {
         echo json_encode(['ok' => false, 'message' => 'Lokasi tidak ditemukan']);
         return;
      }

      $cabLat = (float) ($this->dCabangPublic['latt'] ?? 0);
      $cabLon = (float) ($this->dCabangPublic['long'] ?? 0);
      $locLat = (float) ($lokasi['latt'] ?? 0);
      $locLon = (float) ($lokasi['longt'] ?? 0);
      if ($cabLat == 0.0 && $cabLon == 0.0) {
         echo json_encode(['ok' => false, 'message' => 'Lokasi cabang belum diatur']);
         return;
      }
      if ($locLat == 0.0 && $locLon == 0.0) {
         echo json_encode(['ok' => false, 'message' => 'Koordinat lokasi pelanggan belum lengkap']);
         return;
      }

      // Rates: always laundry ↔ customer (same distance either direction)
      $payload = [
         'origin_latitude' => $cabLat,
         'origin_longitude' => $cabLon,
         'destination_latitude' => $locLat,
         'destination_longitude' => $locLon,
      ];
      $api = $this->helper('BiteshipApi');
      $res = $api->rates($payload);
      if (empty($res['ok'])) {
         $msg = (string) ($res['error'] ?? $res['message'] ?? 'Gagal mengambil tarif Instant');
         if (stripos($msg, 'biteship') !== false || stripos($msg, 'api_key') !== false) {
            $msg = 'Layanan Instant sementara tidak tersedia. Coba lagi nanti.';
         }
         echo json_encode([
            'ok' => false,
            'message' => $msg,
         ], JSON_UNESCAPED_UNICODE);
         return;
      }
      if (empty($res['rates']) || !is_array($res['rates'])) {
         echo json_encode([
            'ok' => false,
            'message' => 'Tidak ada kurir Instant untuk rute ini saat ini. Coba lagi nanti.',
         ], JSON_UNESCAPED_UNICODE);
         return;
      }
      echo json_encode([
         'ok' => true,
         'rates' => $res['rates'] ?? [],
         'jenis' => $jenis,
         'id_lokasi' => $idLokasi,
         'saldoTunai' => (int) round($this->getSaldoTunai($pelanggan)),
      ], JSON_UNESCAPED_UNICODE);
   }

   /**
    * Submit Kurir Instant → menunggu_pembayaran + kas jt=10 + QRIS.
    * Tanpa surcas.
    */
   public function kurirInstantSubmit($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);

      $window = $this->helper('OperatingHours')->instantOrderStatus();
      if (empty($window['ok'])) {
         echo json_encode([
            'ok' => false,
            'message' => $window['message'] ?? 'Kurir Instant di luar jam operasional',
            'instantWindow' => $window,
         ], JSON_UNESCAPED_UNICODE);
         return;
      }

      $jenis = strtolower(trim((string) ($_POST['jenis'] ?? '')));
      $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);
      $courierCompany = trim((string) ($_POST['courier_company'] ?? ''));
      $courierType = trim((string) ($_POST['courier_type'] ?? ''));
      $courierName = trim((string) ($_POST['courier_name'] ?? ''));
      $ongkir = (int) ($_POST['ongkir'] ?? 0);
      $metodeBayar = strtoupper(trim((string) ($_POST['metode'] ?? 'QRIS')));
      if ($metodeBayar !== 'SALDO') {
         $metodeBayar = 'QRIS';
      }
      $catatanKurir = $this->normalizeCatatanKurir($_POST['catatan'] ?? '');
      if ($catatanKurir['ok'] === false) {
         echo json_encode(['ok' => false, 'message' => $catatanKurir['message']]);
         return;
      }
      $idsRaw = $_POST['ids'] ?? [];
      if (!is_array($idsRaw)) {
         $idsRaw = [$idsRaw];
      }
      $ids = [];
      foreach ($idsRaw as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $ids[$id] = $id;
         }
      }
      $ids = array_values($ids);

      if (!in_array($jenis, ['antar', 'jemput'], true)) {
         echo json_encode(['ok' => false, 'message' => 'Jenis tidak valid']);
         return;
      }
      if ($idLokasi <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Pilih lokasi']);
         return;
      }
      if ($courierCompany === '' || $courierType === '') {
         echo json_encode(['ok' => false, 'message' => 'Pilih kurir Instant']);
         return;
      }
      if ($ongkir < 1000) {
         echo json_encode(['ok' => false, 'message' => 'Ongkir Instant tidak valid']);
         return;
      }
      if ($jenis === 'antar' && empty($ids)) {
         echo json_encode(['ok' => false, 'message' => 'Pilih minimal satu item untuk diantar']);
         return;
      }
      if ($jenis === 'jemput' && !empty($ids)) {
         echo json_encode(['ok' => false, 'message' => 'Jemput tidak memerlukan item']);
         return;
      }

      if ($jenis === 'jemput') {
         $pendingLokasi = (int) ($this->db(0)->count_where(
            'delivery_request',
            'id_pelanggan = ' . (int) $pelanggan
               . " AND jenis = 'jemput'"
               . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
               . ' AND id_lokasi = ' . $idLokasi
         ) ?? 0);
         if ($pendingLokasi > 0) {
            echo json_encode([
               'ok' => false,
               'message' => 'Sudah ada jemput berjalan di lokasi ini. Tunggu selesai dulu.',
            ]);
            return;
         }
      }

      $lokasi = $this->db(0)->get_where_row(
         'pelanggan_lokasi',
         'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . (int) $pelanggan
      );
      if (!is_array($lokasi) || empty($lokasi['id_lokasi'])) {
         echo json_encode(['ok' => false, 'message' => 'Lokasi tidak ditemukan']);
         return;
      }

      $cabLat = (float) ($this->dCabangPublic['latt'] ?? 0);
      $cabLon = (float) ($this->dCabangPublic['long'] ?? 0);
      $locLat = (float) ($lokasi['latt'] ?? 0);
      $locLon = (float) ($lokasi['longt'] ?? 0);
      if ($cabLat == 0.0 && $cabLon == 0.0) {
         echo json_encode(['ok' => false, 'message' => 'Lokasi cabang belum diatur']);
         return;
      }
      if ($locLat == 0.0 && $locLon == 0.0) {
         echo json_encode(['ok' => false, 'message' => 'Koordinat lokasi pelanggan belum lengkap']);
         return;
      }

      // Wajib cocokkan ongkir ke rate Biteship (bukan tarif Sameday/AntarTarif)
      $ratesRes = $this->helper('BiteshipApi')->rates([
         'origin_latitude' => $cabLat,
         'origin_longitude' => $cabLon,
         'destination_latitude' => $locLat,
         'destination_longitude' => $locLon,
      ]);
      if (empty($ratesRes['ok']) || empty($ratesRes['rates']) || !is_array($ratesRes['rates'])) {
         echo json_encode([
            'ok' => false,
            'message' => $ratesRes['message'] ?? 'Gagal memuat tarif Instant. Coba lagi.',
         ], JSON_UNESCAPED_UNICODE);
         return;
      }
      $matched = null;
      foreach ($ratesRes['rates'] as $rate) {
         if (!is_array($rate)) {
            continue;
         }
         $rc = strtolower(trim((string) ($rate['courier_company'] ?? '')));
         $rt = strtolower(trim((string) ($rate['courier_type'] ?? '')));
         if ($rc === strtolower($courierCompany) && $rt === strtolower($courierType)) {
            $matched = $rate;
            break;
         }
      }
      if (!$matched) {
         echo json_encode([
            'ok' => false,
            'message' => 'Kurir Instant tidak tersedia / tarif berubah. Silakan pilih ulang kurir.',
         ]);
         return;
      }
      $ongkirBiteship = (int) ($matched['price'] ?? 0);
      if ($ongkirBiteship < 1000) {
         echo json_encode(['ok' => false, 'message' => 'Tarif Instant tidak valid']);
         return;
      }
      // Kunci ke harga Biteship; abaikan ongkir client jika beda
      $ongkir = $ongkirBiteship;
      if ($courierName === '') {
         $courierName = (string) ($matched['courier_name'] ?? ($courierCompany . ' ' . $courierType));
      }

      $eligibleMap = [];
      if ($jenis === 'antar') {
         $eligibleRows = $this->fetchKurirEligibleSaleRows((int) $pelanggan, 'antar', true);
         foreach ($eligibleRows as $row) {
            $eligibleMap[(int) $row['id_penjualan']] = $row;
         }
         foreach ($ids as $idSale) {
            if (!isset($eligibleMap[$idSale])) {
               $reason = $this->antarItemBlockReason($pelanggan, $idSale, true);
               echo json_encode([
                  'ok' => false,
                  'message' => $reason !== '' ? $reason : "Item #$idSale tidak bisa diantar",
               ]);
               return;
            }
         }
      }

      $phoneTail = $this->phoneTailFromPelanggan($this->pelanggan_p);
      if (strlen($phoneTail) < 8) {
         echo json_encode(['ok' => false, 'message' => 'Nomor pelanggan belum lengkap']);
         return;
      }

      // Cegah pending Instant ganda
      $pendingInstant = (int) ($this->db(0)->count_where(
         'delivery_request',
         'id_pelanggan = ' . (int) $pelanggan
            . " AND layanan = 'instant' AND delivery_status = 'menunggu_pembayaran'"
      ) ?? 0);
      if ($pendingInstant > 0) {
         echo json_encode([
            'ok' => false,
            'message' => 'Masih ada Instant menunggu pembayaran. Bayar atau batalkan dulu.',
         ]);
         return;
      }

      $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
      $insData = [
         'sumber' => 'customer',
         'jenis' => $jenis,
         'layanan' => 'instant',
         'delivery_status' => 'menunggu_pembayaran',
         'id_pelanggan' => (int) $pelanggan,
         'phone_tail' => $phoneTail,
         'id_cabang' => (int) $this->id_cabang_p,
         'id_lokasi' => $idLokasi,
         'lokasi_nama' => (string) ($lokasi['nama'] ?? ''),
         'lokasi_detail' => (string) ($lokasi['detail'] ?? ''),
         'lokasi_latt' => $locLat,
         'lokasi_longt' => $locLon,
         'courier_company' => $courierCompany,
         'courier_type' => $courierType,
         'courier_name' => $courierName !== '' ? $courierName : ($courierCompany . ' ' . $courierType),
         'ongkir' => $ongkir,
         'insertTime' => $now,
      ];
      if ($catatanKurir['value'] !== '') {
         $insData['catatan_kurir'] = $catatanKurir['value'];
      }
      $ins = $this->db(0)->insert('delivery_request', $insData);
      if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
         echo json_encode(['ok' => false, 'message' => $ins['error'] ?? 'Gagal membuat permintaan']);
         return;
      }
      $idRequest = (int) ($ins['insert_id'] ?? 0);
      if ($idRequest <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Gagal membuat permintaan']);
         return;
      }

      if ($jenis === 'antar') {
         foreach ($ids as $idSale) {
            $sale = $eligibleMap[$idSale];
            $itemIns = $this->db(0)->insert('delivery_request_item', [
               'id_request' => $idRequest,
               'id_penjualan' => $idSale,
               'no_ref' => (string) ($sale['no_ref'] ?? ''),
            ]);
            if (is_array($itemIns) && isset($itemIns['errno']) && (int) $itemIns['errno'] !== 0) {
               echo json_encode(['ok' => false, 'message' => $itemIns['error'] ?? 'Gagal menyimpan item']);
               return;
            }
         }
      }

      $refFinance = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      $idKas = (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
      $label = $jenis === 'antar' ? 'Antar' : 'Jemput';

      if ($metodeBayar === 'SALDO') {
         $saldoNow = (int) round($this->getSaldoTunai($pelanggan));
         if ($saldoNow < $ongkir) {
            $this->db(0)->update(
               'delivery_request',
               [
                  'delivery_status' => 'batal',
                  'catatan_batal' => 'Saldo tidak cukup',
                  'selesaiTime' => $now,
               ],
               'id_request = ' . $idRequest
            );
            echo json_encode([
               'ok' => false,
               'message' => 'Saldo Deposit tidak cukup. Saldo Rp' . number_format($saldoNow, 0, ',', '.')
                  . ', ongkir Rp' . number_format($ongkir, 0, ',', '.') . '.',
            ]);
            return;
         }

         // Potong saldo: metode_mutasi=3, jenis_mutasi=2, status lunas (seperti Tagihan)
         $kasIns = $this->db(0)->insert('kas', [
            'id_kas' => $idKas,
            'id_cabang' => (int) $this->id_cabang_p,
            'jenis_mutasi' => 2,
            'jenis_transaksi' => 10,
            'metode_mutasi' => 3,
            'note' => 'SALDO',
            'status_mutasi' => 3,
            'jumlah' => $ongkir,
            'id_user' => 0,
            'id_client' => (int) $pelanggan,
            'ref_transaksi' => (string) $idRequest,
            'ref_finance' => $refFinance,
         ]);
         if (is_array($kasIns) && isset($kasIns['errno']) && (int) $kasIns['errno'] !== 0) {
            $this->db(0)->update(
               'delivery_request',
               ['delivery_status' => 'batal', 'catatan_batal' => 'Gagal potong saldo', 'selesaiTime' => $now],
               'id_request = ' . $idRequest
            );
            echo json_encode(['ok' => false, 'message' => $kasIns['error'] ?? 'Gagal memotong Saldo Deposit']);
            return;
         }

         $this->db(0)->update(
            'delivery_request',
            ['payment_ref_finance' => $refFinance],
            'id_request = ' . $idRequest
         );

         $activate = $this->helper('BiteshipApi')->activate(['ref_finance' => $refFinance]);
         $okAct = !empty($activate['ok']);
         echo json_encode([
            'ok' => true,
            'message' => $okAct
               ? "Pembayaran Saldo berhasil. Permintaan $label Instant diproses."
               : "Pembayaran Saldo berhasil. Order Instant sedang diproses.",
            'id_request' => $idRequest,
            'ref_finance' => $refFinance,
            'ongkir' => $ongkir,
            'note' => 'SALDO',
            'paid' => true,
            'pay' => false,
            'activate' => $activate,
         ], JSON_UNESCAPED_UNICODE);
         return;
      }

      $kasIns = $this->db(0)->insert('kas', [
         'id_kas' => $idKas,
         'id_cabang' => (int) $this->id_cabang_p,
         'jenis_mutasi' => 1,
         'jenis_transaksi' => 10,
         'metode_mutasi' => 2,
         'note' => 'QRIS',
         'status_mutasi' => 2,
         'jumlah' => $ongkir,
         'id_user' => 0,
         'id_client' => (int) $pelanggan,
         'ref_transaksi' => (string) $idRequest,
         'ref_finance' => $refFinance,
      ]);
      if (is_array($kasIns) && isset($kasIns['errno']) && (int) $kasIns['errno'] !== 0) {
         $this->db(0)->update(
            'delivery_request',
            ['delivery_status' => 'batal', 'catatan_batal' => 'Gagal buat kas', 'selesaiTime' => $now],
            'id_request = ' . $idRequest
         );
         echo json_encode(['ok' => false, 'message' => $kasIns['error'] ?? 'Gagal membuat pembayaran']);
         return;
      }

      $this->db(0)->update(
         'delivery_request',
         ['payment_ref_finance' => $refFinance],
         'id_request = ' . $idRequest
      );

      echo json_encode([
         'ok' => true,
         'message' => "Permintaan $label Instant dibuat. Lanjutkan pembayaran ongkir.",
         'id_request' => $idRequest,
         'ref_finance' => $refFinance,
         'ongkir' => $ongkir,
         'note' => 'QRIS',
         'pay' => true,
         'paid' => false,
      ], JSON_UNESCAPED_UNICODE);
   }

   /**
    * Bayar Instant menunggu_pembayaran pakai Saldo Deposit.
    */
   public function kurirInstantPaySaldo($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);
      $idRequest = (int) ($_POST['id_request'] ?? 0);
      if ($idRequest <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Request tidak valid']);
         return;
      }

      $req = $this->db(0)->get_where_row(
         'delivery_request',
         'id_request = ' . $idRequest
            . ' AND id_pelanggan = ' . (int) $pelanggan
            . " AND layanan = 'instant' AND delivery_status = 'menunggu_pembayaran'"
      );
      if (!is_array($req) || empty($req['id_request'])) {
         echo json_encode(['ok' => false, 'message' => 'Request tidak ditemukan atau sudah dibayar']);
         return;
      }

      $ongkir = (int) ($req['ongkir'] ?? 0);
      if ($ongkir < 1000) {
         echo json_encode(['ok' => false, 'message' => 'Ongkir tidak valid']);
         return;
      }

      $saldoNow = (int) round($this->getSaldoTunai($pelanggan));
      if ($saldoNow < $ongkir) {
         echo json_encode([
            'ok' => false,
            'message' => 'Saldo Deposit tidak cukup. Saldo Rp' . number_format($saldoNow, 0, ',', '.')
               . ', ongkir Rp' . number_format($ongkir, 0, ',', '.') . '.',
         ]);
         return;
      }

      $refOld = trim((string) ($req['payment_ref_finance'] ?? ''));
      if ($refOld !== '') {
         $delOld = $this->deleteKasSafe(
            "ref_finance = '" . $this->db(0)->escape($refOld)
               . "' AND jenis_transaksi = 10 AND status_mutasi = 2",
            true
         );
         if (!empty($delOld['kept_paid'])) {
            echo json_encode([
               'ok' => true,
               'paid' => true,
               'message' => $delOld['msg'] ?: 'Pembayaran QRIS sudah berhasil. Order Instant diproses.',
            ], JSON_UNESCAPED_UNICODE);
            return;
         }
         if (!empty($delOld['kept_pending']) || !empty($delOld['kept_unknown'])) {
            echo json_encode([
               'ok' => false,
               'message' => $delOld['msg'] ?: 'QRIS masih aktif. Tidak bisa ganti ke Saldo.',
            ], JSON_UNESCAPED_UNICODE);
            return;
         }
         if (!$delOld['ok'] && !empty($delOld['error'])) {
            echo json_encode(['ok' => false, 'message' => $delOld['error']], JSON_UNESCAPED_UNICODE);
            return;
         }
      }

      $refFinance = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      $idKas = (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
      $kasIns = $this->db(0)->insert('kas', [
         'id_kas' => $idKas,
         'id_cabang' => (int) $this->id_cabang_p,
         'jenis_mutasi' => 2,
         'jenis_transaksi' => 10,
         'metode_mutasi' => 3,
         'note' => 'SALDO',
         'status_mutasi' => 3,
         'jumlah' => $ongkir,
         'id_user' => 0,
         'id_client' => (int) $pelanggan,
         'ref_transaksi' => (string) $idRequest,
         'ref_finance' => $refFinance,
      ]);
      if (is_array($kasIns) && isset($kasIns['errno']) && (int) $kasIns['errno'] !== 0) {
         echo json_encode(['ok' => false, 'message' => $kasIns['error'] ?? 'Gagal memotong Saldo Deposit']);
         return;
      }

      $this->db(0)->update(
         'delivery_request',
         ['payment_ref_finance' => $refFinance],
         'id_request = ' . $idRequest
      );

      $activate = $this->helper('BiteshipApi')->activate(['ref_finance' => $refFinance]);
      echo json_encode([
         'ok' => true,
         'message' => !empty($activate['ok'])
            ? 'Pembayaran Saldo berhasil. Order Instant diproses.'
            : 'Pembayaran Saldo berhasil. Order Instant sedang diproses.',
         'paid' => true,
         'activate' => $activate,
      ], JSON_UNESCAPED_UNICODE);
   }

   /**
    * Batalkan Instant hanya saat menunggu_pembayaran.
    */
   public function kurirInstantBatal($pelanggan)
   {
      header('Content-Type: application/json; charset=utf-8');
      $pelanggan = $this->bootCustomer($pelanggan);
      $idRequest = (int) ($_POST['id_request'] ?? 0);
      if ($idRequest <= 0) {
         echo json_encode(['ok' => false, 'message' => 'Request tidak valid']);
         return;
      }

      $req = $this->db(0)->get_where_row(
         'delivery_request',
         'id_request = ' . $idRequest
            . ' AND id_pelanggan = ' . (int) $pelanggan
            . " AND layanan = 'instant' AND delivery_status = 'menunggu_pembayaran'"
      );
      if (!is_array($req) || empty($req['id_request'])) {
         echo json_encode(['ok' => false, 'message' => 'Request tidak ditemukan atau sudah dibayar']);
         return;
      }

      $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
      $refFinance = trim((string) ($req['payment_ref_finance'] ?? ''));
      if ($refFinance !== '') {
         $delKas = $this->deleteKasSafe(
            "ref_finance = '" . $this->db(0)->escape($refFinance)
               . "' AND jenis_transaksi = 10 AND status_mutasi = 2",
            true
         );
      } else {
         $delKas = $this->deleteKasSafe(
            "ref_transaksi = '" . $this->db(0)->escape((string) $idRequest)
               . "' AND jenis_transaksi = 10 AND status_mutasi = 2",
            true
         );
      }
      if (!empty($delKas['kept_paid'])) {
         echo json_encode([
            'ok' => true,
            'paid' => true,
            'message' => $delKas['msg'] ?: 'Pembayaran QRIS sudah berhasil, tidak dibatalkan.',
         ], JSON_UNESCAPED_UNICODE);
         return;
      }
      if (!empty($delKas['kept_pending']) || !empty($delKas['kept_unknown'])) {
         echo json_encode([
            'ok' => false,
            'message' => $delKas['msg'] ?: 'QRIS masih aktif, tidak dapat dibatalkan.',
         ], JSON_UNESCAPED_UNICODE);
         return;
      }
      if (!$delKas['ok'] && !empty($delKas['error'])) {
         echo json_encode(['ok' => false, 'message' => $delKas['error']], JSON_UNESCAPED_UNICODE);
         return;
      }

      $upd = $this->db(0)->update(
         'delivery_request',
         [
            'delivery_status' => 'batal',
            'catatan_batal' => 'Dibatalkan pelanggan sebelum bayar',
            'selesaiTime' => $now,
         ],
         'id_request = ' . $idRequest . " AND delivery_status = 'menunggu_pembayaran'"
      );
      if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
         echo json_encode(['ok' => false, 'message' => $upd['error'] ?? 'Gagal membatalkan']);
         return;
      }

      echo json_encode(['ok' => true, 'message' => 'Permintaan Instant dibatalkan']);
   }

   private function listPelangganLokasi(int $pelanggan): array
   {
      $this->helper('PelangganLokasiApi');
      $api = PelangganLokasiApi::list($pelanggan);
      $rows = [];
      if (!empty($api['ok']) && is_array($api['items'] ?? null)) {
         $rows = $api['items'];
      } else {
         $local = $this->db(0)->get_where(
            'pelanggan_lokasi',
            'id_pelanggan = ' . (int) $pelanggan . ' ORDER BY insertTime DESC, id_lokasi DESC'
         );
         $rows = is_array($local) ? $local : [];
      }

      $jemputBerjalan = [];
      $pendingJemput = $this->db(0)->get_where(
         'delivery_request',
         'id_pelanggan = ' . (int) $pelanggan
            . " AND jenis = 'jemput' AND delivery_status IN ('berjalan','menunggu_pembayaran')"
      );
      if (is_array($pendingJemput)) {
         foreach ($pendingJemput as $pj) {
            $lid = (int) ($pj['id_lokasi'] ?? 0);
            if ($lid > 0) {
               $jemputBerjalan[$lid] = true;
            }
         }
      }

      $cabLat = (float) ($this->dCabangPublic['latt'] ?? 0);
      $cabLon = (float) ($this->dCabangPublic['long'] ?? 0);
      $tarifHelper = $this->helper('AntarTarif');
      $canTarif = !($cabLat == 0.0 && $cabLon == 0.0);

      $out = [];
      foreach ($rows as $r) {
         $idLokasi = (int) ($r['id_lokasi'] ?? 0);
         $locLat = (float) ($r['latt'] ?? 0);
         $locLon = (float) ($r['longt'] ?? 0);
         $km = null;
         $tarif = null;
         $grantApplied = false;
         if ($canTarif) {
            $calc = $tarifHelper->tarifFromCoordsForPelanggan($cabLat, $cabLon, $locLat, $locLon, (int) $pelanggan);
            $km = $calc['km'];
            $tarif = $calc['tarif'];
            $grantApplied = !empty($calc['grant_applied']);
         }
         $out[] = [
            'id_lokasi' => $idLokasi,
            'nama' => (string) ($r['nama'] ?? ''),
            'detail' => (string) ($r['detail'] ?? ''),
            'latt' => $locLat,
            'longt' => $locLon,
            'km' => $km,
            'tarif' => $tarif,
            'grant_applied' => $grantApplied,
            'jemput_berjalan' => !empty($jemputBerjalan[$idLokasi]),
         ];
      }
      return $out;
   }

   /** Alasan item tidak bisa antar (untuk pesan error jelas) */
   private function antarItemBlockReason(int $pelanggan, int $idPenjualan, bool $requireNotifSelesai = false): string
   {
      $pid = (int) $pelanggan;
      $sid = (int) $idPenjualan;
      if ($requireNotifSelesai) {
         $selesai = (int) ($this->db(0)->count_where(
            'notif',
            "tipe = 2 AND no_ref = '" . $this->db(0)->escape((string) $sid) . "'"
         ) ?? 0);
         if ($selesai < 1) {
            return "Item #$sid belum selesai dicuci — tunggu notifikasi selesai dulu";
         }
      }
      $inRiwayat = (int) ($this->db(0)->count_where(
         'delivery_riwayat',
         "id_penjualan = $sid AND jenis = 'antar'"
      ) ?? 0);
      if ($inRiwayat > 0) {
         return "Item #$sid sudah pernah diantar — tidak bisa request ulang";
      }
      $busy = $this->db(0)->query_array(
         "SELECT dri.id
          FROM delivery_request_item dri
          INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
          WHERE dri.id_penjualan = $sid
            AND drq.id_pelanggan = $pid
            AND drq.jenis = 'antar'
            AND drq.delivery_status IN ('berjalan','menunggu_pembayaran')
          LIMIT 1"
      );
      if (is_array($busy) && !empty($busy)) {
         return "Item #$sid sudah ada di permintaan antar yang berjalan";
      }
      return '';
   }

   /** Default titik peta: kota cabang pelanggan (kota.latt / kota.longt) */
   private function getDefaultMapCoords(): array
   {
      $fallback = [
         'latt' => 0.507068,
         'longt' => 101.447779,
         'nama_kota' => 'PEKANBARU',
         'source' => 'fallback',
      ];
      $idKota = (int) ($this->dCabangPublic['id_kota'] ?? 0);
      if ($idKota <= 0) {
         return $fallback;
      }
      $kota = $this->db(0)->get_where_row('kota', 'id_kota = ' . $idKota);
      if (!is_array($kota)) {
         return $fallback;
      }
      $latt = (float) ($kota['latt'] ?? 0);
      $longt = (float) ($kota['longt'] ?? 0);
      if ($latt == 0.0 && $longt == 0.0) {
         return $fallback;
      }
      return [
         'latt' => $latt,
         'longt' => $longt,
         'nama_kota' => (string) ($kota['nama_kota'] ?? ''),
         'source' => 'kota',
      ];
   }

   private function ensureKurirLookups(): void
   {
      if (empty($this->dDurasi)) {
         $this->dDurasi = $this->db(0)->get('durasi');
      }
      if (empty($this->itemGroup)) {
         $this->itemGroup = $this->db(0)->get('item_group');
      }
      if (empty($this->dPenjualan)) {
         $this->dPenjualan = $this->db(0)->get('penjualan_jenis');
      }
      if (empty($this->dSatuan)) {
         $this->dSatuan = $this->db(0)->get('satuan');
      }
   }

   private function getPendingKurirRequests($pelanggan): array
   {
      $rows = $this->db(0)->get_where(
         'delivery_request',
         'id_pelanggan = ' . (int) $pelanggan
            . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
            . ' ORDER BY insertTime DESC'
      );
      if (!is_array($rows)) {
         return [];
      }

      // Antar: cek apakah sudah ada item selesai laundry (notif tipe=2)
      $antarIds = [];
      foreach ($rows as $r) {
         $idReq = (int) ($r['id_request'] ?? 0);
         $jenis = strtolower((string) ($r['jenis'] ?? ''));
         if ($idReq > 0 && $jenis === 'antar') {
            $antarIds[] = $idReq;
         }
      }
      $selesaiMap = [];
      if (!empty($antarIds)) {
         $idList = implode(',', array_map('intval', $antarIds));
         $selesaiRows = $this->db(0)->query_array(
            "SELECT dri.id_request, COUNT(*) AS selesai_count
             FROM delivery_request_item dri
             INNER JOIN notif n
               ON n.tipe = 2
              AND n.no_ref = CAST(dri.id_penjualan AS CHAR)
             WHERE dri.id_request IN ($idList)
             GROUP BY dri.id_request"
         );
         if (is_array($selesaiRows)) {
            foreach ($selesaiRows as $sr) {
               $rid = (int) ($sr['id_request'] ?? 0);
               if ($rid > 0) {
                  $selesaiMap[$rid] = (int) ($sr['selesai_count'] ?? 0);
               }
            }
         }
      }

      $out = [];
      foreach ($rows as $r) {
         $idReq = (int) ($r['id_request'] ?? 0);
         $jenis = (string) ($r['jenis'] ?? '');
         $out[] = [
            'id_request' => $idReq,
            'jenis' => $jenis,
            'layanan' => (string) ($r['layanan'] ?? 'sameday'),
            'delivery_status' => (string) ($r['delivery_status'] ?? ''),
            'insertTime' => (string) ($r['insertTime'] ?? ''),
            'lokasi_nama' => (string) ($r['lokasi_nama'] ?? ''),
            'lokasi_detail' => (string) ($r['lokasi_detail'] ?? ''),
            'catatan_kurir' => (string) ($r['catatan_kurir'] ?? ''),
            'ongkir' => isset($r['ongkir']) ? (int) $r['ongkir'] : null,
            'courier_name' => (string) ($r['courier_name'] ?? ''),
            'biteship_status' => (string) ($r['biteship_status'] ?? ''),
            'tracking_url' => (string) ($r['tracking_url'] ?? ''),
            'payment_ref_finance' => (string) ($r['payment_ref_finance'] ?? ''),
            'driver_name' => (string) ($r['driver_name'] ?? ''),
            'items_selesai_count' => strtolower($jenis) === 'antar'
               ? (int) ($selesaiMap[$idReq] ?? 0)
               : 0,
         ];
      }
      return $out;
   }

   /**
    * Riwayat kurir (selesai + batal) untuk portal J — agar order tidak "hilang".
    */
   private function getKurirRiwayat($pelanggan): array
   {
      $pelanggan = (int) $pelanggan;
      $rows = $this->db(0)->get_where(
         'delivery_request',
         'id_pelanggan = ' . $pelanggan
            . " AND delivery_status IN ('selesai','batal')"
            . ' ORDER BY COALESCE(selesaiTime, insertTime) DESC, id_request DESC'
            . ' LIMIT 30'
      );
      if (!is_array($rows) || empty($rows)) {
         return [];
      }

      $ids = [];
      foreach ($rows as $r) {
         $id = (int) ($r['id_request'] ?? 0);
         if ($id > 0) {
            $ids[] = $id;
         }
      }
      $refundedMap = [];
      if (!empty($ids)) {
         $idList = implode(',', $ids);
         $refundRows = $this->db(0)->get_where(
            'kas',
            'id_client = ' . $pelanggan
               . ' AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3'
               . " AND ref_transaksi IN ($idList)"
               . " AND note LIKE 'Refund Instant #%'"
         );
         if (is_array($refundRows)) {
            foreach ($refundRows as $rk) {
               $rid = (int) ($rk['ref_transaksi'] ?? 0);
               if ($rid > 0) {
                  $refundedMap[$rid] = true;
               }
            }
         }
      }

      $out = [];
      foreach ($rows as $r) {
         $idReq = (int) ($r['id_request'] ?? 0);
         $catatanBatal = (string) ($r['catatan_batal'] ?? '');
         $refunded = !empty($refundedMap[$idReq])
            || (stripos($catatanBatal, 'Saldo Deposit') !== false);
         $out[] = [
            'id_request' => $idReq,
            'jenis' => (string) ($r['jenis'] ?? ''),
            'layanan' => (string) ($r['layanan'] ?? 'sameday'),
            'delivery_status' => (string) ($r['delivery_status'] ?? ''),
            'insertTime' => (string) ($r['insertTime'] ?? ''),
            'selesaiTime' => (string) ($r['selesaiTime'] ?? ''),
            'lokasi_nama' => (string) ($r['lokasi_nama'] ?? ''),
            'ongkir' => isset($r['ongkir']) ? (int) $r['ongkir'] : null,
            'courier_name' => (string) ($r['courier_name'] ?? ''),
            'biteship_status' => (string) ($r['biteship_status'] ?? ''),
            'tracking_url' => (string) ($r['tracking_url'] ?? ''),
            'catatan_batal' => $catatanBatal,
            'refunded' => $refunded,
         ];
      }
      return $out;
   }

   private function phoneTailFromPelanggan(array $pelanggan): string
   {
      $this->helper('PelangganByPhone');

      return PelangganByPhone::key($pelanggan['nomor_pelanggan'] ?? '');
   }

   /**
    * Catatan untuk kurir (opsional). Max 150 karakter.
    * @return array{ok:bool,value:string,message:string}
    */
   private function normalizeCatatanKurir($raw): array
   {
      $val = trim((string) $raw);
      $val = preg_replace("/[\r\n]+/", ' ', $val);
      $val = preg_replace('/\s+/u', ' ', (string) $val);
      $val = trim((string) $val);
      $len = function_exists('mb_strlen') ? mb_strlen($val, 'UTF-8') : strlen($val);
      if ($len > 150) {
         return [
            'ok' => false,
            'value' => '',
            'message' => 'Catatan maksimal 150 karakter',
         ];
      }
      return ['ok' => true, 'value' => $val, 'message' => ''];
   }

   private function fetchKurirEligibleSaleRows(int $pelanggan, string $jenis, bool $requireNotifSelesai = false): array
   {
      $jenisEsc = $this->db(0)->escape($jenis);
      $pid = (int) $pelanggan;
      $selesaiClause = '';
      if ($requireNotifSelesai) {
         // Notif selesai laundry = tipe 2; no_ref = id_penjualan
         $selesaiClause = "
            AND EXISTS (
              SELECT 1 FROM notif n
              WHERE n.tipe = 2
                AND n.no_ref = CAST(s.id_penjualan AS CHAR)
            )";
      }
      $rows = $this->db(0)->query_array(
         "SELECT s.*
          FROM sale s
          WHERE s.bin = 0
            AND s.id_pelanggan = $pid
            AND (
              s.tuntas = 0
              OR (s.tuntas = 1 AND s.tuntasTime IS NOT NULL AND s.tuntasTime >= (NOW() - INTERVAL 2 DAY))
            )
            AND NOT EXISTS (
              SELECT 1 FROM delivery_riwayat dr
              WHERE dr.id_penjualan = s.id_penjualan AND dr.jenis = '$jenisEsc'
            )
            AND NOT EXISTS (
              SELECT 1
              FROM delivery_request_item dri
              INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
              WHERE dri.id_penjualan = s.id_penjualan
                AND drq.jenis = '$jenisEsc'
                AND drq.delivery_status IN ('berjalan','menunggu_pembayaran')
            )
            $selesaiClause
          ORDER BY s.insertTime DESC, s.id_penjualan DESC
          LIMIT 200"
      );
      return is_array($rows) ? $rows : [];
   }

   private function buildKurirEligibleOrders(int $pelanggan, string $jenis, bool $requireNotifSelesai = false): array
   {
      $rows = $this->fetchKurirEligibleSaleRows($pelanggan, $jenis, $requireNotifSelesai);
      if (empty($rows)) {
         return [];
      }

      $mapSatuan = [];
      foreach ($this->dPenjualan ?? [] as $l) {
         $sat = '';
         foreach ($this->dSatuan ?? [] as $sa) {
            if (($sa['id_satuan'] ?? null) == ($l['id_satuan'] ?? null)) {
               $sat = $sa['nama_satuan'] ?? '';
               break;
            }
         }
         $mapSatuan[$l['id_penjualan_jenis']] = $sat;
      }
      $mapKategori = [];
      foreach ($this->itemGroup ?? [] as $g) {
         $mapKategori[$g['id_item_group']] = $g['item_kategori'];
      }
      $mapDurasi = [];
      foreach ($this->dDurasi ?? [] as $d) {
         $mapDurasi[$d['id_durasi']] = $d['durasi'];
      }

      $orders = [];
      foreach ($rows as $a) {
         $ref = (string) ($a['no_ref'] ?? '');
         if ($ref === '') {
            $ref = 'ID' . (int) $a['id_penjualan'];
         }
         if (!isset($orders[$ref])) {
            $orders[$ref] = [
               'no_ref' => $ref,
               'insertTime' => $a['insertTime'] ?? '',
               'items' => [],
            ];
         }
         $qty = round((float) ($a['qty'] ?? 0), 2);
         $satuan = $mapSatuan[$a['id_penjualan_jenis'] ?? 0] ?? '';
         $qtyShow = rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') . $satuan;
         $orders[$ref]['items'][] = [
            'id' => (int) $a['id_penjualan'],
            'kategori' => $mapKategori[$a['id_item_group'] ?? 0] ?? '',
            'durasi' => strtoupper((string) ($mapDurasi[$a['id_durasi'] ?? 0] ?? '')),
            'qty_show' => $qtyShow,
            'tuntas' => (int) ($a['tuntas'] ?? 0),
         ];
      }
      return array_values($orders);
   }

   /**
    * List pelanggan_lokasi + km/tarif dari koordinat cabang.
    * @return array{ok:bool,message?:string,list?:array,cabang?:array}
    */
   private function buildLokasiListWithTarif($pelanggan)
   {
      $cabLat = (float) ($this->dCabangPublic['latt'] ?? 0);
      $cabLon = (float) ($this->dCabangPublic['long'] ?? 0);
      if ($cabLat == 0.0 && $cabLon == 0.0) {
         return ['ok' => false, 'message' => 'Lokasi cabang belum diatur', 'list' => []];
      }

      $rows = $this->db(0)->get_where(
         'pelanggan_lokasi',
         'id_pelanggan = ' . (int) $pelanggan . ' ORDER BY id_lokasi ASC'
      );
      if (!is_array($rows)) {
         $rows = [];
      }

      $tarifHelper = $this->helper('AntarTarif');
      $list = [];
      foreach ($rows as $row) {
         $locLat = (float) ($row['latt'] ?? 0);
         $locLon = (float) ($row['longt'] ?? 0);
         $calc = $tarifHelper->tarifFromCoordsForPelanggan($cabLat, $cabLon, $locLat, $locLon, (int) $pelanggan);
         $list[] = [
            'id_lokasi' => (int) ($row['id_lokasi'] ?? 0),
            'nama' => (string) ($row['nama'] ?? ''),
            'detail' => (string) ($row['detail'] ?? ''),
            'latt' => $locLat,
            'longt' => $locLon,
            'km' => $calc['km'],
            'tarif' => $calc['tarif'],
            'grant_applied' => !empty($calc['grant_applied']),
         ];
      }

      return [
         'ok' => true,
         'list' => $list,
         'cabang' => [
            'latt' => $cabLat,
            'long' => $cabLon,
            'nama' => (string) ($this->dCabangPublic['nama_cabang'] ?? $this->dCabangPublic['nama'] ?? ''),
         ],
      ];
   }

   /**
    * Satu no_ref dari item terpilih yang statusnya belum tuntas (tuntas=0).
    * @param int $pelanggan
    * @param int[] $ids id_penjualan
    * @return string|null
    */
   private function pickBelumTuntasRef($pelanggan, array $ids)
   {
      if (empty($ids)) {
         return null;
      }
      $safeIds = [];
      foreach ($ids as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $safeIds[] = $id;
         }
      }
      if (empty($safeIds)) {
         return null;
      }

      $idsIn = implode(',', $safeIds);
      $rows = $this->db(0)->get_where(
         'sale',
         'id_pelanggan = ' . (int) $pelanggan
            . ' AND bin = 0 AND tuntas = 0'
            . ' AND id_penjualan IN (' . $idsIn . ')'
            . ' ORDER BY id_penjualan ASC'
      );
      if (!is_array($rows) || empty($rows)) {
         return null;
      }

      $noRef = trim((string) ($rows[0]['no_ref'] ?? ''));
      return $noRef !== '' ? $noRef : null;
   }

   /**
    * Insert surcas Pengantaran (jenis 2). Satu ref boleh banyak;
    * skip hanya jika semua id_penjualan sudah terikat jenis ini.
    *
    * @param int $idDeliveryRequest 0 = tidak ditandai delivery
    * @param int[] $ids
    * @return true|'exists'|false
    */
   private function insertSurcasPengantaran($noRef, $jumlah, $idDeliveryRequest = 0, array $ids = [])
   {
      $noRef = trim((string) $noRef);
      $jumlah = (int) $jumlah;
      $idDeliveryRequest = (int) $idDeliveryRequest;
      if ($noRef === '' || $jumlah < 0) {
         return false;
      }

      $idCabang = (int) $this->id_cabang_p;
      $this->helper('AntarTarif');
      $this->helper('SurcasKurir');
      try {
         $res = SurcasKurir::insertForSales(
            $this->db(0),
            $idCabang,
            $ids,
            $jumlah,
            0,
            (int) AntarTarif::SURCAS_JENIS_PENGANTARAN,
            $idDeliveryRequest,
            'Pengantaran'
         );
         return !empty($res['skipped']) ? 'exists' : true;
      } catch (\Throwable $e) {
         $this->model('Log')->write(__CLASS__ . '->insertSurcasPengantaran() ' . $e->getMessage());
         return false;
      }
   }
}
