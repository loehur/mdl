<?php

namespace App\Controllers\Cron;

use App\Core\Controller;

/**
 * CleanKas Controller
 * Menghapus record kas QRIS yang expired (belum dibayar > 1 jam)
 * Kondisi: note QRIS, metode_mutasi = 2, status_mutasi <> 3, id_client = 0, insertTime > 1 jam
 */
class CleanKas extends Controller
{
    /**
     * Menghapus kas QRIS yang expired
     */
    public function index()
    {
        $db = $this->db(1); // kas berada di db(1) - mdl_laundry

        if (!$db) {
            header('Content-Type: text/plain');
            echo "ERROR: Database connection failed\n";
            return;
        }

        // Kondisi: note QRIS, metode_mutasi = 2, status_mutasi <> 3, id_client = 0, insertTime > 1 jam
        $where = "UPPER(note) = 'QRIS' AND metode_mutasi = 2 AND status_mutasi <> 3 AND payment_trx_id IS NULL AND id_user = 0 AND id_client <> 0 AND insertTime < DATE_SUB(NOW(), INTERVAL 1 HOUR)";

        try {
            // Hitung dulu sebelum hapus
            $countResult = $db->query("SELECT COUNT(*) as cnt FROM kas WHERE $where")->result_array();
            $count = (int) ($countResult[0]['cnt'] ?? 0);

            // Hapus
            $db->query("DELETE FROM kas WHERE $where");
            $output = "OK: CleanKas deleted $count record(s)\n";
        } catch (\Exception $e) {
            $output = "ERROR: " . $e->getMessage() . "\n";
        }

        header('Content-Type: text/plain');
        echo $output;
    }
}
