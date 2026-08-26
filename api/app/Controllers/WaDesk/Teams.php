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

        $channelsByTeam = $this->loadTeamChannelsMap((int) $admin['tenant_id']);
        foreach ($rows as &$row) {
            $row['channels'] = $channelsByTeam[(int) $row['id']] ?? [];
        }
        unset($row);

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

    /** Set default team untuk nomor WA (customer baru masuk ke team ini). */
    public function setDefaultChannel()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['team_id']);
        $teamId = (int) $body['team_id'];
        $channelId = (int) ($body['channel_id'] ?? 0);
        $tenantId = (int) $admin['tenant_id'];

        if (!$this->getTenantTeam($teamId, $tenantId)) {
            $this->error('Team tidak ditemukan', 404);
        }

        $tbl = $this->channelsTable();

        if ($channelId <= 0) {
            $current = $this->db($this->db_index)->query(
                "SELECT id FROM {$tbl} WHERE tenant_id = ? AND team_id = ? LIMIT 1",
                [$tenantId, $teamId]
            )->row_array();
            if (!$current) {
                $this->success(null, 'Tidak ada perubahan');

                return;
            }
            $this->reassignChannelDefaultAwayFromTeam((int) $current['id'], $teamId, $tenantId);
            $this->success(null, 'Default team dihapus dari nomor ini');

            return;
        }

        $channel = $this->db($this->db_index)->query(
            "SELECT * FROM {$tbl} WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$channelId, $tenantId]
        )->row_array();
        if (!$channel) {
            $this->error('Channel tidak ditemukan', 404);
        }

        $assignedIds = array_map(
            static fn ($r) => (int) ($r['id'] ?? 0),
            $this->channelTeamRows($channelId, $tenantId)
        );
        if (!in_array($teamId, $assignedIds, true)) {
            $this->error('Team belum di-assign ke nomor ini. Assign dulu di tab Channel.', 400);
        }

        if (!$this->isTeamAvailableAsDefault($tenantId, $teamId, $channelId)) {
            $prev = $this->db($this->db_index)->query(
                "SELECT id FROM {$tbl} WHERE tenant_id = ? AND team_id = ? LIMIT 1",
                [$tenantId, $teamId]
            )->row_array();
            if ($prev && (int) $prev['id'] !== $channelId) {
                $this->reassignChannelDefaultAwayFromTeam((int) $prev['id'], $teamId, $tenantId);
            }
        }

        $updated = $this->db($this->db_index)->update($tbl, ['team_id' => $teamId], ['id' => $channelId]);
        if ($updated === false) {
            $this->error('Gagal menyimpan default team.', 409);
        }

        $teamIds = array_values(array_unique(array_merge($assignedIds, [$teamId])));
        $this->syncChannelTeams($channelId, $teamIds);

        $this->success(null, 'Default team disimpan');
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
            "SELECT COUNT(*) AS c FROM {$this->channelsTable()} WHERE team_id = ?",
            [$id]
        )->row_array();
        if ((int) ($keys['c'] ?? 0) > 0) {
            $this->error('Pindahkan/hapus channel team dulu', 400);
        }

        $shared = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS c FROM wa_channel_teams WHERE team_id = ?",
            [$id]
        )->row_array();
        if ((int) ($shared['c'] ?? 0) > 0) {
            $this->error('Team masih di-assign ke nomor lain. Hapus dari channel dulu.', 400);
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

    /** @return array<int, list<array{id:int,label:string,phone_number:string,is_primary:bool}>> */
    private function loadTeamChannelsMap(int $tenantId): array
    {
        $tbl = $this->channelsTable();
        $links = $this->db($this->db_index)->query(
            "SELECT c.id, c.label, c.phone_number, c.team_id AS primary_team_id, link.team_id
             FROM {$tbl} c
             INNER JOIN (
               SELECT channel_id, team_id FROM wa_channel_teams
               UNION
               SELECT id, team_id FROM {$tbl} WHERE team_id IS NOT NULL
             ) link ON link.channel_id = c.id
             WHERE c.tenant_id = ? AND c.status = 'active'
             ORDER BY c.label ASC, c.id ASC",
            [$tenantId]
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
                'is_primary' => $teamId === (int) ($link['primary_team_id'] ?? 0),
            ];
        }
        return $map;
    }
}
