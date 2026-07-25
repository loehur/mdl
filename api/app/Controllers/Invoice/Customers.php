<?php

namespace App\Controllers\Invoice;

class Customers extends InvoiceController
{
    public function list()
    {
        $this->verifyAuth();
        $userId = (int) $this->currentUser()['id'];

        try {
            $rows = $this->db($this->db_index)->query(
                "SELECT id, name, phone, email, created_at, updated_at
                 FROM customers
                 WHERE user_id = ?
                 ORDER BY name ASC",
                [$userId]
            )->result_array();

            $customers = array_map([$this, 'formatCustomer'], $rows);

            $this->success(['customers' => $customers], 'Daftar pelanggan');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat pelanggan: ' . $e->getMessage(), 500);
        }
    }

    public function detail()
    {
        $this->verifyAuth();
        $userId = (int) $this->currentUser()['id'];
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID pelanggan tidak valid', 400);
        }

        try {
            $row = $this->findCustomer($id, $userId);
            if (!$row) {
                $this->error('Pelanggan tidak ditemukan', 404);
            }

            $this->success($this->formatCustomer($row), 'Detail pelanggan');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat detail: ' . $e->getMessage(), 500);
        }
    }

    public function create()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];

        try {
            $body = $this->getBody();
            $parsed = $this->parseCustomerBody($body);

            $id = (int) $this->db($this->db_index)->insert('customers', [
                'user_id' => $userId,
                'name' => $parsed['name'],
                'phone' => $parsed['phone'],
                'email' => $parsed['email'],
            ]);

            if ($id <= 0) {
                $this->error('Gagal menyimpan pelanggan', 500);
            }

            $row = $this->findCustomer($id, $userId);
            $this->success($this->formatCustomer($row), 'Pelanggan berhasil ditambahkan');
        } catch (\Throwable $e) {
            $this->error('Gagal menambah pelanggan: ' . $e->getMessage(), 500);
        }
    }

    public function update()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];

        try {
            $body = $this->getBody();
            $id = (int) ($body['id'] ?? 0);

            if ($id <= 0) {
                $this->error('ID pelanggan tidak valid', 400);
            }

            $existing = $this->findCustomer($id, $userId);
            if (!$existing) {
                $this->error('Pelanggan tidak ditemukan', 404);
            }

            $parsed = $this->parseCustomerBody($body);

            $this->db($this->db_index)->update('customers', [
                'name' => $parsed['name'],
                'phone' => $parsed['phone'],
                'email' => $parsed['email'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            $row = $this->findCustomer($id, $userId);
            $this->success($this->formatCustomer($row), 'Pelanggan berhasil diperbarui');
        } catch (\Throwable $e) {
            $this->error('Gagal memperbarui pelanggan: ' . $e->getMessage(), 500);
        }
    }

    public function delete()
    {
        $this->verifyAuth();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $userId = (int) $this->currentUser()['id'];
        $body = $this->getBody();
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            $this->error('ID pelanggan tidak valid', 400);
        }

        try {
            $existing = $this->findCustomer($id, $userId);
            if (!$existing) {
                $this->error('Pelanggan tidak ditemukan', 404);
            }

            $used = $this->db($this->db_index)->query(
                "SELECT COUNT(*) AS cnt FROM invoices WHERE customer_id = ? AND user_id = ?",
                [$id, $userId]
            )->row_array();

            if ((int) ($used['cnt'] ?? 0) > 0) {
                $this->error('Pelanggan tidak dapat dihapus karena masih dipakai di invoice', 400);
            }

            $this->db($this->db_index)->delete('customers', ['id' => $id, 'user_id' => $userId]);

            $this->success(null, 'Pelanggan berhasil dihapus');
        } catch (\Throwable $e) {
            $this->error('Gagal menghapus pelanggan: ' . $e->getMessage(), 500);
        }
    }

    /** @return array<string, mixed>|null */
    protected function findCustomer(int $id, int $userId): ?array
    {
        $row = $this->db($this->db_index)->query(
            "SELECT id, user_id, name, phone, email, created_at, updated_at
             FROM customers
             WHERE id = ? AND user_id = ?
             LIMIT 1",
            [$id, $userId]
        )->row_array();

        return $row ?: null;
    }

    /** @return array<string, mixed> */
    protected function parseCustomerBody(array $body): array
    {
        $this->validate($body, ['name', 'phone']);

        $name = trim((string) $body['name']);
        $phone = trim((string) $body['phone']);
        $email = trim((string) ($body['email'] ?? ''));

        if ($name === '') {
            $this->error('Nama / perusahaan wajib diisi', 400);
        }

        if ($phone === '') {
            $this->error('Nomor HP wajib diisi', 400);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid', 400);
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
        ];
    }

    /** @param array<string, mixed> $row */
    protected function formatCustomer(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
