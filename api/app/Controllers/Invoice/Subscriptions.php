<?php

namespace App\Controllers\Invoice;

/**
 * Status langganan untuk app multi-tenant.
 *
 * GET /Invoice/Subscriptions/status?subscription_id=sub_xxx
 * Header: X-Invoice-Api-Key: <Env::INVOICE_API_KEY>
 */
class Subscriptions extends InvoiceController
{
    private const PUBLIC_BASE = 'https://invoice.nalju.com/#/i/';

    public function status()
    {
        if (!$this->verifyInvoiceApiKey()) {
            $this->error('Unauthorized', 401);
        }

        $subscriptionId = trim((string) ($_GET['subscription_id'] ?? ''));
        if ($subscriptionId === '') {
            $this->error('subscription_id wajib diisi', 400);
        }

        try {
            $bill = $this->db($this->db_index)->query(
                "SELECT * FROM recurring_bills WHERE subscription_id = ? LIMIT 1",
                [$subscriptionId]
            )->row_array();

            if (!$bill) {
                $this->success([
                    'subscription_id' => $subscriptionId,
                    'ok' => false,
                    'status' => 'not_found',
                    'service_allowed' => false,
                    'recurring_active' => false,
                    'period' => null,
                    'next_issue_date' => null,
                    'invoice' => null,
                ], 'Subscription status');
            }

            $today = date('Y-m-d');
            $openInvoice = $this->db($this->db_index)->query(
                "SELECT * FROM invoices
                 WHERE recurring_bill_id = ?
                   AND status != 'cancelled'
                   AND payment_status IN ('unpaid', 'pending')
                 ORDER BY
                   CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 0 ELSE 1 END ASC,
                   due_date ASC,
                   issue_date DESC,
                   id DESC
                 LIMIT 1",
                [(int) $bill['id'], $today]
            )->row_array();

            $latestInvoice = null;
            if (!$openInvoice) {
                $latestInvoice = $this->db($this->db_index)->query(
                    "SELECT * FROM invoices
                     WHERE recurring_bill_id = ?
                       AND status != 'cancelled'
                     ORDER BY issue_date DESC, id DESC
                     LIMIT 1",
                    [(int) $bill['id']]
                )->row_array();
            }

            $invoiceRow = $openInvoice ?: $latestInvoice;
            $invoicePayload = $invoiceRow ? $this->formatSubscriptionInvoice($invoiceRow, $today) : null;

            $recurringActive = (int) $bill['is_active'] === 1;
            $status = 'active';
            $ok = true;
            $serviceAllowed = true;

            if ($openInvoice) {
                $due = $openInvoice['due_date'] ?? null;
                if ($due && $due < $today) {
                    $status = 'overdue';
                    $ok = false;
                    $serviceAllowed = false;
                } else {
                    $status = 'grace';
                    $ok = true;
                    $serviceAllowed = true;
                }
            } elseif (!$recurringActive) {
                $status = 'inactive';
                $ok = false;
                $serviceAllowed = false;
            } else {
                $status = 'active';
                $ok = true;
                $serviceAllowed = true;
            }

            // Prioritas: overdue sudah di atas; inactive hanya jika tidak overdue
            if ($status !== 'overdue' && !$recurringActive) {
                $status = 'inactive';
                $ok = false;
                $serviceAllowed = false;
            }

            $this->success([
                'subscription_id' => $bill['subscription_id'],
                'ok' => $ok,
                'status' => $status,
                'service_allowed' => $serviceAllowed,
                'recurring_active' => $recurringActive,
                'period' => $bill['period'],
                'next_issue_date' => $bill['next_issue_date'],
                'invoice' => $invoicePayload,
            ], 'Subscription status');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat status: ' . $e->getMessage(), 500);
        }
    }

    protected function verifyInvoiceApiKey(): bool
    {
        $expected = '';
        if (class_exists('Env') && defined('Env::INVOICE_API_KEY')) {
            $expected = (string) \Env::INVOICE_API_KEY;
        }
        if ($expected === '') {
            $expected = getenv('INVOICE_API_KEY') ?: '';
        }
        if ($expected === '') {
            return false;
        }

        $provided = trim((string) ($_SERVER['HTTP_X_INVOICE_API_KEY'] ?? ''));
        if ($provided === '') {
            $provided = trim((string) ($_GET['api_key'] ?? ''));
        }

        return hash_equals($expected, $provided);
    }

    /** @param array<string, mixed> $invoice */
    protected function formatSubscriptionInvoice(array $invoice, string $today): array
    {
        $due = $invoice['due_date'] ?? null;
        $daysUntilDue = null;
        if ($due) {
            try {
                $dueDt = new \DateTimeImmutable($due);
                $todayDt = new \DateTimeImmutable($today);
                $daysUntilDue = (int) $todayDt->diff($dueDt)->format('%r%a');
            } catch (\Throwable $e) {
                $daysUntilDue = null;
            }
        }

        $paymentStatus = (string) ($invoice['payment_status'] ?? '');
        if ($paymentStatus === 'paid') {
            $daysUntilDue = null;
        }

        return [
            'number' => $invoice['invoice_number'],
            'issue_date' => $invoice['issue_date'],
            'due_date' => $due,
            'payment_status' => $paymentStatus,
            'total' => (float) $invoice['total'],
            'days_until_due' => $daysUntilDue,
            'public_url' => self::PUBLIC_BASE . $invoice['public_token'],
        ];
    }
}
