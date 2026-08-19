<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\TemplateQuota as WaDeskTemplateQuota;

/**
 * Quota — admin top-up of per-team template balances; shared by TL + agents.
 *
 * GET  /WaDesk/Quota/list
 * POST /WaDesk/Quota/topup
 * GET  /WaDesk/Quota/logs?team_id=
 * GET  /WaDesk/Quota/me
 * GET  /WaDesk/Quota/forKey?ycloud_key_id=  (admin blast UI)
 */
class Quota extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $rows = $this->db($this->db_index)->query(
            "SELECT t.id AS team_id, t.name AS team_name, t.team_leader_user_id,
                    tl.name AS leader_name, tl.email AS leader_email,
                    COALESCE(q.balance, 0) AS balance, q.updated_at AS quota_updated_at
             FROM teams t
             LEFT JOIN users tl ON tl.id = t.team_leader_user_id
             LEFT JOIN wa_team_template_quotas q ON q.team_id = t.id
             WHERE t.tenant_id = ?
             ORDER BY t.name ASC",
            [(int) $admin['tenant_id']]
        )->result_array();

        $this->success(['quotas' => $rows]);
    }

    public function topup()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $teamId = (int) ($body['team_id'] ?? 0);
        $amount = (int) ($body['amount'] ?? 0);
        $note = trim((string) ($body['note'] ?? ''));

        if ($teamId <= 0) {
            $this->error('team_id wajib', 400);
        }
        if ($amount < 1) {
            $this->error('amount harus lebih dari 0', 400);
        }
        if ($amount > 1000000) {
            $this->error('amount terlalu besar', 400);
        }

        $team = $this->db($this->db_index)->query(
            "SELECT id, tenant_id, name FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }

        $quota = new WaDeskTemplateQuota($this->db($this->db_index));
        $result = $quota->topUp(
            $teamId,
            (int) $admin['tenant_id'],
            $amount,
            (int) $admin['id'],
            $note !== '' ? $note : null
        );

        if (!$result['ok']) {
            $this->error($result['error'] ?: 'Top-up gagal', 400);
        }

        $this->success([
            'team_id' => $teamId,
            'team_name' => $team['name'],
            'balance' => $result['balance'],
            'added' => $amount,
        ], 'Kuota ditambahkan');
    }

    public function logs()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $teamId = (int) $this->query('team_id', 0);
        if ($teamId <= 0) {
            $this->error('team_id wajib', 400);
        }

        $team = $this->db($this->db_index)->query(
            "SELECT id FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }

        $page = max(1, (int) $this->query('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $rows = $this->db($this->db_index)->query(
            "SELECT l.*, u.name AS user_name
             FROM wa_team_template_quota_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.team_id = ? AND l.tenant_id = ?
             ORDER BY l.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$teamId, (int) $admin['tenant_id']]
        )->result_array();

        $total = (int) $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS cnt FROM wa_team_template_quota_logs
             WHERE team_id = ? AND tenant_id = ?",
            [$teamId, (int) $admin['tenant_id']]
        )->row_array()['cnt'];

        $this->success([
            'logs' => $rows,
            'total' => $total,
            'page' => $page,
        ]);
    }

    /** Balance for current user's team (TL/agent). Admin gets null team. */
    public function me()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();

        if (($user['role'] ?? '') === 'admin') {
            $this->success([
                'role' => 'admin',
                'team_id' => null,
                'balance' => null,
                'team_name' => null,
            ]);
        }

        $teamId = (int) ($user['team_id'] ?? 0);
        if ($teamId <= 0) {
            $this->success([
                'role' => $user['role'],
                'team_id' => null,
                'balance' => 0,
                'team_name' => null,
            ]);
        }

        $team = $this->db($this->db_index)->query(
            "SELECT id, name FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, (int) $user['tenant_id']]
        )->row_array();

        $quota = new WaDeskTemplateQuota($this->db($this->db_index));
        $quota->ensureRow($teamId, (int) $user['tenant_id']);

        $this->success([
            'role' => $user['role'],
            'team_id' => $teamId,
            'team_name' => $team['name'] ?? null,
            'balance' => $quota->getBalance($teamId),
        ]);
    }

    /** Balance for the team that owns a channel (blast UI). */
    public function forKey()
    {
        return $this->forChannel();
    }

    public function forChannel()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $channelId = (int) $this->query('channel_id', 0);
        if ($channelId <= 0) {
            $this->error('channel_id wajib', 400);
        }

        $tbl = $this->channelsTable();
        $channel = $this->db($this->db_index)->query(
            "SELECT k.id, k.team_id, k.label, t.name AS team_name
             FROM {$tbl} k
             INNER JOIN teams t ON t.id = k.team_id
             WHERE k.id = ? AND k.tenant_id = ? LIMIT 1",
            [$channelId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$channel) {
            $this->error('Channel tidak ditemukan', 404);
        }

        $quota = new WaDeskTemplateQuota($this->db($this->db_index));
        $quota->ensureRow((int) $channel['team_id'], (int) $admin['tenant_id']);

        $this->success([
            'channel_id' => (int) $channel['id'],
            'team_id' => (int) $channel['team_id'],
            'team_name' => $channel['team_name'],
            'key_label' => $channel['label'],
            'channel_label' => $channel['label'],
            'balance' => $quota->getBalance((int) $channel['team_id']),
        ]);
    }
}
