<?php

namespace App\Controllers\Invoice;

use App\Core\Controller as BaseController;

abstract class InvoiceController extends BaseController
{
    protected $db_index = 6;
    protected $session_key = 'invoice_user_session';
    protected $token_cookie = 'invoice_token';
    protected $token_lifetime = 604800;

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
                'business_name' => $row['business_name'],
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

    protected function buildShareText(array $invoice, string $publicUrl): string
    {
        $total = number_format((float) $invoice['total'], 0, ',', '.');
        $due = $invoice['due_date']
            ? date('d M Y', strtotime($invoice['due_date']))
            : '-';

        $lines = ["Invoice {$invoice['invoice_number']}"];

        $title = trim((string) ($invoice['title'] ?? ''));
        if ($title !== '') {
            $lines[] = "Judul: {$title}";
        }

        $lines[] = "Kepada: {$invoice['customer_name']}";
        $lines[] = "Total: Rp {$total}";
        $lines[] = "Jatuh tempo: {$due}";
        $lines[] = '';
        $lines[] = 'Lihat & bayar invoice:';
        $lines[] = $publicUrl;

        return implode("\n", $lines);
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
