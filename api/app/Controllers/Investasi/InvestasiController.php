<?php

namespace App\Controllers\Investasi;

use App\Core\Controller as BaseController;

abstract class InvestasiController extends BaseController
{
    protected $db_index = 5;
    protected $session_key = 'investasi_user_session';
    protected $token_cookie = 'investasi_token';
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

    /** Pulihkan login dari session PHP atau token persisten (header/cookie). */
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
        if (!empty($_SERVER['HTTP_X_INVESTASI_TOKEN'])) {
            return trim($_SERVER['HTTP_X_INVESTASI_TOKEN']);
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
                "SELECT t.user_id, u.id, u.name, u.email, u.is_active
                 FROM investasi_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.token_hash = ? AND t.expires_at > NOW()
                 LIMIT 1",
                [$hash]
            )->row_array();

            if (!$row || (int) $row['is_active'] !== 1) {
                return null;
            }

            $this->db($this->db_index)->update('investasi_tokens', [
                'expires_at' => date('Y-m-d H:i:s', time() + $this->token_lifetime),
            ], ['token_hash' => $hash]);

            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
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

            $this->db($this->db_index)->insert('investasi_tokens', [
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
                $this->db($this->db_index)->delete('investasi_tokens', ['token_hash' => $hash]);
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
                "SELECT id FROM investasi_tokens
                 WHERE user_id = ?
                 ORDER BY id DESC",
                [$userId]
            )->result_array();

            if (count($rows) <= 5) {
                return;
            }

            $keep = array_column(array_slice($rows, 0, 5), 'id');
            $placeholders = implode(',', array_fill(0, count($keep), '?'));
            $this->db($this->db_index)->query(
                "DELETE FROM investasi_tokens WHERE user_id = ? AND id NOT IN ({$placeholders})",
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

    /** Tandai session aktif 7 hari (sliding). Cookie lifetime diatur di init.php. */
    protected function extendSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!empty($_SESSION[$this->session_key]['logged_in'])) {
            $_SESSION[$this->session_key]['expires_at'] = time() + $this->token_lifetime;
        }
    }

    /** Total expense aman jika tabel daily_expenses belum ada di server. */
    protected function safeExpenseSum(string $sql, array $bind = []): float
    {
        try {
            $row = $this->db($this->db_index)->query($sql, $bind)->row_array();
            return (float) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    protected function currentUser()
    {
        return $_SESSION[$this->session_key]['user'] ?? null;
    }

    protected function sanitizeAmount($value)
    {
        if (!is_numeric($value)) {
            $this->error('Jumlah tidak valid', 400);
        }

        $amount = round((float) $value, 2);
        if ($amount <= 0) {
            $this->error('Jumlah harus lebih dari 0', 400);
        }

        return $amount;
    }

    protected function sanitizeDate($value, $field = 'record_date')
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->error("Format {$field} tidak valid (gunakan YYYY-MM-DD)", 400);
        }

        return $value;
    }

    /** @return array{start: string, end: string, meta: array} */
    protected function resolveListPeriod(?string $from, ?string $to, ?string $month): array
    {
        if ($from !== null && $from !== '' && $to !== null && $to !== '') {
            $start = $this->sanitizeDate($from);
            $end = $this->sanitizeDate($to);
            if ($start > $end) {
                $this->error('Rentang tanggal tidak valid', 400);
            }

            return [
                'start' => $start,
                'end' => $end,
                'meta' => ['from' => $start, 'to' => $end],
            ];
        }

        $month = $month ?: date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Format month tidak valid (YYYY-MM)', 400);
        }

        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        return [
            'start' => $start,
            'end' => $end,
            'meta' => ['month' => $month],
        ];
    }

    /**
     * Total deposit, penarikan, dan modal bersih (deposit - penarikan).
     */
    protected function getInvestmentTotals(): array
    {
        $deposits = (float) ($this->db($this->db_index)->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM investment_movements WHERE movement_type = 'deposit'"
        )->row_array()['total'] ?? 0);

        $withdrawals = (float) ($this->db($this->db_index)->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM investment_movements WHERE movement_type = 'withdrawal'"
        )->row_array()['total'] ?? 0);

        return [
            'total_deposits' => $deposits,
            'total_withdrawals' => $withdrawals,
            'net_investment' => $deposits - $withdrawals,
        ];
    }

    /**
     * Snapshot portfolio terbaru + selisih vs modal investasi.
     * Pemasukan harian TIDAK masuk perhitungan ini.
     */
    protected function getPortfolioPerformance(): array
    {
        $totals = $this->getInvestmentTotals();
        $netInvestment = $totals['net_investment'];

        $portfolio = $this->db($this->db_index)->query(
            "SELECT id, amount, record_date, note, created_at
             FROM portfolio_snapshots
             ORDER BY record_date DESC, id DESC
             LIMIT 1"
        )->row_array();

        $portfolioAmount = $portfolio ? (float) $portfolio['amount'] : null;
        $gainLoss = null;
        $gainLossPct = null;
        $status = null;

        if ($portfolioAmount !== null) {
            $gainLoss = $portfolioAmount - $netInvestment;
            if ($gainLoss > 0) {
                $status = 'profit';
            } elseif ($gainLoss < 0) {
                $status = 'loss';
            } else {
                $status = 'breakeven';
            }

            if ($netInvestment > 0) {
                $gainLossPct = round(($gainLoss / $netInvestment) * 100, 2);
            }
        }

        return array_merge($totals, [
            'portfolio' => $portfolio ?: null,
            'portfolio_amount' => $portfolioAmount,
            'gain_loss' => $gainLoss,
            'gain_loss_pct' => $gainLossPct,
            'status' => $status,
        ]);
    }
}
