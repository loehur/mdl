<?php

namespace App\Controllers\Investasi;

/**
 * IncomeSource — kelola sumber pemasukan
 * GET list, POST add, POST delete
 */
class IncomeSource extends InvestasiController
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
             FROM income_sources
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
            $this->error('Nama sumber minimal 2 karakter', 400);
        }
        if (strlen($name) > 100) {
            $this->error('Nama sumber maksimal 100 karakter', 400);
        }

        $exists = $this->db($this->db_index)->get_where('income_sources', ['name' => $name], 1)->row_array();
        if ($exists) {
            if ((int) $exists['is_active'] === 1) {
                $this->error('Sumber pemasukan sudah ada', 400);
            }
            $this->db($this->db_index)->update('income_sources', [
                'is_active' => 1,
            ], ['id' => $exists['id']]);
            $this->success(['id' => (int) $exists['id'], 'name' => $name], 'Sumber pemasukan ditambahkan');
        }

        $maxSort = $this->db($this->db_index)->query(
            "SELECT COALESCE(MAX(sort_order), 0) AS m FROM income_sources"
        )->row_array()['m'] ?? 0;

        $id = $this->db($this->db_index)->insert('income_sources', [
            'name' => $name,
            'sort_order' => (int) $maxSort + 1,
        ]);

        $this->success(['id' => $id, 'name' => $name], 'Sumber pemasukan ditambahkan');
    }

    public function delete()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);

        $id = (int) $body['id'];
        $source = $this->db($this->db_index)->get_where('income_sources', ['id' => $id], 1)->row_array();
        if (!$source) {
            $this->error('Sumber tidak ditemukan', 404);
        }

        $used = $this->db($this->db_index)->get_where('daily_incomes', ['source_id' => $id], 1)->row_array();
        if ($used) {
            $this->error('Sumber masih dipakai di riwayat pemasukan', 400);
        }

        $this->db($this->db_index)->delete('income_sources', ['id' => $id]);
        $this->success(['id' => $id], 'Sumber pemasukan dihapus');
    }
}
