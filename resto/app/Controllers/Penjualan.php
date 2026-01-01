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
      $layout = ['title' => 'Buka Order'];
      $data['kat'] =  $_SESSION['resto_kat'];
      $data['order_0'] = $this->db($this->book)->get_where('ref', "step = 0 AND mode = 0", "nomor");
      $data['order_1'] = $this->db($this->book)->get_where('ref', "step = 0 AND mode = 1", "nomor");
      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($mode = 0, $nomor = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data['mode'] = $mode;
      $data['nomor'] = $nomor;

      $cek = $this->db($this->book)->get_where_row('ref', "mode = " . $mode . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['menu'] = $_SESSION['resto_menu'];
         $data['order'] = $this->db($this->book)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
         $data['bayar'] = $this->db($this->book)->get_where('kas', "ref = '" . $cek['id'] . "' AND status_mutasi <> 2");
      } else {
         $data['order'] = [];
         $data['bayar'] = [];
      }

      $data['ref'] = $cek;
      $this->view($viewData, $data);
   }

   public function menu($id_kat = 0, $mode = 0, $nomor = 0)
   {
      $viewData = __CLASS__ . '/menu';
      if ($id_kat == 0) {
         $data['menu'] = $_SESSION['resto_menu'];
      } else {
         $menu_byKat =  $_SESSION['resto_menu_byKat'];
         $data['menu'] = isset($menu_byKat[$id_kat]) ? $menu_byKat[$id_kat] : [];
      }

      $cek = $this->db($this->book)->get_where_row('ref', "mode = " . $mode . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['order'] = $this->db($this->book)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
      } else {
         $data['order'] = [];
      }

      $this->view($viewData, $data);
   }

   public function ubah($mode = 0, $nomor = 0)
   {
      $viewData = __CLASS__ . '/ubah';
      $data['menu'] = $this->db(0)->get_where('menu_item', $this->wCabang . " ORDER BY freq DESC", 'id');

      $cek = $this->db($this->book)->get_where_row('ref', "mode = " . $mode . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['order'] = $this->db($this->book)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
      } else {
         $data['order'] = [];
      }

      $this->view($viewData, $data);
   }

   public function bayar()
   {
      $uang_diterima = $_POST['dibayar'];
      $metode = $_POST['metode'];
      $ref = $_POST['ref'];

      if ($metode == 1) {
         $st_mutasi = 1;
         $step = 1;
      } else {
         $st_mutasi = 0;
         $step = 4;
      }

      $order = $this->db($this->book)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");

      $sisa_tagihan = 0;
      foreach ($order as $dk) {
         $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
         $sisa_tagihan += $subTotal;
      }

      $yg_sudah_dibayar = 0;
      $cek_dibayar = $this->db($this->book)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $ref . "'");
      foreach ($cek_dibayar as $b) {
         $yg_sudah_dibayar += $b['jumlah'];
         if ($b['status_mutasi'] == 0) {
            $step = 4; //checking
         }
      }

      $sisa_tagihan -= $yg_sudah_dibayar;

      if ($sisa_tagihan > 0) {
         $kembali = $uang_diterima - $sisa_tagihan;
         if ($kembali < 0) {
            $kembali = 0;
         }

         if ($uang_diterima >= $sisa_tagihan) {
            $jumlah_bayar = $sisa_tagihan;
         } else {
            $jumlah_bayar = $uang_diterima;
         }

         $cols = "id_cabang, jenis_mutasi, jenis_transaksi, ref, metode_mutasi, status_mutasi, jumlah, id_user, dibayar, kembali";
         $vals = $this->id_cabang . ",1,1,'" . $ref . "'," . $metode . "," . $st_mutasi . "," . $jumlah_bayar . "," . $this->id_user . "," . $uang_diterima . "," . $kembali;
         $in = $this->db($this->book)->insertCols("kas", $cols, $vals);
         if ($in['errno'] == 0) {
            if ($uang_diterima >= $sisa_tagihan) {
               $up = $this->db($this->book)->update('ref', "step = " . $step, "id = '" . $ref . "'");
               echo $up['errno'] == 0 ? 0 : $up['error'];
            } else {
               echo 1;
            }
         } else {
            echo $in['error'];
         }
      }
   }

   public function piutang()
   {
      $pelanggan = $_POST['pelanggan'];
      $ref = $_POST['ref'];

      if ($pelanggan <= 0) {
         echo "Pelanggan tidak ditemukan";
         exit();
      }

      $order = $this->db($this->book)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");

      $total = 0;
      foreach ($order as $dk) {
         $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
         $total += $subTotal;
      }

      if ($total > 0) {
         $up = $this->db($this->book)->update('ref', "step = 3, pelanggan = " . $pelanggan, "id = '" . $ref . "'");
         echo $up['errno'] == 0 ? 0 : $up['error'];
      }
   }

   public function cek_bayar($ref)
   {
      $viewData = __CLASS__ . '/bayar';
      $data['order'] = $this->db($this->book)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db($this->book)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $data['ref'] = $ref;
      $this->view($viewData, $data);
   }

   public function cek_piutang($ref)
   {
      $viewData = __CLASS__ . '/piutang';
      $data['order'] = $this->db($this->book)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db($this->book)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $data['pelanggan'] = $this->db(0)->get("pelanggan");
      $data['ref'] = $ref;
      $this->view($viewData, $data);
   }

   function add_manual($mode, $nomor)
   {
      $p = $_POST;
      $num_qty = preg_replace('/[^0-9]/', '', $p['qty']);
      $cek = $this->db($this->book)->get_where_row("ref", "mode = " . $mode . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $where = "id_menu = " . $p['id'] . " AND ref = '" . $cek['id'] . "'";
         $cek_menu = $this->db($this->book)->get_where_row("pesanan", $where);
         if (count($cek_menu) > 0) {
            if ($num_qty <= 0) {
               $del = $this->db($this->book)->delete_where("pesanan", $where);
               if ($del['errno'] == 0) {
                  $hitung_menu = $this->db($this->book)->count_where("pesanan", "ref = '" . $cek_menu['ref'] . "'");
                  if ($hitung_menu == 0) {
                     //update freq
                     $this->db(0)->update("menu_item", "freq = freq - 1", "id = " . $p['id']);
                     $this->db(0)->update("menu_kategori", "freq = freq - 1", "id = " . $p['id_kat']);

                     $del = $this->db($this->book)->delete_where("ref", "id = '" . $cek_menu['ref'] . "'");
                     echo $del['errno'] == 0 ? 1 : $del['error'];
                  } else {
                     echo 0;
                  }
               } else {
                  echo $del['error'];
               }
            } else {
               $up = $this->db($this->book)->update("pesanan", "qty = " . $num_qty, $where);
               //update freq
               $this->db(0)->update("menu_item", "freq = freq + 1", "id = " . $p['id']);
               $this->db(0)->update("menu_kategori", "freq = freq + 1", "id = " . $p['id_kat']);
               echo $up['errno'] == 0 ? 0 : $up['error'];
            }
         } else {
            $cols = "ref, id_menu, qty, harga";
            $vals = "'" . $cek['id'] . "'," . $p['id'] . "," . $num_qty . "," . $_SESSION['resto_menu'][$p['id']]['harga'];
            $in = $this->db($this->book)->insertCols("pesanan", $cols, $vals);
            //update freq
            $this->db(0)->update("menu_item", "freq = freq + 1", "id = " . $p['id']);
            $this->db(0)->update("menu_kategori", "freq = freq + 1", "id = " . $p['id_kat']);
            echo $in['errno'] == 0 ? 0 : $in['error'];
         }
      } else {
         if ($num_qty <= 0) {
            echo "Qty 0 diabaikan";
            exit();
         }

         $ref = (date('Y') - 2024) . date('mdHis') . $this->id_cabang;
         $cols = "id, mode, nomor, tgl, jam, id_cabang";
         $vals = "'" . $ref . "'," . $mode . "," . $nomor . ",'" . date('Y-m-d') . "','" . date("H:i") . "'," . $this->id_cabang;
         $in = $this->db($this->book)->insertCols("ref", $cols, $vals);
         if ($in['errno'] == 0) {
            $p = $_POST;
            $cols = "ref, id_menu, qty, harga";
            $vals = "'" . $ref . "'," . $p['id'] . "," . $num_qty . "," . $_SESSION['resto_menu'][$p['id']]['harga'];
            $in = $this->db($this->book)->insertCols("pesanan", $cols, $vals);
            //update freq
            $this->db(0)->update("menu_item", "freq = freq + 1", "id = " . $p['id']);
            $this->db(0)->update("menu_kategori", "freq = freq + 1", "id = " . $p['id_kat']);
            echo $in['errno'] == 0 ? 0 : $in['error'];
         } else {
            echo $in['error'];
         }
      }
   }

   function set_diskon()
   {
      $p = $_POST;
      $where = "id = " . $p['id'];
      $cek_menu = $this->db($this->book)->get_where_row("pesanan", $where);
      $max_diskon = $cek_menu['harga'] * $cek_menu['qty'];
      if ($p['diskon'] > $max_diskon) {
         echo "Dikon melebihi Total";
         exit();
      }
      $up = $this->db($this->book)->update("pesanan", "diskon = " . $p['diskon'], $where);
      echo $up['errno'] == 0 ? 0 : $up['error'];
   }

   function set_harga()
   {
      $p = $_POST;
      $where = "id = " . $p['id'];
      $cek_menu = $this->db($this->book)->get_where_row("pesanan", $where);
      $min_harga = $cek_menu['harga'];
      if ($p['harga'] < $min_harga) {
         echo "Harga harus lebih mahal dari harga awal";
         exit();
      }
      $up = $this->db($this->book)->update("pesanan", "harga = " . $p['harga'], $where);
      echo $up['errno'] == 0 ? 0 : $up['error'];
   }
}
