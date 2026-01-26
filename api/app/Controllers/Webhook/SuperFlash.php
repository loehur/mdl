<?php
namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * SuperFlash QRIS Webhook Handler
 * Handles payment notifications from SuperFlash (Flash Mobile) QRIS service
 */
class SuperFlash extends Controller
{
    public function index()
    {
        // ==============================
        // CONFIGURATION
        // ==============================
        // Get config from Env.php
        $clientKey = defined('\Env::SUPERFLASH_CLIENT_KEY') ? \Env::SUPERFLASH_CLIENT_KEY : null;
        $serverKey = defined('\Env::SUPERFLASH_SERVER_KEY') ? \Env::SUPERFLASH_SERVER_KEY : null;

        header('Content-Type: application/json; charset=utf-8');

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data) {
            echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
            \Log::write("Err: Invalid JSON", 'webhook', 'SuperFlash');
            return;
        }

        // SuperFlash QRIS callback format (from docs):
        // - external_id: Merchant transaction ID (our order_id)
        // - transaction_id: SuperFlash transaction ID (FM-xxxx)
        // - status: PENDING, SUCCESS, FAILED
        // - signature: (to be verified based on SuperFlash docs)
        $external_id = isset($data['external_id']) ? $data['external_id'] : (isset($data['data']['external_id']) ? $data['data']['external_id'] : '');
        $transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : (isset($data['data']['transaction_id']) ? $data['data']['transaction_id'] : '');
        $status = isset($data['status']) ? $data['status'] : (isset($data['data']['status']) ? $data['data']['status'] : '');
        $signature_provided = isset($data['signature']) ? $data['signature'] : (isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '');

        // Use external_id as primary identifier (same as our order_id)
        $reff_id = $external_id;

        if (empty($reff_id)) {
            echo json_encode(['status' => false, 'message' => 'Missing external_id']);
            \Log::write("Err: Missing external_id", 'webhook', 'SuperFlash');
            return;
        }

        // TODO: Verify Signature based on SuperFlash documentation
        // Placeholder: Signature verification will be implemented based on actual SuperFlash webhook format
        // Possible methods: HMAC SHA256, RSA signature, or header-based signature
        $signature_valid = true; // Placeholder - update based on actual SuperFlash signature method
        if (!empty($signature_provided) && $clientKey && $serverKey) {
            // TODO: Implement actual signature verification
            // Example (if HMAC): hash_hmac('sha256', $json, $serverKey)
            // Example (if RSA): openssl_verify($json, base64_decode($signature_provided), $publicKey, OPENSSL_ALGO_SHA256)
            $signature_valid = true; // Temporary: accept all for now
        }

        if (!$signature_valid && !empty($signature_provided)) {
            echo json_encode(['status' => false, 'message' => 'Invalid Signature']);
            \Log::write("Err: Invalid Signature external_id=$external_id", 'webhook', 'SuperFlash');
            return;
        }

        // Handle based on prefix (same as Tokopay)
        $parts = explode('_', $reff_id);
        if ($parts[0] === 'TEST') {
             // TEST: hanya log saja, tidak perlu proses
             // Log raw JSON dan semua data yang diterima
             $raw_json = file_get_contents('php://input');
             $log_data = [
                 'raw_json' => $raw_json,
                 'decoded_data' => $data,
                 'external_id' => $external_id,
                 'transaction_id' => $transaction_id,
                 'status' => $status,
                 'signature_provided' => $signature_provided,
                 'signature_valid' => $signature_valid,
                 'timestamp' => date('Y-m-d H:i:s'),
                 'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                 'headers' => [
                     'X-Signature' => $_SERVER['HTTP_X_SIGNATURE'] ?? 'none',
                     'X-Timestamp' => $_SERVER['HTTP_X_TIMESTAMP'] ?? 'none',
                     'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'none'
                 ]
             ];
             \Log::write("TEST: SuperFlash Webhook received - " . json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'webhook', 'SuperFlash');
             echo json_encode(['status' => true, 'message' => 'TEST webhook logged', 'logged' => true]);
             return;
        } else if ($parts[0] === 'SALONSUB') {
             $this->handleSalonSubscription($reff_id, $status, $transaction_id);
             echo json_encode(['status' => true, 'message' => 'Processed SALONSUB']);
             return;
        } else if ($parts[0] === 'RESTOKAS') {
             $this->handleRestoKas($reff_id, $status, $transaction_id);
             echo json_encode(['status' => true, 'message' => 'Processed RESTOKAS']);
             return;
        } else {
             $this->handleKasLaundry($reff_id, $status, $transaction_id);
        }
    }
    
    private function handleSalonSubscription($payment_ref, $status_superflash, $transaction_id)
    {
        $db_index = 4; // mdl_salon
        $db = $this->db($db_index);

        if (!$db) {
             \Log::write("Err: DB Salon Not Found", 'webhook', 'SuperFlash');
             return;
        }

        // Get payment record
        $payment = $db->get_where('subscription_payments', ['payment_ref' => $payment_ref])->row_array();

        if (!$payment) {
             \Log::write("Err: Sub Pmt Not Found ref=$payment_ref", 'webhook', 'SuperFlash');
             return;
        }
        
        // Normalize status: SuperFlash uses PENDING, SUCCESS, FAILED
        $statusLower = strtolower($status_superflash);
        $isPaid = ($statusLower === 'success');
        $isExpired = ($statusLower === 'expired' || $statusLower === 'failed');
        $isFailed = ($statusLower === 'failed');

        if ($isPaid) {
            if ($payment['payment_status'] !== 'paid' && $payment['payment_status'] !== 'success') {
                // Update payment status - Use success for ENUM consistency
                $db->update('subscription_payments', [
                    'payment_status' => 'success'
                ], ['payment_ref' => $payment_ref]);

                $salon_id = $payment['salon_id'];
                
                // Update subscription tables
                $db->update('subscriptions', [
                    'status' => 'active',
                    'end_date' => $payment['period_end'],
                    'last_payment_date' => date('Y-m-d'),
                    'last_payment_amount' => $payment['amount'],
                    'payment_ref' => $payment_ref
                ], ['salon_id' => $salon_id]);
                
                \Log::write("OK: Salon Sub PAID ref=$payment_ref salon=$salon_id trx=$transaction_id", 'webhook', 'SuperFlash');
            }
        } elseif ($isExpired) {
            $db->update('subscription_payments', [
                'payment_status' => 'failed' 
            ], ['payment_ref' => $payment_ref]);
            \Log::write("OK: Salon Sub EXPIRED ref=$payment_ref trx=$transaction_id", 'webhook', 'SuperFlash');
        } elseif ($isFailed) {
            $db->update('subscription_payments', [
                'payment_status' => 'failed'
            ], ['payment_ref' => $payment_ref]);
             \Log::write("OK: Salon Sub FAILED ref=$payment_ref trx=$transaction_id", 'webhook', 'SuperFlash');
        }
    }

    private function handleKasLaundry($reff_id, $status_superflash, $transaction_id)
    {
        // external_id dari SuperFlash adalah order_id yang kita kirim
        // Format BARU: ref_finance_timestamp (contoh: 1234567890_1704873600)
        // Format LAMA: ref_finance saja (contoh: 1234567890)
        $superflash_trx_id = $transaction_id ?: $reff_id;

        // Extract ref_finance dari external_id jika format baru (mengandung underscore)
        $ref_finance_extracted = $reff_id;
        if (strpos($reff_id, '_') !== false) {
            $parts = explode('_', $reff_id);
            array_pop($parts); // Hapus timestamp
            $ref_finance_extracted = implode('_', $parts);
        }

        $db_kas = $this->db(1); // db kas itu db 1
        
        if (!$db_kas) {
            \Log::write("Err: DB 1", 'webhook', 'SuperFlash');
            echo json_encode(['status' => false, 'message' => 'DB Error']);
            return;
        }

        // Normalize status: SuperFlash uses PENDING, SUCCESS, FAILED
        $statusLower = strtolower($status_superflash);
        $isPaid = ($statusLower === 'success');
        $isExpired = ($statusLower === 'expired' || $statusLower === 'failed');
        $isFailed = ($statusLower === 'failed');

        // Update payment_state first (use transaction_id if available, otherwise external_id)
        $update_kas = $db_kas->update("kas", ["payment_state" => $status_superflash], ["payment_trx_id" => $superflash_trx_id]);
        $affected = $db_kas->affected_rows();
        
        if (!$update_kas || $affected == 0) {
            // Fallback: coba update berdasarkan ref_finance (untuk data lama)
            $update_kas = $db_kas->update("kas", ["payment_state" => $status_superflash], ["ref_finance" => $ref_finance_extracted]);
            $affected = $db_kas->affected_rows();
            
            if (!$update_kas || $affected == 0) {
                \Log::write("Err: Kas payment_state Update Failed trx=$superflash_trx_id ref=$ref_finance_extracted status=$status_superflash (affected=0)", 'webhook', 'SuperFlash');
            } else {
                \Log::write("OK: Kas payment_state Updated by ref_finance=$ref_finance_extracted status=$status_superflash", 'webhook', 'SuperFlash');
            }
        } else {
            \Log::write("OK: Kas payment_state Updated by payment_trx_id=$superflash_trx_id status=$status_superflash", 'webhook', 'SuperFlash');
        }

        // Handle based on status
        if ($isPaid) {
            // Lookup kas record
            $cek_kas = $db_kas->get_where("kas", ["payment_trx_id" => $superflash_trx_id])->row();
            
            // Fallback: lookup by ref_finance
            if (!$cek_kas) {
                $cek_kas = $db_kas->get_where("kas", ["ref_finance" => $ref_finance_extracted])->row();
            }

            if (!$cek_kas) {
                \Log::write("Err: Kas Not Found trx=$superflash_trx_id ref=$ref_finance_extracted", 'webhook', 'SuperFlash');
                echo json_encode(['status' => false, 'message' => 'Kas Not Found']);
                return;
            }

            $ref_finance = $cek_kas->ref_finance;

            // Update kas status_mutasi = 3 (paid), payment_state = 'paid'
            $update = $db_kas->update("kas", ["status_mutasi" => 3, "payment_state" => "paid"], ["ref_finance" => $ref_finance]);

            if (!$update) {
                \Log::write("Err: Upd Kas ref=$ref_finance", 'webhook', 'SuperFlash');
            } else {
                \Log::write("OK: Kas PAID ref=$ref_finance trx=$superflash_trx_id", 'webhook', 'SuperFlash');
                
                // Send Webhook to QR Server (Node.js) to notify frontend
                $this->notifyQRServer($cek_kas);
            }
        } elseif ($isExpired) {
            // Delete kas if status_mutasi is not yet 3 (not paid)
            $db_kas->query("DELETE FROM kas WHERE payment_trx_id = ? AND status_mutasi != 3", [$superflash_trx_id]);
            $affected = $db_kas->affected_rows();
            \Log::write("OK: Kas EXPIRED trx=$superflash_trx_id deleted=$affected", 'webhook', 'SuperFlash');
        } elseif ($isFailed) {
            // Delete kas if status_mutasi is not yet 3 (not paid)
            $db_kas->query("DELETE FROM kas WHERE payment_trx_id = ? AND status_mutasi != 3", [$superflash_trx_id]);
            $affected = $db_kas->affected_rows();
            \Log::write("OK: Kas FAILED trx=$superflash_trx_id deleted=$affected", 'webhook', 'SuperFlash');
        } else {
            \Log::write("Warn: Unknown status=$status_superflash trx=$superflash_trx_id", 'webhook', 'SuperFlash');
        }

        echo json_encode(['status' => true, 'message' => 'Success']);
    }

    /**
     * Notify QR Server about payment success
     */
    private function notifyQRServer($cek_kas)
    {
        try {
            $qrString = isset($cek_kas->payment_qr_string) ? $cek_kas->payment_qr_string : '';

            if ($cek_kas && !empty($qrString)) {
                $kasirId = $cek_kas->id_cabang;

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
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                    \Log::write("Err: QRS Push $httpCode kasir=$kasirId", 'webhook', 'SuperFlash');
                }
            }
        } catch (\Exception $ex) {
            \Log::write("Err: QRS Exc " . $ex->getMessage(), 'webhook', 'SuperFlash');
        }
    }

    /**
     * Handle Resto Kas QRIS Payment
     * external_id format: RESTOKAS_timestamp
     */
    private function handleRestoKas($reff_id, $status_superflash, $transaction_id)
    {
        $db_index = 2; // mdl_resto
        $db = $this->db($db_index);

        if (!$db) {
            \Log::write("Err: DB Resto Not Found", 'webhook', 'SuperFlash');
            return;
        }

        // Normalize status: SuperFlash uses PENDING, SUCCESS, FAILED
        $statusLower = strtolower($status_superflash);
        $isPaid = ($statusLower === 'success');

        if (!$isPaid) {
            \Log::write("RESTOKAS: Status not paid ($status_superflash) - $reff_id", 'webhook', 'SuperFlash');
            return;
        }

        // Find kas record by payment_trx_id (use transaction_id if available, otherwise external_id)
        $search_id = $transaction_id ?: $reff_id;
        $kas = $db->get_where_row('kas', "payment_trx_id = '" . $search_id . "'");

        if (!$kas) {
            \Log::write("RESTOKAS: Kas not found - $search_id", 'webhook', 'SuperFlash');
            return;
        }

        // Already paid?
        if ($kas['status_mutasi'] == 1 && $kas['payment_state'] == 'paid') {
            \Log::write("RESTOKAS: Already paid - $search_id", 'webhook', 'SuperFlash');
            return;
        }

        // Update kas: status_mutasi = 1 (verified), payment_state = 'paid'
        $update = $db->update('kas', "status_mutasi = 1, payment_state = 'paid'", "id = " . $kas['id']);

        if ($update['errno'] != 0) {
            \Log::write("RESTOKAS: Update failed - " . $update['error'], 'webhook', 'SuperFlash');
            return;
        }

        \Log::write("RESTOKAS: Paid OK - $search_id, Ref: " . $kas['ref'], 'webhook', 'SuperFlash');

        // Update step of the order (ref)
        $ref = $kas['ref'];
        
        // Calculate totals
        $order = $db->get_where('pesanan', "ref = '" . $ref . "'");
        $total_tagihan = 0;
        foreach ($order as $o) {
            $subTotal = ($o['harga'] * $o['qty']) - ($o['diskon'] ?? 0);
            $total_tagihan += $subTotal;
        }

        $payments = $db->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $ref . "'");
        $total_dibayar = 0;
        $total_verified = 0;
        $has_pending = false;
        
        foreach ($payments as $p) {
            $total_dibayar += $p['jumlah'];
            if ($p['status_mutasi'] == 1) {
                $total_verified += $p['jumlah'];
            } else {
                $has_pending = true;
            }
        }

        // Determine step
        if ($total_dibayar >= $total_tagihan) {
            if ($total_verified >= $total_tagihan && !$has_pending) {
                // All verified, close order
                $db->update('ref', "step = 1", "id = '" . $ref . "'");
                \Log::write("RESTOKAS: Order closed - $ref", 'webhook', 'SuperFlash');
            } else {
                // Has pending, needs manual check
                $db->update('ref', "step = 4", "id = '" . $ref . "'");
                \Log::write("RESTOKAS: Order pending check - $ref", 'webhook', 'SuperFlash');
            }
        }
        // If not fully paid, step remains 0 (order open)
    }
}
