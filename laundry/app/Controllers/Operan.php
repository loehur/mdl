<?php

class Operan extends Controller
{
   private $log;

   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
      $this->log = new Log();
   }

   /**
    * Helper untuk write log dengan format konsisten
    * @param string $method Nama method yang memanggil
    * @param string $type Tipe log: ERROR, WARNING, INFO
    * @param string $message Pesan error
    * @param array $context Data konteks tambahan
    */
   private function writeLog($method, $type, $message, $context = [])
   {
      $userId = $_SESSION[URL::SESSID]['user']['id_user'] ?? 'N/A';
      $userName = $_SESSION[URL::SESSID]['user']['nama_user'] ?? 'N/A';
      $idCabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 'N/A';

      $logText = "[OPERAN::{$method}] [{$type}] ";
      $logText .= "User: {$userId} ({$userName}) | Cabang: {$idCabang} | ";
      $logText .= "Message: {$message}";

      if (!empty($context)) {
         $logText .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
      }

      $this->log->write($logText);
   }

   public function index()
   {
      if ($this->isTrainingMode()) {
         echo "Operan tidak tersedia di Mode Training. Switch ke Live terlebih dahulu.";
         return;
      }
      $idOperan = "";
      $idCabang = "";
      $data_operasi = ['title' => 'Operan'];
      $viewData = 'operan/form';
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData, ['idOperan' => $idOperan, 'idCabang' => $idCabang]);
   }

   public function load($idOperan, $idCabang)
   {
      if ($this->isTrainingMode()) {
         echo "Operan tidak tersedia di Mode Training";
         exit();
      }
      if ($idCabang == $_SESSION[URL::SESSID]['user']['id_cabang']) {
         $this->writeLog('load', 'ERROR', 'ID Outlet Operan sama dengan ID Outlet saat ini', [
            'idOperan' => $idOperan,
            'idCabang_input' => $idCabang,
            'idCabang_session' => $_SESSION[URL::SESSID]['user']['id_cabang']
         ]);
         echo "ID Outlet Operan harus berbeda dengan ID Outlet saat ini";
         exit();
      }

      if (strlen($idOperan) < 3) {
         $this->writeLog('load', 'WARNING', 'ID Operan kurang dari 3 digit', [
            'idOperan' => $idOperan,
            'length' => strlen($idOperan),
            'idCabang' => $idCabang
         ]);
         echo "<div class='card py-3 px-3 mx-3'>";
         echo "Minimal 3 Digit";
         echo "</div>";
         exit();
      }

      $id_penjualan = $idOperan;
      $where = "id_penjualan LIKE '%" . $id_penjualan . "' AND tuntas = 0 AND bin = 0 AND id_cabang = " . $idCabang;
      $data_main = $this->db(0)->get_where('sale', $where);
      $idOperan = $id_penjualan;

      if (count($data_main) == 0) {
         $this->writeLog('load', 'WARNING', 'Data penjualan tidak ditemukan', [
            'idOperan' => $idOperan,
            'idCabang' => $idCabang,
            'where_clause' => $where,
         ]);
         echo "Data tidak ditemukan";
         exit();
      }

      $numbers = array_column($data_main, 'id_penjualan');

      $operasi = [];
      foreach ($numbers as $id) {

         //OPERASI
         $where = "id_cabang = " . $idCabang . " AND id_penjualan = '" . $id . "'";
         $ops = $this->db(0)->get_where('operasi', $where);
         if (count($ops) > 0) {
            foreach ($ops as $opsv) {
               array_push($operasi, $opsv);
            }
         }
      }

      $viewData = 'operan/content';
      $this->view($viewData, [
         'data_main' => $data_main,
         'operasi' => $operasi,
         'idOperan' => $idOperan,
         'idCabang' => $idCabang
      ]);
   }

   public function operasiOperan()
   {
      $hp = $_POST['hp'] ?? '';
      $karyawan = $_POST['f1'] ?? '';
      $penjualan = $_POST['f2'] ?? '';
      $operasi = $_POST['f3'] ?? '';
      $idCabang = $_POST['idCabang'] ?? 0;
      $pack = $_POST['pack'] ?? '';
      $hanger = $_POST['hanger'] ?? '';

      // Log semua input untuk debugging
      $inputContext = [
         'hp' => $hp,
         'karyawan' => $karyawan,
         'penjualan' => $penjualan,
         'operasi' => $operasi,
         'idCabang' => $idCabang,
         'pack' => $pack,
         'hanger' => $hanger
      ];

      // Validasi karyawan
      if (empty($karyawan)) {
         $this->writeLog('operasiOperan', 'ERROR', 'ID Karyawan kosong', $inputContext);
         echo "ID Karyawan tidak valid";
         exit();
      }

      $users = $this->db(0)->get_where_row("user", "id_user = " . $karyawan);
      if (empty($users)) {
         $this->writeLog('operasiOperan', 'ERROR', 'Data karyawan tidak ditemukan', [
            'karyawan_id' => $karyawan
         ]);
         echo "Data karyawan tidak ditemukan";
         exit();
      }

      // Generate text using WAGenerator (text sudah final, tidak perlu replace lagi)
      $waGen = $this->helper('WAGenerator');
      $jsonText = $waGen->get_selesai_text($penjualan, $karyawan);
      $objText = json_decode($jsonText, true);
      $text = $objText['text'] ?? "";
      
      if (empty($text)) {
         $this->writeLog('operasiOperan', 'ERROR', 'Generated text empty', [
            'penjualan' => $penjualan,
            'karyawan' => $karyawan,
            'jsonText' => $jsonText
         ]);
         echo "Error: Text notifikasi kosong";
         exit();
      }

      if ($idCabang == 0 || strlen($hp) == 0) {
         $this->writeLog('operasiOperan', 'ERROR', 'ID Cabang atau No HP Pelanggan tidak valid', [
            'idCabang' => $idCabang,
            'hp' => $hp,
            'hp_length' => strlen($hp),
            'penjualan' => $penjualan
         ]);
         echo "ID Cabang atau No HP Pelanggan Error";
         exit();
      };

      // ===== START TRANSACTION =====
      // Insert operasi, update sale, dan insert notif harus atomic (all or nothing)
      if (!$this->db(0)->beginTransaction()) {
         $this->writeLog('operasiOperan', 'ERROR', 'Failed to start transaction', [
            'penjualan' => $penjualan
         ]);
         echo "Gagal memulai transaction";
         exit();
      }
      $this->writeLog('operasiOperan', 'INFO', 'Transaction started', [
         'penjualan' => $penjualan
      ]);
      
      try {
         $setOne = "id_penjualan = '" . $penjualan . "' AND jenis_operasi = " . $operasi;
         $where = "id_cabang = " . $idCabang . " AND " . $setOne;
         $data_main = $this->db(0)->count_where('operasi', $where);

         $operasiInserted = false;
         if ($data_main < 1) {
            // INSERT OPERASI
            $data = [
               'id_operasi' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
               'id_cabang' => $idCabang,
               'id_penjualan' => $penjualan,
               'jenis_operasi' => $operasi,
               'id_user_operasi' => $karyawan,
               'insertTime' => $GLOBALS['now']
            ];
            $in = $this->db(0)->insert('operasi', $data);
            if ($in['errno'] <> 0) {
               throw new \Exception("Insert Operasi Error: " . $in['error']);
            }
            $operasiInserted = true;
            $this->writeLog('operasiOperan', 'INFO', 'Insert Operasi Success', [
               'id_operasi' => $data['id_operasi'],
               'penjualan' => $penjualan
            ]);
         } else {
            $this->writeLog('operasiOperan', 'INFO', 'Operasi already exists', [
               'penjualan' => $penjualan,
               'operasi' => $operasi
            ]);
         }

         // UPDATE SALE
         $set = [
            'pack' => $pack,
            'hanger' => $hanger
         ];
         $whereSale = "id_cabang = " . $idCabang . " AND id_penjualan = '" . $penjualan . "'";
         $up = $this->db(0)->update('sale', $set, $whereSale);
         if ($up['errno'] <> 0) {
            throw new \Exception("Update Sale Error: " . $up['error']);
         }
         $this->writeLog('operasiOperan', 'INFO', 'Update Sale Success', [
            'penjualan' => $penjualan,
            'pack' => $pack,
            'hanger' => $hanger
         ]);

         // INSERT NOTIF SELESAI TAPI NOT READY
         $time = date('Y-m-d H:i:s');
         
         $whereNotif = "id_cabang = " . $idCabang . " AND no_ref = '" . $penjualan . "' AND tipe = 2";
         $data_main_notif = $this->db(0)->count_where('notif', $whereNotif);
         
         $notifInserted = false;
         if ($data_main_notif < 1) {
            $dataNotif = [
               'id_notif' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9),
               'insertTime' => $time,
               'id_cabang' => $idCabang,
               'no_ref' => $penjualan,
               'phone' => $hp,
               'text' => $text,
               'state' => 'queue',
               'tipe' => 2
            ];
            $inNotif = $this->db(0)->insert('notif', $dataNotif);
            if ($inNotif['errno'] <> 0) {
               throw new \Exception("Insert Notif Error: " . $inNotif['error']);
            }
            $notifInserted = true;
            $this->writeLog('operasiOperan', 'INFO', 'Insert Notif Success', [
               'id_notif' => $dataNotif['id_notif'],
               'penjualan' => $penjualan,
               'phone' => $hp
            ]);
         } else {
            $this->writeLog('operasiOperan', 'INFO', 'Notif already exists - skipped insert', [
               'penjualan' => $penjualan
            ]);
         }
         
         // ===== COMMIT TRANSACTION =====
         if (!$this->db(0)->commit()) {
            throw new \Exception("Failed to commit transaction");
         }
         $this->writeLog('operasiOperan', 'INFO', 'Transaction committed successfully', [
            'penjualan' => $penjualan,
            'operasi' => $operasiInserted ? 'inserted' : 'skipped',
            'notif' => $notifInserted ? 'inserted' : 'skipped'
         ]);
         
      } catch (\Exception $e) {
         // ===== ROLLBACK TRANSACTION =====
         $rollbackSuccess = $this->db(0)->rollback();
         $error_msg = "CRITICAL: Transaction FAILED and " . ($rollbackSuccess ? "ROLLED BACK" : "ROLLBACK FAILED") . " - Error: " . $e->getMessage();
         $this->writeLog('operasiOperan', 'ERROR', $error_msg, [
            'penjualan' => $penjualan,
            'exception' => $e->getMessage()
         ]);
         echo "Error: " . $e->getMessage();
         exit();
      }
   }

   /**
    * Endpoint untuk menerima console error/log dari JavaScript
    * Menyimpan ke file log yang sama dengan error PHP
    */
   public function jsLog()
   {
      // Terima data JSON dari JavaScript
      $json = file_get_contents('php://input');
      $data = json_decode($json, true);

      if (!$data) {
         echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
         return;
      }

      $type = $data['type'] ?? 'ERROR';
      $message = $data['message'] ?? 'No message';
      $url = $data['url'] ?? 'Unknown URL';
      $line = $data['line'] ?? 'N/A';
      $column = $data['column'] ?? 'N/A';
      $stack = $data['stack'] ?? '';
      $userAgent = $data['userAgent'] ?? '';

      $this->writeLog('JS', $type, $message, [
         'url' => $url,
         'line' => $line,
         'column' => $column,
         'stack' => $stack,
         'userAgent' => $userAgent
      ]);

      echo json_encode(['status' => 'ok']);
   }
}
