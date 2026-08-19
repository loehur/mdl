<?php

namespace App\Controllers\WaDesk;

/**
 * Settings — tenant-level WaDesk configuration (Kirimin API key).
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
        ], 'Kirimin API key disimpan');
    }
}
