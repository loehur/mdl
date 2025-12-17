<?php

class Penjualan extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $viewData = 'penjualan/penjualan_main';
      $data_operasi = ['title' => 'Buka Order'];

      // Cek apakah request AJAX
      if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         $this->view($viewData);
      } else {
         $this->view('layout', ['data_operasi' => $data_operasi]);
         $this->view($viewData);
      }
   }

   public function cart()
   {
      $viewData = 'penjualan/cart';
      $where = $this->wCabang . " AND id_pelanggan = 0";
      $data_main = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('sale', $where);
      $this->view($viewData, ['data_main' => $data_main]);
   }

   public function insert($page)
   {
      $id_harga = $_POST['f1'];
      $qty = $_POST['f2'];
      $note = $_POST['f3'];

      foreach ($this->harga as $a) {
         if ($a['id_harga'] == $id_harga) {
            $durasi = $a['id_durasi'];
            $hari = $a['hari'];
            $jam = $a['jam'];
            $item_group = $a['id_item_group'];

            if ($this->mdl_setting['def_price'] == 0) {
               $harga = $a['harga'];
            } else {
               $harga = $a['harga_b'];
               if ($harga == 0) {
                  $harga = $a['harga'];
               }
            }

            $layanan = $a['list_layanan'];
            $minOrder = $a['min_order'];
         }
      }

      $diskon_qty = 0;
      foreach ($this->diskon as $a) {
         if ($a['id_penjualan_jenis'] == $page && $a['qty_disc'] > 0) {
            if ($qty >= $a['qty_disc']) {
               $diskon_qty = $a['disc_qty'];
            }
         }
      }

      $yr = date('Y');
      $count_data = $this->db(date('Y'))->count('sale') + 1;
      $id_sale = ($yr - 2024) . $count_data;
      $data = [
         'id_penjualan' => $id_sale,
         'id_cabang' => $this->id_cabang,
         'id_item_group' => $item_group,
         'id_penjualan_jenis' => $page,
         'id_durasi' => $durasi,
         'hari' => $hari,
         'jam' => $jam,
         'harga' => $harga,
         'qty' => $qty,
         'note' => $note,
         'list_layanan' => $layanan,
         'diskon_qty' => $diskon_qty,
         'min_order' => $minOrder,
         'id_harga' => $id_harga,
         'insertTime' => $GLOBALS['now']
      ];

      $do = $this->db(date('Y'))->insert('sale', $data);
      if ($do['errno'] == 1062) {
         $max = $this->db(date('Y'))->max('sale', 'id_penjualan');
         $id_sale = $max + 1;
         $data['id_penjualan'] = $id_sale;
         $do = $this->db(date('Y'))->insert('sale', $data);
      }

      $set = "sort = sort+1";
      $whereSort = "id_harga = " . $id_harga;
      $this->db(0)->update("harga", $set, $whereSort);

      if ($do['errno'] <> 0) {
         print_r($do);
      } else {
         $_SESSION[URL::SESSID]['user']['book'] = date('Y');
         echo 0;
      }
   }

   public function proses()
   {
      $no_ref = (date('Y') - 2024) . date("mdHis") . rand(0, 9);

      $pelanggan = $_POST['f1'];
      $id_penerima = $_POST['f2'];
      //cek last ref;

      $where = $this->wCabang . " AND id_pelanggan <> 0 AND no_ref <> '' AND tuntas = 0 AND bin = 0 AND insertTime LIKE '" . date('Y-m-d') . "%' ORDER BY id_penjualan DESC LIMIT 1";
      $cek_ref = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where_row('sale', $where);

      if (isset($cek_ref['id_user'])) {
         if ($id_penerima == $cek_ref['id_user'] && $pelanggan == $cek_ref['id_pelanggan']) {
            $no_ref = $cek_ref['no_ref'];
         }
      }

      $where = $this->wCabang . " AND id_pelanggan = 0";
      $data = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('sale', $where);


      $disc_p = 0;

      $nama_pelanggan = "";
      $dp = $this->pelanggan[$pelanggan];

      $disc_p = $dp['disc'];
      $nama_pelanggan = $dp['nama_pelanggan'];
      $cabang_pelanggan = $dp['id_cabang'];

      $saldo = 0;
      foreach ($data as $a) {
         $saldo = 0;
         $id = $a['id_penjualan'];
         $id_jenis = $a['id_penjualan_jenis'];
         $idHarga = $a['id_harga'];
         $qty = $a['qty'];
         $id_cabang = $a['id_cabang'];

         if ($cabang_pelanggan <> $id_cabang) {
            continue;
         }

         $harga = $a['harga'];
         $total = $harga * $qty;
         $diskon_qty = $a['diskon_qty'];
         $member = $a['member'];

         //CEK JIKA DISKON KHUSUS
         $where_dk = "id_pelanggan = " . $pelanggan . " AND id_harga = " . $idHarga;
         $diskon_k = $this->db(0)->get_where_row("diskon_khusus", $where_dk);
         if (isset($diskon_k['diskon'])) {
            if ($diskon_k['diskon'] > 0) {
               $disc_p = $diskon_k['diskon'];
            }
         }

         $diskon_partner = $disc_p;

         if ($member == 0) {
            if ($diskon_qty > 0 && $diskon_partner == 0) {
               $total = $total - ($total * ($diskon_qty / 100));
            } else if ($diskon_qty == 0 && $diskon_partner > 0) {
               $total = $total - ($total * ($diskon_partner / 100));
            } else if ($diskon_qty > 0 && $diskon_partner > 0) {
               $total = $total - ($total * ($diskon_qty / 100));
               $total = $total - ($total * ($diskon_partner / 100));
            } else {
               $total = ($harga * $qty);
            }
         } else {
            $total = 0;
         }

         $saldo = $this->helper('Saldo')->saldoMember($pelanggan, $idHarga);
         if ($saldo >= $qty) {
            $set = "id_pelanggan = " . $pelanggan . ", no_ref = " . $no_ref . ", pelanggan = '" . $nama_pelanggan . "', member = 1, diskon_partner = " . $disc_p . ", total = " . $total . ", id_user = " . $id_penerima;
            $whereSet = "id_penjualan = " . $id;
            $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $whereSet);
         }

         $reset_diskon = "";
         if ($diskon_qty > 0 && $diskon_partner > 0) {
            foreach ($this->diskon as $a) {
               if ($a['id_penjualan_jenis'] == $id_jenis) {
                  if ($a['combo'] == 0) {
                     $reset_diskon = "diskon_qty = 0, ";
                  }
               }
            }
         }
         $where_update = "id_penjualan = " . $id;
         $set = $reset_diskon . "id_pelanggan = " . $pelanggan . ", pelanggan = '" . $nama_pelanggan . "', diskon_partner = " . $disc_p . ", total = " . $total . ", no_ref = " . $no_ref . ", id_user = " . $id_penerima;
         $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where_update);
      }

      $set = "sort = sort+1";
      $whereSort = "id_pelanggan = " . $pelanggan;
      $this->db(0)->update("pelanggan", $set, $whereSort);
   }

   public function updateCell()
   {
      $id = $_POST['id'];
      $value = $_POST['value'];
      $mode = $_POST['mode'];

      if ($mode == 1) {
         $col = "hari";
      } else if ($mode == 2) {
         $col = "jam";
      }

      $set = $col . " = '" . $value . "'";
      $where = "id_durasi_client  = " . $id;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }

   public function removeRow()
   {
      $id = $_POST['id'];
      $where = $this->wCabang . " AND id_penjualan = '" . $id . "'";
      $del = $this->db($_SESSION[URL::SESSID]['user']['book'])->delete('sale', $where);
      if ($del['errno'] <> 0) {
         echo $del['error'];
      } else {
         echo 0;
      }
   }

   public function addItemForm($data)
   {
      $data = explode("|", $data);
      $b = $this->db(0)->get_where_row("item_group", "id_item_group = " . $data[0])['item_list'];
      $c = $data[1];
      $this->view('penjualan/formItemAdd', ['data' => $b, 'id' => $c]);
   }

   public function orderPenjualanForm($id_penjualan, $id_harga, $saldo = false)
   {
      $data[1] = $id_penjualan;
      $data[2] = $id_harga;
      $data[3] = $saldo;
      $this->view('penjualan/formOrder', $data);
   }

   public function addItem($id)
   {
      $f1 = $_POST['f1'];
      $f2 = $_POST['f2'];
      $newItem = array($f1 => $f2);
      $item_list =  $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where_row('sale', $this->wCabang . " AND id_penjualan  = " . $id)['list_item'];
      if (strlen($item_list) == 0) {
         $value = serialize($newItem);
      } else {
         $arrItemList = unserialize($item_list);
         $arrItemList[$f1] = $f2;
         $value = serialize($arrItemList);
      }
      $set = "list_item = '" . $value . "'";
      $where = $this->wCabang . " AND id_penjualan = " . $id;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }

   public function removeItem()
   {
      $id = $_POST['id'];
      $key = $_POST['key'];
      $item_list =  $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where_row('sale', $this->wCabang . " AND id_penjualan  = " . $id)['list_item'];
      $arrItemList = unserialize($item_list);
      unset($arrItemList[$key]);
      $value = serialize($arrItemList);
      $set = "list_item = '" . $value . "'";
      $where = $this->wCabang . " AND id_penjualan = " . $id;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }

   public function sering($idPelanggan)
   {
      $viewData = 'penjualan/viewSering';
      $where = $this->wCabang . " AND id_harga <> 0 AND bin = 0 AND id_pelanggan = " . $idPelanggan . " GROUP BY id_harga, id_penjualan_jenis, id_item_group, list_layanan, id_durasi ORDER BY count(id_penjualan) DESC limit 2";
      $cols = "id_harga, id_penjualan_jenis, id_item_group, list_layanan, id_durasi, count(id_penjualan)";
      $data = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('sale', $cols, $where, 1);
      $this->view($viewData, ['data' => $data]);
   }

   function loadPelanggan()
   {
      $z = array('page' => "pelanggan");
      $view = 'data_list/pelanggan';
      $where = $this->wCabang;
      $order = 'id_pelanggan DESC';
      $data_main = $this->db(0)->get_where_order("pelanggan", $where, $order);
      $this->view($view, ['data_main' => $data_main, 'z' => $z]);
   }
}
