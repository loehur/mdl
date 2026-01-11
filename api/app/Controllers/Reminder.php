<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;

class Reminder extends Controller
{
   /**
    * View reminder page - /Reminder/r/{id}
    */
   public function r($id)
   {
      if (!is_numeric($id)) {
         echo "Invalid ID";
         exit();
      }

      $db = DB::getInstance(0);
      $where = ['id' => (int)$id];
      $result = $db->get_where('reminder', $where, 1);
      $data = $result->row_array();

      if (!$data) {
         echo "Reminder tidak ditemukan";
         exit();
      }

      $t1 = strtotime($data['next_date']);
      $t2 = strtotime(date("Y-m-d H:i:s"));
      $diff = $t1 - $t2;
      $dates = floor(($diff / (60 * 60)) / 24);

      if ($dates > 0) {
         $data['class'] = 'success';
         $text_count = $dates . " Hari Lagi";
      } elseif ($dates < 0) {
         $data['class'] = 'danger';
         $text_count = "Terlewat " . ($dates * -1) . " Hari";
      } else {
         $data['class'] = 'danger';
         $text_count = "Hari Ini";
      }
      $data['dates'] = $dates;
      $data['warning'] = $text_count;

      // Render the view
      $this->renderView('reminder', $data);
   }

   /**
    * Update reminder next_date - POST /Reminder/update
    */
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

   /**
    * Render a view file
    */
   private function renderView($view, $data = [])
   {
      extract($data);
      include __DIR__ . '/../Views/' . $view . '.php';
   }
}
