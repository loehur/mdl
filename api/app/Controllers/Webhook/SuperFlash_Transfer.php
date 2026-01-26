<?php
namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * SuperFlash Transfer Webhook Handler
 * Handles transfer notifications from SuperFlash (Flash Mobile) Transfer service
 */
class SuperFlash_Transfer extends Controller
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
            \Log::write("Err: Invalid JSON", 'webhook', 'SuperFlash_Transfer');
            return;
        }

        // SuperFlash Transfer callback format (from docs):
        // - external_id: Merchant transaction ID
        // - transaction_id: SuperFlash transaction ID
        // - status: Object with success, code (0001=Success, 0002=Pending, 0003=Failed), message
        // - signature: (to be verified based on SuperFlash docs)
        $external_id = isset($data['external_id']) ? $data['external_id'] : (isset($data['data']['external_id']) ? $data['data']['external_id'] : '');
        $transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : (isset($data['data']['transaction_id']) ? $data['data']['transaction_id'] : '');
        
        // Transfer API uses status object: {success: true/false, code: "0001"/"0002"/"0003", message: "..."}
        $status_obj = isset($data['status']) ? $data['status'] : (isset($data['data']['status']) ? $data['data']['status'] : null);
        $status_code = null;
        $status_message = null;
        $status_success = null;
        
        if (is_array($status_obj)) {
            $status_code = $status_obj['code'] ?? null;
            $status_message = $status_obj['message'] ?? null;
            $status_success = $status_obj['success'] ?? null;
        } else if (is_string($status_obj)) {
            // Fallback: if status is string, try to map it
            $status_code = $status_obj;
        }
        
        // Normalize status to string for compatibility
        $status = '';
        if ($status_code === '0001') {
            $status = 'SUCCESS';
        } else if ($status_code === '0002') {
            $status = 'PENDING';
        } else if ($status_code === '0003') {
            $status = 'FAILED';
        } else if ($status_success === true) {
            $status = 'SUCCESS';
        } else if ($status_success === false) {
            $status = 'FAILED';
        } else {
            $status = strtoupper($status_code ?? $status_message ?? 'UNKNOWN');
        }
        
        $signature_provided = isset($data['signature']) ? $data['signature'] : (isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '');

        // Use external_id as primary identifier
        $reff_id = $external_id;

        if (empty($reff_id)) {
            echo json_encode(['status' => false, 'message' => 'Missing external_id']);
            \Log::write("Err: Missing external_id", 'webhook', 'SuperFlash_Transfer');
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
            \Log::write("Err: Invalid Signature external_id=$external_id", 'webhook', 'SuperFlash_Transfer');
            return;
        }

        // Handle based on prefix (same as Tokopay and SuperFlash QRIS)
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
                 'status_code' => $status_code,
                 'status_message' => $status_message,
                 'status_success' => $status_success,
                 'status_normalized' => $status,
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
             \Log::write("TEST: SuperFlash Transfer Webhook received - " . json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'webhook', 'SuperFlash_Transfer');
             echo json_encode(['status' => true, 'message' => 'TEST webhook logged', 'logged' => true]);
             return;
        } else if ($parts[0] === 'TRANSFER') {
             // Handle transfer transactions
             $this->handleTransfer($reff_id, $status, $status_code, $transaction_id, $data);
             echo json_encode(['status' => true, 'message' => 'Processed TRANSFER']);
             return;
        } else {
             // Default: log and acknowledge
             \Log::write("Info: SuperFlash Transfer Webhook - external_id=$external_id, status=$status, transaction_id=$transaction_id", 'webhook', 'SuperFlash_Transfer');
             echo json_encode(['status' => true, 'message' => 'Webhook received']);
        }
    }
    
    /**
     * Handle Transfer transaction updates
     * 
     * @param string $reff_id External ID (merchant transaction ID)
     * @param string $status Normalized status (SUCCESS, PENDING, FAILED)
     * @param string $status_code Original status code (0001, 0002, 0003)
     * @param string $transaction_id SuperFlash transaction ID
     * @param array $data Full webhook payload
     */
    private function handleTransfer($reff_id, $status, $status_code, $transaction_id, $data)
    {
        // Example implementation - adjust based on your database schema
        // This is a placeholder that logs the transfer update
        
        \Log::write("Transfer Update: ref=$reff_id, status=$status, code=$status_code, trx=$transaction_id", 'webhook', 'SuperFlash_Transfer');
        
        // TODO: Implement your transfer update logic here
        // Example:
        // 1. Find transfer record by external_id
        // 2. Update status based on status_code:
        //    - 0001 (Success): Mark as completed
        //    - 0002 (Pending): Keep as pending
        //    - 0003 (Failed): Mark as failed
        // 3. Update transaction_id if needed
        // 4. Send notifications if needed
        
        // Example database update (adjust table/field names):
        // $db = $this->db(0); // or appropriate DB index
        // $db->update('transfers', [
        //     'status' => $status,
        //     'status_code' => $status_code,
        //     'transaction_id' => $transaction_id,
        //     'updated_at' => date('Y-m-d H:i:s')
        // ], ['external_id' => $reff_id]);
    }
}
