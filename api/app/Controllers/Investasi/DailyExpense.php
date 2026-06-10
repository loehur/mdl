<?php

namespace App\Controllers\Investasi;

/**
 * DailyExpense — GET list, POST add, POST update, POST delete
 */
class DailyExpense extends InvestasiController
{
    public function __construct()
    {
        parent::__construct();
        $this->verifyAuth();
    }

    public function list()
    {
        $period = $this->resolveListPeriod(
            $this->query('from'),
            $this->query('to'),
            $this->query('month')
        );

        $rows = $this->db($this->db_index)->query(
            "SELECT d.id, d.record_date, d.amount, d.target_id, d.note, d.created_at,
                    t.name AS target_name
             FROM daily_expenses d
             LEFT JOIN expense_targets t ON t.id = d.target_id
             WHERE d.record_date BETWEEN ? AND ?
             ORDER BY d.record_date DESC, d.id DESC",
            [$period['start'], $period['end']]
        )->result_array();

        $total = array_sum(array_map(fn($r) => (float) $r['amount'], $rows));

        $this->success(array_merge($period['meta'], [
            'total' => $total,
            'items' => $rows,
        ]));
    }

    public function add()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['record_date', 'amount', 'target_id']);

        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);
        $targetId = $this->resolveTargetId($body['target_id']);
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        $id = $this->db($this->db_index)->insert('daily_expenses', [
            'record_date' => $recordDate,
            'amount' => $amount,
            'target_id' => $targetId,
            'note' => $note ?: null,
        ]);

        $this->success([
            'id' => $id,
            'record_date' => $recordDate,
            'amount' => $amount,
            'target_id' => $targetId,
            'note' => $note,
        ], 'Pengeluaran harian berhasil disimpan');
    }

    public function update()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id', 'record_date', 'amount', 'target_id']);

        $id = (int) $body['id'];
        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);
        $targetId = $this->resolveTargetId($body['target_id']);
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        $existing = $this->db($this->db_index)->get_where('daily_expenses', ['id' => $id], 1)->row_array();
        if (!$existing) {
            $this->error('Data tidak ditemukan', 404);
        }

        $this->db($this->db_index)->update('daily_expenses', [
            'record_date' => $recordDate,
            'amount' => $amount,
            'target_id' => $targetId,
            'note' => $note ?: null,
        ], ['id' => $id]);

        $this->success(['id' => $id], 'Pengeluaran harian berhasil diperbarui');
    }

    public function delete()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);

        $id = (int) $body['id'];
        $existing = $this->db($this->db_index)->get_where('daily_expenses', ['id' => $id], 1)->row_array();
        if (!$existing) {
            $this->error('Data tidak ditemukan', 404);
        }

        $this->db($this->db_index)->delete('daily_expenses', ['id' => $id]);
        $this->success(['id' => $id], 'Pengeluaran harian berhasil dihapus');
    }

    private function resolveTargetId($targetId): int
    {
        $id = (int) $targetId;
        if ($id <= 0) {
            $this->error('Pilih target pengeluaran', 400);
        }

        $target = $this->db($this->db_index)->get_where('expense_targets', [
            'id' => $id,
            'is_active' => 1,
        ], 1)->row_array();

        if (!$target) {
            $this->error('Target pengeluaran tidak valid', 400);
        }

        return $id;
    }
}
