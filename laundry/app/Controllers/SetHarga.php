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
      $page = (int) $page;
      if ($page < 1) {
         echo json_encode(['ok' => 0, 'msg' => 'Jenis penjualan tidak valid.']);
         return;
      }
      if (empty($_POST['f2']) || !is_array($_POST['f2'])) {
         echo json_encode(['ok' => 0, 'msg' => 'Layanan wajib dipilih.']);
         return;
      }
      if (!isset($_POST['f3']) || $_POST['f3'] === '') {
         echo json_encode(['ok' => 0, 'msg' => 'Durasi wajib dipilih.']);
         return;
      }
      if (!isset($_POST['f4']) || $_POST['f4'] === '' || (float) $_POST['f4'] <= 0) {
         echo json_encode(['ok' => 0, 'msg' => 'Harga tidak valid.']);
         return;
      }

      $layanan = serialize($_POST['f2']);
      $durasi = (int) $_POST['f3'];
      $kategoriBaru = trim((string) ($_POST['f1_new'] ?? ''));
      $item_group = (int) ($_POST['f1'] ?? 0);

      if ($kategoriBaru !== '') {
         $kategoriEsc = $this->db(0)->escape($kategoriBaru);
         $whereGrup = "item_kategori = '" . $kategoriEsc . "' AND id_penjualan_jenis = " . $page;
         $existing = $this->db(0)->get_where_row('item_group', $whereGrup);
         if (!empty($existing['id_item_group'])) {
            $item_group = (int) $existing['id_item_group'];
         } else {
            $ins = $this->db(0)->insert('item_group', [
               'id_penjualan_jenis' => $page,
               'item_kategori' => $kategoriBaru,
            ]);
            if ((int) ($ins['errno'] ?? 1) !== 0) {
               echo json_encode([
                  'ok' => 0,
                  'msg' => 'Gagal membuat kategori: ' . ($ins['error'] ?: 'unknown'),
               ]);
               return;
            }
            $item_group = (int) ($ins['insert_id'] ?? 0);
            if ($item_group < 1) {
               $existing = $this->db(0)->get_where_row('item_group', $whereGrup);
               $item_group = (int) ($existing['id_item_group'] ?? 0);
            }
         }
      }

      if ($item_group < 1) {
         echo json_encode(['ok' => 0, 'msg' => 'Pilih kategori atau buat kategori baru.']);
         return;
      }

      $setOne = 'id_penjualan_jenis = ' . $page;
      $layananEsc = $this->db(0)->escape($layanan);
      $where = $setOne . " AND list_layanan = '" . $layananEsc . "' AND id_durasi = " . $durasi . " AND id_item_group = " . $item_group;
      $data_main = $this->db(0)->count_where($this->table, $where);
      if ($data_main < 1) {
         $data = [
            'id_penjualan_jenis' => $page,
            'id_item_group' => $item_group,
            'list_layanan' => $layanan,
            'id_durasi' => $durasi,
            'harga' => (string) (int) round((float) str_replace(',', '.', (string) $_POST['f4']), 0),
            'min_order' => number_format(round((float) str_replace(',', '.', (string) ($_POST['f5'] ?? '0')), 2), 2, '.', ''),
            'is_active' => 1
         ];
         $query = $this->db(0)->insert($this->table, $data);
         if ((int) ($query['errno'] ?? 1) !== 0) {
            echo json_encode([
               'ok' => 0,
               'msg' => 'Gagal menambah harga: ' . ($query['error'] ?: 'unknown'),
            ]);
            return;
         }
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
         echo json_encode(['ok' => 1, 'msg' => 'Harga berhasil ditambahkan.']);
         return;
      }

      echo json_encode(['ok' => 0, 'msg' => 'Kombinasi kategori / layanan / durasi sudah ada.']);
   }

   public function updateCell()
   {
      $id = (int) $_POST['id'];
      $value = $_POST['value'];
      $mode = (string) ($_POST['mode'] ?? '');

      if ($mode === "8") {
         $query = $this->db(0)->update('item_group', ['item_kategori' => $value], "id_item_group = " . $id);
         if ($query['errno'] == 0) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
         }
         return;
      }

      $where = "id_harga = " . $id;
      $payload = [];

      switch ($mode) {
         case "1":
            $payload['harga'] = (string) (int) round((float) str_replace(',', '.', (string) $value), 0);
            break;
         case "6":
            $hargaCabang = (int) round((float) str_replace(',', '.', (string) $value), 0);
            $idCabang = (int) $this->id_cabang;
            $whereCabang = "id_cabang = " . $idCabang . " AND id_harga = " . $id;
            if ($hargaCabang <= 0) {
               $this->db(0)->delete('harga_cabang', $whereCabang);
               $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
               return;
            }
            $existing = $this->db(0)->get_where_row('harga_cabang', $whereCabang);
            if (!empty($existing['id_harga_cabang'])) {
               $query = $this->db(0)->update('harga_cabang', ['harga' => (string) $hargaCabang], $whereCabang);
            } else {
               $query = $this->db(0)->insert('harga_cabang', [
                  'id_cabang' => $idCabang,
                  'id_harga' => $id,
                  'harga' => $hargaCabang,
               ]);
            }
            if (($query['errno'] ?? 1) == 0) {
               $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
            }
            return;
         case "2":
            $payload['hari'] = (string) (int) $value;
            break;
         case "3":
            $payload['jam'] = (string) (int) $value;
            break;
         case "4":
            $payload['sort'] = (string) (int) $value;
            break;
         case "5":
            $n = round((float) str_replace(',', '.', (string) $value), 2);
            $payload['min_order'] = number_format($n, 2, '.', '');
            break;
         case "7":
            $payload['is_active'] = (string) (int) $value;
            break;
         default:
            return;
      }

      $query = $this->db(0)->update($this->table, $payload, $where);
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
