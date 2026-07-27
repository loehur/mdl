<?php

namespace App\Controllers\Invoice;

class RecurringBills extends InvoiceController
{
    public function list()
    {
        $this->verifyAuth();
        $userId = (int) $this->currentUser()['id'];

        try {
            $rows = $this->db($this->db_index)->query(
                "SELECT rb.*, c.name AS customer_name, c.phone AS customer_phone
                 FROM recurring_bills rb
                 LEFT JOIN customers c ON c.id = rb.customer_id AND c.user_id = rb.user_id
                 WHERE rb.user_id = ?
                 ORDER BY rb.is_active DESC, rb.next_issue_date ASC, rb.id DESC",
                [$userId]
            )->result_array();

            $bills = array_map([$this, 'formatRecurringBill'], $rows);

            $this->success(['bills' => $bills], 'Daftar langganan');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat langganan: ' . $e->getMessage(), 500);
        }
    }

    public function detail()
    {
        $this->verifyAuth();
        $userId = (int) $this->currentUser()['id'];
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID langganan tidak valid', 400);
        }

        try {
            $row = $this->findRecurringBill($id, $userId);
            if (!$row) {
                $this->error('Langganan tidak ditemukan', 404);
            }

            $this->success($this->formatRecurringBill($row, true), 'Detail langganan');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat detail: ' . $e->getMessage(), 500);
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
                $this->error('ID langganan tidak valid', 400);
            }

            $existing = $this->findRecurringBill($id, $userId);
            if (!$existing) {
                $this->error('Langganan tidak ditemukan', 404);
            }

            $parsed = $this->parseRecurringBody(
                $body,
                $userId,
                trim((string) ($existing['subscription_id'] ?? '')) ?: null
            );

            $this->db($this->db_index)->update('recurring_bills', [
                'customer_id' => $parsed['customer_id'],
                'subscription_id' => $parsed['subscription_id'],
                'title' => $parsed['title'],
                'tax_percent' => $parsed['tax_percent'],
                'notes' => $parsed['notes'],
                'items_json' => json_encode($parsed['items'], JSON_UNESCAPED_UNICODE),
                'period' => $parsed['period'],
                'next_issue_date' => $parsed['next_issue_date'],
                'due_days' => $parsed['due_days'],
                'is_active' => $parsed['is_active'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id, 'user_id' => $userId]);

            $row = $this->findRecurringBill($id, $userId);
            $this->success($this->formatRecurringBill($row, true), 'Langganan berhasil diperbarui');
        } catch (\Throwable $e) {
            $this->error('Gagal memperbarui langganan: ' . $e->getMessage(), 500);
        }
    }

    public function setActive()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];
        $body = $this->getBody();
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID langganan tidak valid', 400);
        }

        if (!array_key_exists('is_active', $body)) {
            $this->error('Status aktif wajib diisi', 400);
        }

        $isActive = !empty($body['is_active']) ? 1 : 0;

        try {
            $existing = $this->findRecurringBill($id, $userId);
            if (!$existing) {
                $this->error('Langganan tidak ditemukan', 404);
            }

            $this->db($this->db_index)->update('recurring_bills', [
                'is_active' => $isActive,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id, 'user_id' => $userId]);

            $row = $this->findRecurringBill($id, $userId);
            $message = $isActive ? 'Langganan diaktifkan' : 'Langganan dinonaktifkan';
            $this->success($this->formatRecurringBill($row, true), $message);
        } catch (\Throwable $e) {
            $this->error('Gagal mengubah status: ' . $e->getMessage(), 500);
        }
    }

    /** @return array<string, mixed>|null */
    protected function findRecurringBill(int $id, int $userId): ?array
    {
        $row = $this->db($this->db_index)->query(
            "SELECT rb.*, c.name AS customer_name, c.phone AS customer_phone
             FROM recurring_bills rb
             LEFT JOIN customers c ON c.id = rb.customer_id AND c.user_id = rb.user_id
             WHERE rb.id = ? AND rb.user_id = ?
             LIMIT 1",
            [$id, $userId]
        )->row_array();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    protected function parseRecurringBody(array $body, int $userId, ?string $existingSubId = null): array
    {
        $this->validate($body, ['customer_id', 'title', 'items', 'period', 'next_issue_date']);

        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            $this->error('Judul wajib diisi', 400);
        }

        $customerId = (int) ($body['customer_id'] ?? 0);
        if ($customerId <= 0) {
            $this->error('Pelanggan wajib dipilih', 400);
        }

        $customer = $this->db($this->db_index)->query(
            "SELECT id FROM customers WHERE id = ? AND user_id = ? LIMIT 1",
            [$customerId, $userId]
        )->row_array();

        if (!$customer) {
            $this->error('Pelanggan tidak ditemukan', 404);
        }

        $period = trim((string) ($body['period'] ?? 'monthly'));
        if (!in_array($period, ['monthly', 'yearly'], true)) {
            $this->error('Periode tidak valid', 400);
        }

        $nextIssue = trim((string) ($body['next_issue_date'] ?? ''));
        if ($nextIssue === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextIssue)) {
            $this->error('Tanggal terbit berikutnya tidak valid', 400);
        }

        $dueDays = $body['due_days'] ?? null;
        if ($dueDays === '' || $dueDays === null) {
            $dueDays = null;
        } else {
            $dueDays = (int) $dueDays;
            if ($dueDays < 0) {
                $this->error('Jatuh tempo (hari) tidak valid', 400);
            }
        }

        $taxPercent = round((float) ($body['tax_percent'] ?? 0), 2);
        if ($taxPercent < 0 || $taxPercent > 100) {
            $this->error('Pajak tidak valid (0-100%)', 400);
        }

        if (!is_array($body['items']) || count($body['items']) === 0) {
            $this->error('Minimal 1 item', 400);
        }

        $subtotal = 0.0;
        $parsedItems = [];

        foreach ($body['items'] as $item) {
            $desc = trim((string) ($item['description'] ?? ''));
            if ($desc === '') {
                $this->error('Deskripsi item tidak boleh kosong', 400);
            }

            $qty = round((float) ($item['quantity'] ?? 1), 2);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);

            if ($qty <= 0 || $unitPrice < 0) {
                $this->error('Jumlah atau harga item tidak valid', 400);
            }

            $subtotal += round($qty * $unitPrice, 2);
            $parsedItems[] = [
                'description' => $desc,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
            ];
        }

        if ($subtotal <= 0) {
            $this->error('Total harus lebih dari 0', 400);
        }

        $subscriptionId = trim((string) ($body['subscription_id'] ?? ''));
        if ($subscriptionId !== '') {
            $subscriptionId = substr($subscriptionId, 0, 64);
        } elseif ($existingSubId) {
            $subscriptionId = $existingSubId;
        } else {
            $subscriptionId = 'sub_' . bin2hex(random_bytes(12));
        }

        $isActive = array_key_exists('is_active', $body)
            ? (!empty($body['is_active']) ? 1 : 0)
            : 1;

        return [
            'customer_id' => $customerId,
            'title' => $title,
            'period' => $period,
            'next_issue_date' => $nextIssue,
            'due_days' => $dueDays,
            'tax_percent' => $taxPercent,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'items' => $parsedItems,
            'subscription_id' => $subscriptionId,
            'is_active' => $isActive,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function formatRecurringBill(array $row, bool $includeItems = false): array
    {
        $items = [];
        $raw = $row['items_json'] ?? '[]';
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $qty = round((float) ($item['quantity'] ?? 0), 2);
                $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
                $items[] = [
                    'description' => (string) ($item['description'] ?? ''),
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'amount' => round($qty * $unitPrice, 2),
                ];
            }
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += $item['amount'];
        }
        $taxPercent = round((float) ($row['tax_percent'] ?? 0), 2);
        $taxAmount = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        $data = [
            'id' => (int) $row['id'],
            'customer_id' => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            'customer_name' => $row['customer_name'] ?? null,
            'customer_phone' => $row['customer_phone'] ?? null,
            'subscription_id' => $row['subscription_id'] ?? null,
            'title' => $row['title'] ?? null,
            'period' => $row['period'] ?? 'monthly',
            'next_issue_date' => $row['next_issue_date'] ?? null,
            'due_days' => isset($row['due_days']) && $row['due_days'] !== null
                ? (int) $row['due_days']
                : null,
            'tax_percent' => $taxPercent,
            'notes' => $row['notes'] ?? null,
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'source_invoice_id' => isset($row['source_invoice_id']) && $row['source_invoice_id'] !== null
                ? (int) $row['source_invoice_id']
                : null,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if ($includeItems) {
            $data['items'] = $items;
        }

        return $data;
    }
}
