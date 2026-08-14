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
      $tipe = (int) $tipe;
      $idEsc = $this->db(0)->escape((string) $id);
      $where = $this->wCabang . " AND ref_finance = '" . $idEsc . "'";
      $kas = $this->db(0)->get_where_row('kas', $where);
      if (!$kas || empty($kas['id_kas'])) {
         echo 'Transaksi tidak ditemukan';
         return;
      }

      $guard = $this->guardQrisStatusChange($kas, $tipe, false);
      if (empty($guard['ok'])) {
         echo $guard['msg'] ?: 'QRIS tidak dapat diubah';
         return;
      }

      if (empty($guard['paid']) || $tipe !== 3) {
         $set = [
            'status_mutasi' => $tipe
         ];
         $up = $this->db(0)->update('kas', $set, $where);
         if($up['errno'] <> 0){
            $this->model('Log')->write('[NonTunai::operasi] Update Kas Error: ' . $up['error']);
            echo $up['error'];
            return;
         }
      }

      // Update wa_conversations priority = 0 jika priority = 2 (payment confirmed)
      try {
         $kasData = $this->db(0)->get_where_row('kas', $where);

         if ($kasData && isset($kasData['id_client'])) {
            $pelanggan = $this->db(0)->get_where_row('pelanggan', "id_pelanggan = '{$kasData['id_client']}'");

            if ($pelanggan && !empty($pelanggan['nomor_pelanggan'])) {
               $this->helper('PelangganByPhone');
               $nomor = PelangganByPhone::key($pelanggan['nomor_pelanggan']);
               $phonePlus62 = $nomor !== '' ? ('62' . $nomor) : '';

               if ($nomor !== '') {
                  $this->db(100)->query(
                     "UPDATE wa_conversations SET priority = 0 WHERE priority = 2 AND "
                     . PelangganByPhone::likeSql($this->db(100)->escape($nomor), 'wa_number')
                  );
               }

               $payload = [
                  'type' => 'priority_updated',
                  'phone' => $phonePlus62,
                  'priority' => 0,
                  'target_id' => '0',
                  'sender_id' => 'system'
               ];

               $this->model('Log')->write('[NonTunai::operasi] Attempting WebSocket push. Payload: ' . json_encode($payload) . ' | Phone: ' . $phonePlus62);

               $wsResult = $this->pushToWebSocket($payload);

               $this->model('Log')->write('[NonTunai::operasi] WebSocket push result: ' . ($wsResult ? $wsResult : 'NULL/EMPTY'));
            }
         }
      } catch (\Exception $e) {
         $this->model('Log')->write("[NonTunai::operasi] WA conversation error: " . $e->getMessage());
      } catch (\Error $e) {
         $this->model('Log')->write("[NonTunai::operasi] WA conversation fatal error: " . $e->getMessage());
      }

      echo 0;
   }


   
   private function pushToWebSocket($data)
   {
      $url = 'http://127.0.0.1:3003/incoming';
      
      // Log request details
      $this->model('Log')->write('[NonTunai::pushToWebSocket] Starting request to: ' . $url . ' | Data: ' . json_encode($data));
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
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
