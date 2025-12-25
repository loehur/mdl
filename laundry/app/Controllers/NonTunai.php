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
      $cols = "ref_finance, note, id_user, id_client, status_mutasi, jenis_transaksi, SUM(jumlah) as total";
      $where = $this->wCabang . " AND metode_mutasi = 2 AND status_mutasi = 2 AND ref_finance <> '' GROUP BY ref_finance ORDER BY ref_finance DESC LIMIT $limit";
      $list['cek'] = $this->db(0)->get_cols_where('kas', $cols, $where, 1);

      $this->view($view, $list);
   }

   public function operasi($tipe)
   {
      $id = $_POST['id'];
      
      // Debug: log awal fungsi
      $this->model('Log')->write("[NonTunai::operasi] START - tipe=$tipe, id=$id");
      
      $set = [
         'status_mutasi' => $tipe
      ];
      $where = $this->wCabang . " AND ref_finance = '" . $id . "'";
      $up = $this->db(0)->update('kas', $set, $where);
      if($up['errno'] <> 0){
         $this->model('Log')->write('[NonTunai::operasi] Update Kas Error: ' . $up['error']);
         return $up['error'];
      }else{
         // Debug: log setelah update berhasil
         $this->model('Log')->write("[NonTunai::operasi] Update Kas SUCCESS - ref_finance=$id");
         
         // Update wa_conversations priority = 0 jika priority = 2 (payment confirmed)
         // Get nomor_pelanggan from kas table using ref_finance
         $kasData = $this->db(0)->query("SELECT id_client FROM kas WHERE ref_finance = '$id' LIMIT 1")->row();
         
         if ($kasData && $kasData->id_client) {
            $pelanggan = $this->db(1)->get_where('pelanggan', ['id_pelanggan' => $kasData->id_client])->row();
            
            if ($pelanggan && !empty($pelanggan->nomor_pelanggan)) {
               // Format nomor dengan berbagai variasi (+62, 62, 08)
               $cleanPhone = preg_replace('/[^0-9]/', '', $pelanggan->nomor_pelanggan);
               $phone08 = '0' . substr($cleanPhone, -10);
               $phone62 = '62' . substr($cleanPhone, -10);
               $phonePlus62 = '+62' . substr($cleanPhone, -10);
               
               $phones = ["'$phone08'", "'$phone62'", "'$phonePlus62'"];
               $phoneIn = implode(',', $phones);
               
               // Update priority dari 2 (payment check) menjadi 0 (done)
               $updatePriority = $this->db(100)->query(
                  "UPDATE wa_conversations SET priority = 0 WHERE priority = 2 AND wa_number IN ($phoneIn)"
               );
               
               if ($updatePriority) {
                  $this->model('Log')->write("[NonTunai::operasi] Payment confirmed for $phonePlus62, priority reset to 0");
                  
                  // Broadcast WebSocket ke semua agent
                  $payload = [
                     'type' => 'priority_updated',
                     'phone' => $phonePlus62,
                     'priority' => 0,
                     'target_id' => '0', // Broadcast to all
                     'sender_id' => 'system'
                  ];
                  
                  // Push to WebSocket server
                  $this->pushToWebSocket($payload);
               }
            }
         }
         
         //delete tracker webhooks
         // Debug: cek apakah record ada sebelum delete
         $check = $this->db(100)->get_where('wh_moota', ['trx_id' => $id]);
         $count = $check->num_rows();
         $this->model('Log')->write("[NonTunai::operasi] Check wh_moota trx_id='$id', found: $count rows");
         
         if ($count > 0) {
            $delete = $this->db(100)->delete('wh_moota', ['trx_id' => $id]);
            $this->model('Log')->write("[NonTunai::operasi] Delete result: errno={$delete['errno']}, affected={$delete['affected_rows']}");
            
            if($delete['errno'] <> 0){
               $this->model('Log')->write('[NonTunai::operasi] Delete Wh Moota Error: ' . $delete['error']);
               return $delete['error'];
            }
         } else {
            $this->model('Log')->write("[NonTunai::operasi] No wh_moota record found with trx_id='$id'");
         }
      }
      
      // Debug: log akhir fungsi
      $this->model('Log')->write("[NonTunai::operasi] END SUCCESS - ref_finance=$id");
      return 0;
   }

   public function tokopayBalance()
   {
      header('Content-Type: application/json');
      
      try {
         $response = $this->model('Tokopay')->merchant();
         $responseData = json_decode($response, true);
         
         // Log API response (status: 1 atau rc: 200 = success)
         $isSuccess = ($responseData['status'] ?? 0) == 1 || ($responseData['rc'] ?? 0) == 200;
         $status = $isSuccess ? 'success' : 'error';
         $this->model('Log')->apiLog('Tokopay/merchant/balance', null, $response, $status);
         
         echo $response;
      } catch (Exception $e) {
         $errorResponse = ['status' => 'error', 'message' => $e->getMessage()];
         $this->model('Log')->apiLog('Tokopay/merchant/balance', null, $errorResponse, 'error');
         echo json_encode($errorResponse);
      }
   }
   public function withdraw()
   {
      header('Content-Type: application/json');
      
      $nominal = isset($_POST['nominal']) ? intval($_POST['nominal']) : 0;
      
      if ($nominal < 10000) {
         echo json_encode(['status' => 'error', 'message' => 'Minimal penarikan Rp 10.000']);
         return;
      }

      try {
         $response = $this->model('Tokopay')->tarikSaldo($nominal);
         $responseData = json_decode($response, true);
         
         // Log API response
         $status = isset($responseData['status']) && $responseData['status'] === true ? 'success' : 'error';
         
         // Cek jika response status menggunakan code/rc yang berbeda
         if ($status == 'error' && (isset($responseData['rc']) && $responseData['rc'] == 200)) {
            $status = 'success';
         }

         $this->model('Log')->apiLog('Tokopay/v1/tarik-saldo', ['nominal' => $nominal], $response, $status);
         
         echo $response;
      } catch (Exception $e) {
         $errorResponse = ['status' => 'error', 'message' => $e->getMessage()];
         $this->model('Log')->apiLog('Tokopay/v1/tarik-saldo', ['nominal' => $nominal], $errorResponse, 'error');
         echo json_encode($errorResponse);
      }
   }
   
   private function pushToWebSocket($data)
   {
      $url = 'https://waserver.nalju.com/incoming';
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
      curl_setopt($ch, CURLOPT_TIMEOUT, 3);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      
      $result = curl_exec($ch);
      
      if (curl_errno($ch)) {
         $this->model('Log')->write('[NonTunai::pushToWebSocket] cURL Error: ' . curl_error($ch));
      }
      
      curl_close($ch);
      return $result;
   }
}
