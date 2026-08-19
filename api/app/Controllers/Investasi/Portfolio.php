<?php

namespace App\Controllers\Investasi;

/**
 * Portfolio — update & history of portfolio value
 */
class Portfolio extends InvestasiController
{
    public function __construct()
    {
        parent::__construct();
        $this->verifyAuth();
    }

    public function current()
    {
        $performance = $this->getPortfolioPerformance();

        $this->success([
            'current' => $performance['portfolio'],
            'amount' => $performance['portfolio_amount'],
            'net_investment' => $performance['net_investment'],
            'gain_loss' => $performance['gain_loss'],
            'gain_loss_pct' => $performance['gain_loss_pct'],
            'status' => $performance['status'],
        ]);
    }

    public function history()
    {
        $limit = min(50, max(1, (int) $this->query('limit', 3)));

        $rows = $this->db($this->db_index)->query(
            "SELECT id, amount, record_date, created_at
             FROM portfolio_snapshots
             ORDER BY record_date DESC, id DESC
             LIMIT ?",
            [$limit]
        )->result_array();

        $this->success(['items' => $rows]);
    }

    public function chart()
    {
        $year = (int) $this->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            $this->error('Tahun tidak valid', 400);
        }

        $rows = $this->db($this->db_index)->query(
            "SELECT amount, record_date
             FROM portfolio_snapshots
             WHERE YEAR(record_date) = ?
             ORDER BY record_date ASC, id ASC",
            [$year]
        )->result_array();

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = [
                'month' => $m,
                'open' => null,
                'high' => null,
                'low' => null,
                'close' => null,
                'snapshot_count' => 0,
            ];
        }

        foreach ($rows as $row) {
            $m = (int) date('n', strtotime($row['record_date']));
            $amount = round((float) $row['amount'], 2);

            if ($months[$m]['snapshot_count'] === 0) {
                $months[$m]['open'] = $amount;
                $months[$m]['high'] = $amount;
                $months[$m]['low'] = $amount;
            } else {
                $months[$m]['high'] = max($months[$m]['high'], $amount);
                $months[$m]['low'] = min($months[$m]['low'], $amount);
            }

            $months[$m]['close'] = $amount;
            $months[$m]['snapshot_count']++;
        }

        $movements = $this->db($this->db_index)->query(
            "SELECT movement_type, amount, record_date
             FROM investment_movements
             ORDER BY record_date ASC, id ASC"
        )->result_array();

        foreach ($months as $m => &$month) {
            $month['net_investment'] = null;
            $month['net_profit'] = null;

            if ($month['snapshot_count'] === 0 || $month['close'] === null) {
                continue;
            }

            $monthEnd = sprintf('%04d-%02d-%02d', $year, $m, (int) date('t', strtotime("{$year}-{$m}-01")));
            $netInvestment = 0.0;

            foreach ($movements as $movement) {
                if ($movement['record_date'] > $monthEnd) {
                    break;
                }
                $mvAmount = (float) $movement['amount'];
                if ($movement['movement_type'] === 'deposit') {
                    $netInvestment += $mvAmount;
                } else {
                    $netInvestment -= $mvAmount;
                }
            }

            $netInvestment = round($netInvestment, 2);
            $month['net_investment'] = $netInvestment;
            $month['net_profit'] = round((float) $month['close'] - $netInvestment, 2);
        }
        unset($month);

        $yearRows = $this->db($this->db_index)->query(
            "SELECT DISTINCT YEAR(record_date) AS y
             FROM portfolio_snapshots
             ORDER BY y DESC"
        )->result_array();

        $this->success([
            'year' => $year,
            'months' => array_values($months),
            'years' => array_map(fn($r) => (int) $r['y'], $yearRows),
        ]);
    }

    public function update()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['amount', 'record_date']);

        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);

        $existing = $this->db($this->db_index)->query(
            "SELECT id FROM portfolio_snapshots WHERE record_date = ? ORDER BY id DESC LIMIT 1",
            [$recordDate]
        )->row_array();

        if ($existing) {
            $id = (int) $existing['id'];
            $this->db($this->db_index)->update('portfolio_snapshots', [
                'amount' => $amount,
            ], ['id' => $id]);
        } else {
            $id = $this->db($this->db_index)->insert('portfolio_snapshots', [
                'amount' => $amount,
                'record_date' => $recordDate,
            ]);
        }

        $this->success([
            'id' => $id,
            'amount' => $amount,
            'record_date' => $recordDate,
        ], 'Nilai portfolio berhasil diperbarui');
    }

    public function edit()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id', 'amount', 'record_date']);

        $id = (int) $body['id'];
        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);

        $existing = $this->db($this->db_index)->get_where('portfolio_snapshots', ['id' => $id], 1)->row_array();
        if (!$existing) {
            $this->error('Data tidak ditemukan', 404);
        }

        $duplicate = $this->db($this->db_index)->query(
            "SELECT id FROM portfolio_snapshots WHERE record_date = ? AND id != ? LIMIT 1",
            [$recordDate, $id]
        )->row_array();

        if ($duplicate) {
            $this->error('Sudah ada snapshot di tanggal ini', 409);
        }

        $this->db($this->db_index)->update('portfolio_snapshots', [
            'amount' => $amount,
            'record_date' => $recordDate,
        ], ['id' => $id]);

        $this->success([
            'id' => $id,
            'amount' => $amount,
            'record_date' => $recordDate,
        ], 'Snapshot berhasil diperbarui');
    }
}
