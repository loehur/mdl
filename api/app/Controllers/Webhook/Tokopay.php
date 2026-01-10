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
        
        // reff_id dari Tokopay adalah trx_id (unique order_id) yang kita kirim
        // Format: ref_finance_timestamp (contoh: 1234567890_1704873600)
        $tokopay_trx_id = $reff_id;

        if (isset($data['status'])) {
            $db_main = $this->db(0);
            if (!$db_main) {
                \Log::write("Err: DB 0", 'webhook', 'Tokopay');
                return;
            }

            // Update wh_tokopay - try by trx_id first, then fallback to ref_id
            $up_wh = $db_main->update("wh_tokopay", ["state" => $status], ["trx_id" => $tokopay_trx_id]);
            if (!$up_wh) {
                // Fallback for old format where trx_id = ref_id
                $up_wh = $db_main->update("wh_tokopay", ["state" => $status], ["ref_id" => $tokopay_trx_id]);
            }
        }

        $statusLower = strtolower($status);
        if ($statusLower == 'success' || $statusLower == 'completed' || $statusLower == 'expired') {
            // Processing success (no verbose log)

            // Debugging DB connection and query
            try {
                $db_instance = $this->db(0);
                if (!$db_instance) {
                    \Log::write("Err: DB 0", 'webhook', 'Tokopay');
                    return;
                }
                // DB instance obtained (no log)

                // Try update by trx_id first (new format: ref_finance_timestamp)
                $update_wh = $db_instance->update("wh_tokopay", ["state" => $status], ["trx_id" => $tokopay_trx_id]);
                if (!$update_wh) {
                    // Fallback: try by ref_id (old format: trx_id = ref_id = ref_finance)
                    $db_instance->update("wh_tokopay", ["state" => $status], ["ref_id" => $tokopay_trx_id]);
                }

                // Lookup by trx_id first (new format)
                $cek_target_query = $db_instance->get_where("wh_tokopay", ["trx_id" => $tokopay_trx_id]);
                $cek_target = $cek_target_query ? $cek_target_query->row() : null;
                
                // Fallback: lookup by ref_id (old format where trx_id = ref_id)
                if (!$cek_target) {
                    $cek_target_query = $db_instance->get_where("wh_tokopay", ["ref_id" => $tokopay_trx_id]);
                    $cek_target = $cek_target_query ? $cek_target_query->row() : null;
                }
                
                if (!$cek_target) {
                    \Log::write("Err: WH Null trx/ref=$tokopay_trx_id", 'webhook', 'Tokopay');
                    return;
                }
            } catch (\Exception $e) {
                \Log::write("Exc: DB " . $e->getMessage(), 'webhook', 'Tokopay');
                return;
            }

            if ($cek_target && ($statusLower == 'success' || $statusLower == 'completed')) {
            // Target found (no log)

                $book = $cek_target->book;
                $target = $cek_target->target;
                // Ambil ref_id yang merupakan ref_finance asli (tanpa timestamp)
                $ref_finance = $cek_target->ref_id;

                if ($target == "kas_laundry") {
                    // FIX: use db(0) directly instead of year iteration
                    // Update kas (no verbose log)

                    try {
                        // db kas itu db 1
                        $db_update_instance = $this->db(1);
                        if (!$db_update_instance) {
                            \Log::write("Err: DB 1", 'webhook', 'Tokopay');
                        } else {
                            // Update kas menggunakan ref_finance asli (bukan trx_id dari Tokopay)
                            $update = $db_update_instance->update("kas", ["status_mutasi" => 3], ["ref_finance" => $ref_finance]);

                            if (!$update) {
                                \Log::write("Err: Upd Kas ref=$ref_finance", 'webhook', 'Tokopay');
                            } else {
                                // Send Webhook to QR Server (Node.js) to notify frontend
                                try {
                                    // 1. Get QR String from wh_tokopay (already fetched in $cek_target)
                                    $qrString = isset($cek_target->qr_string) ? $cek_target->qr_string : '';

                                    // 2. Get Kasir ID (id_cabang) from kas table - gunakan ref_finance asli
                                    $kasData = $db_update_instance->query("SELECT id_cabang FROM kas WHERE ref_finance = '$ref_finance'")->row();
                                    
                                    if ($kasData && !empty($qrString)) {
                                        $kasirId = $kasData->id_cabang; // Ensure this maps to your Node server Kasir IDs (3, 4, etc)
                                        
                                        $url = 'https://qrs.nalju.com/payment-success';
                                        $postData = [
                                            'kasir_id' => (string)$kasirId,
                                            'qr_string' => $qrString,
                                            'status' => true
                                        ];
                                        
                                        $ch = curl_init($url);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_POST, true);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                                        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Don't hang PHP
                                        
                                        $response = curl_exec($ch);
                                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                        curl_close($ch);
                                        
                                        // Optional: Log success/fail of this push
                                        if ($httpCode !== 200) {
                                            \Log::write("Err: QRS Push $httpCode", 'webhook', 'Tokopay');
                                        }
                                    }
                                } catch (\Exception $ex) {
                                    \Log::write("Err: QRS Exc " . $ex->getMessage(), 'webhook', 'Tokopay');
                                }
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
