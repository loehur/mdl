<?php

namespace App\Controllers\Investasi;

/**
 * ExpenseTarget — kelola target pengeluaran
 * GET list, POST add, POST delete
 */
class ExpenseTarget extends InvestasiController
{
    public function __construct()
    {
        parent::__construct();
        $this->verifyAuth();
    }

    public function list()
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT id, name, sort_order, created_at
             FROM expense_targets
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC"
        )->result_array();

        $this->success(['items' => $rows]);
    }

    public function add()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['name']);

        $name = trim((string) $body['name']);
        if (strlen($name) < 2) {
            $this->error('Nama target minimal 2 karakter', 400);
        }
        if (strlen($name) > 100) {
            $this->error('Nama target maksimal 100 karakter', 400);
        }

        $exists = $this->db($this->db_index)->get_where('expense_targets', ['name' => $name], 1)->row_array();
        if ($exists) {
            if ((int) $exists['is_active'] === 1) {
                $this->error('Target pengeluaran sudah ada', 400);
            }
            $this->db($this->db_index)->update('expense_targets', [
                'is_active' => 1,
            ], ['id' => $exists['id']]);
            $this->success(['id' => (int) $exists['id'], 'name' => $name], 'Target pengeluaran ditambahkan');
        }

        $maxSort = $this->db($this->db_index)->query(
            "SELECT COALESCE(MAX(sort_order), 0) AS m FROM expense_targets"
        )->row_array()['m'] ?? 0;

        $id = $this->db($this->db_index)->insert('expense_targets', [
            'name' => $name,
            'sort_order' => (int) $maxSort + 1,
        ]);

        $this->success(['id' => $id, 'name' => $name], 'Target pengeluaran ditambahkan');
    }

    public function delete()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);

        $id = (int) $body['id'];
        $target = $this->db($this->db_index)->get_where('expense_targets', ['id' => $id], 1)->row_array();
        if (!$target) {
            $this->error('Target tidak ditemukan', 404);
        }

        $used = $this->db($this->db_index)->get_where('daily_expenses', ['target_id' => $id], 1)->row_array();
        if ($used) {
            $this->error('Target masih dipakai di riwayat pengeluaran', 400);
        }

        $this->db($this->db_index)->delete('expense_targets', ['id' => $id]);
        $this->success(['id' => $id], 'Target pengeluaran dihapus');
    }
}
