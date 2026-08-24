<?php

namespace App\Helpers\WaDesk;

/**
 * Enforce per-WABA daily limit on unique destination numbers (successful sends only).
 *
 * Rule:
 * - Max N unique phones per WABA per day (wa_waba_daily_limits, default from tenants.daily_unique_limit)
 * - Only counts phones with status >= sent (sent, delivered, read)
 * - Failed sends are NOT counted
 * - Same phone can be retried; still counts as one unique slot once sent
 */
class DailyKeyLimit
{
    public const DEFAULT_DAILY_UNIQUE_LIMIT = 250;

    /** Statuses that consume daily unique quota. */
    public const COUNTABLE_STATUSES = ['sent', 'delivered', 'read'];

    private $db;

    /** @var array<string,int> */
    private array $limitCache = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function ensureLimitRow(string $wabaId, int $tenantId, ?string $label = null): void
    {
        $wabaId = trim($wabaId);
        if ($wabaId === '') {
            return;
        }

        $existing = $this->db->query(
            "SELECT waba_id FROM wa_waba_daily_limits WHERE waba_id = ? LIMIT 1",
            [$wabaId]
        )->row_array();
        if ($existing) {
            return;
        }

        $default = self::DEFAULT_DAILY_UNIQUE_LIMIT;
        try {
            $row = $this->db->query(
                "SELECT daily_unique_limit FROM tenants WHERE id = ? LIMIT 1",
                [$tenantId]
            )->row_array();
            $val = (int) ($row['daily_unique_limit'] ?? 0);
            if ($val > 0) {
                $default = $val;
            }
        } catch (\Throwable $e) {
            /* ignore */
        }

        try {
            $this->db->insert('wa_waba_daily_limits', [
                'waba_id' => $wabaId,
                'tenant_id' => $tenantId,
                'daily_unique_limit' => $default,
                'label' => $label !== null && trim($label) !== '' ? trim($label) : null,
            ]);
        } catch (\Throwable $e) {
            /* parallel insert race */
        }
    }

    public function getLimit(string $wabaId, int $tenantId): int
    {
        $wabaId = trim($wabaId);
        if ($wabaId === '') {
            return self::DEFAULT_DAILY_UNIQUE_LIMIT;
        }

        if (isset($this->limitCache[$wabaId])) {
            return $this->limitCache[$wabaId];
        }

        $this->ensureLimitRow($wabaId, $tenantId);

        $limit = self::DEFAULT_DAILY_UNIQUE_LIMIT;
        try {
            $row = $this->db->query(
                "SELECT daily_unique_limit FROM wa_waba_daily_limits WHERE waba_id = ? LIMIT 1",
                [$wabaId]
            )->row_array();
            $val = (int) ($row['daily_unique_limit'] ?? 0);
            if ($val > 0) {
                $limit = $val;
            } else {
                $tenantRow = $this->db->query(
                    "SELECT daily_unique_limit FROM tenants WHERE id = ? LIMIT 1",
                    [$tenantId]
                )->row_array();
                $tenantVal = (int) ($tenantRow['daily_unique_limit'] ?? 0);
                if ($tenantVal > 0) {
                    $limit = $tenantVal;
                }
            }
        } catch (\Throwable $e) {
            /* table/column may not exist yet */
        }

        $this->limitCache[$wabaId] = $limit;
        return $limit;
    }

    /**
     * @return array{allowed:bool,is_new:bool,used:int,limit:int,error:string,waba_id:string}
     */
    public function canSend(string $wabaId, int $tenantId, string $phone): array
    {
        $wabaId = trim($wabaId);
        if ($wabaId === '') {
            return [
                'allowed' => false,
                'is_new' => false,
                'used' => 0,
                'limit' => 0,
                'error' => 'WABA ID belum diatur untuk channel ini. Isi manual di Admin → Channel.',
                'waba_id' => '',
            ];
        }

        $limit = $this->getLimit($wabaId, $tenantId);
        $today = $this->todayDate();

        $existing = $this->findCountable($wabaId, $today, $phone);
        if ($existing) {
            return [
                'allowed' => true,
                'is_new' => false,
                'used' => $this->countUsed($wabaId, $today),
                'limit' => $limit,
                'error' => '',
                'waba_id' => $wabaId,
            ];
        }

        $used = $this->countUsed($wabaId, $today);
        if ($used >= $limit) {
            return [
                'allowed' => false,
                'is_new' => true,
                'used' => $used,
                'limit' => $limit,
                'error' => 'Limit harian WABA tercapai: maksimal ' . $limit . ' nomor unik terkirim (sent) per hari.',
                'waba_id' => $wabaId,
            ];
        }

        return [
            'allowed' => true,
            'is_new' => true,
            'used' => $used,
            'limit' => $limit,
            'error' => '',
            'waba_id' => $wabaId,
        ];
    }

    public function recordSuccess(
        string $wabaId,
        int $tenantId,
        string $phone,
        ?int $userId = null,
        string $source = 'chat'
    ): void {
        $wabaId = trim($wabaId);
        if ($wabaId === '') {
            return;
        }

        $this->ensureLimitRow($wabaId, $tenantId);

        $today = $this->todayDate();
        $now = date('Y-m-d H:i:s');
        $phone = trim($phone);
        if ($phone === '') {
            return;
        }

        $existing = $this->findCountable($wabaId, $today, $phone);
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
                'waba_id' => $wabaId,
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
                    'wa_key_daily_contacts insert failed waba=' . $wabaId . ' phone=' . $phone . ' err=' . $e->getMessage(),
                    'wadesk',
                    'DailyKeyLimit'
                );
            } catch (\Throwable $ignored) {
            }
            $existing = $this->findCountable($wabaId, $today, $phone);
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
    public function reserve(string $wabaId, int $tenantId, string $phone, ?int $userId = null, string $source = 'chat'): array
    {
        return $this->canSend($wabaId, $tenantId, $phone);
    }

    /**
     * @param array<int,string> $phones
     * @return array{allowed:bool,used:int,new_unique:int,remaining:int,limit:int,error:string,waba_id:string}
     */
    public function checkBatch(string $wabaId, int $tenantId, array $phones): array
    {
        $wabaId = trim($wabaId);
        if ($wabaId === '') {
            return [
                'allowed' => false,
                'used' => 0,
                'new_unique' => count($phones),
                'remaining' => 0,
                'limit' => 0,
                'error' => 'WABA ID belum diatur untuk channel ini. Isi manual di Admin → Channel.',
                'waba_id' => '',
            ];
        }

        $limit = $this->getLimit($wabaId, $tenantId);
        $today = $this->todayDate();
        $phones = array_values(array_unique(array_filter(array_map('strval', $phones))));
        if ($phones === []) {
            $used = $this->countUsed($wabaId, $today);
            return [
                'allowed' => true,
                'used' => $used,
                'new_unique' => 0,
                'remaining' => max(0, $limit - $used),
                'limit' => $limit,
                'error' => '',
                'waba_id' => $wabaId,
            ];
        }

        $existingPhones = $this->findCountablePhones($wabaId, $today, $phones);
        $newUnique = count(array_diff($phones, $existingPhones));
        $used = $this->countUsed($wabaId, $today);
        $remaining = max(0, $limit - $used);

        if ($newUnique > $remaining) {
            return [
                'allowed' => false,
                'used' => $used,
                'new_unique' => $newUnique,
                'remaining' => $remaining,
                'limit' => $limit,
                'error' => 'Limit harian WABA tidak cukup. Sisa kuota nomor unik terkirim (sent) hari ini: ' . $remaining . '.',
                'waba_id' => $wabaId,
            ];
        }

        return [
            'allowed' => true,
            'used' => $used,
            'new_unique' => $newUnique,
            'remaining' => $remaining,
            'limit' => $limit,
            'error' => '',
            'waba_id' => $wabaId,
        ];
    }

    public function countUsedToday(string $wabaId): int
    {
        $wabaId = trim($wabaId);
        if ($wabaId === '') {
            return 0;
        }
        $row = $this->db->query('SELECT CURDATE() AS d')->row_array();
        $today = (string) ($row['d'] ?? date('Y-m-d'));
        return $this->countUsed($wabaId, $today);
    }

    private function findCountable(string $wabaId, string $today, string $phone): ?array
    {
        $statuses = self::COUNTABLE_STATUSES;
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $hasStatusCol = $this->hasStatusColumn();
        $hasWabaCol = $this->hasWabaColumn();

        if (!$hasWabaCol) {
            return $this->findCountableLegacy((int) $this->tenantIdFromWaba($wabaId), $today, $phone);
        }

        if (!$hasStatusCol) {
            return $this->db->query(
                "SELECT id FROM wa_key_daily_contacts
                 WHERE waba_id = ? AND contact_date = ? AND phone = ?
                 LIMIT 1",
                [$wabaId, $today, $phone]
            )->row_array() ?: null;
        }

        return $this->db->query(
            "SELECT id FROM wa_key_daily_contacts
             WHERE waba_id = ? AND contact_date = ? AND phone = ?
               AND status IN ({$placeholders})",
            array_merge([$wabaId, $today, $phone], $statuses)
        )->row_array() ?: null;
    }

    /** @param array<int,string> $phones */
    private function findCountablePhones(string $wabaId, string $today, array $phones): array
    {
        if ($phones === [] || !$this->hasWabaColumn()) {
            return [];
        }

        $statuses = self::COUNTABLE_STATUSES;
        $phonePh = implode(',', array_fill(0, count($phones), '?'));
        $statusPh = implode(',', array_fill(0, count($statuses), '?'));
        $hasStatusCol = $this->hasStatusColumn();

        if (!$hasStatusCol) {
            $rows = $this->db->query(
                "SELECT phone FROM wa_key_daily_contacts
                 WHERE waba_id = ? AND contact_date = ? AND phone IN ({$phonePh})",
                array_merge([$wabaId, $today], $phones)
            )->result_array();
        } else {
            $rows = $this->db->query(
                "SELECT phone FROM wa_key_daily_contacts
                 WHERE waba_id = ? AND contact_date = ? AND phone IN ({$phonePh})
                   AND status IN ({$statusPh})",
                array_merge([$wabaId, $today], $phones, $statuses)
            )->result_array();
        }

        return array_map(static fn ($row) => (string) $row['phone'], $rows);
    }

    private function countUsed(string $wabaId, string $today): int
    {
        if ($wabaId === '' || !$this->hasWabaColumn()) {
            return 0;
        }

        $statuses = self::COUNTABLE_STATUSES;
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $hasStatusCol = $this->hasStatusColumn();

        if (!$hasStatusCol) {
            $row = $this->db->query(
                "SELECT COUNT(*) AS cnt
                 FROM wa_key_daily_contacts
                 WHERE waba_id = ? AND contact_date = ?",
                [$wabaId, $today]
            )->row_array();
        } else {
            $row = $this->db->query(
                "SELECT COUNT(*) AS cnt
                 FROM wa_key_daily_contacts
                 WHERE waba_id = ? AND contact_date = ? AND status IN ({$placeholders})",
                array_merge([$wabaId, $today], $statuses)
            )->row_array();
        }

        return (int) ($row['cnt'] ?? 0);
    }

    /** Fallback bila migration belum jalan (tenant-level lama). */
    private function findCountableLegacy(int $tenantId, string $today, string $phone): ?array
    {
        if ($tenantId <= 0) {
            return null;
        }
        return $this->db->query(
            "SELECT id FROM wa_key_daily_contacts
             WHERE tenant_id = ? AND contact_date = ? AND phone = ?
             LIMIT 1",
            [$tenantId, $today, $phone]
        )->row_array() ?: null;
    }

    private function tenantIdFromWaba(string $wabaId): int
    {
        try {
            $row = $this->db->query(
                "SELECT tenant_id FROM wa_waba_daily_limits WHERE waba_id = ? LIMIT 1",
                [$wabaId]
            )->row_array();
            return (int) ($row['tenant_id'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
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

    private function hasWabaColumn(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wa_key_daily_contacts' AND COLUMN_NAME = 'waba_id'"
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
