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
      $data['qty'] = [];

      // Tanggal Range
      $tgl_end = date('Ymd');
      $tgl_start = date('Ymd', strtotime('-6 days'));
      $tgl_start_pesan = date('Y-m-d', strtotime('-6 days'));

      // 1. Ambil Menu Induk (Resep)
      $menu_induk = $this->db(0)->get_where('menu_item', "induk <> 0 ORDER BY freq DESC", "id");

      // 2. BATCH QUERY STOK (Stok Awal & Sisa) - Group by Tgl
      $q_stok = $this->db(0)->run("
         SELECT tgl, SUM(a) as start, SUM(s) as sisa 
         FROM stok 
         WHERE tgl BETWEEN '$tgl_start' AND '$tgl_end' 
         GROUP BY tgl
      ");
      $stok_map = [];
      foreach ($q_stok as $row) $stok_map[$row['tgl']] = $row;

      // 3. BATCH QUERY PESANAN (Terjual) - Group by Tgl & Menu
      $q_pesanan = $this->db(0)->run("
         SELECT DATE(insertTime) as tgl_pesan, id_menu, SUM(qty) as qty 
         FROM pesanan 
         WHERE insertTime >= '$tgl_start_pesan 00:00:00' 
         GROUP BY DATE(insertTime), id_menu
      ");
      $pesanan_map = [];
      foreach ($q_pesanan as $p) {
         $k_tgl = date('Ymd', strtotime($p['tgl_pesan']));
         $pesanan_map[$k_tgl][$p['id_menu']] = $p['qty'];
      }

      // 4. Process Logic (PHP Only) -> Loop 7 hari
      for ($i = 0; $i >= -6; $i--) {
         $tgl = date('Ymd', strtotime($i . ' days'));
         array_push($data['tgl'], $tgl);
         
         // Set Stok Awal & Sisa dari map
         $data['qty'][$tgl]['a'] = isset($stok_map[$tgl]) ? $stok_map[$tgl]['start'] : 0;
         $data['qty'][$tgl]['s'] = isset($stok_map[$tgl]) ? $stok_map[$tgl]['sisa'] : 0;

         // Hitung Terjual (Konversi Resep)
         $sales_total = 0;
         if (isset($pesanan_map[$tgl])) {
            $terjual_hari_ini = $pesanan_map[$tgl];
            
            // Loop Menu Induk untuk hitung total bahan terpakai
            foreach ($menu_induk as $id_menu => $d) {
               if (isset($terjual_hari_ini[$id_menu])) {
                  // qty terjual * porsi induk
                  $sales_total += ($terjual_hari_ini[$id_menu] * $d['qty_induk']);
               }
            }
         }
         $data['qty'][$tgl]['t'] = $sales_total;
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
         $terjual = $this->db(0)->get_cols_where('pesanan', 'id_menu, SUM(qty) as qty', "insertTime LIKE '" . $tgl_pesan . "%' GROUP BY id_menu", 1, 'id_menu'); //sale
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

         $data['data'] = $this->db(0)->get_where('stok', "tgl = '" . $get . "'", "id_menu");
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
         $data['data'] = $this->db(0)->get_where('stok', "tgl = '" . $get . "'", "id_menu");
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
               $in = $this->db(0)->insertCols("stok", $cols, $vals, $update);
               if ($in['errno'] <> 0) {
                  echo $in['error'];
                  exit();
               }
            }
         }

         echo $this->db(0)->sum_col_where('stok', $mode, "tgl = '" . $p['tgl'] . "' AND " . $mode . " <> 0");
      } else {
         echo "date expired";
      }
   }
}
