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
      $list['cek'] = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('kas', $cols, $where, 1);

      $this->view($view, $list);
   }

   public function operasi($tipe)
   {
      $id = $_POST['id'];
      $set = [
         'status_mutasi' => $tipe
      ];
      $where = $this->wCabang . " AND ref_finance = '" . $id . "'";
      $up = $this->db($_SESSION[URL::SESSID]['user']['book'])->update('kas', $set, $where);
      if($up['errno'] <> 0){
         $this->model('Log')->write('[NonTunai::operasi] Update Kas Error: ' . $up['error']);
         return $up['error'];
      }else{
         //delete tracker webhooks
         $delete = $this->db(100)->delete('wh_moota', "trx_id = '" . $id . "'");
         if($delete['errno'] <> 0){
            $this->model('Log')->write('[NonTunai::operasi] Delete Wh Moota Error: ' . $delete['error']);
            return $delete['error'];
         }
      }
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
}
