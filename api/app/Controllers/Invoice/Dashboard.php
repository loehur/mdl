<?php

namespace App\Controllers\Invoice;

class Dashboard extends InvoiceController
{
    public function summary()
    {
        $this->verifyAuth();
        $userId = (int) $this->currentUser()['id'];

        try {
            $today = date('Y-m-d');

            $stats = $this->db($this->db_index)->query(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN payment_status != 'paid' AND status != 'cancelled' THEN 1 ELSE 0 END) AS unpaid_count,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS paid_amount,
                    COALESCE(SUM(CASE WHEN payment_status != 'paid' AND status != 'cancelled' THEN total ELSE 0 END), 0) AS unpaid_amount,
                    SUM(
                        CASE
                            WHEN payment_status != 'paid'
                             AND status != 'cancelled'
                             AND due_date IS NOT NULL
                             AND due_date < ?
                            THEN 1 ELSE 0
                        END
                    ) AS overdue_count,
                    COALESCE(SUM(
                        CASE
                            WHEN payment_status != 'paid'
                             AND status != 'cancelled'
                             AND due_date IS NOT NULL
                             AND due_date < ?
                            THEN total ELSE 0
                        END
                    ), 0) AS overdue_amount
                 FROM invoices
                 WHERE user_id = ?",
                [$today, $today, $userId]
            )->row_array();

            $monthStats = $this->db($this->db_index)->query(
                "SELECT COUNT(*) AS month_count,
                        COALESCE(SUM(total), 0) AS month_total
                 FROM invoices
                 WHERE user_id = ? AND DATE_FORMAT(issue_date, '%Y-%m') = ?",
                [$userId, date('Y-m')]
            )->row_array();

            $recent = $this->db($this->db_index)->query(
                "SELECT id, invoice_number, title, customer_name, total, payment_status, status, issue_date, due_date, public_token
                 FROM invoices
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT 5",
                [$userId]
            )->result_array();

            $overdue = $this->db($this->db_index)->query(
                "SELECT id, invoice_number, title, customer_name, total, payment_status, status, issue_date, due_date, public_token
                 FROM invoices
                 WHERE user_id = ?
                   AND payment_status != 'paid'
                   AND status != 'cancelled'
                   AND due_date IS NOT NULL
                   AND due_date < ?
                 ORDER BY due_date ASC, id ASC
                 LIMIT 10",
                [$userId, $today]
            )->result_array();

            $this->success([
                'total' => (int) ($stats['total'] ?? 0),
                'paid_count' => (int) ($stats['paid_count'] ?? 0),
                'unpaid_count' => (int) ($stats['unpaid_count'] ?? 0),
                'paid_amount' => (float) ($stats['paid_amount'] ?? 0),
                'unpaid_amount' => (float) ($stats['unpaid_amount'] ?? 0),
                'overdue_count' => (int) ($stats['overdue_count'] ?? 0),
                'overdue_amount' => (float) ($stats['overdue_amount'] ?? 0),
                'month_count' => (int) ($monthStats['month_count'] ?? 0),
                'month_total' => (float) ($monthStats['month_total'] ?? 0),
                'overdue' => $overdue,
                'recent' => $recent,
            ], 'Ringkasan invoice');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat ringkasan: ' . $e->getMessage(), 500);
        }
    }
}
