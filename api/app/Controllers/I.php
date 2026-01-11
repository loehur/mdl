<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * I Controller
 * Public interface untuk reminder dan invoice links
 */
class I extends Controller
{
    /**
     * Reminder view - /I/r/{id}
     */
    public function r($id)
    {
        if (!is_numeric($id)) {
            echo "Invalid ID";
            exit();
        }

        $where = ['id' => $id];
        $result = $this->db(0)->get_where('reminder', $where);
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
     * Render a view file
     */
    private function renderView($view, $data = [])
    {
        extract($data);
        include __DIR__ . '/../Views/' . $view . '.php';
    }
}
