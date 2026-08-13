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
      $db = $this->db(0);
      $base = $this->wCabang . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 2";

      // Pisah pending vs riwayat agar LIMIT 20 tidak menelan antrean approve
      $pending = $db->get_where('kas', $base . " AND status_mutasi = 2 ORDER BY insertTime DESC LIMIT 20");
      $riwayat = $db->get_where('kas', $base . " AND status_mutasi <> 2 ORDER BY insertTime DESC LIMIT 20");
      $list = array_merge(is_array($pending) ? $pending : [], is_array($riwayat) ? $riwayat : []);

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
