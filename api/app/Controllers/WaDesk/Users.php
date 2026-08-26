<?php

namespace App\Controllers\WaDesk;

/**
 * Users — Admin CRUD team_leader & agent
 */
class Users extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];

        $role = $this->query('role');
        $teamId = $this->query('team_id');
        $pageRaw = $this->query('page');
        $q = trim((string) $this->query('q', ''));

        $select = "SELECT u.id, u.email, u.name, u.role, u.team_id, u.is_active, u.created_at,
                          t.name AS team_name,
                          t.team_leader_user_id,
                          tl.name AS team_leader_name,
                          tl.email AS team_leader_email,
                          (SELECT COUNT(*) FROM users a
                           WHERE a.team_id = u.team_id AND a.role = 'agent'
                             AND a.tenant_id = u.tenant_id AND a.id <> u.id) AS agent_count";

        $where = "u.tenant_id = ? AND u.role IN ('team_leader', 'agent')";
        $binds = [$tenantId];

        if ($role === 'team_leader' || $role === 'agent') {
            $where .= ' AND u.role = ?';
            $binds[] = $role;
        }
        if ($teamId !== null && $teamId !== '') {
            $where .= ' AND u.team_id = ?';
            $binds[] = (int) $teamId;
        }
        if ($q !== '') {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR t.name LIKE ? OR tl.name LIKE ?)';
            $like = '%' . $q . '%';
            $binds = array_merge($binds, [$like, $like, $like, $like]);
        }

        $from = "FROM users u
                 LEFT JOIN teams t ON t.id = u.team_id
                 LEFT JOIN users tl ON tl.id = t.team_leader_user_id";

        if ($pageRaw !== null && $pageRaw !== '') {
            $page = max(1, (int) $pageRaw);
            $limit = min(50, max(1, (int) $this->query('limit', 20)));
            $totalRow = $this->db($this->db_index)->query(
                "SELECT COUNT(*) AS c {$from} WHERE {$where}",
                $binds
            )->row_array();
            $total = (int) ($totalRow['c'] ?? 0);
            $offset = ($page - 1) * $limit;
            $rows = $this->db($this->db_index)->query(
                "{$select} {$from} WHERE {$where}
                 ORDER BY u.role ASC, u.name ASC
                 LIMIT {$limit} OFFSET {$offset}",
                $binds
            )->result_array();
            $this->success([
                'users' => $rows,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ]);

            return;
        }

        $rows = $this->db($this->db_index)->query(
            "{$select} {$from} WHERE {$where} ORDER BY u.role ASC, u.name ASC",
            $binds
        )->result_array();
        $this->success(['users' => $rows]);
    }

    /** Ringkas — team leader aktif untuk dropdown form user. */
    public function leaders()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $rows = $this->db($this->db_index)->query(
            "SELECT u.id, u.name, u.email, u.team_id, t.name AS team_name
             FROM users u
             LEFT JOIN teams t ON t.id = u.team_id
             WHERE u.tenant_id = ? AND u.role = 'team_leader' AND u.is_active = 1
             ORDER BY u.name ASC",
            [(int) $admin['tenant_id']]
        )->result_array();

        $this->success(['leaders' => $rows]);
    }

    public function create()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['name', 'email', 'password', 'role']);

        $role = $body['role'];
        if (!in_array($role, ['team_leader', 'agent'], true)) {
            $this->error('Role harus team_leader atau agent', 400);
        }

        $teamId = 0;

        if ($role === 'team_leader') {
            if (empty($body['team_id'])) {
                $this->error('Team Leader wajib memilih team', 400);
            }
            $teamId = (int) $body['team_id'];
            $team = $this->db($this->db_index)->query(
                "SELECT * FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$teamId, (int) $admin['tenant_id']]
            )->row_array();
            if (!$team) {
                $this->error('Team tidak ditemukan', 404);
            }
            if (!empty($team['team_leader_user_id'])) {
                $this->error('Team sudah punya team leader', 400);
            }
        } else {
            // Agent wajib punya team leader
            if (empty($body['team_leader_user_id'])) {
                $this->error('Agent wajib memilih team leader', 400);
            }
            $leaderId = (int) $body['team_leader_user_id'];
            $leader = $this->db($this->db_index)->query(
                "SELECT u.id, u.team_id, u.role, t.team_leader_user_id
                 FROM users u
                 INNER JOIN teams t ON t.id = u.team_id
                 WHERE u.id = ? AND u.tenant_id = ? AND u.role = 'team_leader' AND u.is_active = 1
                 LIMIT 1",
                [$leaderId, (int) $admin['tenant_id']]
            )->row_array();
            if (!$leader) {
                $this->error('Team leader tidak ditemukan / tidak aktif', 404);
            }
            if ((int) ($leader['team_leader_user_id'] ?? 0) !== $leaderId) {
                $this->error('User tersebut bukan team leader resmi pada team-nya', 400);
            }
            if (empty($leader['team_id'])) {
                $this->error('Team leader belum terhubung ke team', 400);
            }
            $teamId = (int) $leader['team_id'];
        }

        $email = strtolower(trim($body['email']));
        $exists = $this->db($this->db_index)->get_where('users', ['email' => $email], 1)->row_array();
        if ($exists) {
            $this->error('Email sudah terdaftar', 409);
        }

        $password = (string) $body['password'];
        if (strlen($password) < 6) {
            $this->error('Password minimal 6 karakter', 400);
        }

        $userId = (int) $this->db($this->db_index)->insert('users', [
            'tenant_id' => (int) $admin['tenant_id'],
            'team_id' => $teamId,
            'email' => $email,
            'name' => trim($body['name']),
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => 1,
        ]);

        if ($role === 'team_leader') {
            $this->db($this->db_index)->update('teams', [
                'team_leader_user_id' => $userId,
            ], ['id' => $teamId]);
        }

        $this->success([
            'id' => $userId,
            'team_id' => $teamId,
            'role' => $role,
        ], 'User dibuat');
    }

    public function update()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);
        $id = (int) $body['id'];

        $user = $this->db($this->db_index)->query(
            "SELECT * FROM users WHERE id = ? AND tenant_id = ? AND role IN ('team_leader','agent') LIMIT 1",
            [$id, (int) $admin['tenant_id']]
        )->row_array();
        if (!$user) {
            $this->error('User tidak ditemukan', 404);
        }

        $data = [];
        if (isset($body['name'])) {
            $data['name'] = trim($body['name']);
        }
        if (isset($body['email'])) {
            $email = strtolower(trim($body['email']));
            $dup = $this->db($this->db_index)->query(
                "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1",
                [$email, $id]
            )->row_array();
            if ($dup) {
                $this->error('Email sudah dipakai', 409);
            }
            $data['email'] = $email;
        }
        if (!empty($body['password'])) {
            if (strlen((string) $body['password']) < 6) {
                $this->error('Password minimal 6 karakter', 400);
            }
            $data['password'] = password_hash((string) $body['password'], PASSWORD_DEFAULT);
        }
        if (isset($body['is_active'])) {
            $data['is_active'] = (int) ((bool) $body['is_active']);
        }
        if (isset($body['team_id']) || isset($body['team_leader_user_id'])) {
            if ($user['role'] === 'agent' || (isset($body['role']) && $body['role'] === 'agent')) {
                // Agent: pindah via team leader
                if (empty($body['team_leader_user_id']) && empty($body['team_id'])) {
                    $this->error('Agent wajib memilih team leader', 400);
                }
                if (!empty($body['team_leader_user_id'])) {
                    $leaderId = (int) $body['team_leader_user_id'];
                    $leader = $this->db($this->db_index)->query(
                        "SELECT u.id, u.team_id, t.team_leader_user_id
                         FROM users u
                         INNER JOIN teams t ON t.id = u.team_id
                         WHERE u.id = ? AND u.tenant_id = ? AND u.role = 'team_leader' AND u.is_active = 1
                         LIMIT 1",
                        [$leaderId, (int) $admin['tenant_id']]
                    )->row_array();
                    if (!$leader || (int) $leader['team_leader_user_id'] !== $leaderId) {
                        $this->error('Team leader tidak valid', 400);
                    }
                    $data['team_id'] = (int) $leader['team_id'];
                } elseif (isset($body['team_id'])) {
                    $teamId = (int) $body['team_id'];
                    $team = $this->db($this->db_index)->query(
                        "SELECT * FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
                        [$teamId, (int) $admin['tenant_id']]
                    )->row_array();
                    if (!$team) {
                        $this->error('Team tidak ditemukan', 404);
                    }
                    if (empty($team['team_leader_user_id'])) {
                        $this->error('Team belum punya team leader; agent wajib punya TL', 400);
                    }
                    $data['team_id'] = $teamId;
                }
            } elseif (isset($body['team_id'])) {
                $teamId = (int) $body['team_id'];
                $team = $this->db($this->db_index)->query(
                    "SELECT * FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
                    [$teamId, (int) $admin['tenant_id']]
                )->row_array();
                if (!$team) {
                    $this->error('Team tidak ditemukan', 404);
                }
                $data['team_id'] = $teamId;
            }
        }

        if ($data) {
            $this->db($this->db_index)->update('users', $data, ['id' => $id]);
        }

        if (($user['role'] === 'team_leader') && isset($data['team_id']) && (int) $data['team_id'] !== (int) $user['team_id']) {
            $this->db($this->db_index)->update('teams', ['team_leader_user_id' => null], ['id' => (int) $user['team_id']]);
            $this->db($this->db_index)->update('teams', ['team_leader_user_id' => $id], ['id' => (int) $data['team_id']]);
        }

        $this->success(null, 'User diupdate');
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

        $user = $this->db($this->db_index)->query(
            "SELECT * FROM users WHERE id = ? AND tenant_id = ? AND role IN ('team_leader','agent') LIMIT 1",
            [$id, (int) $admin['tenant_id']]
        )->row_array();
        if (!$user) {
            $this->error('User tidak ditemukan', 404);
        }

        if ($user['role'] === 'team_leader' && $user['team_id']) {
            $agents = $this->db($this->db_index)->query(
                "SELECT COUNT(*) AS c FROM users
                 WHERE team_id = ? AND role = 'agent' AND id <> ?",
                [(int) $user['team_id'], $id]
            )->row_array();
            if ((int) ($agents['c'] ?? 0) > 0) {
                $this->error('Hapus/pindahkan agent di bawah Team Leader ini dulu', 400);
            }

            $this->db($this->db_index)->update('teams', [
                'team_leader_user_id' => null,
            ], ['id' => (int) $user['team_id']]);
        }

        $this->db($this->db_index)->delete('wadesk_tokens', ['user_id' => $id]);
        $this->db($this->db_index)->delete('users', ['id' => $id]);
        $this->success(null, 'User dihapus');
    }

    /**
     * Promote agent → team_leader; current TL of that team demotes → agent.
     * POST body: { id }  (agent user id)
     */
    public function promoteToLeader()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);
        $agentId = (int) $body['id'];

        $agent = $this->db($this->db_index)->query(
            "SELECT * FROM users
             WHERE id = ? AND tenant_id = ? AND role = 'agent' AND is_active = 1
             LIMIT 1",
            [$agentId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$agent) {
            $this->error('Agent tidak ditemukan / tidak aktif', 404);
        }

        $teamId = (int) ($agent['team_id'] ?? 0);
        if ($teamId <= 0) {
            $this->error('Agent belum terhubung ke team', 400);
        }

        $team = $this->db($this->db_index)->query(
            "SELECT * FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }

        $oldLeaderId = (int) ($team['team_leader_user_id'] ?? 0);
        if ($oldLeaderId > 0 && $oldLeaderId === $agentId) {
            $this->error('User sudah menjadi team leader team ini', 400);
        }

        $oldLeader = null;
        if ($oldLeaderId > 0) {
            $oldLeader = $this->db($this->db_index)->query(
                "SELECT * FROM users
                 WHERE id = ? AND tenant_id = ? AND role = 'team_leader'
                 LIMIT 1",
                [$oldLeaderId, (int) $admin['tenant_id']]
            )->row_array();
        }

        // Demote old TL → agent (same team)
        if ($oldLeader) {
            $this->db($this->db_index)->update('users', [
                'role' => 'agent',
                'team_id' => $teamId,
            ], ['id' => $oldLeaderId]);
        }

        // Promote agent → TL
        $this->db($this->db_index)->update('users', [
            'role' => 'team_leader',
            'team_id' => $teamId,
        ], ['id' => $agentId]);

        $this->db($this->db_index)->update('teams', [
            'team_leader_user_id' => $agentId,
        ], ['id' => $teamId]);

        $this->success([
            'team_id' => $teamId,
            'new_leader_id' => $agentId,
            'demoted_leader_id' => $oldLeaderId ?: null,
        ], $oldLeader
            ? 'Agent dipromosikan jadi Team Leader; TL lama turun jadi agent'
            : 'Agent dipromosikan jadi Team Leader');
    }
}
