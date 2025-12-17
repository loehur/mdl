<?php

class SaldoTunai extends Controller
{

   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function tampil_rekap($all = true, $id_client = 0)
   {
      $data_operasi = ['title' => 'List Deposit Tunai'];
      $viewData = 'saldoTunai/viewRekap';

      if ($all == true) {
         $this->view('layout', ['data_operasi' => $data_operasi]);
         $where = $this->wCabang . " AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
         $where2 = $this->wCabang . " AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
      } else {
         $where = $this->wCabang . " AND id_client = " . $id_client . " AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
         $where2 = $this->wCabang . " AND id_client = " . $id_client . " AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
      }

      $cols = "id_client, SUM(jumlah) as saldo";
      $data = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('kas', $cols, $where, 1);
      $data3 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('kas', $cols, $where2, 1);

      $saldo = [];
      $pakai = [];

      foreach ($data as $a) {
         $idPelanggan = $a['id_client'];
         $saldo[$idPelanggan] = $a['saldo'];
         $where = $this->wCabang . " AND id_client = " . $idPelanggan . " AND metode_mutasi = 3 AND jenis_mutasi = 2";
         $cols = "SUM(jumlah) as pakai";
         $data2 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('kas', $cols, $where, 0);
         if (isset($data2['pakai'])) {
            $saldoPengurangan = $data2['pakai'];
            $pakai[$idPelanggan] = $saldoPengurangan;
         } else {
            $pakai[$idPelanggan] = 0;
         }
      }

      foreach ($data3 as $a2) {
         $idPelanggan = $a2['id_client'];
         if (isset($pakai[$idPelanggan])) {
            $pakai[$idPelanggan] += $a2['saldo'];
         } else {
            $pakai[$idPelanggan] = $a2['saldo'];
         }
      }

      $this->view($viewData, ['saldo' => $saldo, 'pakai' => $pakai, 'client' => $id_client]);
   }

   public function tambah($get_pelanggan = 0)
   {
      if ($get_pelanggan <> 0) {
         $pelanggan = $get_pelanggan;
      } else if (isset($_POST['p'])) {
         $pelanggan = $_POST['p'];
      } else {
         $pelanggan = 0;
      }

      $this->tampilkanMenu($pelanggan);
   }

   public function tampilkanMenu($pelanggan)
   {
      $view = 'saldoTunai/memberMenu';
      $data_operasi = ['title' => '(+) Deposit Tunai'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, ['data_operasi' => $data_operasi, 'pelanggan' => $pelanggan]);
   }

   public function tampilkan($id_client)
   {
      $viewData = 'saldoTunai/viewData';
      $where = $this->wCabang . " AND id_client = " . $id_client . " AND jenis_transaksi = 6 ORDER BY id_kas DESC";
      $cols = "id_kas, jenis_mutasi, id_client, id_user, jumlah, metode_mutasi, status_mutasi, note, insertTime";
      $data = [];
      for ($y = URL::FIRST_YEAR; $y <= date('Y'); $y++) {
         $data_ = $this->db($y)->get_cols_where('kas', $cols, $where, 1);
         if (count($data_) > 0) {
            foreach ($data_ as $dk) {
               array_push($data, $dk);
            }
         }
      }

      $notif = [];

      foreach ($data as $dme) {

         //NOTIF SALDO TUNAI
         $where = $this->wCabang . " AND tipe = 4 AND no_ref = '" . $dme['id_kas'] . "'";
         $nm = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where_row('notif', $where);
         if (count($nm) > 0) {
            array_push($notif, $nm);
         }
         $nm = $this->db($_SESSION[URL::SESSID]['user']['book'] + 1)->get_where_row('notif', $where);
         if (count($nm) > 0) {
            array_push($notif, $nm);
         }
      }

      $this->view($viewData, [
         'data_' => $data,
         'pelanggan' => $id_client,
         "notif" => $notif
      ]);
   }

   public function restoreRef()
   {
      $id = $_POST['id'];
      $setOne = "id_member = '" . $id . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $set = ['bin' => 0];
      $this->db(0)->update('member', $set, $where);
   }

   public function orderPaket($pelanggan, $id_harga)
   {
      if ($id_harga <> 0) {
         $where = "id_harga = " . $id_harga;
         $data['main'] = $this->db(0)->get_where('harga_paket', $where);
      } else {
         $data['main'] = $this->db(0)->get('harga_paket');
      }
      $data['pelanggan'] = $pelanggan;
      $this->view('saldoTunai/formOrder', $data);
   }

   public function deposit($id_pelanggan)
   {
      $jumlah = $_POST['jumlah'];
      $id_user = $_POST['staf'];
      $metode = $_POST['metode'];
      $note = $_POST['noteBayar'];

      if (strlen($note) == 0) {
         switch ($metode) {
            case 2:
               $note = "Non_Tunai";
               break;
            default:
               $note = "";
               break;
         }
      }

      $status_mutasi = 3;
      switch ($metode) {
         case "2":
            $status_mutasi = 2;
            break;
         default:
            $status_mutasi = 3;
            break;
      }

      if ($this->id_privilege == 100) {
         $status_mutasi = 3;
      }

      $today = date('Y-m-d');
      $setOne = "id_client = '" . $id_pelanggan . "' AND jumlah = " . $jumlah . " AND jenis_transaksi = 6 AND insertTime LIKE '" . $today . "%'";
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(date('Y'))->count_where('kas', $where);

      $ref_f = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'jenis_mutasi' => 1,
            'jenis_transaksi' => 6,
            'metode_mutasi' => $metode,
            'note' => $note,
            'status_mutasi' => $status_mutasi,
            'jumlah' => $jumlah,
            'id_user' => $id_user,
            'id_client' => $id_pelanggan,
            'ref_finance' => $ref_f
         ];
         $this->db(date('Y'))->insert('kas', $data);
      }
      $this->tambah($id_pelanggan);
   }

   public function refund($id_pelanggan)
   {
      $this->session_cek(1);
      $jumlah = $_POST['jumlah'];
      $id_user = $_POST['staf'];
      $metode = $_POST['metode'];
      $note = $_POST['noteBayar'];

      if (strlen($note) == 0) {
         switch ($metode) {
            case 2:
               $note = "Non_Tunai";
               break;
            default:
               $note = "";
               break;
         }
      }

      $status_mutasi = 3;
      switch ($metode) {
         case "2":
            $status_mutasi = 2;
            break;
         default:
            $status_mutasi = 3;
            break;
      }

      if ($this->id_privilege == 100) {
         $status_mutasi = 3;
      }

      $today = date('Y-m-d');
      $setOne = "id_client = '" . $id_pelanggan . "' AND jumlah = " . $jumlah . " AND jenis_transaksi = 6 AND insertTime LIKE '" . $today . "%'";
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(date('Y'))->count_where('kas', $where);

      $ref_f = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'jenis_mutasi' => 2,
            'jenis_transaksi' => 6,
            'metode_mutasi' => $metode,
            'note' => $note,
            'status_mutasi' => $status_mutasi,
            'jumlah' => $jumlah,
            'id_user' => $id_user,
            'id_client' => $id_pelanggan,
            'ref_finance' => $ref_f
         ];
         $this->db(date('Y'))->insert('kas', $data);
      }
      $this->tambah($id_pelanggan);
   }

   public function sendNotifDeposit()
   {
      $hp = $_POST['hp'];
      $noref = $_POST['ref'];
      $time =  $_POST['time'];
      $text = $_POST['text'];

      $cols =  'insertTime, id_cabang, no_ref, phone, text, id_api, proses, tipe';
      $res = $this->helper('Notif')->send_wa($hp, $text, false);

      $setOne = "no_ref = '" . $noref . "' AND tipe = 4";
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(date('Y'))->count_where('notif', $where);

      if ($res['status']) {
         $status = $res['data']['status'];
         $vals = [
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $noref,
            'phone' => $hp,
            'text' => $text,
            'id_api' => $res['data']['id'],
            'proses' => $status,
            'tipe' => 4
         ];
      } else {
         $status = $res['data']['status'];
         $vals = [
            'insertTime' => $time,
            'id_cabang' => $this->id_cabang,
            'no_ref' => $noref,
            'phone' => $hp,
            'text' => $text,
            'id_api' => '',
            'proses' => $status,
            'tipe' => 4
         ];
      }

      if ($data_main < 1) {
         $do = $this->db(date('Y'))->insert('notif', $vals);
         if ($do['errno'] <> 0) {
            echo $do['error'];
         } else {
            echo 0;
         }
      }
   }
}
