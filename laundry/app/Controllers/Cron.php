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

      $data = $this->db(0)->get_where('notif', $where);

      $this->helper('NotifRecipient');
      $db100 = $this->db(100);
      $cswMap = [];

      if (!empty($data)) {
         $phones = [];
         foreach ($data as $dm) {
            $idPel = (int) ($dm['id_pelanggan'] ?? 0);
            $resolved = NotifRecipient::phoneById($this->db(0), $idPel);
            if ($resolved === null || $resolved === '') {
               continue;
            }
            foreach (NotifRecipient::phoneLookupVariants($resolved) as $variant) {
               $phones[] = $variant;
            }
         }

         if (!empty($phones)) {
            $escapedPhones = [];
            foreach (array_unique($phones) as $p) {
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
         $tipe = $dm['tipe'];
         $data_pending .= $dm['id_cabang'] . "#" . $id_notif . ' ';

         $expired_bol = false;

         $t1 = strtotime($dm['insertTime']);
         $t2 = strtotime(date("Y-m-d H:i:s"));
         $diff = $t2 - $t1;
         $hours = round($diff / (60 * 60), 1);

         if ($hours > 72) {
            $expired_bol = true;
         }

         $no_ref = $dm['no_ref'] ?? '';
         if (!empty($no_ref) && ($tipe == 1 || $tipe == 2)) {
            $saleCheck = $this->db(0)->get_where_row("sale", "id_penjualan = '" . $no_ref . "'");
            if ($saleCheck && (intval($saleCheck['tuntas'] ?? 0) == 1 || intval($saleCheck['id_user_ambil'] ?? 0) != 0)) {
               $set = ['state' => 'expired'];
               $where2 = "id_notif = '" . $id_notif . "'";
               $this->db(0)->update('notif', $set, $where2);
               $expire += 1;
               continue;
            }
         }

         $id_pelanggan = (int) ($dm['id_pelanggan'] ?? 0);
         $hp = NotifRecipient::phoneById($this->db(0), $id_pelanggan);
         $csw_last_in_at = null;

         if ($hp !== null && $hp !== '') {
            foreach (NotifRecipient::phoneLookupVariants($hp) as $variant) {
               if (isset($cswMap[$variant])) {
                  $csw_last_in_at = $cswMap[$variant];
                  break;
               }
            }
         }

         $csw_open = false;
         $hoursDiff = null;

         if (!empty($csw_last_in_at)) {
            $now = date('Y-m-d H:i:s');
            $lastInTime = strtotime($csw_last_in_at);
            $nowTime = strtotime($now);
            $hoursDiff = ($nowTime - $lastInTime) / (60 * 60);
            $csw_open = $hoursDiff <= 24;
         }

         $dm['csw'] = [
            'open' => $csw_open,
            'last_in_at' => $csw_last_in_at,
            'hours_elapsed' => $hoursDiff !== null ? round($hoursDiff, 2) : null
         ];

         if ($expired_bol == false && $csw_open && $id_pelanggan > 0 && $hp !== null && $hp !== '') {
            $text = $dm['text'];
            $res = $this->helper('Notif')->send_wa($id_pelanggan, $text);

            $apiData = $res['data']['data'] ?? $res['data'] ?? [];
            $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');
            $statusProses = $apiData['status'] ?? 'sent';

            $log = $this->model('Log');
            $statusText = isset($res['status']) && $res['status'] ? 'SUCCESS' : 'FAILED';
            $idApiLog = $idApi ?: 'N/A';
            $logMessage = "send_wa response | ID_Notif: {$id_notif} | id_pelanggan: {$id_pelanggan} | HP: {$hp} | Status: {$statusText} | ID_API: {$idApiLog} | Status_Proses: {$statusProses} | Response: " . json_encode($res);

            if ($res['status']) {
               $set = ['state' => 'sent', 'id_api' => $idApi];
               $where2 = "id_notif = '" . $id_notif . "'";
               $this->db(0)->update('notif', $set, $where2);
               $sent += 1;
               $this->helper('Notif')->deleteMatchingWaOutQueue($id_pelanggan, $text);
            } else {
               $log->write($logMessage, 'laundry', 'cron_send_wa');
            }
         } else {
            if ($expired_bol) {
               $set = ['state' => 'expired'];
               $where2 = "id_notif = '" . $id_notif . "'";
               $this->db(0)->update('notif', $set, $where2);
               $expire += 1;
            } else if (!$csw_open) {
               $csw_closed += 1;
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
