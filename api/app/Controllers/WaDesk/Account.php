<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\TemplateQuota as WaDeskTemplateQuota;

/**
 * Account — profil, team, kuota (self-service).
 *
 * GET  /WaDesk/Account/profile
 * POST /WaDesk/Account/updateProfile
 * POST /WaDesk/Account/changePassword
 * GET  /WaDesk/Account/team
 * POST /WaDesk/Account/addAgent
 * GET  /WaDesk/Account/quota
 */
class Account extends WaDeskController
{
    public function profile()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();

        $row = $this->db($this->db_index)->query(
            "SELECT u.id, u.name, u.email, u.role, u.tenant_id, u.team_id, u.is_active, u.created_at,
                    t.name AS team_name,
                    tn.name AS tenant_name
             FROM users u
             LEFT JOIN teams t ON t.id = u.team_id
             INNER JOIN tenants tn ON tn.id = u.tenant_id
             WHERE u.id = ?
             LIMIT 1",
            [(int) $user['id']]
        )->row_array();

        if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
            $this->error('Akun tidak ditemukan', 404);
        }

        $this->success([
            'profile' => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'tenant_id' => (int) $row['tenant_id'],
                'tenant_name' => $row['tenant_name'],
                'team_id' => $row['team_id'] !== null ? (int) $row['team_id'] : null,
                'team_name' => $row['team_name'] ?: null,
                'created_at' => $row['created_at'],
            ],
        ]);
    }

    public function updateProfile()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['name']);
        $name = trim((string) $body['name']);
        if ($name === '') {
            $this->error('Nama wajib diisi', 400);
        }

        $this->db($this->db_index)->update('users', [
            'name' => $name,
        ], ['id' => (int) $user['id']]);

        $public = $this->loadPublicUser((int) $user['id']);
        if ($public) {
            $this->establishSession($public);
        }

        $this->success(['user' => $public], 'Profil diperbarui');
    }

    public function changePassword()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['current_password', 'new_password']);

        $current = (string) $body['current_password'];
        $newPassword = (string) $body['new_password'];
        if (strlen($newPassword) < 6) {
            $this->error('Password baru minimal 6 karakter', 400);
        }

        $row = $this->db($this->db_index)->query(
            "SELECT password FROM users WHERE id = ? LIMIT 1",
            [(int) $user['id']]
        )->row_array();
        if (!$row || !password_verify($current, $row['password'])) {
            $this->error('Password saat ini salah', 401);
        }

        $this->db($this->db_index)->update('users', [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ], ['id' => (int) $user['id']]);

        $this->success(null, 'Password berhasil diubah');
    }

    public function team()
    {
        $this->verifyAuth();
        $user = $this->requireTeamLeaderOrAdmin();
        $tenantId = (int) $user['tenant_id'];
        $teamId = $this->resolveManagedTeamId($user);
        $viewerIsAdmin = ($user['role'] ?? '') === 'admin';

        $team = $this->db($this->db_index)->query(
            "SELECT t.id, t.name, t.team_leader_user_id, t.created_at,
                    tl.name AS leader_name, tl.email AS leader_email
             FROM teams t
             LEFT JOIN users tl ON tl.id = t.team_leader_user_id
             WHERE t.id = ? AND t.tenant_id = ?
             LIMIT 1",
            [$teamId, $tenantId]
        )->row_array();
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }

        $where = 'u.team_id = ? AND u.tenant_id = ? AND u.is_active = 1';
        $binds = [$teamId, $tenantId];
        if (!$viewerIsAdmin) {
            $where .= " AND u.role <> 'admin'";
        }

        $members = $this->db($this->db_index)->query(
            "SELECT u.id, u.name, u.email, u.role, u.created_at,
                    (u.id = ?) AS is_self
             FROM users u
             WHERE {$where}
             ORDER BY FIELD(u.role, 'team_leader', 'agent', 'admin'), u.name ASC",
            array_merge([(int) $user['id']], $binds)
        )->result_array();

        $agentCount = $this->countTeamAgents($teamId, $tenantId);

        $this->success([
            'team' => [
                'id' => (int) $team['id'],
                'name' => $team['name'],
                'leader_name' => $team['leader_name'],
                'leader_email' => $team['leader_email'],
                'created_at' => $team['created_at'],
            ],
            'members' => array_map(static function (array $m) {
                return [
                    'id' => (int) $m['id'],
                    'name' => $m['name'],
                    'email' => $m['email'],
                    'role' => $m['role'],
                    'created_at' => $m['created_at'],
                    'is_self' => (bool) ($m['is_self'] ?? false),
                ];
            }, $members),
            'agent_count' => $agentCount,
            'max_agents' => self::MAX_AGENTS_PER_TEAM,
            'can_add_agent' => $agentCount < self::MAX_AGENTS_PER_TEAM,
        ]);
    }

    public function addAgent()
    {
        $this->verifyAuth();
        $user = $this->requireTeamLeaderOrAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $tenantId = (int) $user['tenant_id'];
        $teamId = $this->resolveManagedTeamId($user);

        $body = $this->getBody();
        $this->validate($body, ['name', 'email', 'password']);

        $agentCount = $this->countTeamAgents($teamId, $tenantId);
        if ($agentCount >= self::MAX_AGENTS_PER_TEAM) {
            $this->error(
                'Team sudah memiliki maksimal ' . self::MAX_AGENTS_PER_TEAM . ' agent',
                400,
                ['code' => 'max_agents', 'max_agents' => self::MAX_AGENTS_PER_TEAM]
            );
        }

        $team = $this->db($this->db_index)->query(
            "SELECT id, team_leader_user_id FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, $tenantId]
        )->row_array();
        if (!$team || empty($team['team_leader_user_id'])) {
            $this->error('Team belum punya Team Leader resmi', 400);
        }

        $email = strtolower(trim((string) $body['email']));
        $exists = $this->db($this->db_index)->get_where('users', ['email' => $email], 1)->row_array();
        if ($exists) {
            $this->error('Email sudah terdaftar', 409);
        }

        $password = (string) $body['password'];
        if (strlen($password) < 6) {
            $this->error('Password minimal 6 karakter', 400);
        }

        $userId = (int) $this->db($this->db_index)->insert('users', [
            'tenant_id' => $tenantId,
            'team_id' => $teamId,
            'email' => $email,
            'name' => trim((string) $body['name']),
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'agent',
            'is_active' => 1,
        ]);

        $this->success([
            'id' => $userId,
            'team_id' => $teamId,
            'role' => 'agent',
            'agent_count' => $agentCount + 1,
            'max_agents' => self::MAX_AGENTS_PER_TEAM,
        ], 'Agent ditambahkan ke team');
    }

    public function quota()
    {
        $this->verifyAuth();
        $user = $this->requireTeamLeaderOrAdmin();
        $tenantId = (int) $user['tenant_id'];
        $teamId = $this->resolveManagedTeamId($user);

        $team = $this->db($this->db_index)->query(
            "SELECT id, name FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, $tenantId]
        )->row_array();
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }

        $quota = new WaDeskTemplateQuota($this->db($this->db_index));
        $quota->ensureRow($teamId, $tenantId);
        $balance = $quota->getBalance($teamId);

        $category = strtolower(trim((string) $this->query('category', 'all')));
        $typeFilter = '';
        if ($category === 'topup') {
            $typeFilter = " AND l.type = 'topup'";
        } elseif ($category === 'usage') {
            $typeFilter = " AND l.type IN ('consume', 'adjust')";
        } elseif ($category !== '' && $category !== 'all') {
            $this->error('category harus topup, usage, atau all', 400);
        }

        $page = max(1, (int) $this->query('page', 1));
        $limit = min(50, max(1, (int) $this->query('limit', 20)));
        $offset = ($page - 1) * $limit;

        $rows = $this->db($this->db_index)->query(
            "SELECT l.id, l.type, l.amount, l.balance_after, l.note, l.source, l.created_at,
                    u.name AS user_name
             FROM wa_team_template_quota_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.team_id = ? AND l.tenant_id = ?{$typeFilter}
             ORDER BY l.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$teamId, $tenantId]
        )->result_array();

        $totalRow = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS cnt FROM wa_team_template_quota_logs l
             WHERE l.team_id = ? AND l.tenant_id = ?{$typeFilter}",
            [$teamId, $tenantId]
        )->row_array();
        $total = (int) ($totalRow['cnt'] ?? 0);
        $loaded = $offset + count($rows);

        $this->success([
            'team_id' => $teamId,
            'team_name' => $team['name'],
            'balance' => $balance,
            'category' => $category !== '' ? $category : 'all',
            'logs' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $loaded < $total,
        ]);
    }
}
