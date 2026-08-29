<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\Meta;

/** WABA catalogue. Numbers and templates are synced into their existing menus. */
class Wabas extends WaDeskController
{
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

        $this->success(['wabas' => $rows]);
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
        $stats = ['wabas' => 0, 'phones' => 0, 'templates' => 0, 'errors' => []];
        foreach ($fetched['data'] as $waba) {
            $wabaId = trim((string) ($waba['id'] ?? ''));
            if ($wabaId === '') {
                continue;
            }
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
            'SELECT id, team_id FROM wa_channels WHERE tenant_id = ? AND meta_phone_number_id = ? LIMIT 1',
            [$tenantId, $phoneId]
        )->row_array();
        $data = [
            'waba_id' => $wabaId,
            'phone_number' => $number !== '' ? $number : $phoneId,
            'label' => $label,
            'channel_type' => 'waba',
            'provider' => 'meta',
            'status' => $channelStatus,
        ];
        if ($existing) {
            $data['device_id'] = $phoneId; // Compatibility for existing template/team linkage.
            $db->update('wa_channels', $data, ['id' => (int) $existing['id']]);
        } else {
            $data += ['tenant_id' => $tenantId, 'meta_phone_number_id' => $phoneId, 'device_id' => $phoneId, 'team_id' => null];
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
            $this->linkTemplateToWabaChannels($templateId, $tenantId, $wabaId);
        }
        return true;
    }

    private function linkTemplateToWabaChannels(int $templateId, int $tenantId, string $wabaId): void
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT device_id FROM wa_channels WHERE tenant_id = ? AND waba_id = ? AND provider = 'meta' AND device_id IS NOT NULL",
            [$tenantId, $wabaId]
        )->result_array();
        foreach ($rows as $row) {
            $deviceId = trim((string) ($row['device_id'] ?? ''));
            if ($deviceId !== '') {
                $this->db($this->db_index)->insertIgnore('wa_template_devices', ['template_id' => $templateId, 'device_id' => $deviceId]);
            }
        }
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

    private function requireWabaTable(): void
    {
        $row = $this->db($this->db_index)->query("SHOW TABLES LIKE 'wa_wabas'")->row_array();
        if (!$row) {
            $this->error('Migration WABA belum dijalankan. Jalankan 032_meta_waba_sync.sql.', 503);
        }
    }
}
