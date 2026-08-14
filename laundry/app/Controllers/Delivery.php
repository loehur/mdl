<?php

class Delivery extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $data_operasi = ['title' => 'Delivery Order'];
      $canCekDetail = $this->canCekDetail();

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('delivery/index', [
         'data_operasi' => $data_operasi,
         'deferBoard' => true,
         'canCekDetail' => $canCekDetail,
      ]);
   }

   /**
    * Muat isi board Delivery (customer + cabang) — dipanggil async setelah shell tampil.
    */
   public function board_data()
   {
      header('Content-Type: application/json; charset=utf-8');
      try {
         echo json_encode([
            'status' => 'success',
            'data' => $this->buildBoardPayload(),
         ], JSON_UNESCAPED_UNICODE);
      } catch (\Throwable $e) {
         echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
         ], JSON_UNESCAPED_UNICODE);
      }
   }

   /**
    * @return array{html_customer:string,html_cabang:string,count_customer:int,count_cabang:int}
    */
   private function buildBoardPayload(): array
   {
      $transfers = $this->getPendingCabangTransfers();
      $customerRequests = $this->getPendingCustomerRequests();
      $customerRequestsSiap = [];
      $customerRequestsBelum = [];
      foreach ($customerRequests as $rq) {
         if (!empty($rq['siap_selesai'])) {
            $customerRequestsSiap[] = $rq;
         } else {
            $customerRequestsBelum[] = $rq;
         }
      }

      return [
         'html_customer' => $this->renderViewPartial('delivery/partials/board_customer_body', [
            'customerRequestsSiap' => $customerRequestsSiap,
            'customerRequestsBelum' => $customerRequestsBelum,
         ]),
         'html_cabang' => $this->renderViewPartial('delivery/partials/board_cabang_body', [
            'transfers' => $transfers,
         ]),
         'count_customer' => count($customerRequests),
         'count_cabang' => count($transfers),
      ];
   }

   private function renderViewPartial(string $viewFile, array $data = []): string
   {
      foreach ($data as $key => $value) {
         $$key = $value;
      }
      ob_start();
      require 'app/Views/' . $viewFile . '.php';
      return (string) ob_get_clean();
   }

   /**
    * Cari pelanggan cabang session aktif (untuk tambah Delivery manual).
    */
   public function search_pelanggan()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      header('Content-Type: application/json; charset=utf-8');

      $idCabang = (int) ($this->id_cabang ?? 0);
      if ($idCabang <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Cabang session tidak valid', 'items' => []]);
         return;
      }

      $q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
      $where = 'id_cabang = ' . $idCabang;
      if ($q !== '') {
         $esc = $this->db(0)->escape($q);
         $digits = preg_replace('/\D/', '', $q);
         $parts = [
            "nama_pelanggan LIKE '%{$esc}%'",
            "nomor_pelanggan LIKE '%{$esc}%'",
         ];
         if ($digits !== '') {
            $escD = $this->db(0)->escape($digits);
            $parts[] = "nomor_pelanggan LIKE '%{$escD}%'";
         }
         $where .= ' AND (' . implode(' OR ', $parts) . ')';
      }

      $rows = $this->db(0)->get_where_order('pelanggan', $where, 'nama_pelanggan ASC LIMIT 30');
      if (!is_array($rows)) {
         $rows = [];
      }

      $items = [];
      foreach ($rows as $r) {
         $items[] = [
            'id_pelanggan' => (int) ($r['id_pelanggan'] ?? 0),
            'nama_pelanggan' => (string) ($r['nama_pelanggan'] ?? ''),
            'nomor_pelanggan' => (string) ($r['nomor_pelanggan'] ?? ''),
         ];
      }

      echo json_encode(['status' => 'success', 'items' => $items], JSON_UNESCAPED_UNICODE);
   }

   /**
    * List lokasi tersimpan pelanggan (hanya pilih, tidak create).
    */
   public function lokasi_options()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      header('Content-Type: application/json; charset=utf-8');

      $idCabang = (int) ($this->id_cabang ?? 0);
      $idPelanggan = (int) ($_GET['id_pelanggan'] ?? $_POST['id_pelanggan'] ?? 0);
      if ($idCabang <= 0 || $idPelanggan <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Data tidak valid', 'items' => []]);
         return;
      }

      $pel = $this->db(0)->get_where_row(
         'pelanggan',
         'id_pelanggan = ' . $idPelanggan . ' AND id_cabang = ' . $idCabang
      );
      if (!is_array($pel) || empty($pel['id_pelanggan'])) {
         echo json_encode(['status' => 'error', 'message' => 'Pelanggan tidak ditemukan di cabang ini', 'items' => []]);
         return;
      }

      $rows = $this->db(0)->get_where_order(
         'pelanggan_lokasi',
         'id_pelanggan = ' . $idPelanggan,
         'id_lokasi DESC'
      );
      if (!is_array($rows)) {
         $rows = [];
      }

      $items = [];
      foreach ($rows as $r) {
         $latt = (float) ($r['latt'] ?? 0);
         $longt = (float) ($r['longt'] ?? 0);
         $items[] = [
            'id_lokasi' => (int) ($r['id_lokasi'] ?? 0),
            'nama' => (string) ($r['nama'] ?? ''),
            'detail' => (string) ($r['detail'] ?? ''),
            'latt' => $latt,
            'longt' => $longt,
         ];
      }

      echo json_encode(['status' => 'success', 'items' => $items], JSON_UNESCAPED_UNICODE);
   }

   /**
    * Hitung tarif surcas dari rumus ongkir (jarak cabang → lokasi pelanggan).
    * GET/POST: id_pelanggan (opsional jika phone_tail), phone_tail (opsional), id_request (opsional).
    */
   public function tarif_surcas()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }

      $idPelanggan = (int) ($_REQUEST['id_pelanggan'] ?? 0);
      $idRequest = (int) ($_REQUEST['id_request'] ?? 0);
      $phoneTail = preg_replace('/[^0-9]/', '', (string) ($_REQUEST['phone_tail'] ?? ''));

      if ($idPelanggan <= 0 && strlen($phoneTail) >= 8) {
         $ids = $this->pelangganIdsByPhoneTail($phoneTail);
         if (!empty($ids)) {
            $idPelanggan = (int) $ids[0];
         }
      }

      $loc = $this->resolveLokasiCoordsForTarif($idPelanggan, $idRequest);
      if ($loc === null) {
         echo json_encode([
            'status' => 'error',
            'message' => 'Lokasi pelanggan belum ada',
         ], JSON_UNESCAPED_UNICODE);
         return;
      }

      $cabLat = (float) ($this->dCabang['latt'] ?? 0);
      $cabLon = (float) ($this->dCabang['long'] ?? 0);
      if ($cabLat == 0.0 && $cabLon == 0.0) {
         echo json_encode([
            'status' => 'error',
            'message' => 'Koordinat cabang belum diatur',
         ], JSON_UNESCAPED_UNICODE);
         return;
      }

      $calc = $this->helper('AntarTarif')->tarifFromCoords(
         $cabLat,
         $cabLon,
         (float) $loc['latt'],
         (float) $loc['longt']
      );

      echo json_encode([
         'status' => 'success',
         'data' => [
            'tarif' => (int) ($calc['tarif'] ?? 0),
            'km' => (float) ($calc['km'] ?? 0),
            'lokasi_nama' => (string) ($loc['nama'] ?? ''),
         ],
      ], JSON_UNESCAPED_UNICODE);
   }

   /**
    * Tarik lokasi pelanggan ke delivery_request yang lokasinya masih kosong.
    * POST: id_request, id_lokasi (opsional).
    * Tanpa id_lokasi: 1 lokasi → apply; >1 → need_choose + list; 0 → error.
    */
   public function tarik_lokasi_request()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $idRequest = (int) ($_POST['id_request'] ?? 0);
         $idLokasiPick = (int) ($_POST['id_lokasi'] ?? 0);
         if ($idRequest <= 0) {
            throw new Exception('Request tidak valid');
         }

         $req = $this->db(0)->get_where_row(
            'delivery_request',
            'id_request = ' . $idRequest . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
         );
         if (!is_array($req) || empty($req['id_request'])) {
            throw new Exception('Request tidak ditemukan atau sudah selesai');
         }

         $lokNama = trim((string) ($req['lokasi_nama'] ?? ''));
         $lokDetail = trim((string) ($req['lokasi_detail'] ?? ''));
         $lokLatt = (float) ($req['lokasi_latt'] ?? 0);
         $lokLongt = (float) ($req['lokasi_longt'] ?? 0);
         $hasLokasi = ($lokNama !== '' || $lokDetail !== '' || $lokLatt != 0.0 || $lokLongt != 0.0);
         if ($hasLokasi) {
            throw new Exception('Lokasi request sudah terisi');
         }

         $idPelanggan = (int) ($req['id_pelanggan'] ?? 0);
         if ($idPelanggan <= 0) {
            throw new Exception('Pelanggan request tidak valid');
         }

         $rows = $this->db(0)->get_where_order(
            'pelanggan_lokasi',
            'id_pelanggan = ' . $idPelanggan,
            'id_lokasi DESC'
         );
         if (!is_array($rows)) {
            $rows = [];
         }

         $items = [];
         foreach ($rows as $r) {
            $idLok = (int) ($r['id_lokasi'] ?? 0);
            if ($idLok <= 0) {
               continue;
            }
            $latt = (float) ($r['latt'] ?? 0);
            $longt = (float) ($r['longt'] ?? 0);
            $items[] = [
               'id_lokasi' => $idLok,
               'nama' => (string) ($r['nama'] ?? ''),
               'detail' => (string) ($r['detail'] ?? ''),
               'latt' => $latt,
               'longt' => $longt,
               'maps_url' => ($latt != 0.0 || $longt != 0.0)
                  ? ('https://www.google.com/maps?q=' . $latt . ',' . $longt)
                  : '',
            ];
         }

         if (empty($items)) {
            throw new Exception('Pelanggan belum punya lokasi tersimpan');
         }

         $apply = null;
         if ($idLokasiPick > 0) {
            foreach ($items as $it) {
               if ((int) $it['id_lokasi'] === $idLokasiPick) {
                  $apply = $it;
                  break;
               }
            }
            if ($apply === null) {
               throw new Exception('Lokasi tidak valid untuk pelanggan ini');
            }
         } elseif (count($items) === 1) {
            $apply = $items[0];
         } else {
            $response = [
               'status' => 'success',
               'need_choose' => true,
               'message' => 'Pilih lokasi',
               'data' => [
                  'id_request' => $idRequest,
                  'id_pelanggan' => $idPelanggan,
                  'items' => $items,
               ],
            ];
            ob_end_clean();
            if (!headers_sent()) {
               header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
         }

         $set = [
            'id_lokasi' => (int) $apply['id_lokasi'],
            'lokasi_nama' => (string) ($apply['nama'] ?? ''),
            'lokasi_detail' => (string) ($apply['detail'] ?? ''),
            'lokasi_latt' => (float) ($apply['latt'] ?? 0),
            'lokasi_longt' => (float) ($apply['longt'] ?? 0),
         ];
         $upd = $this->db(0)->update(
            'delivery_request',
            $set,
            'id_request = ' . $idRequest . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
         );
         if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
            throw new Exception($upd['error'] ?? 'Gagal update lokasi');
         }

         $response = [
            'status' => 'success',
            'need_choose' => false,
            'message' => 'Lokasi diupdate',
            'data' => [
               'id_request' => $idRequest,
               'id_lokasi' => $set['id_lokasi'],
               'lokasi_nama' => $set['lokasi_nama'],
               'lokasi_detail' => $set['lokasi_detail'],
               'lokasi_latt' => $set['lokasi_latt'],
               'lokasi_longt' => $set['lokasi_longt'],
               'maps_url' => ($set['lokasi_latt'] != 0.0 || $set['lokasi_longt'] != 0.0)
                  ? ('https://www.google.com/maps?q=' . $set['lokasi_latt'] . ',' . $set['lokasi_longt'])
                  : '',
            ],
         ];
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response, JSON_UNESCAPED_UNICODE);
   }

   /**
    * Share lokasi request (Maps) ke group Fonnte.
    * POST: id_request, target=cabang|delivery
    */
   public function share_lokasi_request()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $idRequest = (int) ($_POST['id_request'] ?? 0);
         $target = strtolower(trim((string) ($_POST['target'] ?? '')));
         if ($idRequest <= 0) {
            throw new Exception('Request tidak valid');
         }
         if (!in_array($target, ['cabang', 'delivery'], true)) {
            throw new Exception('Pilih tujuan: Group Cabang atau Group Delivery');
         }

         $req = $this->db(0)->get_where_row(
            'delivery_request',
            'id_request = ' . $idRequest . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
         );
         if (!is_array($req) || empty($req['id_request'])) {
            throw new Exception('Request tidak ditemukan atau sudah selesai');
         }

         $lokNama = trim((string) ($req['lokasi_nama'] ?? ''));
         $lokDetail = trim((string) ($req['lokasi_detail'] ?? ''));
         $lokLatt = (float) ($req['lokasi_latt'] ?? 0);
         $lokLongt = (float) ($req['lokasi_longt'] ?? 0);
         if ($lokLatt == 0.0 && $lokLongt == 0.0) {
            throw new Exception('Koordinat Maps belum ada di request');
         }
         $mapsUrl = 'https://www.google.com/maps?q=' . $lokLatt . ',' . $lokLongt;

         $jenis = strtolower((string) ($req['jenis'] ?? ''));
         $jenisLbl = $jenis === 'antar' ? 'Antar' : ($jenis === 'jemput' ? 'Jemput' : strtoupper($jenis));
         $idPelanggan = (int) ($req['id_pelanggan'] ?? 0);
         $idCabangReq = (int) ($req['id_cabang'] ?? 0);

         $namaPel = 'Customer';
         if ($idPelanggan > 0) {
            $pel = $this->db(0)->get_where_row('pelanggan', 'id_pelanggan = ' . $idPelanggan);
            if (is_array($pel) && !empty($pel['nama_pelanggan'])) {
               $namaPel = strtoupper(trim((string) $pel['nama_pelanggan']));
            }
         }

         $kodeCabang = '';
         if ($idCabangReq > 0) {
            $cab = $this->db(0)->get_where_row('cabang', 'id_cabang = ' . $idCabangReq);
            if (is_array($cab)) {
               $kodeCabang = (string) ($cab['kode_cabang'] ?? '');
            }
         }

         $lines = [];
         $lines[] = '*' . $jenisLbl . ' #' . $idRequest . '*' . ($kodeCabang !== '' ? ' · ' . $kodeCabang : '');
         $lines[] = $namaPel;
         if ($lokNama !== '') {
            $lines[] = 'Lokasi: ' . $lokNama . ($lokDetail !== '' ? ' · ' . $lokDetail : '');
         } elseif ($lokDetail !== '') {
            $lines[] = 'Lokasi: ' . $lokDetail;
         }
         $lines[] = 'Maps: ' . $mapsUrl;
         $message = implode("\n", $lines);

         $groupId = $this->resolveShareFonnteGroupId($target, $idCabangReq);
         if ($groupId === '') {
            throw new Exception($target === 'cabang'
               ? 'ID Group Cabang belum diset'
               : 'ID Group Delivery belum tersedia');
         }

         $this->helper('FonnteService');
         $send = FonnteService::sendToGroup($groupId, $message);
         if (empty($send['success'])) {
            throw new Exception($send['error'] ?? 'Gagal kirim ke group');
         }

         $label = $target === 'cabang' ? 'Group Cabang' : 'Group Delivery';
         $response = [
            'status' => 'success',
            'message' => 'Lokasi dikirim ke ' . $label,
            'data' => [
               'id_request' => $idRequest,
               'target' => $target,
               'maps_url' => $mapsUrl,
            ],
         ];
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response, JSON_UNESCAPED_UNICODE);
   }

   /**
    * Buat delivery_request manual dari board Delivery.
    * Lokasi opsional (hanya pilih yang sudah ada — tidak create lokasi baru).
    */
   public function buat_manual()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $idCabang = (int) ($this->id_cabang ?? 0);
         $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
         $jenis = strtolower(trim((string) ($_POST['jenis'] ?? '')));
         $idLokasi = (int) ($_POST['id_lokasi'] ?? 0);

         if ($idCabang <= 0) {
            throw new Exception('Cabang session tidak valid');
         }
         if ($idPelanggan <= 0) {
            throw new Exception('Pilih pelanggan');
         }
         if (!in_array($jenis, ['jemput', 'antar'], true)) {
            throw new Exception('Pilih jenis jemput atau antar');
         }

         $pel = $this->db(0)->get_where_row(
            'pelanggan',
            'id_pelanggan = ' . $idPelanggan . ' AND id_cabang = ' . $idCabang
         );
         if (!is_array($pel) || empty($pel['id_pelanggan'])) {
            throw new Exception('Pelanggan tidak ditemukan di cabang aktif');
         }

         $digits = preg_replace('/[^0-9]/', '', (string) ($pel['nomor_pelanggan'] ?? ''));
         $phoneTail = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
         if (strlen($phoneTail) < 8) {
            throw new Exception('Nomor pelanggan belum lengkap');
         }

         $pending = (int) ($this->db(0)->count_where(
            'delivery_request',
            'id_pelanggan = ' . $idPelanggan
               . " AND jenis = '" . $this->db(0)->escape($jenis) . "'"
               . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
               . " AND layanan = 'sameday'"
         ) ?? 0);
         if ($pending > 0) {
            throw new Exception('Sudah ada request ' . $jenis . ' berjalan untuk pelanggan ini');
         }

         $lokasiNama = '';
         $lokasiDetail = '';
         $lokLatt = 0.0;
         $lokLongt = 0.0;
         $tarifSurcas = null;

         if ($idLokasi > 0) {
            $lok = $this->db(0)->get_where_row(
               'pelanggan_lokasi',
               'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . $idPelanggan
            );
            if (!is_array($lok) || empty($lok['id_lokasi'])) {
               throw new Exception('Lokasi tidak valid untuk pelanggan ini');
            }
            $lokasiNama = (string) ($lok['nama'] ?? '');
            $lokasiDetail = (string) ($lok['detail'] ?? '');
            $lokLatt = (float) ($lok['latt'] ?? 0);
            $lokLongt = (float) ($lok['longt'] ?? 0);

            $cabLat = (float) ($this->dCabang['latt'] ?? 0);
            $cabLon = (float) ($this->dCabang['long'] ?? 0);
            if ($cabLat != 0.0 || $cabLon != 0.0) {
               if ($lokLatt != 0.0 || $lokLongt != 0.0) {
                  $calc = $this->helper('AntarTarif')->tarifFromCoords($cabLat, $cabLon, $lokLatt, $lokLongt);
                  $tarifSurcas = (int) ($calc['tarif'] ?? 0);
               }
            }
         }

         $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
         $insData = [
            'sumber' => 'customer',
            'jenis' => $jenis,
            'layanan' => 'sameday',
            'delivery_status' => 'berjalan',
            'id_pelanggan' => $idPelanggan,
            'phone_tail' => $phoneTail,
            'id_cabang' => $idCabang,
            'id_lokasi' => $idLokasi > 0 ? $idLokasi : 0,
            'lokasi_nama' => $lokasiNama,
            'lokasi_detail' => $lokasiDetail,
            'lokasi_latt' => $lokLatt,
            'lokasi_longt' => $lokLongt,
            'insertTime' => $now,
            'catatan_kurir' => 'Manual dari board Delivery',
         ];
         if ($tarifSurcas !== null) {
            $insData['tarif_surcas'] = $tarifSurcas;
         }

         $ins = $this->db(0)->insert('delivery_request', $insData);
         if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
            throw new Exception($ins['error'] ?? 'Gagal membuat request');
         }
         $idRequest = (int) ($ins['insert_id'] ?? 0);
         if ($idRequest <= 0) {
            throw new Exception('Gagal membuat request');
         }

         $response = [
            'status' => 'success',
            'message' => 'Request ' . $jenis . ' #' . $idRequest . ' dibuat',
            'data' => [
               'id_request' => $idRequest,
               'jenis' => $jenis,
               'phone_tail' => $phoneTail,
               'nama' => strtoupper((string) ($pel['nama_pelanggan'] ?? 'Customer')),
            ],
         ];
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response, JSON_UNESCAPED_UNICODE);
   }

   /**
    * Dari Operasi FAB Kurir.
    * Surcas selalu ditulis ke nota (wajib pilih item). Request lama di board diikat (reuse).
    * - jemput: selesai + surcas jemput (+ tutup request jemput jika ada)
    * - antar tanpa penyelesai: request antar + surcas antar ke nota
    * - antar + penyelesai: selesai + surcas antar (+ tutup request antar jika ada)
    * - jemput_antar: selesai jemput + kedua surcas ke nota + request antar di board
    */
   public function kurir_dari_operasi()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $idCabang = (int) ($this->id_cabang ?? 0);
         $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
         $jenis = strtolower(trim((string) ($_POST['jenis'] ?? '')));
         $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);

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

         if ($idCabang <= 0) {
            throw new Exception('Cabang session tidak valid');
         }
         if ($idPelanggan <= 0) {
            throw new Exception('Pilih pelanggan di Operasi dulu');
         }
         if (!in_array($jenis, ['jemput', 'antar', 'jemput_antar'], true)) {
            throw new Exception('Pilih jenis jemput / antar / jemput & antar');
         }

         $pel = $this->db(0)->get_where_row(
            'pelanggan',
            'id_pelanggan = ' . $idPelanggan . ' AND id_cabang = ' . $idCabang
         );
         if (!is_array($pel) || empty($pel['id_pelanggan'])) {
            throw new Exception('Pelanggan tidak ditemukan di cabang aktif');
         }

         $digits = preg_replace('/[^0-9]/', '', (string) ($pel['nomor_pelanggan'] ?? ''));
         $phoneTail = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
         if (strlen($phoneTail) < 8) {
            throw new Exception('Nomor pelanggan belum lengkap');
         }

         $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
         $pelangganIds = [$idPelanggan];

         // ===== Jemput / Jemput & Antar: selesai jemput =====
         if ($jenis === 'jemput' || $jenis === 'jemput_antar') {
            if ($idKaryawan <= 0) {
               throw new Exception('Wajib pilih penyelesai jemput');
            }
            if (empty($ids)) {
               throw new Exception('Pilih minimal satu item jemput');
            }

            $jumlahAntar = -1;
            if ($jenis === 'jemput_antar') {
               $jumlahAntar = (int) ($_POST['jumlah_surcas_antar'] ?? -1);
               if ($jumlahAntar < 0) {
                  throw new Exception('Isi Surcas Pengantaran (isi 0 untuk gratis)');
               }
            }

            $karyawan = $this->db(0)->get_where_row('user', 'id_user = ' . $idKaryawan . ' AND en = 1');
            if (!$karyawan) {
               throw new Exception('Karyawan tidak ditemukan');
            }
            $namaKaryawan = (string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan));

            $reqJemput = $this->findActiveDeliveryRequest($idPelanggan, 'jemput');
            $idReqJemput = (int) ($reqJemput['id_request'] ?? 0);

            $deliveredSet = $this->saleIdsWithDeliveryRiwayat($ids, 'jemput');
            $freshIds = [];
            foreach ($ids as $idSale) {
               $idSale = (int) $idSale;
               if ($idSale > 0 && !isset($deliveredSet[$idSale])) {
                  $freshIds[] = $idSale;
               }
            }

            $inserted = 0;
            if (!empty($freshIds)) {
               $inserted = $this->insertDeliveryRiwayatBatch(
                  $phoneTail,
                  $pelangganIds,
                  'jemput',
                  $freshIds,
                  $idKaryawan,
                  $namaKaryawan,
                  $idCabang,
                  $now,
                  $idReqJemput,
                  true
               );
            }

            $jumlahSurcas = (int) ($_POST['jumlah_surcas_jemput'] ?? -1);
            $lockedJemput = $this->lockedTarifSurcasForSaleIds($ids, 'jemput');
            if ($lockedJemput !== null) {
               $jumlahSurcas = $lockedJemput;
            }
            $surcasJemput = $this->upsertSurcasPenjemputan(
               $idCabang,
               $ids,
               $jumlahSurcas,
               $idKaryawan,
               $idReqJemput
            );

            if ($idReqJemput > 0) {
               $this->closeActiveDeliveryRequest($idReqJemput, $idKaryawan, $namaKaryawan, $now);
            }

            $msg = $inserted > 0
               ? "Delivery jemput selesai ($inserted item)"
               : 'Item sudah ada riwayat jemput';
            if ($idReqJemput > 0) {
               $msg .= " · Request jemput #$idReqJemput ditutup";
            }
            $msg .= !empty($surcasJemput['skipped'])
               ? ' · Surcas jemput sudah ada (dilewati)'
               : ' · Surcas jemput ditambahkan';

            $idAntar = 0;
            $antarBound = false;
            $surcasAntar = null;
            if ($jenis === 'jemput_antar') {
               $lockedAntar = $this->lockedTarifSurcasForSaleIds($ids, 'antar');
               if ($lockedAntar !== null) {
                  $jumlahAntar = $lockedAntar;
               }
               $antarRes = $this->ensureAntarRequestBound(
                  $idPelanggan,
                  $idCabang,
                  $phoneTail,
                  $jumlahAntar,
                  $idReqJemput,
                  'Antar kembali setelah jemput Operasi (Kurir)'
               );
               $idAntar = (int) ($antarRes['id_request'] ?? 0);
               $antarBound = !empty($antarRes['bound']);
               if ($idAntar <= 0) {
                  throw new Exception('Gagal memastikan request antar kembali');
               }
               $this->attachSaleItemsToRequest($idAntar, $idPelanggan, $ids);
               $surcasAntar = $this->upsertSurcasPengantaran(
                  $idCabang,
                  $ids,
                  $jumlahAntar,
                  $idKaryawan,
                  $idAntar
               );
               $msg .= $antarBound
                  ? " · Request Antar #$idAntar diikat"
                  : " + Request Antar kembali #$idAntar";
               $msg .= !empty($surcasAntar['skipped'])
                  ? ' · Surcas antar sudah ada (dilewati)'
                  : ' · Surcas antar ditambahkan ke nota';
            }

            $response = [
               'status' => 'success',
               'message' => $msg,
               'data' => [
                  'mode' => $jenis === 'jemput_antar' ? 'selesai_plus_antar' : 'selesai',
                  'jenis' => 'jemput',
                  'phone_tail' => $phoneTail,
                  'count' => $inserted,
                  'surcas_jemput' => $surcasJemput,
                  'surcas_antar' => $surcasAntar,
                  'id_request_jemput' => $idReqJemput > 0 ? $idReqJemput : null,
                  'id_request_antar' => $idAntar > 0 ? $idAntar : null,
                  'antar_bound' => $antarBound,
               ],
            ];
         } elseif ($jenis === 'antar') {
            // ===== Antar: request / selesai / backfill surcas (legacy delivered tanpa surcas) =====
            if (empty($ids)) {
               throw new Exception('Pilih minimal satu item antar (untuk surcas ke nota)');
            }
            $tarifSurcas = (int) ($_POST['jumlah_surcas_antar'] ?? -1);
            $lockedAntar = $this->lockedTarifSurcasForSaleIds($ids, 'antar');
            if ($lockedAntar !== null) {
               $tarifSurcas = $lockedAntar;
            }
            if ($tarifSurcas < 0) {
               throw new Exception('Isi Surcas Pengantaran (isi 0 untuk gratis)');
            }

            $deliveredSet = $this->saleIdsWithDeliveryRiwayat($ids, 'antar');
            $deliveredCount = 0;
            foreach ($ids as $idSale) {
               if (isset($deliveredSet[(int) $idSale])) {
                  $deliveredCount++;
               }
            }
            if ($deliveredCount > 0 && $deliveredCount < count($ids)) {
               throw new Exception('Jangan campur item sudah Delivered dengan yang belum. Pilih terpisah.');
            }

            // Legacy: item sudah Delivered (WA/manual lama) tapi surcas pengantaran belum masuk nota
            if ($deliveredCount === count($ids)) {
               $reqAntar = $this->findActiveDeliveryRequest($idPelanggan, 'antar');
               $idReqAntar = (int) ($reqAntar['id_request'] ?? 0);
               $surcasAntar = $this->upsertSurcasPengantaran(
                  $idCabang,
                  $ids,
                  $tarifSurcas,
                  $idKaryawan,
                  $idReqAntar
               );
               if (!empty($surcasAntar['skipped'])) {
                  throw new Exception('Surcas pengantaran sudah ada di nota ref ' . ($surcasAntar['no_ref'] ?? ''));
               }
               $response = [
                  'status' => 'success',
                  'message' => 'Surcas antar ditambahkan ke nota (item sudah Delivered — tanpa request baru)',
                  'data' => [
                     'mode' => 'surcas_backfill',
                     'jenis' => 'antar',
                     'phone_tail' => $phoneTail,
                     'surcas_antar' => $surcasAntar,
                     'id_request_antar' => $idReqAntar > 0 ? $idReqAntar : null,
                  ],
               ];
            } elseif ($idKaryawan <= 0) {
            // ===== Antar: request (buat atau ikat) + surcas ke nota =====
            $reqAntar = $this->findActiveDeliveryRequest($idPelanggan, 'antar');
            $idRequest = (int) ($reqAntar['id_request'] ?? 0);
            $bound = false;

            if ($idRequest > 0) {
               $bound = true;
               $updSet = [
                  'catatan_kurir' => mb_substr('Diikat dari Operasi (Kurir)', 0, 150),
               ];
               $existingTarif = $reqAntar['tarif_surcas'] ?? null;
               if ($existingTarif === null || $existingTarif === '') {
                  $updSet['tarif_surcas'] = $tarifSurcas;
               }
               if ($this->requestLokasiIsEmpty($reqAntar)) {
                  $updSet = array_merge($updSet, $this->defaultLokasiFieldsForRequest($idPelanggan));
               }
               $upd = $this->db(0)->update(
                  'delivery_request',
                  $updSet,
                  'id_request = ' . $idRequest
               );
               if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
                  throw new Exception($upd['error'] ?? 'Gagal mengikat request antar');
               }
            } else {
               $lokasiSet = $this->defaultLokasiFieldsForRequest($idPelanggan);
               $ins = $this->db(0)->insert('delivery_request', array_merge([
                  'sumber' => 'customer',
                  'jenis' => 'antar',
                  'layanan' => 'sameday',
                  'delivery_status' => 'berjalan',
                  'id_pelanggan' => $idPelanggan,
                  'phone_tail' => $phoneTail,
                  'id_cabang' => $idCabang,
                  'insertTime' => $now,
                  'catatan_kurir' => 'Dari Operasi (Kurir)',
                  'tarif_surcas' => $tarifSurcas,
               ], $lokasiSet));
               if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
                  throw new Exception($ins['error'] ?? 'Gagal membuat request');
               }
               $idRequest = (int) ($ins['insert_id'] ?? 0);
               if ($idRequest <= 0) {
                  throw new Exception('Gagal membuat request');
               }
            }

            $this->attachSaleItemsToRequest($idRequest, $idPelanggan, $ids);

            $surcasAntar = $this->upsertSurcasPengantaran(
               $idCabang,
               $ids,
               $tarifSurcas,
               $idKaryawan,
               $idRequest
            );

            $msg = $bound
               ? 'Request antar #' . $idRequest . ' diikat dari Operasi'
               : 'Request antar #' . $idRequest . ' masuk board Delivery';
            $msg .= !empty($surcasAntar['skipped'])
               ? ' · Surcas antar sudah ada (dilewati)'
               : ' · Surcas antar ditambahkan ke nota';

            $response = [
               'status' => 'success',
               'message' => $msg,
               'data' => [
                  'mode' => $bound ? 'request_bound' : 'request',
                  'id_request' => $idRequest,
                  'jenis' => 'antar',
                  'phone_tail' => $phoneTail,
                  'bound' => $bound,
                  'surcas_antar' => $surcasAntar,
               ],
            ];
         } else {
            // ===== Antar: selesai langsung (ikat request jika ada) =====
            $jumlahAntarSelesai = $tarifSurcas;

            $karyawan = $this->db(0)->get_where_row('user', 'id_user = ' . $idKaryawan . ' AND en = 1');
            if (!$karyawan) {
               throw new Exception('Karyawan tidak ditemukan');
            }
            $namaKaryawan = (string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan));

            $reqAntar = $this->findActiveDeliveryRequest($idPelanggan, 'antar');
            $idReqAntar = (int) ($reqAntar['id_request'] ?? 0);

            $inserted = $this->insertDeliveryRiwayatBatch(
               $phoneTail,
               $pelangganIds,
               'antar',
               $ids,
               $idKaryawan,
               $namaKaryawan,
               $idCabang,
               $now,
               $idReqAntar
            );

            $surcasAntar = $this->upsertSurcasPengantaran(
               $idCabang,
               $ids,
               $jumlahAntarSelesai,
               $idKaryawan,
               $idReqAntar
            );

            if ($idReqAntar > 0) {
               $this->closeActiveDeliveryRequest($idReqAntar, $idKaryawan, $namaKaryawan, $now);
            }

            $msg = "Delivery antar selesai ($inserted item)";
            if ($idReqAntar > 0) {
               $msg .= " · Request antar #$idReqAntar ditutup";
            }
            $msg .= !empty($surcasAntar['skipped'])
               ? ' · Surcas antar sudah ada (dilewati)'
               : ' · Surcas antar ditambahkan ke nota';

            $response = [
               'status' => 'success',
               'message' => $msg,
               'data' => [
                  'mode' => 'selesai',
                  'jenis' => 'antar',
                  'phone_tail' => $phoneTail,
                  'count' => $inserted,
                  'surcas_antar' => $surcasAntar,
                  'id_request_antar' => $idReqAntar > 0 ? $idReqAntar : null,
               ],
            ];
         }
         }
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response, JSON_UNESCAPED_UNICODE);
   }

   /**
    * Riwayat 50 pesan terakhir conversation customer — semua user.
    * Param: 9 digit terakhir nomor WA.
    */
   public function customer_detail($phoneTail = '')
   {
      header('Content-Type: application/json; charset=utf-8');

      $phoneTail = preg_replace('/[^0-9]/', '', (string) $phoneTail);
      if (strlen($phoneTail) < 9) {
         echo json_encode(['status' => 'error', 'message' => 'Nomor tidak valid']);
         return;
      }
      $phoneTail = substr($phoneTail, -9);
      $tailEsc = $this->db(100)->escape($phoneTail);

      $convRows = $this->db(100)->query_array(
         "SELECT id, wa_number, contact_name, COALESCE(code, '00') AS kode_cabang, conv_case, last_message_at
          FROM wa_conversations
          WHERE RIGHT(REPLACE(REPLACE(REPLACE(wa_number, '+', ''), '-', ''), ' ', ''), 9) = '$tailEsc'
          LIMIT 5"
      );
      if (!is_array($convRows) || empty($convRows)) {
         echo json_encode(['status' => 'error', 'message' => 'Conversation tidak ditemukan']);
         return;
      }

      $conv = null;
      foreach ($convRows as $row) {
         if ($this->conversationHasOpenCase2($row['conv_case'] ?? '')) {
            $conv = $row;
            break;
         }
      }
      if (!$conv) {
         $conv = $convRows[0];
      }

      $messages = [];
      try {
         $messages = $this->helper('WaChatHistory')->fetchMessages($this->db(100), $phoneTail, 30);
      } catch (\Throwable $e) {
         $messages = [];
      }

      $digits = preg_replace('/[^0-9]/', '', (string) ($conv['wa_number'] ?? ''));
      $phoneDisplay = $this->formatPhoneDisplay((string) ($conv['wa_number'] ?? $digits));
      echo json_encode([
         'status' => 'success',
         'data' => [
            'nama' => strtoupper(trim((string) ($conv['contact_name'] ?? '')) !== '' ? trim($conv['contact_name']) : 'Customer'),
            'phone_tail' => strlen($digits) >= 9 ? substr($digits, -9) : $digits,
            'phone_display' => $phoneDisplay,
            'kode_cabang' => strtoupper((string) ($conv['kode_cabang'] ?? '00')),
            'messages' => $messages,
         ],
      ]);
   }

   /**
    * List sale eligible untuk Selesai Delivery Customer.
    * GET Delivery/sales_options/{phoneTail}?jenis=jemput|antar
    * Operasi: ?operasi=1
    * - antar: item sudah delivered / terikat request tetap tampil jika belum surcas pengantaran
    * - jemput: semua item nota aktif (terikat / sudah riwayat jemput tetap tampil), seperti Antar
    */
   public function sales_options($phoneTail = '')
   {
      header('Content-Type: application/json; charset=utf-8');

      $fromOperasi = (int) ($_GET['operasi'] ?? 0) === 1;
      $idPelangganGet = (int) ($_GET['id_pelanggan'] ?? 0);

      $phoneTail = preg_replace('/[^0-9]/', '', (string) $phoneTail);
      if (strlen($phoneTail) < 8 && !($fromOperasi && $idPelangganGet > 0)) {
         echo json_encode(['status' => 'error', 'message' => 'Nomor tidak valid']);
         return;
      }
      if (strlen($phoneTail) >= 9) {
         $phoneTail = substr($phoneTail, -9);
      }
      $jenis = strtolower(trim((string) ($_GET['jenis'] ?? '')));
      if (!in_array($jenis, ['jemput', 'antar'], true)) {
         echo json_encode(['status' => 'error', 'message' => 'Pilih jenis jemput/antar']);
         return;
      }
      $exceptRequestId = (int) ($_GET['id_request'] ?? 0);
      $includeDeliveredMissingSurcas = $fromOperasi && $jenis === 'antar';
      $includeAllActive = $fromOperasi && $jenis === 'jemput';

      $pelangganIds = [];
      if ($fromOperasi && $idPelangganGet > 0) {
         $pelangganIds = [$idPelangganGet];
      } else {
         $pelangganIds = $this->pelangganIdsByPhoneTail($phoneTail);
      }
      if (empty($pelangganIds)) {
         echo json_encode([
            'status' => 'success',
            'data' => ['orders' => [], 'pelanggan_ids' => []],
            'message' => 'Pelanggan tidak ditemukan',
         ]);
         return;
      }

      $orders = $this->buildEligibleSalesOrders(
         $pelangganIds,
         $jenis,
         $exceptRequestId,
         $includeDeliveredMissingSurcas,
         false,
         $includeAllActive
      );
      echo json_encode([
         'status' => 'success',
         'data' => [
            'orders' => $orders,
            'pelanggan_ids' => $pelangganIds,
         ],
      ]);
   }

   /**
    * Selesaikan delivery customer: simpan riwayat + tutup case 2 CRM.
    * Semua user boleh; wajib pilih karyawan.
    */
   public function selesai_customer()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $phoneTail = preg_replace('/[^0-9]/', '', (string) ($_POST['phone_tail'] ?? ''));
         $jenis = strtolower(trim((string) ($_POST['jenis'] ?? '')));
         $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);
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

         $sekalian = (int) ($_POST['sekalian'] ?? 0) === 1;
         $idsSekalianRaw = $_POST['ids_sekalian'] ?? [];
         if (!is_array($idsSekalianRaw)) {
            $idsSekalianRaw = [$idsSekalianRaw];
         }
         $idsSekalian = [];
         foreach ($idsSekalianRaw as $id) {
            $id = (int) $id;
            if ($id > 0) {
               $idsSekalian[$id] = $id;
            }
         }
         $idsSekalian = array_values($idsSekalian);

         if (strlen($phoneTail) < 9) {
            throw new Exception('Nomor tidak valid');
         }
         $phoneTail = substr($phoneTail, -9);
         if (!in_array($jenis, ['jemput', 'antar'], true)) {
            throw new Exception('Jenis harus jemput atau antar');
         }
         if ($idKaryawan <= 0) {
            throw new Exception('Pilih karyawan yang menyelesaikan');
         }
         if (empty($ids)) {
            throw new Exception('Pilih minimal satu item penjualan');
         }
         if ($sekalian && empty($idsSekalian)) {
            throw new Exception('Sekalian aktif: pilih minimal satu item lawan jenis');
         }
         if ($sekalian) {
            $overlap = array_intersect($ids, $idsSekalian);
            if (!empty($overlap)) {
               throw new Exception('Item jemput dan antar tidak boleh sama');
            }
         }

         $karyawan = $this->db(0)->get_where_row('user', 'id_user = ' . $idKaryawan . ' AND en = 1');
         if (!$karyawan) {
            throw new Exception('Karyawan tidak ditemukan');
         }
         $namaKaryawan = (string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan));

         $pelangganIds = $this->pelangganIdsByPhoneTail($phoneTail);
         if (empty($pelangganIds)) {
            throw new Exception('Pelanggan tidak ditemukan untuk nomor ini');
         }

         $activePortal = $this->countActiveDeliveryRequestsByPhoneTail($phoneTail);
         if ($activePortal > 0) {
            throw new Exception(
               'Customer ini punya ' . $activePortal . ' request portal aktif. Selesaikan request portal dulu (case CRM ikut tertutup otomatis setelah semua lokasi selesai).'
            );
         }

         $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
         $idCabang = (int) ($this->id_cabang ?? 0);

         $inserted = $this->insertDeliveryRiwayatBatch(
            $phoneTail,
            $pelangganIds,
            $jenis,
            $ids,
            $idKaryawan,
            $namaKaryawan,
            $idCabang,
            $now
         );

         $surcasJemput = null;
         $surcasAntar = null;

         $antarKembali = (int) ($_POST['antar_kembali'] ?? 0) === 1 && $jenis === 'jemput';
         if ($antarKembali && $sekalian) {
            throw new Exception('Pilih salah satu: Sekalian Antar atau Request Antar kembali');
         }

         if ($jenis === 'jemput') {
            $jumlahSurcas = (int) ($_POST['jumlah_surcas_jemput'] ?? -1);
            $surcasJemput = $this->upsertSurcasPenjemputan(
               $idCabang,
               $ids,
               $jumlahSurcas,
               $idKaryawan,
               0
            );
         }

         if ($jenis === 'antar') {
            $jumlahAntarOnly = (int) ($_POST['jumlah_surcas_antar'] ?? -1);
            $surcasAntar = $this->upsertSurcasPengantaran(
               $idCabang,
               $ids,
               $jumlahAntarOnly,
               $idKaryawan,
               0
            );
         }

         $insertedSekalian = 0;
         $jenisSekalian = $jenis === 'antar' ? 'jemput' : 'antar';
         if ($sekalian) {
            $insertedSekalian = $this->insertDeliveryRiwayatBatch(
               $phoneTail,
               $pelangganIds,
               $jenisSekalian,
               $idsSekalian,
               $idKaryawan,
               $namaKaryawan,
               $idCabang,
               $now
            );
         }

         $antarKembaliId = 0;
         if ($antarKembali) {
            $jumlahAntar = (int) ($_POST['jumlah_surcas_antar'] ?? -1);
            $surcasAntar = $this->upsertSurcasPengantaran(
               $idCabang,
               $ids,
               $jumlahAntar,
               $idKaryawan,
               0
            );
            $seed = $this->buildCrmAntarKembaliSeed($pelangganIds, $phoneTail, $idCabang);
            $antarKembaliId = $this->createAntarKembaliRequest($seed, $jumlahAntar, 0);
            if ($antarKembaliId <= 0) {
               throw new Exception('Gagal membuat request Antar kembali');
            }
         }

         // Jika Antar kembali dibuat, biarkan case CRM terbuka sampai request Antar selesai
         $crmClosed = $antarKembaliId > 0
            ? $this->maybeCloseCrmCase2ByPhoneTail($phoneTail, $idKaryawan)
            : $this->closeCrmCase2ByPhoneTail($phoneTail, $idKaryawan);

         $msg = "Delivery $jenis selesai ($inserted item)";
         if ($sekalian) {
            $msg .= " + sekalian $jenisSekalian ($insertedSekalian item)";
         }
         if (is_array($surcasJemput)) {
            $msg .= !empty($surcasJemput['skipped'])
               ? ' · Surcas jemput sudah ada (dilewati)'
               : ' · Surcas jemput ditambahkan';
         }
         if (is_array($surcasAntar)) {
            $msg .= !empty($surcasAntar['skipped'])
               ? ' · Surcas antar sudah ada (dilewati)'
               : ' · Surcas antar ditambahkan';
         }
         if ($antarKembaliId > 0) {
            $msg .= " + Request Antar kembali #$antarKembaliId";
         }
         if ($crmClosed) {
            $msg .= '. Case CRM ikut ditutup.';
         }

         $response = [
            'status' => 'success',
            'message' => $msg,
            'data' => [
               'phone_tail' => $phoneTail,
               'jenis' => $jenis,
               'count' => $inserted,
               'sekalian' => $sekalian ? $jenisSekalian : null,
               'count_sekalian' => $insertedSekalian,
               'surcas_jemput' => $surcasJemput,
               'surcas_antar' => $surcasAntar,
               'antar_kembali_id' => $antarKembaliId > 0 ? $antarKembaliId : null,
               'crm_closed' => $crmClosed,
            ],
         ];
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response);
   }

   /**
    * Selesaikan request customer (delivery_request berjalan).
    * Wajib karyawan + item utama; opsional sekalian lawan jenis.
    */
   public function selesai_request()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $idRequest = (int) ($_POST['id_request'] ?? 0);
         $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);
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

         $sekalian = (int) ($_POST['sekalian'] ?? 0) === 1;
         $idsSekalianRaw = $_POST['ids_sekalian'] ?? [];
         if (!is_array($idsSekalianRaw)) {
            $idsSekalianRaw = [$idsSekalianRaw];
         }
         $idsSekalian = [];
         foreach ($idsSekalianRaw as $id) {
            $id = (int) $id;
            if ($id > 0) {
               $idsSekalian[$id] = $id;
            }
         }
         $idsSekalian = array_values($idsSekalian);

         if ($idRequest <= 0) {
            throw new Exception('Request tidak valid');
         }
         if ($idKaryawan <= 0) {
            throw new Exception('Pilih karyawan yang menyelesaikan');
         }
         if (empty($ids)) {
            throw new Exception('Pilih minimal satu item penjualan');
         }
         if ($sekalian && empty($idsSekalian)) {
            throw new Exception('Sekalian aktif: pilih minimal satu item lawan jenis');
         }
         if ($sekalian) {
            $overlap = array_intersect($ids, $idsSekalian);
            if (!empty($overlap)) {
               throw new Exception('Item jemput dan antar tidak boleh sama');
            }
         }

         $karyawan = $this->db(0)->get_where_row('user', 'id_user = ' . $idKaryawan . ' AND en = 1');
         if (!$karyawan) {
            throw new Exception('Karyawan tidak ditemukan');
         }
         $namaKaryawan = (string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan));

         $req = $this->db(0)->get_where_row(
            'delivery_request',
            'id_request = ' . $idRequest . " AND delivery_status = 'berjalan'"
         );
         if (!is_array($req) || empty($req['id_request'])) {
            throw new Exception('Request tidak ditemukan atau sudah selesai');
         }

         $jenis = strtolower((string) ($req['jenis'] ?? ''));
         if (!in_array($jenis, ['antar', 'jemput'], true)) {
            throw new Exception('Jenis request tidak valid');
         }

         $layanan = strtolower((string) ($req['layanan'] ?? 'sameday'));
         if ($layanan === 'instant') {
            if ($jenis !== 'jemput') {
               throw new Exception('Instant Antar selesai otomatis dari Biteship (tidak via Delivery)');
            }
            // Instant Jemput: riwayat saja, tanpa surcas / sekalian
            $sekalian = false;
            $idsSekalian = [];
         }

         $phoneTail = preg_replace('/[^0-9]/', '', (string) ($req['phone_tail'] ?? ''));
         if (strlen($phoneTail) >= 9) {
            $phoneTail = substr($phoneTail, -9);
         }
         if ($phoneTail === '') {
            throw new Exception('Nomor request tidak valid');
         }

         $idPelanggan = (int) ($req['id_pelanggan'] ?? 0);
         $pelangganIds = $idPelanggan > 0
            ? [$idPelanggan]
            : $this->pelangganIdsByPhoneTail($phoneTail);
         if (empty($pelangganIds)) {
            throw new Exception('Pelanggan tidak ditemukan');
         }

         $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
         $idCabang = (int) ($req['id_cabang'] ?? $this->id_cabang ?? 0);

         $inserted = $this->insertDeliveryRiwayatBatch(
            $phoneTail,
            $pelangganIds,
            $jenis,
            $ids,
            $idKaryawan,
            $namaKaryawan,
            $idCabang,
            $now,
            $idRequest
         );

         $surcasJemput = null;
         $surcasAntar = null;

         $antarKembali = (int) ($_POST['antar_kembali'] ?? 0) === 1
            && $jenis === 'jemput'
            && $layanan !== 'instant';
         if ($antarKembali && $sekalian) {
            throw new Exception('Pilih salah satu: Sekalian Antar atau Request Antar kembali');
         }

         if ($jenis === 'jemput' && $layanan !== 'instant') {
            $jumlahSurcas = (int) ($_POST['jumlah_surcas_jemput'] ?? -1);
            if ($jumlahSurcas < 0) {
               $jumlahSurcas = (int) ($req['tarif_surcas'] ?? -1);
            }
            $surcasJemput = $this->upsertSurcasPenjemputan(
               $idCabang,
               $ids,
               $jumlahSurcas,
               $idKaryawan,
               $idRequest
            );
         }

         if ($jenis === 'antar' && $layanan !== 'instant') {
            $jumlahAntar = (int) ($_POST['jumlah_surcas_antar'] ?? -1);
            if ($jumlahAntar < 0) {
               $jumlahAntar = (int) ($req['tarif_surcas'] ?? -1);
            }
            $surcasAntar = $this->upsertSurcasPengantaran(
               $idCabang,
               $ids,
               $jumlahAntar,
               $idKaryawan,
               $idRequest
            );
         }

         $insertedSekalian = 0;
         $jenisSekalian = $jenis === 'antar' ? 'jemput' : 'antar';
         if ($sekalian) {
            $insertedSekalian = $this->insertDeliveryRiwayatBatch(
               $phoneTail,
               $pelangganIds,
               $jenisSekalian,
               $idsSekalian,
               $idKaryawan,
               $namaKaryawan,
               $idCabang,
               $now,
               $idRequest
            );
         }

         $upd = $this->db(0)->update(
            'delivery_request',
            [
               'delivery_status' => 'selesai',
               'id_karyawan' => $idKaryawan,
               'nama_karyawan' => strtoupper($namaKaryawan),
               'selesaiTime' => $now,
            ],
            'id_request = ' . $idRequest . " AND delivery_status = 'berjalan'"
         );
         if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
            throw new Exception($upd['error'] ?? 'Gagal memperbarui status request');
         }

         $antarKembaliId = 0;
         if ($antarKembali) {
            $jumlahAntar = (int) ($_POST['jumlah_surcas_antar'] ?? -1);
            if ($jumlahAntar < 0) {
               $jumlahAntar = (int) ($req['tarif_surcas'] ?? -1);
            }
            $surcasAntar = $this->upsertSurcasPengantaran(
               $idCabang,
               $ids,
               $jumlahAntar,
               $idKaryawan,
               $idRequest
            );
            $antarKembaliId = $this->createAntarKembaliRequest($req, max(0, $jumlahAntar), $idRequest);
            if ($antarKembaliId <= 0) {
               throw new Exception('Gagal membuat request Antar kembali');
            }
         }

         // Tutup case CRM 2 hanya jika semua request portal customer ini sudah selesai
         $crmClosed = $this->maybeCloseCrmCase2ByPhoneTail($phoneTail, $idKaryawan);

         $msg = "Request $jenis selesai ($inserted item)";
         if ($sekalian) {
            $msg .= " + sekalian $jenisSekalian ($insertedSekalian item)";
         }
         if (is_array($surcasJemput)) {
            $msg .= !empty($surcasJemput['skipped'])
               ? ' · Surcas jemput sudah ada (dilewati)'
               : ' · Surcas jemput ditambahkan';
         }
         if (is_array($surcasAntar)) {
            $msg .= !empty($surcasAntar['skipped'])
               ? ' · Surcas antar sudah ada (dilewati)'
               : ' · Surcas antar ditambahkan';
         }
         if ($antarKembaliId > 0) {
            $msg .= " + Request Antar kembali #$antarKembaliId";
         }
         if ($crmClosed) {
            $msg .= '. Case CRM ikut ditutup.';
         }

         $response = [
            'status' => 'success',
            'message' => $msg,
            'data' => [
               'id_request' => $idRequest,
               'phone_tail' => $phoneTail,
               'jenis' => $jenis,
               'count' => $inserted,
               'sekalian' => $sekalian ? $jenisSekalian : null,
               'count_sekalian' => $insertedSekalian,
               'surcas_jemput' => $surcasJemput,
               'surcas_antar' => $surcasAntar,
               'antar_kembali_id' => $antarKembaliId > 0 ? $antarKembaliId : null,
               'crm_closed' => $crmClosed,
            ],
         ];
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response);
   }

   /**
    * Batalkan request customer: status batal + catatan, tanpa riwayat.
    */
   public function batal_request()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $idRequest = (int) ($_POST['id_request'] ?? 0);
         $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);
         $catatan = trim((string) ($_POST['catatan'] ?? ''));

         if ($idRequest <= 0) {
            throw new Exception('Request tidak valid');
         }
         if ($idKaryawan < 1) {
            throw new Exception('Pilih karyawan yang membatalkan');
         }
         if ($catatan === '') {
            throw new Exception('Catatan wajib diisi');
         }

         $karyawan = $this->db(0)->get_where_row('user', 'id_user = ' . $idKaryawan . ' AND en = 1');
         if (!$karyawan) {
            throw new Exception('Karyawan tidak ditemukan');
         }
         $namaKaryawan = (string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan));
         $idUser = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
         $idCabang = (int) ($this->id_cabang ?? 0);

         $req = $this->db(0)->get_where_row(
            'delivery_request',
            'id_request = ' . $idRequest . " AND delivery_status = 'berjalan'"
         );
         if (!is_array($req) || empty($req['id_request'])) {
            throw new Exception('Request tidak ditemukan atau sudah selesai');
         }

         $layananBatal = strtolower((string) ($req['layanan'] ?? 'sameday'));
         if ($layananBatal === 'instant') {
            throw new Exception('Request Instant tidak bisa dibatalkan dari Delivery');
         }

         $boundSurcas = (int) ($this->db(0)->count_where(
            'surcas',
            'id_delivery_request = ' . $idRequest
         ) ?? 0);
         if ($boundSurcas > 0) {
            throw new Exception('Request sudah terikat surcas, tidak bisa dibatalkan');
         }

         $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
         $upd = $this->db(0)->update(
            'delivery_request',
            [
               'delivery_status' => 'batal',
               'id_karyawan' => $idKaryawan,
               'nama_karyawan' => strtoupper($namaKaryawan),
               'catatan_batal' => $catatan,
               'selesaiTime' => $now,
            ],
            'id_request = ' . $idRequest . " AND delivery_status = 'berjalan'"
         );
         if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
            throw new Exception($upd['error'] ?? 'Gagal membatalkan request');
         }

         $log = $this->helper('ActivityLog')->write([
            'modul' => 'delivery',
            'aksi' => 'batal_request',
            'id_ref' => (string) $idRequest,
            'ref' => (string) ($req['phone_tail'] ?? ''),
            'id_karyawan' => $idKaryawan,
            'nama_karyawan' => strtoupper($namaKaryawan),
            'id_user' => $idUser,
            'id_cabang' => $idCabang,
            'catatan' => $catatan,
            'meta' => [
               'id_request' => $idRequest,
               'jenis' => $req['jenis'] ?? '',
               'phone_tail' => $req['phone_tail'] ?? '',
            ],
         ]);
         if (is_array($log) && isset($log['errno']) && (int) $log['errno'] !== 0) {
            throw new Exception($log['error'] ?? 'Gagal menyimpan log');
         }

         $phoneTailReq = preg_replace('/[^0-9]/', '', (string) ($req['phone_tail'] ?? ''));
         if (strlen($phoneTailReq) >= 9) {
            $phoneTailReq = substr($phoneTailReq, -9);
         }
         $crmClosed = $phoneTailReq !== ''
            ? $this->maybeCloseCrmCase2ByPhoneTail($phoneTailReq, $idKaryawan)
            : false;

         $response = [
            'status' => 'success',
            'message' => $crmClosed ? 'Request dibatalkan. Case CRM ikut ditutup.' : 'Request dibatalkan',
            'data' => [
               'id_request' => $idRequest,
               'phone_tail' => $phoneTailReq,
               'id_karyawan' => $idKaryawan,
               'crm_closed' => $crmClosed,
            ],
         ];
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response);
   }

   /**
    * Batalkan delivery customer: tutup case 2 saja (tanpa riwayat).
    * Semua user boleh; wajib karyawan + catatan; dicatat ke activity_log.
    */
   public function batal_customer()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $phoneTail = preg_replace('/[^0-9]/', '', (string) ($_POST['phone_tail'] ?? ''));
         $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);
         $catatan = trim((string) ($_POST['catatan'] ?? ''));

         if (strlen($phoneTail) < 9) {
            throw new Exception('Nomor tidak valid');
         }
         $phoneTail = substr($phoneTail, -9);
         if ($idKaryawan < 1) {
            throw new Exception('Pilih karyawan yang membatalkan');
         }
         if ($catatan === '') {
            throw new Exception('Catatan wajib diisi');
         }

         $karyawan = $this->db(0)->get_where_row('user', 'id_user = ' . $idKaryawan . ' AND en = 1');
         if (!$karyawan) {
            throw new Exception('Karyawan tidak ditemukan');
         }
         $namaKaryawan = (string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan));
         $idUser = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
         $idCabang = (int) ($this->id_cabang ?? 0);

         $activeLeft = $this->countActiveDeliveryRequestsByPhoneTail($phoneTail);
         if ($activeLeft > 0) {
            throw new Exception(
               'Masih ada ' . $activeLeft . ' request portal aktif. Selesaikan/batalkan request portal dulu sebelum menutup case CRM.'
            );
         }

         $this->closeCrmCase2ByPhoneTail($phoneTail, $idKaryawan);

         $log = $this->helper('ActivityLog')->write([
            'modul' => 'delivery',
            'aksi' => 'batal_customer',
            'id_ref' => $phoneTail,
            'ref' => $phoneTail,
            'id_karyawan' => $idKaryawan,
            'nama_karyawan' => strtoupper($namaKaryawan),
            'id_user' => $idUser,
            'id_cabang' => $idCabang,
            'catatan' => $catatan,
            'meta' => [
               'phone_tail' => $phoneTail,
               'closed_case' => 2,
            ],
         ]);
         if (is_array($log) && isset($log['errno']) && (int) $log['errno'] !== 0) {
            throw new Exception($log['error'] ?? 'Gagal menyimpan log');
         }

         $response = [
            'status' => 'success',
            'message' => 'Delivery dibatalkan (case ditutup)',
            'data' => [
               'phone_tail' => $phoneTail,
               'id_karyawan' => $idKaryawan,
            ],
         ];
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response);
   }

   /**
    * Detail barang transfer — semua user login.
    */
   public function detail($ref = '')
   {
      header('Content-Type: application/json; charset=utf-8');

      $ref = trim((string) $ref);
      if ($ref === '') {
         echo json_encode(['status' => 'error', 'message' => 'Ref tidak valid']);
         return;
      }

      $refEsc = $this->db(0)->escape($ref);
      $items = $this->db(0)->get_where(
         'barang_mutasi',
         "ref = '$refEsc' AND type = 2 AND state = 0 AND source_id > 0 AND target_id > 0"
      );

      if (!is_array($items) || empty($items)) {
         echo json_encode(['status' => 'error', 'message' => 'Transfer tidak ditemukan atau sudah diterima']);
         return;
      }

      $cabangMap = $this->buildCabangMap();
      $first = $items[0];
      $sourceId = (int) ($first['source_id'] ?? 0);
      $targetId = (int) ($first['target_id'] ?? 0);

      $list = [];
      $total = 0;
      foreach ($items as $item) {
         $item = $this->itemWithBarangMeta($item);
         $margin = (float) ($item['margin'] ?? 0);
         $price = (float) ($item['price'] ?? 0) + $margin;
         $qty = (float) ($item['qty'] ?? 0);
         $lineTotal = $price * $qty;
         $total += $lineTotal;
         $list[] = [
            'id' => (int) ($item['id'] ?? 0),
            'nama' => $item['nama_barang'] ?? '-',
            'deskripsi' => $item['deskripsi_barang'] ?? '',
            'qty' => $qty,
            'unit' => $item['unit_nama'] ?? '',
            'denom' => $item['denom'] ?? 1,
            'price' => $price,
            'total' => $lineTotal,
         ];
      }

      echo json_encode([
         'status' => 'success',
         'data' => [
            'ref' => $ref,
            'date' => $first['created_at'] ?? '',
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'source_kode' => $cabangMap[$sourceId] ?? ('#' . $sourceId),
            'target_kode' => $cabangMap[$targetId] ?? ('#' . $targetId),
            'items' => $list,
            'total' => $total,
         ],
      ]);
   }

   /**
    * Edit qty multi-item transfer cabang (belum diterima).
    * Semua user boleh buka; authorize wajib karyawan priv 12/100.
    */
   public function update_qty()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $ref = trim((string) ($_POST['ref'] ?? ''));
         $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);
         $itemsRaw = $_POST['items'] ?? '[]';

         if ($ref === '') {
            throw new Exception('Ref tidak valid');
         }
         if ($idKaryawan < 1) {
            throw new Exception('Pilih karyawan yang mengedit');
         }

         $karyawan = $this->db(0)->get_where_row('user', 'id_user = ' . $idKaryawan . ' AND en = 1');
         if (!$karyawan) {
            throw new Exception('Karyawan tidak ditemukan');
         }
         $priv = (int) ($karyawan['id_privilege'] ?? 0);
         if ($priv !== 12 && $priv !== 100) {
            throw new Exception('Hanya Admin atau Kurir yang boleh mengedit qty');
         }
         $namaKaryawan = (string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan));

         if (is_string($itemsRaw)) {
            $itemsIn = json_decode($itemsRaw, true);
         } else {
            $itemsIn = $itemsRaw;
         }
         if (!is_array($itemsIn) || empty($itemsIn)) {
            throw new Exception('Data item tidak valid');
         }

         $qtyById = [];
         foreach ($itemsIn as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
               continue;
            }
            $qty = (float) ($row['qty'] ?? 0);
            if ($qty <= 0) {
               throw new Exception('Qty harus lebih dari 0');
            }
            $qtyById[$id] = $qty;
         }
         if (empty($qtyById)) {
            throw new Exception('Tidak ada item yang diubah');
         }

         $refEsc = $this->db(0)->escape($ref);
         $dbItems = $this->db(0)->get_where(
            'barang_mutasi',
            "ref = '$refEsc' AND type = 2 AND state = 0 AND source_id > 0 AND target_id > 0"
         );
         if (!is_array($dbItems) || empty($dbItems)) {
            throw new Exception('Transfer tidak ditemukan atau sudah diterima');
         }

         $dbById = [];
         foreach ($dbItems as $item) {
            $dbById[(int) ($item['id'] ?? 0)] = $item;
         }

         $changes = [];
         foreach ($qtyById as $id => $newQty) {
            if (!isset($dbById[$id])) {
               throw new Exception('Item #' . $id . ' tidak ada di transfer ini');
            }
            $oldQty = (float) ($dbById[$id]['qty'] ?? 0);
            if (abs($oldQty - $newQty) < 0.00001) {
               continue;
            }
            $changes[] = [
               'id' => $id,
               'id_barang' => (int) ($dbById[$id]['id_barang'] ?? 0),
               'qty_lama' => $oldQty,
               'qty_baru' => $newQty,
            ];
         }
         if (empty($changes)) {
            throw new Exception('Tidak ada perubahan qty');
         }

         if (!$this->db(0)->beginTransaction()) {
            throw new Exception('Gagal memulai transaksi');
         }

         try {
            foreach ($changes as $ch) {
               $id = (int) $ch['id'];
               $upd = $this->db(0)->update(
                  'barang_mutasi',
                  ['qty' => $ch['qty_baru']],
                  "id = $id AND ref = '$refEsc' AND type = 2 AND state = 0"
               );
               if (isset($upd['errno']) && (int) $upd['errno'] !== 0) {
                  throw new Exception($upd['error'] ?? 'Gagal update qty item #' . $id);
               }
            }

            $idUser = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
            $idCabang = (int) ($this->id_cabang ?? 0);
            $log = $this->helper('ActivityLog')->write([
               'modul' => 'delivery',
               'aksi' => 'update_qty_cabang',
               'id_ref' => $ref,
               'ref' => $ref,
               'id_karyawan' => $idKaryawan,
               'nama_karyawan' => strtoupper($namaKaryawan),
               'id_user' => $idUser,
               'id_cabang' => $idCabang,
               'catatan' => 'Edit qty transfer cabang',
               'meta' => [
                  'ref' => $ref,
                  'changes' => $changes,
               ],
            ]);
            if (is_array($log) && isset($log['errno']) && (int) $log['errno'] !== 0) {
               throw new Exception($log['error'] ?? 'Gagal menyimpan log');
            }

            if (!$this->db(0)->commit()) {
               throw new Exception('Gagal commit transaksi');
            }

            $response = [
               'status' => 'success',
               'message' => 'Qty berhasil diupdate',
               'data' => [
                  'ref' => $ref,
                  'updated' => count($changes),
                  'id_karyawan' => $idKaryawan,
               ],
            ];
         } catch (\Throwable $e) {
            $this->db(0)->rollback();
            throw $e;
         }
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response);
   }

   /**
    * Terima Pakai — wajib pilih karyawan penerima.
    * Pakai dicatat atas cabang penerima (target_id transfer), id_user = karyawan penerima.
    */
   public function terima_pakai()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         $ref = trim((string) ($_POST['ref'] ?? ''));
         $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);

         if ($ref === '') {
            throw new Exception('Ref tidak valid');
         }
         if ($idKaryawan < 1) {
            throw new Exception('Pilih karyawan penerima');
         }
         $penerima = $this->db(0)->get_where_row('user', 'id_user = ' . $idKaryawan . ' AND en = 1');
         if (!$penerima) {
            throw new Exception('Karyawan tidak ditemukan');
         }

         $refEsc = $this->db(0)->escape($ref);
         $items = $this->db(0)->get_where(
            'barang_mutasi',
            "ref = '$refEsc' AND type = 2 AND state = 0 AND source_id > 0 AND target_id > 0"
         );
         if (!is_array($items) || empty($items)) {
            throw new Exception('Transfer tidak ditemukan atau sudah diterima');
         }

         $first = $items[0];
         $sourceId = (int) ($first['source_id'] ?? 0);
         $targetId = (int) ($first['target_id'] ?? 0);
         if ($sourceId <= 0) {
            throw new Exception('Terima Pakai hanya untuk barang dari transfer cabang (bukan supplier)');
         }
         if ($targetId <= 0) {
            throw new Exception('Cabang penerima tidak valid');
         }

         $payments = $this->db(0)->get_where('kas', "ref_transaksi = '$refEsc' AND jenis_transaksi = 7");
         if (!empty($payments)) {
            throw new Exception('Tidak dapat Terima Pakai nota yang sudah memiliki riwayat pembayaran');
         }

         if (!$this->db(0)->beginTransaction()) {
            throw new Exception('Gagal memulai transaksi');
         }

         try {
            $update1 = $this->db(0)->update(
               'barang_mutasi',
               ['state' => 1, 'id_user' => $idKaryawan],
               "ref = '$refEsc'"
            );
            if (isset($update1['errno']) && (int) $update1['errno'] !== 0) {
               throw new Exception($update1['error'] ?? 'Gagal terima barang');
            }

            $refPakai = $ref . '_P';
            foreach ($items as $item) {
               $data = [
                  'type' => 3,
                  'ref' => $refPakai,
                  'id_barang' => $item['id_barang'],
                  'source_id' => $targetId,
                  'target_id' => 0,
                  'denom' => $item['denom'] ?? 1,
                  'price' => $item['price'] ?? 0,
                  'qty' => $item['qty'],
                  'margin' => $item['margin'] ?? 0,
                  'state' => 0,
                  'id_user' => $idKaryawan,
               ];
               $insert = $this->db(0)->insert('barang_mutasi', $data);
               if (isset($insert['errno']) && (int) $insert['errno'] !== 0) {
                  throw new Exception($insert['error'] ?? 'Gagal insert pakai: ' . ($item['id_barang'] ?? ''));
               }
            }

            if (!$this->db(0)->commit()) {
               throw new Exception('Gagal commit transaksi');
            }

            $response = [
               'status' => 'success',
               'message' => 'Barang berhasil diterima dan dipakai',
               'data' => ['ref' => $ref, 'target_id' => $targetId, 'id_karyawan' => $idKaryawan],
            ];
         } catch (\Throwable $e) {
            $this->db(0)->rollback();
            throw $e;
         }
      } catch (\Throwable $e) {
         $response = ['status' => 'error', 'message' => $e->getMessage()];
      }

      ob_end_clean();
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($response);
   }

   private function canCekDetail(): bool
   {
      $priv = (int) ($this->id_privilege ?? 0);
      return $priv === 100 || $priv === 12;
   }

   /**
    * Transfer Sales antar cabang yang belum diterima (semua cabang).
    */
   private function getPendingCabangTransfers(): array
   {
      $rows = $this->db(0)->get_where(
         'barang_mutasi',
         'type = 2 AND state = 0 AND source_id > 0 AND target_id > 0 ORDER BY created_at DESC, id DESC'
      );
      if (!is_array($rows)) {
         $rows = [];
      }

      $cabangMap = $this->buildCabangMap();
      $grouped = [];

      foreach ($rows as $item) {
         $ref = $item['ref'] ?? '';
         if ($ref === '') {
            continue;
         }
         if (!isset($grouped[$ref])) {
            $sourceId = (int) ($item['source_id'] ?? 0);
            $targetId = (int) ($item['target_id'] ?? 0);
            $grouped[$ref] = [
               'ref' => $ref,
               'date' => $item['created_at'] ?? '',
               'source_id' => $sourceId,
               'target_id' => $targetId,
               'source_kode' => $cabangMap[$sourceId] ?? ('#' . $sourceId),
               'target_kode' => $cabangMap[$targetId] ?? ('#' . $targetId),
               'item_count' => 0,
            ];
         }
         $grouped[$ref]['item_count']++;
      }

      return array_values($grouped);
   }

   /**
    * Conversation CRM case 2 (Pickup/Delivery) yang masih open — semua cabang.
    * Sumber: mdl_main.wa_conversations via db(100).
    */
   private function getPendingCustomerDeliveries(): array
   {
      $rows = $this->db(100)->query_array(
         "SELECT id, wa_number, contact_name, COALESCE(code, '00') AS kode_cabang, conv_case, last_message_at
          FROM wa_conversations
          WHERE (
            (conv_case LIKE '%\"case\":2%' AND conv_case LIKE '%\"status\":\"open\"%')
            OR (conv_case LIKE '%\"case\":\"2\"%' AND conv_case LIKE '%\"status\":\"open\"%')
          )
          ORDER BY last_message_at DESC
          LIMIT 200"
      );
      if (!is_array($rows)) {
         return [];
      }

      $out = [];
      foreach ($rows as $row) {
         if (!$this->conversationHasOpenCase2($row['conv_case'] ?? '')) {
            continue;
         }
         $digits = preg_replace('/[^0-9]/', '', (string) ($row['wa_number'] ?? ''));
         $phoneTail = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
         if ($phoneTail === '') {
            continue;
         }
         $nama = trim((string) ($row['contact_name'] ?? ''));
         $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'nama' => strtoupper($nama !== '' ? $nama : 'Customer'),
            'phone_tail' => $phoneTail,
            'kode_cabang' => strtoupper((string) ($row['kode_cabang'] ?? '00')),
            'last_message_at' => $row['last_message_at'] ?? '',
         ];
      }
      return $out;
   }

   /**
    * Request kurir customer (delivery_request berjalan) — semua cabang.
    */
   private function getPendingCustomerRequests(): array
   {
      $rows = $this->db(0)->query_array(
         "SELECT r.*, p.nama_pelanggan, p.nomor_pelanggan
          FROM delivery_request r
          LEFT JOIN pelanggan p ON p.id_pelanggan = r.id_pelanggan
          WHERE r.delivery_status = 'berjalan'
          ORDER BY r.insertTime DESC
          LIMIT 200"
      );
      if (!is_array($rows)) {
         return [];
      }

      $cabangMap = $this->buildCabangMap();
      $requestIds = [];
      foreach ($rows as $row) {
         $idRequest = (int) ($row['id_request'] ?? 0);
         if ($idRequest > 0) {
            $requestIds[$idRequest] = $idRequest;
         }
      }

      $itemsByRequest = [];
      if (!empty($requestIds)) {
         $reqIn = implode(',', array_map('intval', array_values($requestIds)));
         $allItems = $this->db(0)->get_where(
            'delivery_request_item',
            'id_request IN (' . $reqIn . ')'
         );
         if (is_array($allItems)) {
            foreach ($allItems as $it) {
               $rid = (int) ($it['id_request'] ?? 0);
               $sid = (int) ($it['id_penjualan'] ?? 0);
               if ($rid > 0 && $sid > 0) {
                  $itemsByRequest[$rid][] = $sid;
               }
            }
         }
      }

      $surcasBoundSet = [];
      if (!empty($requestIds)) {
         $reqIn = implode(',', array_map('intval', array_values($requestIds)));
         $scRows = $this->db(0)->query_array(
            'SELECT DISTINCT id_delivery_request FROM surcas
             WHERE id_delivery_request IN (' . $reqIn . ')'
         );
         if (is_array($scRows)) {
            foreach ($scRows as $sc) {
               $rid = (int) ($sc['id_delivery_request'] ?? 0);
               if ($rid > 0) {
                  $surcasBoundSet[$rid] = true;
               }
            }
         }
      }

      $out = [];
      foreach ($rows as $row) {
         $idRequest = (int) ($row['id_request'] ?? 0);
         if ($idRequest <= 0) {
            continue;
         }
         $jenis = strtolower((string) ($row['jenis'] ?? ''));
         $idCabang = (int) ($row['id_cabang'] ?? 0);
         $nama = trim((string) ($row['nama_pelanggan'] ?? ''));
         $phoneTail = preg_replace('/[^0-9]/', '', (string) ($row['phone_tail'] ?? ''));
         if (strlen($phoneTail) >= 9) {
            $phoneTail = substr($phoneTail, -9);
         }
         $nomorRaw = trim((string) ($row['nomor_pelanggan'] ?? ''));
         $phoneDisplay = $this->formatPhoneDisplay($nomorRaw !== '' ? $nomorRaw : $phoneTail);

         $prefillIds = $itemsByRequest[$idRequest] ?? [];

         $out[] = [
            'id_request' => $idRequest,
            'nama' => strtoupper($nama !== '' ? $nama : 'Customer'),
            'phone_tail' => $phoneTail,
            'phone_display' => $phoneDisplay,
            'id_pelanggan' => (int) ($row['id_pelanggan'] ?? 0),
            'jenis' => $jenis,
            'layanan' => (string) ($row['layanan'] ?? 'sameday'),
            'kode_cabang' => $cabangMap[$idCabang] ?? ('#' . $idCabang),
            'insertTime' => $row['insertTime'] ?? '',
            'prefill_ids' => $prefillIds,
            'surcas_bound' => !empty($surcasBoundSet[$idRequest]),
            'lokasi_nama' => (string) ($row['lokasi_nama'] ?? ''),
            'lokasi_detail' => (string) ($row['lokasi_detail'] ?? ''),
            'catatan_kurir' => (string) ($row['catatan_kurir'] ?? ''),
            'lokasi_latt' => isset($row['lokasi_latt']) ? (float) $row['lokasi_latt'] : null,
            'lokasi_longt' => isset($row['lokasi_longt']) ? (float) $row['lokasi_longt'] : null,
            'tarif_surcas' => isset($row['tarif_surcas']) && $row['tarif_surcas'] !== null
               ? (int) $row['tarif_surcas']
               : null,
            'courier_name' => (string) ($row['courier_name'] ?? ''),
            'ongkir' => isset($row['ongkir']) ? (int) $row['ongkir'] : null,
            'biteship_status' => (string) ($row['biteship_status'] ?? ''),
            'tracking_url' => (string) ($row['tracking_url'] ?? ''),
            'driver_name' => (string) ($row['driver_name'] ?? ''),
            'driver_phone' => (string) ($row['driver_phone'] ?? ''),
            'waybill_id' => (string) ($row['waybill_id'] ?? ''),
         ];
      }
      return $this->enrichCustomerRequestsReadiness($out);
   }

   /**
    * Hitung kesiapan selesai per delivery_request (lokasi + item siap).
    */
   private function enrichCustomerRequestsReadiness(array $requests): array
   {
      if (empty($requests)) {
         return [];
      }

      $eligibleByRequest = $this->batchEligibleSaleIdsByRequest($requests);
      $allAntarIdsForSelesai = [];
      foreach ($requests as $rq) {
         if (strtolower((string) ($rq['jenis'] ?? '')) !== 'antar') {
            continue;
         }
         $idReq = (int) ($rq['id_request'] ?? 0);
         foreach ($eligibleByRequest[$idReq] ?? [] as $sid) {
            $allAntarIdsForSelesai[$sid] = $sid;
         }
      }
      $selesaiSet = $this->saleIdsWithLaundrySelesai(array_values($allAntarIdsForSelesai));

      foreach ($requests as &$rq) {
         $jenis = strtolower((string) ($rq['jenis'] ?? ''));
         $layanan = strtolower((string) ($rq['layanan'] ?? 'sameday'));
         $isInstant = $layanan === 'instant';
         $idReq = (int) ($rq['id_request'] ?? 0);

         $lokNama = trim((string) ($rq['lokasi_nama'] ?? ''));
         $lokDetail = trim((string) ($rq['lokasi_detail'] ?? ''));
         $lokLatt = (float) ($rq['lokasi_latt'] ?? 0);
         $lokLongt = (float) ($rq['lokasi_longt'] ?? 0);
         $hasLokasi = ($lokNama !== '' || $lokDetail !== '' || ($lokLatt != 0.0 && $lokLongt != 0.0));

         $siapCount = 0;
         $belumCount = 0;
         foreach ($eligibleByRequest[$idReq] ?? [] as $sid) {
            if ($jenis === 'antar' && !isset($selesaiSet[$sid])) {
               $belumCount++;
            } else {
               $siapCount++;
            }
         }

         $siapSelesai = false;
         $blockHint = '';
         if ($isInstant && $jenis === 'antar') {
            $blockHint = 'Instant · track only';
         } elseif ($jenis === 'antar' && !$isInstant && !$hasLokasi) {
            $blockHint = 'Lokasi belum lengkap';
         } elseif ($siapCount <= 0 && $belumCount > 0) {
            $blockHint = 'Laundry belum selesai';
         } elseif ($siapCount <= 0) {
            $blockHint = 'Belum ada item siap';
         }

         if ($jenis === 'antar' && !$isInstant) {
            $siapSelesai = $hasLokasi && $siapCount > 0;
         } elseif ($jenis === 'jemput') {
            $siapSelesai = $siapCount > 0;
         }

         $rq['has_lokasi'] = $hasLokasi;
         $rq['siap_item_count'] = $siapCount;
         $rq['belum_item_count'] = $belumCount;
         $rq['siap_selesai'] = $siapSelesai;
         $rq['block_hint'] = $blockHint;
      }
      unset($rq);

      return $requests;
   }

   /**
    * Batch: eligible id_penjualan per request (ganti N× fetchEligibleSaleRows).
    * @return array<int,int[]>
    */
   private function batchEligibleSaleIdsByRequest(array $requests): array
   {
      $requestMeta = [];
      $pelangganIds = [];
      foreach ($requests as $rq) {
         $idReq = (int) ($rq['id_request'] ?? 0);
         if ($idReq <= 0) {
            continue;
         }
         $idPel = (int) ($rq['id_pelanggan'] ?? 0);
         $jenis = strtolower((string) ($rq['jenis'] ?? ''));
         $requestMeta[$idReq] = ['id_pelanggan' => $idPel, 'jenis' => $jenis];
         if ($idPel > 0) {
            $pelangganIds[$idPel] = $idPel;
         }
      }

      $result = [];
      foreach (array_keys($requestMeta) as $idReq) {
         $result[$idReq] = [];
      }
      if (empty($pelangganIds)) {
         return $result;
      }

      $idsIn = implode(',', array_map('intval', array_values($pelangganIds)));
      $sales = $this->db(0)->query_array(
         "SELECT s.id_penjualan, s.id_pelanggan
          FROM sale s
          WHERE s.bin = 0
            AND s.id_pelanggan IN ($idsIn)
            AND (
              s.tuntas = 0
              OR (s.tuntas = 1 AND s.tuntasTime IS NOT NULL AND s.tuntasTime >= (NOW() - INTERVAL 2 DAY))
            )
          ORDER BY s.insertTime DESC, s.id_penjualan DESC
          LIMIT 5000"
      );
      if (!is_array($sales)) {
         $sales = [];
      }

      $saleIds = [];
      $salesByPelanggan = [];
      foreach ($sales as $s) {
         $sid = (int) ($s['id_penjualan'] ?? 0);
         $pid = (int) ($s['id_pelanggan'] ?? 0);
         if ($sid <= 0 || $pid <= 0) {
            continue;
         }
         $saleIds[$sid] = $sid;
         $salesByPelanggan[$pid][] = $sid;
      }

      $riwayatMap = [];
      if (!empty($saleIds)) {
         $saleIn = implode(',', array_map('intval', array_values($saleIds)));
         $riwayatRows = $this->db(0)->query_array(
            "SELECT id_penjualan, jenis FROM delivery_riwayat
             WHERE id_penjualan IN ($saleIn)"
         );
         if (is_array($riwayatRows)) {
            foreach ($riwayatRows as $r) {
               $sid = (int) ($r['id_penjualan'] ?? 0);
               $j = strtolower((string) ($r['jenis'] ?? ''));
               if ($sid > 0 && $j !== '') {
                  $riwayatMap[$sid][$j] = true;
               }
            }
         }
      }

      $bindingMap = [];
      if (!empty($saleIds)) {
         $saleIn = implode(',', array_map('intval', array_values($saleIds)));
         $bindRows = $this->db(0)->query_array(
            "SELECT dri.id_penjualan, dri.id_request, drq.jenis
             FROM delivery_request_item dri
             INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
             WHERE dri.id_penjualan IN ($saleIn)
               AND drq.delivery_status = 'berjalan'"
         );
         if (is_array($bindRows)) {
            foreach ($bindRows as $r) {
               $sid = (int) ($r['id_penjualan'] ?? 0);
               if ($sid <= 0) {
                  continue;
               }
               $bindingMap[$sid][] = [
                  'id_request' => (int) ($r['id_request'] ?? 0),
                  'jenis' => strtolower((string) ($r['jenis'] ?? '')),
               ];
            }
         }
      }

      foreach ($requestMeta as $idReq => $meta) {
         $idPel = (int) ($meta['id_pelanggan'] ?? 0);
         $jenis = (string) ($meta['jenis'] ?? '');
         if ($idPel <= 0 || !in_array($jenis, ['jemput', 'antar'], true)) {
            continue;
         }
         $eligible = [];
         foreach ($salesByPelanggan[$idPel] ?? [] as $sid) {
            if (!empty($riwayatMap[$sid][$jenis])) {
               continue;
            }
            $blocked = false;
            foreach ($bindingMap[$sid] ?? [] as $bind) {
               if (($bind['jenis'] ?? '') === $jenis && (int) ($bind['id_request'] ?? 0) !== $idReq) {
                  $blocked = true;
                  break;
               }
            }
            if (!$blocked) {
               $eligible[] = $sid;
            }
         }
         $result[$idReq] = $eligible;
      }

      return $result;
   }

   /**
    * Insert delivery_riwayat untuk daftar id_penjualan eligible.
    * @throws Exception
    */
   private function insertDeliveryRiwayatBatch(
      string $phoneTail,
      array $pelangganIds,
      string $jenis,
      array $ids,
      int $idKaryawan,
      string $namaKaryawan,
      int $idCabang,
      string $now,
      int $exceptRequestId = 0,
      bool $includeBound = false
   ): int {
      $eligibleMap = [];
      foreach ($this->fetchEligibleSaleRows($pelangganIds, $jenis, $exceptRequestId, false, $includeBound) as $row) {
         $eligibleMap[(int) $row['id_penjualan']] = $row;
      }

      $selesaiSet = [];
      if ($jenis === 'antar' && !empty($eligibleMap)) {
         $selesaiSet = $this->saleIdsWithLaundrySelesai(array_keys($eligibleMap));
      }

      $inserted = 0;
      foreach ($ids as $idPenjualan) {
         $idPenjualan = (int) $idPenjualan;
         if (!isset($eligibleMap[$idPenjualan])) {
            throw new Exception("Item #$idPenjualan tidak eligible atau sudah ada riwayat $jenis");
         }
         if ($jenis === 'antar' && !isset($selesaiSet[$idPenjualan])) {
            throw new Exception("Item #$idPenjualan belum selesai — tidak bisa diantar");
         }
         $sale = $eligibleMap[$idPenjualan];
         $data = [
            'phone_tail' => $phoneTail,
            'id_pelanggan' => (int) ($sale['id_pelanggan'] ?? 0),
            'id_penjualan' => $idPenjualan,
            'no_ref' => (string) ($sale['no_ref'] ?? ''),
            'jenis' => $jenis,
            'id_karyawan' => $idKaryawan,
            'nama_karyawan' => strtoupper($namaKaryawan),
            'id_cabang' => $idCabang,
            'id_user' => $idKaryawan,
            'insertTime' => $now,
         ];
         $ins = $this->db(0)->insert('delivery_riwayat', $data);
         if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
            throw new Exception($ins['error'] ?? 'Gagal menyimpan riwayat');
         }
         $inserted++;
      }
      return $inserted;
   }

   /**
    * id_penjualan yang sudah punya delivery_riwayat untuk jenis tertentu.
    * @param int[] $idPenjualans
    * @return array<int,true>
    */
   private function saleIdsWithDeliveryRiwayat(array $idPenjualans, string $jenis): array
   {
      $safe = [];
      foreach ($idPenjualans as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $safe[$id] = $id;
         }
      }
      if (empty($safe)) {
         return [];
      }
      $jenisEsc = $this->db(0)->escape($jenis);
      $rows = $this->db(0)->query_array(
         'SELECT DISTINCT id_penjualan FROM delivery_riwayat
          WHERE jenis = \'' . $jenisEsc . '\'
            AND id_penjualan IN (' . implode(',', $safe) . ')'
      );
      $out = [];
      if (is_array($rows)) {
         foreach ($rows as $r) {
            $sid = (int) ($r['id_penjualan'] ?? 0);
            if ($sid > 0) {
               $out[$sid] = true;
            }
         }
      }
      return $out;
   }

   /**
    * Binding item ke delivery_request berjalan untuk jenis tertentu (request terbaru dulu).
    * @param int[] $idPenjualans
    * @return array<int, array{id_request:int, tarif_surcas:?int, layanan:string}>
    */
   private function saleBindingsToRunningRequest(array $idPenjualans, string $jenis): array
   {
      $safe = [];
      foreach ($idPenjualans as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $safe[$id] = $id;
         }
      }
      if (empty($safe)) {
         return [];
      }
      $jenisEsc = $this->db(0)->escape($jenis);
      $rows = $this->db(0)->query_array(
         "SELECT dri.id_penjualan, drq.id_request, drq.tarif_surcas, drq.layanan
          FROM delivery_request_item dri
          INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
          WHERE dri.id_penjualan IN (" . implode(',', $safe) . ")
            AND drq.jenis = '$jenisEsc'
            AND drq.delivery_status IN ('berjalan','menunggu_pembayaran')
          ORDER BY drq.id_request DESC"
      );
      $out = [];
      if (is_array($rows)) {
         foreach ($rows as $r) {
            $sid = (int) ($r['id_penjualan'] ?? 0);
            if ($sid <= 0 || isset($out[$sid])) {
               continue;
            }
            $tarifRaw = $r['tarif_surcas'] ?? null;
            $out[$sid] = [
               'id_request' => (int) ($r['id_request'] ?? 0),
               'tarif_surcas' => ($tarifRaw === null || $tarifRaw === '') ? null : (int) $tarifRaw,
               'layanan' => strtolower((string) ($r['layanan'] ?? 'sameday')),
            ];
         }
      }
      return $out;
   }

   /**
    * Tarif sameday yang mengunci surcas (null = tidak terkunci). 0 = gratis.
    * Instant diabaikan (pakai ongkir, bukan surcas nota).
    * @param int[] $ids
    */
   private function lockedTarifSurcasForSaleIds(array $ids, string $jenis): ?int
   {
      $map = $this->saleBindingsToRunningRequest($ids, $jenis);
      foreach ($ids as $id) {
         $b = $map[(int) $id] ?? null;
         if (!$b) {
            continue;
         }
         if (($b['layanan'] ?? '') === 'instant') {
            continue;
         }
         if (!array_key_exists('tarif_surcas', $b) || $b['tarif_surcas'] === null) {
            continue;
         }
         return (int) $b['tarif_surcas'];
      }
      return null;
   }

   /**
    * @param array{id_request?:int, tarif_surcas?:mixed, layanan?:string}|null $binding
    */
   private function bindingLockTarif(?array $binding): ?int
   {
      if (!$binding || (($binding['layanan'] ?? '') === 'instant')) {
         return null;
      }
      if (!array_key_exists('tarif_surcas', $binding) || $binding['tarif_surcas'] === null) {
         return null;
      }
      return (int) $binding['tarif_surcas'];
   }

   /**
    * id_penjualan yang sudah punya notif selesai laundry (tipe=2, no_ref=id_penjualan).
    * @param int[] $idPenjualans
    * @return array<int,true>
    */
   private function saleIdsWithLaundrySelesai(array $idPenjualans): array
   {
      $safe = [];
      foreach ($idPenjualans as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $safe[$id] = $id;
         }
      }
      if (empty($safe)) {
         return [];
      }
      $inList = [];
      foreach ($safe as $id) {
         $inList[] = "'" . $this->db(0)->escape((string) $id) . "'";
      }
      $rows = $this->db(0)->query_array(
         'SELECT DISTINCT no_ref FROM notif WHERE tipe = 2 AND no_ref IN (' . implode(',', $inList) . ')'
      );
      $out = [];
      if (is_array($rows)) {
         foreach ($rows as $r) {
            $sid = (int) ($r['no_ref'] ?? 0);
            if ($sid > 0) {
               $out[$sid] = true;
            }
         }
      }
      return $out;
   }

   private function conversationHasOpenCase2($raw): bool
   {
      if (!is_string($raw) || $raw === '') {
         return false;
      }
      $trim = trim($raw);
      if ($trim === '' || ($trim[0] !== '{' && $trim[0] !== '[')) {
         return false;
      }
      $json = json_decode($trim, true);
      if (!is_array($json)) {
         return false;
      }
      $list = isset($json[0]) ? $json : (isset($json['case']) ? [$json] : []);
      foreach ($list as $item) {
         if (!is_array($item)) {
            continue;
         }
         $case = (int) ($item['case'] ?? 0);
         $status = (string) ($item['status'] ?? 'open');
         if ($case === 2 && $status !== 'closed') {
            return true;
         }
      }
      return false;
   }

   private function buildCabangMap(): array
   {
      $cabangList = $this->getCabangOperasional();
      $map = [];
      if (is_array($cabangList)) {
         foreach ($cabangList as $cb) {
            $id = (int) ($cb['id_cabang'] ?? 0);
            if ($id > 0) {
               $map[$id] = strtoupper((string) ($cb['kode_cabang'] ?? $id));
            }
         }
      }
      return $map;
   }

   private function itemWithBarangMeta($item)
   {
      if (!is_array($item)) {
         $item = [];
      }
      $idEsc = $this->db(0)->escape($item['id_barang'] ?? '');
      $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '$idEsc'");
      if (!is_array($barang)) {
         $barang = [];
      }
      $item['nama_barang'] = $barang['nama'] ?? strtoupper(trim(($barang['brand'] ?? '') . ' ' . ($barang['model'] ?? '')));
      $item['deskripsi_barang'] = isset($barang['description']) ? trim((string) $barang['description']) : '';
      $unitNama = '';
      if (!empty($barang['unit'])) {
         $uid = $this->db(0)->escape($barang['unit']);
         $unit = $this->db(0)->get_where_row('barang_unit', "id = '$uid'");
         $unitNama = $unit['nama'] ?? '';
      }
      $item['unit_nama'] = $unitNama;
      return $item;
   }

   /**
    * Nomor HP untuk tampilan (08…). 9 digit / 62… dilengkapi, tanpa mengubah data match.
    */
   private function formatPhoneDisplay(string $raw): string
   {
      $digits = preg_replace('/[^0-9]/', '', $raw);
      if ($digits === '') {
         return trim($raw);
      }
      if (strpos($digits, '62') === 0 && strlen($digits) >= 10) {
         $digits = '0' . substr($digits, 2);
      } elseif ($digits[0] !== '0') {
         $digits = '0' . $digits;
      }
      return $digits;
   }

   /**
    * Resolve group Fonnte untuk share lokasi: cabang | delivery.
    */
   private function resolveShareFonnteGroupId(string $target, int $idCabang): string
   {
      $this->helper('FonnteService');
      if ($target === 'delivery') {
         return FonnteService::driverGroupId();
      }

      $cabangRow = null;
      if ($idCabang > 0) {
         $cabangRow = $this->db(0)->get_where_row('cabang', 'id_cabang = ' . $idCabang);
      }
      return FonnteService::cabangGroupId(is_array($cabangRow) ? $cabangRow : null);
   }

   /**
    * Koordinat lokasi pelanggan untuk hitung tarif ongkir.
    *
    * @return array{latt:float,longt:float,nama:string}|null
    */
   private function resolveLokasiCoordsForTarif(int $idPelanggan, int $idRequest = 0): ?array
   {
      if ($idRequest > 0) {
         $req = $this->db(0)->get_where_row('delivery_request', 'id_request = ' . $idRequest);
         if (is_array($req) && !empty($req['id_request'])) {
            if ($idPelanggan <= 0) {
               $idPelanggan = (int) ($req['id_pelanggan'] ?? 0);
            }
            $lat = (float) ($req['lokasi_latt'] ?? 0);
            $lon = (float) ($req['lokasi_longt'] ?? 0);
            if ($lat != 0.0 || $lon != 0.0) {
               $nama = trim((string) ($req['lokasi_nama'] ?? ''));
               return [
                  'latt' => $lat,
                  'longt' => $lon,
                  'nama' => $nama !== '' ? $nama : 'Lokasi request',
               ];
            }
            $idLok = (int) ($req['id_lokasi'] ?? 0);
            if ($idLok > 0 && $idPelanggan > 0) {
               $fromLok = $this->lokasiCoordsRow($idLok, $idPelanggan);
               if ($fromLok !== null) {
                  return $fromLok;
               }
            }
         }
      }

      if ($idPelanggan <= 0) {
         return null;
      }

      $rows = $this->db(0)->get_where_order(
         'pelanggan_lokasi',
         'id_pelanggan = ' . $idPelanggan,
         'id_lokasi DESC'
      );
      if (!is_array($rows)) {
         return null;
      }
      foreach ($rows as $r) {
         $lat = (float) ($r['latt'] ?? 0);
         $lon = (float) ($r['longt'] ?? 0);
         if ($lat != 0.0 || $lon != 0.0) {
            $nama = trim((string) ($r['nama'] ?? ''));
            return [
               'latt' => $lat,
               'longt' => $lon,
               'nama' => $nama !== '' ? $nama : 'Lokasi pelanggan',
            ];
         }
      }

      return null;
   }

   /**
    * @return array{latt:float,longt:float,nama:string}|null
    */
   private function lokasiCoordsRow(int $idLokasi, int $idPelanggan): ?array
   {
      $lok = $this->db(0)->get_where_row(
         'pelanggan_lokasi',
         'id_lokasi = ' . $idLokasi . ' AND id_pelanggan = ' . $idPelanggan
      );
      if (!is_array($lok) || empty($lok['id_lokasi'])) {
         return null;
      }
      $lat = (float) ($lok['latt'] ?? 0);
      $lon = (float) ($lok['longt'] ?? 0);
      if ($lat == 0.0 && $lon == 0.0) {
         return null;
      }
      $nama = trim((string) ($lok['nama'] ?? ''));
      return [
         'latt' => $lat,
         'longt' => $lon,
         'nama' => $nama !== '' ? $nama : 'Lokasi pelanggan',
      ];
   }

   /**
    * Prefill lokasi seperti intent MINTA_JEMPUT_ANTAR:
    * 1 lokasi tersimpan → pakai itu; >1 → lokasi delivery selesai terakhir; else lokasi tersimpan terbaru.
    */
   private function pickDefaultLokasiForRequest(int $idPelanggan): ?array
   {
      $idPelanggan = (int) $idPelanggan;
      if ($idPelanggan <= 0) {
         return null;
      }
      $rows = $this->db(0)->get_where_order(
         'pelanggan_lokasi',
         'id_pelanggan = ' . $idPelanggan,
         'id_lokasi DESC'
      );
      if (!is_array($rows) || empty($rows)) {
         return null;
      }
      $list = array_values($rows);
      if (count($list) === 1) {
         return $list[0];
      }

      $lastOk = $this->db(0)->get_where_row(
         'delivery_request',
         'id_pelanggan = ' . $idPelanggan
            . " AND delivery_status = 'selesai'"
            . ' AND id_lokasi IS NOT NULL AND id_lokasi > 0'
            . ' ORDER BY COALESCE(selesaiTime, insertTime) DESC, id_request DESC'
      );
      if (is_array($lastOk)) {
         $targetId = (int) ($lastOk['id_lokasi'] ?? 0);
         if ($targetId > 0) {
            foreach ($list as $lok) {
               if ((int) ($lok['id_lokasi'] ?? 0) === $targetId) {
                  return $lok;
               }
            }
         }
      }

      return $list[0];
   }

   /**
    * @return array{id_lokasi:int,lokasi_nama:string,lokasi_detail:string,lokasi_latt:float,lokasi_longt:float}
    */
   private function lokasiFieldsForDeliveryRequest(array $lok): array
   {
      return [
         'id_lokasi' => (int) ($lok['id_lokasi'] ?? 0),
         'lokasi_nama' => (string) ($lok['nama'] ?? ''),
         'lokasi_detail' => (string) ($lok['detail'] ?? ''),
         'lokasi_latt' => isset($lok['latt']) ? (float) $lok['latt'] : 0,
         'lokasi_longt' => isset($lok['longt']) ? (float) $lok['longt'] : 0,
      ];
   }

   private function defaultLokasiFieldsForRequest(int $idPelanggan): array
   {
      $lok = $this->pickDefaultLokasiForRequest($idPelanggan);
      if ($lok === null) {
         return [
            'id_lokasi' => 0,
            'lokasi_nama' => '',
            'lokasi_detail' => '',
            'lokasi_latt' => 0,
            'lokasi_longt' => 0,
         ];
      }
      return $this->lokasiFieldsForDeliveryRequest($lok);
   }

   private function requestLokasiIsEmpty(?array $req): bool
   {
      if (!is_array($req) || empty($req)) {
         return true;
      }
      if ((int) ($req['id_lokasi'] ?? 0) > 0) {
         return false;
      }
      if (trim((string) ($req['lokasi_nama'] ?? '')) !== '') {
         return false;
      }
      $lat = (float) ($req['lokasi_latt'] ?? 0);
      $lon = (float) ($req['lokasi_longt'] ?? 0);
      return $lat == 0.0 && $lon == 0.0;
   }

   /**
    * id_pelanggan yang nomornya match 9 digit terakhir.
    */
   private function pelangganIdsByPhoneTail(string $phoneTail): array
   {
      $tailEsc = $this->db(0)->escape($phoneTail);
      $digitsExpr = "REPLACE(REPLACE(REPLACE(COALESCE(nomor_pelanggan,''), '+', ''), '-', ''), ' ', '')";
      $rows = $this->db(0)->query_array(
         "SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan
          FROM pelanggan
          WHERE RIGHT($digitsExpr, 9) = '$tailEsc'
             OR RIGHT($digitsExpr, 8) = '" . $this->db(0)->escape(substr($phoneTail, -8)) . "'
          ORDER BY id_pelanggan DESC
          LIMIT 50"
      );
      if (!is_array($rows)) {
         return [];
      }
      $ids = [];
      foreach ($rows as $r) {
         $id = (int) ($r['id_pelanggan'] ?? 0);
         if ($id > 0) {
            $ids[$id] = $id;
         }
      }
      return array_values($ids);
   }

   private function fetchEligibleSaleRows(
      array $pelangganIds,
      string $jenis,
      int $exceptRequestId = 0,
      bool $includeDeliveredMissingSurcas = false,
      bool $includeBound = false,
      bool $includeAllActive = false
   ): array {
      if (empty($pelangganIds)) {
         return [];
      }
      $idsIn = implode(',', array_map('intval', $pelangganIds));
      $jenisEsc = $this->db(0)->escape($jenis);
      $exceptClause = '';
      if ($exceptRequestId > 0) {
         $exceptClause = ' AND dri.id_request <> ' . (int) $exceptRequestId;
      }

      // Default: belum ada riwayat + tidak terikat request berjalan.
      $boundClause = $includeBound ? '' : "
            AND NOT EXISTS (
              SELECT 1
              FROM delivery_request_item dri
              INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
              WHERE dri.id_penjualan = s.id_penjualan
                AND drq.jenis = '$jenisEsc'
                AND drq.delivery_status = 'berjalan'
                $exceptClause
            )";
      $eligibilityClause = "
            AND NOT EXISTS (
              SELECT 1 FROM delivery_riwayat dr
              WHERE dr.id_penjualan = s.id_penjualan AND dr.jenis = '$jenisEsc'
            )
            $boundClause";

      // Operasi jemput: semua item nota aktif (terikat / sudah riwayat tetap tampil), seperti Antar.
      if ($includeAllActive) {
         $eligibilityClause = '';
      }

      // Operasi antar: semua item nota aktif yang belum punya surcas pengantaran
      // (termasuk sudah delivered / terikat request — supaya bisa backfill surcas).
      if ($includeDeliveredMissingSurcas && $jenis === 'antar') {
         $this->helper('AntarTarif');
         $jenisSurcas = (int) AntarTarif::SURCAS_JENIS_PENGANTARAN;
         $eligibilityClause = "
            AND NOT EXISTS (
              SELECT 1 FROM surcas sc
              WHERE sc.transaksi_jenis = 1
                AND sc.id_jenis_surcas = $jenisSurcas
                AND CAST(sc.no_ref AS CHAR) = CAST(s.no_ref AS CHAR)
            )";
      }

      $rows = $this->db(0)->query_array(
         "SELECT s.*
          FROM sale s
          WHERE s.bin = 0
            AND s.id_pelanggan IN ($idsIn)
            AND (
              s.tuntas = 0
              OR (s.tuntas = 1 AND s.tuntasTime IS NOT NULL AND s.tuntasTime >= (NOW() - INTERVAL 2 DAY))
            )
            $eligibilityClause
          ORDER BY s.insertTime DESC, s.id_penjualan DESC
          LIMIT 300"
      );
      return is_array($rows) ? $rows : [];
   }

   private function buildEligibleSalesOrders(
      array $pelangganIds,
      string $jenis,
      int $exceptRequestId = 0,
      bool $includeDeliveredMissingSurcas = false,
      bool $includeBound = false,
      bool $includeAllActive = false
   ): array {
      $rows = $this->fetchEligibleSaleRows(
         $pelangganIds,
         $jenis,
         $exceptRequestId,
         $includeDeliveredMissingSurcas,
         $includeBound,
         $includeAllActive
      );
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
               'surcas_penjemputan' => null,
               'surcas_pengantaran' => null,
            ];
         }
         $qty = round((float) ($a['qty'] ?? 0), 2);
         $min = round((float) ($a['min_order'] ?? 0), 2);
         $satuan = $mapSatuan[$a['id_penjualan_jenis'] ?? 0] ?? '';
         $qtyShow = rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') . $satuan;
         if ($qty < $min && $min > 0) {
            $qtyShow .= ' (Min.' . rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ',') . ')';
         }
         $orders[$ref]['items'][] = [
            'id' => (int) $a['id_penjualan'],
            'id_pelanggan' => (int) ($a['id_pelanggan'] ?? 0),
            'kategori' => $mapKategori[$a['id_item_group'] ?? 0] ?? '',
            'durasi' => strtoupper((string) ($mapDurasi[$a['id_durasi'] ?? 0] ?? '')),
            'qty_show' => $qtyShow,
            'tuntas' => (int) ($a['tuntas'] ?? 0),
            'tuntasTime' => $a['tuntasTime'] ?? '',
            'member' => (int) ($a['member'] ?? 0),
            'belum_selesai' => false,
            'sudah_delivered' => false,
            'terikat' => false,
            'tarif_surcas' => null,
            'terikat_antar' => false,
            'tarif_surcas_antar' => null,
         ];
      }

      // Antar: tandai item tanpa notif selesai (tipe=2) — tampil terkunci di UI
      if ($jenis === 'antar' && !empty($orders)) {
         $allIds = [];
         foreach ($orders as $ord) {
            foreach ($ord['items'] as $it) {
               $allIds[] = (int) $it['id'];
            }
         }
         $selesaiSet = $this->saleIdsWithLaundrySelesai($allIds);
         $deliveredSet = $includeDeliveredMissingSurcas
            ? $this->saleIdsWithDeliveryRiwayat($allIds, 'antar')
            : [];
         $boundMap = $this->saleBindingsToRunningRequest($allIds, 'antar');
         foreach ($orders as $refKey => $ord) {
            foreach ($ord['items'] as $ix => $it) {
               $sid = (int) ($it['id'] ?? 0);
               $belum = $sid > 0 && !isset($selesaiSet[$sid]);
               $orders[$refKey]['items'][$ix]['belum_selesai'] = $belum;
               $orders[$refKey]['items'][$ix]['sudah_delivered'] = $sid > 0 && isset($deliveredSet[$sid]);
               $bind = $boundMap[$sid] ?? null;
               $orders[$refKey]['items'][$ix]['terikat'] = $bind !== null;
               $orders[$refKey]['items'][$ix]['tarif_surcas'] = $this->bindingLockTarif($bind);
            }
            // Siap dulu, lalu belum selesai
            usort($orders[$refKey]['items'], static function ($a, $b) {
               $ba = !empty($a['belum_selesai']) ? 1 : 0;
               $bb = !empty($b['belum_selesai']) ? 1 : 0;
               if ($ba !== $bb) {
                  return $ba <=> $bb;
               }
               return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            });
         }
      }

      // Operasi jemput: tandai item terikat request / sudah ada riwayat jemput
      if ($includeAllActive && $jenis === 'jemput' && !empty($orders)) {
         $allIds = [];
         foreach ($orders as $ord) {
            foreach ($ord['items'] as $it) {
               $allIds[] = (int) $it['id'];
            }
         }
         $boundMap = $this->saleBindingsToRunningRequest($allIds, 'jemput');
         $antarBoundMap = $this->saleBindingsToRunningRequest($allIds, 'antar');
         $deliveredSet = $this->saleIdsWithDeliveryRiwayat($allIds, 'jemput');
         foreach ($orders as $refKey => $ord) {
            foreach ($ord['items'] as $ix => $it) {
               $sid = (int) ($it['id'] ?? 0);
               $bind = $boundMap[$sid] ?? null;
               $bindAntar = $antarBoundMap[$sid] ?? null;
               $orders[$refKey]['items'][$ix]['terikat'] = $bind !== null;
               $orders[$refKey]['items'][$ix]['tarif_surcas'] = $this->bindingLockTarif($bind);
               $orders[$refKey]['items'][$ix]['terikat_antar'] = $bindAntar !== null;
               $orders[$refKey]['items'][$ix]['tarif_surcas_antar'] = $this->bindingLockTarif($bindAntar);
               $orders[$refKey]['items'][$ix]['sudah_delivered'] = $sid > 0 && isset($deliveredSet[$sid]);
            }
         }
      }

      if (!empty($orders)) {
         $this->helper('AntarTarif');
         $idCabang = (int) ($this->id_cabang ?? 0);
         $refs = array_keys($orders);
         $safe = [];
         foreach ($refs as $rk) {
            $safe[] = "'" . $this->db(0)->escape((string) $rk) . "'";
         }
         if (!empty($safe)) {
            $refsIn = implode(',', $safe);
            $jenisList = [
               AntarTarif::SURCAS_JENIS_PENJEMPUTAN,
               AntarTarif::SURCAS_JENIS_PENGANTARAN,
            ];
            $scRows = $this->db(0)->get_where(
               'surcas',
               'id_cabang = ' . $idCabang
                  . ' AND transaksi_jenis = 1 AND id_jenis_surcas IN (' . implode(',', $jenisList) . ')'
                  . " AND no_ref IN ($refsIn)"
            );
            if (is_array($scRows)) {
               foreach ($scRows as $sc) {
                  $r = (string) ($sc['no_ref'] ?? '');
                  if ($r === '' || !isset($orders[$r])) {
                     continue;
                  }
                  $jid = (int) ($sc['id_jenis_surcas'] ?? 0);
                  $amt = (int) ($sc['jumlah'] ?? 0);
                  if ($jid === AntarTarif::SURCAS_JENIS_PENJEMPUTAN) {
                     $orders[$r]['surcas_penjemputan'] = $amt;
                  } elseif ($jid === AntarTarif::SURCAS_JENIS_PENGANTARAN) {
                     $orders[$r]['surcas_pengantaran'] = $amt;
                  }
               }
            }
         }
      }

      return array_values($orders);
   }

   /**
    * Gabung request portal/chat per phone_tail (untuk board Delivery).
    * Hanya delivery_request — case CRM tidak lagi ditampilkan di board.
    */
   private function buildCustomerDeliveryGroups(array $customerRequests): array
   {
      $groups = [];

      foreach ($customerRequests as $rq) {
         $tail = (string) ($rq['phone_tail'] ?? '');
         if ($tail === '') {
            continue;
         }
         if (!isset($groups[$tail])) {
            $groups[$tail] = [
               'phone_tail' => $tail,
               'phone_display' => (string) ($rq['phone_display'] ?? $tail),
               'nama' => (string) ($rq['nama'] ?? 'Customer'),
               'kode_cabang' => (string) ($rq['kode_cabang'] ?? '00'),
               'crm' => null,
               'requests' => [],
               'sort_time' => (string) ($rq['insertTime'] ?? ''),
            ];
         }
         $groups[$tail]['requests'][] = $rq;
         $groups[$tail]['nama'] = (string) ($rq['nama'] ?? $groups[$tail]['nama']);
         $groups[$tail]['kode_cabang'] = (string) ($rq['kode_cabang'] ?? $groups[$tail]['kode_cabang']);
         $disp = trim((string) ($rq['phone_display'] ?? ''));
         if ($disp !== '') {
            $groups[$tail]['phone_display'] = $disp;
         }
         $t = (string) ($rq['insertTime'] ?? '');
         if ($t !== '' && $t > ($groups[$tail]['sort_time'] ?? '')) {
            $groups[$tail]['sort_time'] = $t;
         }
      }

      $list = array_values($groups);
      usort($list, static function ($a, $b) {
         return strcmp((string) ($b['sort_time'] ?? ''), (string) ($a['sort_time'] ?? ''));
      });
      return $list;
   }

   /**
    * Jumlah delivery_request aktif (berjalan / menunggu bayar) untuk phone_tail.
    */
   private function countActiveDeliveryRequestsByPhoneTail(string $phoneTail): int
   {
      $phoneTail = preg_replace('/[^0-9]/', '', $phoneTail);
      if (strlen($phoneTail) >= 9) {
         $phoneTail = substr($phoneTail, -9);
      }
      if ($phoneTail === '') {
         return 0;
      }
      $esc = $this->db(0)->escape($phoneTail);
      return (int) ($this->db(0)->count_where(
         'delivery_request',
         "phone_tail = '" . $esc . "'"
            . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
      ) ?? 0);
   }

   /**
    * Tutup case CRM 2 hanya jika tidak ada request portal aktif tersisa.
    * @return bool true jika case CRM 2 benar-benar ditutup
    */
   private function maybeCloseCrmCase2ByPhoneTail(string $phoneTail, int $userId = 0): bool
   {
      if ($this->countActiveDeliveryRequestsByPhoneTail($phoneTail) > 0) {
         return false;
      }
      return $this->closeCrmCase2ByPhoneTail($phoneTail, $userId);
   }

   /**
    * @return bool true jika ada case 2 yang ditutup
    */
   private function closeCrmCase2ByPhoneTail(string $phoneTail, int $userId = 0): bool
   {
      $tailEsc = $this->db(100)->escape($phoneTail);
      $rows = $this->db(100)->query_array(
         "SELECT id, wa_number, conv_case
          FROM wa_conversations
          WHERE RIGHT(REPLACE(REPLACE(REPLACE(wa_number, '+', ''), '-', ''), ' ', ''), 9) = '$tailEsc'
          LIMIT 10"
      );
      if (!is_array($rows)) {
         return false;
      }

      $anyClosed = false;
      foreach ($rows as $row) {
         if (!$this->conversationHasOpenCase2($row['conv_case'] ?? '')) {
            continue;
         }
         $raw = $row['conv_case'] ?? '';
         $list = [];
         $trim = trim((string) $raw);
         if ($trim !== '' && ($trim[0] === '[' || $trim[0] === '{')) {
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) {
               $list = isset($decoded[0]) ? $decoded : (isset($decoded['case']) ? [$decoded] : []);
            }
         }
         $modified = false;
         $hasOpen = false;
         foreach ($list as &$item) {
            if (!is_array($item)) {
               continue;
            }
            $case = (int) ($item['case'] ?? 0);
            $status = (string) ($item['status'] ?? 'open');
            if ($case === 2 && $status !== 'closed') {
               $item['status'] = 'closed';
               $modified = true;
            }
            if ((int) ($item['case'] ?? 0) > 0 && ($item['status'] ?? 'open') !== 'closed') {
               $hasOpen = true;
            }
         }
         unset($item);
         if (!$modified) {
            continue;
         }
         $anyClosed = true;
         $json = json_encode(array_values($list));
         $id = (int) ($row['id'] ?? 0);
         $wa = $this->db(100)->escape((string) ($row['wa_number'] ?? ''));
         if ($id > 0) {
            $this->db(100)->update('wa_conversations', ['conv_case' => $json], "id = '$id'");
         } else {
            $this->db(100)->update('wa_conversations', ['conv_case' => $json], "wa_number = '$wa'");
         }

         $phone = (string) ($row['wa_number'] ?? '');
         $this->pushDeliveryCaseResolved($phone, $userId, !$hasOpen);
      }
      return $anyClosed;
   }

   private function pushDeliveryCaseResolved(string $phone, int $userId, bool $allClosed): void
   {
      if ($phone === '') {
         return;
      }
      $payload = [
         'type' => 'case_resolved',
         'phone' => $phone,
         'case' => 2,
         'target_id' => '0',
         'sender_id' => (string) $userId,
         'all_closed' => $allClosed,
      ];
      try {
         $url = 'http://127.0.0.1:3003/incoming';
         $ch = curl_init($url);
         curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
         curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
         curl_setopt($ch, CURLOPT_TIMEOUT, 3);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
         curl_exec($ch);
         curl_close($ch);
      } catch (\Throwable $e) {
         // ignore WS failure
      }
   }

   /**
    * Satu no_ref dari id_penjualan terpilih (urut id ASC).
    * @param int[] $ids
    * @return string|null
    */
   private function pickRefFromSaleIds(array $ids): ?string
   {
      $safe = [];
      foreach ($ids as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $safe[] = $id;
         }
      }
      if (empty($safe)) {
         return null;
      }
      $rows = $this->db(0)->get_where(
         'sale',
         'bin = 0 AND id_penjualan IN (' . implode(',', $safe) . ') ORDER BY id_penjualan ASC'
      );
      if (!is_array($rows) || empty($rows)) {
         return null;
      }
      $noRef = trim((string) ($rows[0]['no_ref'] ?? ''));
      return $noRef !== '' ? $noRef : null;
   }

   /**
    * Insert Surcas Penjemputan (jenis 3) ke satu ref dari ids.
    * Jika sudah ada di ref yang sama → skip (tidak insert, tidak update).
    * Menandai dari_delivery=1 (+ id_delivery_request bila ada).
    *
    * @param int[] $ids
    * @return array{no_ref:string,jumlah:int,updated:bool,skipped:bool}
    * @throws Exception
    */
   private function upsertSurcasPenjemputan(
      int $idCabang,
      array $ids,
      int $jumlah,
      int $idUser,
      int $idDeliveryRequest = 0
   ): array {
      $jumlah = (int) $jumlah;
      $noRef = $this->pickRefFromSaleIds($ids);
      if ($noRef === null || $noRef === '') {
         throw new Exception('Tidak ada ref dari item yang dipilih');
      }

      $this->helper('AntarTarif');
      $jenis = AntarTarif::SURCAS_JENIS_PENJEMPUTAN;
      $noRefEsc = $this->db(0)->escape($noRef);
      $where = 'id_cabang = ' . (int) $idCabang
         . " AND transaksi_jenis = 1 AND id_jenis_surcas = $jenis"
         . " AND no_ref = '" . $noRefEsc . "'";

      $existing = $this->db(0)->get_where_row('surcas', $where);
      if (is_array($existing) && !empty($existing['id_surcas'])) {
         return [
            'no_ref' => $noRef,
            'jumlah' => (int) ($existing['jumlah'] ?? 0),
            'updated' => false,
            'skipped' => true,
         ];
      }

      if ($jumlah < 0) {
         throw new Exception('Isi Surcas Penjemputan (isi 0 untuk gratis)');
      }

      $set = [
         'jumlah' => $jumlah,
         'id_user' => (int) $idUser,
         'dari_delivery' => 1,
      ];
      if ($idDeliveryRequest > 0) {
         $set['id_delivery_request'] = $idDeliveryRequest;
      }

      $ins = $this->db(0)->insert('surcas', array_merge([
         'id_cabang' => (int) $idCabang,
         'transaksi_jenis' => 1,
         'id_jenis_surcas' => $jenis,
         'no_ref' => is_numeric($noRef) ? (0 + $noRef) : $noRef,
      ], $set));
      if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
         throw new Exception($ins['error'] ?? 'Gagal insert surcas penjemputan');
      }
      return ['no_ref' => $noRef, 'jumlah' => $jumlah, 'updated' => false, 'skipped' => false];
   }

   /**
    * Insert Surcas Pengantaran (jenis 2) ke satu ref dari ids.
    * Jika sudah ada di ref yang sama → skip (tidak insert, tidak update).
    *
    * @param int[] $ids
    * @return array{no_ref:string,jumlah:int,updated:bool,skipped:bool}
    * @throws Exception
    */
   private function upsertSurcasPengantaran(
      int $idCabang,
      array $ids,
      int $jumlah,
      int $idUser,
      int $idDeliveryRequest = 0
   ): array {
      $jumlah = (int) $jumlah;
      $noRef = $this->pickRefFromSaleIds($ids);
      if ($noRef === null || $noRef === '') {
         throw new Exception('Tidak ada ref dari item yang dipilih');
      }

      $this->helper('AntarTarif');
      $jenis = AntarTarif::SURCAS_JENIS_PENGANTARAN;
      $noRefEsc = $this->db(0)->escape($noRef);
      $where = 'id_cabang = ' . (int) $idCabang
         . " AND transaksi_jenis = 1 AND id_jenis_surcas = $jenis"
         . " AND no_ref = '" . $noRefEsc . "'";

      $existing = $this->db(0)->get_where_row('surcas', $where);
      if (is_array($existing) && !empty($existing['id_surcas'])) {
         return [
            'no_ref' => $noRef,
            'jumlah' => (int) ($existing['jumlah'] ?? 0),
            'updated' => false,
            'skipped' => true,
         ];
      }

      if ($jumlah < 0) {
         throw new Exception('Isi Surcas Pengantaran (isi 0 untuk gratis)');
      }

      $set = [
         'jumlah' => $jumlah,
         'id_user' => (int) $idUser,
         'dari_delivery' => 1,
      ];
      if ($idDeliveryRequest > 0) {
         $set['id_delivery_request'] = $idDeliveryRequest;
      }

      $ins = $this->db(0)->insert('surcas', array_merge([
         'id_cabang' => (int) $idCabang,
         'transaksi_jenis' => 1,
         'id_jenis_surcas' => $jenis,
         'no_ref' => is_numeric($noRef) ? (0 + $noRef) : $noRef,
      ], $set));
      if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
         throw new Exception($ins['error'] ?? 'Gagal insert surcas pengantaran');
      }
      return ['no_ref' => $noRef, 'jumlah' => $jumlah, 'updated' => false, 'skipped' => false];
   }

   /**
    * Seed delivery_request Antar dari selesai CRM (bukan portal request).
    * Ambil lokasi terakhir pelanggan bila ada.
    *
    * @param int[] $pelangganIds
    */
   private function buildCrmAntarKembaliSeed(array $pelangganIds, string $phoneTail, int $idCabang): array
   {
      $safe = [];
      foreach ($pelangganIds as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $safe[$id] = $id;
         }
      }
      $safe = array_values($safe);
      $idPelanggan = (int) ($safe[0] ?? 0);
      $lokasi = null;
      foreach ($safe as $pid) {
         $found = $this->pickDefaultLokasiForRequest((int) $pid);
         if ($found !== null) {
            $lokasi = $found;
            $idPelanggan = (int) ($found['id_pelanggan'] ?? $pid);
            break;
         }
      }
      if ($idPelanggan <= 0) {
         throw new Exception('Pelanggan tidak ditemukan untuk Request Antar kembali');
      }
      return [
         'id_pelanggan' => $idPelanggan,
         'id_cabang' => $idCabang,
         'phone_tail' => $phoneTail,
         'id_lokasi' => (int) ($lokasi['id_lokasi'] ?? 0),
         'lokasi_nama' => (string) ($lokasi['nama'] ?? ''),
         'lokasi_detail' => (string) ($lokasi['detail'] ?? ''),
         'lokasi_latt' => isset($lokasi['latt']) ? (float) $lokasi['latt'] : 0,
         'lokasi_longt' => isset($lokasi['longt']) ? (float) $lokasi['longt'] : 0,
      ];
   }

   /**
    * Request delivery aktif (berjalan / menunggu bayar) per pelanggan + jenis.
    */
   private function findActiveDeliveryRequest(int $idPelanggan, string $jenis): ?array
   {
      $idPelanggan = (int) $idPelanggan;
      $jenis = strtolower(trim($jenis));
      if ($idPelanggan <= 0 || !in_array($jenis, ['jemput', 'antar'], true)) {
         return null;
      }
      $row = $this->db(0)->get_where_row(
         'delivery_request',
         'id_pelanggan = ' . $idPelanggan
            . " AND jenis = '" . $this->db(0)->escape($jenis) . "'"
            . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
            . " AND layanan = 'sameday'"
            . ' ORDER BY id_request DESC'
      );
      if (!is_array($row) || empty($row['id_request'])) {
         return null;
      }
      return $row;
   }

   /**
    * Tutup request aktif sebagai selesai (dari Operasi Kurir).
    */
   private function closeActiveDeliveryRequest(
      int $idRequest,
      int $idKaryawan,
      string $namaKaryawan,
      string $now
   ): void {
      $idRequest = (int) $idRequest;
      if ($idRequest <= 0) {
         return;
      }
      $upd = $this->db(0)->update(
         'delivery_request',
         [
            'delivery_status' => 'selesai',
            'id_karyawan' => (int) $idKaryawan,
            'nama_karyawan' => strtoupper($namaKaryawan),
            'selesaiTime' => $now,
         ],
         'id_request = ' . $idRequest . " AND delivery_status IN ('berjalan','menunggu_pembayaran')"
      );
      if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
         throw new Exception($upd['error'] ?? 'Gagal menutup request delivery');
      }
   }

   /**
    * Tambah item ke delivery_request_item (skip yang sudah ada).
    * @param int[] $ids
    */
   private function attachSaleItemsToRequest(int $idRequest, int $idPelanggan, array $ids): void
   {
      $idRequest = (int) $idRequest;
      $idPelanggan = (int) $idPelanggan;
      if ($idRequest <= 0 || $idPelanggan <= 0 || empty($ids)) {
         return;
      }
      $safe = [];
      foreach ($ids as $id) {
         $id = (int) $id;
         if ($id > 0) {
            $safe[$id] = $id;
         }
      }
      $safe = array_values($safe);
      if (empty($safe)) {
         return;
      }

      $existing = [];
      $rowsEx = $this->db(0)->get_where('delivery_request_item', 'id_request = ' . $idRequest);
      if (is_array($rowsEx)) {
         foreach ($rowsEx as $ex) {
            $sid = (int) ($ex['id_penjualan'] ?? 0);
            if ($sid > 0) {
               $existing[$sid] = true;
            }
         }
      }

      $saleRows = $this->db(0)->get_where(
         'sale',
         'bin = 0 AND id_pelanggan = ' . $idPelanggan
            . ' AND id_penjualan IN (' . implode(',', $safe) . ')'
      );
      if (!is_array($saleRows)) {
         return;
      }
      foreach ($saleRows as $sr) {
         $idSale = (int) ($sr['id_penjualan'] ?? 0);
         if ($idSale <= 0 || isset($existing[$idSale])) {
            continue;
         }
         $itemIns = $this->db(0)->insert('delivery_request_item', [
            'id_request' => $idRequest,
            'id_penjualan' => $idSale,
            'no_ref' => (string) ($sr['no_ref'] ?? ''),
         ]);
         if (is_array($itemIns) && isset($itemIns['errno']) && (int) $itemIns['errno'] !== 0) {
            throw new Exception($itemIns['error'] ?? 'Gagal menyimpan item request');
         }
      }
   }

   /**
    * Pastikan ada request antar: ikat yang aktif atau buat baru.
    * @return array{id_request:int,bound:bool}
    */
   private function ensureAntarRequestBound(
      int $idPelanggan,
      int $idCabang,
      string $phoneTail,
      int $tarifSurcas,
      int $fromJemputId,
      string $catatan
   ): array {
      $existing = $this->findActiveDeliveryRequest($idPelanggan, 'antar');
      if (is_array($existing) && !empty($existing['id_request'])) {
         $idRequest = (int) $existing['id_request'];
         $updSet = [
            'catatan_kurir' => mb_substr($catatan !== '' ? $catatan : 'Diikat dari Operasi (Kurir)', 0, 150),
         ];
         $existingTarif = $existing['tarif_surcas'] ?? null;
         if ($existingTarif === null || $existingTarif === '') {
            $updSet['tarif_surcas'] = max(0, (int) $tarifSurcas);
         }
         if ($this->requestLokasiIsEmpty($existing)) {
            $updSet = array_merge($updSet, $this->defaultLokasiFieldsForRequest($idPelanggan));
         }
         $upd = $this->db(0)->update(
            'delivery_request',
            $updSet,
            'id_request = ' . $idRequest
         );
         if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
            throw new Exception($upd['error'] ?? 'Gagal mengikat request antar');
         }
         return ['id_request' => $idRequest, 'bound' => true];
      }

      $seed = array_merge([
         'id_pelanggan' => $idPelanggan,
         'id_cabang' => $idCabang,
         'phone_tail' => $phoneTail,
      ], $this->defaultLokasiFieldsForRequest($idPelanggan));
      $idNew = $this->createAntarKembaliRequest($seed, $tarifSurcas, $fromJemputId);
      if ($idNew <= 0) {
         throw new Exception('Gagal membuat request antar kembali');
      }
      if ($catatan !== '') {
         $this->db(0)->update(
            'delivery_request',
            ['catatan_kurir' => mb_substr($catatan, 0, 150)],
            'id_request = ' . $idNew
         );
      }
      return ['id_request' => $idNew, 'bound' => false];
   }

   /**
    * Buat delivery_request Antar baru (menunggu laundry selesai) dari request jemput.
    * @return int id_request baru
    */
   private function createAntarKembaliRequest(array $jemputReq, int $tarifSurcas, int $fromJemputId): int
   {
      $idPelanggan = (int) ($jemputReq['id_pelanggan'] ?? 0);
      $idCabang = (int) ($jemputReq['id_cabang'] ?? 0);
      $phoneTail = preg_replace('/[^0-9]/', '', (string) ($jemputReq['phone_tail'] ?? ''));
      if (strlen($phoneTail) >= 9) {
         $phoneTail = substr($phoneTail, -9);
      }
      if ($idPelanggan <= 0 || $idCabang <= 0 || $phoneTail === '') {
         throw new Exception('Data request jemput tidak lengkap untuk Antar kembali');
      }

      $now = date('Y-m-d H:i:s');
      $catatan = $fromJemputId > 0
         ? 'Antar kembali setelah laundry selesai (dari jemput #' . (int) $fromJemputId . ')'
         : 'Antar kembali setelah laundry selesai (dari jemput CRM)';
      $catatan = mb_substr($catatan, 0, 150);
      $idLokasi = (int) ($jemputReq['id_lokasi'] ?? 0);
      $data = [
         'sumber' => 'customer',
         'jenis' => 'antar',
         'layanan' => 'sameday',
         'delivery_status' => 'berjalan',
         'id_pelanggan' => $idPelanggan,
         'phone_tail' => $phoneTail,
         'id_cabang' => $idCabang,
         'lokasi_nama' => (string) ($jemputReq['lokasi_nama'] ?? ''),
         'lokasi_detail' => (string) ($jemputReq['lokasi_detail'] ?? ''),
         'lokasi_latt' => isset($jemputReq['lokasi_latt']) ? (float) $jemputReq['lokasi_latt'] : 0,
         'lokasi_longt' => isset($jemputReq['lokasi_longt']) ? (float) $jemputReq['lokasi_longt'] : 0,
         'insertTime' => $now,
         'tarif_surcas' => max(0, (int) $tarifSurcas),
         'catatan_kurir' => $catatan,
      ];
      if ($idLokasi > 0) {
         $data['id_lokasi'] = $idLokasi;
      }
      $ins = $this->db(0)->insert('delivery_request', $data);
      if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
         throw new Exception($ins['error'] ?? 'Gagal insert request Antar kembali');
      }
      return (int) ($ins['insert_id'] ?? 0);
   }
}
