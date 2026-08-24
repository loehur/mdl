<?php

namespace App\Controllers\Invoice;

use App\Helpers\Payment\BankAccountGuide;
use App\Helpers\Payment\BcaUniqueNominal;
use App\Helpers\Payment\QrisService;

/**
 * Public invoice — tanpa autentikasi.
 * GET  /Invoice/PublicView/view?token=
 * POST /Invoice/PublicView/pay  { token, payment_method: qris|bca }
 * POST /Invoice/PublicView/cancelPayment { token, payment_ref }
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
                'name' => $this->resolveIssuerName($invoice['business_name'] ?? null),
                'phone' => $invoice['business_phone'] ?? '',
                'address' => $invoice['business_address'] ?? '',
            ];

            unset($invoice['issuer_name'], $invoice['business_name'], $invoice['business_phone'], $invoice['business_address']);

            $data = $this->formatInvoice($invoice, $items, $issuer);
            $data['can_pay'] = $invoice['payment_status'] !== 'paid';

            $pendingPayment = $this->db($this->db_index)->query(
                "SELECT payment_ref, payment_method, amount, base_amount, payment_status, created_at
                 FROM invoice_payments
                 WHERE invoice_id = ? AND payment_status = 'pending'
                 ORDER BY id DESC LIMIT 1",
                [(int) $invoice['id']]
            )->row_array();
            $data['pending_payment'] = is_array($pendingPayment) ? [
                'payment_ref' => $pendingPayment['payment_ref'],
                'payment_method' => $pendingPayment['payment_method'] ?? 'qris',
                'amount' => (float) ($pendingPayment['amount'] ?? 0),
                'base_amount' => (float) ($pendingPayment['base_amount'] ?? $pendingPayment['amount'] ?? 0),
            ] : null;

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
            $token = $this->strFromBody($body, 'token');
            $paymentMethod = strtolower($this->strFromBody($body, 'payment_method', 'qris'));
            if (!in_array($paymentMethod, ['qris', 'bca'], true)) {
                $paymentMethod = 'qris';
            }

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
            $baseAmount = (int) round((float) $invoice['total']);

            $pending = $this->db($this->db_index)->query(
                "SELECT * FROM invoice_payments
                 WHERE invoice_id = ? AND payment_status = 'pending'
                 ORDER BY id DESC LIMIT 1",
                [$invoiceId]
            )->row_array();

            if ($pending) {
                $pendingMethod = strtolower(trim((string) ($pending['payment_method'] ?? 'qris')));
                $createdAt = strtotime($pending['created_at']);
                $ageSec = time() - ($createdAt ?: 0);
                $withinWindow = $ageSec >= 0 && $ageSec < (BcaUniqueNominal::LOOKBACK_DAYS * 86400);

                if ($withinWindow && $pendingMethod === $paymentMethod) {
                    if ($paymentMethod === 'qris' && $ageSec < 300 && !empty($pending['qr_string'])) {
                        $this->success([
                            'payment_ref' => $pending['payment_ref'],
                            'payment_method' => 'qris',
                            'amount' => (float) $pending['amount'],
                            'base_amount' => (float) ($pending['base_amount'] ?? $pending['amount']),
                            'qr_string' => $pending['qr_string'],
                            'invoice_number' => $invoice['invoice_number'],
                        ], 'Melanjutkan pembayaran tertunda');
                        return;
                    }

                    if ($paymentMethod === 'bca') {
                        $this->success($this->buildBcaPayResponse($pending, $invoice), 'Melanjutkan pembayaran BCA tertunda');
                        return;
                    }
                }

                if ($pendingMethod !== $paymentMethod) {
                    $this->error(
                        'Masih ada pembayaran pending dengan metode ' . strtoupper($pendingMethod) . '. Batalkan dulu sebelum ganti metode.',
                        409,
                        ['pending_payment_ref' => $pending['payment_ref'], 'pending_payment_method' => $pendingMethod]
                    );
                }

                $this->db($this->db_index)->update('invoice_payments', [
                    'payment_status' => 'expired',
                ], ['id' => $pending['id']]);
            }

            if ($paymentMethod === 'bca') {
                $this->createBcaPayment($invoice, $baseAmount);
                return;
            }

            $this->createQrisPayment($invoice, $baseAmount);
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
                    'payment_method' => $payment['payment_method'] ?? 'qris',
                    'invoice_number' => $payment['invoice_number'],
                    'amount' => (float) $payment['amount'],
                ], 'Pembayaran berhasil');
                return;
            }

            $method = strtolower(trim((string) ($payment['payment_method'] ?? 'qris')));
            if ($method === 'bca') {
                if (in_array($payment['payment_status'], ['expired', 'failed'], true)) {
                    $this->success(['payment_status' => 'expired'], 'Pembayaran kadaluarsa');
                    return;
                }

                $this->success(['payment_status' => 'pending'], 'Menunggu transfer BCA');
                return;
            }

            $amount = (int) round((float) $payment['amount']);
            $qris = new QrisService();
            $checked = $qris->checkStatus($paymentRef, $amount);

            if ($checked['connection_error']) {
                $this->error('Gagal cek pembayaran: ' . $checked['message'], 500);
            }

            $status = $checked['payment_status'];

            if ($status === 'paid') {
                $this->markInvoicePaid((int) $payment['invoice_id'], $paymentRef);

                $this->success([
                    'payment_status' => 'paid',
                    'payment_method' => 'qris',
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

    /**
     * POST — batalkan pembayaran pending (ganti metode / tutup modal).
     * Body: { token, payment_ref }
     */
    public function cancelPayment()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $token = $this->strFromBody($body, 'token');
            $paymentRef = $this->strFromBody($body, 'payment_ref');

            if ($token === '' || $paymentRef === '') {
                $this->error('Token dan payment_ref diperlukan', 400);
            }

            $invoice = $this->db($this->db_index)->query(
                "SELECT * FROM invoices WHERE public_token = ? AND status != 'cancelled' LIMIT 1",
                [$token]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            if ($invoice['payment_status'] === 'paid') {
                $this->error('Invoice sudah lunas', 400);
            }

            $payment = $this->db($this->db_index)->query(
                "SELECT * FROM invoice_payments
                 WHERE payment_ref = ? AND invoice_id = ?
                 LIMIT 1",
                [$paymentRef, (int) $invoice['id']]
            )->row_array();

            if (!$payment) {
                $this->error('Pembayaran tidak ditemukan', 404);
            }

            if ($payment['payment_status'] === 'success') {
                $this->error('Pembayaran sudah berhasil, tidak dapat dibatalkan', 400);
            }

            if ($payment['payment_status'] !== 'pending') {
                $this->error('Hanya pembayaran pending yang dapat dibatalkan', 400);
            }

            $alreadyPaid = $this->resolvePaymentPaidBeforeCancel($payment);
            if ($alreadyPaid) {
                $this->markInvoicePaid((int) $invoice['id'], $paymentRef);
                $this->error('Pembayaran sudah berhasil diverifikasi', 400, [
                    'payment_status' => 'paid',
                ]);
            }

            $this->db($this->db_index)->update('invoice_payments', [
                'payment_status' => 'failed',
            ], ['id' => (int) $payment['id']]);

            $this->syncInvoicePaymentStatus((int) $invoice['id']);

            $this->success([
                'payment_ref' => $paymentRef,
                'payment_status' => 'cancelled',
            ], 'Pembayaran dibatalkan. Anda dapat memilih metode bayar lain.');
        } catch (\Throwable $e) {
            $this->error('Gagal membatalkan pembayaran: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Pastikan pembayaran pending belum lunas di gateway sebelum dibatalkan.
     *
     * @param array<string,mixed> $payment
     */
    private function resolvePaymentPaidBeforeCancel(array $payment): bool
    {
        if (($payment['payment_status'] ?? '') === 'success') {
            return true;
        }

        $method = strtolower(trim((string) ($payment['payment_method'] ?? 'qris')));
        if ($method === 'bca') {
            return false;
        }

        $trxId = trim((string) ($payment['trx_id'] ?? ''));
        $qrString = trim((string) ($payment['qr_string'] ?? ''));
        if ($trxId === '' && $qrString === '') {
            // Transfer BCA / non-QRIS — tidak ada order di Tokopay
            return false;
        }

        $paymentRef = trim((string) ($payment['payment_ref'] ?? ''));
        $amount = (int) round((float) ($payment['amount'] ?? 0));
        if ($paymentRef === '' || $amount < 1) {
            return false;
        }

        $qris = new QrisService();
        $checked = $qris->checkStatus($paymentRef, $amount);
        if (!empty($checked['connection_error'])) {
            return false;
        }

        return ($checked['payment_status'] ?? '') === 'paid';
    }

    private function syncInvoicePaymentStatus(int $invoiceId): void
    {
        $stillPending = $this->db($this->db_index)->query(
            "SELECT id FROM invoice_payments
             WHERE invoice_id = ? AND payment_status = 'pending'
             LIMIT 1",
            [$invoiceId]
        )->row_array();

        if (!empty($stillPending['id'])) {
            return;
        }

        $this->db($this->db_index)->update('invoices', [
            'payment_status' => 'unpaid',
        ], ['id' => $invoiceId]);
    }

    /**
     * @param array<string,mixed> $invoice
     */
    private function createQrisPayment(array $invoice, int $baseAmount): void
    {
        $invoiceId = (int) $invoice['id'];
        $paymentRef = 'MDLINV_' . $invoiceId . '_' . time();

        $insert = [
            'invoice_id' => $invoiceId,
            'amount' => $baseAmount,
            'payment_method' => 'qris',
            'payment_ref' => $paymentRef,
            'payment_status' => 'pending',
        ];
        $this->tryInsertBaseAmount($insert, $baseAmount);
        $this->db($this->db_index)->insert('invoice_payments', $insert);

        $this->db($this->db_index)->update('invoices', [
            'payment_status' => 'pending',
        ], ['id' => $invoiceId]);

        $qris = new QrisService();
        $order = $qris->generate($baseAmount, $paymentRef, false);

        if ($order['failed'] || !$order['status']) {
            $this->db($this->db_index)->update('invoice_payments', [
                'payment_status' => 'failed',
            ], ['payment_ref' => $paymentRef]);

            $this->error('QRIS Error: ' . ($order['message'] ?? 'Gagal membuat QRIS'), 500);
        }

        $qrString = $order['qr_string'];

        $this->db($this->db_index)->update('invoice_payments', [
            'qr_string' => $qrString,
            'trx_id' => $order['trx_id'],
        ], ['payment_ref' => $paymentRef]);

        $this->success([
            'payment_ref' => $paymentRef,
            'payment_method' => 'qris',
            'amount' => $baseAmount,
            'base_amount' => $baseAmount,
            'qr_string' => $qrString,
            'invoice_number' => $invoice['invoice_number'],
        ], 'Scan QRIS untuk membayar Rp ' . number_format($baseAmount, 0, ',', '.'));
    }

    /**
     * @param array<string,mixed> $invoice
     */
    private function createBcaPayment(array $invoice, int $baseAmount): void
    {
        $invoiceId = (int) $invoice['id'];
        $uniqueAmount = BcaUniqueNominal::allocate(
            $baseAmount,
            $this->db($this->db_index),
            $this->db(4),
            $this->db(1)
        );

        $paymentRef = 'MDLINV_' . $invoiceId . '_' . time();

        $insert = [
            'invoice_id' => $invoiceId,
            'amount' => $uniqueAmount,
            'payment_method' => 'bca',
            'payment_ref' => $paymentRef,
            'payment_status' => 'pending',
        ];
        $this->tryInsertBaseAmount($insert, $baseAmount);
        $this->db($this->db_index)->insert('invoice_payments', $insert);

        $this->db($this->db_index)->update('invoices', [
            'payment_status' => 'pending',
        ], ['id' => $invoiceId]);

        $pending = $this->db($this->db_index)->query(
            'SELECT * FROM invoice_payments WHERE payment_ref = ? LIMIT 1',
            [$paymentRef]
        )->row_array();

        $this->success(
            $this->buildBcaPayResponse(is_array($pending) ? $pending : $insert, $invoice),
            'Transfer BCA Rp ' . number_format($uniqueAmount, 0, ',', '.') . ' (exact)'
        );
    }

    /**
     * @param array<string,mixed> $payment
     * @param array<string,mixed> $invoice
     * @return array<string,mixed>
     */
    private function buildBcaPayResponse(array $payment, array $invoice): array
    {
        $bca = BankAccountGuide::bcaAccount();
        $amount = (float) ($payment['amount'] ?? 0);
        $baseAmount = (float) ($payment['base_amount'] ?? $amount);

        return [
            'payment_ref' => $payment['payment_ref'] ?? '',
            'payment_method' => 'bca',
            'amount' => $amount,
            'base_amount' => $baseAmount,
            'unique_nominal' => $amount !== $baseAmount,
            'invoice_number' => $invoice['invoice_number'] ?? '',
            'bank_account' => $bca,
            'bank_message' => BankAccountGuide::bcaTransferMessage(),
        ];
    }

    /**
     * @param array<string,mixed> $insert
     */
    private function tryInsertBaseAmount(array &$insert, int $baseAmount): void
    {
        try {
            $this->db($this->db_index)->query('SELECT base_amount FROM invoice_payments LIMIT 1');
            $insert['base_amount'] = $baseAmount;
        } catch (\Throwable $e) {
            // column belum dimigrate
        }
    }
}
