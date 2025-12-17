<?php

class SetGroup extends Controller
{
   public $table;
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
      $this->table = 'item_group';
   }

   public function i($page)
   {
      $data_main = array();
      $view = 'setGroup/all';
      $where = 'id_penjualan_jenis = ' . $page;
      foreach ($this->dPenjualan as $a) {
         if ($page == $a['id_penjualan_jenis']) {
            $penjualan = $a['penjualan_jenis'];
            $z = ['title' => 'Produk ' . $penjualan, 'page' => $page];
            $data_operasi = ['title' => 'Produk ' . $penjualan];
         }
      }
      $data_main = $this->db(0)->get_where($this->table, $where);
      $d2 = $this->db(0)->get('item');
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, ['data_main' => $data_main, 'd2' => $d2, 'z' => $z]);
   }

   public function insert($page)
   {
      $item_list = serialize($_POST['f1']);
      $where = "item_kategori = '" . $_POST['f2'] . "' AND id_penjualan_jenis = $page";
      $data_main = $this->db(0)->count_where($this->table, $where);
      if ($data_main < 1) {
         $data = [
            'id_penjualan_jenis' => $page,
            'item_kategori' => $_POST['f2'],
            'item_list' => $item_list
         ];
         $this->db(0)->insert($this->table, $data);
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      }
   }

   public function updateCell()
   {
      $id = $_POST['id'];
      $value = $_POST['value'];
      $mode = $_POST['mode'];
      if ($mode == 1) {
         $col = "item_kategori";
      }
      $set = "$col = '$value'";
      $where = "id_item_group = $id";
      $this->db(0)->update($this->table, $set, $where);
      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }

   public function removeItem()
   {
      $id = $_POST['id'];
      $id_item = $_POST['id_item'];
      $value = $_POST['value'];
      $serVal = unserialize($value);
      $newVal = array_diff($serVal, array($id_item));
      $value = serialize($newVal);
      $set = "item_list = '$value'";
      $where = "id_item_group = $id";
      $this->db(0)->update($this->table, $set, $where);
      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }

   public function addItem($page)
   {
      $setOne = 'id_penjualan_jenis = ' . $page;
      $id = $_POST['f2'];
      $value = $_POST['f3'];
      $serVal = unserialize($value);
      $add = $_POST['f1'];
      $add_ = '"' . $add . '"';
      $where = "" . $setOne . " AND item_list LIKE '%$add_%'";
      $data_main = $this->db(0)->count_where($this->table, $where);
      if ($data_main < 1) {
         array_push($serVal, "$add");
         $value = serialize($serVal);
         $set = "item_list = '$value'";
         $where = "id_item_group = $id";
      }
      $this->db(0)->update($this->table, $set, $where);
      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }
}
