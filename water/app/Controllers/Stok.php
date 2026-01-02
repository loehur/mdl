<?php

class Stok extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Stok'];
      $id_user = $_SESSION[URL::SESSID]['user']['id_user'];

      //saya
      // $refs = "";
      // $refs_arr = [];
      // $tgl_pesan = date('Y-m-d');
      // $refs_arr = $this->db(0)->get_where('ref', "tgl = '" . $tgl_pesan . "' AND id_user = " . $id_user . " AND step <> 2", 'id');
      // $refs_arr = array_keys($refs_arr); // Get only the IDs of the refs
      // foreach ($refs_arr as $key => $d) {
      //    $refs .= $d . ",";
      // }
      // $refs = rtrim($refs, ',');
      // if (empty($refs)) {
      //    $refs = "''"; // If no refs, set to empty string to avoid SQL error
      // }
      // $where = "ref IN (" . $refs . ")";
      // $data['me'] = $this->db(0)->sum_col_where('pesanan', 'qty', $where);

      //bukan saya
      // $refs = "";
      // $refs_arr = [];
      // $tgl_pesan = date('Y-m-d');
      // $refs_arr = $this->db(0)->get_where('ref', "tgl = '" . $tgl_pesan . "' AND id_user <> " . $id_user . " AND step <> 2", 'id');
      // $refs_arr = array_keys($refs_arr); // Get only the IDs of the refs
      // foreach ($refs_arr as $key => $d) {
      //    $refs .= $d . ",";
      // }
      // $refs = rtrim($refs, ',');
      // if (empty($refs)) {
      //    $refs = "''"; // If no refs, set to empty string to avoid SQL error
      // }
      // $where = "ref IN (" . $refs . ")";
      // $data['xme'] = $this->db(0)->sum_col_where('pesanan', 'qty', $where);

      //semua hari ini
      $refs = "";
      $refs_arr = [];
      $tgl_pesan = date('Y-m-d');
      $refs_arr = $this->db(0)->get_where('ref', "tgl = '" . $tgl_pesan . "' AND step <> 2", 'id');
      $refs_arr = array_keys($refs_arr); // Get only the IDs of the refs
      foreach ($refs_arr as $key => $d) {
         $refs .= $d . ",";
      }
      $refs = rtrim($refs, ',');
      if (empty($refs)) {
         $refs = "''"; // If no refs, set to empty string to avoid SQL error
      }
      $where = "ref IN (" . $refs . ")";
      $data['alltoday'] = $this->db(0)->sum_col_where('pesanan', 'qty', $where);
      $data['allv_t'] = $this->db(0)->get_cols_where('pesanan', 'v, SUM(qty) as qty', $where . " GROUP BY v", 1, 'v');

      //semua kemarin
      $refs = "";
      $refs_arr = [];
      $tgl_pesan = date('Y-m-d', strtotime('-1 day'));
      $refs_arr = $this->db(0)->get_where('ref', "tgl = '" . $tgl_pesan . "' AND step <> 2", 'id');
      $refs_arr = array_keys($refs_arr); // Get only the IDs of the refs
      foreach ($refs_arr as $key => $d) {
         $refs .= $d . ",";
      }
      $refs = rtrim($refs, ',');
      if (empty($refs)) {
         $refs = "''"; // If no refs, set to empty string to avoid SQL error
      }
      $where = "ref IN (" . $refs . ")";
      $data['allyesterday'] = $this->db(0)->sum_col_where('pesanan', 'qty', $where);
      $data['allv_y'] = $this->db(0)->get_cols_where('pesanan', 'v, SUM(qty) as qty', $where . " GROUP BY v", 1, 'v');

      //semua sebulan
      $refs = "";
      $refs_arr = [];
      $tgl_pesan = date('Y-m-');
      $refs_arr = $this->db(0)->get_where('ref', "tgl LIKE '" . $tgl_pesan . "%' AND step <> 2", 'id');
      $refs_arr = array_keys($refs_arr); // Get only the IDs of the refs
      foreach ($refs_arr as $key => $d) {
         $refs .= $d . ",";
      }
      $refs = rtrim($refs, ',');
      if (empty($refs)) {
         $refs = "''"; // If no refs, set to empty string to avoid SQL error
      }
      $where = "ref IN (" . $refs . ")";
      $data['allm'] = $this->db(0)->sum_col_where('pesanan', 'qty', $where);
      $data['allmv_t'] = $this->db(0)->get_cols_where('pesanan', 'v, SUM(qty) as qty', $where . " GROUP BY v", 1, 'v');

      //semua sebulan lalu
      $refs = "";
      $refs_arr = [];
      $tgl_pesan = date('Y-m-', strtotime('-1 month'));
      $refs_arr = $this->db(0)->get_where('ref', "tgl LIKE '" . $tgl_pesan . "%' AND step <> 2", 'id');
      $refs_arr = array_keys($refs_arr); // Get only the IDs of the refs
      foreach ($refs_arr as $key => $d) {
         $refs .= $d . ",";
      }
      $refs = rtrim($refs, ',');
      if (empty($refs)) {
         $refs = "''"; // If no refs, set to empty string to avoid SQL error
      }
      $where = "ref IN (" . $refs . ")";
      $data['allml'] = $this->db(0)->sum_col_where('pesanan', 'qty', $where);
      $data['allmv_y'] = $this->db(0)->get_cols_where('pesanan', 'v, SUM(qty) as qty', $where . " GROUP BY v", 1, 'v');

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }
}
