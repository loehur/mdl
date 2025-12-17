<?php

class Broadcast extends Controller
{

   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }
   public function i($mode = 1)
   {

      $dateT = [];
      $dateF = [];
      $data = [];

      if (isset($_POST['d'])) {
         $dateFrom = $_POST['Y'] . "-" . $_POST['m'] . "-" . $_POST['d'];
         $dateTo = $_POST['Yt'] . "-" . $_POST['mt'] . "-" . $_POST['dt'];
         $dateF['d'] = $_POST['d'];
         $dateF['m'] = $_POST['m'];
         $dateF['Y'] = $_POST['Y'];
         $dateT['d'] = $_POST['dt'];
         $dateT['m'] = $_POST['mt'];
         $dateT['Y'] = $_POST['Yt'];
      }

      $cols = "id_pelanggan, id_cabang";
      $dPelanggan = $this->db(0)->get('pelanggan');

      $data = [];
      if ($mode == 1) {
         //DALAM PROSES
         $data_operasi = ['title' => 'Broadcast PDP', 'vLaundry' => false];
         if (isset($_POST['d'])) {
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(insertTime) >= '" . $dateFrom . "' AND DATE(insertTime) <= '" . $dateTo . "' GROUP BY id_pelanggan, id_cabang";
            $i = URL::FIRST_YEAR;
            while ($i <= date('Y')) {
               $ks = $this->db($i)->get_cols_where('sale', $cols, $where, 1);
               if (count($ks) > 0) {
                  foreach ($ks as $ksv) {
                     array_push($data, $ksv);
                  }
               }
               $i += 1;
            }
         }
         $this->view('layout', ['data_operasi' => $data_operasi]);
         $this->view('broadcast/main', ['data' => $data, 'mode' => $mode, 'dateF' => $dateF, 'dateT' => $dateT, 'pelanggan' => $dPelanggan]);
      } else if ($mode == 3) {
         //SEMUA PELANGGAN
         $data_operasi = ['title' => 'Broadcast Semua Pelanggan', 'vLaundry' => false];
         if (isset($_POST['d'])) {
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND DATE(insertTime) >= '" . $dateFrom . "' AND DATE(insertTime) <= '" . $dateTo . "' GROUP BY id_pelanggan, id_cabang";
            $i = URL::FIRST_YEAR;
            while ($i <= date('Y')) {
               $ks = $this->db($i)->get_cols_where('sale', $cols, $where, 1);
               if (count($ks) > 0) {
                  foreach ($ks as $ksv) {
                     array_push($data, $ksv);
                  }
               }
               $i += 1;
            }
         }

         $this->view('layout', ['data_operasi' => $data_operasi]);
         $this->view('broadcast/main', ['data' => $data, 'mode' => $mode, 'dateF' => $dateF, 'dateT' => $dateT, 'pelanggan' => $dPelanggan]);
      } else if ($mode == 2) {
         //TUNTAS
         $data_operasi = ['title' => 'Broadcast PNP', 'vLaundry' => false];
         if (isset($_POST['d'])) {
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 1 AND DATE(insertTime) >= '" . $dateFrom . "' AND DATE(insertTime) <= '" . $dateTo . "' GROUP BY id_pelanggan, id_cabang";
            $i = URL::FIRST_YEAR;
            while ($i <= date('Y')) {
               $ks = $this->db($i)->get_cols_where('sale', $cols, $where, 1);
               if (count($ks) > 0) {
                  foreach ($ks as $ksv) {
                     array_push($data, $ksv);
                  }
               }
               $i += 1;
            }
         }

         $this->view('layout', ['data_operasi' => $data_operasi]);
         $this->view('broadcast/main', ['data' => $data, 'mode' => $mode, 'dateF' => $dateF, 'dateT' => $dateT, 'pelanggan' => $dPelanggan]);
      } else if ($mode == 4) {
         $data = [];
         $data_operasi = ['title' => 'Broadcast List', 'vLaundry' => false];
         $cols = "insertTime, text, count(insertTime) as c";
         $where = $this->wCabang . " AND tipe = 5 GROUP BY insertTime, text";
         $data = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('notif', $cols, $where, 1);
         $this->view('layout', ['data_operasi' => $data_operasi]);
         $this->view('broadcast/list', $data);
      }
   }

   public function load($mode, $time_e, $st)
   {
      $time = base64_decode($time_e);
      $data = [];
      if ($mode == 1) {
         $where = "insertTime = '" . $time . "' AND proses = '" . $st . "'";
      } else {
         $where = "insertTime = '" . $time . "' AND state = '" . $st . "'";
      }

      $data = [];
      $i = URL::FIRST_YEAR;
      while ($i <= date('Y')) {
         $ks = $this->db($i)->get_where('notif', $where);
         if (count($ks) > 0) {
            foreach ($ks as $ksv) {
               array_push($data, $ksv);
            }
         }
         $i += 1;
      }
      $this->view('broadcast/load', $data);
   }
   public function load_1()
   {
      echo "halo";
   }


   public function insert()
   {
      $text_ori = $_POST['text'];
      $broad = json_decode($_POST['broad'], JSON_PRETTY_PRINT);
      $cols =  'insertTime, id_cabang, no_ref, phone, text, tipe, id_api, proses';
      $hp = "";
      $time = date('Y-m-d H:i:s');
      $cab = $this->id_cabang;
      $text = $text_ori;

      foreach ($broad as $k => $v) {
         $hp .= $v['no'] . ",";
      }

      $hp = rtrim($hp, ',');

      $res = $this->model(URL::WA_API[1])->send_b($hp, $text, URL::WA_TOKEN[1]);
      if (isset($res['id'])) {
         foreach ($res["id"] as $k => $v) {
            $status = $res['data']['status'];
            $target = $res["target"][$k];
            $vals = "'" . $time . "'," . $cab . ",'" . $v . "','" . $target . "','" . $text_ori . "',5,'" . $v . "','" . $status . "'";
            $setOne = "no_ref = '" . $v . "' AND tipe = 5";
            $where = $this->wCabang . " AND " . $setOne;
            $data_main = $this->db(date('Y'))->count_where('notif', $where);
            if ($data_main < 1) {
               $data = [
                  'insertTime' => $time,
                  'id_cabang' => $cab,
                  'no_ref' => $v,
                  'phone' => $target,
                  'text' => $text_ori,
                  'tipe' => 5,
                  'id_api' => $v,
                  'proses' => $status
               ];
               $this->db(date('Y'))->insert('notif', $data);
            }
         }
      }
      print_r(json_encode($res));
   }
}
