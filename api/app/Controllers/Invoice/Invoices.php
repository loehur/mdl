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
            $sql = "SELECT id, invoice_number, public_token, customer_name, total,
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

            $data = $this->formatInvoice($invoice, $items, [
                'name' => $user['business_name'] ?: $user['name'],
                'phone' => $user['business_phone'] ?? '',
                'address' => $user['business_address'] ?? '',
            ]);
            $data['public_url'] = $publicUrl;
            $data['share_text'] = $this->buildShareText($invoice, $publicUrl);

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
            $parsed = $this->parseInvoiceBody($body);

            $invoiceNumber = $this->generateInvoiceNumber($userId);
            $publicToken = $this->generatePublicToken();

            $invoiceId = (int) $this->db($this->db_index)->insert('invoices', [
                'user_id' => $userId,
                'invoice_number' => $invoiceNumber,
                'public_token' => $publicToken,
                'customer_name' => $parsed['customer_name'],
                'customer_email' => $parsed['customer_email'],
                'customer_phone' => $parsed['customer_phone'],
                'issue_date' => $parsed['issue_date'],
                'due_date' => $parsed['due_date'],
                'subtotal' => $parsed['subtotal'],
                'tax_percent' => $parsed['tax_percent'],
                'tax_amount' => $parsed['tax_amount'],
                'total' => $parsed['total'],
                'notes' => $parsed['notes'],
                'status' => 'sent',
                'payment_status' => 'unpaid',
            ]);

            if ($invoiceId <= 0) {
                $this->error('Gagal menyimpan invoice ke database', 500);
            }

            foreach ($parsed['items'] as $item) {
                $item['invoice_id'] = $invoiceId;
                $this->db($this->db_index)->insert('invoice_items', $item);
            }

            $invoice = $this->db($this->db_index)->get_where('invoices', ['id' => $invoiceId], 1)->row_array();
            $items = $this->getInvoiceItems($invoiceId);
            $user = $this->currentUser();
            $publicUrl = $this->buildPublicUrl($publicToken);

            $data = $this->formatInvoice($invoice, $items, [
                'name' => $user['business_name'] ?: $user['name'],
                'phone' => $user['business_phone'] ?? '',
                'address' => $user['business_address'] ?? '',
            ]);
            $data['public_url'] = $publicUrl;
            $data['share_text'] = $this->buildShareText($invoice, $publicUrl);

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

            $parsed = $this->parseInvoiceBody($body);

            $updateData = [
                'customer_name' => $parsed['customer_name'],
                'customer_email' => $parsed['customer_email'],
                'customer_phone' => $parsed['customer_phone'],
                'issue_date' => $parsed['issue_date'],
                'due_date' => $parsed['due_date'],
                'subtotal' => $parsed['subtotal'],
                'tax_percent' => $parsed['tax_percent'],
                'tax_amount' => $parsed['tax_amount'],
                'total' => $parsed['total'],
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
                $item['invoice_id'] = $id;
                $this->db($this->db_index)->insert('invoice_items', $item);
            }

            $updated = $this->db($this->db_index)->get_where('invoices', ['id' => $id], 1)->row_array();
            $items = $this->getInvoiceItems($id);
            $user = $this->currentUser();
            $publicUrl = $this->buildPublicUrl($updated['public_token']);

            $data = $this->formatInvoice($updated, $items, [
                'name' => $user['business_name'] ?: $user['name'],
                'phone' => $user['business_phone'] ?? '',
                'address' => $user['business_address'] ?? '',
            ]);
            $data['public_url'] = $publicUrl;
            $data['share_text'] = $this->buildShareText($updated, $publicUrl);

            $this->success($data, 'Invoice berhasil diperbarui');
        } catch (\Throwable $e) {
            $this->error('Gagal memperbarui invoice: ' . $e->getMessage(), 500);
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
    protected function parseInvoiceBody(array $body): array
    {
        $this->validate($body, ['customer_name', 'items']);

        if (!is_array($body['items']) || count($body['items']) === 0) {
            $this->error('Minimal 1 item invoice', 400);
        }

        $issueDate = $body['issue_date'] ?? date('Y-m-d');
        $dueDate = $body['due_date'] ?? null;
        $taxPercent = round((float) ($body['tax_percent'] ?? 0), 2);

        if ($taxPercent < 0 || $taxPercent > 100) {
            $this->error('Pajak tidak valid (0-100%)', 400);
        }

        $subtotal = 0;
        $parsedItems = [];

        foreach ($body['items'] as $idx => $item) {
            $desc = trim($item['description'] ?? '');
            if ($desc === '') {
                $this->error('Deskripsi item tidak boleh kosong', 400);
            }

            $qty = round((float) ($item['quantity'] ?? 1), 2);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);

            if ($qty <= 0 || $unitPrice < 0) {
                $this->error('Jumlah atau harga item tidak valid', 400);
            }

            $amount = round($qty * $unitPrice, 2);
            $subtotal += $amount;

            $parsedItems[] = [
                'description' => $desc,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'sort_order' => $idx,
            ];
        }

        if ($subtotal <= 0) {
            $this->error('Total invoice harus lebih dari 0', 400);
        }

        $taxAmount = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'customer_name' => trim($body['customer_name']),
            'customer_email' => trim($body['customer_email'] ?? '') ?: null,
            'customer_phone' => trim($body['customer_phone'] ?? '') ?: null,
            'issue_date' => $issueDate,
            'due_date' => $dueDate ?: null,
            'subtotal' => $subtotal,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'notes' => trim($body['notes'] ?? '') ?: null,
            'items' => $parsedItems,
        ];
    }
}
