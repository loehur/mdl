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
        $tenantId = (int) $admin['tenant_id'];

        $page = max(1, (int) $this->query('page', 0));
        $limit = min(50, max(1, (int) $this->query('limit', 20)));
        $q = trim((string) $this->query('q', ''));

        $where = 't.tenant_id = ?';
        $binds = [$tenantId];
        if ($q !== '') {
            $where .= ' AND t.name LIKE ?';
            $binds[] = '%' . $q . '%';
        }

        $totalRow = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS c FROM teams t WHERE {$where}",
            $binds
        )->row_array();
        $total = (int) ($totalRow['c'] ?? 0);

        $defaultRow = $this->db($this->db_index)->query(
            "SELECT id FROM teams WHERE tenant_id = ? AND is_default = 1 LIMIT 1",
            [$tenantId]
        )->row_array();
        $defaultTeamId = (int) ($defaultRow['id'] ?? 0);

        if ($page <= 0) {
            $page = 1;
            $limit = max($limit, $total > 0 ? $total : 1);
        }

        $offset = ($page - 1) * $limit;
        $rows = $this->db($this->db_index)->query(
            "SELECT t.*, u.name AS leader_name, u.email AS leader_email,
                    (SELECT COUNT(*) FROM users a WHERE a.team_id = t.id AND a.role = 'agent') AS agent_count,
                    (SELECT COUNT(*) FROM wa_channel_teams ct WHERE ct.team_id = t.id) AS channel_count
             FROM teams t
             LEFT JOIN users u ON u.id = t.team_leader_user_id
             WHERE {$where}
             ORDER BY t.is_default DESC, t.name ASC
             LIMIT {$limit} OFFSET {$offset}",
            $binds
        )->result_array();

        $teamIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $rows);
        $channelsByTeam = $this->loadTeamChannelsMap($tenantId, $teamIds);
        foreach ($rows as &$row) {
            $row['channels'] = $channelsByTeam[(int) $row['id']] ?? [];
        }
        unset($row);

        $this->success([
            'teams' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'default_team_id' => $defaultTeamId > 0 ? $defaultTeamId : null,
        ]);
    }

    /** Ringkas — untuk dropdown di tab lain (tanpa channel detail). */
    public function options()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $rows = $this->db($this->db_index)->query(
            "SELECT t.id, t.name, t.is_default, t.team_leader_user_id,
                    u.name AS leader_name,
                    (SELECT COUNT(*) FROM users a WHERE a.team_id = t.id AND a.role = 'agent') AS agent_count
             FROM teams t
             LEFT JOIN users u ON u.id = t.team_leader_user_id
             WHERE t.tenant_id = ?
             ORDER BY t.is_default DESC, t.name ASC",
            [(int) $admin['tenant_id']]
        )->result_array();

        $defaultTeamId = 0;
        foreach ($rows as $row) {
            if ((int) ($row['is_default'] ?? 0) === 1) {
                $defaultTeamId = (int) $row['id'];
                break;
            }
        }

        $this->success([
            'teams' => $rows,
            'default_team_id' => $defaultTeamId > 0 ? $defaultTeamId : null,
        ]);
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

    /** Tetapkan satu default team untuk tenant (customer baru masuk ke team ini). */
    public function setDefault()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['team_id']);
        $teamId = (int) $body['team_id'];
        $tenantId = (int) $admin['tenant_id'];

        if (!$this->getTenantTeam($teamId, $tenantId)) {
            $this->error('Team tidak ditemukan', 404);
        }

        $this->db($this->db_index)->update('teams', ['is_default' => 0], ['tenant_id' => $tenantId]);
        $this->db($this->db_index)->update('teams', ['is_default' => 1], ['id' => $teamId, 'tenant_id' => $tenantId]);

        $this->success(['team_id' => $teamId], 'Default team disimpan');
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

        $team = $this->getTenantTeam($id, (int) $admin['tenant_id']);
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }
        if ((int) ($team['is_default'] ?? 0) === 1) {
            $this->error('Team ini masih default team. Pilih default team lain dulu di tab Teams.', 400);
        }

        $members = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS c FROM users WHERE team_id = ?",
            [$id]
        )->row_array();
        if ((int) ($members['c'] ?? 0) > 0) {
            $this->error('Hapus/pindahkan anggota team dulu', 400);
        }

        $keys = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS c FROM wa_channel_teams WHERE team_id = ?",
            [$id]
        )->row_array();
        if ((int) ($keys['c'] ?? 0) > 0) {
            $this->error('Team masih di-assign ke nomor WA. Hapus dari tab Channel dulu.', 400);
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

    /** @return array<int, list<array{id:int,label:string,phone_number:string}>> */
    private function loadTeamChannelsMap(int $tenantId, array $teamIds = []): array
    {
        $tbl = $this->channelsTable();
        $teamIds = array_values(array_filter(array_map('intval', $teamIds), static fn ($id) => $id > 0));
        $teamFilter = '';
        $binds = [$tenantId];
        if ($teamIds !== []) {
            $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
            $teamFilter = " AND link.team_id IN ({$placeholders})";
            $binds = array_merge($binds, $teamIds);
        }

        $links = $this->db($this->db_index)->query(
            "SELECT c.id, c.label, c.phone_number, link.team_id
             FROM {$tbl} c
             INNER JOIN (
               SELECT channel_id, team_id FROM wa_channel_teams
             ) link ON link.channel_id = c.id
             WHERE c.tenant_id = ? AND c.status = 'active'{$teamFilter}
             ORDER BY c.label ASC, c.id ASC",
            $binds
        )->result_array();

        $map = [];
        foreach ($links as $link) {
            $teamId = (int) ($link['team_id'] ?? 0);
            $channelId = (int) ($link['id'] ?? 0);
            if ($teamId <= 0 || $channelId <= 0) {
                continue;
            }
            if (!isset($map[$teamId])) {
                $map[$teamId] = [];
            }
            $exists = false;
            foreach ($map[$teamId] as $ch) {
                if ((int) $ch['id'] === $channelId) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                continue;
            }
            $map[$teamId][] = [
                'id' => $channelId,
                'label' => (string) ($link['label'] ?? ''),
                'phone_number' => (string) ($link['phone_number'] ?? ''),
            ];
        }
        return $map;
    }
}
