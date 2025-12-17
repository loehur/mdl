<?php

class Pengeluaran extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $view = 'admin_approval/pengeluaran';

      $where = $this->wCabang . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 4 AND status_mutasi = 2 ORDER BY id_kas DESC LIMIT 20";
      $list[0] = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);

      $where = $this->wCabang . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 4 AND status_mutasi <> 2 ORDER BY id_kas DESC LIMIT 20";
      $list[1] = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);
      $this->view($view, $list);
   }

   public function operasi($tipe)
   {
      $id = $_POST['id'];
      $set = [
         'status_mutasi' => $tipe
      ];
      $where = $this->wCabang . " AND id_kas = " . $id;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('kas', $set, $where);
   }
}
