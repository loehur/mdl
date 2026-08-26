<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\Kirimin as WaDeskKirimin;

/**
 * Channels — Kirimin device/nomor; satu channel bisa dipakai beberapa team (wa_channel_teams).
 */
class Channels extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();
        $tbl = $this->channelsTable();
        $scope = trim((string) $this->query('scope', 'operational'));

        $select = "SELECT k.id, k.tenant_id, k.team_id, k.label, k.phone_number, k.device_id,
                          k.waba_id, k.channel_type, k.status, k.created_at, t.name AS team_name";

        if ($user['role'] === 'admin' && $scope === 'all') {
            $rows = $this->db($this->db_index)->query(
                "{$select}
                 FROM {$tbl} k
                 LEFT JOIN teams t ON t.id = k.team_id
                 WHERE k.tenant_id = ?
                 ORDER BY k.id DESC",
                [(int) $user['tenant_id']]
            )->result_array();
        } elseif ($this->hasOperationalTeam($user)) {
            $rows = $this->db($this->db_index)->query(
                "{$select}
                 FROM {$tbl} k
                 LEFT JOIN teams t ON t.id = k.team_id
                 WHERE k.tenant_id = ? AND k.status = 'active'
                   AND {$this->channelTeamSql($tbl, (int) $user['team_id'])}
                 ORDER BY k.id DESC",
                [(int) $user['tenant_id']]
            )->result_array();
        } else {
            $rows = [];
        }

        $channels = array_map(fn ($r) => $this->mapChannelRow($r), $rows);
        $channels = array_map(function (array $r) {
            $r['team_ids'] = array_map('intval', array_column($this->channelTeamRows((int) $r['id'], (int) $r['tenant_id']), 'id'));
            $r['team_names'] = $this->formatTeamNames($this->channelTeamRows((int) $r['id'], (int) $r['tenant_id']));
            return $r;
        }, $channels);
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
            "SELECT id, device_id, team_id, label, phone_number, waba_id, status
             FROM {$tbl} WHERE tenant_id = ?",
            [$tenantId]
        )->result_array();

        $byDevice = [];
        foreach ($assigned as $row) {
            $did = trim((string) ($row['device_id'] ?? ''));
            if ($did !== '') {
                $row['team_ids'] = array_map('intval', array_column($this->channelTeamRows((int) $row['id'], $tenantId), 'id'));
                $row['team_names'] = $this->formatTeamNames($this->channelTeamRows((int) $row['id'], $tenantId));
                $byDevice[$did] = $this->mapChannelRow($row);
            }
        }

        $devices = [];
        foreach ($fetched['devices'] as $dev) {
            if (!is_array($dev)) {
                continue;
            }
            $deviceId = trim((string) ($dev['device_id'] ?? $dev['id'] ?? ''));
            if ($deviceId === '') {
                continue;
            }

            $phone = $this->normalizePhone((string) ($dev['phone_number'] ?? $dev['phone'] ?? ''));
            $wabaId = trim((string) ($dev['waba_id'] ?? ''));
            $channelType = strtolower((string) ($dev['channel_type'] ?? 'waba'));
            if (!in_array($channelType, ['waba', 'device'], true)) {
                $channelType = 'waba';
            }

            if (isset($byDevice[$deviceId]) && $wabaId !== '') {
                $channelId = (int) ($byDevice[$deviceId]['id'] ?? 0);
                $currentWaba = trim((string) ($byDevice[$deviceId]['waba_id'] ?? ''));
                if ($channelId > 0 && $currentWaba === '') {
                    $this->db($this->db_index)->update($tbl, ['waba_id' => $wabaId], ['id' => $channelId]);
                    $byDevice[$deviceId]['waba_id'] = $wabaId;
                    $this->syncWabaLimitRow($wabaId, $tenantId, (string) ($byDevice[$deviceId]['label'] ?? ''));
                }
            }

            $devices[] = [
                'device_id' => $deviceId,
                'phone_number' => $phone,
                'label' => trim((string) ($dev['label'] ?? $dev['name'] ?? $deviceId)),
                'channel_type' => $channelType,
                'waba_id' => $wabaId !== '' ? $wabaId : null,
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
        $this->validate($body, ['device_id', 'label']);

        $tenantId = (int) $admin['tenant_id'];
        $teamIds = $this->extractTeamIds($body);
        if ($teamIds === []) {
            $this->error('Pilih minimal satu team', 400);
        }
        foreach ($teamIds as $tid) {
            $team = $this->db($this->db_index)->query(
                "SELECT id FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$tid, $tenantId]
            )->row_array();
            if (!$team) {
                $this->error('Team tidak ditemukan', 404);
            }
        }
        $teamId = $teamIds[0];

        $deviceId = trim((string) $body['device_id']);
        $tbl = $this->channelsTable();

        $existing = $this->db($this->db_index)->query(
            "SELECT * FROM {$tbl} WHERE device_id = ? AND tenant_id = ? LIMIT 1",
            [$deviceId, $tenantId]
        )->row_array();
        if ($existing) {
            $channelId = (int) $existing['id'];
            $currentIds = array_map(
                static fn ($r) => (int) ($r['id'] ?? 0),
                $this->channelTeamRows($channelId, $tenantId)
            );
            $merged = array_values(array_unique(array_merge($currentIds, $teamIds)));
            if ($merged === $currentIds) {
                $this->error('Semua team terpilih sudah di-assign ke nomor ini', 409);
            }
            $this->syncChannelTeams($channelId, $merged);
            $label = trim((string) ($body['label'] ?? ''));
            if ($label !== '' && $label !== (string) ($existing['label'] ?? '')) {
                $this->db($this->db_index)->update($tbl, ['label' => $label], ['id' => $channelId]);
            }
            $this->success([
                'id' => $channelId,
                'channel_id' => $channelId,
                'merged' => true,
            ], 'Team ditambahkan ke nomor');

            return;
        }

        $meta = $this->resolveDeviceMeta($deviceId, $tenantId);
        $phone = isset($body['phone_number'])
            ? $this->normalizePhone((string) $body['phone_number'])
            : ($meta['phone_number'] ?: '');
        if ($phone === '') {
            $phone = $deviceId;
        }

        $channelType = strtolower((string) ($body['channel_type'] ?? $meta['channel_type'] ?? 'waba'));
        if (!in_array($channelType, ['waba', 'device'], true)) {
            $channelType = 'waba';
        }

        $wabaId = trim((string) ($body['waba_id'] ?? $meta['waba_id'] ?? ''));

        $insertData = [
            'tenant_id' => $tenantId,
            'team_id' => $teamId,
            'label' => trim($body['label']),
            'device_id' => $deviceId,
            'channel_type' => $channelType,
            'phone_number' => $phone,
            'status' => 'active',
        ];
        if ($wabaId !== '') {
            $insertData['waba_id'] = $wabaId;
        }

        $id = (int) $this->db($this->db_index)->insert($tbl, $insertData);
        if ($id <= 0) {
            $this->error('Gagal assign channel.', 409);
        }
        if ($wabaId !== '') {
            $this->syncWabaLimitRow($wabaId, $tenantId, trim($body['label']));
        }

        $this->syncChannelTeams($id, $teamIds);

        $this->success([
            'id' => $id,
            'channel_id' => $id,
            'waba_id' => $wabaId !== '' ? $wabaId : null,
            'needs_waba_id' => $wabaId === '',
        ], $wabaId === ''
            ? 'Channel di-assign. WABA ID kosong — isi manual di Admin agar bisa kirim pesan.'
            : 'Channel di-assign');
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
        $tenantId = (int) $admin['tenant_id'];
        $tbl = $this->channelsTable();

        $channel = $this->db($this->db_index)->query(
            "SELECT * FROM {$tbl} WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$id, $tenantId]
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
        if (array_key_exists('waba_id', $body)) {
            $wabaId = trim((string) $body['waba_id']);
            $data['waba_id'] = $wabaId !== '' ? $wabaId : null;
        }
        if ($data) {
            $updated = $this->db($this->db_index)->update($tbl, $data, ['id' => $id]);
            if ($updated === false) {
                $this->error('Gagal update channel.', 409);
            }
            $newWaba = trim((string) ($data['waba_id'] ?? $channel['waba_id'] ?? ''));
            if ($newWaba !== '') {
                $label = trim((string) ($data['label'] ?? $channel['label'] ?? ''));
                $this->syncWabaLimitRow($newWaba, $tenantId, $label);
            }
        }

        $requestedTeamIds = $this->extractTeamIds($body);
        if (isset($body['team_ids']) || isset($body['teams']) || isset($body['team_ids[]'])) {
            if ($requestedTeamIds === []) {
                $this->error('Pilih minimal satu team', 400);
            }
            $this->syncChannelTeams($id, $requestedTeamIds);
            $this->db($this->db_index)->update($tbl, ['team_id' => $requestedTeamIds[0]], ['id' => $id]);
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

    /** Team tambahan dari body (team_ids[] / team_id / teams[]), dibersihkan ke int > 0. */
    private function extractTeamIds(array $body): array
    {
        $ids = [];
        foreach (['team_ids', 'teams', 'team_ids[]'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                foreach ($body[$key] as $v) {
                    $id = (int) $v;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
        }
        if (isset($body['team_id']) && (int) $body['team_id'] > 0) {
            $ids[] = (int) $body['team_id'];
        }
        return array_values(array_unique($ids));
    }

    /** @return array{phone_number:string,waba_id:string,channel_type:string} */
    private function resolveDeviceMeta(string $deviceId, int $tenantId): array
    {
        $out = ['phone_number' => '', 'waba_id' => '', 'channel_type' => 'waba'];
        try {
            $client = $this->requireKiriminConfigured($tenantId);
            $fetched = $client->listDevices();
            if (!$fetched['success']) {
                return $out;
            }
            foreach ($fetched['devices'] as $dev) {
                if (!is_array($dev)) {
                    continue;
                }
                $id = trim((string) ($dev['device_id'] ?? $dev['id'] ?? ''));
                if ($id !== $deviceId) {
                    continue;
                }
                $out['phone_number'] = $this->normalizePhone((string) (
                    $dev['phone_number'] ?? $dev['phone'] ?? ''
                ));
                $out['waba_id'] = trim((string) ($dev['waba_id'] ?? ''));
                $type = strtolower((string) ($dev['channel_type'] ?? 'waba'));
                $out['channel_type'] = in_array($type, ['waba', 'device'], true) ? $type : 'waba';
                break;
            }
        } catch (\Throwable $e) {
            return $out;
        }
        return $out;
    }
}
