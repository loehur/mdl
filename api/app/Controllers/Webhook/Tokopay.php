<?php
namespace App\Controllers\Webhook;

use App\Core\Controller;

class Tokopay extends Controller
{
    public function index()
    {
        // ==============================
        // CONFIGURATION
        // ==============================
        $merchant_id = 'M240926BMTGB612'; // Ganti dengan Tokopay Merchant ID Anda
        $secret      = '4aea0ede516df65d88ccb773a443c61b3b3702fe1b9647deb9293cac07fd72bf'; // Ganti dengan Tokopay Secret Key Anda

        header('Content-Type: application/json; charset=utf-8');

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Removed verbose request logging

        if (!$data) {
            echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
            return;
        }

        $reff_id = isset($data['reff_id']) ? $data['reff_id'] : '';
        $signature_provided = isset($data['signature']) ? $data['signature'] : '';

        if (empty($reff_id) || empty($signature_provided)) {
            echo json_encode(['status' => false, 'message' => 'Missing parameter']);
            \Log::write("Err: Param", 'webhook', 'Tokopay');
            return;
        }

        // Validate Signature: md5(merchant_id:secret:reff_id)
        $signature_generated = md5($merchant_id . ':' . $secret . ':' . $reff_id);

        if ($signature_provided !== $signature_generated) {
            echo json_encode(['status' => false, 'message' => 'Invalid Signature']);
            \Log::write("Err: Sign", 'webhook', 'Tokopay');
            return;
        }

        // Process Transaction
        $status = isset($data['status']) ? $data['status'] : '';

        if (isset($data['status'])) {
            $db_main = $this->db(0);
            if (!$db_main) {
                \Log::write("Err: DB 0", 'webhook', 'Tokopay');
                return;
            }

            $up_wh = $db_main->update("wh_tokopay", ["state" => $status], ["ref_id" => $reff_id]);
            if (!$up_wh) {
                \Log::write("Err: Upd WH", 'webhook', 'Tokopay');
                return;
            }
        }

        if ($status == 'Success' || $status == 'Completed') {
            // Processing success (no verbose log)

            // Debugging DB connection and query
            try {
                $db_instance = $this->db(0);
                if (!$db_instance) {
                    \Log::write("Err: DB 0", 'webhook', 'Tokopay');
                    return;
                }
                // DB instance obtained (no log)

                $update_wh = $db_instance->update("wh_tokopay", ["state" => $status], ["ref_id" => $reff_id]);
                if (!$update_wh) {
                    \Log::write("Err: Upd WH $reff_id", 'webhook', 'Tokopay');
                }
                // Success - no log

                $cek_target_query = $db_instance->get_where("wh_tokopay", ["ref_id" => $reff_id]);
                if (!$cek_target_query) {
                    \Log::write("Err: WH Null", 'webhook', 'Tokopay');
                    return;
                }

                $cek_target = $cek_target_query->row();
            } catch (\Exception $e) {
                \Log::write("Exc: DB " . $e->getMessage(), 'webhook', 'Tokopay');
                return;
            }

            if ($cek_target) {
            // Target found (no log)

                $book = $cek_target->book;
                $target = $cek_target->target;

                if ($target == "kas_laundry") {
                    // FIX: use db(0) directly instead of year iteration
                    // Update kas (no verbose log)

                    try {
                        $db_update_instance = $this->db(0);
                        if (!$db_update_instance) {
                            \Log::write("Err: DB 0", 'webhook', 'Tokopay');
                        } else {
                            $update = $db_update_instance->update("kas", ["status_mutasi" => 3], ["ref_finance" => $reff_id]);

                            if (!$update) {
                                \Log::write("Err: Upd Kas $reff_id", 'webhook', 'Tokopay');
                            }
                            // Success - no log
                        }
                    } catch (\Exception $e) {
                        \Log::write("Exc: Upd " . $e->getMessage(), 'webhook', 'Tokopay');
                    }
                } else {
                    \Log::write("Err: Trg !kas_laundry", 'webhook', 'Tokopay');
                }
            } else {
                \Log::write("Err: Trg Not Fnd", 'webhook', 'Tokopay');
            }
        } else {
            \Log::write("Err: Sts $status", 'webhook', 'Tokopay');
        }

        // Webhook processed (no log)
        echo json_encode(['status' => true, 'message' => 'Success']);
    }
}
