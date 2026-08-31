<?php

namespace App\Helpers\WaDesk;

/** Chooses the safest available Meta number for one template send. */
class TemplateChannelSelector
{
    /**
     * Quality ratings that are safe for template sending.
     * UNKNOWN/NA/empty mean Meta has not gathered enough data yet — not that the
     * number is penalized. Only RED is actually blocked by Meta policy.
     */
    public const ELIGIBLE_QUALITIES = ['GREEN', 'YELLOW', 'UNKNOWN', 'NA', ''];

    public static function qualityEligible(string $quality): bool
    {
        return in_array(strtoupper(trim($quality)), self::ELIGIBLE_QUALITIES, true);
    }

    public static function qualitySql(string $alias = 'k'): string
    {
        return "UPPER(COALESCE({$alias}.meta_quality_rating, '')) IN ('GREEN', 'YELLOW', 'UNKNOWN', 'NA', '')";
    }

    public function __construct(private $db) {}

    /** @return array|null */
    public function select(int $tenantId, int $teamId, string $wabaId): ?array
    {
        if ($tenantId <= 0 || $teamId <= 0 || trim($wabaId) === '') return null;

        $today = ChannelDailyStats::wibDate();
        $rows = $this->db->query(
            "SELECT k.*, COALESCE(d.template_sent_count, 0) AS daily_template_sent_count
             FROM wa_channels k
             LEFT JOIN wa_channel_daily_stats d ON d.channel_id = k.id AND d.stat_date = ?
             WHERE k.tenant_id = ? AND k.waba_id = ?
               AND k.provider = 'meta' AND k.status = 'active'
               AND k.template_sending_enabled = 1
               AND UPPER(COALESCE(k.meta_provider_status, '')) = 'CONNECTED'
               AND COALESCE(k.meta_phone_number_id, k.device_id, '') <> ''
               AND " . self::qualitySql('k') . "
               AND EXISTS (SELECT 1 FROM wa_channel_teams ct WHERE ct.channel_id = k.id AND ct.team_id = ?)
             ORDER BY COALESCE(d.template_sent_count, 0) ASC, k.inbound_count DESC,
               CASE UPPER(k.meta_quality_rating) WHEN 'GREEN' THEN 0 WHEN 'YELLOW' THEN 1 ELSE 2 END ASC, k.id ASC",
            [$today, $tenantId, trim($wabaId), $teamId]
        )->result_array();
        if ($rows === []) return null;

        // All remaining exact ties are randomized, so no equally healthy number is favored.
        $first = $rows[0];
        $tied = array_values(array_filter($rows, static fn (array $row): bool =>
            (int) $row['daily_template_sent_count'] === (int) $first['daily_template_sent_count']
            && (int) $row['inbound_count'] === (int) $first['inbound_count']
            && strtoupper((string) $row['meta_quality_rating']) === strtoupper((string) $first['meta_quality_rating'])
        ));
        return $tied[random_int(0, count($tied) - 1)];
    }
}
