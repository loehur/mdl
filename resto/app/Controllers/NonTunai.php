<?php

class NonTunai extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $limit = 12;
      $view = 'non_tunai/nt_main';
      $cols = "ref_finance, note, id_user, id_client, status_mutasi, jenis_transaksi, SUM(jumlah) as total";
      $where = $this->wCabang . " AND metode_mutasi <> 1 AND status_mutasi = 2 AND ref_finance <> '' GROUP BY ref_finance ORDER BY ref_finance DESC LIMIT $limit";
      $list['cek'] = $this->db($_SESSION['resto_user']['book'])->get_cols_where('kas', $cols, $where, 1);

      $where = $this->wCabang . " AND metode_mutasi = 2 AND status_mutasi <> 2 AND ref_finance <> '' GROUP BY ref_finance ORDER BY ref_finance DESC LIMIT $limit";
      $list['done'] = $this->db($_SESSION['resto_user']['book'])->get_cols_where('kas', $cols, $where, 1);

      $this->view($view, $list);
   }

   public function operasi($tipe)
   {
      $id = $_POST['id'];
      $set = "status_mutasi = '" . $tipe . "'";
      $where = $this->wCabang . " AND ref_finance = '" . $id . "'";
      $this->db($_SESSION['resto_user']['book'])->update('kas', $set, $where);
   }
}
