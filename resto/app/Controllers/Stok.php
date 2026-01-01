<?php

class Stok extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Stok'];
      $data['tgl'] = [];
      $menu_induk = $this->db(0)->get_where('menu_item', "induk <> 0 ORDER BY freq DESC", "id");
      for ($i = 0; $i >= -6; $i--) {
         $sales = [];
         $tgl = date('Ymd', strtotime($i . ' days', strtotime(date('Y-m-d'))));
         array_push($data['tgl'], $tgl);
         $data['qty'][$tgl]['a'] = $this->db($this->book)->sum_col_where('stok', 'a', "tgl = '" . $tgl . "' AND a <> 0");
         $data['qty'][$tgl]['s'] = $this->db($this->book)->sum_col_where('stok', 's', "tgl = '" . $tgl . "' AND s <> 0");
         $tgl_pesan = date('Y-m-d', strtotime($i . ' days', strtotime(date('Y-m-d'))));
         $terjual = $this->db($this->book)->get_cols_where('pesanan', 'id_menu, SUM(qty) as qty', "insertTime LIKE '" . $tgl_pesan . "%' GROUP BY id_menu", 1, 'id_menu'); //sale

         foreach ($menu_induk as $id_menu => $d) {
            if (isset($terjual[$id_menu])) {
               if (isset($sales[$d['induk']])) {
                  $sales[$d['induk']] += ($terjual[$id_menu]['qty'] * $d['qty_induk']);
               } else {
                  $sales[$d['induk']] = ($terjual[$id_menu]['qty'] * $d['qty_induk']);
               }
            }
         }

         $data['qty'][$tgl]['t'] = array_sum($sales);
      }
      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   function cek($get, $mode = "a")
   {
      $data['tgl'] = $get;
      $tgl_pesan = date('Y-m-d', strtotime($get));

      $data['mode'] = $mode;
      $data['menu'] = $this->db(0)->get_where('menu_item', "hitung = 1 ORDER BY freq DESC", "id");
      if ($mode == "sa") {
         $terjual = $this->db($this->book)->get_cols_where('pesanan', 'id_menu, SUM(qty) as qty', "insertTime LIKE '" . $tgl_pesan . "%' GROUP BY id_menu", 1, 'id_menu'); //sale
         $menu_induk = $this->db(0)->get_where('menu_item', "induk <> 0 ORDER BY freq DESC", "id");
         foreach ($menu_induk as $id_menu => $d) {
            if (isset($terjual[$id_menu])) {
               if (isset($sales[$d['induk']])) {
                  $sales[$d['induk']] += ($terjual[$id_menu]['qty'] * $d['qty_induk']);
               } else {
                  $sales[$d['induk']] = ($terjual[$id_menu]['qty'] * $d['qty_induk']);
               }
            }
         }

         $data['data'] = $this->db($this->book)->get_where('stok', "tgl = '" . $get . "'", "id_menu");
         foreach ($data['menu'] as $key => $v) {
            if (!isset($data['data'][$key])) {
               $data['data'][$key]['a'] = 0;
            }

            if (isset($sales[$key])) {
               $data['data'][$key]['sa'] = $data['data'][$key]['a'] - $sales[$key];
            } else {
               $data['data'][$key]['sa'] = $data['data'][$key]['a'];
            }
         }
      } else {
         $data['data'] = $this->db($this->book)->get_where('stok', "tgl = '" . $get . "'", "id_menu");
         foreach ($data['menu'] as $key => $v) {
            if (!isset($data['data'][$key])) {
               $data['data'][$key][$mode] = 0;
            }
         }
      }
      $this->view(__CLASS__ . "/load", $data);
   }

   function update($mode = "a")
   {
      $p = $_POST;
      if ($p['tgl'] == date('Ymd')) {
         foreach ($p['data'] as $key => $d) {
            if ($d <> '') {
               $cols = "id, id_menu, " . $mode . ", tgl";
               $vals = "'" . $p['tgl'] . "_" . $key . "'," . $key . "," . $d . "," . $p['tgl'];
               $update = $mode . " = " . $d;
               $in = $this->db($this->book)->insertCols("stok", $cols, $vals, $update);
               if ($in['errno'] <> 0) {
                  echo $in['error'];
                  exit();
               }
            }
         }

         echo $this->db($this->book)->sum_col_where('stok', $mode, "tgl = '" . $p['tgl'] . "' AND " . $mode . " <> 0");
      } else {
         echo "date expired";
      }
   }
}
