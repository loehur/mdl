<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\Crypto as WaDeskCrypto;

/**
 * Keys — Admin CRUD YCloud API keys (assigned to a team)
 */
class Keys extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();

        if ($user['role'] === 'admin') {
            $rows = $this->db($this->db_index)->query(
                "SELECT k.id, k.tenant_id, k.team_id, k.label, k.phone_number, k.ycloud_phone_id, k.status,
                        k.api_key_hash, k.created_at, t.name AS team_name
                 FROM ycloud_keys k
                 LEFT JOIN teams t ON t.id = k.team_id
                 WHERE k.tenant_id = ?
                 ORDER BY k.id DESC",
                [(int) $user['tenant_id']]
            )->result_array();
        } else {
            $rows = $this->db($this->db_index)->query(
                "SELECT k.id, k.tenant_id, k.team_id, k.label, k.phone_number, k.ycloud_phone_id, k.status,
                        k.api_key_hash, k.created_at, t.name AS team_name
                 FROM ycloud_keys k
                 LEFT JOIN teams t ON t.id = k.team_id
                 WHERE k.tenant_id = ? AND k.team_id = ? AND k.status = 'active'
                 ORDER BY k.id DESC",
                [(int) $user['tenant_id'], (int) $user['team_id']]
            )->result_array();
        }

        foreach ($rows as &$r) {
            $r['api_key_masked'] = '••••••••';
            // Expose hash so UI can group shared templates (not a secret)
            if (empty($r['api_key_hash'])) {
                // leave null; filled lazily on sync/send
            }
            unset($r['api_key_enc']);
        }

        $this->success(['keys' => $rows]);
    }

    public function create()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['label', 'api_key', 'phone_number', 'team_id']);

        $teamId = (int) $body['team_id'];
        $team = $this->db($this->db_index)->query(
            "SELECT id FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }

        $phone = $this->normalizePhone($body['phone_number']);
        $plainKey = trim($body['api_key']);
        $enc = WaDeskCrypto::encrypt($plainKey);

        $id = (int) $this->db($this->db_index)->insert('ycloud_keys', [
            'tenant_id' => (int) $admin['tenant_id'],
            'team_id' => $teamId,
            'label' => trim($body['label']),
            'api_key_enc' => $enc,
            'api_key_hash' => WaDeskCrypto::fingerprint($plainKey),
            'phone_number' => $phone,
            'ycloud_phone_id' => $body['ycloud_phone_id'] ?? null,
            'status' => 'active',
        ]);

        $this->success(['id' => $id], 'API key disimpan');
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

        $key = $this->db($this->db_index)->query(
            "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$id, (int) $admin['tenant_id']]
        )->row_array();
        if (!$key) {
            $this->error('Key tidak ditemukan', 404);
        }

        $data = [];
        if (isset($body['label'])) {
            $data['label'] = trim($body['label']);
        }
        if (isset($body['phone_number'])) {
            $data['phone_number'] = $this->normalizePhone($body['phone_number']);
        }
        if (isset($body['ycloud_phone_id'])) {
            $data['ycloud_phone_id'] = $body['ycloud_phone_id'] ?: null;
        }
        if (isset($body['status']) && in_array($body['status'], ['active', 'inactive'], true)) {
            $data['status'] = $body['status'];
        }
        if (!empty($body['api_key'])) {
            $plain = trim($body['api_key']);
            $data['api_key_enc'] = WaDeskCrypto::encrypt($plain);
            $data['api_key_hash'] = WaDeskCrypto::fingerprint($plain);
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
            $data['team_id'] = $teamId;
        }

        if ($data) {
            $this->db($this->db_index)->update('ycloud_keys', $data, ['id' => $id]);
            if (isset($data['team_id']) && (int) $data['team_id'] !== (int) $key['team_id']) {
                $this->db($this->db_index)->update('conversations', [
                    'team_id' => (int) $data['team_id'],
                ], ['ycloud_key_id' => $id]);
            }
        }

        $this->success(null, 'API key diupdate');
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

        $key = $this->db($this->db_index)->query(
            "SELECT id FROM ycloud_keys WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$id, (int) $admin['tenant_id']]
        )->row_array();
        if (!$key) {
            $this->error('Key tidak ditemukan', 404);
        }

        $this->db($this->db_index)->delete('ycloud_keys', ['id' => $id]);
        $this->success(null, 'API key dihapus');
    }
}
