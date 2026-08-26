<?php

namespace App\Helpers\WaDesk;

/**
 * Enforce tenant-level daily limit on unique destination numbers (successful sends only).
 *
 * Rule:
 * - Max N unique phones per tenant per day (tenants.daily_unique_limit)
 * - Only counts phones with status >= sent (sent, delivered, read)
 * - Failed sends are NOT counted
 */
class DailyKeyLimit
{
    public const DEFAULT_DAILY_UNIQUE_LIMIT = 250;

    /** Statuses that consume daily unique quota. */
    public const COUNTABLE_STATUSES = ['sent', 'delivered', 'read'];

    private $db;

    /** @var array<int,int> */
    private array $limitCache = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getLimit(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return self::DEFAULT_DAILY_UNIQUE_LIMIT;
        }

        if (isset($this->limitCache[$tenantId])) {
            return $this->limitCache[$tenantId];
        }

        $limit = self::DEFAULT_DAILY_UNIQUE_LIMIT;
        try {
            $row = $this->db->query(
                "SELECT daily_unique_limit FROM tenants WHERE id = ? LIMIT 1",
                [$tenantId]
            )->row_array();
            $val = (int) ($row['daily_unique_limit'] ?? 0);
            if ($val > 0) {
                $limit = $val;
            }
        } catch (\Throwable $e) {
            /* ignore */
        }

        $this->limitCache[$tenantId] = $limit;
        return $limit;
    }

    /**
     * @return array{allowed:bool,is_new:bool,used:int,limit:int,error:string,tenant_id:int}
     */
    public function canSend(int $tenantId, string $phone): array
    {
        if ($tenantId <= 0) {
            return [
                'allowed' => false,
                'is_new' => false,
                'used' => 0,
                'limit' => 0,
                'error' => 'Tenant tidak valid.',
                'tenant_id' => 0,
            ];
        }

        $limit = $this->getLimit($tenantId);
        $today = $this->todayDate();

        $existing = $this->findCountable($tenantId, $today, $phone);
        if ($existing) {
            return [
                'allowed' => true,
                'is_new' => false,
                'used' => $this->countUsed($tenantId, $today),
                'limit' => $limit,
                'error' => '',
                'tenant_id' => $tenantId,
            ];
        }

        $used = $this->countUsed($tenantId, $today);
        if ($used >= $limit) {
            return [
                'allowed' => false,
                'is_new' => true,
                'used' => $used,
                'limit' => $limit,
                'error' => 'Limit harian tercapai: maksimal ' . $limit . ' nomor unik terkirim (sent) per hari.',
                'tenant_id' => $tenantId,
            ];
        }

        return [
            'allowed' => true,
            'is_new' => true,
            'used' => $used,
            'limit' => $limit,
            'error' => '',
            'tenant_id' => $tenantId,
        ];
    }

    public function recordSuccess(
        int $tenantId,
        string $phone,
        ?int $userId = null,
        string $source = 'chat'
    ): void {
        if ($tenantId <= 0) {
            return;
        }

        $today = $this->todayDate();
        $now = date('Y-m-d H:i:s');
        $phone = trim($phone);
        if ($phone === '') {
            return;
        }

        $existing = $this->findCountable($tenantId, $today, $phone);
        if ($existing) {
            $this->db->update('wa_key_daily_contacts', [
                'last_user_id' => $userId ?: null,
                'last_source' => $source,
                'last_attempt_at' => $now,
                'status' => 'sent',
            ], ['id' => (int) $existing['id']]);
            return;
        }

        try {
            $this->db->insert('wa_key_daily_contacts', [
                'tenant_id' => $tenantId,
                'contact_date' => $today,
                'phone' => $phone,
                'status' => 'sent',
                'first_user_id' => $userId ?: null,
                'last_user_id' => $userId ?: null,
                'first_source' => $source,
                'last_source' => $source,
                'first_attempt_at' => $now,
                'last_attempt_at' => $now,
            ]);
        } catch (\Throwable $e) {
            try {
                \Log::write(
                    'wa_key_daily_contacts insert failed tenant=' . $tenantId . ' phone=' . $phone . ' err=' . $e->getMessage(),
                    'wadesk',
                    'DailyKeyLimit'
                );
            } catch (\Throwable $ignored) {
            }
            $existing = $this->findCountable($tenantId, $today, $phone);
            if ($existing) {
                $this->db->update('wa_key_daily_contacts', [
                    'last_user_id' => $userId ?: null,
                    'last_source' => $source,
                    'last_attempt_at' => $now,
                    'status' => 'sent',
                ], ['id' => (int) $existing['id']]);
            }
        }
    }

    /** @deprecated Use canSend() before send and recordSuccess() after success */
    public function reserve(int $tenantId, string $phone, ?int $userId = null, string $source = 'chat'): array
    {
        return $this->canSend($tenantId, $phone);
    }

    /**
     * @param array<int,string> $phones
     * @return array{allowed:bool,used:int,new_unique:int,remaining:int,limit:int,error:string,tenant_id:int}
     */
    public function checkBatch(int $tenantId, array $phones): array
    {
        if ($tenantId <= 0) {
            return [
                'allowed' => false,
                'used' => 0,
                'new_unique' => count($phones),
                'remaining' => 0,
                'limit' => 0,
                'error' => 'Tenant tidak valid.',
                'tenant_id' => 0,
            ];
        }

        $limit = $this->getLimit($tenantId);
        $today = $this->todayDate();
        $phones = array_values(array_unique(array_filter(array_map('strval', $phones))));
        if ($phones === []) {
            $used = $this->countUsed($tenantId, $today);
            return [
                'allowed' => true,
                'used' => $used,
                'new_unique' => 0,
                'remaining' => max(0, $limit - $used),
                'limit' => $limit,
                'error' => '',
                'tenant_id' => $tenantId,
            ];
        }

        $existingPhones = $this->findCountablePhones($tenantId, $today, $phones);
        $newUnique = count(array_diff($phones, $existingPhones));
        $used = $this->countUsed($tenantId, $today);
        $remaining = max(0, $limit - $used);

        if ($newUnique > $remaining) {
            return [
                'allowed' => false,
                'used' => $used,
                'new_unique' => $newUnique,
                'remaining' => $remaining,
                'limit' => $limit,
                'error' => 'Limit harian tidak cukup. Sisa kuota nomor unik terkirim (sent) hari ini: ' . $remaining . '.',
                'tenant_id' => $tenantId,
            ];
        }

        return [
            'allowed' => true,
            'used' => $used,
            'new_unique' => $newUnique,
            'remaining' => $remaining,
            'limit' => $limit,
            'error' => '',
            'tenant_id' => $tenantId,
        ];
    }

    public function countUsedToday(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }
        $row = $this->db->query('SELECT CURDATE() AS d')->row_array();
        $today = (string) ($row['d'] ?? date('Y-m-d'));
        return $this->countUsed($tenantId, $today);
    }

    private function findCountable(int $tenantId, string $today, string $phone): ?array
    {
        $statuses = self::COUNTABLE_STATUSES;
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $hasStatusCol = $this->hasStatusColumn();

        if (!$hasStatusCol) {
            return $this->db->query(
                "SELECT id FROM wa_key_daily_contacts
                 WHERE tenant_id = ? AND contact_date = ? AND phone = ?
                 LIMIT 1",
                [$tenantId, $today, $phone]
            )->row_array() ?: null;
        }

        return $this->db->query(
            "SELECT id FROM wa_key_daily_contacts
             WHERE tenant_id = ? AND contact_date = ? AND phone = ?
               AND status IN ({$placeholders})",
            array_merge([$tenantId, $today, $phone], $statuses)
        )->row_array() ?: null;
    }

    /** @param array<int,string> $phones */
    private function findCountablePhones(int $tenantId, string $today, array $phones): array
    {
        if ($phones === []) {
            return [];
        }

        $statuses = self::COUNTABLE_STATUSES;
        $phonePh = implode(',', array_fill(0, count($phones), '?'));
        $statusPh = implode(',', array_fill(0, count($statuses), '?'));
        $hasStatusCol = $this->hasStatusColumn();

        if (!$hasStatusCol) {
            $rows = $this->db->query(
                "SELECT phone FROM wa_key_daily_contacts
                 WHERE tenant_id = ? AND contact_date = ? AND phone IN ({$phonePh})",
                array_merge([$tenantId, $today], $phones)
            )->result_array();
        } else {
            $rows = $this->db->query(
                "SELECT phone FROM wa_key_daily_contacts
                 WHERE tenant_id = ? AND contact_date = ? AND phone IN ({$phonePh})
                   AND status IN ({$statusPh})",
                array_merge([$tenantId, $today], $phones, $statuses)
            )->result_array();
        }

        return array_map(static fn ($row) => (string) $row['phone'], $rows);
    }

    private function countUsed(int $tenantId, string $today): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        $statuses = self::COUNTABLE_STATUSES;
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $hasStatusCol = $this->hasStatusColumn();

        if (!$hasStatusCol) {
            $row = $this->db->query(
                "SELECT COUNT(*) AS cnt
                 FROM wa_key_daily_contacts
                 WHERE tenant_id = ? AND contact_date = ?",
                [$tenantId, $today]
            )->row_array();
        } else {
            $row = $this->db->query(
                "SELECT COUNT(*) AS cnt
                 FROM wa_key_daily_contacts
                 WHERE tenant_id = ? AND contact_date = ? AND status IN ({$placeholders})",
                array_merge([$tenantId, $today], $statuses)
            )->row_array();
        }

        return (int) ($row['cnt'] ?? 0);
    }

    private function hasStatusColumn(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wa_key_daily_contacts' AND COLUMN_NAME = 'status'"
            )->row_array();
            $cache = (int) ($row['cnt'] ?? 0) > 0;
        } catch (\Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    private function todayDate(): string
    {
        try {
            $row = $this->db->query('SELECT CURDATE() AS d')->row_array();
            return (string) ($row['d'] ?? date('Y-m-d'));
        } catch (\Throwable $e) {
            return date('Y-m-d');
        }
    }
}
