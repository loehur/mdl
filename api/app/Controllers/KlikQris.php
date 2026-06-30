<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\KlikQris as KlikQrisModel;

/**
 * KlikQRIS Controller
 * Reference: https://klikqris.com/dokumentasi-api
 */
class KlikQris extends Controller
{
    /**
     * Create QRIS transaction
     * Endpoint: POST /KlikQris/create
     *
     * Body (JSON):
     *   - amount (int, required): Nominal pembayaran
     *   - order_id (string, optional): ID unik transaksi, default INV-{timestamp}
     *   - keterangan (string, optional): Catatan transaksi
     *   - callback_url (string, optional): URL webhook khusus transaksi ini
     *   - sandbox (bool, optional): true = pakai API sandbox KlikQRIS
     */
    public function create()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }

        try {
            $body = $this->getBody();

            $amount = isset($body['amount']) ? (int) $body['amount'] : 0;
            $orderId = isset($body['order_id']) ? trim((string) $body['order_id']) : '';
            $keterangan = isset($body['keterangan']) ? trim((string) $body['keterangan']) : null;
            $callbackUrl = isset($body['callback_url']) ? trim((string) $body['callback_url']) : null;
            $sandbox = !empty($body['sandbox']);

            if ($amount <= 0) {
                $this->error('amount wajib diisi dan minimal 1', 400);
            }

            if ($orderId === '') {
                $orderId = 'INV-' . time();
            }

            $klikQris = new KlikQrisModel();
            $result = $klikQris->createTransaction($orderId, $amount, $keterangan, $callbackUrl, $sandbox);

            $httpCode = $result['http_code'] ?: 502;
            $this->json($result, $httpCode >= 100 && $httpCode < 600 ? $httpCode : 200);
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }
}
