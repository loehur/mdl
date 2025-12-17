<?php

class Prepaid extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $view = 'prepaid/content';
      $data_operasi = ['title' => 'Pre/Post Paid'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $data['list'] = $this->db(0)->get_where("prepaid_list", "id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang']);
      $this->view($view, $data);
   }

   function buy()
   {
      $pin = $_POST['pin'];
      $id = $_POST['id'];
      if (!is_numeric($pin)) {
         $res = [
            'code' => 0,
            'msg' => "PIN TIDAK VALID"
         ];
         print_r(json_encode($res));
         exit();
      }
      $otp = $this->model("Enc")->otp($pin);
      //cekpin
      $user_data = $this->helper('User')->pin_today($_SESSION[URL::SESSID]['user']['username'], $otp);
      if (isset($user_data['otp'])) {
         //pin ok
         //cek limit
         $pre_list = $this->db(0)->get_where_row("prepaid_list", "pre_id = " . $id . " AND id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang']);
         $product_code = $pre_list['product_code'];
         $customer_id = $pre_list['customer_id'];
         $akan_dipakai = $pre_list['nominal'];
         $limit = $pre_list['monthly_limit'];

         $pakai_bulan_ini = $this->helper('Pre')->bulan_ini($product_code);
         $total_pakai = $akan_dipakai + $pakai_bulan_ini;

         if ($total_pakai > $limit) {
            $res = [
               'code' => 0,
               'msg' => "GAGAL - SUDAH MENCAPAI LIMIT BULANAN"
            ];
         } else {
            $ref_id = "mdlpre-" . date('YmdHi') . "-" . $_SESSION[URL::SESSID]['user']['id_cabang'];

            $col = [
               'id_cabang' => $_SESSION[URL::SESSID]['user']['id_cabang'],
               'ref_id' => $ref_id,
               'product_code' => $product_code,
               'customer_id' => $customer_id
            ];
            $do = $this->db(0)->insert("prepaid", $col);

            if ($do['errno'] == 0) {
               $a = $this->db(0)->get_where_row("prepaid", "ref_id = '" . $ref_id . "'");
               $proses = $this->model('IAK')->pre_pay($ref_id, $customer_id, $product_code);
               if (isset($proses['data'])) {
                  $d = $proses['data'];

                  $tr_status = isset($d['status']) ? $d['status'] : $a['tr_status'];
                  $price = isset($d['price']) ? $d['price'] : $a['price'];
                  $message = isset($d['message']) ? $d['message'] : $a['message'];
                  $balance = isset($d['balance']) ? $d['balance'] : $a['balance'];
                  $tr_id = isset($d['tr_id']) ? $d['tr_id'] : $a['tr_id'];
                  $rc = isset($d['rc']) ? $d['rc'] : $a['rc'];
                  $sn = isset($d['sn']) ? $d['sn'] : $a['sn'];

                  $where = "ref_id = '" . $ref_id . "'";
                  $set =  ['sn' => $sn, 'tr_status' => $tr_status, 'price' => $price, 'message' => $message, 'balance' => $balance, 'tr_id' => $tr_id, 'rc' => $rc];
                  $update = $this->db(0)->update('prepaid', $set, $where);
                  if ($update['errno'] == 0) {
                     $res = [
                        'code' => 1,
                        'msg' => "PEMBELIAN DI PROSES"
                     ];
                  } else {
                     $res = [
                        'code' => 0,
                        'msg' => $update['error']
                     ];
                  }
               } else {
                  $res = [
                     'code' => 0,
                     'msg' => "SERVER GANGGUAN, RELOAD HALAMAN DAN KLIK [cek status]"
                  ];
               }
            } else {
               $res = [
                  'code' => 0,
                  'msg' => $do['error']
               ];
            }
         }
      } else {
         $res = [
            'code' => 0,
            'msg' => "PIN TIDAK VALID"
         ];
      }
      print_r(json_encode($res));
   }

   function cek_status()
   {
      $ref_id = $_POST['ref_id'];
      $a = $this->db(0)->get_where_row("prepaid", "ref_id = '" . $ref_id . "'");
      $response = $this->model('IAK')->pre_cek($ref_id);
      if (isset($response['data'])) {
         $d = $response['data'];

         $tr_status = isset($d['status']) ? $d['status'] : $a['tr_status'];
         $price = isset($d['price']) ? $d['price'] : $a['price'];
         $message = isset($d['message']) ? $d['message'] : $a['message'];
         $balance = isset($d['balance']) ? $d['balance'] : $a['balance'];
         $tr_id = isset($d['tr_id']) ? $d['tr_id'] : $a['tr_id'];
         $rc = isset($d['rc']) ? $d['rc'] : $a['rc'];
         $sn = isset($d['sn']) ? $d['sn'] : $a['sn'];

         $where = "ref_id = '" . $ref_id . "'";
         $set =  ['sn' => $sn, 'tr_status' => $tr_status, 'price' => $price, 'message' => $message, 'balance' => $balance, 'tr_id' => $tr_id, 'rc' => $rc];
         $update = $this->db(0)->update('prepaid', $set, $where);
         if ($update['errno'] == 0) {
            echo 0;
         } else {
            echo $update['error'];
         }
      } else {
         echo  "DATA RESPONSE NOT FOUND";
      }
   }

   function cek_status_post()
   {
      $msg = "";
      $ref_id = $_POST['ref_id'];
      $where = "ref_id = '" . $ref_id . "'";
      $a = $this->db(0)->get_where_row('postpaid', $where);
      $month = $this->helper('Pre')->get_post_month();
      $response = $this->model('IAK')->post_cek($ref_id);
      if (isset($response['data'])) {
         $d = $response['data'];
         if (isset($d['status'])) {
            if ($d['status'] == $a['tr_status']) {
               echo $a['message'];
               exit();
            }
         }

         $message = isset($d['message']) ? $d['message'] : $a['message'];
         $rc = isset($d['response_code']) ? $d['response_code'] : $a['response_code'];
         $price = isset($d['price']) ? $d['price'] : $a['price'];
         $balance = isset($d['balance']) ? $d['balance'] : $a['balance'];
         $tr_id = isset($d['tr_id']) ? $d['tr_id'] : $a['tr_id'];
         $datetime = isset($d['datetime']) ? $d['datetime'] : $a['datetime'];
         $noref = isset($d['noref']) ? $d['noref'] : $a['noref'];
         $tr_status = isset($d['status']) ? $d['status'] : $a['tr_status'];

         if ($tr_status == 1) {
            $where = "customer_id = '" . $d['hp'] . "' AND code = '" . $d['code'] . "'";
            $set =  ['last_bill' => $month];
            $update = $this->db(0)->update('postpaid_list', $set, $where);
            if ($update['errno'] <> 0) {
               $alert = "DB ERROR - " . $update['error'];
               $msg .= $alert . "\n";
               $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
               if (!$res['status']) {
                  if (isset($res['data']['status'])) {
                     $msg .= "WHTASAPP ERROR - " . $res['data']['status'] . "\n";
                  } else {
                     $msg .= "WHTASAPP ERROR - SENDING FAILED\n";
                  }
               }
               echo $msg;
               exit();
            }
         }

         $where = "ref_id = '" . $ref_id . "'";
         $set =  ['tr_status' => $tr_status, 'datetime' => $datetime, 'noref' => $noref, 'price' => $price, 'message' => $message, 'balance' => $balance, 'tr_id' => $tr_id, 'response_code' => $rc];
         $update = $this->db(0)->update('postpaid', $set, $where);
         if ($update['errno'] == 0) {
            $msg = 0;
         } else {
            $alert = "DB ERROR - " . $update['error'];
            $msg .= $alert . "\n";
            $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
            if (!$res['status']) {
               if (isset($res['data']['status'])) {
                  $msg .= "WHTASAPP ERROR - " . $res['data']['status'] . "\n";
               } else {
                  $msg .= "WHTASAPP ERROR - SENDING FAILED\n";
               }
            }
         }
      } else {
         $alert = "DATA RESPONSE NOT FOUND - " . json_encode($response);
         $msg .= $alert . "\n";
         $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
         if (!$res['status']) {
            if (isset($res['data']['status'])) {
               $msg .= "WHTASAPP ERROR - " . $res['data']['status'] . "\n";
            } else {
               $msg .= "WHTASAPP ERROR - SENDING FAILED\n";
            }
         }
      }

      echo $msg;
   }

   function load_data()
   {
      $view = 'prepaid/data';
      $data['pre'] = $this->db(0)->get_where("prepaid", "id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'] . " ORDER BY id DESC LIMIT 5");
      $data['post'] = $this->db(0)->get_where("postpaid", "id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'] . " ORDER BY id DESC LIMIT 5");
      $this->view($view, $data);
   }
}
