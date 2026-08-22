<?php

namespace App\Controllers\WaDesk;

/**
 * Team message reports — daily outbound stats.
 *
 * GET /WaDesk/Report/daily?from=YYYY-MM-DD&to=YYYY-MM-DD
 */
class Report extends WaDeskController
{
    private const MAX_DAYS = 90;

    /** @var list<string> */
    private const FAILED_STATUSES = ['failed', 'undelivered', 'error', 'rejected'];

    /** @var list<string> */
    private const DELIVERED_STATUSES = ['delivered', 'delivery', 'read', 'played'];

    /** @var list<string> */
    private const READ_STATUSES = ['read', 'played'];

    public function daily()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $this->verifyAuth();
        $user = $this->requireChatUser();

        if (!$this->hasOperationalTeam($user)) {
            $this->error('Masuk team dulu untuk melihat report', 403);
        }

        [$from, $to] = $this->parseDateRange(
            (string) $this->query('from', ''),
            (string) $this->query('to', '')
        );

        $tenantId = (int) $user['tenant_id'];
        $teamId = (int) $user['team_id'];

        $fromDt = $from . ' 00:00:00';
        $toExclusive = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';

        $rows = $this->db($this->db_index)->query(
            "SELECT DATE(m.created_at) AS report_date,
                    COUNT(*) AS total,
                    SUM(CASE WHEN m.direction = 'in' THEN 1 ELSE 0 END) AS total_in,
                    SUM(CASE WHEN m.direction = 'out' THEN 1 ELSE 0 END) AS total_out,
                    SUM(CASE WHEN m.direction = 'out'
                        AND LOWER(COALESCE(m.status, '')) IN ('failed','undelivered','error','rejected')
                        THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN m.direction = 'out'
                        AND LOWER(COALESCE(m.status, '')) IN ('delivered','delivery','read','played')
                        THEN 1 ELSE 0 END) AS delivered,
                    SUM(CASE WHEN m.direction = 'out'
                        AND LOWER(COALESCE(m.status, '')) IN ('read','played')
                        THEN 1 ELSE 0 END) AS read_count
             FROM messages m
             INNER JOIN conversations c ON c.id = m.conversation_id
             WHERE c.tenant_id = ? AND c.team_id = ?
               AND m.created_at >= ? AND m.created_at < ?
             GROUP BY DATE(m.created_at)
             ORDER BY report_date DESC",
            [$tenantId, $teamId, $fromDt, $toExclusive]
        )->result_array();

        $byDate = [];
        foreach ($rows as $row) {
            $d = (string) ($row['report_date'] ?? '');
            if ($d === '') {
                continue;
            }
            $totalOut = (int) ($row['total_out'] ?? 0);
            $failed = (int) ($row['failed'] ?? 0);
            $byDate[$d] = [
                'date' => $d,
                'total' => (int) ($row['total'] ?? 0),
                'total_in' => (int) ($row['total_in'] ?? 0),
                'total_out' => $totalOut,
                'failed' => $failed,
                'sent' => max(0, $totalOut - $failed),
                'delivered' => (int) ($row['delivered'] ?? 0),
                'read' => (int) ($row['read_count'] ?? 0),
            ];
        }

        $days = $this->fillDateRange($from, $to, $byDate);
        $summary = $this->summarizeDays($days);

        $team = $this->db($this->db_index)->query(
            "SELECT name FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, $tenantId]
        )->row_array();

        $this->success([
            'team_id' => $teamId,
            'team_name' => (string) ($team['name'] ?? ''),
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'summary' => $summary,
        ]);
    }

    /** @return array{0:string,1:string} */
    private function parseDateRange(string $fromRaw, string $toRaw): array
    {
        $to = $this->normalizeDate($toRaw) ?: date('Y-m-d');
        $from = $this->normalizeDate($fromRaw) ?: date('Y-m-d', strtotime($to . ' -29 days'));

        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $days = (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($days > self::MAX_DAYS) {
            $from = date('Y-m-d', strtotime($to . ' -' . (self::MAX_DAYS - 1) . ' days'));
        }

        return [$from, $to];
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /** @param array<string,array> $byDate @return list<array> */
    private function fillDateRange(string $from, string $to, array $byDate): array
    {
        $out = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        while ($cursor <= $end) {
            $d = date('Y-m-d', $cursor);
            $out[] = $byDate[$d] ?? [
                'date' => $d,
                'total' => 0,
                'total_in' => 0,
                'total_out' => 0,
                'failed' => 0,
                'sent' => 0,
                'delivered' => 0,
                'read' => 0,
            ];
            $cursor = strtotime('+1 day', $cursor);
        }

        usort($out, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $out;
    }

    /** @param list<array> $days @return array<string,int> */
    private function summarizeDays(array $days): array
    {
        $sum = [
            'total' => 0,
            'total_in' => 0,
            'total_out' => 0,
            'sent' => 0,
            'failed' => 0,
            'delivered' => 0,
            'read' => 0,
        ];
        foreach ($days as $day) {
            foreach ($sum as $k => $_) {
                $sum[$k] += (int) ($day[$k] ?? 0);
            }
        }
        return $sum;
    }
}
