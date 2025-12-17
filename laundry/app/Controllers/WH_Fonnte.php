<?php

class WH_Fonnte extends Controller
{
   public function update()
   {
      header('Content-Type: application/json; charset=utf-8');
      $json = file_get_contents('php://input');
      $data = json_decode($json, true);

      $id = $data['id'];
      $stateid = $data['stateid'];
      $status = $data['status'];
      $state = $data['state'];

      //update status and state
      if (isset($id) && isset($stateid)) {
         $id = $data['id'];
         $stateid = $data['stateid'];
         $status = $data['status'];
         $state = $data['state'];
         $set = ['proses' => $status, 'state' => $state, 'id_state' => $stateid, 'status' => 2];
         $where = "id_api = '" . $id . "' OR id_api_2 = '" . $id . "'";
      } else if (isset($id) && !isset($stateid)) {
         $id = $data['id'];
         $status = $data['status'];
         $set = ['proses' => $status, 'status' => 2];
         $where = "id_api = '" . $id . "' OR id_api_2 = '" . $id . "'";
      } else {
         $stateid = $data['stateid'];
         $state = $data['state'];
         $set = ['state' => $state, 'status' => 2];
         $where = "id_state = '" . $stateid . "'";
      }

      $y = date('Y') - 1;
      while ($y <= (date('Y'))) {
         $do = $this->db($y)->update('notif', $set, $where);
         if ($do['errno'] <> 0) {
            $this->model('Log')->write($do['error']);
         }
         $y++;
      }
   }


}
