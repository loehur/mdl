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
