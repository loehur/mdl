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
      $canCekDetail = $this->canCekDetail();

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('delivery/index', [
         'data_operasi' => $data_operasi,
         'transfers' => $transfers,
         'canCekDetail' => $canCekDetail,
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
            'source_kode' => $cabangMap[$sourceId] ?? ('#' . $sourceId),
            'target_kode' => $cabangMap[$targetId] ?? ('#' . $targetId),
            'items' => $list,
            'total' => $total,
         ],
      ]);
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
