<?php

class HapusDeposit extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $viewData = 'member/viewDataHapus';
      $data_main = array();
      $where = $this->wCabang . " AND bin = 1";
      $order = "id_member DESC";
      $data_manual = $this->db(0)->get_where_order('member', $where, $order);

      $kas = array();
      if (count($data_manual) > 0) {
         $numbers = array_column($data_manual, 'id_member');
         $min = min($numbers);
         $max = max($numbers);
         $where = $this->wCabang . " AND jenis_transaksi = 3 AND (ref_transaksi BETWEEN " . $min . " AND " . $max . ")";
         $kas = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);
      }
      $this->view($viewData, ['data_main' => $data_main, 'data_manual' => $data_manual, 'kas' => $kas]);
   }
}
