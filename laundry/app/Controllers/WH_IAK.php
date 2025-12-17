<?php

class WH_IAK extends Controller
{
   public function update()
   {
      header('Content-Type: application/json; charset=utf-8');
      $json = file_get_contents('php://input');
      $response = json_decode($json, true);

      if (isset($response['data'])) {
         $d = $response['data'];

         $ref_id = $d['ref_id'];
         $a = $this->db(0)->get_where_row("prepaid", "ref_id = '" . $ref_id . "'");

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
         echo "DATA RESPONSE NOT FOUND";
      }
   }
}
