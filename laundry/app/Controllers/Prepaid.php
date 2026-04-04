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
      $data['list'] = $this->db(100)->get_where("prepaid_list", "bisnis = 'laundry' AND id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang']);
      $this->view($view, $data);
   }

   function cek_status()
   {
      $ref_id = $_POST['ref_id'];
      $a = $this->db(100)->get_where_row("prepaid", "ref_id = '" . $ref_id . "'");
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
         $update = $this->db(100)->update('prepaid', $set, $where);
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
      $a = $this->db(100)->get_where_row('postpaid', $where);
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
            $update = $this->db(100)->update('postpaid_list', $set, $where);
            if ($update['errno'] <> 0) {
               $alert = "DB ERROR - " . $update['error'];
               $msg .= $alert . "\n";
               $this->model('Log')->write("[Prepaid::cek_status_post] Error: " . $update['error'] . " | Query: " . $update['query']);
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
         $update = $this->db(100)->update('postpaid', $set, $where);
         if ($update['errno'] == 0) {
            $msg = 0;
         } else {
            $alert = "DB ERROR - " . $update['error'];
            $msg .= $alert . "\n";
            $this->model('Log')->write("[Prepaid::cek_status_post] Error: " . $update['error'] . " | Query: " . $update['query']);
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
         $this->model('Log')->write("[Prepaid::cek_status_post] Error: " . $alert);
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
      $data['pre'] = $this->db(100)->get_where("prepaid", "bisnis = 'laundry' AND id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'] . " ORDER BY id DESC LIMIT 5");
      $data['post'] = $this->db(100)->get_where("postpaid", "bisnis = 'laundry' AND id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'] . " ORDER BY id DESC LIMIT 5");
      $this->view($view, $data);
   }
}
