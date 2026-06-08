<?php

namespace App\Controllers\Investasi;

use App\Core\Controller as BaseController;

abstract class InvestasiController extends BaseController
{
    protected $db_index = 5;
    protected $session_key = 'investasi_user_session';

    public function __construct()
    {
        $this->handleCors();
    }

    protected function verifyAuth()
    {
        if (empty($_SESSION[$this->session_key]['logged_in'])) {
            $this->error('Unauthorized', 401);
        }

        $this->extendSession();
    }

    /** Perpanjang session 7 hari (sliding) — tanpa setcookie setelah header terkirim. */
    protected function extendSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = 7 * 24 * 60 * 60;
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        if (!empty($_SESSION[$this->session_key]['logged_in'])) {
            $_SESSION[$this->session_key]['expires_at'] = time() + $lifetime;
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
