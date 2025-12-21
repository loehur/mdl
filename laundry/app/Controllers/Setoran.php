<?php

class Setoran extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $view = 'setoran/setoran_main';
      $where = $this->wCabang . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 2 ORDER BY insertTime DESC LIMIT 20";
      $list = $this->db(0)->get_where('kas', $where);
      $this->view($view, ['list' => $list]);
   }

   public function operasi($tipe)
   {
      $id = $_POST['id'];
      $set = "status_mutasi = '" . $tipe . "'";
      $where = $this->wCabang . " AND id_kas = '" . $id . "'";
      $this->db(0)->update('kas', $set, $where);
   }
}
