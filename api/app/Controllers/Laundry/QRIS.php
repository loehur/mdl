<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Models\Tokopay;

/**
 * QRIS Controller (Laundry TokoPay)
 * Handles TokoPay QRIS payment gateway operations
 * URL: /Laundry/QRIS/{method}
 */
class QRIS extends Controller
{
    /**
     * Generate QRIS payment
     * Endpoint: /Laundry/QRIS/generate
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
            header('Content-Type: application/json');
            http_response_code(405);
            echo json_encode([
                'status' => false,
                'message' => 'Method not allowed. Use POST'
            ]);
            exit;
        }

        try {
            $body = $this->getBody();
            
            $nominal = isset($body['nominal']) ? intval($body['nominal']) : 0;
            $ref_id = isset($body['ref_id']) ? trim($body['ref_id']) : '';
            $metode = isset($body['metode']) ? trim($body['metode']) : 'QRIS';

            // Validation
            if ($nominal <= 0) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'Nominal tidak valid. Minimal 1 Rupiah'
                ]);
                exit;
            }

            if (empty($ref_id)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'ref_id tidak boleh kosong'
                ]);
                exit;
            }

            if (strtoupper($metode) !== 'QRIS') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'Hanya menerima metode QRIS'
                ]);
                exit;
            }

            // PENTING: Bersihkan ref_id dari timestamp jika ada (untuk menghindari double)
            // ref_id seharusnya hanya ID transaksi, bukan dengan timestamp
            $clean_ref_id = $ref_id;
            if (strpos($ref_id, '_') !== false) {
                $parts = explode('_', $ref_id);
                $last_part = end($parts);
                // Jika bagian terakhir adalah timestamp (10 digit angka), ambil hanya ref asli
                if (is_numeric($last_part) && strlen($last_part) == 10) {
                    array_pop($parts);
                    $clean_ref_id = implode('_', $parts);
                }
            }

            // Generate unique order_id dengan ref_id yang bersih
            $unique_order_id = $clean_ref_id . '_' . time();

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
                    header('Content-Type: application/json');
                    http_response_code(500);
                    echo json_encode([
                        'status' => false,
                        'message' => 'QR String tidak ditemukan dari TokoPay'
                    ]);
                    exit;
                }

                // Simplified response
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode([
                    'status' => true,
                    'trx_id' => $unique_order_id,
                    'ref_id' => $ref_id,
                    'qr_string' => $qr_string
                ]);
                exit;
            } else {
                $errorMsg = isset($data['message']) ? $data['message'] : 'Gagal generate QRIS dari TokoPay';
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'status' => false,
                    'message' => $errorMsg
                ]);
                exit;
            }
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'status' => false,
                'message' => 'Internal Server Error: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Check payment status
     * Endpoint: /Laundry/QRIS/status
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
            header('Content-Type: application/json');
            http_response_code(405);
            echo json_encode([
                'status' => false,
                'message' => 'Method not allowed. Use GET'
            ]);
            exit;
        }

        try {
            $ref_id = isset($_GET['ref_id']) ? trim($_GET['ref_id']) : '';
            $nominal = isset($_GET['nominal']) ? intval($_GET['nominal']) : 0;
            $metode = isset($_GET['metode']) ? trim($_GET['metode']) : 'QRIS';

            // Validation
            if (empty($ref_id)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'ref_id tidak boleh kosong'
                ]);
                exit;
            }

            if ($nominal <= 0) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'Nominal tidak valid'
                ]);
                exit;
            }

            if (strtoupper($metode) !== 'QRIS') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'Hanya menerima metode QRIS'
                ]);
                exit;
            }

            // Call TokoPay API
            $tokopay = new Tokopay();
            $response = $tokopay->checkStatus($ref_id, $nominal, $metode);
            $data = json_decode($response, true);

            // Check for connection error
            if (isset($data['status']) && $data['status'] === false && isset($data['message'])) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'status' => false,
                    'message' => 'Gagal cek status ke TokoPay: ' . $data['message']
                ]);
                exit;
            }

            // Jika order tidak ditemukan / tidak ada data transaksi, return pending
            if (empty($data) || (isset($data['data']) && !is_array($data['data']))) {
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode([
                    'status' => true,
                    'trx_id' => $ref_id,
                    'ref_id' => $ref_id,
                    'payment_status' => 'pending',
                    'trx_status' => 'not_found'
                ]);
                exit;
            }

            // Parse status from response
            $status_trx = '';
            $isPaid = false;
            $isExpired = false;

            // Check inside 'data' object first (highest priority)
            if (isset($data['data'])) {
                if (isset($data['data']['status_pembayaran']) && !empty($data['data']['status_pembayaran'])) {
                    $status_trx = strtolower(trim($data['data']['status_pembayaran']));
                } elseif (isset($data['data']['status']) && !empty($data['data']['status']) && is_string($data['data']['status'])) {
                    $status_trx = strtolower(trim($data['data']['status']));
                } elseif (isset($data['data']['status_detail']) && !empty($data['data']['status_detail'])) {
                    $status_trx = strtolower(trim($data['data']['status_detail']));
                }
            }

            // Check at root level (lower priority) - HATI-HATI: root "status" = "Success" 
            // dari TokoPay berarti API call berhasil, BUKAN status pembayaran!
            // Jangan pernah pakai root status untuk payment status.
            if (empty($status_trx)) {
                if (isset($data['status_pembayaran']) && !empty($data['status_pembayaran'])) {
                    $status_trx = strtolower(trim($data['status_pembayaran']));
                } elseif (isset($data['status_detail']) && !empty($data['status_detail'])) {
                    $status_trx = strtolower(trim($data['status_detail']));
                }
                // JANGAN pakai data['status'] - di TokoPay itu = "Success" (API ok), bukan status bayar
            }

            // Jika tidak ada status apapun yang ditemukan, anggap pending
            if (empty($status_trx)) {
                $status_trx = 'pending';
            }

            // Determine payment status based on explicit status values
            if (!empty($status_trx)) {
                if (in_array($status_trx, \Env::QRIS_STATUS_SUCCESS)) {
                    $isPaid = true;
                } elseif (in_array($status_trx, \Env::QRIS_STATUS_EXPIRED)) {
                    $isExpired = true;
                }
            }

            // Simplified response
            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'trx_id' => $ref_id,
                'ref_id' => $ref_id,
                'payment_status' => $isPaid ? 'paid' : ($isExpired ? 'expired' : 'pending'),
                'trx_status' => $status_trx ?: 'unknown'
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'status' => false,
                'message' => 'Internal Server Error: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Check merchant balance
     * Endpoint: /Laundry/QRIS/balance
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
     * Endpoint: /Laundry/QRIS/withdraw
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
