<?php

namespace App\Controllers\Investasi;

/**
 * Investment — deposit & withdrawal movements
 */
class Investment extends InvestasiController
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
            "SELECT id, movement_type, amount, record_date, note, created_at
             FROM investment_movements
             WHERE record_date BETWEEN ? AND ?
             ORDER BY record_date DESC, id DESC",
            [$start, $end]
        )->result_array();

        $depositTotal = 0;
        $withdrawalTotal = 0;
        foreach ($rows as $row) {
            if ($row['movement_type'] === 'deposit') {
                $depositTotal += (float) $row['amount'];
            } else {
                $withdrawalTotal += (float) $row['amount'];
            }
        }

        $this->success([
            'month' => $month,
            'deposit_total' => $depositTotal,
            'withdrawal_total' => $withdrawalTotal,
            'net' => $depositTotal - $withdrawalTotal,
            'items' => $rows,
        ]);
    }

    public function add()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['movement_type', 'record_date', 'amount']);

        $type = $body['movement_type'];
        if (!in_array($type, ['deposit', 'withdrawal'], true)) {
            $this->error('movement_type harus deposit atau withdrawal', 400);
        }

        $recordDate = $this->sanitizeDate($body['record_date']);
        $amount = $this->sanitizeAmount($body['amount']);
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        $id = $this->db($this->db_index)->insert('investment_movements', [
            'movement_type' => $type,
            'record_date' => $recordDate,
            'amount' => $amount,
            'note' => $note ?: null,
        ]);

        $label = $type === 'deposit' ? 'Deposit' : 'Penarikan';

        $this->success([
            'id' => $id,
            'movement_type' => $type,
            'record_date' => $recordDate,
            'amount' => $amount,
            'note' => $note,
        ], "{$label} investasi berhasil disimpan");
    }

    public function delete()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);

        $id = (int) $body['id'];
        $existing = $this->db($this->db_index)->get_where('investment_movements', ['id' => $id], 1)->row_array();
        if (!$existing) {
            $this->error('Data tidak ditemukan', 404);
        }

        $this->db($this->db_index)->delete('investment_movements', ['id' => $id]);
        $this->success(['id' => $id], 'Transaksi investasi berhasil dihapus');
    }
}
