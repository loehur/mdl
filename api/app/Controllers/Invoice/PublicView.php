<?php

namespace App\Controllers\Invoice;

use App\Models\Tokopay;

/**
 * Public invoice — tanpa autentikasi.
 * GET  /Invoice/PublicView/view?token=
 * POST /Invoice/PublicView/pay
 * GET  /Invoice/PublicView/checkPayment?payment_ref=
 */
class PublicView extends InvoiceController
{
    public function view()
    {
        $token = trim($_GET['token'] ?? '');
        if ($token === '') {
            $this->error('Token tidak valid', 400);
        }

        try {
            $invoice = $this->db($this->db_index)->query(
                "SELECT i.*, u.name AS issuer_name, u.business_name, u.business_phone, u.business_address
                 FROM invoices i
                 INNER JOIN users u ON u.id = i.user_id
                 WHERE i.public_token = ? AND i.status != 'cancelled'
                 LIMIT 1",
                [$token]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            $items = $this->getInvoiceItems((int) $invoice['id']);

            $issuer = [
                'name' => $invoice['business_name'] ?: $invoice['issuer_name'],
                'phone' => $invoice['business_phone'] ?? '',
                'address' => $invoice['business_address'] ?? '',
            ];

            unset($invoice['issuer_name'], $invoice['business_name'], $invoice['business_phone'], $invoice['business_address']);

            $data = $this->formatInvoice($invoice, $items, $issuer);
            $data['can_pay'] = $invoice['payment_status'] !== 'paid';

            $this->success($data, 'Invoice publik');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat invoice: ' . $e->getMessage(), 500);
        }
    }

    public function pay()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $token = trim($body['token'] ?? '');

            if ($token === '') {
                $this->error('Token tidak valid', 400);
            }

            $invoice = $this->db($this->db_index)->query(
                "SELECT * FROM invoices WHERE public_token = ? AND status != 'cancelled' LIMIT 1",
                [$token]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            if ($invoice['payment_status'] === 'paid') {
                $this->error('Invoice sudah dibayar', 400);
            }

            $invoiceId = (int) $invoice['id'];
            $amount = (int) round((float) $invoice['total']);

            $pending = $this->db($this->db_index)->query(
                "SELECT * FROM invoice_payments
                 WHERE invoice_id = ? AND payment_status = 'pending'
                 ORDER BY id DESC LIMIT 1",
                [$invoiceId]
            )->row_array();

            if ($pending) {
                $createdAt = strtotime($pending['created_at']);
                if (time() - $createdAt < 300 && !empty($pending['qr_string'])) {
                    $this->success([
                        'payment_ref' => $pending['payment_ref'],
                        'amount' => (float) $pending['amount'],
                        'qr_string' => $pending['qr_string'],
                        'invoice_number' => $invoice['invoice_number'],
                    ], 'Melanjutkan pembayaran tertunda');
                    return;
                }

                $this->db($this->db_index)->update('invoice_payments', [
                    'payment_status' => 'expired',
                ], ['id' => $pending['id']]);
            }

            $paymentRef = 'MDLINV_' . $invoiceId . '_' . time();

            $this->db($this->db_index)->insert('invoice_payments', [
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_method' => 'qris',
                'payment_ref' => $paymentRef,
                'payment_status' => 'pending',
            ]);

            $this->db($this->db_index)->update('invoices', [
                'payment_status' => 'pending',
            ], ['id' => $invoiceId]);

            $tokopay = new Tokopay();
            $response = $tokopay->createOrder($amount, $paymentRef, 'QRIS');
            $data = json_decode($response, true);

            if (!$this->isTokopaySuccess($data)) {
                $errorMsg = $data['message'] ?? 'Gagal membuat QRIS';
                $this->db($this->db_index)->update('invoice_payments', [
                    'payment_status' => 'failed',
                ], ['payment_ref' => $paymentRef]);

                $this->error('Tokopay Error: ' . $errorMsg, 500);
            }

            $qrString = $this->extractQrString($data);
            if ($qrString === '') {
                $this->error('QR String tidak ditemukan dari Tokopay', 500);
            }

            $this->db($this->db_index)->update('invoice_payments', [
                'qr_string' => $qrString,
                'trx_id' => $paymentRef,
            ], ['payment_ref' => $paymentRef]);

            $this->success([
                'payment_ref' => $paymentRef,
                'amount' => $amount,
                'qr_string' => $qrString,
                'invoice_number' => $invoice['invoice_number'],
            ], 'Scan QRIS untuk membayar Rp ' . number_format($amount, 0, ',', '.'));
        } catch (\Throwable $e) {
            $this->error('Gagal membuat pembayaran: ' . $e->getMessage(), 500);
        }
    }

    public function checkPayment()
    {
        $paymentRef = trim($_GET['payment_ref'] ?? '');
        if ($paymentRef === '') {
            $this->error('payment_ref diperlukan', 400);
        }

        try {
            $payment = $this->db($this->db_index)->query(
                "SELECT p.*, i.invoice_number, i.public_token
                 FROM invoice_payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 WHERE p.payment_ref = ?
                 LIMIT 1",
                [$paymentRef]
            )->row_array();

            if (!$payment) {
                $this->error('Pembayaran tidak ditemukan', 404);
            }

            if ($payment['payment_status'] === 'success') {
                $this->success([
                    'payment_status' => 'paid',
                    'invoice_number' => $payment['invoice_number'],
                    'amount' => (float) $payment['amount'],
                ], 'Pembayaran berhasil');
                return;
            }

            $amount = (int) round((float) $payment['amount']);
            $tokopay = new Tokopay();
            $response = $tokopay->checkStatus($paymentRef, $amount, 'QRIS');
            $data = json_decode($response, true);

            $status = $this->parsePaymentStatus($data);

            if ($status === 'paid') {
                $this->markInvoicePaid((int) $payment['invoice_id'], $paymentRef);

                $this->success([
                    'payment_status' => 'paid',
                    'invoice_number' => $payment['invoice_number'],
                    'amount' => (float) $payment['amount'],
                ], 'Pembayaran berhasil');
                return;
            }

            if ($status === 'expired') {
                $this->db($this->db_index)->update('invoice_payments', [
                    'payment_status' => 'expired',
                ], ['payment_ref' => $paymentRef]);

                $this->db($this->db_index)->update('invoices', [
                    'payment_status' => 'unpaid',
                ], ['id' => $payment['invoice_id']]);

                $this->success([
                    'payment_status' => 'expired',
                ], 'Pembayaran kadaluarsa');
                return;
            }

            $this->success([
                'payment_status' => 'pending',
            ], 'Menunggu pembayaran');
        } catch (\Throwable $e) {
            $this->error('Gagal cek pembayaran: ' . $e->getMessage(), 500);
        }
    }
}
