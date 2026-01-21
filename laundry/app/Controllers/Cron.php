<?php

class Cron extends Controller
{
   public function send()
   {
      $pending = 0;
      $expire = 0;
      $sent = 0;
      $where = "state = 'pending' ORDER BY insertTime ASC";
      $data_pending = '';

      $data = $this->db(0)->get_where('notif', $where);
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

         if ($expired_bol == false) {
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
            $set = ['state' => 'expired'];
            $where2 = "id_notif = '" . $id_notif . "'";
            $this->db(0)->update('notif', $set, $where2);
            $expire += 1;
         }
      }

      echo "PENDING: " . $pending . " EXPIRED: " . $expire . " SENT: " . $sent . "\n";
      if ($data_pending <> '') {
         echo "PENDING (CabangID#NotifID): ";
         echo $data_pending . "\n";
      }
   }
}
