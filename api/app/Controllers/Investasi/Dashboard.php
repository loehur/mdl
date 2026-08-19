<?php

namespace App\Controllers\Investasi;

/**
 * Dashboard — GET /Investasi/Dashboard/summary
 *
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
        $performance = $this->getPortfolioPerformance();

        $this->success([
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
