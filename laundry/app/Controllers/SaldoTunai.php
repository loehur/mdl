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
      $data = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      $data3 = $this->db(0)->get_cols_where('kas', $cols, $where2, 1);

      $saldo = [];
      $pakai = [];

      // ✅ OPTIMIZED: Build saldo array from first query
      foreach ($data as $a) {
         $saldo[$a['id_client']] = $a['saldo'];
         $pakai[$a['id_client']] = 0; // Initialize
      }

      // ✅ OPTIMIZED: Get ALL usage in ONE query instead of N queries!
      if (count($data) > 0) {
         $wherePakai = $this->wCabang . " AND metode_mutasi = 3 AND jenis_mutasi = 2";
         $colsPakai = "id_client, SUM(jumlah) as pakai";
         $dataPakai = $this->db(0)->get_cols_where('kas', $colsPakai, $wherePakai . " GROUP BY id_client", 1);
         
         // Map usage data
         foreach ($dataPakai as $dp) {
            if (isset($saldo[$dp['id_client']])) {
               $pakai[$dp['id_client']] = $dp['pakai'];
            }
         }
      }

      // ✅ OPTIMIZED: Process data3 (faster with pre-initialized array)
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
      $where = $this->wCabang . " AND id_client = " . $id_client . " AND jenis_transaksi = 6 ORDER BY insertTime DESC, id_kas DESC";
      $cols = "id_kas, jenis_mutasi, id_client, id_user, jumlah, metode_mutasi, status_mutasi, note, insertTime";
      // FIX: use db(0) directly
      $data = [];
      $data_ = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      if (count($data_) > 0) {
         foreach ($data_ as $dk) {
            array_push($data, $dk);
         }
      }

      $notif = [];

      foreach ($data as $dme) {

         //NOTIF SALDO TUNAI - FIX: use db(0) only
         $where = $this->wCabang . " AND tipe = 4 AND no_ref = '" . $dme['id_kas'] . "'";
         $nm = $this->db(0)->get_where_row('notif', $where);
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
      $data_main = $this->db(0)->count_where('kas', $where);

      $ref_f = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      if ($data_main < 1) {
         $data = [
            'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
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
         $this->db(0)->insert('kas', $data);
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
      $data_main = $this->db(0)->count_where('kas', $where);

      $ref_f = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      if ($data_main < 1) {
         $data = [
            'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
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
         $this->db(0)->insert('kas', $data);
      }
      $this->tambah($id_pelanggan);
   }

   public function sendNotifDeposit()
   {
      $noref = $_POST['ref'] ?? '';
      $time = $_POST['time'] ?? '';
      $text = $_POST['text'] ?? '';

      if (session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      $kasRow = $this->db(0)->get_where_row('kas', 'id_kas = ' . (int) $noref);
      $id_pelanggan_kas = (int) ($kasRow['id_client'] ?? 0);
      if ($id_pelanggan_kas <= 0) {
         $this->helper('PelangganByPhone');
         $hpFallback = $_POST['hp'] ?? '';
         $id_pelanggan_kas = (int) (new PelangganByPhone())->id($hpFallback);
      }
      if ($id_pelanggan_kas <= 0) {
         echo 0;
         return;
      }

      // Sama pola dengan Member::sendNotifDeposit / Antrian::sendNotif
      $setOne = "no_ref = '" . $noref . "' AND tipe = 4";
      $where = $this->wCabang . " AND " . $setOne;
      $existingNotif = $this->db(0)->count_where('notif', $where);

      if ($existingNotif > 0) {
         echo 0;
         return;
      }

      $id_notif = (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9);
      $pendingData = [
         'id_notif' => $id_notif,
         'insertTime' => $time,
         'id_cabang' => $this->id_cabang,
         'no_ref' => $noref,
         'id_pelanggan' => $id_pelanggan_kas,
         'text' => $text,
         'tipe' => 4,
         'id_api' => '',
         'state' => 'pending',
      ];

      $insertResult = $this->db(0)->insert('notif', $pendingData);
      if (isset($insertResult['errno']) && $insertResult['errno'] <> 0) {
         echo 0;
         return;
      }

      // `false` sebagai template_name salah: ikuti Member (teks bebas = 'free')
      $res = $this->helper('Notif')->send_wa($id_pelanggan_kas, $text, 'free');

      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');
      $idApiStr = is_scalar($idApi) ? (string) $idApi : '';

      $whereNotif = "id_notif = '" . $id_notif . "'";
      if ($res['status']) {
         $this->db(0)->update('notif', [
            'id_api' => $idApiStr,
            'state' => 'sent',
         ], $whereNotif);
      } else {
         $this->model('Log')->write(
            __CLASS__ . '::sendNotifDeposit | WA gagal | id_pelanggan: ' . $id_pelanggan_kas . ' | ' . ($res['error'] ?? '') . ' | ' . json_encode($res)
         );
      }

      echo 0;
   }
}
