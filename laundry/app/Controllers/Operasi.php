<?php

class Operasi extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function i($modeOperasi, $id_pelanggan)
   {
      $viewData = 'operasi/form_proses';
      $formData = array('id_pelanggan' => $id_pelanggan, 'mode' => $modeOperasi);
      switch ($modeOperasi) {
         case 0:
            //DALAM PROSES
            $data_operasi = ['title' => 'Operasi Order Proses'];
            break;
         case 1:
            //TUNTAS
            $data_operasi = ['title' => 'Operasi Order Tuntas'];
            break;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData, $formData);
   }

   public function loadData($id_pelanggan, $mode = 0)
   {
      $formData = [];
      $data_main = [];
      $idOperan = "";
      $modeView = 1;

      $pelanggan = [];

      $pelanggan = $this->pelanggan[$id_pelanggan];

      if ($mode == 1) {
         $whereSale = $this->wCabang . " AND id_pelanggan = $id_pelanggan AND bin = 0 AND tuntas = " . $mode . " ORDER BY id_penjualan DESC";
         $modeView = 2;
      } else {
         $whereSale = $this->wCabang . " AND id_pelanggan = $id_pelanggan AND bin = 0 AND tuntas = 0 ORDER BY id_penjualan DESC";
      }

      $data_main = $this->db(0)->get_where('sale', $whereSale);
      $data_main2 = $this->db(0)->get_where('sale', $whereSale, 'no_ref', 1);

      $viewData = 'operasi/view_load';

      $sale_ids = [];
      $sale_refs = [];
      foreach ($data_main as $dm) {
         $sale_ids[] = "'" . $dm['id_penjualan'] . "'";
         $sale_refs[] = $dm['no_ref'];
      }

      $operasi = [];
      if (!empty($sale_ids)) {
         $where_o = $this->wCabang . " AND id_penjualan IN (" . implode(',', $sale_ids) . ")";
         $operasi = $this->db(0)->get_where('operasi', $where_o);
      }

      $notifSelesai = [];
      if (!empty($sale_ids)) {
         $where_n = $this->wCabang . " AND tipe = 2 AND no_ref IN ('" . implode("','", $sale_ids) . "')";
         $notifSelesai = $this->db(0)->get_where('notif', $where_n);
      }

      $kas = [];
      $notifBon = [];
      $surcas = [];
      if (!empty($sale_refs)) {
         $where_kas = $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi IN ('" . implode("','", $sale_refs) . "')";
         $kas = $this->db(0)->get_where('kas', $where_kas);

         $where_notif = $this->wCabang . " AND tipe = 1 AND no_ref IN ('" . implode("','", $sale_refs) . "')";
         $notifBon = $this->db(0)->get_where('notif', $where_notif);

         $where_surcas = $this->wCabang . " AND no_ref IN ('" . implode("','", $sale_refs) . "')";
         $surcas = $this->db(0)->get_where('surcas', $where_surcas);
      }

      $finance_history = [];
      foreach ($kas as $k) {
         if (!isset($k['ref_finance']) || $k['ref_finance'] == '') continue;
         $rf = $k['ref_finance'];
         if (!isset($finance_history[$rf])) {
            $finance_history[$rf] = [
               'ref_finance' => $rf,
               'total' => 0,
               'status' => $k['status_mutasi'],
               'metode' => $k['metode_mutasi'],
               'note' => $k['note'],
               'insertTime' => $k['insertTime']
            ];
         }
         $finance_history[$rf]['total'] += intval($k['jumlah']);
         if (isset($k['insertTime']) && $k['insertTime'] > $finance_history[$rf]['insertTime']) {
            $finance_history[$rf]['insertTime'] = $k['insertTime'];
            $finance_history[$rf]['status'] = $k['status_mutasi'];
            $finance_history[$rf]['metode'] = $k['metode_mutasi'];
            $finance_history[$rf]['note'] = $k['note'];
         }
      }

      $finance_history = array_filter($finance_history, function ($item) {
         return $item['status'] == 2;
      });

      foreach ($finance_history as $key => $fh) {
         $check_moota = $this->db(0)->get_where_row("wh_moota", "trx_id = '" . $fh['ref_finance'] . "'");
         if (isset($check_moota['amount']) && $check_moota['amount'] > 0) {
            $finance_history[$key]['total'] = $check_moota['amount'];
         }
      }

      //MEMBER
      $data_member = [];
      $whereMember = $this->wCabang . " AND bin = 0 AND id_pelanggan = " . $id_pelanggan . " AND lunas = 0";
      $data_member = $this->db(0)->get_where('member', $whereMember);

      $notif_member = [];
      $kas_member = [];
      foreach ($data_member as $dme) {
         $harga = $dme['harga'];
         $idm = $dme['id_member'];
         $historyBayar[$dme['id_member']] = [];

         $whereKasMember = $this->wCabang . " AND jenis_transaksi = 3 AND ref_transaksi = '" . $dme['id_member'] . "'";
         $km = $this->db(0)->get_where('kas', $whereKasMember);
         if (count($km) > 0) {
            if (!isset($kas_member[$idm])) {
               $kas_member[$idm] = [];
            }
            foreach ($km as $kmv) {
               array_push($kas_member[$idm], $kmv);
            }
         }

         $whereNotifMember = $this->wCabang . " AND tipe = 3 AND no_ref = '" . $dme['id_member'] . "'";
         $nm = $this->db(0)->get_where_row('notif', $whereNotifMember);
         if (count($nm) > 0) {
            array_push($notif_member, $nm);
         }

         if (isset($kas_member[$idm])) {
            foreach ($kas_member[$idm] as $k) {
               if ($k['ref_transaksi'] == $idm && $k['status_mutasi'] == 3) {
                  array_push($historyBayar[$idm], $k['jumlah']);
               }
               $totalBayar = array_sum($historyBayar[$idm]);
               if ($totalBayar >= $harga) {
                  $lunas = $this->db(0)->update('member', ['lunas' => 1], 'id_member = ' . $idm);
                  if ($lunas['errno'] <> 0) {
                     $this->model('Log')->write("[loadData] ERROR UPDATE PAID, MEMBER ID " . $idm . " Error: " . $lunas['error']);
                     echo "ERROR UPDATE PAID, MEMBER ID " . $idm;
                  }
               }
            }
         }
      }

      //SALDO DEPOSIT
      //SALDO DEPOSIT
      //$sisaSaldo = $this->helper('Saldo')->getSaldoTunai($id_pelanggan);
      
      // FIX: use db(0) directly
      $q_cr = "id_client = '$id_pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3";
      $topup = $this->db(0)->sum_col_where('kas', 'jumlah', $q_cr) ?? 0;

      $q_cr_out = "id_client = '$id_pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3";
      $topup_out = $this->db(0)->sum_col_where('kas', 'jumlah', $q_cr_out) ?? 0;

      $q_use = "id_client = '$id_pelanggan' AND metode_mutasi = 3 AND jenis_mutasi = 2";
      $usage = $this->db(0)->sum_col_where('kas', 'jumlah', $q_use) ?? 0;

      $sisaSaldo = $topup - $topup_out - $usage;

      $users = $this->db(0)->get("user", "id_user");
      $this->view($viewData, [
         'modeView' => $modeView,
         'pelanggan' => $pelanggan,
         'data_main' => $data_main2,
         'operasi' => $operasi,
         'kas' => $kas,
         'notif_bon' => $notifBon,
         'notif_selesai' => $notifSelesai,
         'notif_member' => $notif_member,
         'formData' => $formData,
         'idOperan' => $idOperan,
         "surcas" => $surcas,
         'data_member' => $data_member,
         'kas_member' => $kas_member,
         'saldoTunai' => $sisaSaldo,
         'users' => $users,
         'finance_history' => $finance_history
      ]);
   }

   public function payment_gateway_order($ref_finance)
   {
      $this->payment_gateway_logic($ref_finance, false);
   }

   public function payment_gateway_check_status($ref_finance)
   {
      $this->payment_gateway_status_logic($ref_finance, false);
   }

   public function bayarMulti($karyawan, $idPelanggan, $metode = 2, $note = "")
   {
      $rekap = isset($_POST['rekap']) ? $_POST['rekap'][0] : [];
      $dibayar = isset($_POST['dibayar']) ? $_POST['dibayar'] : 0;

      $res = $this->model('KasModel')->bayarMulti($rekap, $dibayar, $idPelanggan, $this->id_cabang, $karyawan, $metode, $note);

      echo $res;
   }

   public function ganti_operasi()
   {
      $karyawan = $_POST['f1'];
      $id = $_POST['id'];

      $set = ['id_user_operasi' => $karyawan];
      $where = $this->wCabang . " AND id_operasi = " . $id;
      $in = $this->db(0)->update('operasi', $set, $where); // Changed to db(0)
      if ($in['errno'] <> 0) {
         $this->model('Log')->write("[ganti_operasi] Update Operasi Error: " . $in['error']);
         echo $in['error'];
      } else {
         echo 0;
      }
   }

   public function cancel_payment($ref_finance)
   {
      // Check if transaction exists with cabang filter
      $where = $this->wCabang . " AND ref_finance = '" . $ref_finance . "'";
      $kas = $this->db(0)->get_where_row('kas', $where); // Changed to db(0)

      if (!isset($kas['id_kas'])) {
         echo json_encode(['status' => 'error', 'msg' => 'Transaksi tidak ditemukan']);
         exit();
      }

      // Reject if status_mutasi == 3 (already successful)
      if ($kas['status_mutasi'] == 3) {
         echo json_encode(['status' => 'error', 'msg' => 'Transaksi sudah berhasil, tidak dapat dibatalkan']);
         exit();
      }

      // Delete from kas table
      $deleteKas = $this->db(0)->delete('kas', $this->wCabang . " AND ref_finance = '$ref_finance'"); // Changed to db(0)
      if ($deleteKas['errno'] != 0) {
         $this->model('Log')->write("[cancel_payment] Delete Kas Error: " . $deleteKas['error']);
         echo json_encode(['status' => 'error', 'msg' => 'Gagal menghapus data kas: ' . $deleteKas['error']]);
         exit();
      }

      // Delete from wh_tokopay (ignore if table doesn't exist)
      try {
         $this->db(100)->delete('wh_tokopay', "ref_id = '$ref_finance'");
      } catch (Exception $e) {
      } // Changed to db(0)

      // Delete from wh_midtrans (ignore if table doesn't exist)
      try {
         $this->db(100)->delete('wh_midtrans', "ref_id = '$ref_finance'");
      } catch (Exception $e) {
      } // Changed to db(0)

      // Delete from wh_moota (ignore if table doesn't exist)
      try {
         $this->db(100)->delete('wh_moota', "trx_id = '$ref_finance'");
      } catch (Exception $e) {
      } // Changed to db(0)

      echo json_encode(['status' => 'success', 'msg' => 'Pembayaran berhasil dibatalkan']);
   }
}
