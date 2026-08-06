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
      $canCekDetail = $this->canCekDetail();
      $listCabang = $this->getCabangOperasional();

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('delivery/index', [
         'data_operasi' => $data_operasi,
         'transfers' => $transfers,
         'customers' => $customers,
         'canCekDetail' => $canCekDetail,
         'listCabang' => is_array($listCabang) ? $listCabang : [],
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
      if (strlen($phoneTail) < 9) {
         echo json_encode(['status' => 'error', 'message' => 'Nomor tidak valid']);
         return;
      }
      $phoneTail = substr($phoneTail, -9);
      $jenis = strtolower(trim((string) ($_GET['jenis'] ?? '')));
      if (!in_array($jenis, ['jemput', 'antar'], true)) {
         echo json_encode(['status' => 'error', 'message' => 'Pilih jenis jemput/antar']);
         return;
      }

      $pelangganIds = $this->pelangganIdsByPhoneTail($phoneTail);
      if (empty($pelangganIds)) {
         echo json_encode([
            'status' => 'success',
            'data' => ['orders' => [], 'pelanggan_ids' => []],
            'message' => 'Pelanggan tidak ditemukan',
         ]);
         return;
      }

      $orders = $this->buildEligibleSalesOrders($pelangganIds, $jenis);
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

         if (strlen($phoneTail) < 9) {
            throw new Exception('Nomor tidak valid');
         }
         $phoneTail = substr($phoneTail, -9);
         if (!in_array($jenis, ['jemput', 'antar'], true)) {
            throw new Exception('Jenis harus jemput atau antar');
         }
         if ($idKaryawan <= 0) {
            throw new Exception('Pilih karyawan');
         }
         if (empty($ids)) {
            throw new Exception('Pilih minimal satu item penjualan');
         }

         $karyawan = $this->db(0)->get_where_row('user', "id_user = '$idKaryawan'");
         if (!$karyawan) {
            // fallback nama dari session lists
            $namaKaryawan = '';
            foreach (array_merge($this->user ?? [], $this->userCabang ?? []) as $u) {
               if ((int) ($u['id_user'] ?? 0) === $idKaryawan) {
                  $namaKaryawan = (string) ($u['nama_user'] ?? '');
                  break;
               }
            }
            if ($namaKaryawan === '') {
               throw new Exception('Karyawan tidak ditemukan');
            }
         } else {
            $namaKaryawan = (string) ($karyawan['nama_user'] ?? ('#' . $idKaryawan));
         }

         $pelangganIds = $this->pelangganIdsByPhoneTail($phoneTail);
         if (empty($pelangganIds)) {
            throw new Exception('Pelanggan tidak ditemukan untuk nomor ini');
         }

         $eligibleMap = [];
         foreach ($this->fetchEligibleSaleRows($pelangganIds, $jenis) as $row) {
            $eligibleMap[(int) $row['id_penjualan']] = $row;
         }

         $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
         $idCabang = (int) ($this->id_cabang ?? 0);
         $idUser = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
         $inserted = 0;

         foreach ($ids as $idPenjualan) {
            if (!isset($eligibleMap[$idPenjualan])) {
               throw new Exception("Item #$idPenjualan tidak eligible atau sudah ada riwayat $jenis");
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
               'id_user' => $idUser,
               'insertTime' => $now,
            ];
            $ins = $this->db(0)->insert('delivery_riwayat', $data);
            if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
               throw new Exception($ins['error'] ?? 'Gagal menyimpan riwayat');
            }
            $inserted++;
         }

         $this->closeCrmCase2ByPhoneTail($phoneTail, $idUser);

         $response = [
            'status' => 'success',
            'message' => "Delivery $jenis selesai ($inserted item)",
            'data' => [
               'phone_tail' => $phoneTail,
               'jenis' => $jenis,
               'count' => $inserted,
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
    * Detail barang transfer — hanya admin (100) / driver (12).
    */
   public function detail($ref = '')
   {
      header('Content-Type: application/json; charset=utf-8');

      if (!$this->canCekDetail()) {
         echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
         return;
      }

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
    * Ubah source_id seluruh baris transfer — hanya admin (100) / driver (12).
    */
   public function ubah_sumber()
   {
      if (ob_get_length()) {
         ob_clean();
      }
      ob_start();
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
         if (!$this->canCekDetail()) {
            throw new Exception('Akses ditolak');
         }

         $ref = trim((string) ($_POST['ref'] ?? ''));
         $sourceId = (int) ($_POST['source_id'] ?? 0);

         if ($ref === '') {
            throw new Exception('Ref tidak valid');
         }
         if ($sourceId <= 0) {
            throw new Exception('Pilih cabang sumber');
         }

         $refEsc = $this->db(0)->escape($ref);
         $items = $this->db(0)->get_where(
            'barang_mutasi',
            "ref = '$refEsc' AND type = 2 AND state = 0 AND source_id > 0 AND target_id > 0"
         );
         if (!is_array($items) || empty($items)) {
            throw new Exception('Transfer tidak ditemukan atau sudah diterima');
         }

         $targetId = (int) ($items[0]['target_id'] ?? 0);
         if ($sourceId === $targetId) {
            throw new Exception('Cabang sumber tidak boleh sama dengan cabang tujuan');
         }

         $cabang = $this->db(0)->get_where_row('cabang', "id_cabang = '$sourceId'");
         if (!$cabang || !empty($cabang['is_training'])) {
            throw new Exception('Cabang sumber tidak valid');
         }

         $this->db(0)->update(
            'barang_mutasi',
            ['source_id' => $sourceId],
            "ref = '$refEsc' AND type = 2 AND state = 0"
         );

         $verify = $this->db(0)->get_where_row(
            'barang_mutasi',
            "ref = '$refEsc' AND type = 2 AND state = 0"
         );
         if (!$verify || (int) ($verify['source_id'] ?? 0) !== $sourceId) {
            throw new Exception('Gagal mengubah sumber cabang');
         }

         $kode = strtoupper((string) ($cabang['kode_cabang'] ?? $sourceId));
         $response = [
            'status' => 'success',
            'message' => 'Sumber berhasil diubah ke ' . $kode,
            'data' => [
               'ref' => $ref,
               'source_id' => $sourceId,
               'source_kode' => $kode,
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

   private function fetchEligibleSaleRows(array $pelangganIds, string $jenis): array
   {
      if (empty($pelangganIds)) {
         return [];
      }
      $idsIn = implode(',', array_map('intval', $pelangganIds));
      $jenisEsc = $this->db(0)->escape($jenis);
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
          ORDER BY s.insertTime DESC, s.id_penjualan DESC
          LIMIT 300"
      );
      return is_array($rows) ? $rows : [];
   }

   private function buildEligibleSalesOrders(array $pelangganIds, string $jenis): array
   {
      $rows = $this->fetchEligibleSaleRows($pelangganIds, $jenis);
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
         ];
      }
      return array_values($orders);
   }

   private function closeCrmCase2ByPhoneTail(string $phoneTail, int $userId = 0): void
   {
      $tailEsc = $this->db(100)->escape($phoneTail);
      $rows = $this->db(100)->query_array(
         "SELECT id, wa_number, conv_case
          FROM wa_conversations
          WHERE RIGHT(REPLACE(REPLACE(REPLACE(wa_number, '+', ''), '-', ''), ' ', ''), 9) = '$tailEsc'
          LIMIT 10"
      );
      if (!is_array($rows)) {
         return;
      }

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
}
