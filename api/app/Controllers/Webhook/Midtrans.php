<?php

class Midtrans extends Controller
{
    public function update()
    {
        // ==============================
        // CONFIGURATION
        // ==============================
        $server_key = 'YOUR_MIDTRANS_SERVER_KEY'; // Ganti dengan Server Key Midtrans Anda

        header('Content-Type: application/json; charset=utf-8');

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Logging
        Log::write("Req: " . $json, 'webhook', 'Midtrans');

        if (!$data) {
            echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
            return;
        }

        $order_id = isset($data['order_id']) ? $data['order_id'] : '';
        $status_code = isset($data['status_code']) ? $data['status_code'] : '';
        $gross_amount = isset($data['gross_amount']) ? $data['gross_amount'] : '';
        $signature_provided = isset($data['signature_key']) ? $data['signature_key'] : '';

        if (empty($order_id) || empty($signature_provided)) {
            echo json_encode(['status' => false, 'message' => 'Missing parameter']);
            Log::write("Err: Param", 'webhook', 'Midtrans');
            return;
        }

        // Validate Signature: hash("sha512", order_id + status_code + gross_amount + ServerKey)
        $signature_generated = hash("sha512", $order_id . $status_code . $gross_amount . $server_key);

        if ($signature_provided !== $signature_generated) {
            echo json_encode(['status' => false, 'message' => 'Invalid Signature']);
            Log::write("Err: Sign", 'webhook', 'Midtrans');
            return;
        }

        // Process Transaction
        $transaction_status = isset($data['transaction_status']) ? $data['transaction_status'] : '';
        $fraud_status = isset($data['fraud_status']) ? $data['fraud_status'] : '';

        // Map Midtrans status to our status
        $status = $this->mapMidtransStatus($transaction_status, $fraud_status);

        if (isset($data['transaction_status'])) {
            $db_main = $this->db(0);
            if (!$db_main) {
                Log::write("Err: DB 2000", 'webhook', 'Midtrans');
                return;
            }

            $up_wh = $db_main->update("wh_midtrans", ["state" => $status], ["ref_id" => $order_id]);
            if (!$up_wh) {
                Log::write("Err: Upd WH", 'webhook', 'Midtrans');
                return;
            }
        }

        if ($status == 'Success' || $status == 'Completed') {
            Log::write("Ref: $order_id", 'webhook', 'Midtrans');

            // Debugging DB connection and query
            try {
                $db_instance = $this->db(0);
                if (!$db_instance) {
                    Log::write("Err: DB 2000 main", 'webhook', 'Midtrans');
                    return;
                }
                // Log::write("DB 2000 OK", 'midtrans');

                $cek_target_query = $db_instance->get_where("wh_midtrans", ["ref_id" => $order_id]);
                if (!$cek_target_query) {
                    Log::write("Err: Trg Null", 'webhook', 'Midtrans');
                    return;
                }

                $cek_target = $cek_target_query->row();
            } catch (Exception $e) {
                Log::write("Err: DB Lookup " . $e->getMessage(), 'webhook', 'Midtrans');
                return;
            }

            if ($cek_target) {
                Log::write("Fnd: B: $cek_target->book, T: $cek_target->target", 'webhook', 'Midtrans');

                $book = $cek_target->book;
                $target = $cek_target->target;

                if ($target == "kas_laundry") {
                    // FIX: use db(0) directly instead of year iteration
                    Log::write("Upd Kas: db(0)", 'webhook', 'Midtrans');

                    try {
                        $db_update_instance = $this->db(0);
                        if (!$db_update_instance) {
                            Log::write("Err: DB 0", 'webhook', 'Midtrans');
                        } else {
                            $update = $db_update_instance->update("kas", ["status_mutasi" => 3], ["ref_finance" => $order_id]);

                            if (!$update) {
                                Log::write("Err: Upd Kas $order_id", 'webhook', 'Midtrans');
                            } else {
                                Log::write("OK: Upd Kas $order_id", 'webhook', 'Midtrans');
                            }
                        }
                    } catch (Exception $e) {
                        Log::write("Exc: Upd " . $e->getMessage(), 'webhook', 'Midtrans');
                    }
                } else {
                    Log::write("Err: Trg !kas_laundry", 'webhook', 'Midtrans');
                }
            } else {
                Log::write("Err: Trg Not Fnd", 'webhook', 'Midtrans');
            }
        } else {
            Log::write("Err: Sts $status", 'webhook', 'Midtrans');
        }

        Log::write("End: $status", 'webhook', 'Midtrans');
        echo json_encode(['status_code' => '200', 'status_message' => 'Success']);
    }

    private function mapMidtransStatus($transaction_status, $fraud_status)
    {
        // Map Midtrans transaction status to our internal status
        if ($transaction_status == 'capture') {
            if ($fraud_status == 'accept') {
                return 'Success';
            } else if ($fraud_status == 'challenge') {
                return 'Pending';
            } else {
                return 'Failed';
            }
        } else if ($transaction_status == 'settlement') {
            return 'Completed';
        } else if ($transaction_status == 'pending') {
            return 'Pending';
        } else if ($transaction_status == 'deny' || $transaction_status == 'expire' || $transaction_status == 'cancel') {
            return 'Failed';
        } else {
            return 'Unknown';
        }
    }
}
