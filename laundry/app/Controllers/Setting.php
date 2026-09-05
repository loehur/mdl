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

   /**
    * Halaman download aplikasi Android (QRIS Display, MDL Chat)
    */
   public function android()
   {
      $this->operating_data();
      $this->view("layout", [
         "content" => "Setting/android_content",
         "data_operasi" => ['title' => "Android"]
      ]);

      $this->view("Setting/android_content");
   }

   /** Halaman Tools untuk perangkat cabang. */
   public function tools()
   {
      $this->operating_data();
      $this->view("layout", [
         "content" => "Setting/tools_content",
         "data_operasi" => ['title' => "Unduhan Tools"]
      ]);

      $this->view("Setting/tools_content");
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
