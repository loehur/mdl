<?php

namespace App\Controllers\Investasi;

/**
 * Dashboard — GET /Investasi/Dashboard/summary
 *
 * Pemasukan harian = modul terpisah, tidak mempengaruhi portfolio.
 * Portfolio dibandingkan dengan modal investasi (deposit - penarikan).
 */
class Dashboard extends InvestasiController
{
    public function __construct()
    {
        parent::__construct();
        $this->verifyAuth();
    }

    public function summary()
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $todayIncome = (float) ($this->db($this->db_index)->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM daily_incomes WHERE record_date = ?",
            [$today]
        )->row_array()['total'] ?? 0);

        $monthIncome = (float) ($this->db($this->db_index)->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM daily_incomes WHERE record_date BETWEEN ? AND ?",
            [$monthStart, $today]
        )->row_array()['total'] ?? 0);

        $performance = $this->getPortfolioPerformance();

        $this->success([
            // Pemasukan — independen dari portfolio
            'today_income' => $todayIncome,
            'month_income' => $monthIncome,
            // Investasi & portfolio
            'total_deposits' => $performance['total_deposits'],
            'total_withdrawals' => $performance['total_withdrawals'],
            'net_investment' => $performance['net_investment'],
            'portfolio' => $performance['portfolio'],
            'portfolio_amount' => $performance['portfolio_amount'],
            'gain_loss' => $performance['gain_loss'],
            'gain_loss_pct' => $performance['gain_loss_pct'],
            'status' => $performance['status'],
        ]);
    }
}
