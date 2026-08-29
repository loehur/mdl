<?php

namespace App\Helpers\WaDesk;

/** Chooses the safest available Meta number for one template send. */
class TemplateChannelSelector
{
    public function __construct(private $db) {}

    /** @return array|null */
    public function select(int $tenantId, int $teamId, string $wabaId): ?array
    {
        if ($tenantId <= 0 || $teamId <= 0 || trim($wabaId) === '') return null;

        $rows = $this->db->query(
            "SELECT k.* FROM wa_channels k
             WHERE k.tenant_id = ? AND k.waba_id = ?
               AND k.provider = 'meta' AND k.status = 'active'
               AND k.template_sending_enabled = 1
               AND COALESCE(k.meta_phone_number_id, k.device_id, '') <> ''
               AND UPPER(COALESCE(k.meta_quality_rating, '')) IN ('GREEN', 'YELLOW')
               AND EXISTS (SELECT 1 FROM wa_channel_teams ct WHERE ct.channel_id = k.id AND ct.team_id = ?)
             ORDER BY k.template_sent_count ASC, k.inbound_count DESC,
               CASE UPPER(k.meta_quality_rating) WHEN 'GREEN' THEN 0 ELSE 1 END ASC, k.id ASC",
            [$tenantId, trim($wabaId), $teamId]
        )->result_array();
        if ($rows === []) return null;

        // All remaining exact ties are randomized, so no equally healthy number is favored.
        $first = $rows[0];
        $tied = array_values(array_filter($rows, static fn (array $row): bool =>
            (int) $row['template_sent_count'] === (int) $first['template_sent_count']
            && (int) $row['inbound_count'] === (int) $first['inbound_count']
            && strtoupper((string) $row['meta_quality_rating']) === strtoupper((string) $first['meta_quality_rating'])
        ));
        return $tied[random_int(0, count($tied) - 1)];
    }
}
