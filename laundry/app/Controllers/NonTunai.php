<?php

class NonTunai extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $limit = 12;
      $view = 'non_tunai/nt_main';
      $cols = "ref_finance, MAX(ref_transaksi) AS ref_transaksi, note, id_user, id_client, status_mutasi, jenis_transaksi, SUM(jumlah) AS total, MIN(insertTime) AS insertTime";
      $where = $this->wCabang . " AND metode_mutasi = 2 AND status_mutasi = 2 AND ref_finance <> '' GROUP BY ref_finance ORDER BY ref_finance DESC LIMIT $limit";
      $list['cek'] = $this->db(0)->get_cols_where('kas', $cols, $where, 1);

      $this->view($view, $list);
   }

   public function operasi($tipe)
   {
      $id = $_POST['id'];
      $set = [
         'status_mutasi' => $tipe
      ];
      $where = $this->wCabang . " AND ref_finance = '" . $id . "'";
      $up = $this->db(0)->update('kas', $set, $where);
      if($up['errno'] <> 0){
         $this->model('Log')->write('[NonTunai::operasi] Update Kas Error: ' . $up['error']);
         return $up['error'];
      }else{
         // Update wa_conversations priority = 0 jika priority = 2 (payment confirmed)
         try {
            // Get nomor_pelanggan from kas table using ref_finance
            $kasData = $this->db(0)->get_where_row('kas', "ref_finance = '$id'");
            
            if ($kasData && isset($kasData['id_client'])) {
               $pelanggan = $this->db(0)->get_where_row('pelanggan', "id_pelanggan = '{$kasData['id_client']}'");
               
               if ($pelanggan && !empty($pelanggan['nomor_pelanggan'])) {
                  // Format nomor dengan berbagai variasi (+62, 62, 08)
                  $cleanPhone = preg_replace('/[^0-9]/', '', $pelanggan['nomor_pelanggan']);
                  $phone08 = '0' . substr($cleanPhone, -10);
                  $phone62 = '62' . substr($cleanPhone, -10);
                  $phonePlus62 = '+62' . substr($cleanPhone, -10);
                  
                  $phones = ["'$phone08'", "'$phone62'", "'$phonePlus62'"];
                  $phoneIn = implode(',', $phones);
                  
                  // Update priority dari 2 (payment check) menjadi 0 (done)
                  $this->db(100)->query(
                     "UPDATE wa_conversations SET priority = 0 WHERE priority = 2 AND wa_number IN ($phoneIn)"
                  );
                     
                  // Broadcast WebSocket ke semua agent
                  $payload = [
                     'type' => 'priority_updated',
                     'phone' => $phonePlus62,
                     'priority' => 0,
                     'target_id' => '0', // Broadcast to all
                     'sender_id' => 'system'
                  ];
                  
                  // Log payload sebelum push
                  $this->model('Log')->write('[NonTunai::operasi] Attempting WebSocket push. Payload: ' . json_encode($payload) . ' | Phone: ' . $phonePlus62);
                  
                  // Push to WebSocket server
                  $wsResult = $this->pushToWebSocket($payload);
                  
                  // Log hasil push
                  $this->model('Log')->write('[NonTunai::operasi] WebSocket push result: ' . ($wsResult ? $wsResult : 'NULL/EMPTY'));
               }
            }
         } catch (\Exception $e) {
            $this->model('Log')->write("[NonTunai::operasi] WA conversation error: " . $e->getMessage());
         } catch (\Error $e) {
            $this->model('Log')->write("[NonTunai::operasi] WA conversation fatal error: " . $e->getMessage());
         }
         
         // Note: Moota integration removed - no wh_moota cleanup needed
      }
      return 0;
   }


   
   private function pushToWebSocket($data)
   {
      $url = 'https://waserver.nalju.com/incoming';
      
      // Log request details
      $this->model('Log')->write('[NonTunai::pushToWebSocket] Starting request to: ' . $url . ' | Data: ' . json_encode($data));
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
      curl_setopt($ch, CURLOPT_TIMEOUT, 3);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      
      $result = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      
      // Log response details
      if (curl_errno($ch)) {
         $this->model('Log')->write('[NonTunai::pushToWebSocket] cURL Error [' . curl_errno($ch) . ']: ' . $curlError);
      } else {
         $this->model('Log')->write('[NonTunai::pushToWebSocket] Success - HTTP Code: ' . $httpCode . ' | Response: ' . ($result ? $result : 'EMPTY'));
      }
      
      curl_close($ch);
      return $result;
   }
}
