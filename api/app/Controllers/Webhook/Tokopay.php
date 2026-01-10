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
        $secret = '4aea0ede516df65d88ccb773a443c61b3b3702fe1b9647deb9293cac07fd72bf'; // Ganti dengan Tokopay Secret Key Anda

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
        // Format BARU: ref_finance_timestamp (contoh: 1234567890_1704873600)
        // Format LAMA: ref_finance saja (contoh: 1234567890)
        $tokopay_trx_id = $reff_id;

        // Extract ref_finance dari trx_id jika format baru (mengandung underscore)
        // ref_finance adalah bagian sebelum underscore terakhir
        $ref_finance_extracted = $reff_id;
        if (strpos($reff_id, '_') !== false) {
            // Format baru: ambil bagian sebelum underscore terakhir
            $parts = explode('_', $reff_id);
            array_pop($parts); // Hapus timestamp
            $ref_finance_extracted = implode('_', $parts);
        }

        // Update wh_tokopay untuk SEMUA status (pending, success, expired, dll)
        $db_instance = $this->db(0);
        if ($db_instance) {
            $update_wh = $db_instance->update("wh_tokopay", ["state" => $status], ["trx_id" => $tokopay_trx_id]);
            if (!$update_wh) {
                $update_wh = $db_instance->update("wh_tokopay", ["state" => $status], ["ref_id" => $ref_finance_extracted]);
                if (!$update_wh) {
                    \Log::write("Err: WH Update Failed trx=$tokopay_trx_id ref=$ref_finance_extracted status=$status", 'webhook', 'Tokopay');
                } else {
                    \Log::write("OK: WH Updated by ref_id=$ref_finance_extracted status=$status", 'webhook', 'Tokopay');
                }
            } else {
                \Log::write("OK: WH Updated by trx_id=$tokopay_trx_id status=$status", 'webhook', 'Tokopay');
            }
        }

        $statusLower = strtolower($status);
        if ($statusLower == 'success' || $statusLower == 'completed' || $statusLower == 'expired') {
            // Processing for success/completed/expired

            try {
                if (!$db_instance) {
                    \Log::write("Err: DB 0", 'webhook', 'Tokopay');
                    return;
                }

                // Lookup by trx_id first (new format)
                $cek_target_query = $db_instance->get_where("wh_tokopay", ["trx_id" => $tokopay_trx_id]);
                $cek_target = $cek_target_query ? $cek_target_query->row() : null;

                // Fallback: lookup by ref_id using extracted ref_finance
                if (!$cek_target) {
                    $cek_target_query = $db_instance->get_where("wh_tokopay", ["ref_id" => $ref_finance_extracted]);
                    $cek_target = $cek_target_query ? $cek_target_query->row() : null;
                }

                if (!$cek_target) {
                    \Log::write("Err: WH Null trx=$tokopay_trx_id ref=$ref_finance_extracted", 'webhook', 'Tokopay');
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
                                            'kasir_id' => (string) $kasirId,
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

                                        // Log success/fail of this push
                                        if ($httpCode !== 200) {
                                            \Log::write("Err: QRS Push $httpCode kasir=$kasirId", 'webhook', 'Tokopay');
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
