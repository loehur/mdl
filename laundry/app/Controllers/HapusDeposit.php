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
      $db = $this->db(0);
      $wc = $this->wCabang;

      $where = $wc . " AND bin = 1";
      $order = "id_member DESC";
      $data_manual = $db->get_where_order('member', $where, $order);

      $kas = array();
      if (count($data_manual) > 0) {
         $ids = array_values(array_unique(array_map('intval', array_column($data_manual, 'id_member'))));
         if (count($ids) > 0) {
            $idIn = implode(',', $ids);
            // IN exact ref — bukan BETWEEN min/max (bisa ikut transaksi lain di rentang)
            $kas = $db->get_where('kas', $wc . " AND jenis_transaksi = 3 AND ref_transaksi IN ($idIn)");
         }
      }
      $this->view($viewData, ['data_main' => $data_main, 'data_manual' => $data_manual, 'kas' => $kas]);
   }
}
