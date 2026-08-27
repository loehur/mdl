<?php

namespace App\Helpers\WaDesk;

/** Tenant-wide template usage ledger for the Dev Fee dashboard. */
class TenantDevFee
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function tableReady(): bool
    {
        try {
            $row = $this->db->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wa_tenant_dev_fee_quotas'"
            )->row_array();
            return (int) ($row['cnt'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function ensureRow(int $tenantId): void
    {
        if (!$this->tableReady()) return;
        try {
            $this->db->query(
                'INSERT IGNORE INTO wa_tenant_dev_fee_quotas (tenant_id) VALUES (?)',
                [$tenantId]
            );
        } catch (\Throwable $e) {
        }
    }

    /** A tenant must have an active paid Dev Fee quota before sending templates. */
    public function canSend(int $tenantId): bool
    {
        return $this->canConsume($tenantId, 1);
    }

    public function canConsume(int $tenantId, int $count = 1): bool
    {
        $count = max(1, $count);
        if (!$this->tableReady()) return true;
        $this->ensureRow($tenantId);
        try {
            $row = $this->db->query(
                'SELECT quota_total, quota_used FROM wa_tenant_dev_fee_quotas WHERE tenant_id = ? LIMIT 1',
                [$tenantId]
            )->row_array();
            if (!is_array($row) || $row['quota_total'] === null) return false;
            return ((int) $row['quota_total'] - (int) $row['quota_used']) >= $count;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** Record one successfully accepted template send. This does not enforce a quota yet. */
    public function recordUsage(array $data): void
    {
        if (!$this->tableReady()) return;
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        if ($tenantId <= 0) return;
        try {
            $this->ensureRow($tenantId);
            $this->db->insert('wa_tenant_dev_fee_logs', [
                'tenant_id' => $tenantId,
                'message_id' => !empty($data['message_id']) ? (int) $data['message_id'] : null,
                'template_id' => !empty($data['template_id']) ? (int) $data['template_id'] : null,
                'template_name' => mb_substr((string) ($data['template_name'] ?? '[template]'), 0, 150),
                'user_id' => !empty($data['user_id']) ? (int) $data['user_id'] : null,
                'team_id' => !empty($data['team_id']) ? (int) $data['team_id'] : null,
                'channel_id' => !empty($data['channel_id']) ? (int) $data['channel_id'] : null,
                'phone' => mb_substr((string) ($data['phone'] ?? ''), 0, 32) ?: null,
                'source' => ($data['source'] ?? 'chat') === 'blast' ? 'blast' : 'chat',
            ]);
            if ((int) $this->db->affected_rows() > 0) {
                $this->db->query(
                    'UPDATE wa_tenant_dev_fee_quotas SET quota_used = quota_used + 1, updated_at = NOW() WHERE tenant_id = ?',
                    [$tenantId]
                );
            }
        } catch (\Throwable $e) {
            // Accounting must never affect an already successful provider send.
        }
    }
}
