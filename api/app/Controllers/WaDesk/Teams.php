<?php

namespace App\Controllers\WaDesk;

/**
 * Teams — Admin CRUD teams
 */
class Teams extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $rows = $this->db($this->db_index)->query(
            "SELECT t.*, u.name AS leader_name, u.email AS leader_email,
                    (SELECT COUNT(*) FROM users a WHERE a.team_id = t.id AND a.role = 'agent') AS agent_count
             FROM teams t
             LEFT JOIN users u ON u.id = t.team_leader_user_id
             WHERE t.tenant_id = ?
             ORDER BY t.name ASC",
            [(int) $admin['tenant_id']]
        )->result_array();

        $this->success(['teams' => $rows]);
    }

    public function create()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['name']);

        $id = (int) $this->db($this->db_index)->insert('teams', [
            'tenant_id' => (int) $admin['tenant_id'],
            'name' => trim($body['name']),
        ]);

        $this->success(['id' => $id], 'Team dibuat');
    }

    public function update()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id', 'name']);
        $id = (int) $body['id'];
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            $this->error('Nama team wajib diisi', 400);
        }
        if (mb_strlen($name) > 100) {
            $this->error('Nama team maksimal 100 karakter', 400);
        }

        if (!$this->getTenantTeam($id, (int) $admin['tenant_id'])) {
            $this->error('Team tidak ditemukan', 404);
        }

        $dup = $this->db($this->db_index)->query(
            "SELECT id FROM teams WHERE tenant_id = ? AND name = ? AND id <> ? LIMIT 1",
            [(int) $admin['tenant_id'], $name, $id]
        )->row_array();
        if ($dup) {
            $this->error('Nama team sudah dipakai', 400);
        }

        $this->db($this->db_index)->update('teams', [
            'name' => $name,
        ], ['id' => $id]);

        $this->success(['id' => $id, 'name' => $name], 'Nama team diubah');
    }

    public function delete()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);
        $id = (int) $body['id'];

        if (!$this->getTenantTeam($id, (int) $admin['tenant_id'])) {
            $this->error('Team tidak ditemukan', 404);
        }

        $members = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS c FROM users WHERE team_id = ?",
            [$id]
        )->row_array();
        if ((int) ($members['c'] ?? 0) > 0) {
            $this->error('Hapus/pindahkan anggota team dulu', 400);
        }

        $keys = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS c FROM ycloud_keys WHERE team_id = ?",
            [$id]
        )->row_array();
        if ((int) ($keys['c'] ?? 0) > 0) {
            $this->error('Pindahkan/hapus API key team dulu', 400);
        }

        $this->db($this->db_index)->delete('teams', ['id' => $id]);
        $this->success(null, 'Team dihapus');
    }

    private function getTenantTeam(int $id, int $tenantId): ?array
    {
        return $this->db($this->db_index)->query(
            "SELECT * FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$id, $tenantId]
        )->row_array() ?: null;
    }
}
