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
        $limit = min(50, max(1, (int) $this->query('limit', 20)));

        $rows = $this->db($this->db_index)->query(
            "SELECT id, amount, record_date, note, created_at
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
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        $id = $this->db($this->db_index)->insert('portfolio_snapshots', [
            'amount' => $amount,
            'record_date' => $recordDate,
            'note' => $note ?: null,
        ]);

        $this->success([
            'id' => $id,
            'amount' => $amount,
            'record_date' => $recordDate,
            'note' => $note,
        ], 'Nilai portfolio berhasil diperbarui');
    }
}
