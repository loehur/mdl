<?php

class Approval extends Controller
{
   private $mode = ['pengeluaran', 'penarikan', 'non-Tunai'];
   private $where = [
      "jenis_transaksi = 4 AND jenis_mutasi = 2",
      "jenis_transaksi = 2 AND jenis_mutasi = 2",
      "jenis_transaksi = 1 AND jenis_mutasi = 1 AND metode_mutasi = 2"
   ];

   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Approval'];
      $data['mode'] = $this->mode;
      foreach ($this->mode as $key => $dm) {
         $data[$dm] = $this->db(0)->count_where('kas', "status_mutasi = 0 AND " . $this->where[$key]);
      }

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cek($key)
   {
      $data['key'] = $key;
      $data[$this->mode[$key]] = $this->db(0)->get_where('kas', "status_mutasi = 0 AND " . $this->where[$key], 'id');
      $refs_arr = array_column($data[$this->mode[$key]], 'ref');
      $refs_arr = array_unique($refs_arr);
      $refs = "";
      foreach ($refs_arr as $ds) {
         $refs .= $ds . ",";
      }

      $refs = rtrim($refs, ',');
      $where = "id IN (" . $refs . ")";
      $data['ref'] = $this->db(0)->get_where('ref', $where, 'id');
      $data['pelanggan'] = $this->db(0)->get('pelanggan', 'id');


      $viewData = __CLASS__ . '/' . $this->mode[$key];
      $this->view($viewData, $data);
   }

   function verify()
   {
      $p = $_POST;
      $where = $this->where[$p['key']];
      $up =  $this->db(0)->update('kas', "status_mutasi = " . $p['v'], "id = " . $p['id']);
      if ($up['errno'] == 0) {
         echo $this->db(0)->count_where('kas', "status_mutasi = 0 AND " . $where);
      } else {
         echo $up['error'];
      }
   }
}
