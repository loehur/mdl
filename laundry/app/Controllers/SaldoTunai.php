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

   /**
    * WA deposit tunai — selaras Antrian::sendNotif (API WhatsApp/send, free vs template, fallback CSW).
    * Simpan notif tipe 4 dulu, lalu kirim; update state + id_api.
    */
   public function sendNotifDeposit()
   {
      $hp = trim((string) ($_POST['hp'] ?? ''));
      $noref = trim((string) ($_POST['ref'] ?? ''));
      $time = trim((string) ($_POST['time'] ?? ''));
      $text = (string) ($_POST['text'] ?? '');
      $id_pelanggan = (int) ($_POST['id_pelanggan'] ?? 0);

      if ($hp === '' || $noref === '' || $text === '') {
         $this->model('Log')->write(__CLASS__ . '::sendNotifDeposit | batal: hp/ref/teks kosong');
         echo 'Data WA tidak lengkap (nomor / referensi / teks).';
         return;
      }

      if ($time === '') {
         $time = date('Y-m-d H:i:s');
      }

      if (session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      $db = $this->db(0);
      $norefEsc = $db->escape($noref);
      $setOne = "no_ref = '" . $norefEsc . "' AND tipe = 4";
      $whereCheck = $this->wCabang . " AND " . $setOne;
      $existingNotif = $db->count_where('notif', $whereCheck);
      if (is_array($existingNotif)) {
         $this->model('Log')->write(__CLASS__ . '::sendNotifDeposit | count_where gagal: ' . json_encode($existingNotif));
         echo 'Gagal cek data notifikasi.';
         return;
      }
      if ((int) $existingNotif > 0) {
         echo 0;
         return;
      }

      $plainText = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      $plainText = str_replace(["\r\n", "\r"], "\n", $plainText);
      if ($plainText === '') {
         echo 'Teks WA kosong.';
         return;
      }

      // --- Pola Antrian::sendNotif: variasi nomor + user internal + wa_messages_out ---
      $hpClean = preg_replace('/[^0-9]/', '', $hp);
      if ($hpClean === '') {
         echo 'Nomor WA tidak valid.';
         return;
      }

      $hpVariations = [];
      if (substr($hpClean, 0, 2) === '62') {
         $hpVariations[] = "'+62" . substr($hpClean, 2) . "'";
         $hpVariations[] = "'" . $hpClean . "'";
         $hpVariations[] = "'0" . substr($hpClean, 2) . "'";
         $hpVariations[] = "'" . substr($hpClean, 2) . "'";
      } elseif (substr($hpClean, 0, 1) === '0') {
         $hpVariations[] = "'+62" . substr($hpClean, 1) . "'";
         $hpVariations[] = "'62" . substr($hpClean, 1) . "'";
         $hpVariations[] = "'" . $hpClean . "'";
         $hpVariations[] = "'" . substr($hpClean, 1) . "'";
      } else {
         $hpVariations[] = "'+62" . $hpClean . "'";
         $hpVariations[] = "'62" . $hpClean . "'";
         $hpVariations[] = "'0" . $hpClean . "'";
         $hpVariations[] = "'" . $hpClean . "'";
      }

      $whereUser = "no_user IN (" . implode(', ', $hpVariations) . ")";
      $userExists = $db->count_where('user', $whereUser);
      $userExists = is_array($userExists) ? 0 : (int) $userExists;

      $matchDigitsWa = (strlen($hpClean) >= 9) ? substr($hpClean, -9) : $hpClean;
      $whereWaOut = "REPLACE(REPLACE(phone, '+', ''), '-', '') LIKE '%" . $matchDigitsWa . "'";
      $waOutCount = $this->db(100)->count_where('wa_messages_out', $whereWaOut);
      $waOutExists = is_numeric($waOutCount) ? (int) $waOutCount : 0;

      $id_notif = (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9);
      $pendingData = [
         'id_notif' => $id_notif,
         'insertTime' => $time,
         'id_cabang' => $this->id_cabang,
         'no_ref' => $noref,
         'phone' => $hp,
         'text' => $plainText,
         'tipe' => 4,
         'id_api' => '',
         'state' => 'pending',
      ];

      $insertResult = $db->insert('notif', $pendingData);
      if (isset($insertResult['errno']) && $insertResult['errno'] <> 0) {
         echo $insertResult['error'] ?? 'Gagal simpan notif';
         return;
      }

      $cleanOrderList = str_replace(["\n", "\r", "\t"], " | ", $plainText);
      $cleanOrderList = preg_replace('/\s{2,}/', ' ', $cleanOrderList);
      $cleanOrderList = trim($cleanOrderList, ' |');
      $invoiceTail = $id_pelanggan > 0 ? ('/I/s/' . $id_pelanggan) : '';
      $templateParams = [
         'customer' => '*INFO DEPOSIT TUNAI*',
         'order_list' => '| ' . $cleanOrderList . ' |',
         'invoice_link' => rtrim(URL::HOST_URL, '/') . $invoiceTail . ' _Deposit tunai MDL_',
      ];
      $jsonText = json_encode([
         'text' => $plainText,
         'template_params' => $templateParams,
      ], JSON_UNESCAPED_UNICODE);

      if ($userExists > 0) {
         $template_name = 'free';
      } elseif ($waOutExists > 0) {
         $this->model('Log')->write(__CLASS__ . '::sendNotifDeposit | wa_messages_out ada → free | ref=' . $noref);
         $template_name = 'free';
      } else {
         $template_name = URL::TEMPLATE_NOTA;
      }

      if ($template_name === 'free') {
         $res = $this->helper('Notif')->send_wa($hp, $plainText, 'free');
      } else {
         $res = $this->helper('Notif')->send_wa($hp, $jsonText, URL::TEMPLATE_NOTA);
      }

      if (!$res['status'] && $template_name === 'free') {
         $apiPayload = $res['data'] ?? [];
         $cswExpired = !empty($apiPayload['data']['csw_expired'])
            || (isset($apiPayload['message']) && stripos((string) $apiPayload['message'], 'CSW') !== false)
            || (isset($apiPayload['message']) && stripos((string) $apiPayload['message'], 'Customer Service Window') !== false)
            || (isset($res['error']) && stripos((string) $res['error'], '24 jam') !== false);
         if ($cswExpired) {
            if ($waOutExists > 0) {
               $this->model('Log')->write(__CLASS__ . '::sendNotifDeposit | CSW tutup, tidak fallback template (sudah pernah wa out) | ref=' . $noref);
            } else {
               $this->model('Log')->write(__CLASS__ . '::sendNotifDeposit | CSW tutup → fallback template | ref=' . $noref);
               $res = $this->helper('Notif')->send_wa($hp, $jsonText, URL::TEMPLATE_NOTA);
            }
         }
      }

      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');
      $idApiStr = is_scalar($idApi) ? (string) $idApi : '';

      $whereUp = $this->wCabang . " AND no_ref = '" . $norefEsc . "' AND tipe = 4";
      if ($res['status']) {
         $db->update('notif', [
            'id_api' => $idApiStr,
            'state' => 'sent',
         ], $whereUp);
      } else {
         $db->update('notif', ['state' => 'pending'], $whereUp);
         $this->model('Log')->write(
            __CLASS__ . '::sendNotifDeposit | WA gagal (pending) | HP: ' . $hp . ' | ' . ($res['error'] ?? '') . ' | ' . json_encode($res)
         );
      }

      echo 0;
   }
}
