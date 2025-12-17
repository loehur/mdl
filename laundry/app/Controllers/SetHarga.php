<?php

class SetHarga extends Controller
{
   public $table;
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
      $this->table = 'harga';
   }

   public function i($page)
   {
      $view = 'setHarga/all';
      foreach ($this->dPenjualan as $a) {
         if ($page == $a['id_penjualan_jenis']) {
            $penjualan = $a['penjualan_jenis'];
            $data_operasi = ['title' => 'Harga ' . $penjualan];
            $z = array('unit' => $a['id_satuan'], 'set' => $penjualan, 'page' => $page);
         }
      }

      $setOne = 'id_penjualan_jenis = ' . $page;
      $where = $setOne;
      $d2 = $this->db(0)->get_where('item_group', $where);
      $where = $setOne . " ORDER BY id_item_group ASC, list_layanan ASC, id_durasi ASC";
      $data_main = $this->db(0)->get_where($this->table, $where);
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, ['data_main' => $data_main, 'd2' => $d2, 'z' => $z]);
   }

   public function insert($page)
   {
      $layanan = serialize($_POST['f2']);
      $durasi = $_POST['f3'];
      $item_group = $_POST['f1'];
      $setOne = 'id_penjualan_jenis = ' . $page;
      $where = $setOne . " AND list_layanan = '$layanan' AND id_durasi = $durasi AND id_item_group = $item_group";
      $data_main = $this->db(0)->count_where($this->table, $where);
      if ($data_main < 1) {
         $data = [
            'id_penjualan_jenis' => $page,
            'id_item_group' => $item_group,
            'list_layanan' => $layanan,
            'id_durasi' => $durasi,
            'harga' => $_POST['f4'],
            'min_order' => $_POST['f5']
         ];
         $query = $this->db(0)->insert($this->table, $data);
         if ($query) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
         }
      }
   }

   public function updateCell()
   {
      $id = $_POST['id'];
      $value = $_POST['value'];
      $mode = $_POST['mode'];

      switch ($mode) {
         case "1":
            $col = "harga";
            break;
         case "6":
            $col = "harga_b";
            break;
         case "2":
            $col = "hari";
            break;
         case "3":
            $col = "jam";
            break;
         case "4":
            $col = "sort";
            break;
         case "5":
            $col = "min_order";
            break;
      }

      $set = $col . " = '" . $value . "'";
      $where = "id_harga = " . $id;
      $query = $this->db(0)->update($this->table, $set, $where);
      if ($query['errno'] == 0) {
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      }
   }

   public function removeRow()
   {
      $id = $_POST['id'];
      $where = "id_harga = " . $id;
      $query = $this->db(0)->delete($this->table, $where);
      if ($query) {
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      }
   }
}
