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
         $where = $this->wCabang . " AND id_pelanggan = $id_pelanggan AND bin = 0 AND tuntas = " . $mode . " ORDER BY id_penjualan DESC";
         $modeView = 2;
      } else {
         $where = $this->wCabang . " AND id_pelanggan = $id_pelanggan AND bin = 0 AND tuntas = 0 ORDER BY id_penjualan DESC";
      }
      $data_main = [];
      $data_main2 = [];

      for ($y = date('Y'); $y >= URL::FIRST_YEAR; $y--) {
         $data_s = $this->db($y)->get_where('sale', $where);
         if (count($data_s) > 0) {
            foreach ($data_s as $ds) {
               array_push($data_main, $ds);
            }
         }
         $data_s2 = $this->db($y)->get_where('sale', $where, 'no_ref', 1);
         if (count($data_s2) > 0) {
            foreach ($data_s2 as $key => $ds2) {
               $data_main2[$key] = $ds2;
            }
         }
      }

      $viewData = 'operasi/view_load';

      $numbers = [];
      $refs = [];
      foreach ($data_main as $dm) {
         $year = substr($dm['insertTime'], 0, 4);
         $numbers[$dm['id_penjualan']] = $year;
         $refs[$dm['no_ref']] = $year;
      }

      $operasi = [];
      $kas = [];
      $surcas = [];
      $notifBon = [];
      $notifSelesai = [];

      foreach ($numbers as $id => $book) {

         $where_o = $this->wCabang . " AND id_penjualan = " . $id; //OPERASI
         $where_n = $this->wCabang . " AND tipe = 2 AND no_ref = '" . $id . "'"; //NOTIF SELESAI

         $i = $book;
         while ($i <= date('Y')) {
            //OPERASI
            $ops = $this->db($i)->get_where('operasi', $where_o);
            if (count($ops) > 0) {
               foreach ($ops as $opsv) {
                  array_push($operasi, $opsv);
               }
            }

            //NOTIF SELESAI
            $ns = $this->db($i)->get_where_row('notif', $where_n);
            if (count($ns) > 0) {
               array_push($notifSelesai, $ns);
            }

            $i++;
         }
      }

      foreach ($refs as $rf => $book) {
         $where_kas = $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi = '" . $rf . "'"; //KAS
         $where_notif = $this->wCabang . " AND tipe = 1 AND no_ref = '" . $rf . "'"; //NOTIF BON

         $i = $book;
         while ($i <= date('Y')) {
            //KAS
            $ks = $this->db($i)->get_where('kas', $where_kas);
            if (count($ks) > 0) {
               foreach ($ks as $ksv) {
                  array_push($kas, $ksv);
               }
            }
            //NOTIF NOTA
            $nf = $this->db($i)->get_where_row('notif', $where_notif);
            if (count($nf) > 0) {
               array_push($notifBon, $nf);
            }
            $i++;
         }


         //SURCAS
         $where = $this->wCabang . " AND no_ref = '" . $rf . "'";
         $sc = $this->db(0)->get_where('surcas', $where);
         if (count($sc) > 0) {
            foreach ($sc as $scv) {
               array_push($surcas, $scv);
            }
         }
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
         $check_moota = $this->db(100)->get_where_row("wh_moota", "trx_id = '" . $fh['ref_finance'] . "'");
         if(isset($check_moota['amount']) && $check_moota['amount'] > 0){
             $finance_history[$key]['total'] = $check_moota['amount'];
         }
      }

      //MEMBER
      $data_member = [];
      $where = $this->wCabang . " AND bin = 0 AND id_pelanggan = " . $id_pelanggan . " AND lunas = 0";
      $data_member = $this->db(0)->get_where('member', $where);

      $notif_member = [];
      $kas_member = [];
      foreach ($data_member as $dme) {
         $harga = $dme['harga'];
         $idm = $dme['id_member'];
         $historyBayar[$dme['id_member']] = [];

         $i = substr($dme['insertTime'], 0, 4);
         $where = $this->wCabang . " AND jenis_transaksi = 3 AND ref_transaksi = '" . $dme['id_member'] . "'";
         $where_notif = $this->wCabang . " AND tipe = 3 AND no_ref = '" . $dme['id_member'] . "'";
         while ($i <= date('Y')) {
            //KAS MEMBER
            $km = $this->db($i)->get_where('kas', $where);
            if (count($km) > 0) {
               if (!isset($kas_member[$idm])) {
                  $kas_member[$idm] = [];
               }

               foreach ($km as $kmv) {
                  array_push($kas_member[$idm], $kmv);
               }
            }

            //NOTIF MEMBER
            $nm = $this->db($i)->get_where_row('notif', $where_notif);
            if (count($nm) > 0) {
               array_push($notif_member, $nm);
            }

            $i += 1;
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
      $sisaSaldo = $this->helper('Saldo')->getSaldoTunai($id_pelanggan);

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
      $in = $this->db($_SESSION[URL::SESSID]['user']['book'])->update('operasi', $set, $where);
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
      $kas = $this->db(date('Y'))->get_where_row('kas', $where);
      
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
      $deleteKas = $this->db(date('Y'))->delete('kas', $this->wCabang . " AND ref_finance = '$ref_finance'");
      if ($deleteKas['errno'] != 0) {
         $this->model('Log')->write("[cancel_payment] Delete Kas Error: " . $deleteKas['error']);
         echo json_encode(['status' => 'error', 'msg' => 'Gagal menghapus data kas: ' . $deleteKas['error']]);
         exit();
      }

      // Delete from wh_tokopay (ignore if table doesn't exist)
      try { $this->db(100)->delete('wh_tokopay', "ref_id = '$ref_finance'"); } catch (Exception $e) {}
      
      // Delete from wh_midtrans (ignore if table doesn't exist)
      try { $this->db(100)->delete('wh_midtrans', "ref_id = '$ref_finance'"); } catch (Exception $e) {}
      
      // Delete from wh_moota (ignore if table doesn't exist)
      try { $this->db(100)->delete('wh_moota', "trx_id = '$ref_finance'"); } catch (Exception $e) {}

      echo json_encode(['status' => 'success', 'msg' => 'Pembayaran berhasil dibatalkan']);
   }
}
