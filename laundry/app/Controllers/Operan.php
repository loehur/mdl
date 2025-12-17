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
      $idOperan = "";
      $idCabang = "";
      $data_operasi = ['title' => 'Operan'];
      $viewData = 'operan/form';
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData, ['idOperan' => $idOperan, 'idCabang' => $idCabang]);
   }

   public function load($idOperan, $idCabang)
   {

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
      $data_main = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('sale', $where);
      $idOperan = $id_penjualan;

      if (count($data_main) == 0) {
         $this->writeLog('load', 'WARNING', 'Data penjualan tidak ditemukan', [
            'idOperan' => $idOperan,
            'idCabang' => $idCabang,
            'where_clause' => $where,
            'book' => $_SESSION[URL::SESSID]['user']['book']
         ]);
         echo "Data tidak ditemukan";
         exit();
      }

      $numbers = array_column($data_main, 'id_penjualan');

      $operasi = [];
      foreach ($numbers as $id) {

         //OPERASI
         $where = "id_cabang = " . $idCabang . " AND id_penjualan = " . $id;
         $ops = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('operasi', $where);
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
      $text = $_POST['text'] ?? '';

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

      $nm_karyawan = $users['nama_user'];
      $karyawan_code = strtoupper(substr($nm_karyawan, 0, 2)) . substr($karyawan, -1);

      $text = str_replace("|STAFF|", $karyawan_code, $text);

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

      $setOne = 'id_penjualan = ' . $penjualan . " AND jenis_operasi = " . $operasi;
      $where = "id_cabang = " . $idCabang . " AND " . $setOne;
      $data_main = $this->db(date('Y'))->count_where('operasi', $where);

      if ($data_main < 1) {
         // INSERT OPERASI
         $data = [
            'id_cabang' => $idCabang,
            'id_penjualan' => $penjualan,
            'jenis_operasi' => $operasi,
            'id_user_operasi' => $karyawan,
            'insertTime' => $GLOBALS['now']
         ];
         $in = $this->db(date('Y'))->insert('operasi', $data);
         if ($in['errno'] <> 0) {
            $this->writeLog('operasiOperan', 'ERROR', 'Gagal insert ke tabel operasi', [
               'error_no' => $in['errno'],
               'error_msg' => $in['error'],
               'data' => $data,
               'penjualan' => $penjualan,
               'operasi' => $operasi
            ]);
            echo $in['error'];
            exit();
         }

         // UPDATE SALE
         $set = [
            'pack' => $pack,
            'hanger' => $hanger
         ];
         $where = "id_cabang = " . $idCabang . " AND id_penjualan = " . $penjualan;
         $up = $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
         if ($up['errno'] <> 0) {
            $this->writeLog('operasiOperan', 'ERROR', 'Gagal update tabel sale', [
               'error_no' => $up['errno'],
               'error_msg' => $up['error'],
               'set_data' => $set,
               'where_clause' => $where,
               'penjualan' => $penjualan,
               'book' => $_SESSION[URL::SESSID]['user']['book']
            ]);
            echo $up['error'];
            exit();
         }

         // INSERT NOTIF SELESAI TAPI NOT READY
         $time = date('Y-m-d H:i:s');
         $dataNotif = [
            'insertTime' => $time,
            'id_cabang' => $idCabang,
            'no_ref' => $penjualan,
            'phone' => $hp,
            'text' => $text,
            'status' => 5,
            'tipe' => 2
         ];
         $inNotif = $this->db(date('Y'))->insert('notif', $dataNotif);
         if ($inNotif['errno'] <> 0) {
            $this->writeLog('operasiOperan', 'ERROR', 'Gagal insert ke tabel notif', [
               'error_no' => $inNotif['errno'],
               'error_msg' => $inNotif['error'],
               'data' => $dataNotif,
               'penjualan' => $penjualan
            ]);
            echo $inNotif['error'];
            exit();
         }
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
