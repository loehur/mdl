<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

class WA_Local extends Controller
{
   public function update()
   {
      header('Content-Type: application/json; charset=utf-8');
      $json = file_get_contents('php://input');
      $data = json_decode($json, true);

      // DEBUG: Log incoming webhook data
      \Log::write("[WA_Local::update] Webhook received - Raw JSON: " . $json, 'webhook', 'WA_Local');
      \Log::write("[WA_Local::update] Decoded data: " . json_encode($data), 'webhook', 'WA_Local');

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

      $id = $data['key']['id'] ?? null;
      \Log::write("[WA_Local::update] Message ID: " . ($id ?? 'NULL'), 'webhook', 'WA_Local');

      if (isset($data['update']['status']) && count($data['update']) > 0) {
         $res_state = $data['update']['status'];
         $state = $state_arr[$res_state];
         $status = $proses_arr[$res_state];
         
         \Log::write("[WA_Local::update] Status update - res_state: {$res_state}, state: {$state}, status: {$status}", 'webhook', 'WA_Local');
      } else {
         \Log::write("[WA_Local::update] No status update - Data: " . json_encode($data['update'] ?? []), 'webhook', 'WA_Local');
         
         // Send response for no status update
         $response = [
            'status' => false,
            'message' => 'No status update found'
         ];
         \Log::write("[WA_Local::update] Response: " . json_encode($response), 'webhook', 'WA_Local');
         echo json_encode($response);
         exit();
      }

      $set = ['proses' => $status, 'state' => $state, 'status' => 2];

      \Log::write("[WA_Local::update] Updating notif table - id: {$id}, SET: " . json_encode($set), 'webhook', 'WA_Local');

      // Use raw query since we need OR condition (update() method only supports AND)
      $sql = "UPDATE notif SET proses = ?, state = ?, status = ? WHERE id_api = ? OR id_api_2 = ?";
      $params = [$status, $state, 2, $id, $id];

      try {
         $this->db(1)->query($sql, $params);
         
         // Get affected rows
         $affected = $this->db(1)->conn()->affected_rows;
         
         \Log::write("[WA_Local::update] SUCCESS - Rows affected: " . $affected, 'webhook', 'WA_Local');
         
         // Send success response
         $response = [
            'status' => true,
            'message' => 'Update successful',
            'affected_rows' => $affected
         ];
         \Log::write("[WA_Local::update] Response: " . json_encode($response), 'webhook', 'WA_Local');
         echo json_encode($response);
         
      } catch (\Exception $e) {
         \Log::write("[WA_Local::update] ERROR: " . $e->getMessage(), 'webhook', 'WA_Local');
         
         // Send error response
         $response = [
            'status' => false,
            'message' => 'Update failed: ' . $e->getMessage()
         ];
         \Log::write("[WA_Local::update] Response: " . json_encode($response), 'webhook', 'WA_Local');
         echo json_encode($response);
      }
   }

}
