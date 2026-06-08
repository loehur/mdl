<?php

namespace App\Controllers\Investasi;

/**
 * DailyIncome — GET list, POST add, POST update, POST delete
 */
class DailyIncome extends InvestasiController
{
    public function __construct()
    {
        parent::__construct();
        $this->verifyAuth();
    }

    public function list()
    {
        $month = $this->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Format month tidak valid (YYYY-MM)', 400);
        }

        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        $rows = $this->db($this->db_index)->query(
            "SELECT id, record_date, amount, note, created_at FROM daily_incomes
             WHERE record_date BETWEEN ? AND ?
             ORDER BY record_date DESC, id DESC",
            [$start, $end]
        )->result_array();

        $total = array_sum(array_map(fn($r) => (float) $r['amount'], $rows));

        $this->success([
            'month' => $month,
            'total' => $total,
            'items' => $rows,
        ]);
    }

    public function add()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['record_date', 'amount']);

        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        $id = $this->db($this->db_index)->insert('daily_incomes', [
            'record_date' => $recordDate,
            'amount' => $amount,
            'note' => $note ?: null,
        ]);

        $this->success([
            'id' => $id,
            'record_date' => $recordDate,
            'amount' => $amount,
            'note' => $note,
        ], 'Pemasukan harian berhasil disimpan');
    }

    public function update()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id', 'record_date', 'amount']);

        $id = (int) $body['id'];
        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        $existing = $this->db($this->db_index)->get_where('daily_incomes', ['id' => $id], 1)->row_array();
        if (!$existing) {
            $this->error('Data tidak ditemukan', 404);
        }

        $this->db($this->db_index)->update('daily_incomes', [
            'record_date' => $recordDate,
            'amount' => $amount,
            'note' => $note ?: null,
        ], ['id' => $id]);

        $this->success(['id' => $id], 'Pemasukan harian berhasil diperbarui');
    }

    public function delete()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);

        $id = (int) $body['id'];
        $existing = $this->db($this->db_index)->get_where('daily_incomes', ['id' => $id], 1)->row_array();
        if (!$existing) {
            $this->error('Data tidak ditemukan', 404);
        }

        $this->db($this->db_index)->delete('daily_incomes', ['id' => $id]);
        $this->success(['id' => $id], 'Pemasukan harian berhasil dihapus');
    }
}
