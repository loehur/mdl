<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;

class Reminder extends Controller
{
   public function update()
   {
      $id = $_POST['id'] ?? null;
      
      if (!$id || !is_numeric($id)) {
         echo "Invalid ID";
         return;
      }
      
      $db = DB::getInstance(0);
      
      // Get reminder data
      $result = $db->get_where('reminder', ['id' => (int)$id], 1);
      $d = $result->row_array();
      
      if (!$d) {
         echo "Reminder not found";
         return;
      }
      
      $cycle = $d['cycle'];

      $t1 = date_create($d['next_date']);
      $t2 = date_create(date("Y-m-d"));
      $diff = date_diff($t2, $t1);
      $selisih_hari = $diff->format('%R%a') + 0;

      $rentang = $d['range'];

      if ($selisih_hari <= $rentang) {
         $next_date = date("Y-m-d", strtotime($d['next_date'] . " +" . $cycle . " " . $d['cycle_type']));
         $up = $db->update('reminder', ['next_date' => $next_date], ['id' => (int)$id]);
         
         if ($up) {
            echo 0;
         } else {
            echo "Error Updating, Hubungi Admin";
         }
      } else {
         echo "Reminder belum dalam rentang update";
      }
   }

   public function index()
   {
      $this->success(['message' => 'Reminder API'], 'Reminder endpoint');
   }
}
