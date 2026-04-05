<?php

class SetHargaPaket extends Controller
{
   public $table;
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
      $this->table = 'harga_paket';
   }

   public function index()
   {
      $view = 'setHargaPaket/harga_paket_main';
      $data_operasi = ['title' => 'Harga Paket'];
      $order = "id_harga ASC, qty ASC";
      $data_main = $this->db(0)->get_order($this->table, $order);
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, ['data_main' => $data_main]);
   }

   public function form($id_penjualan)
   {
      $this->view('setHargaPaket/formOrder', $id_penjualan);
   }

   public function cart()
   {
      $viewData = 'setHargaPaket/cart';
      $order = "id_harga ASC, qty ASC";
      $data_main = $this->db(0)->get_order($this->table, $order);
      $this->view($viewData, ['data_main' => $data_main]);
   }

   public function insert()
   {
      $id_harga = $_POST['f1'];
      $qty = $_POST['f2'];
      $harga = $_POST['f3'];

      $cols = 'id_harga, qty, harga';
      $vals = $id_harga . "," . $qty . "," . $harga;

      $where = "id_harga = " . $id_harga . " AND qty = " . $qty;
      $data_main = $this->db(0)->count_where($this->table, $where);
      if ($data_main < 1) {
         $data = [
            'id_harga' => $id_harga,
            'qty' => $qty,
            'harga' => $harga
         ];
         $in = $this->db(0)->insert($this->table, $data);
         if ($in['errno'] == 0) {
            $this->index();
         } else {
            echo $in['error'];
         }
      } else {
         $this->index();
      }
   }

   public function updateCell()
   {
      $id = (int) $_POST['id'];
      $valueRaw = isset($_POST['value']) ? $_POST['value'] : '';
      $valueNew = (float) str_replace(',', '.', (string) $valueRaw);
      $mode = isset($_POST['mode']) ? $_POST['mode'] : '';
      if ($mode !== 'a' && $mode !== 'b') {
         return;
      }
      $col = ($mode === 'a') ? 'harga' : 'harga_b';

      $db = $this->db(0);
      $edited = $db->get_where('harga_paket', 'id_harga_paket = ' . $id);
      if (empty($edited) || !isset($edited[0])) {
         return;
      }
      $row0 = $edited[0];
      $idHarga = (int) $row0['id_harga'];
      $qtyEdit = (float) $row0['qty'];
      if ($qtyEdit <= 0) {
         $qtyEdit = 1.0;
      }

      // Nilai 0 = hanya baris ini (tanpa propagasi), agar tidak mengosongkan semua tier
      if ($valueNew <= 0) {
         $set = [$col => (string) $valueNew];
         $query = $db->update('harga_paket', $set, 'id_harga_paket = ' . $id);
         if ($query['errno'] == 0) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
         }
         return;
      }

      $unitPrice = $valueNew / $qtyEdit;
      $siblings = $db->get_where('harga_paket', 'id_harga = ' . $idHarga);

      foreach ($siblings as $r) {
         $pk = (int) $r['id_harga_paket'];
         $qty = (float) $r['qty'];
         if ($qty <= 0) {
            $qty = 1.0;
         }
         $current = (float) $r[$col];
         if ($pk !== $id && abs($current) < 0.0000001) {
            continue;
         }
         $newVal = ($pk === $id) ? $valueNew : round($unitPrice * $qty, 2);
         $q = $db->update('harga_paket', [$col => (string) $newVal], 'id_harga_paket = ' . $pk);
         if ($q['errno'] !== 0) {
            return;
         }
      }

      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }
}
