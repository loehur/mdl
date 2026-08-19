<?php

namespace App\Helpers\WaDesk;

/**
 * Enforce per-tenant daily limit on unique destination numbers.
 *
 * Rule:
 * - Max N unique phones per tenant per day (tenants.daily_unique_limit, default 250)
 * - Same phone can be retried multiple times in the same day
 * - Shared across all channels/teams in the tenant
 */
class DailyKeyLimit
{
    public const DEFAULT_DAILY_UNIQUE_LIMIT = 250;

    private $db;

    /** @var array<int,int> */
    private array $limitCache = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getLimit(int $tenantId): int
    {
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
            /* column may not exist yet — use default */
        }

        $this->limitCache[$tenantId] = $limit;
        return $limit;
    }

    /**
     * Reserve today's unique-phone slot for this tenant+phone.
     *
     * @return array{allowed:bool,is_new:bool,used:int,limit:int,error:string}
     */
    public function reserve(int $tenantId, string $phone, ?int $userId = null, string $source = 'chat'): array
    {
        $limit = $this->getLimit($tenantId);
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->query(
            "SELECT id FROM wa_key_daily_contacts
             WHERE tenant_id = ? AND contact_date = ? AND phone = ?
             LIMIT 1",
            [$tenantId, $today, $phone]
        )->row_array();

        if ($existing) {
            $this->db->update('wa_key_daily_contacts', [
                'last_user_id' => $userId ?: null,
                'last_source' => $source,
                'last_attempt_at' => $now,
            ], ['id' => (int) $existing['id']]);

            return [
                'allowed' => true,
                'is_new' => false,
                'used' => $this->countUsed($tenantId, $today),
                'limit' => $limit,
                'error' => '',
            ];
        }

        $used = $this->countUsed($tenantId, $today);
        if ($used >= $limit) {
            return [
                'allowed' => false,
                'is_new' => false,
                'used' => $used,
                'limit' => $limit,
                'error' => 'Limit harian tenant tercapai: maksimal ' . $limit . ' nomor customer unik per hari.',
            ];
        }

        try {
            $this->db->insert('wa_key_daily_contacts', [
                'tenant_id' => $tenantId,
                'contact_date' => $today,
                'phone' => $phone,
                'first_user_id' => $userId ?: null,
                'last_user_id' => $userId ?: null,
                'first_source' => $source,
                'last_source' => $source,
                'first_attempt_at' => $now,
                'last_attempt_at' => $now,
            ]);
        } catch (\Throwable $e) {
            $existing = $this->db->query(
                "SELECT id FROM wa_key_daily_contacts
                 WHERE tenant_id = ? AND contact_date = ? AND phone = ?
                 LIMIT 1",
                [$tenantId, $today, $phone]
            )->row_array();

            if (!$existing) {
                $used = $this->countUsed($tenantId, $today);
                if ($used >= $limit) {
                    return [
                        'allowed' => false,
                        'is_new' => false,
                        'used' => $used,
                        'limit' => $limit,
                        'error' => 'Limit harian tenant tercapai: maksimal ' . $limit . ' nomor customer unik per hari.',
                    ];
                }
                return [
                    'allowed' => false,
                    'is_new' => false,
                    'used' => $used,
                    'limit' => $limit,
                    'error' => 'Gagal mencatat penggunaan harian tenant.',
                ];
            }
        }

        return [
            'allowed' => true,
            'is_new' => true,
            'used' => $used + 1,
            'limit' => $limit,
            'error' => '',
        ];
    }

    /**
     * @param array<int,string> $phones
     * @return array{allowed:bool,used:int,new_unique:int,remaining:int,limit:int,error:string}
     */
    public function checkBatch(int $tenantId, array $phones): array
    {
        $limit = $this->getLimit($tenantId);
        $today = date('Y-m-d');
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
            ];
        }

        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        $existingRows = $this->db->query(
            "SELECT phone FROM wa_key_daily_contacts
             WHERE tenant_id = ? AND contact_date = ? AND phone IN ({$placeholders})",
            array_merge([$tenantId, $today], $phones)
        )->result_array();

        $existingPhones = array_map(static fn ($row) => (string) $row['phone'], $existingRows);
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
                'error' => 'Limit harian tenant tidak cukup. Sisa kuota nomor unik hari ini: ' . $remaining . '.',
            ];
        }

        return [
            'allowed' => true,
            'used' => $used,
            'new_unique' => $newUnique,
            'remaining' => $remaining,
            'limit' => $limit,
            'error' => '',
        ];
    }

    private function countUsed(int $tenantId, string $today): int
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS cnt
             FROM wa_key_daily_contacts
             WHERE tenant_id = ? AND contact_date = ?",
            [$tenantId, $today]
        )->row_array();

        return (int) ($row['cnt'] ?? 0);
    }
}
