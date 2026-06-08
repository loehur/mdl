<?php

namespace App\Controllers\Investasi;

/**
 * Dashboard — GET /Investasi/Dashboard/summary
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

        $todayIncome = $this->db($this->db_index)->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM daily_incomes WHERE record_date = ?",
            [$today]
        )->row_array()['total'] ?? 0;

        $monthIncome = $this->db($this->db_index)->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM daily_incomes WHERE record_date BETWEEN ? AND ?",
            [$monthStart, $today]
        )->row_array()['total'] ?? 0;

        $deposits = $this->db($this->db_index)->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM investment_movements WHERE movement_type = 'deposit'"
        )->row_array()['total'] ?? 0;

        $withdrawals = $this->db($this->db_index)->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM investment_movements WHERE movement_type = 'withdrawal'"
        )->row_array()['total'] ?? 0;

        $portfolio = $this->db($this->db_index)->query(
            "SELECT amount, record_date, note, created_at FROM portfolio_snapshots ORDER BY record_date DESC, id DESC LIMIT 1"
        )->row_array();

        $netInvestment = (float) $deposits - (float) $withdrawals;

        $this->success([
            'today_income' => (float) $todayIncome,
            'month_income' => (float) $monthIncome,
            'total_deposits' => (float) $deposits,
            'total_withdrawals' => (float) $withdrawals,
            'net_investment' => $netInvestment,
            'portfolio' => $portfolio ?: null,
            'portfolio_amount' => $portfolio ? (float) $portfolio['amount'] : null,
        ]);
    }
}
