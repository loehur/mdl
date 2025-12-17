<?php

class SetDiskon_Khusus extends Controller
{
   public $table;
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
      $this->table = 'diskon_khusus';
   }

   // ---------------- INDEX -------------------- //
   public function i()
   {
      $view = 'setHarga/diskon_khusus';
      $data_main = $this->db(0)->get_where($this->table, 'id_cabang = ' . $_SESSION[URL::SESSID]['user']['id_cabang'] . ' ORDER BY id_diskon_khusus DESC');
      $data_operasi = ['title' => 'Harga Diskon Khusus'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, ['data_main' => $data_main]);
   }

   public function insert()
   {
      $where = "id_harga = " . $_POST['id_harga'] . " AND id_pelanggan = " . $_POST['pelanggan'];
      $data_main = $this->db(0)->count_where($this->table, $where);
      if ($data_main < 1) {
         $data = [
            'id_pelanggan' => $_POST['pelanggan'],
            'id_harga' => $_POST['id_harga'],
            'diskon' => $_POST['diskon'],
            'id_cabang' => $_SESSION[URL::SESSID]['user']['id_cabang']
         ];
         $do = $this->db(0)->insert($this->table, $data);
         if ($do['errno'] == 0) {
            echo 0;
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
         } else {
            echo $do['error'];
         }
      }
   }

   public function updateCell()
   {
      $id = $_POST['id'];
      $value = $_POST['value'];
      $col = "diskon";

      $where = "id_diskon_khusus = " . $id;

      if ($value <= 0) {
         $del = $this->db(0)->delete($this->table, $where);
      } else {
         $set = $col . " = '" . $value . "'";
         $up = $this->db(0)->update($this->table, $set, $where);
      }

      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }
}
