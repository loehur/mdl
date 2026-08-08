<?php

class Get extends Controller
{
   public function wa_nota($ref)
   {
      echo $this->helper('WAGenerator')->get_nota($ref);
   }

   public function wa_selesai($penjualan)
   {
      echo $this->helper('WAGenerator')->get_selesai($penjualan);
   }

   /**
    * GET /Get/rekening — rekening pembayaran untuk CRM quick reply (publik)
    */
   public function rekening()
   {
      $this->corsJsonHeaders();
      if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
         http_response_code(204);
         return;
      }
      if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
         http_response_code(405);
         echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
         return;
      }

      $qrisUrl = URL::QRIS_PUBLIC_URL;
      $guide = URL::NON_TUNAI_GUIDE;
      $accounts = [];
      $lines = ['QRIS ' . $qrisUrl];
      $ownerName = 'LUHUR GUNAWAN';

      foreach ($guide as $code => $row) {
         $label = trim((string) ($row['label'] ?? $code));
         $number = trim((string) ($row['number'] ?? ''));
         $name = trim((string) ($row['name'] ?? ''));
         if ($number === '') {
            continue;
         }
         if ($name !== '') {
            $ownerName = $name;
         }
         $accounts[] = [
            'code' => (string) $code,
            'label' => $label,
            'number' => $number,
            'name' => $name !== '' ? $name : $ownerName,
         ];
         $lines[] = $label . ' ' . $number;
      }
      $lines[] = 'an. ' . $ownerName;

      echo json_encode([
         'ok' => true,
         'qris_url' => $qrisUrl,
         'accounts' => $accounts,
         'message' => implode("\n", $lines),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   /**
    * GET /Get/lokasi — daftar lokasi cabang untuk CRM quick reply (publik)
    */
   public function lokasi()
   {
      $this->corsJsonHeaders();
      if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
         http_response_code(204);
         return;
      }
      if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
         http_response_code(405);
         echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
         return;
      }

      try {
         $rows = $this->db(0)->get_where_order(
            'cabang',
            '(is_training = 0 OR is_training IS NULL)',
            'kode_cabang ASC'
         );
      } catch (\Throwable $e) {
         http_response_code(500);
         echo json_encode(['ok' => false, 'message' => 'Gagal memuat lokasi', 'data' => []]);
         return;
      }

      $data = [];
      if (is_array($rows)) {
         foreach ($rows as $row) {
            $latt = isset($row['latt']) ? (float) $row['latt'] : 0.0;
            $long = isset($row['long']) ? (float) $row['long'] : 0.0;
            if ($latt == 0.0 && $long == 0.0) {
               continue;
            }
            $kode = trim((string) ($row['kode_cabang'] ?? ''));
            if ($kode === '') {
               continue;
            }
            $data[] = [
               'kode_cabang' => $kode,
               'nama' => trim((string) ($row['nama'] ?? '')),
               'latt' => $latt,
               'long' => $long,
               'maps_url' => 'https://www.google.com/maps?q=' . $latt . ',' . $long,
            ];
         }
      }

      echo json_encode([
         'ok' => true,
         'data' => $data,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   private function corsJsonHeaders()
   {
      header('Access-Control-Allow-Origin: *');
      header('Access-Control-Allow-Methods: GET, OPTIONS');
      header('Access-Control-Allow-Headers: Content-Type');
      header('Content-Type: application/json; charset=utf-8');
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
   }
}
