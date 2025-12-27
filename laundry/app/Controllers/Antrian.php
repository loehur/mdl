<?php

class Antrian extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
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
      $penjualan = $_POST['f2'];
      $operasi = $_POST['f3'];

      // Get sale data to retrieve customer phone
      $sale = $this->db(0)->get_where_row('sale', "id_penjualan = '$penjualan'");
      if (!$sale) {
         $this->model('Log')->write("[operasi] ERROR: Sale data not found - ID: " . $penjualan);
         echo "Error: Sale data tidak ditemukan";
         exit();
      }
      $id_pelanggan = $sale['id_pelanggan'];
      
      // Get customer phone
      $pelanggan = $this->db(0)->get_where_row('pelanggan', "id_pelanggan = '$id_pelanggan'");
      if (!$pelanggan) {
         $this->model('Log')->write("[operasi] ERROR: Customer data not found - ID Pelanggan: " . $id_pelanggan);
         echo "Error: Data pelanggan tidak ditemukan";
         exit();
      }
      $hp = $pelanggan['nomor_pelanggan'];
      
      if (empty($hp)) {
         $this->model('Log')->write("[operasi] ERROR: Customer phone empty - ID Pelanggan: " . $id_pelanggan);
         echo "Error: Nomor HP pelanggan kosong";
         exit();
      }

      // Generate text using WAGenerator (text sudah final, tidak perlu replace lagi)
      $waGen = $this->helper('WAGenerator');
      $jsonText = $waGen->get_selesai_text($penjualan, $karyawan);
      $objText = json_decode($jsonText, true);
      $text = $objText['text'] ?? "";
      
      if (empty($text)) {
         $this->model('Log')->write("[operasi] ERROR: Generated text empty - ID Penjualan: " . $penjualan . " | Karyawan: " . $karyawan . " | JSON: " . $jsonText);
         echo "Error: Text notifikasi kosong";
         exit();
      }

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
            $this->model('Log')->write("[operasi] Insert Operasi Error: " . $in['error']);
            echo $in['error'];
            exit();
         }
      } else {
         $this->model('Log')->write("[operasi] Operasi already exists: " . $penjualan . " - " . $operasi);
      }

      //INSERT NOTIF SELESAI TAPI NOT READY
      $time = date('Y-m-d H:i:s');

      $whereNotif = $this->wCabang . " AND no_ref = '" . $penjualan . "' AND tipe = 2";
      $data_main = $this->db(0)->count_where('notif', $whereNotif);
      
      $this->model('Log')->write("[operasi] Check existing notif - Where: " . $whereNotif . " | Count: " . $data_main);
      
      if ($data_main < 1) {
         $notifData = [
            'id_notif' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $penjualan,
            'phone' => $hp,
            'text' => $text,
            'state' => 'pending',
            'tipe' => 2
         ];
         
         $this->model('Log')->write("[operasi] Attempting insert notif - Phone: " . $hp . " | Ref: " . $penjualan);
         
          $do = $this->db(0)->insert('notif', $notifData);
          if ($do['errno'] <> 0) {
             $this->model('Log')->write("[operasi] Insert Notif Error: " . $do['error'] . " | Phone: " . $hp);
             $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $do['error']);
          } else {
             $this->model('Log')->write("[operasi] Insert Notif Success - ID: " . $notifData['id_notif'] . " | Phone: " . $hp . " | State: pending");
          }
      } else {
         $this->model('Log')->write("[operasi] WARNING: Notif already exists - skipped insert for: " . $penjualan);
      }

      if (isset($_POST['rak'])) {
         if (strlen($_POST['rak']) > 0) {
            $rak = $_POST['rak'];
            $pack = $_POST['pack'];
            $hanger = $_POST['hanger'];
            $set = ['letak' => $rak, 'pack' => $pack, 'hanger' => $hanger];
            $where = $this->wCabang . " AND id_penjualan = '" . $penjualan . "'";
            $upResult = $this->db(0)->update('sale', $set, $where);
            
            if ($upResult['errno'] <> 0) {
               $this->model('Log')->write("[operasi] ERROR: Update rak failed - " . $upResult['error']);
            } else {
               $this->model('Log')->write("[operasi] Update rak success - Rak: " . $rak . " | Pack: " . $pack . " | Hanger: " . $hanger);
            }

            //CEK DATA NOTIF
            $setOne = "no_ref = '" . $penjualan . "' AND tipe = 2 AND (state = 'pending' || state = 'queue')";
            $where = $setOne;
            $data_main = $this->db(0)->count_where('notif', $where);
            
            $this->model('Log')->write("[operasi] Check notif ready to send - Count: " . $data_main . " | Expected: 1");
            
             if ($data_main == 1) {
                $this->model('Log')->write("[operasi] Calling notifReadySend for: " . $penjualan);
                $this->notifReadySend($penjualan);
             } else {
                $this->model('Log')->write("[operasi] WARNING: Notif not ready or not found - Count: " . $data_main);
             }
          } else {
             $this->model('Log')->write("[operasi] WARNING: Rak kosong, skip notifReadySend for: " . $penjualan);
          }
       } else {
          $this->model('Log')->write("[operasi] WARNING: Rak not set in POST, skip notifReadySend for: " . $penjualan);
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
      
      if (!$dm) {
         $this->model('Log')->write("[notifReadySend] WARNING: Notif tidak ditemukan - ID: " . $idPenjualan);
         return;
      }
      
      // Check state to prevent duplicate sends
      $currentState = $dm['state'] ?? '';
      
      // Skip if already sent or currently processing
      if (in_array($currentState, ['sent', 'processing'])) {
         $this->model('Log')->write("[notifReadySend] WARNING: Notif sudah terkirim atau sedang diproses - ID: " . $idPenjualan . " | State: " . $currentState);
         return;
      }
      
      // Set state to 'processing' as a lock before sending
      $lockSet = ['state' => 'processing'];
      $lockResult = $this->db(0)->update('notif', $lockSet, $where);
      
      if ($lockResult['errno'] <> 0) {
         $this->model('Log')->write("[notifReadySend] ERROR: Gagal lock notif - ID: " . $idPenjualan . " | Error: " . $lockResult['error']);
         return;
      }
      
      $hp = $dm['phone'];
      $text = $dm['text'];
      
      // Validate phone and text
      if (empty($hp)) {
         $this->model('Log')->write("[notifReadySend] ERROR: Phone number empty - ID: " . $idPenjualan);
         // Set back to pending
         $this->db(0)->update('notif', ['state' => 'pending'], $where);
         return;
      }
      
      if (empty($text)) {
         $this->model('Log')->write("[notifReadySend] ERROR: Text empty - ID: " . $idPenjualan);
         // Set back to pending
         $this->db(0)->update('notif', ['state' => 'pending'], $where);
         return;
      }
      
      $this->model('Log')->write("[notifReadySend] Sending WA - ID: " . $idPenjualan . " | Phone: " . $hp);
      
      // Text sudah final dari WAGenerator, tidak perlu replace lagi
      $res = $this->helper('Notif')->send_wa($hp, $text, false);

      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');

      $where2 = $this->wCabang . " AND no_ref = '" . $idPenjualan . "' AND tipe = 2";
      if ($res['status']) {
         $set = ['state' => 'sent', 'id_api' => $idApi];
         $updateResult = $this->db(0)->update('notif', $set, $where2);
         
         if ($updateResult['errno'] <> 0) {
            $this->model('Log')->write("[notifReadySend] ERROR: Update notif to sent failed - ID: " . $idPenjualan . " | Error: " . $updateResult['error']);
         } else {
            $this->model('Log')->write("[notifReadySend] SUCCESS: WA sent - ID: " . $idPenjualan . " | API ID: " . $idApi . " | Phone: " . $hp);
         }
      } else {
         $errorMsg = $res['message'] ?? $res['error'] ?? 'Unknown error';
         $this->model('Log')->write("[notifReadySend] ERROR: WA send failed - ID: " . $idPenjualan . " | Phone: " . $hp . " | Error: " . $errorMsg);
         
         $set = ['state' => 'pending'];
         $updateResult = $this->db(0)->update('notif', $set, $where2);
         
         if ($updateResult['errno'] <> 0) {
            $this->model('Log')->write("[notifReadySend] ERROR: Update notif back to pending failed - ID: " . $idPenjualan . " | Error: " . $updateResult['error']);
         }
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

      // Cek apakah no HP ada di tabel user dengan berbagai kemungkinan format
      $hpVariations = [];
      $hpClean = preg_replace('/[^0-9]/', '', $hp); // Hapus karakter non-angka
      
      // Buat variasi nomor: +628xxx, 628xxx, 08xxx, 8xxx
      if (substr($hpClean, 0, 2) === '62') {
         // Jika dimulai dengan 62
         $hpVariations[] = "'+62" . substr($hpClean, 2) . "'";
         $hpVariations[] = "'" . $hpClean . "'";
         $hpVariations[] = "'0" . substr($hpClean, 2) . "'";
         $hpVariations[] = "'" . substr($hpClean, 2) . "'";
      } elseif (substr($hpClean, 0, 1) === '0') {
         // Jika dimulai dengan 0
         $hpVariations[] = "'+62" . substr($hpClean, 1) . "'";
         $hpVariations[] = "'62" . substr($hpClean, 1) . "'";
         $hpVariations[] = "'" . $hpClean . "'";
         $hpVariations[] = "'" . substr($hpClean, 1) . "'";
      } else {
         // Jika dimulai dengan 8
         $hpVariations[] = "'+62" . $hpClean . "'";
         $hpVariations[] = "'62" . $hpClean . "'";
         $hpVariations[] = "'0" . $hpClean . "'";
         $hpVariations[] = "'" . $hpClean . "'";
      }
      
      $whereUser = "no_user IN (" . implode(', ', $hpVariations) . ")";
      $userExists = $this->db(0)->count_where('user', $whereUser);
      
      // Check if notification already exists to prevent duplicate sends
      $setOne = "no_ref = '" . $noref . "' AND tipe = 1";
      $where = $this->wCabang . " AND " . $setOne;
      $existingNotif = $this->db(0)->count_where('notif', $where);
      
      if ($existingNotif === 0) {
         // Notification already sent, skip sending again
         $this->model('Log')->write("[sendNotif] WARNING: Notif already exists, skipped sending - Ref: " . $noref . " | HP: " . $hp);
         echo json_encode(['status' => 'exists', 'message' => 'Notifikasi sudah pernah dikirim']);
         return;
      }
      
      // INSERT PENDING RECORD FIRST as distributed lock to prevent race condition
      // This ensures that if another request comes in before WA is sent, it will see this record
      $id_notif = (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9);
      $pendingVals = [
         'id_notif' => $id_notif,
         'insertTime' => $time,
         'id_cabang' => $this->id_cabang,
         'no_ref' => $noref,
         'phone' => $hp,
         'text' => $text,
         'tipe' => $tipe,
         'id_api' => '',
         'state' => 'pending'
      ];
      
      $insertResult = $this->db(0)->insert('notif', $pendingVals);
      if ($insertResult['errno'] <> 0) {
         // Insert failed (might be duplicate key if another request just inserted)
         $this->model('Log')->write("[sendNotif] WARNING: Insert pending failed - likely duplicate - Ref: " . $noref . " | Error: " . $insertResult['error']);
         echo json_encode(['status' => 'exists', 'message' => 'Notifikasi sedang diproses']);
         return;
      }
      
      // NOW send WA (protected by the record we just inserted)
      $useTemplate = ($userExists > 0) ? 'free' : 'template';
      $res = $this->helper("Notif")->send_wa($hp, $jsonText, $useTemplate);
      
      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');

      // Update the record with WA API result
      if ($res['status']) {
         $updateVals = [
            'id_api' => $idApi,
            'state' => 'sent'
         ];
         $this->db(0)->update('notif', $updateVals, $where);
         echo 0;
      } else {
         // WA send failed, update state to pending for retry
         $updateVals = [
            'state' => 'pending'
         ];
         $this->db(0)->update('notif', $updateVals, $where);
         echo json_encode(['status' => 'failed', 'message' => 'Gagal mengirim WA']);
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
