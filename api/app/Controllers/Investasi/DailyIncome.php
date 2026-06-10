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
        $period = $this->resolveListPeriod(
            $this->query('from'),
            $this->query('to'),
            $this->query('month')
        );

        $rows = $this->db($this->db_index)->query(
            "SELECT d.id, d.record_date, d.amount, d.source_id, d.note, d.created_at,
                    s.name AS source_name
             FROM daily_incomes d
             LEFT JOIN income_sources s ON s.id = d.source_id
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
        $this->validate($body, ['record_date', 'amount', 'source_id']);

        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);
        $sourceId = $this->resolveSourceId($body['source_id']);
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        $id = $this->db($this->db_index)->insert('daily_incomes', [
            'record_date' => $recordDate,
            'amount' => $amount,
            'source_id' => $sourceId,
            'note' => $note ?: null,
        ]);

        $this->success([
            'id' => $id,
            'record_date' => $recordDate,
            'amount' => $amount,
            'source_id' => $sourceId,
            'note' => $note,
        ], 'Pemasukan harian berhasil disimpan');
    }

    public function update()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id', 'record_date', 'amount', 'source_id']);

        $id = (int) $body['id'];
        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);
        $sourceId = $this->resolveSourceId($body['source_id']);
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        $existing = $this->db($this->db_index)->get_where('daily_incomes', ['id' => $id], 1)->row_array();
        if (!$existing) {
            $this->error('Data tidak ditemukan', 404);
        }

        $this->db($this->db_index)->update('daily_incomes', [
            'record_date' => $recordDate,
            'amount' => $amount,
            'source_id' => $sourceId,
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

    private function resolveSourceId($sourceId): int
    {
        $id = (int) $sourceId;
        if ($id <= 0) {
            $this->error('Pilih sumber pemasukan', 400);
        }

        $source = $this->db($this->db_index)->get_where('income_sources', [
            'id' => $id,
            'is_active' => 1,
        ], 1)->row_array();

        if (!$source) {
            $this->error('Sumber pemasukan tidak valid', 400);
        }

        return $id;
    }
}
