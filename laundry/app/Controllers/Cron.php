<?php

class Cron extends Controller
{
   public function send()
   {
      $pending = 0;
      $expire = 0;
      $sent = 0;
      $csw_closed = 0;
      $where = "state = 'pending' ORDER BY insertTime ASC";
      $data_pending = '';

      // Query notif dari db(0)
      $data = $this->db(0)->get_where('notif', $where);
      
      // Query wa_conversations dari db(100) untuk mendapatkan CSW status
      // Buat mapping phone -> last_in_at untuk semua notif sekaligus
      $db100 = $this->db(100);
      $cswMap = []; // Mapping phone -> last_in_at
      
      if (!empty($data)) {
         // Kumpulkan semua phone dari notif
         $phones = [];
         foreach ($data as $dm) {
            $phone = $dm['phone'] ?? '';
            if (!empty($phone)) {
               // Normalisasi phone untuk berbagai format
               $phones[] = $phone;
               $phones[] = '+' . $phone;
               // Jika mulai dengan 0, tambahkan format 62
               if (substr($phone, 0, 1) == '0') {
                  $phones[] = '62' . substr($phone, 1);
                  $phones[] = '+62' . substr($phone, 1);
                  $phones[] = '628' . substr($phone, 2);
                  $phones[] = '+628' . substr($phone, 2);
               }
            }
         }
         
         // Query wa_conversations untuk semua phone sekaligus
         if (!empty($phones)) {
            // Escape phone numbers untuk SQL menggunakan mysqli real_escape_string
            $escapedPhones = [];
            foreach (array_unique($phones) as $p) {
               // Akses mysqli melalui reflection untuk real_escape_string
               $reflection = new \ReflectionClass($db100);
               $mysqliProperty = $reflection->getProperty('mysqli');
               $mysqliProperty->setAccessible(true);
               $mysqli = $mysqliProperty->getValue($db100);
               $escapedPhones[] = "'" . $mysqli->real_escape_string($p) . "'";
            }
            
            $phoneIn = implode(',', $escapedPhones);
            $cswQuery = "SELECT wa_number, last_in_at FROM wa_conversations WHERE wa_number IN ($phoneIn)";
            $cswResults = $db100->query_array($cswQuery);
            
            if ($cswResults !== false) {
               foreach ($cswResults as $cswRow) {
                  $cswMap[$cswRow['wa_number']] = $cswRow['last_in_at'];
               }
            }
         }
      }
      
      $pending += count($data);
      foreach ($data as $dm) {
         $id_notif = $dm['id_notif'];
         $data_pending .= $dm['id_cabang'] . "#" . $id_notif . ' ';

         $expired_bol = false;

         $t1 = strtotime($dm['insertTime']);
         $t2 = strtotime(date("Y-m-d H:i:s"));
         $diff = $t2 - $t1;
         $hours = round($diff / (60 * 60), 1);

         if ($hours > 72) { // 72 hours atau 3 hari
            $expired_bol = true;
         }

         // Cek apakah transaksi sudah tuntas (sudah diambil customer)
         $no_ref = $dm['no_ref'] ?? '';
         if (!empty($no_ref)) {
            $saleCheck = $this->db(0)->get_where_row("sale", "id_penjualan = '" . $no_ref . "'");
            if ($saleCheck && (intval($saleCheck['tuntas'] ?? 0) == 1 || intval($saleCheck['id_user_ambil'] ?? 0) != 0)) {
               // Transaksi sudah tuntas, tidak perlu kirim WA lagi
               $set = ['state' => 'expired'];
               $where2 = "id_notif = '" . $id_notif . "'";
               $this->db(0)->update('notif', $set, $where2);
               $expire += 1;
               continue; // Skip ke notif berikutnya
            }
         }

         // Cek CSW status sebelum kirim dari db(100)
         $phone = $dm['phone'] ?? '';
         $csw_last_in_at = null;
         
         // Cari last_in_at dari mapping dengan berbagai format phone
         $phoneVariants = [
            $phone,
            '+' . $phone,
            '62' . ltrim($phone, '0'),
            '+62' . ltrim($phone, '0'),
         ];
         
         if (substr($phone, 0, 1) == '0') {
            $phoneVariants[] = '628' . substr($phone, 2);
            $phoneVariants[] = '+628' . substr($phone, 2);
         }
         
         // Cari di mapping
         foreach ($phoneVariants as $variant) {
            if (isset($cswMap[$variant])) {
               $csw_last_in_at = $cswMap[$variant];
               break;
            }
         }
         
         $csw_open = false;
         $hoursDiff = null;
         
         if (!empty($csw_last_in_at)) {
            // CSW open jika last_in_at <= 24 jam yang lalu
            $now = date('Y-m-d H:i:s');
            $lastInTime = strtotime($csw_last_in_at);
            $nowTime = strtotime($now);
            $hoursDiff = ($nowTime - $lastInTime) / (60 * 60);
            $csw_open = $hoursDiff <= 24;
         }
         
         // Tambahkan field CSW ke $dm untuk logging
         $dm['csw'] = [
            'open' => $csw_open,
            'last_in_at' => $csw_last_in_at,
            'hours_elapsed' => $hoursDiff !== null ? round($hoursDiff, 2) : null
         ];

         if ($expired_bol == false && $csw_open) {
            $hp = $dm['phone'];
            $text = $dm['text'];
            $res = $this->helper('Notif')->send_wa($hp, $text);

            // Log response dari send_wa
            $apiData = $res['data']['data'] ?? $res['data'] ?? [];
            $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');
            $statusProses = $apiData['status'] ?? 'sent';
            
            $log = $this->model('Log');
            $statusText = isset($res['status']) && $res['status'] ? 'SUCCESS' : 'FAILED';
            $idApiLog = $idApi ?: 'N/A';
            $logMessage = "send_wa response | ID_Notif: {$id_notif} | Phone: {$hp} | Status: {$statusText} | ID_API: {$idApiLog} | Status_Proses: {$statusProses} | Response: " . json_encode($res);
            $log->write($logMessage, 'laundry', 'cron_send_wa');

            if ($res['status']) {
               $set = ['state' => 'sent', 'id_api' => $idApi];
               $where2 = "id_notif = '" . $id_notif . "'";
               $this->db(0)->update('notif', $set, $where2);
               $sent += 1;
            }
         } else {
            // Expired atau CSW closed
            if ($expired_bol) {
               $set = ['state' => 'expired'];
               $where2 = "id_notif = '" . $id_notif . "'";
               $this->db(0)->update('notif', $set, $where2);
               $expire += 1;
            } else if (!$csw_open) {
               // CSW closed - tidak kirim, tetap pending untuk dicoba lagi nanti
               $csw_closed += 1;
               $hp = $dm['phone'];
               $log = $this->model('Log');
               $logMessage = "CSW CLOSED | ID_Notif: {$id_notif} | Phone: {$hp} | Last_in_at: " . ($csw_last_in_at ?? 'N/A') . " | Hours_elapsed: " . ($dm['csw']['hours_elapsed'] ?? 'N/A');
               $log->write($logMessage, 'laundry', 'cron_csw');
            }
         }
      }

      echo "PENDING: " . $pending . " EXPIRED: " . $expire . " SENT: " . $sent . " CSW_CLOSED: " . $csw_closed . "\n";
      if ($data_pending <> '') {
         echo "PENDING (CabangID#NotifID): ";
         echo $data_pending . "\n";
      }
   }
}
