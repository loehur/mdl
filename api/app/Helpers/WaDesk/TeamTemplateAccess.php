<?php

namespace App\Helpers\WaDesk;

/** Determines whether a team may send WhatsApp templates today (WIB). */
final class TeamTemplateAccess
{
    public function __construct(private $db) {}

    public function allowed(int $teamId, int $tenantId): bool
    {
        if ($teamId <= 0 || $tenantId <= 0) return false;
        $row = $this->db->query(
            'SELECT template_access_expires_at FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$teamId, $tenantId]
        )->row_array();
        if (!$row) return false;
        $expiresAt = trim((string) ($row['template_access_expires_at'] ?? ''));
        return $expiresAt === '' || $expiresAt >= ChannelDailyStats::wibDate();
    }
}
