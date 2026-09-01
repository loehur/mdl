<?php

class I extends Controller
{
   /**
    * Forward halaman view customer ke portal J.
    * Set false untuk rollback ke UI klasik tanpa menghapus kode di bawah.
    * Bypass sementara: tambahkan ?classic=1 (mis. I/123?classic=1).
    */
   private $forwardToJ = true;

   private function shouldForwardToJ()
   {
      if (!$this->forwardToJ) {
         return false;
      }
      $classic = $_GET['classic'] ?? '';
      return !($classic === '1' || $classic === 'true');
   }

   // Backward compatibility: /I/i/123 still works, forwards to index()
   public function i($pelanggan)
   {
      return $this->index($pelanggan);
   }

   public function index($pelanggan) //invoice tagihan total
   {
      if (!is_numeric($pelanggan)) {
         exit();
      }

      if ($this->shouldForwardToJ()) {
         header('Location: ' . URL::BASE_URL . 'J/tagihan/' . (int) $pelanggan);
         exit;
      }

      $this->public_data($pelanggan);
      $viewData = 'invoice/invoice_main';

      $operasi = array();
      $kas = array();
      $data_main = array();
      $data_terima = array();
      $data_kembali = array();
      $surcas = array();

      $data_tanggal = array();
      if (isset($_POST['Y'])) {
         $data_tanggal = array('bulan' => $_POST['m'], 'tahun' => $_POST['Y']);
      }

      if (count($data_tanggal) > 0) {
         $bulannya = $data_tanggal['tahun'] . "-" . $data_tanggal['bulan'];
         $where = "id_pelanggan = " . $pelanggan . " AND insertTime LIKE '" . $bulannya . "%' AND bin = 0 AND tuntas = 0 ORDER BY id_penjualan DESC";
      } else {
         $where = "id_pelanggan = " . $pelanggan . " AND bin = 0 AND tuntas = 0 ORDER BY id_penjualan DESC";
      }

      $data_main = $this->db(0)->get_where('sale', $where);

      $numbers = [];
      $refs = [];
      foreach ($data_main as $dm) {
         $i = substr($dm['insertTime'], 0, 4);
         $numbers[$dm['id_penjualan']] = "'" . $i . "'";
         $refs[$dm['no_ref']] = $i;
      }

      $where2 = "id_pelanggan = " . $pelanggan . " AND bin = 0 AND lunas = 1 GROUP BY id_harga";
      $list_paket = $this->db(0)->get_where('member', $where2);

      foreach ($numbers as $id => $book) {
         //OPERASI - FIX: use db(0)
         $where = "id_cabang = " . $this->id_cabang_p . " AND id_penjualan = '" . $id . "'";
         $ops = $this->db(0)->get_where('operasi', $where);
         if (count($ops) > 0) {
            foreach ($ops as $opsv) {
               array_push($operasi, $opsv);
            }
         }
      }

      $notifBon = [];
      foreach ($refs as $rf => $book) {
         // FIX: use db(0)
         $where = "id_cabang = " . $this->id_cabang_p . "  AND jenis_transaksi = 1 AND ref_transaksi = '" . $rf . "'";
         $ks = $this->db(0)->get_where('kas', $where);
         if (count($ks) > 0) {
            foreach ($ks as $ksv) {
               array_push($kas, $ksv);
            }
         }

         //SURCAS
         $where = "id_cabang = " . $this->id_cabang_p . "  AND no_ref = '" . $rf . "'";
         $sc = $this->db(0)->get_where('surcas', $where);
         if (count($sc) > 0) {
            foreach ($sc as $scv) {
               array_push($surcas, $scv);
            }
         }
      }

      if (!empty($refs)) {
         $refs_in = "'" . implode("','", array_keys($refs)) . "'";
         $notifBon = $this->db(0)->get_where(
            'notif',
            "id_cabang = " . (int) $this->id_cabang_p . " AND tipe = 1 AND no_ref IN ($refs_in)"
         );
      }

      $data_member = array();
      $where = "id_cabang = " . $this->id_cabang_p . "  AND bin = 0 AND id_pelanggan = " . $pelanggan . " AND lunas = 0";
      $order = "id_member DESC";
      $data_member = $this->db(0)->get_where_order('member', $where, $order);

      $numbersMember = array();
      $kasM = array();

      if (count($data_member) > 0) {
         $numbersMember = array_column($data_member, 'id_member');

         $where = "id_cabang = " . $this->id_cabang_p . "  AND bin = 0 AND id_pelanggan = " . $pelanggan . " ORDER BY insertTime ASC LIMIT 1";
         $yr_first = $this->db(0)->get_where_row('member', $where)['insertTime'];
         $i = substr($yr_first, 0, 4);

         foreach ($numbersMember as $nm) {
            $where = "id_cabang = " . $this->id_cabang_p . "  AND jenis_transaksi = 3 AND ref_transaksi = '" . $nm . "'";
            $kasMd = $this->db(0)->get_where('kas', $where);
            if (count($kasMd) > 0) {
               foreach ($kasMd as $ksmV) {
                  array_push($kasM, $ksmV);
               }
            }
         }

         foreach ($data_member as $key => $value) {
            $lunasNya = false;
            $totalNya = $value['harga'];
            $akumBayar = 0;
            foreach ($kasM as $ck) {
               // Only count successful payments (status_mutasi = 3)
               if ($value['id_member'] == $ck['ref_transaksi'] && $ck['status_mutasi'] == 3) {
                  $akumBayar += $ck['jumlah'];
                  // Removed break - need to sum ALL payments for this member
               }
            }
            if ($akumBayar >= $totalNya) {
               $lunasNya = true;
            }
            if ($lunasNya == true) {
               unset($data_member[$key]);
            }
         }
      }

      $finance_history = [];
      $c_history = array_merge($kas, $kasM);
      foreach ($c_history as $k) {
         if (!isset($k['ref_finance']) || $k['ref_finance'] == '') continue;
         $rf = $k['ref_finance'];
         if (!isset($finance_history[$rf])) {
            $finance_history[$rf] = [
               'ref_finance' => $rf,
               'total' => 0,
               'status' => $k['status_mutasi'],
               'metode' => $k['metode_mutasi'],
               'note' => $k['note'],
               'insertTime' => $k['insertTime'],
               'id_user' => (int) ($k['id_user'] ?? 0),
            ];
         }
         $finance_history[$rf]['total'] += intval($k['jumlah']);
         if (isset($k['insertTime']) && $k['insertTime'] > $finance_history[$rf]['insertTime']) {
            $finance_history[$rf]['insertTime'] = $k['insertTime'];
            $finance_history[$rf]['status'] = $k['status_mutasi'];
            $finance_history[$rf]['metode'] = $k['metode_mutasi'];
            $finance_history[$rf]['note'] = $k['note'];
            $finance_history[$rf]['id_user'] = (int) ($k['id_user'] ?? 0);
         }
      }

      $finance_history = array_filter($finance_history, function ($item) {
         return $item['status'] == 2;
      });

      // Note: Moota integration removed

      // FIX: calculate saldo directly with db(0)
      $q_cr = "id_client = '$pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3";
      $topup = $this->db(0)->sum_col_where('kas', 'jumlah', $q_cr) ?? 0;
      $q_cr_out = "id_client = '$pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3";
      $topup_out = $this->db(0)->sum_col_where('kas', 'jumlah', $q_cr_out) ?? 0;
      $q_use = "id_client = '$pelanggan' AND metode_mutasi = 3 AND jenis_mutasi = 2";
      $usage = $this->db(0)->sum_col_where('kas', 'jumlah', $q_use) ?? 0;
      $saldoTunai = $topup - $topup_out - $usage;

      $nonTunaiGuide = BankAccountsApi::accounts();

      $this->view($viewData, [
         'data_pelanggan' => $this->pelanggan_p,
         'dataTanggal' => $data_tanggal,
         'data_main' => $data_main,
         'operasi' => $operasi,
         'kas' => $kas,
         'kasM' => $kasM,
         'nonTunaiGuide' => $nonTunaiGuide,
         'dTerima' => $data_terima,
         'dKembali' => $data_kembali,
         'listPaket' => $list_paket,
         'data_member' => $data_member,
         "surcas" => $surcas,
         'saldoTunai' => $saldoTunai,
         'saldoTunai' => $saldoTunai,
         'finance_history' => $finance_history,
         'notif_bon' => $notifBon,
      ]);
   }

   /** Kirim nota WA dari halaman invoice publik (sama logika Antrian::sendNotif tipe=1). */
   public function send_nota($tipe = 1)
   {
      $id_pelanggan = $_POST['id_pelanggan'] ?? 0;
      $noref = trim((string) ($_POST['ref'] ?? ''));
      if (!is_numeric($id_pelanggan) || $noref === '') {
         echo 1;
         return;
      }

      $this->public_data($id_pelanggan);
      $db = $this->db(0);
      $refSql = "'" . $db->escape($noref) . "'";
      $ownsRef = $db->count_where(
         'sale',
         "no_ref = $refSql AND id_pelanggan = " . (int) $id_pelanggan . " AND id_cabang = " . (int) $this->id_cabang_p . " AND bin = 0"
      );
      if ($ownsRef < 1) {
         echo 1;
         return;
      }

      $this->id_cabang = (int) $this->id_cabang_p;
      $this->wCabang = 'id_cabang = ' . $this->id_cabang;

      $hp = $_POST['hp'] ?? ($this->pelanggan_p['nomor_pelanggan'] ?? '');
      $time = $_POST['time'] ?? date('Y-m-d H:i:s');
      $tipe = (int) $tipe;

      $waGen = $this->helper('WAGenerator');
      $jsonText = $waGen->get_nota($noref);
      $objText = json_decode($jsonText, true);
      $text = $objText['text'] ?? '';

      if (session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      $hpClean = preg_replace('/[^0-9]/', '', $hp);
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

      $userExists = $db->count_where('user', 'no_user IN (' . implode(', ', $hpVariations) . ')');
      $id_pelanggan = (int) $id_pelanggan;
      $this->helper('PelangganByPhone');
      $this->helper('NotifRecipient');
      $matchDigitsWa = PelangganByPhone::key($hpClean);
      $waOutClauses = ['id_pelanggan = ' . (int) $id_pelanggan];
      if ($matchDigitsWa !== '') {
         $waOutClauses[] = PelangganByPhone::likeSql($this->db(100)->escape($matchDigitsWa), 'phone');
      }
      $waOutCount = $this->db(100)->count_where(
         'wa_messages_out',
         '(' . implode(' OR ', $waOutClauses) . ')'
      );
      $waOutExists = is_numeric($waOutCount) ? (int) $waOutCount : 0;

      $setOne = "no_ref = $refSql AND tipe = " . (int) $tipe;
      $where = $this->wCabang . ' AND ' . $setOne;
      if ($db->count_where('notif', $where) > 0) {
         echo json_encode(['status' => 'exists', 'message' => 'Notifikasi sudah pernah dikirim']);
         return;
      }

      $id_notif = (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9);
      $insertResult = $db->insert('notif', [
         'id_notif' => $id_notif,
         'insertTime' => $time,
         'id_cabang' => $this->id_cabang,
         'no_ref' => $noref,
         'id_pelanggan' => (int) $id_pelanggan,
         'text' => $text,
         'tipe' => $tipe,
         'id_api' => '',
         'state' => 'pending',
      ]);
      if ($insertResult['errno'] <> 0) {
         echo json_encode(['status' => 'exists', 'message' => 'Notifikasi sedang diproses']);
         return;
      }

      if ($userExists > 0) {
         $template_name = 'free';
      } elseif ($waOutExists > 0) {
         $template_name = 'free';
      } else {
         $template_name = URL::TEMPLATE_NOTA;
      }
      $res = $this->helper('Notif')->send_wa((int) $id_pelanggan, $jsonText, $template_name);

      if (!$res['status'] && $template_name === 'free') {
         $apiPayload = $res['data'] ?? [];
         $cswExpired = !empty($apiPayload['data']['csw_expired'])
            || (isset($apiPayload['message']) && stripos((string) $apiPayload['message'], 'CSW') !== false)
            || (isset($apiPayload['message']) && stripos((string) $apiPayload['message'], 'Customer Service Window') !== false)
            || (isset($res['error']) && stripos((string) $res['error'], '24 jam') !== false);
         if ($cswExpired && $waOutExists === 0) {
            $res = $this->helper('Notif')->send_wa((int) $id_pelanggan, $jsonText, URL::TEMPLATE_NOTA);
         }
      }

      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');

      if ($res['status']) {
         $db->update('notif', ['id_api' => $idApi, 'state' => 'sent'], $where);
         $this->helper('Notif')->deleteMatchingWaOutQueue((int) $id_pelanggan, $text);
         echo 0;
      } else {
         $db->update('notif', ['state' => 'pending'], $where);
         $this->model('Log')->write('[I::send_nota] WA gagal — Ref: ' . $noref . ' | HP: ' . $hp);
         echo 0;
      }
   }

   public function m($pelanggan, $id_harga) //riwayat member
   {
      if (!is_numeric($pelanggan) || !is_numeric($id_harga)) {
         exit();
      }

      $pelanggan = (int) $pelanggan;
      $id_harga = (int) $id_harga;

      if ($this->shouldForwardToJ()) {
         header('Location: ' . URL::BASE_URL . 'J/paketDetail/' . $pelanggan . '/' . $id_harga);
         exit;
      }

      // Slim master data — hanya yang dipakai member_history
      $this->dLayanan = $this->db(0)->get('layanan');
      $this->dDurasi = $this->db(0)->get('durasi');
      $this->dPenjualan = $this->db(0)->get('penjualan_jenis');
      $this->dSatuan = $this->db(0)->get('satuan');
      $this->harga = $this->db(0)->get_order('harga', 'sort ASC');
      $this->itemGroup = $this->db(0)->get('item_group');
      $this->pelanggan_p = $this->db(0)->get_where_row('pelanggan', 'id_pelanggan = ' . $pelanggan);
      if (!$this->pelanggan_p) {
         exit();
      }
      $this->id_cabang_p = $this->pelanggan_p['id_cabang'];

      $data_main = $this->db(0)->get_cols_where(
         'sale',
         'id_penjualan, id_penjualan_jenis, qty, min_order, insertTime',
         "id_pelanggan = $pelanggan AND id_harga = $id_harga AND bin = 0 AND member = 1 ORDER BY insertTime ASC, id_penjualan ASC"
      );
      if (!is_array($data_main) || isset($data_main['errno'])) {
         $data_main = [];
      }

      $data_main2 = $this->db(0)->get_cols_where(
         'member',
         'id_member, qty, insertTime',
         "id_pelanggan = $pelanggan AND id_harga = $id_harga AND bin = 0 AND lunas = 1 ORDER BY insertTime ASC, id_member ASC"
      );
      if (!is_array($data_main2) || isset($data_main2['errno'])) {
         $data_main2 = [];
      }

      $this->view('member/member_history', [
         'data_pelanggan' => $this->pelanggan_p,
         'data_main' => $data_main,
         'data_main2' => $data_main2,
         'id_harga' => $id_harga,
      ]);
   }

   public function s($pelanggan) // saldo deposit pelanggan
   {
      if (!is_numeric($pelanggan)) {
         exit();
      }

      if ($this->shouldForwardToJ()) {
         header('Location: ' . URL::BASE_URL . 'J/saldo/' . (int) $pelanggan);
         exit;
      }

      $this->public_data($pelanggan);

      $data = array();
      $where = "id_client = " . $pelanggan . " AND status_mutasi = 3 AND ((jenis_transaksi = 1 AND metode_mutasi = 3) OR (jenis_transaksi = 3 AND metode_mutasi = 3) OR jenis_transaksi = 6)";
      $cols = "id_kas, id_client, jumlah, metode_mutasi, note, insertTime, jenis_mutasi, jenis_transaksi";

      // FIX: use db(0) directly
      $kasMd = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      if (count($kasMd) > 0) {
         foreach ($kasMd as $ksmV) {
            array_push($data, $ksmV);
         }
      }

      $saldo = 0;
      foreach ($data as $key => $v) {
         if ($v['jenis_mutasi'] == 1) {
            $saldo += $v['jumlah'];
         } else {
            $saldo -= $v['jumlah'];
         }
         $data[$key]['saldo'] = $saldo;
      }

      $viewData = 'saldoTunai/member_history';

      $this->view($viewData, [
         'data_pelanggan' => $this->pelanggan_p,
         'data_main' => $data,
      ]);
   }

   function q() //gambar qris
   {
      $qrisUrl = URL::IN_ASSETS . 'img/qris/qris_1.jpeg';
      $this->view('qris/qris', ['qris_url' => $qrisUrl]);
   }

   public function bayar()
   {
      if (!isset($_POST['id_pelanggan']) || !isset($_POST['rekap'])) {
         echo "Data incomplete";
         exit();
      }

      $id_pelanggan = $_POST['id_pelanggan'];
      $this->public_data($id_pelanggan); // Load cabang data
      
      $rekap = $_POST['rekap']; // Array [ref => amount]
      $metode_bayar = isset($_POST['metode']) ? trim((string) $_POST['metode']) : '';

      // J/public: Non-tunai (2) atau potong Saldo Deposit (3). Tidak ada cash.
      $metode = 2;
      $note = $metode_bayar;
      if ($metode_bayar === 'SALDO' || strcasecmp($metode_bayar, 'Saldo Deposit') === 0) {
         $metode = 3;
         $note = 'SALDO';
      }

      $dibayar = 0; // full amount per rekap item when dibayar=0
      
      // KasModel expects rekap as [ref => amount]
      // Ensure rekap is in correct format
      
      $karyawan = 0; // System/Self
      $id_cabang = $this->id_cabang_p; 

      $res = $this->model('KasModel')->bayarMulti($rekap, $dibayar, $id_pelanggan, $id_cabang, $karyawan, $metode, $note);
      echo $res;
   }

   public function payment_gateway_order($ref_finance)
   {
      $this->payment_gateway_logic($ref_finance, true);
   }

   public function payment_gateway_check_status($ref_finance)
   {
      $this->payment_gateway_status_db($ref_finance, true);
   }

   public function payment_gateway_status_poll($ref_finance)
   {
      $this->payment_gateway_status_db($ref_finance, true);
   }

   public function cancel_payment($ref_finance)
   {
      $this->cancel_payment_logic($ref_finance, true);
   }
}
