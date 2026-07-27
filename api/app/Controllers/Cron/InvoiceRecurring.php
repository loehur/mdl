<?php

namespace App\Controllers\Cron;

use App\Controllers\Invoice\InvoiceController;

/**
 * Generate invoice otomatis dari recurring_bills yang jatuh tempo.
 *
 * URL example:
 * /Cron/InvoiceRecurring/index?secret=YOUR_CRON_SECRET
 */
class InvoiceRecurring extends InvoiceController
{
    public function index()
    {
        if (!$this->verifyCronSecret()) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(401);
            echo "ERROR: Unauthorized\n";
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');

        $db = $this->db($this->db_index);
        if (!$db) {
            echo "ERROR: Database connection failed\n";
            return;
        }

        $today = date('Y-m-d');
        $rows = $db->query(
            "SELECT * FROM recurring_bills
             WHERE is_active = 1 AND next_issue_date <= ?
             ORDER BY next_issue_date ASC, id ASC
             LIMIT 100",
            [$today]
        )->result_array();

        $created = 0;
        $errors = 0;
        $skipped = 0;

        echo "InvoiceRecurring run at " . date('Y-m-d H:i:s') . "\n";
        echo "Due rows: " . count($rows) . "\n\n";

        foreach ($rows as $bill) {
            try {
                $result = $this->generateFromBill($bill);
                if ($result['ok']) {
                    $created++;
                    echo "OK bill#{$bill['id']} -> invoice {$result['invoice_number']} next={$result['next_issue_date']}\n";
                } else {
                    $skipped++;
                    echo "SKIP bill#{$bill['id']}: {$result['message']}\n";
                }
            } catch (\Throwable $e) {
                $errors++;
                echo "ERR bill#{$bill['id']}: " . $e->getMessage() . "\n";
            }
        }

        echo "\nDone. created={$created} skipped={$skipped} errors={$errors}\n";
    }

    protected function verifyCronSecret(): bool
    {
        $expected = '';
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            $expected = (string) \Env::CRON_SECRET;
        }

        if ($expected === '') {
            // Dev fallback: allow if constant belum di-set
            $expected = getenv('CRON_SECRET') ?: '';
        }

        if ($expected === '') {
            return false;
        }

        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }

        return hash_equals($expected, $provided);
    }

    /** @param array<string, mixed> $bill */
    protected function generateFromBill(array $bill): array
    {
        $userId = (int) $bill['user_id'];
        $customerId = (int) $bill['customer_id'];
        $period = $bill['period'] === 'yearly' ? 'yearly' : 'monthly';
        $issueDate = $bill['next_issue_date'];

        $customer = $this->db($this->db_index)->query(
            "SELECT id, name, phone, email FROM customers WHERE id = ? AND user_id = ? LIMIT 1",
            [$customerId, $userId]
        )->row_array();

        if (!$customer) {
            $this->db($this->db_index)->update('recurring_bills', [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $bill['id']]);

            return ['ok' => false, 'message' => 'customer not found, deactivated'];
        }

        $items = json_decode((string) $bill['items_json'], true);
        if (!is_array($items) || count($items) === 0) {
            return ['ok' => false, 'message' => 'empty items_json'];
        }

        $taxPercent = round((float) $bill['tax_percent'], 2);

        try {
            $converted = $this->convertInvoiceItems($items);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $subtotal = $converted['subtotal'];
        $parsedItems = $converted['items'];
        $taxAmount = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        $dueDate = null;
        if ($bill['due_days'] !== null && $bill['due_days'] !== '') {
            $dueDays = (int) $bill['due_days'];
            if ($dueDays >= 0) {
                $dueDate = (new \DateTimeImmutable($issueDate))
                    ->modify("+{$dueDays} days")
                    ->format('Y-m-d');
            }
        }

        $invoiceNumber = $this->generateInvoiceNumber($userId);
        $publicToken = $this->generatePublicToken();
        $billId = (int) $bill['id'];

        $invoiceId = (int) $this->db($this->db_index)->insert('invoices', [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'recurring_bill_id' => $billId,
            'invoice_number' => $invoiceNumber,
            'public_token' => $publicToken,
            'customer_name' => $customer['name'],
            'customer_email' => $customer['email'] ?: null,
            'customer_phone' => $customer['phone'] ?: null,
            'title' => $bill['title'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'subtotal' => $subtotal,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'exchange_rate' => $converted['exchange_rate'],
            'total_usd' => $converted['total_usd'],
            'notes' => $bill['notes'] ?: null,
            'status' => 'sent',
            'payment_status' => 'unpaid',
        ]);

        if ($invoiceId <= 0) {
            throw new \RuntimeException('failed inserting invoice');
        }

        foreach ($parsedItems as $item) {
            $this->db($this->db_index)->insert('invoice_items', $this->invoiceItemRowForDb($item, $invoiceId));
        }

        $nextIssue = $this->advanceIssueDate($issueDate, $period);
        $this->db($this->db_index)->update('recurring_bills', [
            'next_issue_date' => $nextIssue,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $billId]);

        return [
            'ok' => true,
            'invoice_number' => $invoiceNumber,
            'next_issue_date' => $nextIssue,
        ];
    }
}
