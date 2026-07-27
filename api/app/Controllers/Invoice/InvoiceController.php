<?php

namespace App\Controllers\Invoice;

use App\Core\Controller as BaseController;

abstract class InvoiceController extends BaseController
{
    protected $db_index = 6;
    protected $session_key = 'invoice_user_session';
    protected $token_cookie = 'invoice_token';
    protected $token_lifetime = 604800;
    protected $defaultBusinessName = 'Nalju Digital Solutions (NDS)';

    public function __construct()
    {
        $this->handleCors();
    }

    protected function verifyAuth()
    {
        if (!$this->restoreAuth()) {
            $this->error('Unauthorized', 401);
        }
    }

    protected function restoreAuth(): bool
    {
        if ($this->isSessionLoggedIn()) {
            $this->extendSession();
            return true;
        }

        $user = $this->authenticateByToken();
        if (!$user) {
            return false;
        }

        $this->establishSession($user);
        $this->extendSession();
        return true;
    }

    protected function isSessionLoggedIn(): bool
    {
        return !empty($_SESSION[$this->session_key]['logged_in']);
    }

    protected function getRequestToken(): string
    {
        if (!empty($_SERVER['HTTP_X_INVOICE_TOKEN'])) {
            return trim($_SERVER['HTTP_X_INVOICE_TOKEN']);
        }

        return trim($_COOKIE[$this->token_cookie] ?? '');
    }

    protected function authenticateByToken(): ?array
    {
        $token = $this->getRequestToken();
        if ($token === '') {
            return null;
        }

        try {
            $hash = hash('sha256', $token);
            $row = $this->db($this->db_index)->query(
                "SELECT t.user_id, u.id, u.name, u.email, u.business_name, u.business_phone, u.business_address, u.is_active
                 FROM invoice_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.token_hash = ? AND t.expires_at > NOW()
                 LIMIT 1",
                [$hash]
            )->row_array();

            if (!$row || (int) $row['is_active'] !== 1) {
                return null;
            }

            $this->db($this->db_index)->update('invoice_tokens', [
                'expires_at' => date('Y-m-d H:i:s', time() + $this->token_lifetime),
            ], ['token_hash' => $hash]);

            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'business_name' => $this->resolveIssuerName($row['business_name'] ?? null),
                'business_phone' => $row['business_phone'],
                'business_address' => $row['business_address'],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function establishSession(array $user): void
    {
        $_SESSION[$this->session_key] = [
            'user' => $user,
            'logged_in' => true,
        ];
    }

    protected function issueAuthToken(int $userId): ?string
    {
        try {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);

            $this->db($this->db_index)->insert('invoice_tokens', [
                'user_id' => $userId,
                'token_hash' => $hash,
                'expires_at' => date('Y-m-d H:i:s', time() + $this->token_lifetime),
            ]);

            $this->pruneUserTokens($userId);
            $this->setTokenCookie($token);

            return $token;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function revokeAuthToken(): void
    {
        $token = $this->getRequestToken();
        if ($token !== '') {
            try {
                $hash = hash('sha256', $token);
                $this->db($this->db_index)->delete('invoice_tokens', ['token_hash' => $hash]);
            } catch (\Throwable $e) {
                /* ignore */
            }
        }

        $this->clearTokenCookie();
    }

    protected function pruneUserTokens(int $userId): void
    {
        try {
            $rows = $this->db($this->db_index)->query(
                "SELECT id FROM invoice_tokens WHERE user_id = ? ORDER BY id DESC",
                [$userId]
            )->result_array();

            if (count($rows) <= 5) {
                return;
            }

            $keep = array_column(array_slice($rows, 0, 5), 'id');
            $placeholders = implode(',', array_fill(0, count($keep), '?'));
            $this->db($this->db_index)->query(
                "DELETE FROM invoice_tokens WHERE user_id = ? AND id NOT IN ({$placeholders})",
                array_merge([$userId], $keep)
            );
        } catch (\Throwable $e) {
            /* ignore */
        }
    }

    protected function cookieDomain(): string
    {
        $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        if ($host === 'nalju.com' || str_ends_with($host, '.nalju.com')) {
            return '.nalju.com';
        }

        return '';
    }

    protected function setTokenCookie(string $token): void
    {
        if (headers_sent()) {
            return;
        }

        $domain = $this->cookieDomain();
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        $params = [
            'expires' => time() + $this->token_lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ];
        if ($domain !== '') {
            $params['domain'] = $domain;
        }

        setcookie($this->token_cookie, $token, $params);
    }

    protected function clearTokenCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $domain = $this->cookieDomain();
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        $params = [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ];
        if ($domain !== '') {
            $params['domain'] = $domain;
        }

        setcookie($this->token_cookie, '', $params);
    }

    protected function extendSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!empty($_SESSION[$this->session_key]['logged_in'])) {
            $_SESSION[$this->session_key]['expires_at'] = time() + $this->token_lifetime;
        }
    }

    protected function currentUser()
    {
        return $_SESSION[$this->session_key]['user'] ?? null;
    }

    protected function generatePublicToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    protected function generateInvoiceNumber(int $userId): string
    {
        $prefix = 'INV-' . date('Ym') . '-';
        $row = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS cnt FROM invoices
             WHERE user_id = ? AND invoice_number LIKE ?",
            [$userId, $prefix . '%']
        )->row_array();

        $seq = ((int) ($row['cnt'] ?? 0)) + 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected function formatInvoice(array $invoice, array $items = [], ?array $issuer = null): array
    {
        $data = [
            'id' => (int) $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'public_token' => $invoice['public_token'],
            'customer_id' => isset($invoice['customer_id']) && $invoice['customer_id'] !== null
                ? (int) $invoice['customer_id']
                : null,
            'customer_name' => $invoice['customer_name'],
            'customer_email' => $invoice['customer_email'],
            'customer_phone' => $invoice['customer_phone'],
            'title' => $invoice['title'] ?? null,
            'recurring_bill_id' => isset($invoice['recurring_bill_id']) && $invoice['recurring_bill_id'] !== null
                ? (int) $invoice['recurring_bill_id']
                : null,
            'issue_date' => $invoice['issue_date'],
            'due_date' => $invoice['due_date'],
            'subtotal' => (float) $invoice['subtotal'],
            'tax_percent' => (float) $invoice['tax_percent'],
            'tax_amount' => (float) $invoice['tax_amount'],
            'total' => (float) $invoice['total'],
            'notes' => $invoice['notes'],
            'status' => $invoice['status'],
            'payment_status' => $invoice['payment_status'],
            'created_at' => $invoice['created_at'],
            'items' => $items,
        ];

        if ($issuer) {
            $data['issuer'] = $issuer;
        }

        return $data;
    }

    protected function getInvoiceItems(int $invoiceId): array
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT id, description, quantity, unit_price, amount, sort_order
             FROM invoice_items
             WHERE invoice_id = ?
             ORDER BY sort_order ASC, id ASC",
            [$invoiceId]
        )->result_array();

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'description' => $row['description'],
                'quantity' => (float) $row['quantity'],
                'unit_price' => (float) $row['unit_price'],
                'amount' => (float) $row['amount'],
            ];
        }, $rows);
    }

    protected function resolveIssuerName(?string $businessName): string
    {
        $name = trim((string) $businessName);
        return $name !== '' ? $name : $this->defaultBusinessName;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{name: string, phone: string, address: string}
     */
    protected function buildIssuerPayload(array $user): array
    {
        return [
            'name' => $this->resolveIssuerName($user['business_name'] ?? null),
            'phone' => (string) ($user['business_phone'] ?? ''),
            'address' => (string) ($user['business_address'] ?? ''),
        ];
    }

    protected function buildShareText(array $invoice, string $publicUrl, ?string $issuerName = null): string
    {
        $total = number_format((float) $invoice['total'], 0, ',', '.');
        $date = !empty($invoice['issue_date'])
            ? date('d/m/Y', strtotime($invoice['issue_date']))
            : '-';
        $title = trim((string) ($invoice['title'] ?? ''));
        $customerName = trim((string) ($invoice['customer_name'] ?? ''));
        $brand = $this->resolveIssuerName($issuerName);

        return "*{$brand}*\n"
            . "INVOICE PEMBAYARAN\n"
            . "\n"
            . "Halo *{$customerName}*,\n"
            . "Berikut rincian tagihan *{$title}*,\n"
            . "\n"
            . "* No. Invoice: {$invoice['invoice_number']}\n"
            . "* Tanggal: {$date}\n"
            . "* Total: *Rp{$total},-*\n"
            . "\n"
            . "Lihat & bayar invoice:\n"
            . "{$publicUrl}\n"
            . "\n"
            . "Terima kasih\n"
            . "_{$brand}_";
    }

    protected function advanceIssueDate(string $issueDate, string $period): string
    {
        $dt = new \DateTimeImmutable($issueDate);
        if ($period === 'yearly') {
            return $dt->modify('+1 year')->format('Y-m-d');
        }
        return $dt->modify('+1 month')->format('Y-m-d');
    }

    protected function calcDueDays(?string $issueDate, ?string $dueDate): ?int
    {
        if (!$issueDate || !$dueDate) {
            return null;
        }
        try {
            $issue = new \DateTimeImmutable($issueDate);
            $due = new \DateTimeImmutable($dueDate);
            $days = (int) $issue->diff($due)->format('%r%a');
            return $days >= 0 ? $days : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $parsed from parseInvoiceBody
     * @param array{enabled?: bool, period?: string}|null $recurring
     */
    protected function syncRecurringBill(int $userId, int $invoiceId, array $parsed, ?array $recurring): ?array
    {
        $enabled = !empty($recurring['enabled']);
        $period = trim((string) ($recurring['period'] ?? 'monthly'));
        if (!in_array($period, ['monthly', 'yearly'], true)) {
            $period = 'monthly';
        }

        $existingId = null;
        $invoice = $this->db($this->db_index)->query(
            "SELECT recurring_bill_id FROM invoices WHERE id = ? AND user_id = ? LIMIT 1",
            [$invoiceId, $userId]
        )->row_array();
        if (!empty($invoice['recurring_bill_id'])) {
            $existingId = (int) $invoice['recurring_bill_id'];
        }

        if (!$enabled) {
            if ($existingId) {
                $this->db($this->db_index)->update('recurring_bills', [
                    'is_active' => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => $existingId, 'user_id' => $userId]);
            }
            return [
                'enabled' => false,
                'period' => null,
                'next_issue_date' => null,
                'subscription_id' => null,
            ];
        }

        $existingSubId = null;
        if ($existingId) {
            $existingRow = $this->db($this->db_index)->query(
                "SELECT subscription_id FROM recurring_bills WHERE id = ? AND user_id = ? LIMIT 1",
                [$existingId, $userId]
            )->row_array();
            $existingSubId = trim((string) ($existingRow['subscription_id'] ?? '')) ?: null;
        }

        $requestedSubId = trim((string) ($recurring['subscription_id'] ?? ''));
        if ($requestedSubId !== '') {
            $subscriptionId = substr($requestedSubId, 0, 64);
        } elseif ($existingSubId) {
            $subscriptionId = $existingSubId;
        } else {
            $subscriptionId = 'sub_' . bin2hex(random_bytes(12));
        }

        $itemsJson = array_map(static function ($item) {
            return [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ];
        }, $parsed['items']);

        $nextIssue = $this->advanceIssueDate($parsed['issue_date'], $period);
        $dueDays = $this->calcDueDays($parsed['issue_date'], $parsed['due_date'] ?? null);
        $now = date('Y-m-d H:i:s');

        $payload = [
            'user_id' => $userId,
            'customer_id' => $parsed['customer_id'],
            'subscription_id' => $subscriptionId,
            'title' => $parsed['title'],
            'tax_percent' => $parsed['tax_percent'],
            'notes' => $parsed['notes'],
            'items_json' => json_encode($itemsJson, JSON_UNESCAPED_UNICODE),
            'period' => $period,
            'next_issue_date' => $nextIssue,
            'due_days' => $dueDays,
            'source_invoice_id' => $invoiceId,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if ($existingId) {
            $this->db($this->db_index)->update('recurring_bills', $payload, [
                'id' => $existingId,
                'user_id' => $userId,
            ]);
            $billId = $existingId;
        } else {
            $payload['created_at'] = $now;
            $billId = (int) $this->db($this->db_index)->insert('recurring_bills', $payload);
            if ($billId <= 0) {
                $this->error('Gagal menyimpan jadwal tagihan berulang', 500);
            }
        }

        $this->db($this->db_index)->update('invoices', [
            'recurring_bill_id' => $billId,
        ], ['id' => $invoiceId]);

        return [
            'enabled' => true,
            'period' => $period,
            'next_issue_date' => $nextIssue,
            'recurring_bill_id' => $billId,
            'subscription_id' => $subscriptionId,
        ];
    }

    protected function getRecurringInfoForInvoice(array $invoice): array
    {
        $billId = isset($invoice['recurring_bill_id']) ? (int) $invoice['recurring_bill_id'] : 0;
        if ($billId <= 0) {
            return [
                'enabled' => false,
                'period' => null,
                'next_issue_date' => null,
                'subscription_id' => null,
            ];
        }

        $row = $this->db($this->db_index)->query(
            "SELECT id, period, next_issue_date, is_active, subscription_id
             FROM recurring_bills
             WHERE id = ? AND user_id = ?
             LIMIT 1",
            [$billId, (int) $invoice['user_id']]
        )->row_array();

        if (!$row) {
            return [
                'enabled' => false,
                'period' => null,
                'next_issue_date' => null,
                'subscription_id' => null,
            ];
        }

        if (!(int) $row['is_active']) {
            return [
                'enabled' => false,
                'period' => null,
                'next_issue_date' => null,
                'subscription_id' => $row['subscription_id'] ?? null,
            ];
        }

        return [
            'enabled' => true,
            'period' => $row['period'],
            'next_issue_date' => $row['next_issue_date'],
            'recurring_bill_id' => (int) $row['id'],
            'subscription_id' => $row['subscription_id'] ?? null,
        ];
    }

    protected function isTokopaySuccess(?array $data): bool
    {
        if (!$data || !isset($data['status'])) {
            return false;
        }

        $status = is_string($data['status']) ? strtolower($data['status']) : $data['status'];
        return $status === 'success' || $status === 'true' || $status === true || $status === 1;
    }

    protected function extractQrString(?array $data): string
    {
        if (!$data) {
            return '';
        }

        if (!empty($data['data']['qr_string'])) {
            return $data['data']['qr_string'];
        }

        if (!empty($data['qr_string'])) {
            return $data['qr_string'];
        }

        return '';
    }

    protected function parsePaymentStatus(?array $data): string
    {
        $statusTrx = '';

        if (isset($data['data'])) {
            if (!empty($data['data']['status_pembayaran'])) {
                $statusTrx = strtolower(trim($data['data']['status_pembayaran']));
            } elseif (!empty($data['data']['status']) && is_string($data['data']['status'])) {
                $statusTrx = strtolower(trim($data['data']['status']));
            }
        }

        if ($statusTrx === '' && !empty($data['status_pembayaran'])) {
            $statusTrx = strtolower(trim($data['status_pembayaran']));
        }

        if ($statusTrx === '') {
            return 'pending';
        }

        if (defined('Env::QRIS_STATUS_SUCCESS') && in_array($statusTrx, \Env::QRIS_STATUS_SUCCESS, true)) {
            return 'paid';
        }

        if (defined('Env::QRIS_STATUS_EXPIRED') && in_array($statusTrx, \Env::QRIS_STATUS_EXPIRED, true)) {
            return 'expired';
        }

        if (in_array($statusTrx, ['success', 'paid', 'settlement', 'berhasil'], true)) {
            return 'paid';
        }

        if (in_array($statusTrx, ['expired', 'kadaluarsa', 'failed', 'gagal'], true)) {
            return 'expired';
        }

        return 'pending';
    }

    protected function markInvoicePaid(int $invoiceId, string $paymentRef): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db($this->db_index)->update('invoice_payments', [
            'payment_status' => 'success',
            'paid_at' => $now,
        ], ['payment_ref' => $paymentRef]);

        $this->db($this->db_index)->update('invoices', [
            'payment_status' => 'paid',
            'status' => 'paid',
        ], ['id' => $invoiceId]);
    }
}
