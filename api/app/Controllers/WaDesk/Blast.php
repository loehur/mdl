<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDeskDailyKeyLimit;

/**
 * Blast — admin-only bulk WhatsApp template sender via CSV.
 *
 * Routes (add to your framework router):
 *   GET  /WaDesk/Blast/csvHeaders?template_id=
 *   POST /WaDesk/Blast/create
 *   GET  /WaDesk/Blast/list
 *   GET  /WaDesk/Blast/detail?id=
 *   POST /WaDesk/Blast/cancel
 */
class Blast extends WaDeskController
{
    private const MAX_ROWS = 250;

    // -------------------------------------------------------------------------
    // GET /WaDesk/Blast/csvHeaders?template_id=
    // Returns expected CSV columns for a given template.
    // -------------------------------------------------------------------------
    public function csvHeaders()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $templateId = (int) $this->query('template_id', 0);
        if ($templateId <= 0) {
            $this->error('template_id wajib', 400);
        }

        $tpl = $this->db($this->db_index)->query(
            "SELECT t.* FROM wa_templates t
             INNER JOIN ycloud_keys k ON k.id = t.ycloud_key_id
             WHERE t.id = ? AND k.tenant_id = ? LIMIT 1",
            [$templateId, (int) $admin['tenant_id']]
        )->row_array();

        if (!$tpl) {
            $this->error('Template tidak ditemukan', 404);
        }

        $paramDefs = $this->loadParamDefs($templateId);
        $params = $this->buildCsvParamMeta($paramDefs);

        $headers = ['phone'];
        foreach ($params as $p) {
            $headers[] = $p['key'];
        }

        $this->success([
            'template_id'    => (int) $tpl['id'],
            'template_name'  => $tpl['template_name'],
            'body_preview'   => $tpl['body_preview'],
            'headers'        => $headers,
            'params'         => $params,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /WaDesk/Blast/create
    // Body: { campaign_name, ycloud_key_id, template_id, rows: [{phone, params: {}}] }
    // -------------------------------------------------------------------------
    public function create()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();

        $campaignName = trim((string) ($body['campaign_name'] ?? ''));
        if ($campaignName === '') {
            $this->error('campaign_name wajib', 400);
        }
        if (mb_strlen($campaignName) > 150) {
            $this->error('campaign_name maksimal 150 karakter', 400);
        }

        $keyId = (int) ($body['ycloud_key_id'] ?? 0);
        $templateId = (int) ($body['template_id'] ?? 0);

        if ($keyId <= 0 || $templateId <= 0) {
            $this->error('ycloud_key_id dan template_id wajib', 400);
        }

        // Validate key ownership
        $key = $this->db($this->db_index)->query(
            "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? AND status = 'active' LIMIT 1",
            [$keyId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$key) {
            $this->error('API key tidak ditemukan atau tidak aktif', 404);
        }

        // Validate template ownership
        $tpl = $this->db($this->db_index)->query(
            "SELECT * FROM wa_templates WHERE id = ? AND ycloud_key_id = ? LIMIT 1",
            [$templateId, $keyId]
        )->row_array();
        if (!$tpl) {
            $this->error('Template tidak ditemukan', 404);
        }

        $rows = $body['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) {
            $this->error('rows wajib dan tidak boleh kosong', 400);
        }
        if (count($rows) > self::MAX_ROWS) {
            $this->error('Maksimal ' . self::MAX_ROWS . ' baris per blast', 400);
        }

        $paramDefs = $this->loadParamDefs($templateId);

        // Validate each row
        $errors = [];
        foreach ($rows as $i => $row) {
            $phone = $this->normalizePhone((string) ($row['phone'] ?? ''));
            if (strlen($phone) < 9) {
                $errors[] = "Baris " . ($i + 1) . ": phone tidak valid";
            }
            // Check required params
            $rowParams = $row['params'] ?? [];
            foreach ($paramDefs as $def) {
                if ((int) $def['is_required'] !== 1) {
                    continue;
                }
                $key2 = $this->csvParamKey($def);
                $val = trim((string) ($rowParams[$key2] ?? $rowParams[$def['param_name'] ?? ''] ?? ''));
                if ($val === '') {
                    $errors[] = "Baris " . ($i + 1) . ": kolom '{$key2}' wajib diisi";
                }
            }
        }
        if ($errors !== []) {
            $this->error('Validasi gagal: ' . implode('; ', array_slice($errors, 0, 5)), 422, ['errors' => $errors]);
        }

        $phones = [];
        foreach ($rows as $row) {
            $phones[] = $this->normalizePhone((string) ($row['phone'] ?? ''));
        }

        $limitGuard = new WaDeskDailyKeyLimit($this->db($this->db_index));
        $quota = $limitGuard->checkBatch($keyId, $phones);
        if (!$quota['allowed']) {
            $this->error($quota['error'], 422, [
                'daily_limit' => $quota['limit'],
                'used_today' => $quota['used'],
                'new_unique_in_blast' => $quota['new_unique'],
                'remaining_today' => $quota['remaining'],
            ]);
        }

        // Insert blast job
        $blastId = (int) $this->db($this->db_index)->insert('wa_blasts', [
            'tenant_id'     => (int) $admin['tenant_id'],
            'ycloud_key_id' => $keyId,
            'template_id'   => $templateId,
            'created_by'    => (int) $admin['id'],
            'campaign_name' => $campaignName,
            'status'        => 'pending',
            'total'         => count($rows),
            'sent'          => 0,
            'failed'        => 0,
        ]);

        // Insert recipients
        foreach ($rows as $row) {
            $phone = $this->normalizePhone((string) ($row['phone'] ?? ''));
            $this->db($this->db_index)->insert('wa_blast_recipients', [
                'blast_id'    => $blastId,
                'phone'       => $phone,
                'params_json' => json_encode($row['params'] ?? [], JSON_UNESCAPED_UNICODE),
                'status'      => 'pending',
            ]);
        }

        $this->success(['blast_id' => $blastId], 'Blast dibuat, menunggu pengiriman');
    }

    // -------------------------------------------------------------------------
    // GET /WaDesk/Blast/list?campaign_name=&page=
    // -------------------------------------------------------------------------
    public function list()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $campaign = trim((string) $this->query('campaign_name', ''));
        $page = max(1, (int) $this->query('page', 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT b.*, t.template_name, k.label AS key_label, k.phone_number AS wa_number,
                       u.name AS created_by_name
                FROM wa_blasts b
                INNER JOIN wa_templates t ON t.id = b.template_id
                INNER JOIN ycloud_keys k ON k.id = b.ycloud_key_id
                INNER JOIN users u ON u.id = b.created_by
                WHERE b.tenant_id = ?";
        $binds = [(int) $admin['tenant_id']];

        if ($campaign !== '') {
            $sql .= ' AND b.campaign_name LIKE ?';
            $binds[] = '%' . $campaign . '%';
        }
        $sql .= ' ORDER BY b.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->db($this->db_index)->query($sql, $binds)->result_array();

        // Count for pagination
        $countSql = "SELECT COUNT(*) AS cnt FROM wa_blasts b WHERE b.tenant_id = ?";
        $countBinds = [(int) $admin['tenant_id']];
        if ($campaign !== '') {
            $countSql .= ' AND b.campaign_name LIKE ?';
            $countBinds[] = '%' . $campaign . '%';
        }
        $total = (int) $this->db($this->db_index)->query($countSql, $countBinds)->row_array()['cnt'];

        // Distinct campaign names for filter dropdown
        $campaigns = $this->db($this->db_index)->query(
            "SELECT DISTINCT campaign_name FROM wa_blasts WHERE tenant_id = ? ORDER BY campaign_name ASC",
            [(int) $admin['tenant_id']]
        )->result_array();

        $this->success([
            'blasts'    => $rows,
            'total'     => $total,
            'page'      => $page,
            'campaigns' => array_column($campaigns, 'campaign_name'),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /WaDesk/Blast/detail?id=&page=
    // -------------------------------------------------------------------------
    public function detail()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        $id = (int) $this->query('id', 0);
        if ($id <= 0) {
            $this->error('id wajib', 400);
        }

        $blast = $this->db($this->db_index)->query(
            "SELECT b.*, t.template_name, t.body_preview, k.label AS key_label, k.phone_number AS wa_number,
                    u.name AS created_by_name
             FROM wa_blasts b
             INNER JOIN wa_templates t ON t.id = b.template_id
             INNER JOIN ycloud_keys k ON k.id = b.ycloud_key_id
             INNER JOIN users u ON u.id = b.created_by
             WHERE b.id = ? AND b.tenant_id = ? LIMIT 1",
            [$id, (int) $admin['tenant_id']]
        )->row_array();

        if (!$blast) {
            $this->error('Blast tidak ditemukan', 404);
        }

        $page = max(1, (int) $this->query('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $recipients = $this->db($this->db_index)->query(
            "SELECT id, phone, status, error, conversation_id, message_id, sent_at
             FROM wa_blast_recipients
             WHERE blast_id = ?
             ORDER BY id ASC
             LIMIT {$limit} OFFSET {$offset}",
            [$id]
        )->result_array();

        $recipientTotal = (int) $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS cnt FROM wa_blast_recipients WHERE blast_id = ?",
            [$id]
        )->row_array()['cnt'];

        $this->success([
            'blast'           => $blast,
            'recipients'      => $recipients,
            'recipient_total' => $recipientTotal,
            'page'            => $page,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /WaDesk/Blast/cancel
    // Body: { blast_id }
    // -------------------------------------------------------------------------
    public function cancel()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $blastId = (int) ($body['blast_id'] ?? 0);
        if ($blastId <= 0) {
            $this->error('blast_id wajib', 400);
        }

        $blast = $this->db($this->db_index)->query(
            "SELECT * FROM wa_blasts WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$blastId, (int) $admin['tenant_id']]
        )->row_array();

        if (!$blast) {
            $this->error('Blast tidak ditemukan', 404);
        }

        if (in_array($blast['status'], ['done', 'cancelled'], true)) {
            $this->error('Blast sudah selesai atau dibatalkan', 400);
        }

        $this->db($this->db_index)->update('wa_blasts', [
            'status'      => 'cancelled',
            'finished_at' => date('Y-m-d H:i:s'),
        ], ['id' => $blastId]);

        $this->success(null, 'Blast dibatalkan');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loadParamDefs(int $templateId): array
    {
        return $this->db($this->db_index)->query(
            "SELECT component, param_index, param_name, label, example_value, is_required
             FROM wa_template_params WHERE template_id = ?
             ORDER BY FIELD(component,'header','body','button'), param_index ASC",
            [$templateId]
        )->result_array();
    }

    /** Derive CSV column key from a param def (matches frontend logic). */
    private function csvParamKey(array $def): string
    {
        $name = trim((string) ($def['param_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return strtolower((string) ($def['component'] ?? 'body')) . '_' . (int) ($def['param_index'] ?? 0);
    }

    /** Build param metadata array for csvHeaders endpoint. */
    private function buildCsvParamMeta(array $defs): array
    {
        $result = [];
        foreach ($defs as $def) {
            $result[] = [
                'key'      => $this->csvParamKey($def),
                'label'    => $def['label'],
                'example'  => $def['example_value'] ?? '',
                'required' => (int) $def['is_required'] === 1,
                'component'=> $def['component'],
            ];
        }
        return $result;
    }
}
