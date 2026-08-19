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
        ], 'Limit harian tenant disimpan');
    }

    protected function getTenantDailyUniqueLimit(int $tenantId): int
    {
        $guard = new WaDeskDailyKeyLimit($this->db($this->db_index));
        return $guard->getLimit($tenantId);
    }
}
