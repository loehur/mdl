<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\Kirimin as WaDeskKirimin;

/**
 * Channels — Kirimin device/nomor mapped 1:1 to team (API key per tenant).
 */
class Channels extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();
        $tbl = $this->channelsTable();
        $scope = trim((string) $this->query('scope', 'operational'));

        if ($user['role'] === 'admin' && $scope === 'all') {
            $rows = $this->db($this->db_index)->query(
                "SELECT k.id, k.tenant_id, k.team_id, k.label, k.phone_number, k.device_id,
                        k.channel_type, k.status, k.created_at, t.name AS team_name
                 FROM {$tbl} k
                 LEFT JOIN teams t ON t.id = k.team_id
                 WHERE k.tenant_id = ?
                 ORDER BY k.id DESC",
                [(int) $user['tenant_id']]
            )->result_array();
        } elseif ($this->hasOperationalTeam($user)) {
            $rows = $this->db($this->db_index)->query(
                "SELECT k.id, k.tenant_id, k.team_id, k.label, k.phone_number, k.device_id,
                        k.channel_type, k.status, k.created_at, t.name AS team_name
                 FROM {$tbl} k
                 LEFT JOIN teams t ON t.id = k.team_id
                 WHERE k.tenant_id = ? AND k.team_id = ? AND k.status = 'active'
                 ORDER BY k.id DESC",
                [(int) $user['tenant_id'], (int) $user['team_id']]
            )->result_array();
        } else {
            $rows = [];
        }

        $channels = array_map(fn ($r) => $this->mapChannelRow($r), $rows);
        $this->success(['channels' => $channels, 'keys' => $channels]);
    }

    public function syncFromKirimin()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];

        $client = $this->requireKiriminConfigured($tenantId);
        $fetched = $client->listDevices();
        if (!$fetched['success']) {
            $this->error('Gagal ambil device dari Kirimin: ' . ($fetched['error'] ?: 'unknown'), 502);
        }

        $tbl = $this->channelsTable();
        $assigned = $this->db($this->db_index)->query(
            "SELECT id, device_id, team_id, label, phone_number, status
             FROM {$tbl} WHERE tenant_id = ?",
            [(int) $admin['tenant_id']]
        )->result_array();

        $byDevice = [];
        foreach ($assigned as $row) {
            $did = trim((string) ($row['device_id'] ?? ''));
            if ($did !== '') {
                $byDevice[$did] = $this->mapChannelRow($row);
            }
        }

        $devices = [];
        foreach ($fetched['devices'] as $dev) {
            if (!is_array($dev)) {
                continue;
            }
            $deviceId = trim((string) (
                $dev['device_id'] ?? $dev['id'] ?? $dev['deviceId'] ?? ''
            ));
            if ($deviceId === '') {
                continue;
            }
            $phone = $this->normalizePhone((string) (
                $dev['phone'] ?? $dev['phone_number'] ?? $dev['number'] ?? $dev['device_number'] ?? ''
            ));
            $typeRaw = strtolower((string) ($dev['type'] ?? $dev['device_type'] ?? 'waba'));
            $channelType = str_contains($typeRaw, 'waba') ? 'waba' : 'device';
            $devices[] = [
                'device_id' => $deviceId,
                'phone_number' => $phone,
                'label' => trim((string) ($dev['name'] ?? $dev['label'] ?? $deviceId)),
                'channel_type' => $channelType,
                'status' => (string) ($dev['status'] ?? $dev['connection_status'] ?? ''),
                'assigned' => $byDevice[$deviceId] ?? null,
            ];
        }

        $this->success([
            'devices' => $devices,
            'channels' => array_values($byDevice),
        ]);
    }

    public function assign()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['device_id', 'team_id', 'label']);

        $teamId = (int) $body['team_id'];
        $team = $this->db($this->db_index)->query(
            "SELECT id FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }

        $deviceId = trim((string) $body['device_id']);
        $tbl = $this->channelsTable();

        $teamTaken = $this->db($this->db_index)->query(
            "SELECT id FROM {$tbl} WHERE tenant_id = ? AND team_id = ? LIMIT 1",
            [(int) $admin['tenant_id'], $teamId]
        )->row_array();
        if ($teamTaken) {
            $this->error('Team sudah punya channel/nomor. Hapus mapping lama dulu.', 409);
        }

        $deviceTaken = $this->db($this->db_index)->query(
            "SELECT id FROM {$tbl} WHERE device_id = ? LIMIT 1",
            [$deviceId]
        )->row_array();
        if ($deviceTaken) {
            $this->error('Device sudah di-assign ke team lain', 409);
        }

        $phone = isset($body['phone_number'])
            ? $this->normalizePhone((string) $body['phone_number'])
            : '';
        if ($phone === '') {
            $phone = $this->resolveDevicePhone($deviceId) ?: $deviceId;
        }

        $channelType = strtolower((string) ($body['channel_type'] ?? 'waba'));
        if (!in_array($channelType, ['waba', 'device'], true)) {
            $channelType = 'waba';
        }

        $insertData = [
            'tenant_id' => (int) $admin['tenant_id'],
            'team_id' => $teamId,
            'label' => trim($body['label']),
            'device_id' => $deviceId,
            'channel_type' => $channelType,
            'phone_number' => $phone,
            'status' => 'active',
        ];

        $id = (int) $this->db($this->db_index)->insert($tbl, $insertData);
        $this->success(['id' => $id, 'channel_id' => $id], 'Channel di-assign');
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
        $tbl = $this->channelsTable();

        $channel = $this->db($this->db_index)->query(
            "SELECT * FROM {$tbl} WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$id, (int) $admin['tenant_id']]
        )->row_array();
        if (!$channel) {
            $this->error('Channel tidak ditemukan', 404);
        }

        $data = [];
        if (isset($body['label'])) {
            $data['label'] = trim($body['label']);
        }
        if (isset($body['phone_number'])) {
            $data['phone_number'] = $this->normalizePhone($body['phone_number']);
        }
        if (isset($body['status']) && in_array($body['status'], ['active', 'inactive'], true)) {
            $data['status'] = $body['status'];
        }
        if (isset($body['team_id'])) {
            $teamId = (int) $body['team_id'];
            $team = $this->db($this->db_index)->query(
                "SELECT id FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$teamId, (int) $admin['tenant_id']]
            )->row_array();
            if (!$team) {
                $this->error('Team tidak ditemukan', 404);
            }
            $other = $this->db($this->db_index)->query(
                "SELECT id FROM {$tbl} WHERE tenant_id = ? AND team_id = ? AND id <> ? LIMIT 1",
                [(int) $admin['tenant_id'], $teamId, $id]
            )->row_array();
            if ($other) {
                $this->error('Team sudah punya channel lain', 409);
            }
            $data['team_id'] = $teamId;
        }

        if ($data) {
            $this->db($this->db_index)->update($tbl, $data, ['id' => $id]);
            if (isset($data['team_id']) && (int) $data['team_id'] !== (int) $channel['team_id']) {
                $this->db($this->db_index)->update('conversations', [
                    'team_id' => (int) $data['team_id'],
                ], ['channel_id' => $id]);
            }
        }

        $this->success(null, 'Channel diupdate');
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
        $tbl = $this->channelsTable();

        $channel = $this->db($this->db_index)->query(
            "SELECT id FROM {$tbl} WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$id, (int) $admin['tenant_id']]
        )->row_array();
        if (!$channel) {
            $this->error('Channel tidak ditemukan', 404);
        }

        $this->db($this->db_index)->delete($tbl, ['id' => $id]);
        $this->success(null, 'Channel dihapus');
    }

    /** Backward compat aliases */
    public function create()
    {
        return $this->assign();
    }

    private function resolveDevicePhone(string $deviceId): string
    {
        try {
            $client = $this->requireKiriminConfigured((int) ($this->currentUser()['tenant_id'] ?? 0));
            $fetched = $client->listDevices();
            if (!$fetched['success']) {
                return '';
            }
            foreach ($fetched['devices'] as $dev) {
                if (!is_array($dev)) {
                    continue;
                }
                $id = trim((string) ($dev['device_id'] ?? $dev['id'] ?? ''));
                if ($id === $deviceId) {
                    return $this->normalizePhone((string) (
                        $dev['phone'] ?? $dev['phone_number'] ?? $dev['number'] ?? ''
                    ));
                }
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }
}
