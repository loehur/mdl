<?php

namespace App\Helpers\WaDesk;

/**
 * Enforce per-channel daily limit on unique destination numbers.
 *
 * Rule:
 * - Max 250 unique phones per channel_id per day
 * - Same phone can be retried multiple times in the same day
 * - Failed and successful attempts both count
 * - Limit is shared across all users using the same API key
 */
class DailyKeyLimit
{
    public const DAILY_UNIQUE_LIMIT = 250;

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Reserve today's unique-phone slot for this key+phone.
     * Returns allowed=false if quota is exhausted for a brand new phone today.
     *
     * @return array{allowed:bool,is_new:bool,used:int,limit:int,error:string}
     */
    public function reserve(int $channelId, string $phone, ?int $userId = null, string $source = 'chat'): array
    {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->query(
            "SELECT id FROM wa_key_daily_contacts
             WHERE channel_id = ? AND contact_date = ? AND phone = ?
             LIMIT 1",
            [$channelId, $today, $phone]
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
                'used' => $this->countUsed($channelId, $today),
                'limit' => self::DAILY_UNIQUE_LIMIT,
                'error' => '',
            ];
        }

        $used = $this->countUsed($channelId, $today);
        if ($used >= self::DAILY_UNIQUE_LIMIT) {
            return [
                'allowed' => false,
                'is_new' => false,
                'used' => $used,
                'limit' => self::DAILY_UNIQUE_LIMIT,
                'error' => 'Limit harian API key tercapai: maksimal 250 nomor customer unik per hari.',
            ];
        }

        try {
            $this->db->insert('wa_key_daily_contacts', [
                'channel_id' => $channelId,
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
            // If another request inserted the same phone in parallel, allow it.
            $existing = $this->db->query(
                "SELECT id FROM wa_key_daily_contacts
                 WHERE channel_id = ? AND contact_date = ? AND phone = ?
                 LIMIT 1",
                [$channelId, $today, $phone]
            )->row_array();

            if (!$existing) {
                $used = $this->countUsed($channelId, $today);
                if ($used >= self::DAILY_UNIQUE_LIMIT) {
                    return [
                        'allowed' => false,
                        'is_new' => false,
                        'used' => $used,
                        'limit' => self::DAILY_UNIQUE_LIMIT,
                        'error' => 'Limit harian API key tercapai: maksimal 250 nomor customer unik per hari.',
                    ];
                }
                return [
                    'allowed' => false,
                    'is_new' => false,
                    'used' => $used,
                    'limit' => self::DAILY_UNIQUE_LIMIT,
                    'error' => 'Gagal mencatat penggunaan harian API key.',
                ];
            }
        }

        return [
            'allowed' => true,
            'is_new' => true,
            'used' => $used + 1,
            'limit' => self::DAILY_UNIQUE_LIMIT,
            'error' => '',
        ];
    }

    /**
     * Estimate whether a blast can be created now.
     *
     * @param array<int,string> $phones
     * @return array{allowed:bool,used:int,new_unique:int,remaining:int,limit:int,error:string}
     */
    public function checkBatch(int $channelId, array $phones): array
    {
        $today = date('Y-m-d');
        $phones = array_values(array_unique(array_filter(array_map('strval', $phones))));
        if ($phones === []) {
            $used = $this->countUsed($channelId, $today);
            return [
                'allowed' => true,
                'used' => $used,
                'new_unique' => 0,
                'remaining' => max(0, self::DAILY_UNIQUE_LIMIT - $used),
                'limit' => self::DAILY_UNIQUE_LIMIT,
                'error' => '',
            ];
        }

        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        $existingRows = $this->db->query(
            "SELECT phone FROM wa_key_daily_contacts
             WHERE channel_id = ? AND contact_date = ? AND phone IN ({$placeholders})",
            array_merge([$channelId, $today], $phones)
        )->result_array();

        $existingPhones = array_map(static fn($row) => (string) $row['phone'], $existingRows);
        $newUnique = count(array_diff($phones, $existingPhones));
        $used = $this->countUsed($channelId, $today);
        $remaining = max(0, self::DAILY_UNIQUE_LIMIT - $used);

        if ($newUnique > $remaining) {
            return [
                'allowed' => false,
                'used' => $used,
                'new_unique' => $newUnique,
                'remaining' => $remaining,
                'limit' => self::DAILY_UNIQUE_LIMIT,
                'error' => 'Limit harian API key tidak cukup. Sisa kuota nomor unik hari ini: ' . $remaining . '.',
            ];
        }

        return [
            'allowed' => true,
            'used' => $used,
            'new_unique' => $newUnique,
            'remaining' => $remaining,
            'limit' => self::DAILY_UNIQUE_LIMIT,
            'error' => '',
        ];
    }

    private function countUsed(int $channelId, string $today): int
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS cnt
             FROM wa_key_daily_contacts
             WHERE channel_id = ? AND contact_date = ?",
            [$channelId, $today]
        )->row_array();

        return (int) ($row['cnt'] ?? 0);
    }
}
