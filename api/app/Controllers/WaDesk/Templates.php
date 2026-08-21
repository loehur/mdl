<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\Kirimin as WaDeskKirimin;
use App\Helpers\WaDesk\YCloud as WaDeskYCloud;

/**
 * Templates — Admin CRUD WhatsApp templates + Kirimin sync (tenant-wide).
 */
class Templates extends WaDeskController
{
    public function list()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();
        $tenantId = (int) $user['tenant_id'];
        $channelId = (int) $this->query('channel_id', 0);
        $deviceId = null;

        if ($channelId > 0) {
            if (!$this->hasOperationalTeam($user)) {
                $this->error('Anda belum join team', 403);
            }
            $tbl = $this->channelsTable();
            $channel = $this->db($this->db_index)->query(
                "SELECT device_id FROM {$tbl}
                 WHERE id = ? AND tenant_id = ? AND team_id = ? AND status = 'active'
                 LIMIT 1",
                [$channelId, $tenantId, (int) $user['team_id']]
            )->row_array();
            if (!$channel) {
                $this->error('Channel tidak ditemukan', 404);
            }
            $deviceId = trim((string) ($channel['device_id'] ?? ''));
            if ($deviceId === '') {
                $this->success(['templates' => []]);
                return;
            }
        }

        if ($deviceId !== null && $deviceId !== '' && $this->templateDevicesTableExists()) {
            $rows = $this->db($this->db_index)->query(
                "SELECT DISTINCT t.* FROM wa_templates t
                 INNER JOIN wa_template_devices td ON td.template_id = t.id AND td.device_id = ?
                 WHERE t.tenant_id = ?
                 ORDER BY t.template_name ASC, t.id ASC",
                [$deviceId, $tenantId]
            )->result_array();
        } else {
            $rows = $this->db($this->db_index)->query(
                "SELECT t.* FROM wa_templates t
                 WHERE t.tenant_id = ?
                 ORDER BY t.template_name ASC, t.id ASC",
                [$tenantId]
            )->result_array();
        }
        $rows = $this->dedupeTemplateListRows($rows, $tenantId);

        $hasButtonMeta = $this->columnExists('wa_template_params', 'button_sub_type');
        $hasMaxlength = $this->columnExists('wa_template_params', 'maxlength');
        $paramCols = $hasButtonMeta
            ? "id, component, button_sub_type, button_index, param_index, param_name, label, example_value, is_required"
            : "id, component, param_index, param_name, label, example_value, is_required";
        if ($hasMaxlength) {
            $paramCols .= ', maxlength';
        }

        foreach ($rows as &$row) {
            $row['params'] = $this->db($this->db_index)->query(
                "SELECT $paramCols
                 FROM wa_template_params WHERE template_id = ?
                 ORDER BY FIELD(component,'header','body','button'), param_index ASC",
                [(int) $row['id']]
            )->result_array();
            if ($channelId <= 0 && $this->templateDevicesTableExists()) {
                $row['channels'] = $this->loadTemplateChannelLabels((int) $row['id'], $tenantId);
            }
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
        $tplName = trim((string) ($body['template_name'] ?? $this->query('template_name') ?? ''));
        if ($tplName === '') {
            $this->error('template_name wajib', 400);
        }

        $client = $this->requireKiriminConfigured((int) $admin['tenant_id']);
        $tenantId = (int) $admin['tenant_id'];
        $deviceIds = $this->collectSyncDeviceIds($tenantId, $client);
        $found = null;
        $linkedDevices = [];

        foreach ($deviceIds as $deviceId) {
            $fetched = $client->listAllTemplates([
                'status' => 'APPROVED',
                'device_id' => $deviceId,
            ]);
            if (!$fetched['success']) {
                continue;
            }
            foreach ($fetched['templates'] ?? [] as $t) {
                if (($t['template_name'] ?? $t['name'] ?? '') !== $tplName) {
                    continue;
                }
                if ($found === null) {
                    $found = $t;
                }
                $linkedDevices[$deviceId] = true;
            }
        }

        if (!$found) {
            $this->error('Template tidak ditemukan di Kirimin', 404);
        }

        $mapped = WaDeskKirimin::mapTemplateToWaDesk($found);

        $allRows = $this->db($this->db_index)->query(
            "SELECT id FROM wa_templates
             WHERE template_name = ? AND tenant_id = ?
             ORDER BY id ASC",
            [$tplName, (int) $admin['tenant_id']]
        )->result_array();

        if (!$allRows) {
            $this->error('Template belum ada di DB, lakukan sync penuh dulu', 404);
        }

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
        $this->replaceTemplateDeviceLinks($bestId, array_keys($linkedDevices));

        $this->success([
            'template_id'   => $bestId,
            'deleted_dupes' => $deletedIds,
            'params_synced' => count($mapped['params']),
            'params'        => $mapped['params'],
            'devices'       => array_keys($linkedDevices),
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

        // Strategy: rename old index then add correct one, or use ALTER to replace in one statement
        // Old index uq_tpl_param (template_id, param_index) — cannot drop due to FK, use ALTER TABLE RENAME INDEX
        $byName = $uniqueNames; // already built above
        $hasCorrect = false;
        foreach ($byName as $cols) {
            if (in_array('component', $cols) && in_array('param_index', $cols) && in_array('template_id', $cols)) {
                $hasCorrect = true;
            }
        }

        if ($hasCorrect) {
            $results['already_correct'] = true;
        } else {
            // Try ALTER TABLE ... RENAME INDEX (MySQL 5.7+)
            try {
                $this->db($this->db_index)->query(
                    "ALTER TABLE wa_template_params
                     RENAME INDEX uq_tpl_param TO uq_tpl_param_old,
                     ADD UNIQUE KEY uq_tpl_param (template_id, component, param_index)"
                );
                $results['fixed'] = 'renamed old index, added uq_tpl_param (template_id, component, param_index)';
            } catch (\Throwable $e) {
                $results['rename_error'] = $e->getMessage();
                // Fallback: drop FK constraint temporarily, drop index, re-add both
                try {
                    $this->db($this->db_index)->query("SET FOREIGN_KEY_CHECKS=0");
                    $this->db($this->db_index)->query("ALTER TABLE wa_template_params DROP INDEX uq_tpl_param");
                    $this->db($this->db_index)->query(
                        "ALTER TABLE wa_template_params ADD UNIQUE KEY uq_tpl_param (template_id, component, param_index)"
                    );
                    $this->db($this->db_index)->query("SET FOREIGN_KEY_CHECKS=1");
                    $results['fixed'] = 'forced drop+add uq_tpl_param with FK checks off';
                } catch (\Throwable $e2) {
                    $this->db($this->db_index)->query("SET FOREIGN_KEY_CHECKS=1");
                    $results['fallback_error'] = $e2->getMessage();
                }
            }
        }

        // Drop leftover renamed old index if present
        try {
            $this->db($this->db_index)->query("ALTER TABLE wa_template_params DROP INDEX uq_tpl_param_old");
            $results['dropped_old'] = 'uq_tpl_param_old';
        } catch (\Throwable $e) {
            $results['drop_old_note'] = $e->getMessage();
        }

        // Ensure ENUM allows header/body/button (migration 002 may have narrowed it)
        try {
            $col = $this->db($this->db_index)->query(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wa_template_params' AND COLUMN_NAME = 'component'"
            )->row_array();
            $results['component_type_before'] = $col['COLUMN_TYPE'] ?? null;
            $ctype = strtolower((string) ($col['COLUMN_TYPE'] ?? ''));
            if ($ctype !== '' && (strpos($ctype, 'header') === false || strpos($ctype, 'button') === false)) {
                $this->db($this->db_index)->query(
                    "ALTER TABLE wa_template_params
                     MODIFY COLUMN component ENUM('header','body','button') NOT NULL DEFAULT 'body'"
                );
                $results['enum_fixed'] = "ENUM('header','body','button')";
            } else {
                $results['enum_ok'] = true;
            }
        } catch (\Throwable $e) {
            $results['enum_error'] = $e->getMessage();
        }

        $indexesAfter = $this->db($this->db_index)->query(
            "SHOW INDEX FROM wa_template_params WHERE Key_name != 'PRIMARY'"
        )->result_array();
        $results['indexes_after'] = array_map(fn($i) => $i['Key_name'] . ':' . $i['Column_name'], $indexesAfter);

        $this->success($results, 'Migration 007 selesai');
    }

    public function debugDB()
    {
        $this->verifyAuth();
        $this->requireAdmin();
        $tplName = trim((string) $this->query('template_name'));

        $rows = $this->db($this->db_index)->query(
            "SELECT t.id, t.template_name, t.tenant_id,
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
        $this->requireAdmin();

        $admin = $this->requireAdmin();
        $tplName = trim((string) $this->query('template_name'));
        $client = $this->requireKiriminConfigured((int) $admin['tenant_id']);
        $fetched = $client->listAllTemplates(['status' => 'APPROVED']);

        $found = null;
        foreach ($fetched['templates'] ?? [] as $t) {
            if (($t['template_name'] ?? $t['name'] ?? '') === $tplName) {
                $found = $t;
                break;
            }
        }

        if (!$found) {
            $this->error('Template tidak ditemukan di Kirimin', 404);
        }

        $mapped = WaDeskKirimin::mapTemplateToWaDesk($found);

        $this->success([
            'raw_components' => $found['components'] ?? [],
            'mapped_params'  => $mapped['params'],
            'body_preview'   => $mapped['body_preview'],
        ]);
    }

    public function syncFromKirimin()
    {
        try {
            $this->_syncFromKiriminInner();
        } catch (\Throwable $e) {
            $logLine = date('Y-m-d H:i:s') . ' syncFromKirimin EXCEPTION: '
                . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                . "\nTrace: " . $e->getTraceAsString() . "\n---\n";
            \Log::write($logLine, 'wadesk', 'sync_exception');
            throw $e;
        }
    }

    /** @deprecated alias */
    public function syncFromYCloud()
    {
        return $this->syncFromKirimin();
    }

    private function _syncFromKiriminInner()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $tenantId = (int) $admin['tenant_id'];
        $client = $this->requireKiriminConfigured($tenantId);
        $deviceIds = $this->collectSyncDeviceIds($tenantId, $client);
        if ($deviceIds === []) {
            $this->error('Tidak ada device Kirimin untuk di-sync', 400);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $deleted = 0;
        $synced = [];
        $keepKeys = [];
        $newLinks = [];
        $fetchedTotal = 0;

        foreach ($deviceIds as $deviceId) {
            $fetched = $client->listAllTemplates([
                'status' => 'APPROVED',
                'device_id' => $deviceId,
            ]);
            if (!$fetched['success']) {
                $skipped++;
                continue;
            }

            foreach ($fetched['templates'] as $remote) {
                if (!is_array($remote)) {
                    continue;
                }
                $mapped = WaDeskKirimin::mapTemplateToWaDesk($remote);
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

                $fetchedTotal++;
                $keepKeys[$name . '|' . $lang] = true;

                $existing = $this->findSyncedTemplateRow($tenantId, $name, $lang);

                if ($existing) {
                    $tplId = (int) $existing['id'];
                    $this->db($this->db_index)->update('wa_templates', [
                        'tenant_id' => $tenantId,
                        'body_preview' => $mapped['body_preview'],
                    ], ['id' => $tplId]);
                    $this->replaceParams($tplId, $mapped['params']);
                    $updated++;
                    $synced[] = [
                        'id' => $tplId,
                        'template_name' => $name,
                        'language' => $lang,
                        'device_id' => $deviceId,
                        'action' => 'updated',
                    ];
                } else {
                    $tplId = (int) $this->db($this->db_index)->insert('wa_templates', [
                        'tenant_id' => $tenantId,
                        'template_name' => $name,
                        'language' => $lang,
                        'body_preview' => $mapped['body_preview'],
                    ]);
                    $this->replaceParams($tplId, $mapped['params']);
                    $created++;
                    $synced[] = [
                        'id' => $tplId,
                        'template_name' => $name,
                        'language' => $lang,
                        'device_id' => $deviceId,
                        'action' => 'created',
                    ];
                }

                $newLinks[] = ['template_id' => $tplId, 'device_id' => $deviceId];
            }
        }

        $this->applyTemplateDeviceLinks($tenantId, $newLinks);

        $deleted = $this->pruneMissingTemplates($tenantId, $keepKeys);

        $this->success([
            'tenant_id' => $tenantId,
            'devices' => count($deviceIds),
            'fetched' => $fetchedTotal,
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'skipped' => $skipped,
            'links' => count($newLinks),
            'templates' => $synced,
        ], "Sinkron selesai: {$created} baru, {$updated} diupdate, {$deleted} dihapus, " . count($newLinks) . ' link device');
    }

    private function _syncFromYCloudInner()
    {
        return $this->_syncFromKiriminInner();
    }

    /** @param array<string,true> $keepKeys */
    private function pruneMissingTemplates(int $tenantId, array $keepKeys): int
    {
        $locals = $this->db($this->db_index)->query(
            "SELECT id, template_name, language FROM wa_templates WHERE tenant_id = ?",
            [$tenantId]
        )->result_array();

        $removed = 0;
        foreach ($locals as $row) {
            $key = ($row['template_name'] ?? '') . '|' . ($row['language'] ?? '');
            if (isset($keepKeys[$key])) {
                continue;
            }
            $tid = (int) $row['id'];
            $this->db($this->db_index)->delete('wa_template_params', ['template_id' => $tid]);
            $this->db($this->db_index)->delete('wa_templates', ['id' => $tid]);
            $removed++;
        }
        return $removed;
    }

    public function create()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['template_name']);

        $tenantId = (int) $admin['tenant_id'];
        $insertData = [
            'tenant_id' => $tenantId,
            'template_name' => trim($body['template_name']),
            'language' => trim($body['language'] ?? 'id') ?: 'id',
            'body_preview' => $body['body_preview'] ?? null,
        ];

        $tplId = (int) $this->db($this->db_index)->insert('wa_templates', $insertData);

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

    /** POST { template_id, params: [{ id, maxlength }] } — admin only */
    public function updateMaxlength()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        if (!$this->columnExists('wa_template_params', 'maxlength')) {
            $this->error('Kolom maxlength belum ada. Jalankan migration 011.', 500);
        }

        $body = $this->getBody();
        $templateId = (int) ($body['template_id'] ?? 0);
        $params = $body['params'] ?? [];
        if ($templateId <= 0) {
            $this->error('template_id wajib', 400);
        }
        if (!is_array($params) || $params === []) {
            $this->error('params wajib', 400);
        }

        if (!$this->findTemplateForTenant($templateId, (int) $admin['tenant_id'])) {
            $this->error('Template tidak ditemukan', 404);
        }

        $updated = 0;
        foreach ($params as $p) {
            if (!is_array($p)) {
                continue;
            }
            $id = (int) ($p['id'] ?? 0);
            $max = (int) ($p['maxlength'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($max < 1 || $max > 1024) {
                $this->error('maxlength harus antara 1 dan 1024', 400);
            }

            $row = $this->db($this->db_index)->query(
                "SELECT id FROM wa_template_params WHERE id = ? AND template_id = ? LIMIT 1",
                [$id, $templateId]
            )->row_array();
            if (!$row) {
                $this->error('Param template tidak ditemukan', 404);
            }

            $this->db($this->db_index)->update('wa_template_params', [
                'maxlength' => $max,
            ], ['id' => $id]);
            $updated++;
        }

        $this->success(['updated' => $updated], 'Maxlength param diupdate');
    }

    private function replaceParams(int $templateId, array $params): void
    {
        $maxMap = $this->loadExistingMaxlengthMap($templateId);
        $this->db($this->db_index)->delete('wa_template_params', ['template_id' => $templateId]);
        $hasButtonMeta = $this->columnExists('wa_template_params', 'button_sub_type');
        $hasMaxlength = $this->columnExists('wa_template_params', 'maxlength');
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

            if ($hasMaxlength) {
                $row['maxlength'] = $maxMap[$seenKey]
                    ?? (int) ($p['maxlength'] ?? self::TEMPLATE_PARAM_DEFAULT_MAXLENGTH);
                if ($row['maxlength'] < 1) {
                    $row['maxlength'] = self::TEMPLATE_PARAM_DEFAULT_MAXLENGTH;
                }
            }

            try {
                $insertId = $this->db($this->db_index)->insert('wa_template_params', $row);
                if ($insertId === false || $insertId === 0) {
                    // Fall back to raw INSERT so real MySQL error surfaces
                    $cols = implode(', ', array_keys($row));
                    $ph   = implode(', ', array_fill(0, count($row), '?'));
                    $this->db($this->db_index)->query(
                        "INSERT INTO wa_template_params ($cols) VALUES ($ph)",
                        array_values($row)
                    );
                }
                \Log::write('INSERT OK: ' . $seenKey . ' id=' . (int) $insertId, 'wadesk', 'replace_params');
            } catch (\Throwable $e) {
                \Log::write('INSERT FAIL: ' . $seenKey . ' — ' . $e->getMessage(), 'wadesk', 'replace_params');
                throw $e; // surface real error to caller
            }
        }
    }


    /** @return array<string,int> component:param_index => maxlength */
    private function loadExistingMaxlengthMap(int $templateId): array
    {
        if (!$this->columnExists('wa_template_params', 'maxlength')) {
            return [];
        }

        $rows = $this->db($this->db_index)->query(
            "SELECT component, param_index, maxlength FROM wa_template_params WHERE template_id = ?",
            [$templateId]
        )->result_array();

        $map = [];
        foreach ($rows as $row) {
            $key = strtolower((string) ($row['component'] ?? 'body')) . ':' . (int) ($row['param_index'] ?? 0);
            $map[$key] = (int) ($row['maxlength'] ?? self::TEMPLATE_PARAM_DEFAULT_MAXLENGTH);
        }

        return $map;
    }

    private function getTenantTemplate(int $id, int $tenantId): ?array
    {
        return $this->findTemplateForTenant($id, $tenantId);
    }

    /** Find existing Kirimin-synced row by name+lang; dedupe orphans. */
    private function findSyncedTemplateRow(int $tenantId, string $name, string $lang): ?array
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT id, tenant_id FROM wa_templates
             WHERE template_name = ? AND language = ? AND tenant_id = ?
             ORDER BY id ASC",
            [$name, $lang, $tenantId]
        )->result_array();

        if ($rows === []) {
            return null;
        }

        $keep = $rows[0];
        for ($i = 1, $n = count($rows); $i < $n; $i++) {
            $this->purgeTemplateRow((int) $rows[$i]['id']);
        }

        return $keep;
    }

    /** @param list<array> $rows @return list<array> */
    private function dedupeTemplateListRows(array $rows, int $tenantId): array
    {
        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $key = ($row['template_name'] ?? '') . '|' . ($row['language'] ?? 'id');
            if ($key === '|id' || isset($seen[$key])) {
                if (isset($seen[$key])) {
                    $this->purgeTemplateRow((int) $row['id']);
                }
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    private function purgeTemplateRow(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        if ($this->templateDevicesTableExists()) {
            $this->db($this->db_index)->delete('wa_template_devices', ['template_id' => $id]);
        }
        $this->db($this->db_index)->delete('wa_template_params', ['template_id' => $id]);
        $this->db($this->db_index)->delete('wa_templates', ['id' => $id]);
    }

    /** @return list<string> */
    private function collectSyncDeviceIds(int $tenantId, WaDeskKirimin $client): array
    {
        $ids = [];
        $devRes = $client->listDevices();
        foreach ($devRes['devices'] ?? [] as $dev) {
            if (!is_array($dev)) {
                continue;
            }
            $did = trim((string) ($dev['device_id'] ?? $dev['id'] ?? ''));
            if ($did !== '') {
                $ids[$did] = true;
            }
        }

        $tbl = $this->channelsTable();
        $rows = $this->db($this->db_index)->query(
            "SELECT DISTINCT device_id FROM {$tbl}
             WHERE tenant_id = ? AND device_id IS NOT NULL AND TRIM(device_id) != ''",
            [$tenantId]
        )->result_array();
        foreach ($rows as $row) {
            $did = trim((string) ($row['device_id'] ?? ''));
            if ($did !== '') {
                $ids[$did] = true;
            }
        }

        return array_keys($ids);
    }

    /** @param list<array{template_id:int,device_id:string}> $links */
    private function applyTemplateDeviceLinks(int $tenantId, array $links): void
    {
        if (!$this->templateDevicesTableExists()) {
            return;
        }

        $this->db($this->db_index)->query(
            "DELETE td FROM wa_template_devices td
             INNER JOIN wa_templates t ON t.id = td.template_id
             WHERE t.tenant_id = ?",
            [$tenantId]
        );

        $seen = [];
        foreach ($links as $link) {
            $tplId = (int) ($link['template_id'] ?? 0);
            $deviceId = trim((string) ($link['device_id'] ?? ''));
            if ($tplId <= 0 || $deviceId === '') {
                continue;
            }
            $key = $tplId . '|' . $deviceId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $this->db($this->db_index)->insert('wa_template_devices', [
                'template_id' => $tplId,
                'device_id' => $deviceId,
            ]);
        }
    }

    /** @param list<string> $deviceIds */
    private function replaceTemplateDeviceLinks(int $templateId, array $deviceIds): void
    {
        if (!$this->templateDevicesTableExists() || $templateId <= 0) {
            return;
        }

        $this->db($this->db_index)->delete('wa_template_devices', ['template_id' => $templateId]);
        $seen = [];
        foreach ($deviceIds as $deviceId) {
            $deviceId = trim((string) $deviceId);
            if ($deviceId === '' || isset($seen[$deviceId])) {
                continue;
            }
            $seen[$deviceId] = true;
            $this->db($this->db_index)->insert('wa_template_devices', [
                'template_id' => $templateId,
                'device_id' => $deviceId,
            ]);
        }
    }

    /** @return list<array{id:int,label:string,phone_number:string}> */
    private function loadTemplateChannelLabels(int $templateId, int $tenantId): array
    {
        if (!$this->templateDevicesTableExists()) {
            return [];
        }

        $tbl = $this->channelsTable();
        return $this->db($this->db_index)->query(
            "SELECT DISTINCT c.id, c.label, c.phone_number
             FROM wa_template_devices td
             INNER JOIN {$tbl} c ON c.device_id = td.device_id AND c.tenant_id = ?
             WHERE td.template_id = ?
             ORDER BY c.label ASC, c.phone_number ASC",
            [$tenantId, $templateId]
        )->result_array();
    }
}
