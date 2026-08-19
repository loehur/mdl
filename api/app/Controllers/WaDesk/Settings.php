<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\DailyKeyLimit as WaDeskDailyKeyLimit;

/**
 * Settings — tenant-level WaDesk configuration (Kirimin API key, daily limits).
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
        ]);
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
        ], 'Limit harian tenant disimpan');
    }

    protected function getTenantDailyUniqueLimit(int $tenantId): int
    {
        $guard = new WaDeskDailyKeyLimit($this->db($this->db_index));
        return $guard->getLimit($tenantId);
    }
}
