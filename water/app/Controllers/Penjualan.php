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
      $id_user = $_SESSION[URL::SESSID]['user']['id_user'];
      $data['kat'] =  $_SESSION[URL::SESSID]['kat'];
      $data['order'] = $this->db(0)->get_where('ref', "step = 0 AND id_user = " . $id_user, "nomor");
      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($nomor = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data['nomor'] = $nomor;
      $data['pelanggan'] = $this->db(0)->get_where("pelanggan", $this->wCabang . " ORDER BY last_order DESC", 'id');
      $id_user = $_SESSION[URL::SESSID]['user']['id_user'];

      $cek = $this->db(0)->get_where_row('ref', "id_user = " . $id_user . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['menu'] = $_SESSION[URL::SESSID]['menu'];
         $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
         $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $cek['id'] . "' AND status_mutasi <> 2");
      } else {
         $data['order'] = [];
         $data['bayar'] = [];
      }

      $data['ref'] = $cek;
      if (count($cek) > 0 && $cek['pelanggan'] > 0) {
         $data['ref_pelanggan'] = $this->db(0)->get_where('ref', "id <> '" . $cek['id'] . "' AND tgl = '" . date("Y-m-d") . "' AND pelanggan = " . $cek['pelanggan'], 'id');
      } else {
         $data['ref_pelanggan'] = [];
      }

      $refs = "";
      if (count($data['ref_pelanggan']) > 0) {
         $arr_refs = array_keys($data['ref_pelanggan']);
         foreach ($arr_refs as $r) {
            $refs .= "'" . $r . "',";
         }
         $refs = trim($refs, ',');
         $data['order_pelanggan'] =  $this->db(0)->get_where('pesanan', "ref IN (" . $refs . ")");
      } else {
         $data['order_pelanggan'] = [];
      }

      $data['piutang'] = [];
      if (isset($cek['pelanggan']) && $cek['pelanggan'] > 0) {
         $data['ref_piutang'] = $this->db(0)->get_where('ref', "step = 3 AND pelanggan = " . $cek['pelanggan'], 'id');

         $order = [];
         $piutang = [];
         foreach ($data['ref_piutang'] as $key => $r) {
            $order[$key] = $this->db(0)->get_where('pesanan', "ref = '" . $key . "'");
            foreach ($order[$key] as $dk) {
               $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
               if (isset($piutang[$r['pelanggan']])) {
                  $piutang[$r['pelanggan']] += $subTotal;
               } else {
                  $piutang[$r['pelanggan']] = $subTotal;
               }
            }

            $cek_dibayar[$key] = $this->db(0)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $key . "'");
            foreach ($cek_dibayar[$key] as $b) {
               $piutang[$r['pelanggan']] -= $b['jumlah'];
            }
         }
         $data['piutang'] = $piutang;
      }


      $this->view($viewData, $data);
   }

   function tetapkan_pelanggan()
   {
      $p = $_POST;
      $id = $p['id'];
      $pelanggan = $p['pelanggan'];
      $qty = $p['qty'];

      if ($pelanggan <= 0) {
         echo "Pelanggan tidak ditemukan";
         exit();
      }

      $update = $this->db(0)->update('ref', "pelanggan = " . $pelanggan, "id = '" . $id . "'");

      if ($update['errno'] == 0) {
         $up = $this->db(0)->update('pelanggan', "titip = " . $qty . ", last_order = '" . date('Ymd') . "'", "id = " . $pelanggan);
         echo $up['errno'] == 0 ? 0 : $up['error'];
      } else {
         echo $update['error'];
      }
   }

   public function menu($id_kat = 0, $nomor = 0)
   {
      $viewData = __CLASS__ . '/menu';
      $id_user = $_SESSION[URL::SESSID]['user']['id_user'];
      if ($id_kat == 0) {
         $data['menu'] = $_SESSION[URL::SESSID]['menu'];
      } else {
         $menu_byKat =  $_SESSION[URL::SESSID]['menu_byKat'];
         $data['menu'] = isset($menu_byKat[$id_kat]) ? $menu_byKat[$id_kat] : [];
      }

      $cek = $this->db(0)->get_where_row('ref', "id_user = " . $id_user . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
      } else {
         $data['order'] = [];
      }

      $this->view($viewData, $data);
   }

   public function ubah($nomor = 0)
   {
      $viewData = __CLASS__ . '/ubah';
      $id_user = $_SESSION[URL::SESSID]['user']['id_user'];
      $data['menu'] = $this->db(0)->get_where('menu_item', $this->wCabang . " ORDER BY freq DESC", 'id');

      $cek = $this->db(0)->get_where_row('ref', "id_user = " . $id_user . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
      } else {
         $data['order'] = [];
      }

      $this->view($viewData, $data);
   }

   public function bayar()
   {
      $ref = $_POST['ref'];
      $uang_diterima = $_POST['dibayar'];
      $metode = $_POST['metode'];
      $note = isset($_POST['note']) ? $_POST['note'] : '';

      if ($metode == 1) {
         $st_mutasi = 1;
         $step = 1;
      } else {
         $st_mutasi = 0;
         $step = 4;
      }

      $data_ref = $this->db(0)->get_where_row('ref', "id = '" . $ref . "'");
      $pelanggan = $data_ref['pelanggan'];

      $order = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");

      $sisa_tagihan = 0;
      foreach ($order as $dk) {
         $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
         $sisa_tagihan += $subTotal;
      }

      $yg_sudah_dibayar = 0;
      $cek_dibayar = $this->db(0)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $ref . "'");
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

         $cols = "id_cabang, jenis_mutasi, jenis_transaksi, ref, metode_mutasi, status_mutasi, jumlah, id_user, dibayar, kembali, id_client, note";
         $vals = $this->id_cabang . ",1,1,'" . $ref . "'," . $metode . "," . $st_mutasi . "," . $jumlah_bayar . "," . $this->id_user . "," . $uang_diterima . "," . $kembali . "," . $pelanggan . ",'" . $note . "'";
         $in = $this->db(0)->insertCols("kas", $cols, $vals);
         if ($in['errno'] == 0) {
            if ($uang_diterima >= $sisa_tagihan) {
               $up = $this->db(0)->update('ref', "step = " . $step, "id = '" . $ref . "'");
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
      $id = $_POST['id'];
      $up = $this->db(0)->update('ref', "step = 3", "id = '" . $id . "'");
      echo $up['errno'] == 0 ? 0 : $up['error'];
   }

   public function cek_bayar($ref)
   {
      $viewData = __CLASS__ . '/bayar';
      $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $data['ref'] = $ref;
      $this->view($viewData, $data);
   }

   public function cek_piutang($ref)
   {
      $viewData = __CLASS__ . '/piutang';
      $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $data['ref'] = $ref;
      $this->view($viewData, $data);
   }

   function add_manual($nomor)
   {
      $p = $_POST;
      $id_user = $_SESSION[URL::SESSID]['user']['id_user'];
      $num_qty = preg_replace('/[^0-9]/', '', $p['qty']);
      $cek = $this->db(0)->get_where_row("ref", "id_user = " . $id_user . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $where = "id_menu = " . $p['id'] . " AND ref = '" . $cek['id'] . "'";
         $cek_menu = $this->db(0)->get_where_row("pesanan", $where);
         if (count($cek_menu) > 0) {
            if ($num_qty <= 0) {
               $del = $this->db(0)->delete_where("pesanan", $where);
               if ($del['errno'] == 0) {
                  $hitung_menu = $this->db(0)->count_where("pesanan", "ref = '" . $cek_menu['ref'] . "'");
                  if ($hitung_menu == 0) {
                     //update freq
                     $this->db(0)->update("menu_item", "freq = freq - 1", "id = " . $p['id']);
                     $this->db(0)->update("menu_kategori", "freq = freq - 1", "id = " . $p['id_kat']);

                     //hapus juga riwayat bayar
                     $this->db(0)->delete_where("kas", "ref = '" . $cek_menu['ref'] . "'");

                     $del = $this->db(0)->delete_where("ref", "id = '" . $cek_menu['ref'] . "'");
                     echo $del['errno'] == 0 ? 1 : $del['error'];
                  } else {
                     echo 0;
                  }
               } else {
                  echo $del['error'];
               }
            } else {
               $up = $this->db(0)->update("pesanan", "qty = " . $num_qty, $where);
               //update freq
               $this->db(0)->update("menu_item", "freq = freq + 1", "id = " . $p['id']);
               $this->db(0)->update("menu_kategori", "freq = freq + 1", "id = " . $p['id_kat']);
               echo $up['errno'] == 0 ? 0 : $up['error'];
            }
         } else {
            $cols = "ref, id_menu, qty, harga";
            $vals = "'" . $cek['id'] . "'," . $p['id'] . "," . $num_qty . "," . $_SESSION[URL::SESSID]['menu'][$p['id']]['harga'];
            $in = $this->db(0)->insertCols("pesanan", $cols, $vals);
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
         $cols = "id, nomor, tgl, jam, id_cabang, id_user";
         $vals = "'" . $ref . "'," . $nomor . ",'" . date('Y-m-d') . "','" . date("H:i") . "'," . $this->id_cabang . ", " . $id_user;
         $in = $this->db(0)->insertCols("ref", $cols, $vals);
         if ($in['errno'] == 0) {
            $p = $_POST;
            $cols = "ref, id_menu, qty, harga";
            $vals = "'" . $ref . "'," . $p['id'] . "," . $num_qty . "," . $_SESSION[URL::SESSID]['menu'][$p['id']]['harga'];
            $in = $this->db(0)->insertCols("pesanan", $cols, $vals);
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
      $cek_menu = $this->db(0)->get_where_row("pesanan", $where);
      $max_diskon = $cek_menu['harga'] * $cek_menu['qty'];
      if ($p['diskon'] > $max_diskon) {
         echo "Dikon melebihi Total";
         exit();
      }
      $up = $this->db(0)->update("pesanan", "diskon = " . $p['diskon'], $where);
      echo $up['errno'] == 0 ? 0 : $up['error'];
   }
}
