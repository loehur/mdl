<?php

class Antrian extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   /**
    * Helper untuk write log dengan format konsisten
    * @param string $method Nama method yang memanggil
    * @param string $type Tipe log: INFO, WARNING, ERROR, DEBUG
    * @param string $message Pesan
    * @param array $context Data konteks tambahan (optional)
    */
   private function writeLog($method, $type, $message, $context = [])
   {
      $userId = $_SESSION[URL::SESSID]['user']['id_user'] ?? 'Guest';
      $userName = $_SESSION[URL::SESSID]['user']['nama_user'] ?? 'Guest';
      $idCabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 'N/A';

      $logText = "[ANTRIAN::{$method}] [{$type}] ";
      $logText .= "User: {$userId} ({$userName}) | Cabang: {$idCabang} | ";
      $logText .= "Message: {$message}";

      if (!empty($context)) {
         // Menyembunyikan data sensitif jika ada
         if (isset($context['password'])) $context['password'] = '***';
         $logText .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
      }

      $this->model('Log')->write($logText);
   }

   public function index($antrian = 1)
   {
      $kas = [];
      $notif = [];
      $notifPenjualan = [];
      $data_main = [];
      $surcas = [];

      switch ($antrian) {
         case 1:
            //DALAM PROSES 10 HARI
            $data_operasi = ['title' => 'Data Order Proses H7-'];
            $viewData = 'antrian/view';
            break;
         case 6:
            //DALAM PROSES > 7 HARI
            $data_operasi = ['title' => 'Data Order Proses H7+'];
            $viewData = 'antrian/view';
            break;
         case 7:
            //DALAM PROSES > 30 HARI
            $data_operasi = ['title' => 'Data Order Proses H30+'];
            $viewData = 'antrian/view';
            break;
         case 8:
            //DALAM PROSES > 1 Tahun
            $data_operasi = ['title' => 'Data Order Proses H365+'];
            $viewData = 'antrian/view';
            break;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('antrian/form', [
         'modeView' => $antrian,
      ]);
      $this->view($viewData, [
         'modeView' => $antrian,
         'data_main' => $data_main,
         'kas' => $kas,
         "notif" => $notif,
         'notif_penjualan' => $notifPenjualan,
         "surcas" => $surcas,
      ]);
   }

   public function p($antrian)
   {
      $kas = array();
      $notif = array();
      $notifPenjualan = array();
      $data_main = array();
      $surcas = array();

      switch ($antrian) {
         case 100:
            //DALAM PROSES 10 HARI
            $data_operasi = ['title' => 'Data Piutang'];
            $viewData = 'antrian/view';
            break;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('antrian/form', [
         'modeView' => $antrian,
      ]);
      $this->view($viewData, [
         'modeView' => $antrian,
         'data_main' => $data_main,
         'kas' => $kas,
         "notif" => $notif,
         'notif_penjualan' => $notifPenjualan,
         "surcas" => $surcas,
      ]);
   }

   public function loadList($antrian)
   {
      $data_main = [];
      $viewData = 'antrian/view_content';
      switch ($antrian) {
         case 1:
            //DALAM PROSES 7 HARI
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(NOW()) <= (insertTime + INTERVAL 7 DAY) ORDER BY id_penjualan DESC";
            break;
         case 6:
            //DALAM PROSES > 7 HARI
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(NOW()) > (insertTime + INTERVAL 7 DAY) AND DATE(NOW()) <= (insertTime + INTERVAL 30 DAY) ORDER BY id_penjualan DESC";
            break;
         case 7:
            //DALAM PROSES > 30 HARI
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(NOW()) > (insertTime + INTERVAL 30 DAY) AND DATE(NOW()) <= (insertTime + INTERVAL 365 DAY) ORDER BY id_penjualan DESC";
            break;
         case 8:
            //DALAM PROSES > 1 TAHUN
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(NOW()) > (insertTime + INTERVAL 365 DAY) ORDER BY id_penjualan DESC";
            break;
         case 100:
            //PIUTANG 7 HARI
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND id_user_ambil <> 0 ORDER BY id_penjualan ASC";
            break;
      }

      $data_main = $this->db(0)->get_cols_where('sale', 'id_penjualan', $where, 1, 'id_penjualan');
      $data_main2 = $this->db(0)->get_where('sale', $where, 'no_ref', 1);

      $numbers = array_keys($data_main);
      $refs = array_keys($data_main2);

      $operasi = [];
      $kas = [];
      $surcas = [];
      $notif = [];

      if (count($refs) > 0) {
         $ref_list = "";
         foreach ($refs as $r) {
            $ref_list .= $r . ",";
         }
         $ref_list = rtrim($ref_list, ',');

         $where = $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi IN (" . $ref_list . ")";
         $kas = $this->db(0)->get_where('kas', $where);

         $where = $this->wCabang . " AND no_ref IN (" . $ref_list . ")";
         $surcas = $this->db(0)->get_where('surcas', $where);

         $where = $this->wCabang . " AND tipe = 1 AND no_ref IN (" . $ref_list . ")";
         $notif = $this->db(0)->get_where('notif', $where);
      }

      if (count($numbers) > 0) {
         $no_list = "";
         foreach ($numbers as $r) {
            $no_list .= "'" . $r . "',";
         }
         $no_list = rtrim($no_list, ',');

         //OPERASI
         $where = $this->wCabang . " AND id_penjualan IN (" . $no_list . ")";
         $operasi = $this->db(0)->get_where('operasi', $where);
      }

      $karyawan = $this->userAll;

      $this->view($viewData, [
         'modeView' => $antrian,
         'data_main' => $data_main2,
         'operasi' => $operasi,
         'kas' => $kas,
         "surcas" => $surcas,
         'data_notif' => $notif,
         "karyawan" => $karyawan
      ]);
   }

   public function clearTuntas()
   {
      if (isset($_POST['data'])) {
         $data = unserialize($_POST['data']);

         foreach ($data as $a) {
            $this->tuntasOrder($a);
         }
      } else {

      }
   }

   public function operasi()
   {
      $karyawan = $_POST['f1'];
      $totalNotif = $_POST['inTotalNotif'];

      $penjualan = $_POST['f2'];
      $operasi = $_POST['f3'];

      // Get sale data to retrieve customer phone
      $sale = $this->db(0)->get_where_row('sale', "id_penjualan = '$penjualan'");
      $id_pelanggan = $sale['id_pelanggan'];
      
      // Get customer phone
      $pelanggan = $this->db(0)->get_where_row('pelanggan', "id_pelanggan = '$id_pelanggan'");
      $hp = $pelanggan['nomor_pelanggan'];

      // Generate text using WAGenerator (text sudah final, tidak perlu replace lagi)
      $waGen = $this->helper('WAGenerator');
      $jsonText = $waGen->get_selesai_text($penjualan, $karyawan, $totalNotif);
      $objText = json_decode($jsonText, true);
      $text = $objText['text'] ?? "";

      $setOne = "id_penjualan = '" . $penjualan . "' AND jenis_operasi = " . $operasi;
      $where = $this->wCabang . " AND " . $setOne;

      $data_main = $this->db(0)->count_where('operasi', $where);

      if ($data_main < 1) {
         $data = [
            'id_operasi' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
            'id_cabang' => $this->id_cabang,
            'id_penjualan' => $penjualan,
            'jenis_operasi' => $operasi,
            'id_user_operasi' => $karyawan,
            'insertTime' => $GLOBALS['now']
         ];
         $in = $this->db(0)->insert('operasi', $data);
         if ($in['errno'] <> 0) {
            $this->writeLog('operasi', 'ERROR', 'Insert Operasi Failed', ['error' => $in['error']]);
            $this->model('Log')->write("[operasi] Insert Operasi Error: " . $in['error']);
            echo $in['error'];
            exit();
         }
      }

      //INSERT NOTIF SELESAI TAPI NOT READY
      $time = date('Y-m-d H:i:s');

      $whereNotif = $this->wCabang . " AND no_ref = '" . $penjualan . "' AND tipe = 2";
      $data_main = $this->db(0)->count_where('notif', $whereNotif);
      if ($data_main < 1) {
         $data = [
            'id_notif' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $penjualan,
            'phone' => $hp,
            'text' => $text,
            'state' => 'pending',
            'tipe' => 2
         ];
          $do = $this->db(0)->insert('notif', $data);
          if ($do['errno'] <> 0) {
             $this->writeLog('operasi', 'ERROR', 'Insert Notif Failed', ['error' => $do['error']]);
             $this->model('Log')->write("[operasi] Insert Notif Error: " . $do['error']);
             $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $do['error']);
          }
      }

      if (isset($_POST['rak'])) {
         if (strlen($_POST['rak']) > 0) {
            $rak = $_POST['rak'];
            $pack = $_POST['pack'];
            $hanger = $_POST['hanger'];
            $set = ['letak' => $rak, 'pack' => $pack, 'hanger' => $hanger];
            $where = $this->wCabang . " AND id_penjualan = '" . $penjualan . "'";
            $this->db(0)->update('sale', $set, $where);

            //CEK DATA NOTIF
            $setOne = "no_ref = '" . $penjualan . "' AND tipe = 2 AND (state = 'pending' || state = 'queue')";
            $where = $setOne;
            $data_main = $this->db(0)->count_where('notif', $where);
             if ($data_main == 1) {
                $this->notifReadySend($penjualan);
             }
          }
       }

       echo 0;
   }

   public function surcas()
   {
      $jenis = $_POST['surcas'];
      $jumlah = $_POST['jumlah'];
      $user = $_POST['user'];
      $id_transaksi = $_POST['no_ref'];



      $setOne = "transaksi_jenis = 1 AND no_ref = " . $id_transaksi . " AND id_jenis_surcas = " . $jenis;
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(0)->count_where('surcas', $where);

      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'transaksi_jenis' => 1,
            'id_jenis_surcas' => $jenis,
            'jumlah' => $jumlah,
            'id_user' => $user,
            'no_ref' => $id_transaksi
         ];
             $in = $this->db(0)->insert('surcas', $data);
             if ($in['errno'] <> 0) {
                $this->writeLog('surcas', 'ERROR', 'Insert Surcas Failed', ['error' => $in['error']]);
                $this->model('Log')->write("[surcas] Insert Surcas Error: " . $in['error']);
                echo $in['error'];
                exit();
             }
      }
      echo 0;
   }

   public function updateRak($mode = 0)
   {
      $rak = $_POST['value'];
      $id = $_POST['id'];
      $totalNotif = $_POST['totalNotif'];
      
      switch ($mode) {
         case 0:
            $set = ['letak' => $rak];
            break;
         case 1:
            $set = ['pack' => $rak];
            break;
         case 2:
            $set = ['hanger' => $rak];
            break;
         default:
            $set = ['letak' => $rak];
            break;
      }
      $where = $this->wCabang . " AND id_penjualan = '" . $id . "'";
      $this->db(0)->update('sale', $set, $where);

      //CEK SUDAH TERKIRIM BELUM
      $setOne = "no_ref = '" . $id . "' AND state = 'queue'";
      $where = $setOne;
      $data_main = $this->db(0)->count_where('notif', $where);
      if ($data_main == 1) {
         $this->notifReadySend($id);
      }
   }

   public function tuntasOrder($ref)
   {

      $set = ['tuntas' => 1];
      $where = $this->wCabang . " AND no_ref = " . $ref;
      $this->db(0)->update('sale', $set, $where);
   }

   public function notifReadySend($idPenjualan)
   {
      $setOne = "no_ref = '" . $idPenjualan . "' AND tipe = 2";
      $where = $this->wCabang . " AND " . $setOne;
      $dm = $this->db(0)->get_where_row('notif', $where);
      $hp = $dm['phone'];
      $text = $dm['text'];
      // Text sudah final dari WAGenerator, tidak perlu replace lagi
      $res = $this->helper('Notif')->send_wa($hp, $text, false);

      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');

      $where2 = $this->wCabang . " AND no_ref = '" . $idPenjualan . "' AND tipe = 2";
      if ($res['status']) {
         $set = ['state' => 'sent', 'id_api' => $idApi];
         $this->db(0)->update('notif', $set, $where2);
      } else {
         $set = ['state' => 'pending'];
         $this->db(0)->update('notif', $set, $where2);
      }
   }

   public function sendNotif($countMember, $tipe)
   {
      $hp = $_POST['hp'];
      $noref = $_POST['ref'];
      $time =  $_POST['time'];

      $waGen = $this->helper('WAGenerator');
      $jsonText = $waGen->get_nota($noref);
      $objText = json_decode($jsonText, true);
      $text = $objText['text'] ?? "";

      // FIX: Close session before long-running WA operation to prevent blocking other requests
      if (session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      // Send with template mode (will try free text first, fallback to template if CSW expired)
      // Pass jsonText (contains both 'text' and 'template_params')
      $res = $this->helper("Notif")->send_wa($hp, $jsonText, URL::MESSAGE_MODE);
      
      $setOne = "no_ref = '" . $noref . "' AND tipe = 1";
      $where = $this->wCabang . " AND " . $setOne;

      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');

      // DEBUG LOG - Full response structure
      @file_put_contents(__DIR__ . '/../../logs/notif_debug.log', date('H:i:s') . " === FULL RESPONSE ===\n", FILE_APPEND);
      @file_put_contents(__DIR__ . '/../../logs/notif_debug.log', json_encode($res, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
      @file_put_contents(__DIR__ . '/../../logs/notif_debug.log', date('H:i:s') . " Response status: " . ($res['status'] ? 'true' : 'false') . "\n", FILE_APPEND);
      @file_put_contents(__DIR__ . '/../../logs/notif_debug.log', date('H:i:s') . " apiData: " . json_encode($apiData) . "\n", FILE_APPEND);
      @file_put_contents(__DIR__ . '/../../logs/notif_debug.log', date('H:i:s') . " ID API extracted: '$idApi'\n", FILE_APPEND);
      @file_put_contents(__DIR__ . '/../../logs/notif_debug.log', date('H:i:s') . " === END ===\n\n", FILE_APPEND);

      if ($res['status']) {
         $vals = [
            'id_notif' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $noref,
            'phone' => $hp,
            'text' => $text,
            'tipe' => $tipe,
            'id_api' => $idApi,
            'state' => 'sent'
         ];
         
         // DEBUG LOG
         @file_put_contents(__DIR__ . '/../../logs/notif_debug.log', date('H:i:s') . " Inserting notif with id_api: $idApi\n", FILE_APPEND);
      } else {
         $vals = [
            'id_notif' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $noref,
            'phone' => $hp,
            'text' => $text,
            'tipe' => $tipe,
            'id_api' => '',
            'state' => 'pending'
         ];
      }

      $data_main = $this->db(0)->count_where('notif', $where);
      if ($data_main < 1) {

         $do = $this->db(0)->insert('notif', $vals);
          
         // DEBUG LOG
         @file_put_contents(__DIR__ . '/../../logs/notif_debug.log', date('H:i:s') . " Insert result: " . ($do ? 'success' : 'failed') . "\n", FILE_APPEND);
          if ($do['errno'] <> 0) {
             $this->writeLog('sendNotif', 'ERROR', 'Insert Notif Failed', ['error' => $do['error']]);
             $this->model('Log')->write("[sendNotif] Insert Notif Error: " . $do['error']);
             echo $do['error'];
          } else {
              echo 0;
          }
      }
   }



   public function ambil()
   {
      $karyawan = $_POST['f1'];
      $id = $_POST['f2'];



      $dateNow = date('Y-m-d H:i:s');
      $set = ['tgl_ambil' => $dateNow, 'id_user_ambil' => $karyawan];
      $setOne = "id_penjualan = '" . $id . "'";
      $where = $this->wCabang . " AND " . $setOne;
       $up = $this->db(0)->update('sale', $set, $where);
       if ($up['errno'] <> 0) {
          $this->writeLog('ambil', 'ERROR', 'Update Sales Failed', ['error' => $up['error']]);
          $this->model('Log')->write("[ambil] Update Sale (Ambil) Error: " . $up['error']);
          echo $up['error'];
      } else {
         echo 0;
      }
   }

   public function hapusRef()
   {
      $ref = $_POST['ref'];
      $note = $_POST['note'];

      $setOne = "no_ref = '" . $ref . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $set = ['bin' => 1, 'bin_note' => $note];
      $this->db(0)->update('sale', $set, $where);
   }

   public function restoreRef()
   {
      $ref = $_POST['ref'];

      $setOne = "no_ref = '" . $ref . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $set = ['bin' => 0];
      $this->db(0)->update('sale', $set, $where);
   }
}
