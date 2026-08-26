<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\DailyKeyLimit as WaDeskDailyKeyLimit;

/**
 * Settings — tenant-level WaDesk configuration (Kirimin, OpenAI, daily limits).
 */
class Settings extends WaDeskController
{
    public function kirimin()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];

        if ($this->isPost()) {
            return $this->saveKirimin($tenantId);
        }

        $cfg = $this->getTenantKiriminConfig($tenantId);
        $this->success([
            'configured' => $cfg['configured'],
            'api_key_masked' => $cfg['api_key_masked'],
            'daily_unique_limit' => $this->getTenantDailyUniqueLimit($tenantId),
        ]);
    }

    public function dailyLimit()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];

        if ($this->isPost()) {
            return $this->saveDailyLimit($tenantId);
        }

        $this->success([
            'daily_unique_limit' => $this->getTenantDailyUniqueLimit($tenantId),
            'wabas' => $this->listWabaDailyLimits($tenantId),
        ]);
    }

    /** GET/POST /WaDesk/Settings/wabaDailyLimit — limit per WABA ID */
    public function wabaDailyLimit()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];

        if ($this->isPost()) {
            return $this->saveWabaDailyLimit($tenantId);
        }

        $this->success([
            'tenant_default' => $this->getTenantDailyUniqueLimit($tenantId),
            'wabas' => $this->listWabaDailyLimits($tenantId),
        ]);
    }

    /** GET/POST /WaDesk/Settings/openai — baca / simpan OpenAI API key tenant */
    public function openai()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];

        if ($this->isPost()) {
            return $this->saveOpenAi($tenantId);
        }

        $cfg = $this->getTenantOpenAiConfig($tenantId);
        $this->success([
            'configured' => $cfg['configured'],
            'api_key_masked' => $cfg['api_key_masked'],
        ]);
    }

    /** POST /WaDesk/Settings/deleteOpenai — hapus OpenAI API key tenant */
    public function deleteOpenai()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $tenantId = (int) $admin['tenant_id'];
        $this->db($this->db_index)->update('tenants', [
            'openai_api_key' => null,
        ], ['id' => $tenantId]);

        $this->success([
            'configured' => false,
            'api_key_masked' => '',
        ], 'OpenAI API key dihapus');
    }

    private function saveOpenAi(int $tenantId): void
    {
        $body = $this->getBody();
        $apiKey = trim((string) ($body['api_key'] ?? ''));
        if ($apiKey === '') {
            $this->error('api_key wajib', 400);
        }
        if (!str_starts_with($apiKey, 'sk-')) {
            $this->error('Format API key tidak valid (harus diawali sk-)', 400);
        }
        if (strlen($apiKey) < 20) {
            $this->error('API key terlalu pendek', 400);
        }

        $this->db($this->db_index)->update('tenants', [
            'openai_api_key' => $apiKey,
        ], ['id' => $tenantId]);

        $cfg = $this->getTenantOpenAiConfig($tenantId);
        $this->success([
            'configured' => true,
            'api_key_masked' => $cfg['api_key_masked'],
        ], 'OpenAI API key disimpan');
    }

    private function saveKirimin(int $tenantId): void
    {
        $body = $this->getBody();
        $apiKey = trim((string) ($body['api_key'] ?? ''));
        if ($apiKey === '') {
            $this->error('api_key wajib', 400);
        }
        if (!str_starts_with($apiKey, 'kc_')) {
            $this->error('Format API key tidak valid (harus diawali kc_)', 400);
        }

        $this->db($this->db_index)->update('tenants', [
            'kirimin_api_key' => $apiKey,
        ], ['id' => $tenantId]);

        $cfg = $this->getTenantKiriminConfig($tenantId);
        $this->success([
            'configured' => true,
            'api_key_masked' => $cfg['api_key_masked'],
            'daily_unique_limit' => $this->getTenantDailyUniqueLimit($tenantId),
        ], 'Kirimin API key disimpan');
    }

    private function saveDailyLimit(int $tenantId): void
    {
        $body = $this->getBody();
        $limit = (int) ($body['daily_unique_limit'] ?? 0);
        if ($limit < 1) {
            $this->error('daily_unique_limit minimal 1', 400);
        }
        if ($limit > 100000) {
            $this->error('daily_unique_limit maksimal 100000', 400);
        }

        $this->db($this->db_index)->update('tenants', [
            'daily_unique_limit' => $limit,
        ], ['id' => $tenantId]);

        $this->success([
            'daily_unique_limit' => $limit,
            'wabas' => $this->listWabaDailyLimits($tenantId),
        ], 'Default limit harian tenant disimpan');
    }

    private function saveWabaDailyLimit(int $tenantId): void
    {
        $body = $this->getBody();
        $wabaId = trim((string) ($body['waba_id'] ?? ''));
        if ($wabaId === '') {
            $this->error('waba_id wajib', 400);
        }

        $limit = (int) ($body['daily_unique_limit'] ?? 0);
        if ($limit < 1) {
            $this->error('daily_unique_limit minimal 1', 400);
        }
        if ($limit > 100000) {
            $this->error('daily_unique_limit maksimal 100000', 400);
        }

        $tbl = $this->channelsTable();
        $owned = $this->db($this->db_index)->query(
            "SELECT 1 AS ok FROM {$tbl}
             WHERE tenant_id = ? AND TRIM(waba_id) = ?
             LIMIT 1",
            [$tenantId, $wabaId]
        )->row_array();
        if (!$owned) {
            $this->error('WABA ID tidak ditemukan di channel tenant ini', 404);
        }

        $label = isset($body['label']) ? trim((string) $body['label']) : null;
        $guard = new WaDeskDailyKeyLimit($this->db($this->db_index));
        $guard->ensureLimitRow($wabaId, $tenantId, $label);

        $this->db($this->db_index)->query(
            "INSERT INTO wa_waba_daily_limits (waba_id, tenant_id, daily_unique_limit, label)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               daily_unique_limit = VALUES(daily_unique_limit),
               label = COALESCE(VALUES(label), wa_waba_daily_limits.label)",
            [$wabaId, $tenantId, $limit, $label !== '' ? $label : null]
        );

        $this->success([
            'waba_id' => $wabaId,
            'daily_unique_limit' => $limit,
            'used_today' => $guard->countUsedToday($wabaId),
            'wabas' => $this->listWabaDailyLimits($tenantId),
        ], 'Limit harian WABA disimpan');
    }

    /** @return list<array<string,mixed>> */
    private function listWabaDailyLimits(int $tenantId): array
    {
        if (!$this->tableExists('wa_waba_daily_limits')) {
            return [];
        }

        $tbl = $this->channelsTable();
        $default = $this->getTenantDailyUniqueLimit($tenantId);
        $guard = new WaDeskDailyKeyLimit($this->db($this->db_index));

        $rows = $this->db($this->db_index)->query(
            "SELECT TRIM(c.waba_id) AS waba_id,
                    MIN(c.label) AS channel_label,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS team_names,
                    MAX(l.daily_unique_limit) AS daily_unique_limit,
                    MAX(l.label) AS limit_label
             FROM {$tbl} c
             LEFT JOIN (
               SELECT channel_id, team_id FROM wa_channel_teams
               UNION
               SELECT id, team_id FROM {$tbl} WHERE team_id IS NOT NULL
             ) ct ON ct.channel_id = c.id
             LEFT JOIN teams t ON t.id = ct.team_id
             LEFT JOIN wa_waba_daily_limits l ON l.waba_id = TRIM(c.waba_id) AND l.tenant_id = c.tenant_id
             WHERE c.tenant_id = ?
               AND c.waba_id IS NOT NULL
               AND TRIM(c.waba_id) <> ''
             GROUP BY TRIM(c.waba_id)
             ORDER BY TRIM(c.waba_id) ASC",
            [$tenantId]
        )->result_array();

        $out = [];
        foreach ($rows as $row) {
            $wabaId = trim((string) ($row['waba_id'] ?? ''));
            if ($wabaId === '') {
                continue;
            }
            $guard->ensureLimitRow($wabaId, $tenantId, (string) ($row['channel_label'] ?? ''));
            $limit = (int) ($row['daily_unique_limit'] ?? 0);
            if ($limit < 1) {
                $limit = $default;
            }
            $out[] = [
                'waba_id' => $wabaId,
                'label' => (string) (($row['limit_label'] ?? '') ?: ($row['channel_label'] ?? $wabaId)),
                'team_names' => (string) ($row['team_names'] ?? ''),
                'daily_unique_limit' => $limit,
                'used_today' => $guard->countUsedToday($wabaId),
            ];
        }

        return $out;
    }
}
