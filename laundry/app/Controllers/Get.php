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

      $payload = $this->fetchBankAccountsPayload();
      echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   /**
    * Proxy kompatibilitas untuk klien lama. Data rekening hanya boleh berasal
    * dari API backend, supaya tidak ada daftar rekening kedua di Laundry.
    *
    * @return array<string,mixed>
    */
   private function fetchBankAccountsPayload()
   {
      $qrisUrl = URL::QRIS_PUBLIC_URL;
      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'ml.nalju.com'));
      $qrisImageUrl = $scheme . '://' . $host . URL::IN_ASSETS . 'img/qris/qris_1.jpeg';

      // Sumber tunggal rekening ada di API. ApiLoopback memakai API_BASE_URL
      // (di VPS: http://127.0.0.1:8832), sehingga tidak bergantung pada
      // routing domain Laundry yang memang tidak memiliki endpoint /api.
      $apiPaths = [
         ApiLoopback::baseUrl() . '/Payment/BankAccounts/index',
      ];

      foreach ($apiPaths as $apiUrl) {
         $query = http_build_query([
            'qris_url' => $qrisUrl,
            'qris_image_url' => $qrisImageUrl,
         ]);
         $json = $this->httpGetJson($apiUrl . '?' . $query);
         if (!empty($json['ok'])) {
            return $json;
         }
      }

      return [
         'ok' => false,
         'message' => 'Data rekening backend sedang tidak tersedia',
      ];
   }

   /**
    * @return array<string,mixed>|null
    */
   private function httpGetJson($url)
   {
      if (!function_exists('curl_init')) {
         return null;
      }
      $ch = curl_init($url);
      $options = [
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_TIMEOUT => 5,
         CURLOPT_CONNECTTIMEOUT => 3,
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_HTTPHEADER => ApiLoopback::headers($url, ['Accept: application/json']),
      ];
      curl_setopt_array($ch, ApiLoopback::curlOpts($url, $options));
      $raw = curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($raw === false || $code < 200 || $code >= 300) {
         return null;
      }
      $json = json_decode($raw, true);

      return is_array($json) ? $json : null;
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
            $gmaps = trim((string) ($row['gmaps'] ?? ''));
            if ($gmaps === '' && $latt == 0.0 && $long == 0.0) {
               continue;
            }
            $kode = trim((string) ($row['kode_cabang'] ?? ''));
            if ($kode === '') {
               continue;
            }
            $nama = trim((string) ($row['nama'] ?? ''));
            $alamat = (string) ($row['alamat'] ?? '');
            $mapsUrl = $gmaps !== ''
               ? $gmaps
               : ('https://www.google.com/maps?q=' . $latt . ',' . $long);
            $kodeUp = strtoupper($kode);
            $namaUp = strtoupper($nama !== '' ? $nama : 'MADINAH LAUNDRY');
            $message = $namaUp . ' (' . $kodeUp . ")\n" . $alamat . "\n" . $mapsUrl;
            $data[] = [
               'kode_cabang' => $kode,
               'nama' => $nama,
               'alamat' => $alamat,
               'latt' => $latt,
               'long' => $long,
               'gmaps' => $gmaps,
               'maps_url' => $mapsUrl,
               'message' => $message,
            ];
         }
      }

      echo json_encode([
         'ok' => true,
         'data' => $data,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   /**
    * GET /Get/quickReplies — balas cepat kustom CRM (publik)
    */
   public function quickReplies()
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

      $data = [];
      try {
         $rows = $this->db(100)->get_where_order(
            'crm_quick_replies',
            'is_active = 1',
            'sort_order ASC, id ASC'
         );
         if (is_array($rows)) {
            foreach ($rows as $row) {
               $shortcut = trim((string) ($row['shortcut'] ?? ''));
               $title = trim((string) ($row['title'] ?? ''));
               $message = trim((string) ($row['message'] ?? ''));
               if ($shortcut === '' || $title === '' || $message === '') {
                  continue;
               }
               $data[] = [
                  'id' => (int) ($row['id'] ?? 0),
                  'shortcut' => $shortcut,
                  'title' => $title,
                  'message' => $message,
               ];
            }
         }
      } catch (\Throwable $e) {
         http_response_code(500);
         echo json_encode(['ok' => false, 'message' => 'Gagal memuat quick replies', 'data' => []]);
         return;
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
