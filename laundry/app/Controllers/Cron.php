<?php

class Cron extends Controller
{
   public function send()
   {
      $pending = 0;
      $expire = 0;
      $sent = 0;
      $where = "proses = '' AND status <> 5 ORDER BY insertTime ASC";
      $data_pending = '';

      $data = $this->db(date('Y'))->get_where('notif', $where);
      $pending += count($data);
      foreach ($data as $dm) {
         $id_notif = $dm['id_notif'];
         $data_pending .= $data['id_cabang'] . "#" . $id_notif . ' ';

         $expired_bol = false;

         $t1 = strtotime($dm['insertTime']);
         $t2 = strtotime(date("Y-m-d H:i:s"));
         $diff = $t2 - $t1;
         $hours = round($diff / (60 * 60), 1);

         if ($hours > 24) {
            $expired_bol = true;
         }

         if ($expired_bol == false) {
            $hp = $dm['phone'];
            $text = $dm['text'];
            $res = $this->helper('Notif')->send_wa($hp, $text);

            if ($res['status']) {
               $status = $res['data']['status'];
               $set = ['status' => 1, 'proses' => $status, 'id_api' => $res['data']['id']];
               $where2 = "id_notif = '" . $id_notif . "'";
               $this->db(date('Y'))->update('notif', $set, $where2);
               $sent += 1;
            } else {
               $status = $res["data"]['status'];
               $set = ['status' => 4, 'proses' => $status];
               $where2 = "id_notif = '" . $id_notif . "'";
               $this->db(date('Y'))->update('notif', $set, $where2);
            }
         } else {
            $status = "expired";
            $set = ['status' => 7, 'proses' => $status];
            $where2 = "id_notif = '" . $id_notif . "'";
            $this->db(date('Y'))->update('notif', $set, $where2);
            $expire += 1;
         }
      }

      echo "PENDING: " . $pending . " EXPIRED: " . $expire . " SENT: " . $sent . "\n";
      if ($data_pending <> '') {
         echo "PENDING (CabangID#NotifID): ";
         echo $data_pending . "\n";
      }
   }

   function bayar_after_cek($ref_id, $dt, $a, $month)
   {
      $msg = "";
      $response = $this->model('IAK')->post_pay($a);
      if (isset($response['data'])) {
         $d = $response['data'];

         $rc = isset($d['response_code']) ? $d['response_code'] : $a['response_code'];
         $balance = isset($d['balance']) ? $d['balance'] : $a['balance'];
         $price = isset($d['price']) ? $d['price'] : $a['price'];
         $message = isset($d['message']) ? $d['message'] : $a['message'];
         $tr_id = isset($d['tr_id']) ? $d['tr_id'] : $a['tr_id'];
         $datetime = isset($d['datetime']) ? $d['datetime'] : $a['datetime'];
         $noref = isset($d['noref']) ? $d['noref'] : $a['noref'];
         $tr_status = isset($d['status']) ? $d['status'] : 3;

         switch ($rc) {
            case '17':
               $alert = $dt['description'] . " - POSTPAID LIST - " . $message . " Rp" . number_format($price);
               $msg .= $alert . "\n";
               $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
               if (!$res['status']) {
                  $msg .= "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
               }
               exit();
               break;
            case '04':
               $tr_status = 2;
               break;
         }

         if ($tr_status == 1) {
            $where = "customer_id = '" . $d['hp'] . "' AND code = '" . $d['code'] . "'";
            $set =  ['last_bill' => $month];
            $update = $this->db(0)->update('postpaid_list', $set, $where);
            if ($update['errno'] == 0) {
               $msg .= $dt['description'] . " - POSTPAID LIST - " . $message . "\n";
            } else {
               $alert = "POSTPAID ERROR - " . $update['error'];
               $msg .= $alert . "\n";
               $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
               if (!$res['status']) {
                  $msg .= "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
               }
               return $msg;
            }
         }

         $where = "ref_id = '" . $ref_id . "'";
         $set =  ['tr_status' => $tr_status, 'datetime' => $datetime, 'noref' => $noref, 'price' => $price, 'message' => $message, 'balance' => $balance, 'tr_id' => $tr_id, 'response_code' => $rc];
         $update = $this->db(0)->update('postpaid', $set, $where);
         if ($update['errno'] == 0) {
            $msg .= $dt['description'] . " - PAY - " . $a['message'] . "\n";
         } else {
            $alert = "POSTPAID ERROR - " . $update['error'];
            $msg .= $alert . "\n";
            $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
            if (!$res['status']) {
               $msg .= "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
            }
         }
      } else {
         $alert = "UNKNOWN RESPONSE: " . json_encode($response);
         $msg .= $alert . "\n";
         $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
         if (!$res['status']) {
            $msg .= "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
         }
      }
      return $msg;
   }

   function cek_after_bayar($ref_id, $dt, $a, $month)
   {
      $msg = "";
      $response = $this->model('IAK')->post_cek($ref_id);
      if (isset($response['data'])) {
         $d = $response['data'];
         if (isset($d['status'])) {
            if ($d['status'] == $a['tr_status']) {
               return $dt['description'] . " Pending " . $a['message'] . "\n";
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
            if ($update['errno'] == 0) {
               $msg .= $dt['description'] . " - POSTPAID LIST - " . $message . "\n";
            } else {
               $alert = "POSTPAID - DB ERROR \nERROR: " . $update['error'] . " \nQUERY: " . $update['query'];
               $msg .= $alert . "\n";
               $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
               if (!$res['status']) {
                  $msg .= "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
               }
               return $msg;
            }
         }

         $where = "ref_id = '" . $ref_id . "'";
         $set =  ['tr_status' => $tr_status, 'datetime' => $datetime, 'noref' => $noref, 'price' => $price, 'message' => $message, 'balance' => $balance, 'tr_id' => $tr_id, 'response_code' => $rc];
         $update = $this->db(0)->update('postpaid', $set, $where);
         if ($update['errno'] == 0) {
            $msg .= $dt['description'] . " - POSTPAID - " . $a['message'] . "\n";
         } else {
            $alert = "POSTPAID - DB ERROR \nERROR: " . $update['error'] . " \nQUERY: " . $update['query'];
            $msg .= $alert . "\n";
            $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
            if (!$res['status']) {
               $msg .= "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
            }
         }
      } else {
         $alert = "UNKNOWN RESPONSE: " . json_encode($response);
         $msg .= $alert . "\n";
         $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
         if (!$res['status']) {
            $msg .= "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
         }
      }

      return $msg;
   }

   function pay_bill()
   {
      //CEK SEMUA TAGIHAN
      $month = $this->helper('Pre')->get_post_month();

      $data = $this->db(0)->get_where('postpaid_list', 'en = 1 ORDER BY code ASC');
      foreach ($data as $dt) {
         $code = $dt['code'];
         $customer_id = $dt['customer_id'];

         if ($dt['last_bill'] == $month) {
            echo $dt['description'] . " PAID\n";
            continue;
         }

         //cek tagihan yg udah pernah di cek atau dibayar
         $where = "customer_id = '" . $dt['customer_id'] . "' AND code = '" . $dt['code'] . "' AND (tr_status = 0 OR tr_status = 3)";
         $cek = $this->db(0)->get_where('postpaid', $where);
         if (count($cek) > 0) {
            foreach ($cek as $a) {
               $ref_id = $a['ref_id'];

               if ($a['tr_status'] == 3) {
                  //cek status karna sudah pernah dibayar
                  echo $this->cek_after_bayar($ref_id, $dt, $a, $month);
               } else {
                  //bayar karna sudah pernah di cek
                  echo $this->bayar_after_cek($ref_id, $dt, $a, $month);
               }
            }
         } else {
            //cek tagihan karna belum pernah di cek sama sekali
            $response = $this->model('IAK')->post_inquiry($code, $customer_id, $dt['id_cabang']);
            if (isset($response['data'])) {
               $d = $response['data'];

               if (isset($d['response_code'])) {
                  switch ($d['response_code']) {
                     case "01":
                     case "10":
                     case "34":
                     case "40":
                        //SUDAH DIBAYAR
                        $where = "customer_id = '" . $customer_id . "' AND code = '" . $code . "'";
                        $set =  ['last_bill' => $month];
                        $update = $this->db(0)->update('postpaid_list', $set, $where);
                        if ($update['errno'] == 0) {
                           echo $dt['description'] . " " . $d['message'] . "\n";
                        } else {
                           $alert = "POSTPAID - DB ERROR \nERROR: " . $update['error'] . " \nQUERY: " . $update['query'];;
                           echo $alert . "\n";
                           $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
                           if (!$res['status']) {
                              echo "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
                           }
                        }
                        break;
                        echo $dt['description'] . " " . $a['message'] . "\n";
                     case "00":
                     case "05":
                     case "39":
                     case "201":
                        $col = [
                           'response_code' => $d['response_code'],
                           'message' => $d['message'],
                           'tr_id' => $d['tr_id'],
                           'tr_name' => $d['tr_name'],
                           'period' => $d['period'],
                           'nominal' => $d['nominal'],
                           'admin' => $d['admin'],
                           'ref_id' => $d['ref_id'],
                           'code' => $d['code'],
                           'customer_id' => $d['hp'],
                           'price' => $d['price'],
                           'selling_price' => $d['selling_price'],
                           'description' => serialize($d['desc']),
                           'tr_status' => 0,
                           'id_cabang' => $dt['id_cabang']
                        ];
                        $do = $this->db(0)->insert("postpaid", $col);
                        if ($do['errno'] == 0) {
                           echo $dt['description'] . " - CHECK - " . $d['message'] . "\n";

                           //bayar karna sudah pernah di cek
                           $where = "ref_id = '" . $d['ref_id'] . "'";
                           $a = $this->db(0)->get_where_row('postpaid', $where);
                           echo $this->bayar_after_cek($d['ref_id'], $dt, $a, $month);
                        } else {
                           $alert = "POSTPAID - DB ERROR - " . $do['error'] . "\n";
                           echo $alert . "\n";
                           $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
                           if (!$res['status']) {
                              echo "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
                           }
                        }
                        break;
                     case "106":
                        //PRIVIDER GANGGUAN
                        if (isset($d['message'])) {
                           $alert = $dt['description'] . " - " . $d['message'];
                        } else {
                           $alert = "UNKNOWN RESPONSE CODE: " . $d['response_code'];
                        }
                        echo $alert . "\n";
                        $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
                        if (!$res['status']) {
                           echo "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
                        }
                        break;
                     default:
                        if (isset($d['message'])) {
                           $alert = $dt['description'] . " - RESPONSE CODE: " . $d['response_code'] . " - " . $d['message'];
                        } else {
                           $alert = "UNKNOWN RESPONSE CODE: " . $d['response_code'];
                        }
                        echo $alert . "\n";
                        $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
                        if (!$res['status']) {
                           echo "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
                        }
                        break;
                  }
               } else {
                  $alert = "UNKNOWN RESPONSE: " . json_encode($d);
                  echo $alert . "\n";
                  $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
                  if (!$res['status']) {
                     echo "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
                  }
               }
            } else {
               $alert = "UNKNOWN RESPONSE: " . json_encode($response);
               echo $alert . "\n";
               $res = $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $alert);
               if (!$res['status']) {
                  echo "WHATSAPP ERROR, " . $res['data']['status'] . "\n";
               }
            }
         }
      }
   }
}
