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
      
      // Year data for tuntas mode
      $currentYear = intval(date('Y'));
      $minYear = 2021;
      $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;
      if ($selectedYear < $minYear) $selectedYear = $minYear;
      if ($selectedYear > $currentYear) $selectedYear = $currentYear;
      
      $formData = array(
         'id_pelanggan' => $id_pelanggan, 
         'mode' => $modeOperasi,
         'currentYear' => $currentYear,
         'selectedYear' => $selectedYear,
         'minYear' => $minYear
      );
      
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
      $pelanggan = $this->pelanggan[$id_pelanggan] ?? 0;
      if ($pelanggan == 0) {
         echo "<div class='text-center mt-5'>
            <i class='fa-solid fa-circle-check text-success mb-3' style='font-size: 4rem;'></i>
            <h5 class='text-secondary'>Semua data pelanggan sudah tuntas!</h5>
         </div>";
         exit;
      }

      // Year handling
      $currentYear = intval(date('Y'));
      $minYear = 2021;
      $year = isset($_GET['year']) ? max($minYear, min($currentYear, intval($_GET['year']))) : $currentYear;

      $modeView = ($mode == 1) ? 2 : 1;
      $whereSale = $this->wCabang . " AND id_pelanggan = $id_pelanggan AND bin = 0 AND tuntas = " . ($mode == 1 ? "1 AND YEAR(insertTime) = $year" : "0") . " ORDER BY id_penjualan DESC";

      $data_main2 = $this->db(0)->get_where('sale', $whereSale, 'no_ref', 1);

      // Extract IDs and refs in single loop
      $sale_ids = [];
      $sale_refs = [];
      foreach ($data_main2 as $key_ref => $dm_group) {
         $sale_refs[] = $key_ref;
         foreach ($dm_group as $dm) {
            $sale_ids[] = $dm['id_penjualan'];
         }
      }

      // Batch queries for sale-related data
      $operasi = $notifSelesai = $kas = $notifBon = $surcas = [];
      if (!empty($sale_ids)) {
         $ids_in = "'" . implode("','", $sale_ids) . "'";
         $operasi = $this->db(0)->get_where('operasi', $this->wCabang . " AND id_penjualan IN ($ids_in)");
         $notifSelesai = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 2 AND no_ref IN ($ids_in)");
      }
      if (!empty($sale_refs)) {
         $refs_in = "'" . implode("','", $sale_refs) . "'";
         $kas = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi IN ($refs_in)");
         $notifBon = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 1 AND no_ref IN ($refs_in)");
         $surcas = $this->db(0)->get_where('surcas', $this->wCabang . " AND no_ref IN ($refs_in)");
      }

      // MEMBER - OPTIMIZED: batch query instead of N+1
      $data_member = $this->db(0)->get_where('member', $this->wCabang . " AND bin = 0 AND id_pelanggan = $id_pelanggan AND lunas = 0");
      
      $member_ids = array_column($data_member, 'id_member');
      $notif_member = $kas_member = [];
      
      if (!empty($member_ids)) {
         $member_in = "'" . implode("','", $member_ids) . "'";
         
         // Batch query kas_member
         $all_kas_member = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 3 AND ref_transaksi IN ($member_in)");
         foreach ($all_kas_member as $km) {
            $idm = $km['ref_transaksi'];
            $kas_member[$idm][] = $km;
         }
         
         // Batch query notif_member
         $notif_member = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 3 AND no_ref IN ($member_in)");
         
         // Check lunas status
         foreach ($data_member as $dme) {
            $idm = $dme['id_member'];
            $harga = $dme['harga'];
            $totalBayar = 0;
            
            if (isset($kas_member[$idm])) {
               foreach ($kas_member[$idm] as $k) {
                  if ($k['status_mutasi'] == 3) $totalBayar += $k['jumlah'];
               }
               if ($totalBayar >= $harga) {
                  $lunas = $this->db(0)->update('member', ['lunas' => 1], 'id_member = ' . $idm);
                  if ($lunas['errno'] <> 0) {
                     $this->model('Log')->write("[loadData] ERROR UPDATE PAID, MEMBER ID $idm Error: " . $lunas['error']);
                  }
               }
            }
         }
      }

      // Finance history - optimized merge
      $finance_history = [];
      $all_kas = array_merge($kas, array_merge(...array_values($kas_member ?: [[]])));
      foreach ($all_kas as $k) {
         if (empty($k['ref_finance'])) continue;
         $rf = $k['ref_finance'];
         if (!isset($finance_history[$rf])) {
            $finance_history[$rf] = ['ref_finance' => $rf, 'total' => 0, 'status' => $k['status_mutasi'], 'metode' => $k['metode_mutasi'], 'note' => $k['note'], 'insertTime' => $k['insertTime']];
         }
         $finance_history[$rf]['total'] += intval($k['jumlah']);
         if ($k['insertTime'] > $finance_history[$rf]['insertTime']) {
            $finance_history[$rf] = array_merge($finance_history[$rf], ['insertTime' => $k['insertTime'], 'status' => $k['status_mutasi'], 'metode' => $k['metode_mutasi'], 'note' => $k['note']]);
         }
      }
      $finance_history = array_filter($finance_history, fn($item) => $item['status'] == 2);

      // Saldo deposit - 3 queries combined concept (kept as is for accuracy)
      $topup = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3") ?? 0;
      $topup_out = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3") ?? 0;
      $usage = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND metode_mutasi = 3 AND jenis_mutasi = 2") ?? 0;

      $this->view('operasi/view_load', [
         'modeView' => $modeView, 'pelanggan' => $pelanggan, 'data_main' => $data_main2,
         'operasi' => $operasi, 'kas' => $kas, 'notif_bon' => $notifBon, 'notif_selesai' => $notifSelesai,
         'notif_member' => $notif_member, 'formData' => [], 'idOperan' => '', 'surcas' => $surcas,
         'data_member' => $data_member, 'kas_member' => $kas_member, 'saldoTunai' => $topup - $topup_out - $usage,
         'users' => $this->db(0)->get('user', 'id_user'), 'finance_history' => $finance_history,
         'selectedYear' => $year, 'currentYear' => $currentYear, 'minYear' => $minYear
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

      // Note: wh_tokopay not used anymore - payment info is now in kas table
      // Delete from wh_midtrans (ignore if table doesn't exist)
      try {
         $this->db(100)->delete('wh_midtrans', "ref_id = '$ref_finance'");
      } catch (Exception $e) {
      } // Changed to db(0)

      // Note: Moota integration removed - no wh_moota cleanup needed

      echo json_encode(['status' => 'success', 'msg' => 'Pembayaran berhasil dibatalkan']);
   }
}
