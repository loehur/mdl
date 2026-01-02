<?php

class Pelanggan extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Pelanggan'];
      $order = 'id DESC';
      $data = $this->db(0)->get_where_order('pelanggan', $this->wCabang, $order);
      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   function insert()
   {
      $cols = 'id_cabang, nama, hp';
      $nama_pelanggan = $_POST['f1'];
      $vals = $this->id_cabang . ",'" . $nama_pelanggan . "','" . $_POST['f2'] . "'";
      $setOne = "nama = '" . $_POST['f1'] . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(0)->count_where('pelanggan', $where);
      if ($data_main < 1) {
         $do = $this->db(0)->insertCols('pelanggan', $cols, $vals);
         if ($do['errno'] <> 0) {
            echo $do['error'];
         } else {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
            echo 1;
         }
      } else {
         $text =  "Gagal! nama " . strtoupper($nama_pelanggan) . " sudah digunakan";
         echo $text;
      }
   }

   function updateCell()
   {
      $value = $_POST['value'];
      $mode = $_POST['mode'];
      $id = $_POST['id'];

      switch ($mode) {
         case "1":
            $col = "nama";
            break;
         case "2":
            $col = "hp";
            break;
         case "4":
            $col = "alamat";
            break;
         case "5":
            $this->session_cek(1);
            $col = "disc";
            if ($value > 100) {
               $value = 100;
            }
            break;
      }
      $where = $this->wCabang . " AND id = " . $id;

      $set = $col . " = '" . $value . "'";
      $up = $this->db(0)->update('pelanggan', $set, $where);
      echo $up['errno'] == 0 ? 0 : $up['error'];
   }
}
