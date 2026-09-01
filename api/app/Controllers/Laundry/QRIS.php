<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Payment\QrisService;

/**
 * QRIS Controller — endpoint HTTP untuk generate/cek QRIS.
 * Logika gateway terpusat di App\Helpers\Payment\QrisService.
 * URL: /Laundry/QRIS/{method}
 */
class QRIS extends Controller
{
    /** @var QrisService|null */
    private $qris;

    private function qrisService(): QrisService
    {
        if ($this->qris === null) {
            $this->qris = new QrisService();
        }

        return $this->qris;
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

            $result = $this->qrisService()->generate($nominal, $ref_id, true);

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
                'amount' => (int) ($result['amount'] ?? $nominal),
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

            $result = $this->qrisService()->checkStatus($ref_id, $nominal);

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

    /**
     * Endpoint lama Tokopay tidak dipakai oleh QRIS lokal.
     * GET /Laundry/QRIS/balance
     */
    public function balance()
    {
        $this->handleCors();

        if (!$this->isGet()) {
            $this->error('Method not allowed. Use GET', 405);
        }

        $this->error('Saldo tidak tersedia: QRIS lokal dikonfirmasi melalui mutasi BCA', 410);
    }

    /**
     * Withdraw balance (tarik saldo)
     * POST /Laundry/QRIS/withdraw
     */
    public function withdraw()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }

        $this->error('Penarikan Tokopay tidak tersedia pada QRIS lokal', 410);
    }

    private function jsonError(string $message, int $code): void
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode(['status' => false, 'message' => $message]);
        exit;
    }
}
