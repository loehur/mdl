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
      $this->writeLog('i', 'INFO', 'Mengakses halaman antrian operasi', ['antrian' => $antrian]);
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
      $this->writeLog('p', 'INFO', 'Mengakses halaman antrian piutang', ['antrian' => $antrian]);
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
      $this->writeLog('loadList', 'INFO', 'Load Data Antrian', ['antrian' => $antrian]);
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

      if ($_SESSION[URL::SESSID]['user']['book'] <> date('Y')) {
         $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 ORDER BY id_penjualan DESC";
      }

      $data_main = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('sale', 'id_penjualan', $where, 1, 'id_penjualan');
      $data_main2 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('sale', $where, 'no_ref', 1);

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
         $kas1 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);
         $kas2 = $this->db($_SESSION[URL::SESSID]['user']['book'] + 1)->get_where('kas', $where);
         $kas = array_merge($kas1, $kas2);

         $where = $this->wCabang . " AND no_ref IN (" . $ref_list . ")";
         $surcas = $this->db(0)->get_where('surcas', $where);

         $where = $this->wCabang . " AND tipe = 1 AND no_ref IN (" . $ref_list . ")";
         $notif1 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('notif', $where);
         $notif2 = $this->db($_SESSION[URL::SESSID]['user']['book'] + 1)->get_where('notif', $where);
         $notif = array_merge($notif1, $notif2);
      }

      if (count($numbers) > 0) {
         $no_list = "";
         foreach ($numbers as $r) {
            $no_list .= $r . ",";
         }
         $no_list = rtrim($no_list, ',');

         //OPERASI
         $where = $this->wCabang . " AND id_penjualan IN (" . $no_list . ")";
         $operasi1 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('operasi', $where);
         $operasi2 = $this->db($_SESSION[URL::SESSID]['user']['book'] + 1)->get_where('operasi', $where);
         $operasi = array_merge($operasi1, $operasi2);
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
         $this->writeLog('clearTuntas', 'INFO', 'Clear Tuntas Batch', ['count' => count($data), 'data' => $data]);
         foreach ($data as $a) {
            $this->tuntasOrder($a);
         }
      } else {
         $this->writeLog('clearTuntas', 'WARNING', 'No data to clear');
      }
   }

   public function operasi()
   {
      $karyawan = $_POST['f1'];
      $users = $this->db(0)->get_where_row("user", "id_user = " . $karyawan);
      $nm_karyawan = $users['nama_user'];
      $karyawan_code = strtoupper(substr($nm_karyawan, 0, 2)) . substr($karyawan, -1);
      $hp = $_POST['hp'];
      $text = $_POST['text'];
      $totalNotif = $_POST['inTotalNotif'];
      $text = str_replace("|STAFF|", $karyawan_code, $text);
      $text = str_replace("|TOTAL|", "\n" . $totalNotif, $text);

      $penjualan = $_POST['f2'];
      $operasi = $_POST['f3'];

      $this->writeLog('operasi', 'INFO', 'Proses Operasi', [
         'karyawan_id' => $karyawan,
         'penjualan_id' => $penjualan,
         'jenis_operasi' => $operasi,
         'hp' => $hp
      ]);

      $setOne = 'id_penjualan = ' . $penjualan . " AND jenis_operasi =" . $operasi;
      $where = $this->wCabang . " AND " . $setOne;

      $data_main = $this->db(date('Y'))->count_where('operasi', $where);

      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'id_penjualan' => $penjualan,
            'jenis_operasi' => $operasi,
            'id_user_operasi' => $karyawan,
            'insertTime' => $GLOBALS['now']
         ];
         $in = $this->db(date('Y'))->insert('operasi', $data);
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
      $data_main = $this->db(date('Y'))->count_where('notif', $whereNotif);
      if ($data_main < 1) {
         $data = [
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $penjualan,
            'phone' => $hp,
            'text' => $text,
            'status' => 5,
            'tipe' => 2
         ];
          $do = $this->db(date('Y'))->insert('notif', $data);
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
            $where = $this->wCabang . " AND id_penjualan = " . $penjualan;
            $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);

            //CEK SUDAH TERKIRIM BELUM
            $setOne = "no_ref = '" . $penjualan . "' AND proses <> '' AND tipe = 2";
            $where = $setOne;
            $data_main = $this->db(date('Y'))->count_where('notif', $where);
             if ($data_main < 1) {
                $this->writeLog('operasi', 'INFO', 'Sending Notif Ready', ['penjualan_id' => $penjualan]);
                $this->notifReadySend($penjualan, $totalNotif);
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

      $this->writeLog('surcas', 'INFO', 'Proses Surcas', [
        'jenis' => $jenis,
        'jumlah' => $jumlah,
        'user' => $user,
        'no_ref' => $id_transaksi
      ]);

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

      $this->writeLog('updateRak', 'INFO', 'Update Rak', [
         'mode' => $mode,
         'value' => $rak,
         'id' => $id
      ]);

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
      $where = $this->wCabang . " AND id_penjualan = " . $id;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);

      //CEK SUDAH TERKIRIM BELUM
      $setOne = "no_ref = '" . $id . "' AND proses <> '' AND tipe = 2";
      $where = $setOne;
      $data_main = $this->db(date('Y'))->count_where('notif', $where);
      if ($data_main < 1) {
         $this->notifReadySend($id, $totalNotif);
      }
   }

   public function tuntasOrder($ref)
   {
      $this->writeLog('tuntasOrder', 'INFO', 'Set Tuntas Order', ['ref' => $ref]);
      $set = ['tuntas' => 1];
      $where = $this->wCabang . " AND no_ref = " . $ref;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }

   public function notifReadySend($idPenjualan, $totalNotif = "")
   {
      $this->writeLog('notifReadySend', 'INFO', 'Sending WA Ready', ['id' => $idPenjualan]);
      $setOne = "no_ref = '" . $idPenjualan . "' AND tipe = 2";
      $where = $this->wCabang . " AND " . $setOne;
      $dm = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where_row('notif', $where);
      if (!isset($dm['phone'])) {
         $dm = $this->db($_SESSION[URL::SESSID]['user']['book'] + 1)->get_where_row('notif', $where);
      }
      $hp = $dm['phone'];
      $text = $dm['text'];
      $text = str_replace("|TOTAL|", "\n" . $totalNotif, $text);
      $res = $this->helper('Notif')->send_wa($hp, $text, false);

      $this->writeLog('notifReadySend', 'INFO', 'WA Send Result', ['id' => $idPenjualan, 'result' => $res]);

      $where2 = $this->wCabang . " AND no_ref = '" . $idPenjualan . "' AND tipe = 2";
      if ($res['status']) {
         $status = $res['data']['status'];
         $set = ['status' => 1, 'proses' => $status, 'id_api' => $res['data']['id']];
         $this->db($_SESSION[URL::SESSID]['user']['book'])->update('notif', $set, $where2);
      } else {
         $status = $res['data']['status'];
         $set = ['status' => 4, 'proses' => $status];
         $this->db($_SESSION[URL::SESSID]['user']['book'])->update('notif', $set, $where2);
      }
   }

   public function sendNotif($countMember, $tipe)
   {
      $id_harga = $_POST['id_harga'];
      $hp = $_POST['hp'];
      $noref = $_POST['ref'];
      $time =  $_POST['time'];
      $text = $_POST['text'];
      $idPelanggan = $_POST['idPelanggan'];

      $this->writeLog('sendNotif', 'INFO', 'Send Notif Manual', [
         'tipe' => $tipe,
         'hp' => $hp,
         'ref' => $noref
      ]);

      $text = str_replace("<sup>2</sup>", "²", $text);
      $text = str_replace("<sup>3</sup>", "³", $text);

      if ($countMember > 0) {
         $textMember = $this->textSaldoNotif($idPelanggan, $id_harga);
         $text = $text . $textMember;
      }

      $res = $this->helper("Notif")->send_wa($hp, $text, false);

      $this->writeLog('sendNotif', 'INFO', 'WA Manual Result', ['ref' => $noref, 'result' => $res]);

      $setOne = "no_ref = '" . $noref . "' AND tipe = 1";
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(date('Y'))->count_where('notif', $where);

      $this->model('Log')->write("[sendNotif] Checking existing data - no_ref: {$noref}, tipe: 1, count: {$data_main}, res_status: " . ($res['status'] ? 'true' : 'false'));

      if ($res['status']) {
         $vals = [
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $noref,
            'phone' => $hp,
            'text' => $text,
            'tipe' => $tipe,
            'id_api' => $res['data']['id'],
            'proses' => $res['data']['status']
         ];
      } else {
         $status = $res['data']['status'];
         $vals = [
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $noref,
            'phone' => $hp,
            'text' => $text,
            'tipe' => $tipe,
            'id_api' => '',
            'proses' => $status
         ];
      }

      if ($data_main < 1) {
         $this->model('Log')->write("[sendNotif] Inserting to database - vals: " . json_encode($vals));
         $do = $this->db(date('Y'))->insert('notif', $vals);
          
          if ($do['errno'] <> 0) {
             $this->writeLog('sendNotif', 'ERROR', 'Insert Notif Failed', ['error' => $do['error']]);
             $this->model('Log')->write("[sendNotif] Insert Notif Error: " . $do['error']);
             echo $do['error'];
          } else {
              echo 0;
          }
      }
   }

   public function textSaldoNotif($idPelanggan, $id_harga)
   {
      $saldo_akhir = $this->helper('Saldo')->saldoMember($idPelanggan, $id_harga);
      $unit = $this->helper('Saldo')->unit_by_idHarga($id_harga);
      $textSaldo = "\nM" . $id_harga . " " . number_format($saldo_akhir, 2) . $unit;
      return $textSaldo;
   }

   public function ambil()
   {
      $karyawan = $_POST['f1'];
      $id = $_POST['f2'];

      $this->writeLog('ambil', 'INFO', 'Proses Ambil Cucian', [
         'karyawan' => $karyawan,
         'id' => $id
      ]);

      $dateNow = date('Y-m-d H:i:s');
      $set = ['tgl_ambil' => $dateNow, 'id_user_ambil' => $karyawan];
      $setOne = "id_penjualan = '" . $id . "'";
      $where = $this->wCabang . " AND " . $setOne;
       $up = $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
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
      $this->writeLog('hapusRef', 'WARNING', 'Hapus Ref (BIN)', ['ref' => $ref, 'note' => $note]);
      $setOne = "no_ref = '" . $ref . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $set = ['bin' => 1, 'bin_note' => $note];
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }

   public function restoreRef()
   {
      $ref = $_POST['ref'];
      $this->writeLog('restoreRef', 'WARNING', 'Restore Ref (unBIN)', ['ref' => $ref]);
      $setOne = "no_ref = '" . $ref . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $set = ['bin' => 0];
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }
}
