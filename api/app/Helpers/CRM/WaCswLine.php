<?php

namespace App\Helpers\CRM;

use App\Config\WaLines;

/**
 * CSW per pasangan (customer_phone, business_phone).
 */
class WaCswLine
{
    public static function tableReady($db): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $q = $db->query(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                ['wa_csw_by_line']
            );
            $cache = $q && $q->num_rows() > 0;
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    public static function normalizeCustomerPhone(string $phone): string
    {
        return CrmChatMergeHelper::normalizeWaNumber($phone);
    }

    public static function touch($db, string $customerPhone, string $businessPhone, ?string $at = null): void
    {
        if (!self::tableReady($db)) {
            return;
        }

        $customer = self::normalizeCustomerPhone($customerPhone);
        $business = WaLines::normalizeE164($businessPhone);
        if ($customer === '' || $business === '') {
            return;
        }

        $ts = $at ?? date('Y-m-d H:i:s');

        try {
            $db->query(
                'INSERT INTO wa_csw_by_line (customer_phone, business_phone, last_in_at, updated_at)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    last_in_at = GREATEST(last_in_at, VALUES(last_in_at)),
                    updated_at = VALUES(updated_at)',
                [$customer, $business, $ts, $ts]
            );
        } catch (\Throwable $e) {
            if (class_exists('\\Log')) {
                \Log::write('WaCswLine::touch ' . $e->getMessage(), 'wa_error', 'CSW');
            }
        }
    }

    public static function getLastInAt($db, string $customerPhone, string $businessPhone): ?string
    {
        if (!self::tableReady($db)) {
            return self::legacyLastInAt($db, $customerPhone, $businessPhone);
        }

        $customer = self::normalizeCustomerPhone($customerPhone);
        $business = WaLines::normalizeE164($businessPhone);
        if ($customer === '' || $business === '') {
            return null;
        }

        try {
            $q = $db->query(
                'SELECT last_in_at FROM wa_csw_by_line WHERE customer_phone = ? AND business_phone = ? LIMIT 1',
                [$customer, $business]
            );
            if ($q && $q->num_rows() > 0) {
                $row = $q->row();
                if ($row && !empty($row->last_in_at)) {
                    return (string) $row->last_in_at;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /** Fallback sebelum migration / baris belum ada. */
    private static function legacyLastInAt($db, string $customerPhone, string $businessPhone): ?string
    {
        if (!class_exists(\App\Config\WaLines::class)) {
            require_once __DIR__ . '/../../Config/WaLines.php';
        }
        $business = \App\Config\WaLines::normalizeE164($businessPhone);
        $csPhone = \App\Config\WaLines::get(\App\Config\WaLines::KEY_CS)['phone'] ?? '';

        if ($business === $csPhone) {
            return CrmChatMergeHelper::getLegacyConversationLastInAt($db, $customerPhone);
        }

        return null;
    }
}
