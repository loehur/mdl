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
      $transfers = $this->getPendingCabangTransfers();
      $customers = $this->getPendingCustomerDeliveries();
      $customerRequests = $this->getPendingCustomerRequests();
      $customerGroups = $this->buildCustomerDeliveryGroups($customers, $customerRequests);
      $canCekDetail = $this->canCekDetail();

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('delivery/index', [
         'data_operasi' => $data_operasi,
         'transfers' => $transfers,
         'customers' => $customers,
         'customerRequests' => $customerRequests,
         'customerGroups' => $customerGroups,
         'canCekDetail' => $canCekDetail,
      ]);
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

      $messages = $this->db(100)->query_array(
         "SELECT * FROM (
            SELECT * FROM (
               (SELECT
                   id,
                   text,
                   type,
                   'customer' AS sender,
                   created_at AS time,
                   status,
                   media_id,
                   media_url
                FROM wa_messages_in
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), 9) = '$tailEsc')
               UNION ALL
               (SELECT
                   id,
                   COALESCE(content, '') AS text,
                   type,
                   'me' AS sender,
                   created_at AS time,
                   status,
                   NULL AS media_id,
                   media_url
                FROM wa_messages_out
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), 9) = '$tailEsc'
                  AND COALESCE(`private`, 0) = 0)
            ) AS combined_msgs
            ORDER BY time DESC
            LIMIT 50
         ) AS latest_msgs
         ORDER BY time ASC"
      );
      if (!is_array($messages)) {
         $messages = [];
      }

      $list = [];
      foreach ($messages as $m) {
         $list[] = [
            'sender' => $m['sender'] ?? 'customer',
            'text' => (string) ($m['text'] ?? ''),
            'type' => $m['type'] ?? 'text',
            'time' => $m['time'] ?? '',
            'media_url' => !empty($m['media_url']) ? (string) $m['media_url'] : null,
            'media_id' => !empty($m['media_id']) ? (string) $m['media_id'] : null,
         ];
      }

      $digits = preg_replace('/[^0-9]/', '', (string) ($conv['wa_number'] ?? ''));
      echo json_encode([
         'status' => 'success',
         'data' => [
            'nama' => strtoupper(trim((string) ($conv['contact_name'] ?? '')) !== '' ? trim($conv['contact_name']) : 'Customer'),
            'phone_tail' => substr($digits, -9),
            'kode_cabang' => strtoupper((string) ($conv['kode_cabang'] ?? '00')),
            'messages' => $list,
         ],
      ]);
   }

   /**
    * List sale eligible untuk Selesai Delivery Customer.
    * GET Delivery/sales_options/{phoneTail}?jenis=jemput|antar
    */
   public function sales_options($phoneTail = '')
   {
      header('Content-Type: application/json; charset=utf-8');

      $phoneTail = preg_replace('/[^0-9]/', '', (string) $phoneTail);
      if (strlen($phoneTail) < 8) {
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

      $pelangganIds = $this->pelangganIdsByPhoneTail($phoneTail);
      if (empty($pelangganIds)) {
         echo json_encode([
            'status' => 'success',
            'data' => ['orders' => [], 'pelanggan_ids' => []],
            'message' => 'Pelanggan tidak ditemukan',
         ]);
         return;
      }

      $orders = $this->buildEligibleSalesOrders($pelangganIds, $jenis, $exceptRequestId);
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
    * Semua user boleh; wajib pilih karyawan + Access Key cocok.
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
         $accessKey = trim((string) ($_POST['access_key'] ?? ''));
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

         // Wajib Access Key milik karyawan yang dipilih (semua user boleh submit)
         $karyawan = $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
         if (!$karyawan) {
            throw new Exception('Access Key tidak cocok dengan karyawan yang dipilih');
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
         if ($jenis === 'jemput') {
            $surcasSplit = $this->splitSurcasJenis1To23($idCabang, $ids, $idKaryawan, 0);
            if ($surcasSplit !== null) {
               $surcasJemput = $surcasSplit['surcas_jemput'];
               $surcasAntar = $surcasSplit['surcas_antar'];
            } else {
               $jumlahSurcas = (int) ($_POST['jumlah_surcas_jemput'] ?? 0);
               if ($jumlahSurcas < 0) {
                  throw new Exception('Jumlah Surcas Penjemputan tidak valid');
               }
               $surcasJemput = $this->upsertSurcasPenjemputan(
                  $idCabang,
                  $ids,
                  $jumlahSurcas,
                  $idKaryawan,
                  0
               );
            }
         } elseif ($jenis === 'antar') {
            $surcasSplit = $this->splitSurcasJenis1To23($idCabang, $ids, $idKaryawan, 0);
            if ($surcasSplit !== null) {
               $surcasJemput = $surcasSplit['surcas_jemput'];
               $surcasAntar = $surcasSplit['surcas_antar'];
            }
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

         $this->closeCrmCase2ByPhoneTail($phoneTail, $idKaryawan);

         $msg = "Delivery $jenis selesai ($inserted item)";
         if ($sekalian) {
            $msg .= " + sekalian $jenisSekalian ($insertedSekalian item)";
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
               'crm_closed' => true,
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
    * Wajib karyawan + Access Key + item utama; opsional sekalian lawan jenis.
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
         $accessKey = trim((string) ($_POST['access_key'] ?? ''));
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

         $karyawan = $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
         if (!$karyawan) {
            throw new Exception('Access Key tidak cocok dengan karyawan yang dipilih');
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
         $surcasSplit = null;

         $antarKembali = (int) ($_POST['antar_kembali'] ?? 0) === 1
            && $jenis === 'jemput'
            && $layanan !== 'instant';
         if ($antarKembali && $sekalian) {
            throw new Exception('Pilih salah satu: Sekalian Antar atau Request Antar kembali');
         }

         $hasJenis1 = ($layanan !== 'instant')
            ? $this->getSurcasJenis1ForSaleIds($idCabang, $ids)
            : null;
         if ($hasJenis1 !== null && $jenis === 'jemput' && $sekalian) {
            throw new Exception(
               'Ref punya Surcas gabungan (jenis 1): Request Antar kembali otomatis dibuat. Tidak bisa Sekalian Antar.'
            );
         }

         if ($hasJenis1 !== null && $layanan !== 'instant') {
            $surcasSplit = $this->splitSurcasJenis1To23($idCabang, $ids, $idKaryawan, $idRequest);
            if ($surcasSplit !== null) {
               $surcasJemput = $surcasSplit['surcas_jemput'];
               $surcasAntar = $surcasSplit['surcas_antar'];
            }
         } elseif ($jenis === 'jemput' && $layanan !== 'instant') {
            $jumlahSurcas = (int) ($req['tarif_surcas'] ?? 0);
            if ($jumlahSurcas <= 0) {
               throw new Exception('Tarif surcas penjemputan belum tersedia di request');
            }
            $surcasJemput = $this->upsertSurcasPenjemputan(
               $idCabang,
               $ids,
               $jumlahSurcas,
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
         if ($surcasSplit !== null && $jenis === 'jemput') {
            $jumlahAntar = (int) ($surcasSplit['jumlah_pengantaran'] ?? 0);
            $antarKembaliId = $this->createAntarKembaliRequest($req, $jumlahAntar, $idRequest);
            if ($antarKembaliId <= 0) {
               throw new Exception('Gagal membuat request Antar kembali');
            }
         } elseif ($antarKembali) {
            $jumlahAntar = (int) ($_POST['jumlah_surcas_antar'] ?? -1);
            if ($jumlahAntar < 0) {
               $jumlahAntar = (int) ($req['tarif_surcas'] ?? 0);
            }
            if ($jumlahAntar < 0) {
               throw new Exception('Surcas Pengantaran tidak valid');
            }
            $surcasAntar = $this->upsertSurcasPengantaran(
               $idCabang,
               $ids,
               $jumlahAntar,
               $idKaryawan,
               $idRequest
            );
            $this->deleteSurcasJenis1OnRef($idCabang, (string) ($surcasAntar['no_ref'] ?? ''));
            $antarKembaliId = $this->createAntarKembaliRequest($req, $jumlahAntar, $idRequest);
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
               'surcas_split' => $surcasSplit,
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
         $accessKey = trim((string) ($_POST['access_key'] ?? ''));
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

         $karyawan = $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
         if (!$karyawan) {
            throw new Exception('Access Key tidak cocok dengan karyawan yang dipilih');
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

         // Hapus surcas otomatis yang tertaut request ini (bukan surcas polos Operasi)
         $delSc = $this->db(0)->delete(
            'surcas',
            'dari_delivery = 1 AND id_delivery_request = ' . $idRequest
         );
         if (is_array($delSc) && isset($delSc['errno']) && (int) $delSc['errno'] !== 0) {
            throw new Exception($delSc['error'] ?? 'Gagal menghapus surcas terkait');
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
    * Semua user boleh; wajib karyawan + Access Key + catatan; dicatat ke activity_log.
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
         $accessKey = trim((string) ($_POST['access_key'] ?? ''));
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

         $karyawan = $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
         if (!$karyawan) {
            throw new Exception('Access Key tidak cocok dengan karyawan yang dipilih');
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
    * Semua user boleh buka; authorize wajib karyawan priv 12/100 + Access Key.
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
         $accessKey = trim((string) ($_POST['access_key'] ?? ''));
         $itemsRaw = $_POST['items'] ?? '[]';

         if ($ref === '') {
            throw new Exception('Ref tidak valid');
         }
         if ($idKaryawan < 1) {
            throw new Exception('Pilih karyawan yang mengedit');
         }

         $karyawan = $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
         if (!$karyawan) {
            throw new Exception('Access Key tidak cocok dengan karyawan yang dipilih');
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
    * Terima Pakai — wajib pilih karyawan + Access Key cocok.
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
         $accessKey = trim((string) ($_POST['access_key'] ?? ''));

         if ($ref === '') {
            throw new Exception('Ref tidak valid');
         }
         if ($idKaryawan < 1) {
            throw new Exception('Pilih karyawan penerima');
         }
         $penerima = $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
         if (!$penerima) {
            throw new Exception('Access Key tidak cocok dengan karyawan yang dipilih');
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
         "SELECT r.*, p.nama_pelanggan
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

         $prefillIds = [];
         if ($jenis === 'antar') {
            $items = $this->db(0)->get_where(
               'delivery_request_item',
               'id_request = ' . $idRequest
            );
            if (is_array($items)) {
               foreach ($items as $it) {
                  $sid = (int) ($it['id_penjualan'] ?? 0);
                  if ($sid > 0) {
                     $prefillIds[] = $sid;
                  }
               }
            }
         }

         $out[] = [
            'id_request' => $idRequest,
            'nama' => strtoupper($nama !== '' ? $nama : 'Customer'),
            'phone_tail' => $phoneTail,
            'jenis' => $jenis,
            'layanan' => (string) ($row['layanan'] ?? 'sameday'),
            'kode_cabang' => $cabangMap[$idCabang] ?? ('#' . $idCabang),
            'insertTime' => $row['insertTime'] ?? '',
            'prefill_ids' => $prefillIds,
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
      return $out;
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
      int $exceptRequestId = 0
   ): int {
      $eligibleMap = [];
      foreach ($this->fetchEligibleSaleRows($pelangganIds, $jenis, $exceptRequestId) as $row) {
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

   private function fetchEligibleSaleRows(array $pelangganIds, string $jenis, int $exceptRequestId = 0): array
   {
      if (empty($pelangganIds)) {
         return [];
      }
      $idsIn = implode(',', array_map('intval', $pelangganIds));
      $jenisEsc = $this->db(0)->escape($jenis);
      $exceptClause = '';
      if ($exceptRequestId > 0) {
         $exceptClause = ' AND dri.id_request <> ' . (int) $exceptRequestId;
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
                AND drq.delivery_status = 'berjalan'
                $exceptClause
            )
          ORDER BY s.insertTime DESC, s.id_penjualan DESC
          LIMIT 300"
      );
      return is_array($rows) ? $rows : [];
   }

   private function buildEligibleSalesOrders(array $pelangganIds, string $jenis, int $exceptRequestId = 0): array
   {
      $rows = $this->fetchEligibleSaleRows($pelangganIds, $jenis, $exceptRequestId);
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
               'surcas_jenis_1' => null,
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
         foreach ($orders as $refKey => $ord) {
            foreach ($ord['items'] as $ix => $it) {
               $sid = (int) ($it['id'] ?? 0);
               $belum = $sid > 0 && !isset($selesaiSet[$sid]);
               $orders[$refKey]['items'][$ix]['belum_selesai'] = $belum;
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
            $jenisGabungan = AntarTarif::SURCAS_JENIS_GABUNGAN;
            $scGabungan = $this->db(0)->get_where(
               'surcas',
               'id_cabang = ' . $idCabang
                  . ' AND transaksi_jenis = 1 AND id_jenis_surcas = ' . $jenisGabungan
                  . " AND no_ref IN ($refsIn)"
            );
            if (is_array($scGabungan)) {
               foreach ($scGabungan as $sc) {
                  $r = (string) ($sc['no_ref'] ?? '');
                  if ($r !== '' && isset($orders[$r])) {
                     $orders[$r]['surcas_jenis_1'] = (int) ($sc['jumlah'] ?? 0);
                  }
               }
            }
         }
      }

      if ($jenis === 'jemput' && !empty($orders)) {
         $this->helper('AntarTarif');
         $jenisSc = AntarTarif::SURCAS_JENIS_PENJEMPUTAN;
         $idCabang = (int) ($this->id_cabang ?? 0);
         $refs = array_keys($orders);
         $safe = [];
         foreach ($refs as $rk) {
            $safe[] = "'" . $this->db(0)->escape((string) $rk) . "'";
         }
         if (!empty($safe)) {
            $refsIn = implode(',', $safe);
            $scRows = $this->db(0)->get_where(
               'surcas',
               'id_cabang = ' . $idCabang
                  . ' AND transaksi_jenis = 1 AND id_jenis_surcas = ' . $jenisSc
                  . " AND no_ref IN ($refsIn)"
            );
            if (is_array($scRows)) {
               foreach ($scRows as $sc) {
                  $r = (string) ($sc['no_ref'] ?? '');
                  if ($r !== '' && isset($orders[$r])) {
                     $orders[$r]['surcas_penjemputan'] = (int) ($sc['jumlah'] ?? 0);
                  }
               }
            }
         }
      }

      return array_values($orders);
   }

   /**
    * Gabung CRM + request portal per phone_tail (untuk board Delivery).
    */
   private function buildCustomerDeliveryGroups(array $customers, array $customerRequests): array
   {
      $groups = [];

      foreach ($customers as $cu) {
         $tail = (string) ($cu['phone_tail'] ?? '');
         if ($tail === '') {
            continue;
         }
         if (!isset($groups[$tail])) {
            $groups[$tail] = [
               'phone_tail' => $tail,
               'nama' => (string) ($cu['nama'] ?? 'Customer'),
               'kode_cabang' => (string) ($cu['kode_cabang'] ?? '00'),
               'crm' => null,
               'requests' => [],
               'sort_time' => (string) ($cu['last_message_at'] ?? ''),
            ];
         }
         $groups[$tail]['crm'] = $cu;
         $groups[$tail]['nama'] = (string) ($cu['nama'] ?? $groups[$tail]['nama']);
         $groups[$tail]['kode_cabang'] = (string) ($cu['kode_cabang'] ?? $groups[$tail]['kode_cabang']);
         $t = (string) ($cu['last_message_at'] ?? '');
         if ($t !== '' && $t > ($groups[$tail]['sort_time'] ?? '')) {
            $groups[$tail]['sort_time'] = $t;
         }
      }

      foreach ($customerRequests as $rq) {
         $tail = (string) ($rq['phone_tail'] ?? '');
         if ($tail === '') {
            continue;
         }
         if (!isset($groups[$tail])) {
            $groups[$tail] = [
               'phone_tail' => $tail,
               'nama' => (string) ($rq['nama'] ?? 'Customer'),
               'kode_cabang' => (string) ($rq['kode_cabang'] ?? '00'),
               'crm' => null,
               'requests' => [],
               'sort_time' => (string) ($rq['insertTime'] ?? ''),
            ];
         }
         $groups[$tail]['requests'][] = $rq;
         if (empty($groups[$tail]['crm'])) {
            $groups[$tail]['nama'] = (string) ($rq['nama'] ?? $groups[$tail]['nama']);
            $groups[$tail]['kode_cabang'] = (string) ($rq['kode_cabang'] ?? $groups[$tail]['kode_cabang']);
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
    * Upsert Surcas Penjemputan (jenis 3) ke satu ref dari ids.
    * Menandai dari_delivery=1 (+ id_delivery_request bila ada).
    *
    * @param int[] $ids
    * @return array{no_ref:string,jumlah:int,updated:bool}
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
      if ($jumlah < 0) {
         throw new Exception('Jumlah surcas penjemputan tidak valid');
      }
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
      $set = [
         'jumlah' => $jumlah,
         'id_user' => (int) $idUser,
         'dari_delivery' => 1,
      ];
      if ($idDeliveryRequest > 0) {
         $set['id_delivery_request'] = $idDeliveryRequest;
      }

      if (is_array($existing) && !empty($existing['id_surcas'])) {
         $idSurcas = (int) $existing['id_surcas'];
         $upd = $this->db(0)->update('surcas', $set, 'id_surcas = ' . $idSurcas);
         if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
            throw new Exception($upd['error'] ?? 'Gagal update surcas penjemputan');
         }
         return ['no_ref' => $noRef, 'jumlah' => $jumlah, 'updated' => true];
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
      return ['no_ref' => $noRef, 'jumlah' => $jumlah, 'updated' => false];
   }

   /**
    * Upsert Surcas Pengantaran (jenis 2) ke satu ref dari ids.
    *
    * @param int[] $ids
    * @return array{no_ref:string,jumlah:int,updated:bool}
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
      if ($jumlah < 0) {
         throw new Exception('Jumlah surcas pengantaran tidak valid');
      }
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
      $set = [
         'jumlah' => $jumlah,
         'id_user' => (int) $idUser,
         'dari_delivery' => 1,
      ];
      if ($idDeliveryRequest > 0) {
         $set['id_delivery_request'] = $idDeliveryRequest;
      }

      if (is_array($existing) && !empty($existing['id_surcas'])) {
         $idSurcas = (int) $existing['id_surcas'];
         $upd = $this->db(0)->update('surcas', $set, 'id_surcas = ' . $idSurcas);
         if (is_array($upd) && isset($upd['errno']) && (int) $upd['errno'] !== 0) {
            throw new Exception($upd['error'] ?? 'Gagal update surcas pengantaran');
         }
         return ['no_ref' => $noRef, 'jumlah' => $jumlah, 'updated' => true];
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
      return ['no_ref' => $noRef, 'jumlah' => $jumlah, 'updated' => false];
   }

   /**
    * Surcas jenis 1 (gabungan) pada ref, jika ada.
    */
   private function getSurcasJenis1OnRef(int $idCabang, string $noRef): ?array
   {
      $noRef = trim($noRef);
      if ($noRef === '' || $idCabang <= 0) {
         return null;
      }
      $this->helper('AntarTarif');
      $noRefEsc = $this->db(0)->escape($noRef);
      $where = 'id_cabang = ' . (int) $idCabang
         . ' AND transaksi_jenis = 1 AND id_jenis_surcas = ' . AntarTarif::SURCAS_JENIS_GABUNGAN
         . " AND no_ref = '" . $noRefEsc . "'";
      $row = $this->db(0)->get_where_row('surcas', $where);
      if (!is_array($row) || empty($row['id_surcas'])) {
         return null;
      }
      return $row;
   }

   /**
    * Surcas jenis 1 dari ids penjualan terpilih (satu ref).
    * @param int[] $ids
    */
   private function getSurcasJenis1ForSaleIds(int $idCabang, array $ids): ?array
   {
      $noRef = $this->pickRefFromSaleIds($ids);
      if ($noRef === null || $noRef === '') {
         return null;
      }
      return $this->getSurcasJenis1OnRef($idCabang, $noRef);
   }

   /**
    * Pecah surcas jenis 1 → jenis 2 (pengantaran) + jenis 3 (penjemputan), hapus jenis 1.
    * Input manual surcas diabaikan jika jenis 1 ada.
    *
    * @param int[] $ids
    * @return array{no_ref:string,jumlah_total:int,jumlah_pengantaran:int,jumlah_penjemputan:int,surcas_antar:array,surcas_jemput:array}|null
    * @throws Exception
    */
   private function splitSurcasJenis1To23(
      int $idCabang,
      array $ids,
      int $idUser,
      int $idDeliveryRequest = 0
   ): ?array {
      $noRef = $this->pickRefFromSaleIds($ids);
      if ($noRef === null || $noRef === '') {
         return null;
      }
      $row = $this->getSurcasJenis1OnRef($idCabang, $noRef);
      if ($row === null) {
         return null;
      }
      $total = (int) ($row['jumlah'] ?? 0);
      if ($total < 0) {
         throw new Exception('Jumlah surcas gabungan (jenis 1) tidak valid');
      }
      $jumlahPengantaran = intdiv($total, 2);
      $jumlahPenjemputan = $total - $jumlahPengantaran;

      $surcasAntar = $this->upsertSurcasPengantaran(
         $idCabang,
         $ids,
         $jumlahPengantaran,
         $idUser,
         $idDeliveryRequest
      );
      $surcasJemput = $this->upsertSurcasPenjemputan(
         $idCabang,
         $ids,
         $jumlahPenjemputan,
         $idUser,
         $idDeliveryRequest
      );
      $this->deleteSurcasJenis1OnRef($idCabang, $noRef);

      return [
         'no_ref' => $noRef,
         'jumlah_total' => $total,
         'jumlah_pengantaran' => $jumlahPengantaran,
         'jumlah_penjemputan' => $jumlahPenjemputan,
         'surcas_antar' => $surcasAntar,
         'surcas_jemput' => $surcasJemput,
      ];
   }

   /**
    * Hapus surcas jenis 1 pada ref (hindari dobel dengan jenis pengantaran resmi = 2).
    */
   private function deleteSurcasJenis1OnRef(int $idCabang, string $noRef): void
   {
      $noRef = trim($noRef);
      if ($noRef === '' || $idCabang <= 0) {
         return;
      }
      $this->helper('AntarTarif');
      $noRefEsc = $this->db(0)->escape($noRef);
      $where = 'id_cabang = ' . (int) $idCabang
         . ' AND transaksi_jenis = 1 AND id_jenis_surcas = ' . AntarTarif::SURCAS_JENIS_GABUNGAN
         . " AND no_ref = '" . $noRefEsc . "'";
      $del = $this->db(0)->delete('surcas', $where);
      if (is_array($del) && isset($del['errno']) && (int) $del['errno'] !== 0) {
         throw new Exception($del['error'] ?? 'Gagal menghapus surcas jenis 1');
      }
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
      $catatan = mb_substr(
         'Antar kembali setelah laundry selesai (dari jemput #' . (int) $fromJemputId . ')',
         0,
         150
      );
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
