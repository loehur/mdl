<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\Meta;

/** WABA catalogue. Numbers and templates are synced into their existing menus. */
class Wabas extends WaDeskController
{
    private function metaForAdmin(): array
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $this->requireWabaTable();
        $meta = new Meta();
        if (!$meta->configured()) {
            $this->error('META_WA_ACCESS_TOKEN belum diatur di server API.', 503);
        }
        return [$admin, $meta];
    }

    public function addNumber()
    {
        [$admin, $meta] = $this->metaForAdmin();
        $body = $this->getBody();
        $wabaId = trim((string) ($body['waba_id'] ?? ''));
        $cc = '62';
        $phone = trim((string) ($body['phone_number'] ?? ''));
        $waba = $this->assertTenantWaba((int) $admin['tenant_id'], $wabaId);
        if ($phone === '') $this->error('Nomor wajib diisi', 422);
        $res = $meta->addPhoneNumber($wabaId, $cc, $phone, (string) $waba['name']);
        if (!$res['success']) $this->error('Gagal menambah nomor: ' . $res['error'], 502, $res['data']);
        $phoneId = (string) ($res['data']['id'] ?? $res['data']['phone_number_id'] ?? '');
        $this->success(['phone_number_id' => $phoneId, 'meta' => $res['data']], 'Nomor ditambahkan. Minta OTP untuk melanjutkan.');
    }

    public function requestOtp()
    {
        [, $meta] = $this->metaForAdmin();
        $body = $this->getBody();
        $phoneId = trim((string) ($body['phone_number_id'] ?? ''));
        if ($phoneId === '') $this->error('phone_number_id wajib', 422);
        $res = $meta->requestVerificationCode($phoneId, (string) ($body['method'] ?? 'SMS'));
        if (!$res['success']) $this->error('Gagal meminta OTP: ' . $res['error'], 502, $res['data']);
        $this->success(['meta' => $res['data']], 'OTP dikirim.');
    }

    public function verifyOtp()
    {
        [, $meta] = $this->metaForAdmin();
        $body = $this->getBody();
        $phoneId = trim((string) ($body['phone_number_id'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));
        if ($phoneId === '' || $code === '') $this->error('Phone Number ID dan OTP wajib diisi', 422);
        $res = $meta->verifyCode($phoneId, $code);
        if (!$res['success']) $this->error('OTP tidak valid: ' . $res['error'], 502, $res['data']);
        $this->success(['meta' => $res['data']], 'OTP terverifikasi. Nomor siap diregistrasikan.');
    }

    public function registerNumber()
    {
        [$admin, $meta] = $this->metaForAdmin();
        $body = $this->getBody();
        $phoneId = trim((string) ($body['phone_number_id'] ?? ''));
        if ($phoneId === '') $this->error('phone_number_id wajib', 422);
        $res = $meta->registerPhoneNumber($phoneId);
        if (!$res['success']) $this->error('Gagal register nomor: ' . $res['error'], 502, $res['data']);
        $this->success(['meta' => $res['data']], 'Nomor berhasil diregistrasikan. Sync WABA untuk memunculkannya di daftar.');
    }

    public function deleteNumber()
    {
        [$admin, $meta] = $this->metaForAdmin();
        $body = $this->getBody();
        $channelId = (int) ($body['channel_id'] ?? 0);
        $tenantId = (int) $admin['tenant_id'];
        $channel = $this->db($this->db_index)->query(
            "SELECT id, meta_phone_number_id FROM wa_channels WHERE id = ? AND tenant_id = ? AND provider = 'meta' LIMIT 1",
            [$channelId, $tenantId]
        )->row_array();
        if (!$channel || empty($channel['meta_phone_number_id'])) $this->error('Nomor Meta tidak ditemukan', 404);
        $res = $meta->deletePhoneNumber((string) $channel['meta_phone_number_id']);
        if (!$res['success']) $this->error('Gagal menghapus nomor di Meta: ' . $res['error'], 502, $res['data']);
        $this->db($this->db_index)->delete('wa_channels', ['id' => $channelId]);
        $this->success(['meta' => $res['data']], 'Nomor berhasil dihapus.');
    }

    public function list()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $this->requireWabaTable();
        $tenantId = (int) $admin['tenant_id'];

        $rows = $this->db($this->db_index)->query(
            "SELECT w.*,\n"
            . " (SELECT COUNT(*) FROM wa_channels c WHERE c.tenant_id = w.tenant_id AND c.waba_id = w.meta_waba_id) AS phone_count,\n"
            . " (SELECT COUNT(*) FROM wa_templates t WHERE t.tenant_id = w.tenant_id AND t.meta_waba_id = w.meta_waba_id) AS template_count\n"
            . " FROM wa_wabas w WHERE w.tenant_id = ? ORDER BY w.name ASC, w.id ASC",
            [$tenantId]
        )->result_array();
        foreach ($rows as &$row) {
            $teams = $this->db($this->db_index)->query(
                "SELECT t.id, t.name FROM teams t
                 INNER JOIN wa_waba_teams wt ON wt.team_id = t.id
                 WHERE wt.tenant_id = ? AND wt.waba_id = ? ORDER BY t.name ASC",
                [$tenantId, (int) $row['id']]
            )->result_array();
            $row['teams'] = $teams;
            $row['team_ids'] = array_map('intval', array_column($teams, 'id'));
            $row['team_names'] = implode(' + ', array_column($teams, 'name'));
        }
        unset($row);

        $this->success(['wabas' => $rows]);
    }

    /** Assign teams to one WABA. A team may belong to only one WABA. */
    public function assignTeams()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $this->requireWabaTable();
        $body = $this->getBody();
        $wabaId = (int) ($body['waba_id'] ?? 0);
        $teamIds = array_values(array_unique(array_filter(array_map('intval', (array) ($body['team_ids'] ?? [])))));
        $tenantId = (int) $admin['tenant_id'];
        $db = $this->db($this->db_index);
        $waba = $db->query('SELECT * FROM wa_wabas WHERE id = ? AND tenant_id = ? LIMIT 1', [$wabaId, $tenantId])->row_array();
        if (!$waba) {
            $this->error('WABA tidak ditemukan', 404);
        }
        if ($teamIds !== []) {
            $marks = implode(',', array_fill(0, count($teamIds), '?'));
            $valid = $db->query("SELECT id FROM teams WHERE tenant_id = ? AND id IN ({$marks})", array_merge([$tenantId], $teamIds))->result_array();
            if (count($valid) !== count($teamIds)) {
                $this->error('Ada team yang tidak valid untuk tenant ini', 422);
            }
            $conflict = $db->query(
                "SELECT t.name, w.name AS waba_name FROM wa_waba_teams wt
                 INNER JOIN teams t ON t.id = wt.team_id
                 INNER JOIN wa_wabas w ON w.id = wt.waba_id
                 WHERE wt.tenant_id = ? AND wt.team_id IN ({$marks}) AND wt.waba_id != ? LIMIT 1",
                array_merge([$tenantId], $teamIds, [$wabaId])
            )->row_array();
            if ($conflict) {
                $this->error('Team "' . $conflict['name'] . '" sudah terhubung ke WABA "' . $conflict['waba_name'] . '". Satu team hanya boleh berada pada satu WABA.', 422);
            }
        }

        $db->delete('wa_waba_teams', ['tenant_id' => $tenantId, 'waba_id' => $wabaId]);
        foreach ($teamIds as $teamId) {
            $db->insert('wa_waba_teams', ['tenant_id' => $tenantId, 'waba_id' => $wabaId, 'team_id' => $teamId]);
        }
        $this->syncWabaTeamsToChannels($tenantId, (string) $waba['meta_waba_id'], $teamIds);
        $this->success(['waba_id' => $wabaId, 'team_ids' => $teamIds], 'Team WABA disimpan');
    }

    /** Discover accessible WABAs and sync all their numbers and templates. */
    public function sync()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $this->requireWabaTable();

        $meta = new Meta();
        if (!$meta->configured()) {
            $this->error('META_WA_ACCESS_TOKEN belum diatur di server API.', 503);
        }

        $fetched = $meta->listWabas();
        if (!$fetched['success']) {
            $this->error('Gagal mengambil daftar WABA dari Meta: ' . $fetched['error'], 502);
        }

        $tenantId = (int) $admin['tenant_id'];
        $stats = ['wabas' => 0, 'phones' => 0, 'templates' => 0, 'templates_removed' => 0, 'channels_removed' => 0, 'errors' => []];
        $activeWabaIds = [];
        foreach ($fetched['data'] as $waba) {
            $wabaId = trim((string) ($waba['id'] ?? ''));
            if ($wabaId === '') {
                continue;
            }
            $activeWabaIds[] = $wabaId;
            $name = trim((string) ($waba['name'] ?? $wabaId));
            $this->upsertWaba($tenantId, $wabaId, $name);
            $stats['wabas']++;

            $phones = $meta->listPhoneNumbers($wabaId);
            if ($phones['success']) {
                foreach ($phones['data'] as $phone) {
                    if (is_array($phone) && $this->upsertPhone($tenantId, $wabaId, $phone)) {
                        $stats['phones']++;
                    }
                }
            } else {
                $stats['errors'][] = "WABA {$wabaId} nomor: {$phones['error']}";
            }
            $this->syncWabaTeamsToChannels($tenantId, $wabaId, $this->wabaTeamIds($tenantId, $wabaId));

            $templates = $meta->listTemplates($wabaId);
            if ($templates['success']) {
                foreach ($templates['data'] as $template) {
                    if (is_array($template) && $this->upsertTemplate($tenantId, $wabaId, $template)) {
                        $stats['templates']++;
                    }
                }
            } else {
                $stats['errors'][] = "WABA {$wabaId} template: {$templates['error']}";
            }
        }

        // Meta WABA yang tercantum di environment adalah source of truth.
        // Template lama (termasuk template legacy tanpa WABA) tidak boleh muncul
        // atau terkirim dari WaDesk setelah migrasi ke Meta.
        $stats['templates_removed'] = $this->removeTemplatesOutsideWabas($tenantId, $activeWabaIds);
        $stats['channels_removed'] = $this->removeChannelsOutsideWabas($tenantId, $activeWabaIds);

        $this->success($stats, 'Sinkronisasi WABA selesai');
    }

    private function upsertWaba(int $tenantId, string $wabaId, string $name): void
    {
        $db = $this->db($this->db_index);
        $existing = $db->query('SELECT id FROM wa_wabas WHERE tenant_id = ? AND meta_waba_id = ? LIMIT 1', [$tenantId, $wabaId])->row_array();
        $data = ['name' => $name, 'status' => 'active', 'last_synced_at' => date('Y-m-d H:i:s')];
        if ($existing) {
            $db->update('wa_wabas', $data, ['id' => (int) $existing['id']]);
            return;
        }
        $data += ['tenant_id' => $tenantId, 'meta_waba_id' => $wabaId];
        $db->insert('wa_wabas', $data);
    }

    private function upsertPhone(int $tenantId, string $wabaId, array $phone): bool
    {
        $phoneId = trim((string) ($phone['id'] ?? ''));
        if ($phoneId === '') {
            return false;
        }
        $number = preg_replace('/\D+/', '', (string) ($phone['display_phone_number'] ?? '')) ?? '';
        $label = trim((string) ($phone['verified_name'] ?? $phone['display_phone_number'] ?? $phoneId));
        $status = strtolower(trim((string) ($phone['code_verification_status'] ?? $phone['status'] ?? 'active')));
        $channelStatus = in_array($status, ['connected', 'verified', 'active'], true) ? 'active' : 'inactive';
        $db = $this->db($this->db_index);
        $existing = $db->query(
            'SELECT id FROM wa_channels WHERE tenant_id = ? AND meta_phone_number_id = ? LIMIT 1',
            [$tenantId, $phoneId]
        )->row_array();
        $data = [
            'waba_id' => $wabaId,
            'phone_number' => $number !== '' ? $number : $phoneId,
            'label' => $label,
            'meta_verification_status' => strtoupper(trim((string) ($phone['code_verification_status'] ?? $phone['status'] ?? ''))),
            'meta_quality_rating' => strtoupper(trim((string) ($phone['quality_rating'] ?? ''))),
            'channel_type' => 'waba',
            'provider' => 'meta',
            'status' => $channelStatus,
        ];
        if ($existing) {
            $data['device_id'] = $phoneId; // Compatibility for existing template/team linkage.
            $db->update('wa_channels', $data, ['id' => (int) $existing['id']]);
        } else {
            $data += ['tenant_id' => $tenantId, 'meta_phone_number_id' => $phoneId, 'device_id' => $phoneId];
            $db->insert('wa_channels', $data);
        }
        return true;
    }

    private function upsertTemplate(int $tenantId, string $wabaId, array $template): bool
    {
        $name = trim((string) ($template['name'] ?? ''));
        $language = trim((string) ($template['language'] ?? 'id')) ?: 'id';
        if ($name === '') {
            return false;
        }
        $components = is_array($template['components'] ?? null) ? $template['components'] : [];
        $preview = $this->templatePreview($components);
        $db = $this->db($this->db_index);
        $existing = $db->query(
            'SELECT id FROM wa_templates WHERE tenant_id = ? AND meta_waba_id = ? AND template_name = ? AND language = ? LIMIT 1',
            [$tenantId, $wabaId, $name, $language]
        )->row_array();
        $data = [
            'body_preview' => $preview,
            'meta_template_id' => (string) ($template['id'] ?? ''),
            'meta_status' => strtoupper((string) ($template['status'] ?? '')),
            'meta_category' => strtoupper((string) ($template['category'] ?? '')),
        ];
        if ($existing) {
            $templateId = (int) $existing['id'];
            $db->update('wa_templates', $data, ['id' => $templateId]);
        } else {
            $data += ['tenant_id' => $tenantId, 'meta_waba_id' => $wabaId, 'template_name' => $name, 'language' => $language];
            $templateId = (int) $db->insert('wa_templates', $data);
        }
        if ($templateId > 0) {
            $this->syncTemplateParams($templateId, $components);
        }
        return true;
    }

    private function syncTemplateParams(int $templateId, array $components): void
    {
        $db = $this->db($this->db_index);
        $db->delete('wa_template_params', ['template_id' => $templateId]);
        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $type = strtolower((string) ($component['type'] ?? ''));
            if (!in_array($type, ['header', 'body'], true)) {
                continue;
            }
            $text = (string) ($component['text'] ?? '');
            if (!preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/', $text, $matches)) {
                continue;
            }
            foreach ($matches[1] as $index => $name) {
                $name = trim((string) $name);
                $db->insert('wa_template_params', [
                    'template_id' => $templateId,
                    'component' => $type,
                    'param_index' => $index + 1,
                    'param_name' => $name,
                    'label' => $name !== '' ? $name : (string) ($index + 1),
                    'is_required' => 1,
                ]);
            }
        }
    }

    private function templatePreview(array $components): ?string
    {
        $parts = [];
        foreach ($components as $component) {
            if (is_array($component) && in_array(strtolower((string) ($component['type'] ?? '')), ['header', 'body'], true)) {
                $text = trim((string) ($component['text'] ?? ''));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }
        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /** Remove templates not attached to a WABA currently synced from META_WA_WABA_IDS. */
    private function removeTemplatesOutsideWabas(int $tenantId, array $wabaIds): int
    {
        $db = $this->db($this->db_index);
        if ($wabaIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($wabaIds), '?'));
        $rows = $db->query(
            "SELECT id FROM wa_templates
             WHERE tenant_id = ?
               AND (meta_waba_id IS NULL OR meta_waba_id = '' OR meta_waba_id NOT IN ({$placeholders}))",
            array_merge([$tenantId], $wabaIds)
        )->result_array();
        foreach ($rows as $row) {
            $db->delete('wa_templates', ['id' => (int) $row['id']]);
        }
        return count($rows);
    }

    /** Remove legacy channels and channels from WABAs not configured in META_WA_WABA_IDS. */
    private function removeChannelsOutsideWabas(int $tenantId, array $wabaIds): int
    {
        $db = $this->db($this->db_index);
        if ($wabaIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($wabaIds), '?'));
        $rows = $db->query(
            "SELECT id FROM wa_channels
             WHERE tenant_id = ?
               AND (waba_id IS NULL OR waba_id = '' OR waba_id NOT IN ({$placeholders}))",
            array_merge([$tenantId], $wabaIds)
        )->result_array();
        foreach ($rows as $row) {
            // Foreign-key cascades remove channel-team mappings and conversations safely.
            $db->delete('wa_channels', ['id' => (int) $row['id']]);
        }
        return count($rows);
    }

    /** @return list<int> */
    private function wabaTeamIds(int $tenantId, string $metaWabaId): array
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT wt.team_id FROM wa_waba_teams wt
             INNER JOIN wa_wabas w ON w.id = wt.waba_id
             WHERE wt.tenant_id = ? AND w.meta_waba_id = ?",
            [$tenantId, $metaWabaId]
        )->result_array();
        return array_map('intval', array_column($rows, 'team_id'));
    }

    /** Mirror WABA team access to every Meta phone number in that WABA. */
    private function syncWabaTeamsToChannels(int $tenantId, string $metaWabaId, array $teamIds): void
    {
        $db = $this->db($this->db_index);
        $channels = $db->query(
            "SELECT id FROM wa_channels WHERE tenant_id = ? AND waba_id = ? AND provider = 'meta'",
            [$tenantId, $metaWabaId]
        )->result_array();
        foreach ($channels as $channel) {
            $channelId = (int) $channel['id'];
            $db->delete('wa_channel_teams', ['channel_id' => $channelId]);
            foreach ($teamIds as $teamId) {
                $db->insert('wa_channel_teams', ['channel_id' => $channelId, 'team_id' => (int) $teamId]);
            }
        }
    }

    private function requireWabaTable(): void
    {
        $waba = $this->db($this->db_index)->query("SHOW TABLES LIKE 'wa_wabas'")->row_array();
        $teams = $this->db($this->db_index)->query("SHOW TABLES LIKE 'wa_waba_teams'")->row_array();
        if (!$waba || !$teams) {
            $this->error('Migration WABA belum lengkap. Jalankan 032_meta_waba_sync.sql lalu 033_waba_team_access.sql.', 503);
        }
    }

    private function assertTenantWaba(int $tenantId, string $metaWabaId): array
    {
        $row = $this->db($this->db_index)->query(
            'SELECT id, name FROM wa_wabas WHERE tenant_id = ? AND meta_waba_id = ? LIMIT 1',
            [$tenantId, $metaWabaId]
        )->row_array();
        if (!$row) $this->error('WABA tidak ditemukan. Lakukan Sync WABA terlebih dahulu.', 404);
        return $row;
    }
}
