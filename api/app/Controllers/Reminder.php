<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;

class Reminder extends Controller
{
   public function cek()
   {
      $data = DB::getInstance(0)->query("SELECT * FROM reminder")->result_array();
      
      // Group reminders by phone number
      $grouped = [];
      
      foreach ($data as $d) {
         $t1 = date_create($d['next_date']);
         $t2 = date_create(date("Y-m-d"));
         $diff = date_diff($t2, $t1);
         $selisih_hari = $diff->format('%R%a') + 0;

         $rentang = $d['range'];

         if ($selisih_hari <= $rentang) {
            if ($selisih_hari > 0) {
               $text_count = $selisih_hari . " Hari Lagi";
            } elseif ($selisih_hari < 0) {
               $text_count = "Terlewat " . $selisih_hari * -1 . " Hari";
            } else {
               $text_count = "Hari Ini";
            }

            $note = "";
            if ($d['note'] <> "") {
               $note = "\n" . $d['note'];
            }

            $ops_link = "https://api.nalju.com/I/r/" . $d['id'];
            $hp = $d['notif_number'];
            $text = "*" . $d['name'] . "* " . $note . " \n" . $text_count . " \n" . $ops_link;
            echo $d['name'] . " " . $text_count . " \n";

            // Group by phone number
            if (!isset($grouped[$hp])) {
               $grouped[$hp] = [];
            }
            $grouped[$hp][] = $text;
         }
      }
      
      // Send grouped messages
      foreach ($grouped as $hp => $messages) {
         $combined_text = implode("\n\n", $messages);
         // TODO: Implement notification sending
         // $res = $this->helper('Notif')->send_wa($hp, $combined_text);
      }
   }

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
