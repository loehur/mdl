<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Doku;

/**
 * QRIS_Doku Controller
 * Handles Doku SNAP QRIS payment gateway operations
 * Reference: https://developers.doku.com/accept-payments/direct-api/snap/integration-guide/qris
 */
class QRIS_Doku extends Controller
{
    /**
     * Generate QRIS payment via Doku SNAP
     * Endpoint: /QRIS_Doku/generate
     * Method: POST
     * Parameters:
     *   - nominal (int): Amount in Rupiah
     *   - ref_id (string): Reference ID for the transaction (partnerReferenceNo)
     *   - terminal_id (string, optional): Terminal ID, default 'k45'
     *   - expired_time (string, optional): Expiry time in ISO 8601 format (e.g., 2023-11-08T17:38:42+07:00)
     */
    public function generate()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }

        try {
            $body = $this->getBody();
            
            $nominal = isset($body['nominal']) ? intval($body['nominal']) : 0;
            $ref_id = isset($body['ref_id']) ? trim($body['ref_id']) : '';
            $terminal_id = isset($body['terminal_id']) ? trim($body['terminal_id']) : 'k45';
            $expired_time = isset($body['expired_time']) ? trim($body['expired_time']) : null;

            // Validation
            if ($nominal <= 0) {
                $this->error('Nominal tidak valid. Minimal 1 Rupiah', 400);
            }

            if (empty($ref_id)) {
                $this->error('ref_id tidak boleh kosong', 400);
            }

            // Validate expired_time format if provided
            if ($expired_time) {
                $date = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $expired_time);
                if (!$date) {
                    $this->error('Format expired_time tidak valid. Gunakan format ISO 8601: Y-m-d\TH:i:sP (contoh: 2023-11-08T17:38:42+07:00)', 400);
                }
            }

            // Generate unique partner reference number
            $unique_ref_id = $ref_id . '_' . time();

            // Call Doku API
            $doku = new Doku();
            $response = $doku->generateQRIS($unique_ref_id, $nominal, $terminal_id, $expired_time);
            $data = json_decode($response, true);

            // Check for error in response
            if (isset($data['status']) && $data['status'] === false) {
                $errorMsg = isset($data['message']) ? $data['message'] : 'Gagal generate QRIS dari Doku';
                $this->error($errorMsg, 500, $data);
            }

            // Check for successful response
            // Doc: Generate QRIS success = 2004700; some env may return 2002700
            $successCodes = ['2004700', '2002700'];
            if (isset($data['responseCode']) && in_array($data['responseCode'], $successCodes, true)) {
                // Success - QRIS generated
                $qrContent = isset($data['qrContent']) ? $data['qrContent'] : '';
                
                if (empty($qrContent)) {
                    $this->error('QR Content tidak ditemukan dari Doku', 500);
                }

                $this->success([
                    'qr_string' => $qrContent,
                    'qr_content' => $qrContent, // Alias for compatibility
                    'trx_id' => $unique_ref_id,
                    'ref_id' => $ref_id,
                    'partner_reference_no' => $unique_ref_id,
                    'original_reference_no' => isset($data['originalReferenceNo']) ? $data['originalReferenceNo'] : null,
                    'nominal' => $nominal,
                    'expired_time' => $expired_time,
                    'response_code' => $data['responseCode'],
                    'response_message' => isset($data['responseMessage']) ? $data['responseMessage'] : 'Success'
                ], 'QRIS berhasil di-generate via Doku');
            } else {
                // Error response from Doku
                $responseCode = isset($data['responseCode']) ? $data['responseCode'] : 'UNKNOWN';
                $responseMessage = isset($data['responseMessage']) ? $data['responseMessage'] : 'Unknown error from Doku';
                
                $this->error("Doku Error [$responseCode]: $responseMessage", 500, $data);
            }
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check QRIS payment status via Doku SNAP
     * Endpoint: /QRIS_Doku/status
     * Method: GET or POST
     * Parameters:
     *   - ref_id (string): Reference ID (partnerReferenceNo) used when generating QRIS
     *   - original_ref_no (string, optional): Original reference number from Doku
     *   - service_code (string, optional): Service code, default '47'
     */
    public function status()
    {
        $this->handleCors();

        try {
            // Support both GET and POST methods
            if ($this->isGet()) {
                $ref_id = isset($_GET['ref_id']) ? trim($_GET['ref_id']) : '';
                $original_ref_no = isset($_GET['original_ref_no']) ? trim($_GET['original_ref_no']) : null;
                $service_code = isset($_GET['service_code']) ? trim($_GET['service_code']) : '47';
            } elseif ($this->isPost()) {
                $body = $this->getBody();
                $ref_id = isset($body['ref_id']) ? trim($body['ref_id']) : '';
                $original_ref_no = isset($body['original_ref_no']) ? trim($body['original_ref_no']) : null;
                $service_code = isset($body['service_code']) ? trim($body['service_code']) : '47';
            } else {
                $this->error('Method not allowed. Use GET or POST', 405);
            }

            // Validation
            if (empty($ref_id)) {
                $this->error('ref_id tidak boleh kosong', 400);
            }

            // Call Doku API
            $doku = new Doku();
            $response = $doku->queryQRIS($ref_id, $original_ref_no, $service_code);
            $data = json_decode($response, true);

            // Check for error in response
            if (isset($data['status']) && $data['status'] === false) {
                $errorMsg = isset($data['message']) ? $data['message'] : 'Gagal cek status ke Doku';
                $this->error($errorMsg, 500, $data);
            }

            // Parse status from Doku response
            $isPaid = false;
            $isExpired = false;
            $statusDetail = 'unknown';
            
            // Check response code
            $responseCode = isset($data['responseCode']) ? $data['responseCode'] : '';
            $transactionStatusCode = isset($data['transactionStatusCode']) ? $data['transactionStatusCode'] : '';
            
            // Response codes based on Doku documentation
            // 2002700 = Success (for generate)
            // 2005400 = Success (for query)
            // Transaction status codes:
            // 00 = Success/Paid
            // 01 = Pending
            // 03 = Expired
            
            if ($responseCode === '2005400' || $responseCode === '2002700') {
                // Query successful, check transaction status
                if ($transactionStatusCode === '00') {
                    $isPaid = true;
                    $statusDetail = 'paid';
                } elseif ($transactionStatusCode === '03') {
                    $isExpired = true;
                    $statusDetail = 'expired';
                } elseif ($transactionStatusCode === '01') {
                    $statusDetail = 'pending';
                } else {
                    $statusDetail = 'unknown';
                }
            } else {
                // Error response
                $statusDetail = 'error';
            }

            $this->success([
                'ref_id' => $ref_id,
                'original_reference_no' => isset($data['originalReferenceNo']) ? $data['originalReferenceNo'] : null,
                'partner_reference_no' => isset($data['originalPartnerReferenceNo']) ? $data['originalPartnerReferenceNo'] : $ref_id,
                'status' => $isPaid ? 'paid' : ($isExpired ? 'expired' : 'pending'),
                'status_detail' => $statusDetail,
                'transaction_status_code' => $transactionStatusCode,
                'response_code' => $responseCode,
                'response_message' => isset($data['responseMessage']) ? $data['responseMessage'] : 'Unknown',
                'amount' => isset($data['amount']['value']) ? $data['amount']['value'] : null,
                'paid_time' => isset($data['paidTime']) ? $data['paidTime'] : null,
                'raw_response' => $data
            ], 'Status pembayaran berhasil diambil dari Doku');
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Debug B2B Token Authentication
     * Endpoint: /QRIS_Doku/debug_token
     * Method: GET
     * Returns detailed token request/response for troubleshooting
     */
    public function debug_token()
    {
        $this->handleCors();

        try {
            $doku = new Doku();
            $debug = $doku->getAccessTokenDebug();
            
            $this->success($debug, 'Debug info for B2B token authentication');
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }
}
