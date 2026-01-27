<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Tokopay;

/**
 * QRIS Controller
 * Handles TokoPay QRIS payment gateway operations
 */
class QRIS extends Controller
{
    /**
     * Generate QRIS payment
     * Endpoint: /QRIS/generate
     * Method: POST
     * Parameters:
     *   - nominal (int): Amount in Rupiah
     *   - ref_id (string): Reference ID for the transaction
     *   - metode (string, optional): Payment method, default 'QRIS'
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
            $metode = isset($body['metode']) ? trim($body['metode']) : 'QRIS';

            // Validation
            if ($nominal <= 0) {
                $this->error('Nominal tidak valid. Minimal 1 Rupiah', 400);
            }

            if (empty($ref_id)) {
                $this->error('ref_id tidak boleh kosong', 400);
            }

            if (strtoupper($metode) !== 'QRIS') {
                $this->error('Hanya menerima metode QRIS', 400);
            }

            // Generate unique order_id (ref_id + timestamp)
            $unique_order_id = $ref_id . '_' . time();

            // Call TokoPay API
            $tokopay = new Tokopay();
            $response = $tokopay->createOrder($nominal, $unique_order_id, $metode);
            $data = json_decode($response, true);

            if (isset($data['status']) && $data['status']) {
                $qr_string = '';
                if (isset($data['data']['qr_string']) && !empty($data['data']['qr_string'])) {
                    $qr_string = $data['data']['qr_string'];
                } elseif (isset($data['qr_string']) && !empty($data['qr_string'])) {
                    $qr_string = $data['qr_string'];
                }

                if (empty($qr_string)) {
                    $this->error('QR String tidak ditemukan dari TokoPay', 500);
                }

                $this->success([
                    'qr_string' => $qr_string,
                    'trx_id' => $unique_order_id,
                    'ref_id' => $ref_id,
                    'nominal' => $nominal,
                    'metode' => $metode
                ], 'QRIS berhasil di-generate');
            } else {
                $errorMsg = isset($data['message']) ? $data['message'] : 'Gagal generate QRIS dari TokoPay';
                $this->error($errorMsg, 500, $data);
            }
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check payment status
     * Endpoint: /QRIS/status
     * Method: GET
     * Parameters:
     *   - ref_id (string): Reference ID or transaction ID
     *   - nominal (int): Amount in Rupiah
     *   - metode (string, optional): Payment method, default 'QRIS'
     */
    public function status()
    {
        $this->handleCors();

        if (!$this->isGet()) {
            $this->error('Method not allowed. Use GET', 405);
        }

        try {
            $ref_id = isset($_GET['ref_id']) ? trim($_GET['ref_id']) : '';
            $nominal = isset($_GET['nominal']) ? intval($_GET['nominal']) : 0;
            $metode = isset($_GET['metode']) ? trim($_GET['metode']) : 'QRIS';

            // Validation
            if (empty($ref_id)) {
                $this->error('ref_id tidak boleh kosong', 400);
            }

            if ($nominal <= 0) {
                $this->error('Nominal tidak valid', 400);
            }

            if (strtoupper($metode) !== 'QRIS') {
                $this->error('Hanya menerima metode QRIS', 400);
            }

            // Call TokoPay API
            $tokopay = new Tokopay();
            $response = $tokopay->checkStatus($ref_id, $nominal, $metode);
            $data = json_decode($response, true);

            // Check for connection error
            if (isset($data['status']) && $data['status'] === false && isset($data['message'])) {
                $this->error('Gagal cek status ke TokoPay: ' . $data['message'], 500);
            }

            // Parse status from response
            $status_trx = '';
            $isPaid = false;
            $isExpired = false;

            // Check various possible response structures
            if (isset($data['status'])) {
                $status_val = $data['status'];
                if ($status_val === true || $status_val === 1 || $status_val === 'true' || strtolower($status_val) === 'success') {
                    if (isset($data['data'])) {
                        if (isset($data['data']['status_pembayaran'])) {
                            $status_trx = strtolower($data['data']['status_pembayaran']);
                        } elseif (isset($data['data']['status'])) {
                            $status_trx = strtolower($data['data']['status']);
                        } else {
                            $isPaid = true;
                        }
                    } else {
                        $isPaid = true;
                    }
                }
            }

            // Check inside 'data' object
            if (empty($status_trx) && !$isPaid && isset($data['data'])) {
                if (isset($data['data']['status_pembayaran'])) {
                    $status_trx = strtolower($data['data']['status_pembayaran']);
                } elseif (isset($data['data']['status'])) {
                    $status_trx = strtolower($data['data']['status']);
                }
            }

            // Check at root level
            if (empty($status_trx) && !$isPaid) {
                if (isset($data['status_pembayaran'])) {
                    $status_trx = strtolower($data['status_pembayaran']);
                } elseif (isset($data['status']) && is_string($data['status'])) {
                    $status_trx = strtolower($data['status']);
                }
            }

            // Determine payment status
            if (!empty($status_trx) && !$isPaid) {
                if (in_array($status_trx, ['success', 'paid', 'settlement', 'capture'])) {
                    $isPaid = true;
                } elseif (in_array($status_trx, ['expired', 'cancelled', 'cancel', 'timeout', 'failed', 'fail'])) {
                    $isExpired = true;
                }
            }

            $this->success([
                'ref_id' => $ref_id,
                'nominal' => $nominal,
                'status' => $isPaid ? 'paid' : ($isExpired ? 'expired' : 'pending'),
                'status_detail' => $status_trx ?: 'unknown',
                'raw_response' => $data
            ], 'Status pembayaran berhasil diambil');
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check merchant balance
     * Endpoint: /QRIS/balance
     * Method: GET
     */
    public function balance()
    {
        $this->handleCors();

        if (!$this->isGet()) {
            $this->error('Method not allowed. Use GET', 405);
        }

        try {
            $tokopay = new Tokopay();
            $response = $tokopay->getMerchantBalance();
            $data = json_decode($response, true);

            if (isset($data['status']) && $data['status'] === false) {
                $errorMsg = isset($data['message']) ? $data['message'] : 'Gagal mengambil saldo dari TokoPay';
                $this->error($errorMsg, 500, $data);
            }

            $this->success($data, 'Saldo berhasil diambil');
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Withdraw balance (tarik saldo)
     * Endpoint: /QRIS/withdraw
     * Method: POST
     * Parameters:
     *   - nominal (int): Amount to withdraw in Rupiah (minimum 10,000)
     */
    public function withdraw()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }

        try {
            $body = $this->getBody();
            $nominal = isset($body['nominal']) ? intval($body['nominal']) : 0;

            // Validation
            if ($nominal < 10000) {
                $this->error('Minimal penarikan Rp 10.000', 400);
            }

            // Call TokoPay API
            $tokopay = new Tokopay();
            $response = $tokopay->tarikSaldo($nominal);
            $data = json_decode($response, true);

            // Check for connection error
            if (isset($data['status']) && $data['status'] === false && isset($data['message'])) {
                $this->error('Gagal tarik saldo ke TokoPay: ' . $data['message'], 500);
            }

            $this->success($data, 'Permintaan penarikan saldo berhasil dikirim');
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }
}
