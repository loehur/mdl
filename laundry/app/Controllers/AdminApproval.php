<?php

class AdminApproval extends Controller
{

   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index($mode)
   {
      $data_operasi = ['title' => 'Approval'];

      //SETORAN
      $setoran = array();
      $where = $this->wCabang . " AND jenis_mutasi = 2 AND status_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 2 ORDER BY id_kas DESC LIMIT 20";
      $setoran = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);

      //PENGELUARAN
      $pengeluaran = array();
      $where = $this->wCabang . " AND jenis_mutasi = 2 AND status_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 4 ORDER BY id_kas DESC LIMIT 20";
      $pengeluaran = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);

      //NON TUNAI
      $nonTunai = array();
      $where = $this->wCabang . " AND metode_mutasi = 2 AND status_mutasi = 2 ORDER BY id_kas DESC LIMIT 20";
      $nonTunai = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);

      //HAPUS ORDER
      $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 1 ORDER BY id_penjualan DESC LIMIT 20";
      $hapusOrder = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('sale', $where);

      //DEPOSIT MEMBER HAPUS
      $depositHapus = array();
      $where = $this->wCabang . " AND bin = 1";
      $order = "id_member DESC";
      $depositHapus = $this->db(0)->get_where_order('member', $where, $order);

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view(
         'admin_approval/admin_approval_main',
         [
            'Setoran' => $setoran,
            'NonTunai' => $nonTunai,
            'HapusOrder' => $hapusOrder,
            'HapusDeposit' => $depositHapus,
            'Pengeluaran' => $pengeluaran,
            'mode' => $mode
         ]
      );
   }

   public function tokopayBalance()
   {
      header('Content-Type: application/json');
      
      try {
         $response = $this->model('Tokopay')->merchant();
         echo $response;
      } catch (Exception $e) {
         echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
   }
}
