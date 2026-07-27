<?php

namespace App\Controllers\WaDesk;

/**
 * Templates — Admin CRUD WhatsApp templates + params
 */
class Templates extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();

        $keyId = $this->query('ycloud_key_id');

        $sql = "SELECT t.*, k.label AS key_label, k.team_id, k.phone_number
                FROM wa_templates t
                INNER JOIN ycloud_keys k ON k.id = t.ycloud_key_id
                WHERE k.tenant_id = ?";
        $binds = [(int) $user['tenant_id']];

        if ($user['role'] !== 'admin') {
            $sql .= ' AND k.team_id = ?';
            $binds[] = (int) $user['team_id'];
        }
        if ($keyId !== null && $keyId !== '') {
            $sql .= ' AND t.ycloud_key_id = ?';
            $binds[] = (int) $keyId;
        }
        $sql .= ' ORDER BY t.template_name ASC';

        $rows = $this->db($this->db_index)->query($sql, $binds)->result_array();
        foreach ($rows as &$row) {
            $row['params'] = $this->db($this->db_index)->query(
                "SELECT id, component, param_index, param_name, label, example_value, is_required
                 FROM wa_template_params WHERE template_id = ?
                 ORDER BY FIELD(component,'header','body','button'), param_index ASC",
                [(int) $row['id']]
            )->result_array();
        }

        $this->success(['templates' => $rows]);
    }

    public function create()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['ycloud_key_id', 'template_name']);

        $keyId = (int) $body['ycloud_key_id'];
        $key = $this->db($this->db_index)->query(
            "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$keyId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$key) {
            $this->error('API key tidak ditemukan', 404);
        }

        $tplId = (int) $this->db($this->db_index)->insert('wa_templates', [
            'ycloud_key_id' => $keyId,
            'template_name' => trim($body['template_name']),
            'language' => trim($body['language'] ?? 'id') ?: 'id',
            'body_preview' => $body['body_preview'] ?? null,
        ]);

        $this->replaceParams($tplId, $body['params'] ?? []);

        $this->success(['id' => $tplId], 'Template dibuat');
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

        $tpl = $this->getTenantTemplate($id, (int) $admin['tenant_id']);
        if (!$tpl) {
            $this->error('Template tidak ditemukan', 404);
        }

        $data = [];
        if (isset($body['ycloud_key_id'])) {
            $keyId = (int) $body['ycloud_key_id'];
            $key = $this->db($this->db_index)->query(
                "SELECT id FROM ycloud_keys WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$keyId, (int) $admin['tenant_id']]
            )->row_array();
            if (!$key) {
                $this->error('API key tidak ditemukan', 404);
            }
            $data['ycloud_key_id'] = $keyId;
        }
        if (isset($body['template_name'])) {
            $data['template_name'] = trim($body['template_name']);
        }
        if (isset($body['language'])) {
            $data['language'] = trim($body['language']) ?: 'id';
        }
        if (array_key_exists('body_preview', $body)) {
            $data['body_preview'] = $body['body_preview'];
        }
        if ($data) {
            $this->db($this->db_index)->update('wa_templates', $data, ['id' => $id]);
        }
        if (isset($body['params']) && is_array($body['params'])) {
            $this->replaceParams($id, $body['params']);
        }

        $this->success(null, 'Template diupdate');
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

        if (!$this->getTenantTemplate($id, (int) $admin['tenant_id'])) {
            $this->error('Template tidak ditemukan', 404);
        }

        $this->db($this->db_index)->delete('wa_template_params', ['template_id' => $id]);
        $this->db($this->db_index)->delete('wa_templates', ['id' => $id]);
        $this->success(null, 'Template dihapus');
    }

    private function replaceParams(int $templateId, array $params): void
    {
        $this->db($this->db_index)->delete('wa_template_params', ['template_id' => $templateId]);
        foreach ($params as $p) {
            if (!isset($p['param_index'], $p['label'])) {
                continue;
            }
            $component = 'body';
            $paramName = trim((string) ($p['param_name'] ?? ''));
            if ($paramName === '') {
                $paramName = null;
            }

            $this->db($this->db_index)->insert('wa_template_params', [
                'template_id' => $templateId,
                'component' => $component,
                'param_index' => (int) $p['param_index'],
                'param_name' => $paramName,
                'label' => trim($p['label']),
                'example_value' => $p['example_value'] ?? null,
                'is_required' => isset($p['is_required']) ? (int) ((bool) $p['is_required']) : 1,
            ]);
        }
    }

    private function getTenantTemplate(int $id, int $tenantId): ?array
    {
        return $this->db($this->db_index)->query(
            "SELECT t.* FROM wa_templates t
             INNER JOIN ycloud_keys k ON k.id = t.ycloud_key_id
             WHERE t.id = ? AND k.tenant_id = ? LIMIT 1",
            [$id, $tenantId]
        )->row_array() ?: null;
    }
}
