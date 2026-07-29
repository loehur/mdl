<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\Crypto as WaDeskCrypto;
use App\Helpers\WaDesk\YCloud as WaDeskYCloud;

/**
 * Templates — Admin CRUD WhatsApp templates + YCloud sync.
 *
 * Synced templates are shared by api_key_hash (same YCloud credential),
 * so all team keys / phone numbers using that credential can send them.
 */
class Templates extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();

        $keyId = $this->query('ycloud_key_id');
        $tenantId = (int) $user['tenant_id'];
        $isAdmin = ($user['role'] ?? '') === 'admin';

        // Filter by one WaDesk key → all templates for that credential hash
        if ($keyId !== null && $keyId !== '') {
            $kid = (int) $keyId;
            $keySql = "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ?";
            $keyBinds = [$kid, $tenantId];
            if (!$isAdmin) {
                $keySql .= ' AND team_id = ?';
                $keyBinds[] = (int) $user['team_id'];
            }
            $key = $this->db($this->db_index)->query($keySql, $keyBinds)->row_array();
            if (!$key) {
                $this->success(['templates' => []]);
            }
            $hash = $this->ensureKeyApiHash($key);
            $sql = "SELECT t.*,
                           COALESCE(k.label, k2.label) AS key_label,
                           COALESCE(k.team_id, k2.team_id) AS team_id,
                           COALESCE(k.phone_number, k2.phone_number) AS phone_number
                    FROM wa_templates t
                    LEFT JOIN ycloud_keys k ON k.id = t.ycloud_key_id
                    LEFT JOIN ycloud_keys k2 ON k2.id = ?
                    WHERE (
                        (t.api_key_hash IS NOT NULL AND t.api_key_hash <> '' AND t.api_key_hash = ?)
                        OR ((t.api_key_hash IS NULL OR t.api_key_hash = '') AND t.ycloud_key_id = ?)
                    )
                    ORDER BY t.template_name ASC";
            $rows = $this->db($this->db_index)->query($sql, [$kid, $hash !== '' ? $hash : '__none__', $kid])->result_array();
        } else {
            // All templates visible to user via any accessible key sharing the hash
            $sql = "SELECT t.*,
                           (SELECT k.label FROM ycloud_keys k
                            WHERE k.tenant_id = ?
                              AND (
                                (t.api_key_hash IS NOT NULL AND t.api_key_hash <> '' AND k.api_key_hash = t.api_key_hash)
                                OR k.id = t.ycloud_key_id
                              )
                            ORDER BY k.id ASC LIMIT 1) AS key_label,
                           (SELECT k.team_id FROM ycloud_keys k
                            WHERE k.tenant_id = ?
                              AND (
                                (t.api_key_hash IS NOT NULL AND t.api_key_hash <> '' AND k.api_key_hash = t.api_key_hash)
                                OR k.id = t.ycloud_key_id
                              )
                            ORDER BY k.id ASC LIMIT 1) AS team_id,
                           (SELECT k.phone_number FROM ycloud_keys k
                            WHERE k.tenant_id = ?
                              AND (
                                (t.api_key_hash IS NOT NULL AND t.api_key_hash <> '' AND k.api_key_hash = t.api_key_hash)
                                OR k.id = t.ycloud_key_id
                              )
                            ORDER BY k.id ASC LIMIT 1) AS phone_number
                    FROM wa_templates t
                    WHERE EXISTS (
                        SELECT 1 FROM ycloud_keys k_access
                        WHERE k_access.tenant_id = ?
                          AND (
                            (t.api_key_hash IS NOT NULL AND t.api_key_hash <> '' AND k_access.api_key_hash = t.api_key_hash)
                            OR ((t.api_key_hash IS NULL OR t.api_key_hash = '') AND k_access.id = t.ycloud_key_id)
                          )";
            $binds = [$tenantId, $tenantId, $tenantId, $tenantId];
            if (!$isAdmin) {
                $sql .= ' AND k_access.team_id = ?';
                $binds[] = (int) $user['team_id'];
            }
            $sql .= ') ORDER BY t.template_name ASC';
            $rows = $this->db($this->db_index)->query($sql, $binds)->result_array();
        }

        foreach ($rows as &$row) {
            $row['params'] = $this->db($this->db_index)->query(
                "SELECT id, component, button_sub_type, button_index, param_index, param_name, label, example_value, is_required
                 FROM wa_template_params WHERE template_id = ?
                 ORDER BY FIELD(component,'header','body','button'), param_index ASC",
                [(int) $row['id']]
            )->result_array();
        }

        $this->success(['templates' => $rows]);
    }

    /**
     * Sync APPROVED templates from YCloud for one key.
     * Result is shared with every WaDesk key that uses the same API credential.
     * POST { ycloud_key_id }
     */
    public function syncFromYCloud()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $keyId = (int) ($body['ycloud_key_id'] ?? 0);
        if ($keyId <= 0) {
            $this->error('ycloud_key_id wajib', 400);
        }

        $key = $this->db($this->db_index)->query(
            "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$keyId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$key) {
            $this->error('API key tidak ditemukan', 404);
        }

        try {
            $apiKey = WaDeskCrypto::decrypt($key['api_key_enc']);
        } catch (\Throwable $e) {
            $this->error('Gagal decrypt API key', 500);
        }
        if ($apiKey === '') {
            $this->error('API key kosong', 400);
        }

        $hash = WaDeskCrypto::fingerprint($apiKey);
        $this->db($this->db_index)->update('ycloud_keys', [
            'api_key_hash' => $hash,
        ], ['id' => $keyId]);
        $key['api_key_hash'] = $hash;

        // Fingerprint sibling keys in tenant (same credential → same hash)
        $siblingCount = $this->backfillTenantKeyHashes((int) $admin['tenant_id'], $hash, $apiKey);
        $this->backfillTemplateHashesFromKeys($hash);
        $merged = $this->dedupeTemplatesForHash($hash);

        $client = new WaDeskYCloud($apiKey, (string) ($key['phone_number'] ?? ''));
        $fetched = $client->listAllTemplates(['status' => 'APPROVED']);
        if (!$fetched['success']) {
            $this->error('Gagal ambil template dari YCloud: ' . ($fetched['error'] ?: 'unknown'), 502);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $synced = [];

        foreach ($fetched['templates'] as $remote) {
            if (!is_array($remote)) {
                continue;
            }
            $mapped = WaDeskYCloud::mapTemplateToWaDesk($remote);
            $name = $mapped['template_name'];
            $lang = $mapped['language'];
            if ($name === '') {
                $skipped++;
                continue;
            }
            if ($mapped['status'] !== '' && $mapped['status'] !== 'APPROVED') {
                $skipped++;
                continue;
            }

            $existing = $this->db($this->db_index)->query(
                "SELECT id FROM wa_templates
                 WHERE template_name = ? AND language = ?
                   AND (
                     api_key_hash = ?
                     OR (ycloud_key_id = ? AND (api_key_hash IS NULL OR api_key_hash = ''))
                   )
                 ORDER BY (api_key_hash = ?) DESC, id ASC
                 LIMIT 1",
                [$name, $lang, $hash, $keyId, $hash]
            )->row_array();

            if ($existing) {
                $tplId = (int) $existing['id'];
                $this->db($this->db_index)->update('wa_templates', [
                    'api_key_hash' => $hash,
                    'ycloud_key_id' => $keyId,
                    'body_preview' => $mapped['body_preview'],
                ], ['id' => $tplId]);
                $this->replaceParams($tplId, $mapped['params']);
                $updated++;
                $synced[] = ['id' => $tplId, 'template_name' => $name, 'language' => $lang, 'action' => 'updated'];
            } else {
                $tplId = (int) $this->db($this->db_index)->insert('wa_templates', [
                    'ycloud_key_id' => $keyId,
                    'api_key_hash' => $hash,
                    'template_name' => $name,
                    'language' => $lang,
                    'body_preview' => $mapped['body_preview'],
                ]);
                $this->replaceParams($tplId, $mapped['params']);
                $created++;
                $synced[] = ['id' => $tplId, 'template_name' => $name, 'language' => $lang, 'action' => 'created'];
            }
        }

        $this->success([
            'ycloud_key_id' => $keyId,
            'api_key_hash' => $hash,
            'shared_with_keys' => $siblingCount,
            'merged_duplicates' => $merged,
            'fetched' => count($fetched['templates']),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'templates' => $synced,
        ], "Sinkron selesai: {$created} baru, {$updated} diupdate (dibagikan ke {$siblingCount} key dengan kredensial sama)");
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

        $hash = $this->ensureKeyApiHash($key);

        $tplId = (int) $this->db($this->db_index)->insert('wa_templates', [
            'ycloud_key_id' => $keyId,
            'api_key_hash' => $hash !== '' ? $hash : null,
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
                "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$keyId, (int) $admin['tenant_id']]
            )->row_array();
            if (!$key) {
                $this->error('API key tidak ditemukan', 404);
            }
            $data['ycloud_key_id'] = $keyId;
            $hash = $this->ensureKeyApiHash($key);
            if ($hash !== '') {
                $data['api_key_hash'] = $hash;
            }
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
            $component = strtolower(trim((string) ($p['component'] ?? 'body')));
            if (!in_array($component, ['header', 'body', 'button'], true)) {
                $component = 'body';
            }
            $paramName = trim((string) ($p['param_name'] ?? ''));
            if ($paramName === '') {
                $paramName = null;
            }

            $this->db($this->db_index)->insert('wa_template_params', [
                'template_id' => $templateId,
                'component' => $component,
                'button_sub_type' => $component === 'button'
                    ? (trim((string) ($p['button_sub_type'] ?? '')) ?: 'url')
                    : null,
                'button_index' => $component === 'button' && array_key_exists('button_index', $p)
                    ? (int) $p['button_index']
                    : null,
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
             WHERE t.id = ? AND EXISTS (
                SELECT 1 FROM ycloud_keys k
                WHERE k.tenant_id = ?
                  AND (
                    (t.api_key_hash IS NOT NULL AND t.api_key_hash <> '' AND k.api_key_hash = t.api_key_hash)
                    OR k.id = t.ycloud_key_id
                  )
             )
             LIMIT 1",
            [$id, $tenantId]
        )->row_array() ?: null;
    }

    /** Set api_key_hash on other tenant keys that decrypt to the same credential. */
    private function backfillTenantKeyHashes(int $tenantId, string $targetHash, string $plainApiKey): int
    {
        $keys = $this->db($this->db_index)->query(
            "SELECT id, api_key_enc, api_key_hash FROM ycloud_keys WHERE tenant_id = ?",
            [$tenantId]
        )->result_array();
        $count = 0;
        foreach ($keys as $k) {
            $existing = trim((string) ($k['api_key_hash'] ?? ''));
            if ($existing === $targetHash) {
                $count++;
                continue;
            }
            try {
                $plain = WaDeskCrypto::decrypt((string) $k['api_key_enc']);
            } catch (\Throwable $e) {
                continue;
            }
            if (WaDeskCrypto::fingerprint($plain) !== $targetHash) {
                continue;
            }
            $this->db($this->db_index)->update('ycloud_keys', [
                'api_key_hash' => $targetHash,
            ], ['id' => (int) $k['id']]);
            $count++;
        }
        return max(1, $count);
    }

    private function backfillTemplateHashesFromKeys(string $hash): void
    {
        $this->db($this->db_index)->query(
            "UPDATE wa_templates t
             INNER JOIN ycloud_keys k ON k.id = t.ycloud_key_id
             SET t.api_key_hash = k.api_key_hash
             WHERE k.api_key_hash = ?
               AND (t.api_key_hash IS NULL OR t.api_key_hash = '')",
            [$hash]
        );
    }

    /** Keep one row per (hash, name, language); delete extras. */
    private function dedupeTemplatesForHash(string $hash): int
    {
        $dupes = $this->db($this->db_index)->query(
            "SELECT template_name, language, MIN(id) AS keep_id, COUNT(*) AS cnt
             FROM wa_templates
             WHERE api_key_hash = ?
             GROUP BY template_name, language
             HAVING cnt > 1",
            [$hash]
        )->result_array();
        $removed = 0;
        foreach ($dupes as $d) {
            $extras = $this->db($this->db_index)->query(
                "SELECT id FROM wa_templates
                 WHERE api_key_hash = ? AND template_name = ? AND language = ? AND id <> ?",
                [$hash, $d['template_name'], $d['language'], (int) $d['keep_id']]
            )->result_array();
            foreach ($extras as $ex) {
                $eid = (int) $ex['id'];
                $this->db($this->db_index)->delete('wa_template_params', ['template_id' => $eid]);
                $this->db($this->db_index)->delete('wa_templates', ['id' => $eid]);
                $removed++;
            }
        }
        return $removed;
    }
}
