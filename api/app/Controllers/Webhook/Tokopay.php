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

        // Handle based on prefix
        $parts = explode('_', $reff_id);
        if ($parts[0] === 'TEST') {
             // TEST: hanya log saja, tidak perlu proses
             // Log raw JSON dan semua data yang diterima
             $raw_json = file_get_contents('php://input');
             $log_data = [
                 'raw_json' => $raw_json,
                 'decoded_data' => $data,
                 'reff_id' => $reff_id,
                 'status' => $status,
                 'signature_provided' => $signature_provided,
                 'signature_generated' => md5($merchant_id . ':' . $secret . ':' . $reff_id),
                 'signature_valid' => ($signature_provided === md5($merchant_id . ':' . $secret . ':' . $reff_id)),
                 'timestamp' => date('Y-m-d H:i:s'),
                 'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
             ];
             \Log::write("TEST: Webhook received - " . json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'webhook', 'Tokopay');
             echo json_encode(['status' => true, 'message' => 'TEST webhook logged', 'logged' => true]);
             return;
        } else if ($parts[0] === 'SALONSUB') {
             $this->handleSalonSubscription($reff_id, $status);
             echo json_encode(['status' => true, 'message' => 'Processed SALONSUB']);
             return;
        } else if ($parts[0] === 'MDLINV') {
             $this->handleInvoicePayment($reff_id, $status);
             echo json_encode(['status' => true, 'message' => 'Processed MDLINV']);
             return;
        } else if ($parts[0] === 'RESTOKAS') {
             $this->handleRestoKas($reff_id, $status);
             echo json_encode(['status' => true, 'message' => 'Processed RESTOKAS']);
             return;
        } else {
             $this->handleKasLaundry($reff_id, $status);
        }
    }
    
    private function handleSalonSubscription($payment_ref, $status_tokopay)
    {
        $db_index = 4; // mdl_salon
        $db = $this->db($db_index);

        if (!$db) {
             \Log::write("Err: DB Salon Not Found", 'webhook', 'Tokopay');
             return;
        }

        // Get payment record
        $payment = $db->get_where('subscription_payments', ['payment_ref' => $payment_ref])->row_array();

        if (!$payment) {
             \Log::write("Err: Sub Pmt Not Found ref=$payment_ref", 'webhook', 'Tokopay');
             return;
        }
        
        // Normalize status (gunakan constant dari Env agar sejalan dengan QRIS endpoint)
        $statusLower = strtolower($status_tokopay);
        $isPaid = in_array($statusLower, \Env::QRIS_STATUS_SUCCESS);
        $isExpired = in_array($statusLower, \Env::QRIS_STATUS_EXPIRED);
        $isFailed = ($statusLower === 'failed');

        if ($isPaid) {
            $dbStatus = strtolower($payment['payment_status'] ?? '');
            if (!in_array($dbStatus, \Env::QRIS_STATUS_SUCCESS)) {
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

                // Update salon table - DISABLED (Columns missing in production)
                /*
                $db->update('salon', [
                    'subscription_status' => 'active',
                    'subscription_end_date' => $payment['period_end']
                ], ['salon_id' => $salon_id]);
                */
                
                \Log::write("OK: Salon Sub PAID ref=$payment_ref salon=$salon_id", 'webhook', 'Tokopay');
            }
        } elseif ($isExpired) {
            $db->update('subscription_payments', [
                'payment_status' => 'failed' 
            ], ['payment_ref' => $payment_ref]);
            \Log::write("OK: Salon Sub EXPIRED ref=$payment_ref", 'webhook', 'Tokopay');
        } elseif ($isFailed) {
            $db->update('subscription_payments', [
                'payment_status' => 'failed'
            ], ['payment_ref' => $payment_ref]);
             \Log::write("OK: Salon Sub FAILED ref=$payment_ref", 'webhook', 'Tokopay');
        }
    }

    private function handleInvoicePayment($payment_ref, $status_tokopay)
    {
        $db_index = 6; // mdl_invoice
        $db = $this->db($db_index);

        if (!$db) {
            \Log::write("Err: DB Invoice Not Found", 'webhook', 'Tokopay');
            return;
        }

        $payment = $db->get_where('invoice_payments', ['payment_ref' => $payment_ref])->row_array();

        if (!$payment) {
            \Log::write("Err: Invoice Pmt Not Found ref=$payment_ref", 'webhook', 'Tokopay');
            return;
        }

        $statusLower = strtolower($status_tokopay);
        $isPaid = in_array($statusLower, \Env::QRIS_STATUS_SUCCESS);
        $isExpired = in_array($statusLower, \Env::QRIS_STATUS_EXPIRED);

        if ($isPaid) {
            if ($payment['payment_status'] !== 'success') {
                $now = date('Y-m-d H:i:s');
                $db->update('invoice_payments', [
                    'payment_status' => 'success',
                    'paid_at' => $now,
                ], ['payment_ref' => $payment_ref]);

                $db->update('invoices', [
                    'payment_status' => 'paid',
                    'status' => 'paid',
                ], ['id' => $payment['invoice_id']]);

                \Log::write("OK: Invoice PAID ref=$payment_ref inv={$payment['invoice_id']}", 'webhook', 'Tokopay');
            }
        } elseif ($isExpired) {
            $db->update('invoice_payments', [
                'payment_status' => 'expired',
            ], ['payment_ref' => $payment_ref]);

            $db->update('invoices', [
                'payment_status' => 'unpaid',
            ], ['id' => $payment['invoice_id']]);

            \Log::write("OK: Invoice EXPIRED ref=$payment_ref", 'webhook', 'Tokopay');
        }
    }

    private function handleKasLaundry($reff_id, $status)
    {
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

        $db_kas = $this->db(1); // db kas itu db 1
        
        if (!$db_kas) {
            \Log::write("Err: DB 1", 'webhook', 'Tokopay');
            echo json_encode(['status' => false, 'message' => 'DB Error']);
            return;
        }

        // Normalize status (gunakan constant dari Env agar sejalan dengan QRIS endpoint)
        $statusLower = strtolower($status);
        $isPaid = in_array($statusLower, \Env::QRIS_STATUS_SUCCESS);
        $isExpired = in_array($statusLower, \Env::QRIS_STATUS_EXPIRED);
        $isFailed = ($statusLower === 'failed');

        // Update payment_state first
        $update_kas = $db_kas->update("kas", ["payment_state" => $status], ["payment_trx_id" => $tokopay_trx_id]);
        $affected = $db_kas->affected_rows();
        
        if (!$update_kas || $affected == 0) {
            // Fallback: coba update berdasarkan ref_finance (untuk data lama)
            $update_kas = $db_kas->update("kas", ["payment_state" => $status], ["ref_finance" => $ref_finance_extracted]);
            $affected = $db_kas->affected_rows();
            
            if (!$update_kas || $affected == 0) {
                \Log::write("Err: Kas payment_state Update Failed trx=$tokopay_trx_id ref=$ref_finance_extracted status=$status (affected=0)", 'webhook', 'Tokopay');
            } else {
                \Log::write("OK: Kas payment_state Updated by ref_finance=$ref_finance_extracted status=$status", 'webhook', 'Tokopay');
            }
        } else {
            \Log::write("OK: Kas payment_state Updated by payment_trx_id=$tokopay_trx_id status=$status", 'webhook', 'Tokopay');
        }

        // Handle based on status
        if ($isPaid) {
            // Lookup kas record
            $cek_kas = $db_kas->get_where("kas", ["payment_trx_id" => $tokopay_trx_id])->row();
            
            // Fallback: lookup by ref_finance
            if (!$cek_kas) {
                $cek_kas = $db_kas->get_where("kas", ["ref_finance" => $ref_finance_extracted])->row();
            }

            if (!$cek_kas) {
                \Log::write("Err: Kas Not Found trx=$tokopay_trx_id ref=$ref_finance_extracted", 'webhook', 'Tokopay');
                echo json_encode(['status' => false, 'message' => 'Kas Not Found']);
                return;
            }

            $ref_finance = $cek_kas->ref_finance;

            // Update kas status_mutasi = 3 (paid), payment_state = 'paid'
            $update = $db_kas->update("kas", ["status_mutasi" => 3, "payment_state" => "paid"], ["ref_finance" => $ref_finance]);

            if (!$update) {
                \Log::write("Err: Upd Kas ref=$ref_finance", 'webhook', 'Tokopay');
            } else {
                \Log::write("OK: Kas PAID ref=$ref_finance", 'webhook', 'Tokopay');
                
                // Send Webhook to QR Server (Node.js) to notify frontend
                $this->notifyQRServer($cek_kas);
            }
        } elseif ($isExpired || $isFailed) {
            // Delete kas if status_mutasi is not yet 3 (not paid)
            $db_kas->query("DELETE FROM kas WHERE payment_trx_id = ? AND status_mutasi != 3", [$tokopay_trx_id]);
            $affected = $db_kas->affected_rows();
            \Log::write("OK: Kas EXPIRED trx=$tokopay_trx_id deleted=$affected", 'webhook', 'Tokopay');
        } else {
            \Log::write("Warn: Unknown status=$status trx=$tokopay_trx_id", 'webhook', 'Tokopay');
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
                    \Log::write("Err: QRS Push $httpCode kasir=$kasirId", 'webhook', 'Tokopay');
                }
            }
        } catch (\Exception $ex) {
            \Log::write("Err: QRS Exc " . $ex->getMessage(), 'webhook', 'Tokopay');
        }
    }

    /**
     * Handle Resto Kas QRIS Payment
     * payment_trx_id format: RESTOKAS_timestamp
     */
    private function handleRestoKas($reff_id, $status)
    {
        $db_index = 2; // mdl_resto - adjust as needed
        $db = $this->db($db_index);

        if (!$db) {
            \Log::write("Err: DB Resto Not Found", 'webhook', 'Tokopay');
            return;
        }

        // Normalize status (gunakan constant dari Env agar sejalan dengan QRIS endpoint)
        $statusLower = strtolower($status);
        $isPaid = in_array($statusLower, \Env::QRIS_STATUS_SUCCESS);

        if (!$isPaid) {
            \Log::write("RESTOKAS: Status not paid ($status) - $reff_id", 'webhook', 'Tokopay');
            return;
        }

        // Find kas record by payment_trx_id
        $kas = $db->get_where_row('kas', "payment_trx_id = '" . $reff_id . "'");

        if (!$kas) {
            \Log::write("RESTOKAS: Kas not found - $reff_id", 'webhook', 'Tokopay');
            return;
        }

        // Already paid?
        if ($kas['status_mutasi'] == 1 && $kas['payment_state'] == 'paid') {
            \Log::write("RESTOKAS: Already paid - $reff_id", 'webhook', 'Tokopay');
            return;
        }

        // Update kas: status_mutasi = 1 (verified), payment_state = 'paid'
        $update = $db->update('kas', "status_mutasi = 1, payment_state = 'paid'", "id = " . $kas['id']);

        if ($update['errno'] != 0) {
            \Log::write("RESTOKAS: Update failed - " . $update['error'], 'webhook', 'Tokopay');
            return;
        }

        \Log::write("RESTOKAS: Paid OK - $reff_id, Ref: " . $kas['ref'], 'webhook', 'Tokopay');

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
                \Log::write("RESTOKAS: Order closed - $ref", 'webhook', 'Tokopay');
            } else {
                // Has pending, needs manual check
                $db->update('ref', "step = 4", "id = '" . $ref . "'");
                \Log::write("RESTOKAS: Order pending check - $ref", 'webhook', 'Tokopay');
            }
        }
        // If not fully paid, step remains 0 (order open)
    }
}
