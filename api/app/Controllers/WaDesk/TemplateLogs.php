<?php

namespace App\Controllers\WaDesk;

/**
 * Admin log of failed template sends.
 *
 * GET /WaDesk/TemplateLogs/list?page=1&limit=50
 */
class TemplateLogs extends WaDeskController
{
    public function list()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];

        if (!$this->templateFailLogsTableExists()) {
            $this->success([
                'logs' => [],
                'total' => 0,
                'page' => 1,
                'limit' => 50,
                'table_ready' => false,
            ]);
            return;
        }

        $page = max(1, (int) $this->query('page', 1));
        $limit = min(100, max(1, (int) $this->query('limit', 50)));
        $offset = ($page - 1) * $limit;

        $countRow = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS cnt FROM wa_template_fail_logs WHERE tenant_id = ?",
            [$tenantId]
        )->row_array();
        $total = (int) ($countRow['cnt'] ?? 0);

        $tbl = $this->channelsTable();
        $rows = $this->db($this->db_index)->query(
            "SELECT l.*,
                    u.name AS user_name,
                    tm.name AS team_name,
                    k.label AS channel_label,
                    k.phone_number AS channel_phone
             FROM wa_template_fail_logs l
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN teams tm ON tm.id = l.team_id
             LEFT JOIN {$tbl} k ON k.id = l.channel_id
             WHERE l.tenant_id = ?
             ORDER BY l.id DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $limit, $offset]
        )->result_array();

        foreach ($rows as &$row) {
            $row['request'] = $this->decodeJsonColumn($row['request_json'] ?? null);
            $row['response'] = $this->decodeJsonColumn($row['response_json'] ?? null);
            unset($row['request_json'], $row['response_json']);
        }

        $this->success([
            'logs' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'table_ready' => true,
        ]);
    }

    /** @return array<string,mixed>|list<mixed>|null */
    private function decodeJsonColumn($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
