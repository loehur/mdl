<?php

class SetDiskon extends Controller
{
   public $table;
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
      $this->table = 'diskon_qty';
   }

   // ---------------- INDEX -------------------- //
   public function i()
   {
      $view = 'setHarga/diskon';
      $data_main = $this->db(0)->get($this->table);
      $data_operasi = ['title' => 'Harga Diskon Kuantitas'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, ['data_main' => $data_main]);
   }

   public function insert()
   {
      $where = 'id_penjualan_jenis = ' . $_POST['f1'];
      $data_main = $this->db(0)->count_where($this->table, $where);
      if ($data_main < 1) {
         $data = [
            'id_penjualan_jenis' => $_POST['f1'],
            'qty_disc' => $_POST['f3'],
            'disc_qty' => $_POST['f4'],
            'combo' => $_POST['combo']
         ];
         print_r($this->db(0)->insert($this->table, $data));
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      }
   }

   public function updateCell()
   {
      $id = $_POST['id'];
      $value = $_POST['value'];
      $mode = $_POST['mode'];

      if ($mode == 2) {
         $col = "qty_disc";
      } else if ($mode == 3) {
         $col = "disc_qty";
      } else if ($mode == 4) {
         $col = "disc_partner";
      }

      $set = $col . " = '" . $value . "'";
      $where = "id_diskon = " . $id;
      $this->db(0)->update($this->table, $set, $where);
      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }

   public function updateCell_s()
   {
      $id = $_POST['id'];
      $value = $_POST['value'];
      $col = "combo";

      $set = $col . " = '" . $value . "'";
      $where = "id_diskon = " . $id;
      $this->db(0)->update($this->table, $set, $where);
      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }
}
