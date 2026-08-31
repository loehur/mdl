<?php

namespace App\Helpers\WaDesk;

/** Atomic per-team daily template-send cap, measured in WIB. */
final class TeamDailyTemplateLimit
{
    public const DEFAULT_LIMIT = 250;

    public function __construct(private $db) {}

    public function summary(int $teamId, int $tenantId): array
    {
        $limit = $this->limit($teamId, $tenantId);
        $row = $this->db->query('SELECT template_sent_count FROM wa_team_daily_template_stats WHERE team_id = ? AND stat_date = ?', [$teamId, ChannelDailyStats::wibDate()])->row_array();
        $used = (int) ($row['template_sent_count'] ?? 0);
        return ['limit' => $limit, 'used' => $used, 'remaining' => max(0, $limit - $used)];
    }

    /** Atomically reserve sends before the provider call. */
    public function reserve(int $teamId, int $tenantId, int $count = 1): array
    {
        $count = max(1, $count);
        $limit = $this->limit($teamId, $tenantId);
        $date = ChannelDailyStats::wibDate();
        $this->db->query('INSERT IGNORE INTO wa_team_daily_template_stats (team_id, stat_date) VALUES (?, ?)', [$teamId, $date]);
        $this->db->query('UPDATE wa_team_daily_template_stats SET template_sent_count = template_sent_count + ? WHERE team_id = ? AND stat_date = ? AND template_sent_count + ? <= ?', [$count, $teamId, $date, $count, $limit]);
        if ((int) $this->db->affected_rows() > 0) return ['ok' => true] + $this->summary($teamId, $tenantId);
        return ['ok' => false] + $this->summary($teamId, $tenantId);
    }

    public function release(int $teamId, int $count = 1): void
    {
        $this->db->query('UPDATE wa_team_daily_template_stats SET template_sent_count = GREATEST(0, template_sent_count - ?) WHERE team_id = ? AND stat_date = ?', [max(1, $count), $teamId, ChannelDailyStats::wibDate()]);
    }

    private function limit(int $teamId, int $tenantId): int
    {
        $row = $this->db->query('SELECT daily_template_limit FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1', [$teamId, $tenantId])->row_array();
        return max(1, (int) ($row['daily_template_limit'] ?? self::DEFAULT_LIMIT));
    }
}
