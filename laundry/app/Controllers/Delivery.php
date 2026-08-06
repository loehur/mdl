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
                   status
                FROM wa_messages_in
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), 9) = '$tailEsc')
               UNION ALL
               (SELECT
                   id,
                   COALESCE(content, '') AS text,
                   type,
                   'me' AS sender,
                   created_at AS time,
                   status
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
         ];
      }

      $digits = preg_replace('/[^0-9]/', '', (string) ($conv['wa_number'] ?? ''));
      echo json_encode([
         'status' => 'success',
         'data' => [
            'nama' => trim((string) ($conv['contact_name'] ?? '')) !== '' ? trim($conv['contact_name']) : 'Customer',
            'phone_tail' => substr($digits, -9),
            'kode_cabang' => strtoupper((string) ($conv['kode_cabang'] ?? '00')),
            'messages' => $list,
         ],
      ]);
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
            'nama' => $nama !== '' ? $nama : 'Customer',
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
}
