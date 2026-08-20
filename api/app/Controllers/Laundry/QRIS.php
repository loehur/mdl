<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Payment\QrisService;
use App\Models\Tokopay;

/**
 * QRIS Controller — endpoint HTTP untuk generate/cek QRIS.
 * Logika gateway terpusat di App\Helpers\Payment\QrisService.
 * URL: /Laundry/QRIS/{method}
 */
class QRIS extends Controller
{
    /** @var QrisService */
    private $qris;

    public function __construct()
    {
        parent::__construct();
        $this->qris = new QrisService();
    }

    /**
     * Generate QRIS payment
     * POST /Laundry/QRIS/generate
     */
    public function generate()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->jsonError('Method not allowed. Use POST', 405);
        }

        try {
            $body = $this->getBody();

            $nominal = isset($body['nominal']) ? (int) $body['nominal'] : 0;
            $ref_id = isset($body['ref_id']) ? trim($body['ref_id']) : '';
            $metode = isset($body['metode']) ? trim($body['metode']) : 'QRIS';

            if ($nominal <= 0) {
                $this->jsonError('Nominal tidak valid. Minimal 1 Rupiah', 400);
            }

            if ($ref_id === '') {
                $this->jsonError('ref_id tidak boleh kosong', 400);
            }

            if (strtoupper($metode) !== 'QRIS') {
                $this->jsonError('Hanya menerima metode QRIS', 400);
            }

            $result = $this->qris->generate($nominal, $ref_id, true);

            if ($result['failed'] || !$result['status']) {
                $this->jsonError($result['message'] ?: 'Gagal generate QRIS', 500);
            }

            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'trx_id' => $result['trx_id'],
                'ref_id' => $result['ref_id'],
                'qr_string' => $result['qr_string'],
                'gateway' => $result['gateway'],
            ]);
            exit;
        } catch (\Exception $e) {
            $this->jsonError('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check payment status
     * GET /Laundry/QRIS/status
     */
    public function status()
    {
        $this->handleCors();

        if (!$this->isGet()) {
            $this->jsonError('Method not allowed. Use GET', 405);
        }

        try {
            $ref_id = isset($_GET['ref_id']) ? trim($_GET['ref_id']) : '';
            $nominal = isset($_GET['nominal']) ? (int) $_GET['nominal'] : 0;
            $metode = isset($_GET['metode']) ? trim($_GET['metode']) : 'QRIS';

            if ($ref_id === '') {
                $this->jsonError('ref_id tidak boleh kosong', 400);
            }

            if ($nominal <= 0) {
                $this->jsonError('Nominal tidak valid', 400);
            }

            if (strtoupper($metode) !== 'QRIS') {
                $this->jsonError('Hanya menerima metode QRIS', 400);
            }

            $result = $this->qris->checkStatus($ref_id, $nominal);

            if ($result['connection_error']) {
                $this->jsonError('Gagal cek status ke payment gateway: ' . $result['message'], 500);
            }

            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'trx_id' => $result['trx_id'],
                'ref_id' => $result['trx_id'],
                'payment_status' => $result['payment_status'],
                'trx_status' => $result['trx_status'],
                'gateway' => $result['gateway'],
            ]);
            exit;
        } catch (\Exception $e) {
            $this->jsonError('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

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

    public function withdraw()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }

        try {
            $body = $this->getBody();
            $nominal = isset($body['nominal']) ? (int) $body['nominal'] : 0;

            if ($nominal < 10000) {
                $this->error('Minimal penarikan Rp 10.000', 400);
            }

            $tokopay = new Tokopay();
            $response = $tokopay->tarikSaldo($nominal);
            $data = json_decode($response, true);

            if (isset($data['status']) && $data['status'] === false && isset($data['message'])) {
                $this->error('Gagal tarik saldo ke TokoPay: ' . $data['message'], 500);
            }

            $this->success($data, 'Permintaan penarikan saldo berhasil dikirim');
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    private function jsonError(string $message, int $code): void
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode(['status' => false, 'message' => $message]);
        exit;
    }
}
