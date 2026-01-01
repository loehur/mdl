<?php

class Data_List extends Controller
{
   public function __construct()
   {
      $this->operating_data();
   }

   public function i($page)
   {
      $d2 = array();
      $z = array();
      $data_main = array();
      $z = array('page' => $page);

      switch ($page) {
         case "item":
            $this->session_cek(1);
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Item Laundry'];
            $table = $page;
            $order = 'item ASC';
            $data_main = $this->db(0)->get_order($table, $order);
            break;
         case "item_pengeluaran":
            $this->session_cek(1);
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Item Pengeluaran'];
            $table = $page;
            $order = 'id_item_pengeluaran ASC';
            $data_main = $this->db(0)->get_order($table, $order);
            break;
      }
      $this->view('layout', $data_operasi);
      $this->view($view, ['data_main' => $data_main, 'd2' => $d2, 'z' => $z]);
   }

   public function insert($page)
   {
      $table  = $page;
      switch ($page) {
         case "item":
            $this->session_cek(1);
            $cols = 'item';
            $f1 = $_POST['f1'];
            $vals = "'" . $f1 . "'";
            $where = "item = '" . $f1 . "'";
            $data_main = $this->db(0)->count_where($table, $where);
            if ($data_main < 1) {
               $this->db(0)->insertCols($table, $cols, $vals);
               $this->dataSynchrone($_SESSION['resto_user']['id_user']);
            }
            break;
         case "item_pengeluaran":
            $this->session_cek(1);
            $cols = 'item_pengeluaran';
            $f1 = $_POST['f1'];
            $vals = "'" . $f1 . "'";
            $where = "item_pengeluaran = '" . $f1 . "'";
            $data_main = $this->db(0)->count_where($table, $where);
            if ($data_main < 1) {
               $this->db(0)->insertCols($table, $cols, $vals);
               $this->dataSynchrone($_SESSION['resto_user']['id_user']);
            }
            break;
         case "surcas":
            $this->session_cek(1);
            $table = "surcas_jenis";
            $cols = 'surcas_jenis';
            $f1 = $_POST['f1'];
            $vals = "'" . $f1 . "'";
            $where = "surcas_jenis = '" . $f1 . "'";
            $data_main = $this->db(0)->count_where($table, $where);
            if ($data_main < 1) {
               $this->db(0)->insertCols($table, $cols, $vals);
               $this->dataSynchrone($_SESSION['resto_user']['id_user']);
            }
            break;
         case "pelanggan":
            $cols = 'id_cabang, nama_pelanggan, nomor_pelanggan';
            $nama_pelanggan = $_POST['f1'];
            $vals = $this->id_cabang . ",'" . $nama_pelanggan . "','" . $_POST['f2'] . "'";
            $setOne = "nama_pelanggan = '" . $_POST['f1'] . "'";
            $where = $this->wCabang . " AND " . $setOne;
            $data_main = $this->db(0)->count_where($table, $where);
            if ($data_main < 1) {
               $do = $this->db(0)->insertCols($table, $cols, $vals);
               if ($do['errno'] <> 0) {
                  echo $do['error'];
               } else {
                  $this->dataSynchrone($_SESSION['resto_user']['id_user']);
                  echo 1;
               }
            } else {
               $text =  "Gagal! nama " . strtoupper($nama_pelanggan) . " sudah digunakan";
               echo $text;
            }
            break;
         case "user":
            $this->session_cek(1);
            $privilege = $_POST['f4'];
            if ($privilege == 100) {
               exit();
            }
            $cols = 'username, id_cabang, no_user, nama_user, id_privilege, book';
            $no_user = $_POST['f5'];
            $username = $this->model("Enc")->username($no_user);
            $vals = "'" . $username . "'," . $_POST['f3'] . ",'" . $no_user . "','" . $_POST['f1'] . "'," . $privilege . "," . date('Y');
            $do = $this->db(0)->insertCols($table, $cols, $vals);
            if ($do['errno'] == 0) {
               echo 0;
            } else {
               echo $do['error'];
            }
            break;
      }
   }

   public function updateCell($page)
   {
      $table  = $page;
      $id = $_POST['id'];
      $value = $_POST['value'];
      $mode = $_POST['mode'];

      switch ($page) {
         case "item":
            $this->session_cek(1);
            if ($mode == 1) {
               $col = "item";
            }
            $where = "id_item = " . $id;
            break;
         case "item_pengeluaran":
            $this->session_cek(1);
            if ($mode == 1) {
               $col = "item_pengeluaran";
            }
            $where = "id_item_pengeluaran = " . $id;
            break;
         case "surcas_jenis":
            $this->session_cek(1);
            if ($mode == 1) {
               $col = "surcas_jenis";
            }
            $where = "id_surcas_jenis = " . $id;
            break;
         case "user":
            $this->session_cek(1);
            $table  = $page;
            $id = $_POST['id'];
            $value = $_POST['value'];
            $mode = $_POST['mode'];

            switch ($mode) {
               case "2":
                  $col = "nama_user";
                  break;
               case "4":
                  $col = "id_cabang";
                  break;
               case "5":
                  $col = "id_privilege";
                  break;
               case "6":
                  $col = "no_user";
                  break;
            }
            $where = "id_user = $id";
            break;
         case "karyawan":
            $table  = "user";
            $id = $_POST['id'];
            $value = $_POST['value'];
            $mode = $_POST['mode'];

            switch ($mode) {
               case "2":
                  $col = "mac";
                  break;
               case "3":
                  $col = "mac_2";
                  break;
            }
            $where = "id_user = $id";
            break;
      }


      if ($page == "user" && $col == "id_privilege") {
         if ($value == 100) {
            exit();
         }
      }

      $set = $col . " = '" . $value . "'";
      $up = $this->db(0)->update($table, $set, $where);
      echo $up['errno'] == 0 ? 0 : $up['error'];

      if ($page == "user" && $col == "no_user") {
         $username = $this->model("Enc")->username($value);
         $set = "username = '" . $username . "', otp_active = ''";
         $this->db(0)->update($table, $set, $where);
      }
   }

   public function enable($bol)
   {
      $this->session_cek(1);
      $table  = 'user';
      $id = $_POST['id'];
      $where = "id_user = " . $id;
      $set = "en = " . $bol;
      $this->db(0)->update($table, $set, $where);
      $this->dataSynchrone($_SESSION['resto_user']['id_user']);
   }

   public function synchrone()
   {
      $this->dataSynchrone($_SESSION['resto_user']['id_user']);
   }
}
