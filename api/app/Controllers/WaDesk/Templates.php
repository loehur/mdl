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

        $hasHashCol     = $this->columnExists('wa_templates', 'api_key_hash');
        $hasKeyHashCol  = $this->columnExists('ycloud_keys', 'api_key_hash');

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

            if ($hasHashCol && $hasKeyHashCol) {
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
                // Legacy: no hash columns
                $sql = "SELECT t.*, k.label AS key_label, k.team_id, k.phone_number
                        FROM wa_templates t
                        LEFT JOIN ycloud_keys k ON k.id = t.ycloud_key_id
                        WHERE t.ycloud_key_id = ?
                        ORDER BY t.template_name ASC";
                $rows = $this->db($this->db_index)->query($sql, [$kid])->result_array();
            }
        } else {
            if ($hasHashCol && $hasKeyHashCol) {
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
            } else {
                // Legacy: no hash columns
                $sql = "SELECT t.*, k.label AS key_label, k.team_id, k.phone_number
                        FROM wa_templates t
                        INNER JOIN ycloud_keys k ON k.id = t.ycloud_key_id
                        WHERE k.tenant_id = ?";
                $binds = [$tenantId];
                if (!$isAdmin) {
                    $sql .= ' AND k.team_id = ?';
                    $binds[] = (int) $user['team_id'];
                }
                $sql .= ' ORDER BY t.template_name ASC';
                $rows = $this->db($this->db_index)->query($sql, $binds)->result_array();
            }
        }

        $hasButtonMeta = $this->columnExists('wa_template_params', 'button_sub_type');
        $paramCols = $hasButtonMeta
            ? "id, component, button_sub_type, button_index, param_index, param_name, label, example_value, is_required"
            : "id, component, param_index, param_name, label, example_value, is_required";

        foreach ($rows as &$row) {
            $row['params'] = $this->db($this->db_index)->query(
                "SELECT $paramCols
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
    /**
     * Debug: GET /WaDesk/Templates/debugTemplate?ycloud_key_id=X&template_name=Y
     * Returns raw YCloud template data + mapped params (admin only, remove after use)
     */
    /**
     * GET or POST { ycloud_key_id, template_name } — resync single template params from YCloud
     */
    public function resyncOne()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $body = $this->isPost() ? $this->getBody() : [];
        $keyId = (int) ($body['ycloud_key_id'] ?? $this->query('ycloud_key_id') ?? 0);
        $tplName = trim((string) ($body['template_name'] ?? $this->query('template_name') ?? ''));
        if ($keyId <= 0 || $tplName === '') $this->error('ycloud_key_id dan template_name wajib', 400);

        $key = $this->db($this->db_index)->query(
            "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$keyId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$key) $this->error('Key tidak ditemukan', 404);

        $apiKey = WaDeskCrypto::decrypt($key['api_key_enc']);
        $client = new WaDeskYCloud($apiKey, (string) ($key['phone_number'] ?? ''));
        $fetched = $client->listAllTemplates(['status' => 'APPROVED']);

        $found = null;
        foreach ($fetched['templates'] ?? [] as $t) {
            if (($t['name'] ?? '') === $tplName) { $found = $t; break; }
        }
        if (!$found) $this->error('Template tidak ditemukan di YCloud', 404);

        $mapped = WaDeskYCloud::mapTemplateToWaDesk($found);

        // Find ALL template rows with this name — may have duplicates from multi-key sync
        $allRows = $this->db($this->db_index)->query(
            "SELECT id, ycloud_key_id, api_key_hash FROM wa_templates WHERE template_name = ? ORDER BY id ASC",
            [$tplName]
        )->result_array();

        if (!$allRows) $this->error('Template belum ada di DB, lakukan sync penuh dulu', 404);

        // Keep the row with the most params (or lowest id as tiebreak), delete the rest
        $bestId = (int) $allRows[0]['id'];
        $bestCount = 0;
        foreach ($allRows as $r) {
            $cnt = (int) $this->db($this->db_index)->query(
                "SELECT COUNT(*) AS cnt FROM wa_template_params WHERE template_id = ?", [(int) $r['id']]
            )->row_array()['cnt'];
            if ($cnt > $bestCount) { $bestCount = $cnt; $bestId = (int) $r['id']; }
        }

        // Delete duplicate rows (keep $bestId)
        $deletedIds = [];
        foreach ($allRows as $r) {
            $rid = (int) $r['id'];
            if ($rid !== $bestId) {
                $this->db($this->db_index)->delete('wa_template_params', ['template_id' => $rid]);
                $this->db($this->db_index)->delete('wa_templates', ['id' => $rid]);
                $deletedIds[] = $rid;
            }
        }

        // Update the surviving row with fresh data
        $this->db($this->db_index)->update('wa_templates', [
            'body_preview' => $mapped['body_preview'],
        ], ['id' => $bestId]);
        $this->replaceParams($bestId, $mapped['params']);

        $this->success([
            'template_id'   => $bestId,
            'deleted_dupes' => $deletedIds,
            'params_synced' => count($mapped['params']),
            'params'        => $mapped['params'],
        ], 'Params template berhasil di-resync');
    }

    /**
     * GET /WaDesk/Templates/runMigration007
     * Fix UNIQUE constraint on wa_template_params to include component.
     */
    public function runMigration007()
    {
        $this->verifyAuth();
        $this->requireAdmin();

        $results = [];

        // Check current unique indexes on wa_template_params
        $indexes = $this->db($this->db_index)->query(
            "SHOW INDEX FROM wa_template_params WHERE Key_name != 'PRIMARY'"
        )->result_array();

        $results['indexes_before'] = array_map(fn($i) => $i['Key_name'] . ':' . $i['Column_name'], $indexes);

        // Find unique index names that do NOT include component
        $uniqueNames = [];
        foreach ($indexes as $idx) {
            if ($idx['Non_unique'] == 0) {
                $uniqueNames[$idx['Key_name']][] = $idx['Column_name'];
            }
        }

        $dropped = [];
        foreach ($uniqueNames as $idxName => $cols) {
            // Drop if component not in columns
            if (!in_array('component', $cols)) {
                try {
                    $this->db($this->db_index)->query("ALTER TABLE wa_template_params DROP INDEX `$idxName`");
                    $dropped[] = $idxName;
                } catch (\Throwable $e) {
                    $results['drop_error_' . $idxName] = $e->getMessage();
                }
            }
        }
        $results['dropped'] = $dropped;

        // Check if correct unique already exists
        $newIndexes = $this->db($this->db_index)->query(
            "SHOW INDEX FROM wa_template_params WHERE Key_name != 'PRIMARY'"
        )->result_array();
        $hasCorrect = false;
        $byName = [];
        foreach ($newIndexes as $idx) {
            if ($idx['Non_unique'] == 0) $byName[$idx['Key_name']][] = $idx['Column_name'];
        }
        foreach ($byName as $cols) {
            if (in_array('component', $cols) && in_array('param_index', $cols) && in_array('template_id', $cols)) {
                $hasCorrect = true;
            }
        }

        if (!$hasCorrect) {
            try {
                $this->db($this->db_index)->query(
                    "ALTER TABLE wa_template_params ADD UNIQUE KEY uq_tpl_param (template_id, component, param_index)"
                );
                $results['added'] = 'uq_tpl_param (template_id, component, param_index)';
            } catch (\Throwable $e) {
                $results['add_error'] = $e->getMessage();
            }
        } else {
            $results['already_correct'] = true;
        }

        $this->success($results, 'Migration 007 selesai');
    }

    public function debugDB()
    {
        $this->verifyAuth();
        $this->requireAdmin();
        $tplName = trim((string) $this->query('template_name'));

        $rows = $this->db($this->db_index)->query(
            "SELECT t.id, t.template_name, t.ycloud_key_id, t.api_key_hash,
                    (SELECT COUNT(*) FROM wa_template_params p WHERE p.template_id = t.id) AS param_count
             FROM wa_templates t
             WHERE t.template_name = ?",
            [$tplName]
        )->result_array();

        foreach ($rows as &$r) {
            $r['params'] = $this->db($this->db_index)->query(
                "SELECT id, component, param_index, param_name FROM wa_template_params WHERE template_id = ? ORDER BY component, param_index",
                [(int) $r['id']]
            )->result_array();
        }

        $this->success(['rows' => $rows]);
    }

    public function debugTemplate()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $keyId = (int) $this->query('ycloud_key_id');
        $tplName = trim((string) $this->query('template_name'));

        $key = $this->db($this->db_index)->query(
            "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$keyId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$key) $this->error('Key tidak ditemukan', 404);

        $apiKey = WaDeskCrypto::decrypt($key['api_key_enc']);
        $client = new WaDeskYCloud($apiKey, (string) ($key['phone_number'] ?? ''));
        $fetched = $client->listAllTemplates(['status' => 'APPROVED']);

        $found = null;
        foreach ($fetched['templates'] ?? [] as $t) {
            if (($t['name'] ?? '') === $tplName) {
                $found = $t;
                break;
            }
        }

        if (!$found) $this->error('Template tidak ditemukan di YCloud', 404);

        $mapped = WaDeskYCloud::mapTemplateToWaDesk($found);

        $this->success([
            'raw_components' => $found['components'] ?? [],
            'mapped_params'  => $mapped['params'],
            'body_preview'   => $mapped['body_preview'],
        ]);
    }

    public function syncFromYCloud()
    {
        // Temporary debug: wrap entire function to capture real exception
        try {
            $this->_syncFromYCloudInner();
        } catch (\Throwable $e) {
            $logLine = date('Y-m-d H:i:s') . ' syncFromYCloud EXCEPTION: '
                . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                . "\nTrace: " . $e->getTraceAsString() . "\n---\n";
            \Log::write($logLine, 'wadesk', 'sync_exception');
            throw $e;
        }
    }

    private function _syncFromYCloudInner()
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

        // Detect whether migration 006 (api_key_hash columns) has been applied
        $hashColExists = $this->columnExists('ycloud_keys', 'api_key_hash');

        if ($hashColExists) {
            $this->db($this->db_index)->update('ycloud_keys', [
                'api_key_hash' => $hash,
            ], ['id' => $keyId]);
            $key['api_key_hash'] = $hash;

            $siblingCount = $this->backfillTenantKeyHashes((int) $admin['tenant_id'], $hash, $apiKey);
            // Dedupe by key_id BEFORE backfilling hash (avoids UNIQUE constraint violation on UPDATE)
            $this->dedupeTemplatesByKeyId($hash);
            $this->backfillTemplateHashesFromKeys($hash);
            $merged = $this->dedupeTemplatesForHash($hash);
        } else {
            // Migration not yet applied — fall back to key-scoped sync (legacy behaviour)
            $hashColExists = false;
            $hash = '';
            $siblingCount = 1;
            $merged = 0;
        }

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
            \Log::write('SYNC_MAPPED: ' . json_encode([
                'name'   => $name,
                'params' => $mapped['params'],
                'raw_components' => $remote['components'] ?? [],
            ], JSON_UNESCAPED_UNICODE), 'wadesk', 'sync_template_map');
            if ($name === '') {
                $skipped++;
                continue;
            }
            if ($mapped['status'] !== '' && $mapped['status'] !== 'APPROVED') {
                $skipped++;
                continue;
            }

            // Find existing row — search by hash first, then by key_id fallback
            $existing = null;
            if ($hash !== '') {
                $existing = $this->db($this->db_index)->query(
                    "SELECT id FROM wa_templates
                     WHERE api_key_hash = ? AND template_name = ? AND language = ?
                     LIMIT 1",
                    [$hash, $name, $lang]
                )->row_array();
            }
            if (!$existing) {
                $existing = $this->db($this->db_index)->query(
                    "SELECT id FROM wa_templates
                     WHERE ycloud_key_id = ? AND template_name = ? AND language = ?
                     LIMIT 1",
                    [$keyId, $name, $lang]
                )->row_array();
            }

            if ($existing) {
                $tplId = (int) $existing['id'];
                $updateData = ['body_preview' => $mapped['body_preview'], 'ycloud_key_id' => $keyId];
                if ($hash !== '') {
                    $updateData['api_key_hash'] = $hash;
                }
                $this->db($this->db_index)->update('wa_templates', $updateData, ['id' => $tplId]);
                $this->replaceParams($tplId, $mapped['params']);
                $updated++;
                $synced[] = ['id' => $tplId, 'template_name' => $name, 'language' => $lang, 'action' => 'updated'];
            } else {
                $insertData = [
                    'ycloud_key_id' => $keyId,
                    'template_name' => $name,
                    'language' => $lang,
                    'body_preview' => $mapped['body_preview'],
                ];
                if ($hash !== '') {
                    $insertData['api_key_hash'] = $hash;
                }
                try {
                    $tplId = (int) $this->db($this->db_index)->insert('wa_templates', $insertData);
                } catch (\Throwable $insertEx) {
                    // UNIQUE constraint hit (race / backfill already set hash) — re-fetch and update
                    $tplId = 0;
                    $retry = $hash !== ''
                        ? $this->db($this->db_index)->query(
                            "SELECT id FROM wa_templates WHERE api_key_hash = ? AND template_name = ? AND language = ? LIMIT 1",
                            [$hash, $name, $lang]
                          )->row_array()
                        : $this->db($this->db_index)->query(
                            "SELECT id FROM wa_templates WHERE ycloud_key_id = ? AND template_name = ? AND language = ? LIMIT 1",
                            [$keyId, $name, $lang]
                          )->row_array();
                    if ($retry) {
                        $tplId = (int) $retry['id'];
                        $upd = ['body_preview' => $mapped['body_preview'], 'ycloud_key_id' => $keyId];
                        if ($hash !== '') $upd['api_key_hash'] = $hash;
                        $this->db($this->db_index)->update('wa_templates', $upd, ['id' => $tplId]);
                        $updated++;
                        $synced[] = ['id' => $tplId, 'template_name' => $name, 'language' => $lang, 'action' => 'updated'];
                    } else {
                        $skipped++;
                    }
                }
                if ($tplId > 0) {
                    $this->replaceParams($tplId, $mapped['params']);
                    if (!isset($retry)) {
                        $created++;
                        $synced[] = ['id' => $tplId, 'template_name' => $name, 'language' => $lang, 'action' => 'created'];
                    }
                    unset($retry);
                }
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
        $hasButtonMeta = $this->columnExists('wa_template_params', 'button_sub_type');
        $seen = [];
        \Log::write('replaceParams start tpl=' . $templateId . ' count=' . count($params) . ' params=' . json_encode(array_map(fn($p)=>($p['component']??'?').':'.$p['param_index'].':'.$p['param_name']??'', $params), JSON_UNESCAPED_UNICODE), 'wadesk', 'replace_params');
        foreach ($params as $p) {
            if (!isset($p['param_index'], $p['label'])) {
                \Log::write('SKIP missing param_index/label: ' . json_encode($p), 'wadesk', 'replace_params');
                continue;
            }
            $component = strtolower(trim((string) ($p['component'] ?? 'body')));
            if (!in_array($component, ['header', 'body', 'button'], true)) {
                $component = 'body';
            }
            $seenKey = $component . ':' . (int) $p['param_index'];
            if (isset($seen[$seenKey])) {
                \Log::write('SKIP seen: ' . $seenKey, 'wadesk', 'replace_params');
                continue;
            }
            $seen[$seenKey] = true;
            $paramName = trim((string) ($p['param_name'] ?? ''));
            if ($paramName === '') {
                $paramName = null;
            }

            $row = [
                'template_id' => $templateId,
                'component' => $component,
                'param_index' => (int) $p['param_index'],
                'param_name' => $paramName,
                'label' => trim($p['label']),
                'example_value' => $p['example_value'] ?? null,
                'is_required' => isset($p['is_required']) ? (int) ((bool) $p['is_required']) : 1,
            ];

            if ($hasButtonMeta) {
                $row['button_sub_type'] = $component === 'button'
                    ? (trim((string) ($p['button_sub_type'] ?? '')) ?: 'url')
                    : null;
                $row['button_index'] = $component === 'button' && array_key_exists('button_index', $p)
                    ? (int) $p['button_index']
                    : null;
            }

            try {
                $this->db($this->db_index)->insertIgnore('wa_template_params', $row);
                \Log::write('INSERT OK: ' . $seenKey, 'wadesk', 'replace_params');
            } catch (\Throwable $e) {
                \Log::write('insertIgnore FAIL: ' . $e->getMessage() . ' — trying raw query', 'wadesk', 'replace_params');
                try {
                    $cols = implode(', ', array_keys($row));
                    $ph   = implode(', ', array_fill(0, count($row), '?'));
                    $this->db($this->db_index)->query(
                        "INSERT IGNORE INTO wa_template_params ($cols) VALUES ($ph)",
                        array_values($row)
                    );
                    \Log::write('RAW INSERT OK: ' . $seenKey, 'wadesk', 'replace_params');
                } catch (\Throwable $e2) {
                    \Log::write('RAW INSERT FAIL: ' . $e2->getMessage(), 'wadesk', 'replace_params');
                }
            }
        }
    }


    private function getTenantTemplate(int $id, int $tenantId): ?array
    {
        if ($this->columnExists('wa_templates', 'api_key_hash') && $this->columnExists('ycloud_keys', 'api_key_hash')) {
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
        // Legacy: no hash columns yet
        return $this->db($this->db_index)->query(
            "SELECT t.* FROM wa_templates t
             INNER JOIN ycloud_keys k ON k.id = t.ycloud_key_id
             WHERE t.id = ? AND k.tenant_id = ? LIMIT 1",
            [$id, $tenantId]
        )->row_array() ?: null;
    }

    /** Set api_key_hash on other tenant keys that decrypt to the same credential. */
    private function backfillTenantKeyHashes(int $tenantId, string $targetHash, string $plainApiKey): int
    {
        $keys = $this->db($this->db_index)->query(
            "SELECT id, api_key_enc FROM ycloud_keys WHERE tenant_id = ?",
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
            if ($this->columnExists('ycloud_keys', 'api_key_hash')) {
                $this->db($this->db_index)->update('ycloud_keys', [
                    'api_key_hash' => $targetHash,
                ], ['id' => (int) $k['id']]);
            }
            $count++;
        }
        return max(1, $count);
    }

    /**
     * Before setting api_key_hash, remove duplicate (template_name+language) rows
     * that belong to keys sharing the same credential — keeping the oldest row.
     * This prevents UNIQUE constraint violation when the UPDATE sets api_key_hash.
     */
    private function dedupeTemplatesByKeyId(string $hash): void
    {
        // Find all key IDs that share this credential hash
        $keys = $this->db($this->db_index)->query(
            "SELECT id FROM ycloud_keys WHERE api_key_hash = ?",
            [$hash]
        )->result_array();
        if (count($keys) < 2) {
            return; // nothing to dedupe
        }
        $keyIds = array_column($keys, 'id');
        $placeholders = implode(',', array_fill(0, count($keyIds), '?'));

        // Find duplicates across those key_ids
        $dupes = $this->db($this->db_index)->query(
            "SELECT template_name, language, COUNT(*) AS cnt
             FROM wa_templates
             WHERE ycloud_key_id IN ($placeholders)
             GROUP BY template_name, language
             HAVING cnt > 1",
            $keyIds
        )->result_array();

        foreach ($dupes as $d) {
            // Keep the row with the most params
            $candidates = $this->db($this->db_index)->query(
                "SELECT t.id,
                        (SELECT COUNT(*) FROM wa_template_params p WHERE p.template_id = t.id) AS param_cnt
                 FROM wa_templates t
                 WHERE t.ycloud_key_id IN ($placeholders)
                   AND t.template_name = ? AND t.language = ?
                 ORDER BY param_cnt DESC, t.id ASC",
                array_merge($keyIds, [$d['template_name'], $d['language']])
            )->result_array();
            $keepId = (int) $candidates[0]['id'];
            foreach (array_slice($candidates, 1) as $ex) {
                $eid = (int) $ex['id'];
                $this->db($this->db_index)->delete('wa_template_params', ['template_id' => $eid]);
                $this->db($this->db_index)->delete('wa_templates', ['id' => $eid]);
            }
        }
    }

    private function backfillTemplateHashesFromKeys(string $hash): void
    {
        if (!$this->columnExists('wa_templates', 'api_key_hash')) {
            return;
        }
        $this->db($this->db_index)->query(
            "UPDATE wa_templates t
             INNER JOIN ycloud_keys k ON k.id = t.ycloud_key_id
             SET t.api_key_hash = k.api_key_hash
             WHERE k.api_key_hash = ?
               AND (t.api_key_hash IS NULL OR t.api_key_hash = '')",
            [$hash]
        );
    }

    /** Keep one row per (hash, name, language); delete extras. Keep the one with most params. */
    private function dedupeTemplatesForHash(string $hash): int
    {
        $dupes = $this->db($this->db_index)->query(
            "SELECT template_name, language, COUNT(*) AS cnt
             FROM wa_templates
             WHERE api_key_hash = ?
             GROUP BY template_name, language
             HAVING cnt > 1",
            [$hash]
        )->result_array();
        $removed = 0;
        foreach ($dupes as $d) {
            // Find the row with the most params to keep
            $candidates = $this->db($this->db_index)->query(
                "SELECT t.id,
                        (SELECT COUNT(*) FROM wa_template_params p WHERE p.template_id = t.id) AS param_cnt
                 FROM wa_templates t
                 WHERE t.api_key_hash = ? AND t.template_name = ? AND t.language = ?
                 ORDER BY param_cnt DESC, t.id ASC",
                [$hash, $d['template_name'], $d['language']]
            )->result_array();
            $keepId = (int) $candidates[0]['id'];
            $extras = array_filter($candidates, fn($r) => (int) $r['id'] !== $keepId);
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
