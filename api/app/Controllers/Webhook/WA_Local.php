<?php

class WA_Local extends Controller
{
   public function update()
   {
      header('Content-Type: application/json; charset=utf-8');
      $json = file_get_contents('php://input');
      $data = json_decode($json, true);

      $state_arr = [
         0 => "error",
         1 => "pending",
         2 => "server",
         3 => "delivered",
         4 => "read",
         5 => "played",
      ];

      $proses_arr = [
         0 => "failed",
         1 => "processing",
         2 => "sent",
         3 => "sent",
         4 => "sent",
         5 => "sent",
      ];

      $id = $data['key']['id'];
      if (isset($data['update']['status']) && count($data['update']) > 0) {
         $res_state = $data['update']['status'];
         $state = $state_arr[$res_state];
         $status = $proses_arr[$res_state];
      } else {
         exit();
      }

      $set = ['proses' => $status, 'state' => $state, 'status' => 2];
      $where = "id_api = '" . $id . "' OR id_api_2 = '" . $id . "'";

      // FIX: use db(0) directly instead of year iteration
      $do = $this->db(0)->update('notif', $set, $where);
      if ($do['errno'] <> 0) {
         $this->model('Log')->write($do['error']);
      }
   }


}
