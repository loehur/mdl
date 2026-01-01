<?php

class Cabang_List extends Controller
{

   public function __construct()
   {
      $this->operating_data();
   }
   public function index()
   {
      $data_operasi = ['title' => 'Data Cabang'];

      $table = 'cabang';
      $data_cabang = $this->db(0)->get($table);

      $this->view('layout', $data_operasi);
      $this->view('data_list/cabang', ['data_cabang' => $data_cabang]);
   }

   public function insert()
   {
      $this->session_cek(1);
      $table  = 'cabang';
      $columns = ' id_kota, alamat, kode_cabang';
      $values = "'" . $_POST["kota"] . "','" . $_POST["alamat"] . "','" . $_POST["kode_cabang"] . "'";
      $this->db(0)->insertCols($table, $columns, $values);
      $this->dataSynchrone($_SESSION['resto_user']['id_user']);
   }

   public function selectCabang()
   {
      $this->session_cek(2);
      $id_cabang = $_POST['id'];
      $table  = 'user';
      $set = "id_cabang = " . $id_cabang;
      $where = "id_user = " . $_SESSION['resto_user']['id_user'];
      $this->db(0)->update($table, $set, $where);
      $this->dataSynchrone($_SESSION['resto_user']['id_user']);
   }

   public function selectBook()
   {
      $this->session_cek();
      $book = $_POST['book'];
      $set = "book = '" . $book . "'";
      $where = "id_user = " . $_SESSION['resto_user']['id_user'];
      $up = $this->db(0)->update('user', $set, $where);
      if ($up['errno'] == 0) {
         echo 0;
         $this->dataSynchrone($_SESSION['resto_user']['id_user']);
      } else {
         print_r($up);
      }
   }

   public function update()
   {
      $table  = 'cabang';
      $id = $_POST['id'];
      $value = $_POST['value'];
      $mode = $_POST['mode'];

      if ($mode == 1) {
         $kolom = "kode_cabang";
      } else if ($mode == 2) {
         $kolom = "alamat";
      } else {
         $kolom = "id_kota";
      }
      $set = "$kolom = '$value'";
      $where = "id_cabang = $id";
      $this->db(0)->update($table, $set, $where);
      $this->dataSynchrone($_SESSION['resto_user']['id_user']);
   }
}
