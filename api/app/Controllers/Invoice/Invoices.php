<?php

namespace App\Controllers\Invoice;

class Invoices extends InvoiceController
{
    public function list()
    {
        $this->verifyAuth();
        $userId = (int) $this->currentUser()['id'];

        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Format month tidak valid (YYYY-MM)', 400);
        }

        $status = trim($_GET['status'] ?? '');

        try {
            $sql = "SELECT id, invoice_number, public_token, title, customer_name, total,
                           payment_status, status, issue_date, due_date, created_at
                    FROM invoices
                    WHERE user_id = ? AND DATE_FORMAT(issue_date, '%Y-%m') = ?";
            $bind = [$userId, $month];

            if ($status === 'paid') {
                $sql .= " AND payment_status = 'paid'";
            } elseif ($status === 'unpaid') {
                $sql .= " AND payment_status != 'paid' AND status != 'cancelled'";
            } elseif ($status === 'cancelled') {
                $sql .= " AND status = 'cancelled'";
            }

            $sql .= " ORDER BY created_at DESC";

            $rows = $this->db($this->db_index)->query($sql, $bind)->result_array();

            $this->success([
                'month' => $month,
                'invoices' => $rows,
            ], 'Daftar invoice');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat invoice: ' . $e->getMessage(), 500);
        }
    }

    public function detail()
    {
        $this->verifyAuth();
        $userId = (int) $this->currentUser()['id'];
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID invoice tidak valid', 400);
        }

        try {
            $invoice = $this->db($this->db_index)->query(
                "SELECT * FROM invoices WHERE id = ? AND user_id = ? LIMIT 1",
                [$id, $userId]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            $items = $this->getInvoiceItems($id);
            $user = $this->currentUser();
            $publicUrl = $this->buildPublicUrl($invoice['public_token']);

            $issuer = $this->buildIssuerPayload($user);
            $data = $this->formatInvoice($invoice, $items, $issuer);
            $data['public_url'] = $publicUrl;
            $data['share_text'] = $this->buildShareText($invoice, $publicUrl, $issuer['name']);
            $data['recurring'] = $this->getRecurringInfoForInvoice($invoice);

            $this->success($data, 'Detail invoice');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat detail: ' . $e->getMessage(), 500);
        }
    }

    public function create()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];

        try {
            $body = $this->getBody();
            $parsed = $this->parseInvoiceBody($body, $userId);

            $invoiceNumber = $this->generateInvoiceNumber($userId);
            $publicToken = $this->generatePublicToken();

            $invoiceId = (int) $this->db($this->db_index)->insert('invoices', [
                'user_id' => $userId,
                'customer_id' => $parsed['customer_id'],
                'invoice_number' => $invoiceNumber,
                'public_token' => $publicToken,
                'customer_name' => $parsed['customer_name'],
                'customer_email' => $parsed['customer_email'],
                'customer_phone' => $parsed['customer_phone'],
                'title' => $parsed['title'],
                'issue_date' => $parsed['issue_date'],
                'due_date' => $parsed['due_date'],
                'subtotal' => $parsed['subtotal'],
                'tax_percent' => $parsed['tax_percent'],
                'tax_amount' => $parsed['tax_amount'],
                'total' => $parsed['total'],
                'exchange_rate' => $parsed['exchange_rate'],
                'total_usd' => $parsed['total_usd'],
                'notes' => $parsed['notes'],
                'status' => 'sent',
                'payment_status' => 'unpaid',
            ]);

            if ($invoiceId <= 0) {
                $this->error('Gagal menyimpan invoice ke database', 500);
            }

            foreach ($parsed['items'] as $item) {
                $this->db($this->db_index)->insert('invoice_items', $this->invoiceItemRowForDb($item, $invoiceId));
            }

            $recurringInfo = $this->syncRecurringBill(
                $userId,
                $invoiceId,
                $parsed,
                is_array($body['recurring'] ?? null) ? $body['recurring'] : null
            );

            $invoice = $this->db($this->db_index)->get_where('invoices', ['id' => $invoiceId], 1)->row_array();
            $items = $this->getInvoiceItems($invoiceId);
            $user = $this->currentUser();
            $publicUrl = $this->buildPublicUrl($publicToken);

            $issuer = $this->buildIssuerPayload($user);
            $data = $this->formatInvoice($invoice, $items, $issuer);
            $data['public_url'] = $publicUrl;
            $data['share_text'] = $this->buildShareText($invoice, $publicUrl, $issuer['name']);
            $data['recurring'] = $recurringInfo;

            $this->success($data, 'Invoice berhasil dibuat');
        } catch (\Throwable $e) {
            $this->error('Gagal membuat invoice: ' . $e->getMessage(), 500);
        }
    }

    public function update()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];

        try {
            $body = $this->getBody();
            $id = (int) ($body['id'] ?? 0);

            if ($id <= 0) {
                $this->error('ID invoice tidak valid', 400);
            }

            $invoice = $this->db($this->db_index)->query(
                "SELECT * FROM invoices WHERE id = ? AND user_id = ? LIMIT 1",
                [$id, $userId]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            if ($invoice['payment_status'] === 'paid') {
                $this->error('Invoice yang sudah dibayar tidak dapat diedit', 400);
            }

            if ($invoice['status'] === 'cancelled') {
                $this->error('Invoice yang sudah dibatalkan tidak dapat diedit', 400);
            }

            $parsed = $this->parseInvoiceBody($body, $userId);

            $updateData = [
                'customer_id' => $parsed['customer_id'],
                'customer_name' => $parsed['customer_name'],
                'customer_email' => $parsed['customer_email'],
                'customer_phone' => $parsed['customer_phone'],
                'title' => $parsed['title'],
                'issue_date' => $parsed['issue_date'],
                'due_date' => $parsed['due_date'],
                'subtotal' => $parsed['subtotal'],
                'tax_percent' => $parsed['tax_percent'],
                'tax_amount' => $parsed['tax_amount'],
                'total' => $parsed['total'],
                'exchange_rate' => $parsed['exchange_rate'],
                'total_usd' => $parsed['total_usd'],
                'notes' => $parsed['notes'],
                'payment_status' => 'unpaid',
            ];

            $this->db($this->db_index)->update('invoices', $updateData, ['id' => $id]);

            if ($invoice['payment_status'] === 'pending') {
                $this->db($this->db_index)->update('invoice_payments', [
                    'payment_status' => 'expired',
                ], ['invoice_id' => $id, 'payment_status' => 'pending']);
            }

            $this->db($this->db_index)->delete('invoice_items', ['invoice_id' => $id]);

            foreach ($parsed['items'] as $item) {
                $this->db($this->db_index)->insert('invoice_items', $this->invoiceItemRowForDb($item, $id));
            }

            $recurringInfo = $this->syncRecurringBill(
                $userId,
                $id,
                $parsed,
                is_array($body['recurring'] ?? null) ? $body['recurring'] : null
            );

            $updated = $this->db($this->db_index)->get_where('invoices', ['id' => $id], 1)->row_array();
            $items = $this->getInvoiceItems($id);
            $user = $this->currentUser();
            $publicUrl = $this->buildPublicUrl($updated['public_token']);

            $issuer = $this->buildIssuerPayload($user);
            $data = $this->formatInvoice($updated, $items, $issuer);
            $data['public_url'] = $publicUrl;
            $data['share_text'] = $this->buildShareText($updated, $publicUrl, $issuer['name']);
            $data['recurring'] = $recurringInfo;

            $this->success($data, 'Invoice berhasil diperbarui');
        } catch (\Throwable $e) {
            $this->error('Gagal memperbarui invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Hubungkan invoice lama (tanpa customer_id) ke master pelanggan
     */
    public function setCustomer()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];
        $body = $this->getBody();
        $id = (int) ($body['id'] ?? 0);
        $customerId = (int) ($body['customer_id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID invoice tidak valid', 400);
        }

        if ($customerId <= 0) {
            $this->error('Pelanggan wajib dipilih', 400);
        }

        try {
            $invoice = $this->db($this->db_index)->query(
                "SELECT * FROM invoices WHERE id = ? AND user_id = ? LIMIT 1",
                [$id, $userId]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            if (!empty($invoice['customer_id'])) {
                $this->error('Invoice ini sudah terhubung ke pelanggan', 400);
            }

            $customer = $this->db($this->db_index)->query(
                "SELECT id, name, phone, email FROM customers WHERE id = ? AND user_id = ? LIMIT 1",
                [$customerId, $userId]
            )->row_array();

            if (!$customer) {
                $this->error('Pelanggan tidak ditemukan', 404);
            }

            $this->db($this->db_index)->update('invoices', [
                'customer_id' => (int) $customer['id'],
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'] ?: null,
                'customer_phone' => $customer['phone'] ?: null,
            ], ['id' => $id]);

            $updated = $this->db($this->db_index)->get_where('invoices', ['id' => $id], 1)->row_array();
            $items = $this->getInvoiceItems($id);
            $user = $this->currentUser();
            $publicUrl = $this->buildPublicUrl($updated['public_token']);

            $issuer = $this->buildIssuerPayload($user);
            $data = $this->formatInvoice($updated, $items, $issuer);
            $data['public_url'] = $publicUrl;
            $data['share_text'] = $this->buildShareText($updated, $publicUrl, $issuer['name']);

            $this->success($data, 'Pelanggan berhasil dihubungkan ke invoice');
        } catch (\Throwable $e) {
            $this->error('Gagal menghubungkan pelanggan: ' . $e->getMessage(), 500);
        }
    }

    public function cancel()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];
        $body = $this->getBody();
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID invoice tidak valid', 400);
        }

        try {
            $invoice = $this->db($this->db_index)->query(
                "SELECT id, payment_status FROM invoices WHERE id = ? AND user_id = ? LIMIT 1",
                [$id, $userId]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            if ($invoice['payment_status'] === 'paid') {
                $this->error('Invoice yang sudah dibayar tidak dapat dibatalkan', 400);
            }

            $this->db($this->db_index)->update('invoices', [
                'status' => 'cancelled',
            ], ['id' => $id]);

            $this->db($this->db_index)->update('invoice_payments', [
                'payment_status' => 'expired',
            ], ['invoice_id' => $id, 'payment_status' => 'pending']);

            $this->success(null, 'Invoice dibatalkan');
        } catch (\Throwable $e) {
            $this->error('Gagal membatalkan invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Tandai invoice lunas secara manual (tanpa menunggu pembayaran client)
     */
    public function markPaid()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];
        $body = $this->getBody();
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID invoice tidak valid', 400);
        }

        try {
            $invoice = $this->db($this->db_index)->query(
                "SELECT id, total, payment_status, status FROM invoices WHERE id = ? AND user_id = ? LIMIT 1",
                [$id, $userId]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            if ($invoice['status'] === 'cancelled') {
                $this->error('Invoice yang dibatalkan tidak dapat ditandai lunas', 400);
            }

            if ($invoice['payment_status'] === 'paid') {
                $this->error('Invoice sudah lunas', 400);
            }

            // Batalkan QRIS/pembayaran pending agar tidak bisa dibayar ganda
            $this->db($this->db_index)->update('invoice_payments', [
                'payment_status' => 'expired',
            ], ['invoice_id' => $id, 'payment_status' => 'pending']);

            $paymentRef = 'MDLINV_' . $id . '_MANUAL_' . time();
            $now = date('Y-m-d H:i:s');

            $this->db($this->db_index)->insert('invoice_payments', [
                'invoice_id' => $id,
                'amount' => $invoice['total'],
                'payment_method' => 'manual',
                'payment_ref' => $paymentRef,
                'payment_status' => 'success',
                'paid_at' => $now,
            ]);

            $this->markInvoicePaid($id, $paymentRef);

            $this->success([
                'id' => $id,
                'payment_ref' => $paymentRef,
                'payment_status' => 'paid',
            ], 'Invoice berhasil ditandai lunas');
        } catch (\Throwable $e) {
            $this->error('Gagal menandai lunas: ' . $e->getMessage(), 500);
        }
    }

    public function delete()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];
        $body = $this->getBody();
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID invoice tidak valid', 400);
        }

        try {
            $invoice = $this->db($this->db_index)->query(
                "SELECT id, status FROM invoices WHERE id = ? AND user_id = ? LIMIT 1",
                [$id, $userId]
            )->row_array();

            if (!$invoice) {
                $this->error('Invoice tidak ditemukan', 404);
            }

            if ($invoice['status'] !== 'cancelled') {
                $this->error('Hanya invoice yang sudah dibatalkan yang dapat dihapus', 400);
            }

            $this->db($this->db_index)->delete('invoices', ['id' => $id]);

            $this->success(null, 'Invoice berhasil dihapus');
        } catch (\Throwable $e) {
            $this->error('Gagal menghapus invoice: ' . $e->getMessage(), 500);
        }
    }

    protected function buildPublicUrl(string $token): string
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin && $this->isAllowedPublicOrigin($origin)) {
            return rtrim($origin, '/') . '/#/i/' . $token;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer) {
            $parsed = parse_url($referer);
            $refHost = strtolower($parsed['host'] ?? '');
            if ($refHost === 'localhost' || str_ends_with($refHost, '.nalju.com') || $refHost === 'nalju.com') {
                $scheme = $parsed['scheme'] ?? 'http';
                $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                $path = rtrim(dirname($parsed['path'] ?? '/'), '/');
                return "{$scheme}://{$refHost}{$port}{$path}/#/i/{$token}";
            }
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        if (str_contains($host, 'api.')) {
            $host = str_replace('api.', 'invoice.', $host);
        }

        return "{$scheme}://{$host}/mdl/public/invoice/#/i/{$token}";
    }

    protected function isAllowedPublicOrigin(string $origin): bool
    {
        $parsed = parse_url($origin);
        $host = strtolower($parsed['host'] ?? '');

        return $host === 'localhost'
            || $host === 'nalju.com'
            || str_ends_with($host, '.nalju.com');
    }

    /** @return array<string, mixed> */
    protected function parseInvoiceBody(array $body, int $userId): array
    {
        $this->validate($body, ['customer_id', 'title', 'items']);

        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            $this->error('Judul invoice wajib diisi', 400);
        }

        $customerId = (int) ($body['customer_id'] ?? 0);
        if ($customerId <= 0) {
            $this->error('Pelanggan wajib dipilih', 400);
        }

        $customer = $this->db($this->db_index)->query(
            "SELECT id, name, phone, email FROM customers WHERE id = ? AND user_id = ? LIMIT 1",
            [$customerId, $userId]
        )->row_array();

        if (!$customer) {
            $this->error('Pelanggan tidak ditemukan', 404);
        }

        if (!is_array($body['items']) || count($body['items']) === 0) {
            $this->error('Minimal 1 item invoice', 400);
        }

        $issueDate = $body['issue_date'] ?? date('Y-m-d');
        $dueDate = $body['due_date'] ?? null;
        $taxPercent = round((float) ($body['tax_percent'] ?? 0), 2);

        if ($taxPercent < 0 || $taxPercent > 100) {
            $this->error('Pajak tidak valid (0-100%)', 400);
        }

        try {
            $converted = $this->convertInvoiceItems($body['items']);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 502);
        }

        $taxAmount = round($converted['subtotal'] * ($taxPercent / 100), 2);
        $total = round($converted['subtotal'] + $taxAmount, 2);

        return [
            'customer_id' => (int) $customer['id'],
            'customer_name' => $customer['name'],
            'customer_email' => $customer['email'] ?: null,
            'customer_phone' => $customer['phone'] ?: null,
            'title' => $title,
            'issue_date' => $issueDate,
            'due_date' => $dueDate ?: null,
            'subtotal' => $converted['subtotal'],
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'exchange_rate' => $converted['exchange_rate'],
            'total_usd' => $converted['total_usd'],
            'notes' => trim($body['notes'] ?? '') ?: null,
            'items' => $converted['items'],
        ];
    }
}
