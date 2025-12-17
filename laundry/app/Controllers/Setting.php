<?php

class Setting extends Controller
{
   public $page = __CLASS__;

   public function __construct()
   {
      $this->operating_data();
      $this->v_content = $this->page . "/content";
      $this->v_viewer = $this->page . "/viewer";
   }

   public function index()
   {
      $this->view("layout", [
         "content" => $this->v_content,
         "data_operasi" => ['title' => "Setting"]
      ]);

      $this->viewer();
   }

   public function viewer()
   {
      $this->view($this->v_viewer, ["page" => $this->page]);
   }

   public function content()
   {
      $this->view($this->v_content);
   }

   public function updateCell()
   {
      $value = $_POST['value'];
      $mode = $_POST['mode'];

      $whereCount = $this->wCabang . " AND " . $mode . " >= 0";
      $dataCount = $this->db(0)->count_where('setting', $whereCount);
      if ($dataCount >= 1) {
         $set = $mode . " = '" . $value . "'";
         $where = $this->wCabang;
         $query = $this->db(0)->update("setting", $set, $where);
         if ($query['errno'] == 0) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
         }
      } else {
         $data = [
            'id_cabang' => $this->id_cabang,
            'print_ms' => $value
         ];
         $this->db(0)->insert('setting', $data);
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      }
   }

   function salin_gaji()
   {
      $this->session_cek(1);
      $id_sumber = $_POST['sumber'];
      $id_target = $_POST['target'];

      if ($id_target == 0) {
         $table = "user";
         $where = "en = 1";
         $karyawan = $this->db(0)->get_where($table, $where);
      }

      $gaji['laundry'] = $this->db(0)->get_where('gaji_laundry', 'id_karyawan = ' . $id_sumber);
      foreach ($gaji['laundry'] as $gl) {
         $penjualan = $gl['jenis_penjualan'];
         $id_layanan = $gl['id_layanan'];
         $fee = $gl['gaji_laundry'];
         $target = $gl['target'];
         $bonus_target = $gl['bonus_target'];
         $max_target = $gl['max_target'];

         if ($id_target <> 0) {
            $where = "id_karyawan = " . $id_target . " AND jenis_penjualan = " . $penjualan . " AND id_layanan = " . $id_layanan;
            $data_main = $this->db(0)->count_where('gaji_laundry', $where);
            if ($data_main < 1) {
               $data = [
                  'id_karyawan' => $id_target,
                  'jenis_penjualan' => $penjualan,
                  'id_layanan' => $id_layanan,
                  'gaji_laundry' => $fee,
                  'target' => $target,
                  'bonus_target' => $bonus_target,
                  'max_target' => $max_target
               ];
               $this->db(0)->insert('gaji_laundry', $data);
            } else {
               $set = 'gaji_laundry = ' . $fee;
               $this->db(0)->update('gaji_laundry', $set, $where);
            }
         } else {
            foreach ($karyawan as $k) {
               $id_target = $k['id_user'];
               $where = "id_karyawan = " . $id_target . " AND jenis_penjualan = " . $penjualan . " AND id_layanan = " . $id_layanan;
               $data_main = $this->db(0)->count_where('gaji_laundry', $where);
               if ($data_main < 1) {
                  $data = [
                     'id_karyawan' => $id_target,
                     'jenis_penjualan' => $penjualan,
                     'id_layanan' => $id_layanan,
                     'gaji_laundry' => $fee,
                     'target' => $target,
                     'bonus_target' => $bonus_target,
                     'max_target' => $max_target
                  ];
                  $this->db(0)->insert('gaji_laundry', $data);
               } else {
                  $set = 'gaji_laundry = ' . $fee;
                  $this->db(0)->update('gaji_laundry', $set, $where);
               }
            }
         }
      }

      $gaji['pengali'] = $this->db(0)->get_where('gaji_pengali', 'id_karyawan = ' . $id_sumber);
      foreach ($gaji['pengali'] as $gl) {
         $id_pengali = $gl['id_pengali'];
         $fee = $gl['gaji_pengali'];

         //Abaikan Jika Tunjangan
         if ($id_pengali == 4) {
            continue;
         }

         if ($id_target <> 0) {
            $where = "id_karyawan = " . $id_target . " AND id_pengali = " . $id_pengali;
            $data_main = $this->db(0)->count_where('gaji_pengali', $where);
            if ($data_main < 1) {
               $data = [
                  'id_karyawan' => $id_target,
                  'id_pengali' => $id_pengali,
                  'gaji_pengali' => $fee
               ];
               $this->db(0)->insert('gaji_pengali', $data);
            } else {
               $set = 'gaji_pengali = ' . $fee;
               $this->db(0)->update('gaji_pengali', $set, $where);
            }
         } else {
            foreach ($karyawan as $k) {
               $id_target = $k['id_user'];
               $where = "id_karyawan = " . $id_target . " AND jenis_penjualan = " . $penjualan . " AND id_layanan = " . $id_layanan;
               $data_main = $this->db(0)->count_where('gaji_laundry', $where);
               if ($data_main < 1) {
                  $data = [
                     'id_karyawan' => $id_target,
                     'jenis_penjualan' => $penjualan,
                     'id_layanan' => $id_layanan,
                     'gaji_laundry' => $fee,
                     'target' => $target,
                     'bonus_target' => $bonus_target,
                     'max_target' => $max_target
                  ];
                  $this->db(0)->insert('gaji_laundry', $data);
               } else {
                  $set = 'gaji_laundry = ' . $fee;
                  $this->db(0)->update('gaji_laundry', $set, $where);
               }
            }
         }
      }
   }

   /**
    * Halaman setting printer untuk kasir
    */
   public function printer()
   {
      // Allow kasir to access this page (session_cek without level check)
      $this->operating_data();
      $this->view("layout", [
         "content" => "Setting/printer_content",
         "data_operasi" => ['title' => "Printer Setting"]
      ]);

      $this->view("Setting/printer_content");
   }

   public function updatePrinterMargins()
   {
      header('Content-Type: application/json');
      
      $top = isset($_POST['margin_top']) ? intval($_POST['margin_top']) : 0;
      $bottom = isset($_POST['feed_lines']) ? intval($_POST['feed_lines']) : 0;

      // Force range limit 0-10
      if ($top < 0) $top = 0;
      if ($top > 10){
echo json_encode(['status' => 'error', 'message' => 'Margin top must be between 0 and 10']) ;
exit;
      } 
      if ($bottom < 0) $bottom = 0;
      if ($bottom > 10){
echo json_encode(['status' => 'error', 'message' => 'Feed lines must be between 0 and 10']) ;
exit;
      }

      $where = $this->wCabang;
      $count = $this->db(0)->count_where('setting', $where);

      if ($count > 0) {
         $set = "margin_printer_top = '$top', margin_printer_bottom = '$bottom'";
         $result = $this->db(0)->update('setting', $set, $where);
      } else {
         $data = [
            'id_cabang' => $this->id_cabang,
            'margin_printer_top' => $top,
            'margin_printer_bottom' => $bottom
         ];
         $result = $this->db(0)->insert('setting', $data);
      }

      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      echo json_encode(['status' => 'success', 'message' => 'Margin printer berhasil disimpan']);
   }
}
