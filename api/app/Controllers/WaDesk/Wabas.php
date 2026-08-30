<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\Meta;

/** WABA catalogue. Numbers and templates are synced into their existing menus. */
class Wabas extends WaDeskController
{
    private function metaForAdmin(): array
    {
        $this->verifyAuth();
        $admin = $this->requireChatUser();
        if (!in_array((string) ($admin['role'] ?? ''), ['admin', 'team_leader'], true)) $this->error('Hanya Admin atau Team Leader yang dapat mengelola nomor.', 403);
        if (($admin['role'] ?? '') === 'team_leader' && !$this->hasOperationalTeam($admin)) $this->error('Team Leader harus berada pada team aktif.', 403);
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
        $wabaId = $this->managedWabaId($admin, (string) ($body['waba_id'] ?? ''));
        $cc = '62';
        $phone = trim((string) ($body['phone_number'] ?? ''));
        $waba = $this->assertTenantWaba((int) $admin['tenant_id'], $wabaId);
        if ($phone === '') $this->error('Nomor wajib diisi', 422);
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($normalizedPhone, '628')) $normalizedPhone = substr($normalizedPhone, 2);
        elseif (str_starts_with($normalizedPhone, '08')) $normalizedPhone = substr($normalizedPhone, 1);
        if (!preg_match('/^8\d{7,14}$/', $normalizedPhone)) $this->error('Nomor harus diawali 8 dan berisi 8–15 digit.', 422);
        $res = $meta->addPhoneNumber($wabaId, $cc, $normalizedPhone, (string) $waba['name']);
        if (!$res['success']) $this->error('Gagal menambah nomor: ' . $res['error'], 502, $res['data']);
        $phoneId = (string) ($res['data']['id'] ?? $res['data']['phone_number_id'] ?? '');
        if ($phoneId !== '' && ($admin['role'] ?? '') === 'team_leader') {
            $this->recordTeamPendingPhone($admin, $wabaId, $phoneId, $normalizedPhone, (string) $waba['name']);
        }
        $this->success(['phone_number_id' => $phoneId, 'meta' => $res['data']], 'Nomor ditambahkan. Minta OTP untuk melanjutkan.');
    }

    public function requestOtp()
    {
        [$admin, $meta] = $this->metaForAdmin();
        $this->requireOtpAttemptTable();
        $body = $this->getBody();
        $phoneId = trim((string) ($body['phone_number_id'] ?? ''));
        if ($phoneId === '') $this->error('phone_number_id wajib', 422);
        $this->assertManagedPhone($this->requireChatUser(), $phoneId);
        $method = (string) ($body['method'] ?? 'SMS');
        $attempt = $this->otpAttempt((int) $admin['tenant_id'], $phoneId);
        $verifyRetryAfter = $this->secondsUntil($attempt['verify_locked_until'] ?? null);
        if ($verifyRetryAfter > 0) {
            \Log::write("OTP request BLOCKED BY VERIFY LOCK: phone={$phoneId} retry_after={$verifyRetryAfter} user={$admin['id']} tenant={$admin['tenant_id']}", 'wadesk', 'otp');
            $this->error('Terlalu banyak permintaan atau percobaan OTP. Tunggu hingga waktu lock selesai sebelum meminta OTP lagi.', 429, ['retry_after' => $verifyRetryAfter, 'code' => 'otp_verify_locked']);
        }
        $requestRetryAfter = $this->secondsUntil($attempt['last_request_at'] ?? null, 60);
        if ($requestRetryAfter > 0) {
            \Log::write("OTP request RATE LIMITED: phone={$phoneId} retry_after={$requestRetryAfter} user={$admin['id']} tenant={$admin['tenant_id']}", 'wadesk', 'otp');
            $this->error("OTP baru saja diminta. Tunggu {$requestRetryAfter} detik sebelum meminta ulang.", 429, ['retry_after' => $requestRetryAfter, 'code' => 'otp_request_cooldown']);
        }
        \Log::write("OTP request: phone={$phoneId} method={$method} user={$admin['id']} tenant={$admin['tenant_id']}", 'wadesk', 'otp');
        $res = $meta->requestVerificationCode($phoneId, $method);
        if (!$res['success']) {
            \Log::write("OTP request FAILED: phone={$phoneId} method={$method} http={$res['http_code']} err={$res['error']} resp=" . json_encode($res['data'], JSON_UNESCAPED_SLASHES), 'wadesk', 'otp');
            if ($this->isMetaOtpRateLimit($res)) {
                $this->lockOtpVerification((int) $admin['tenant_id'], $phoneId, 600);
                $this->error('Terlalu banyak permintaan atau percobaan OTP di Meta. Tunggu beberapa menit sebelum mencoba lagi.', 429, ['retry_after' => 600, 'code' => 'meta_otp_rate_limit']);
            }
            $this->error('Gagal meminta OTP: ' . $res['error'], 502, $res['data']);
        }
        $this->touchOtpRequest((int) $admin['tenant_id'], $phoneId);
        \Log::write("OTP request OK: phone={$phoneId} method={$method} http={$res['http_code']}", 'wadesk', 'otp');
        $this->success(['meta' => $res['data'], 'retry_after' => 60], 'OTP dikirim.');
    }

    public function verifyOtp()
    {
        [$admin, $meta] = $this->metaForAdmin();
        $this->requireOtpAttemptTable();
        $body = $this->getBody();
        $phoneId = trim((string) ($body['phone_number_id'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));
        if ($phoneId === '' || $code === '') $this->error('Phone Number ID dan OTP wajib diisi', 422);
        $this->assertManagedPhone($this->requireChatUser(), $phoneId);
        $attempt = $this->otpAttempt((int) $admin['tenant_id'], $phoneId);
        $verifyRetryAfter = $this->secondsUntil($attempt['verify_locked_until'] ?? null);
        if ($verifyRetryAfter > 0) {
            \Log::write("OTP verify RATE LIMITED: phone={$phoneId} retry_after={$verifyRetryAfter} user={$admin['id']} tenant={$admin['tenant_id']}", 'wadesk', 'otp');
            $this->error('Terlalu banyak percobaan verify. Tunggu beberapa menit sebelum mencoba lagi.', 429, ['retry_after' => $verifyRetryAfter, 'code' => 'otp_verify_locked']);
        }
        \Log::write("OTP verify: phone={$phoneId} user={$admin['id']} tenant={$admin['tenant_id']}", 'wadesk', 'otp');
        $res = $meta->verifyCode($phoneId, $code);
        if (!$res['success']) {
            \Log::write("OTP verify FAILED: phone={$phoneId} http={$res['http_code']} err={$res['error']} resp=" . json_encode($res['data'], JSON_UNESCAPED_SLASHES), 'wadesk', 'otp');
            if ($this->isMetaOtpRateLimit($res)) {
                $this->lockOtpVerification((int) $admin['tenant_id'], $phoneId, 600);
                \Log::write("OTP verify META RATE LIMITED: phone={$phoneId} locked_for=600", 'wadesk', 'otp');
                $this->error('Terlalu banyak percobaan verify di Meta. Tunggu beberapa menit sebelum mencoba lagi.', 429, ['retry_after' => 600, 'code' => 'meta_otp_rate_limit']);
            }
            $fails = $this->recordOtpVerifyFailure((int) $admin['tenant_id'], $phoneId);
            if ($fails >= 3) {
                \Log::write("OTP verify LOCKED: phone={$phoneId} fails={$fails} locked_for=600", 'wadesk', 'otp');
                $this->error('Terlalu banyak percobaan verify. Tunggu 10 menit sebelum mencoba lagi.', 429, ['retry_after' => 600, 'code' => 'otp_verify_locked']);
            }
            $remaining = 3 - $fails;
            $this->error("OTP tidak valid. Sisa {$remaining} percobaan.", 422, ['verify_attempts_remaining' => $remaining, 'code' => 'otp_invalid']);
        }
        $this->clearOtpVerifyFailures((int) $admin['tenant_id'], $phoneId);
        \Log::write("OTP verify OK: phone={$phoneId} http={$res['http_code']}", 'wadesk', 'otp');
        $this->success(['meta' => $res['data']], 'OTP terverifikasi. Nomor siap diregistrasikan.');
    }

    public function registerNumber()
    {
        [$admin, $meta] = $this->metaForAdmin();
        $body = $this->getBody();
        $phoneId = trim((string) ($body['phone_number_id'] ?? ''));
        if ($phoneId === '') $this->error('phone_number_id wajib', 422);
        $this->assertManagedPhone($admin, $phoneId);
        $res = $meta->registerPhoneNumber($phoneId);
        if (!$res['success']) $this->error('Gagal register nomor: ' . $res['error'], 502, $res['data']);
        $this->success(['meta' => $res['data']], 'Nomor berhasil diregistrasikan. Sync WABA untuk memunculkannya di daftar.');
    }

    public function teamNumbers()
    {
        $this->verifyAuth(); $user = $this->requireChatUser();
        if (!in_array((string) ($user['role'] ?? ''), ['admin', 'team_leader'], true)) $this->error('Hanya Admin atau Team Leader yang dapat mengakses menu nomor.', 403);
        if (($user['role'] ?? '') === 'team_leader' && !$this->hasOperationalTeam($user)) $this->error('Team Leader harus berada pada team aktif.', 403);
        $wabaId = $this->managedWabaId($user);
        $waba = $this->assertTenantWaba((int) $user['tenant_id'], $wabaId);
        $numbers = $this->db($this->db_index)->query(
            "SELECT * FROM wa_channels WHERE tenant_id = ? AND waba_id = ? AND provider = 'meta' ORDER BY label ASC, id ASC",
            [(int) $user['tenant_id'], $wabaId]
        )->result_array();
        $this->success(['waba' => ['id' => $wabaId, 'name' => $waba['name']], 'numbers' => $numbers]);
    }

    public function syncNumbersForTeam()
    {
        $this->verifyAuth(); $user = $this->requireChatUser();
        if (!in_array((string) ($user['role'] ?? ''), ['admin', 'team_leader'], true)) $this->error('Hanya Admin atau Team Leader yang dapat mengelola nomor.', 403);
        if (($user['role'] ?? '') === 'team_leader' && !$this->hasOperationalTeam($user)) $this->error('Team Leader harus berada pada team aktif.', 403);
        $this->requireWabaTable();
        $wabaId = $this->managedWabaId($user);
        $meta = new Meta(); if (!$meta->configured()) $this->error('META_WA_ACCESS_TOKEN belum diatur.', 503);
        $stats = $this->syncNumbersForWaba((int) $user['tenant_id'], $wabaId, $meta);
        if ($stats['errors'] !== []) $this->error('Gagal sync nomor: ' . implode('; ', $stats['errors']), 502);
        $this->success($stats, 'Nomor WABA berhasil disinkronkan.');
    }

    /** Sync templates for the caller's active team WABA without changing channels. */
    public function syncTemplatesForTeam()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();
        if (!$this->isPost()) $this->error('Method not allowed', 405);
        if (!in_array((string) ($user['role'] ?? ''), ['admin', 'team_leader'], true) || !$this->hasOperationalTeam($user)) $this->error('Admin/Team Leader harus masuk team untuk sync template.', 403);
        $this->requireWabaTable();
        $tenantId = (int) $user['tenant_id']; $teamId = (int) $user['team_id'];
        $waba = $this->db($this->db_index)->query('SELECT w.meta_waba_id FROM wa_wabas w INNER JOIN wa_waba_teams wt ON wt.waba_id = w.id WHERE wt.tenant_id = ? AND wt.team_id = ? LIMIT 1', [$tenantId, $teamId])->row_array();
        if (!$waba) $this->error('Team belum di-assign ke WABA.', 422);
        $meta = new Meta(); if (!$meta->configured()) $this->error('META_WA_ACCESS_TOKEN belum diatur.', 503);
        $stats = $this->syncTemplatesForWaba($tenantId, (string) $waba['meta_waba_id'], $meta);
        if ($stats['errors'] !== []) $this->error('Gagal sync template: ' . implode('; ', $stats['errors']), 502);
        $this->success($stats, 'Template berhasil disinkronkan.');
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

    /** Sync only configured WABAs and remove local records no longer in Env. */
    public function sync()
    {
        try {
            $this->syncInternal();
        } catch (\Throwable $e) {
            \Log::write('WABA sync failed: ' . $e->getMessage(), 'wadesk', 'waba_sync');
            $this->error('Sync WABA gagal: ' . $e->getMessage(), 500);
        }
    }

    private function syncInternal(): void
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
        $stats = ['wabas' => 0, 'templates_removed' => 0, 'channels_removed' => 0, 'wabas_removed' => 0, 'errors' => []];
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

        }

        // Meta WABA yang tercantum di environment adalah source of truth.
        // Template lama (termasuk template legacy tanpa WABA) tidak boleh muncul
        // atau terkirim dari WaDesk setelah migrasi ke Meta.
        $stats['templates_removed'] = $this->removeTemplatesOutsideWabas($tenantId, $activeWabaIds);
        $stats['channels_removed'] = $this->removeChannelsOutsideWabas($tenantId, $activeWabaIds);
        // Remove the WABA record last. Its WABA-team assignments are cascaded,
        // while its templates and numbers have already been removed above.
        $stats['wabas_removed'] = $this->removeWabasOutsideConfiguredList($tenantId, $activeWabaIds);

        $this->success($stats, 'Sinkronisasi WABA selesai');
    }

    /** Sync numbers and coexistence subscription for one selected WABA. */
    public function syncNumbers()
    {
        $this->verifyAuth(); $admin = $this->requireAdmin();
        if (!$this->isPost()) $this->error('Method not allowed', 405);
        $this->requireWabaTable();
        $wabaId = trim((string) (($this->getBody())['waba_id'] ?? ''));
        $this->assertTenantWaba((int) $admin['tenant_id'], $wabaId);
        $meta = new Meta(); if (!$meta->configured()) $this->error('META_WA_ACCESS_TOKEN belum diatur.', 503);
        $stats = $this->syncNumbersForWaba((int) $admin['tenant_id'], $wabaId, $meta);
        $this->success($stats, 'Nomor WABA berhasil disinkronkan.');
    }

    /** Sync templates and params for one selected WABA. */
    public function syncTemplates()
    {
        $this->verifyAuth(); $admin = $this->requireAdmin();
        if (!$this->isPost()) $this->error('Method not allowed', 405);
        $this->requireWabaTable();
        $wabaId = trim((string) (($this->getBody())['waba_id'] ?? ''));
        $this->assertTenantWaba((int) $admin['tenant_id'], $wabaId);
        $meta = new Meta(); if (!$meta->configured()) $this->error('META_WA_ACCESS_TOKEN belum diatur.', 503);
        $stats = $this->syncTemplatesForWaba((int) $admin['tenant_id'], $wabaId, $meta);
        $this->success($stats, 'Template WABA berhasil disinkronkan.');
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

    /** @return array{phones:int,phones_removed:int,coex_phones:int,coex_subscriptions:int,coex_subscriptions_skipped:int,errors:array} */
    private function syncNumbersForWaba(int $tenantId, string $wabaId, Meta $meta): array
    {
        $stats = ['phones' => 0, 'phones_removed' => 0, 'coex_phones' => 0, 'coex_subscriptions' => 0, 'coex_subscriptions_skipped' => 0, 'errors' => []];
        \Log::write("Number sync START: tenant={$tenantId} waba={$wabaId}", 'wadesk', 'number-sync');
        $phones = $meta->listPhoneNumbers($wabaId);
        if (!$phones['success']) {
            $stats['errors'][] = $phones['error'];
            \Log::write("Number sync FAILED: tenant={$tenantId} waba={$wabaId} http={$phones['http_code']} err={$phones['error']}", 'wadesk', 'number-sync');
            return $stats;
        }
        \Log::write("Number sync META OK: tenant={$tenantId} waba={$wabaId} received=" . count($phones['data']), 'wadesk', 'number-sync');
        $hasCoex = false;
        $metaPhoneIds = [];
        foreach ($phones['data'] as $phone) {
            if (!is_array($phone)) continue;
            $phoneId = trim((string) ($phone['id'] ?? ''));
            if ($phoneId === '') continue;
            $metaPhoneIds[] = $phoneId;
            if ($this->upsertPhone($tenantId, $wabaId, $phone)) $stats['phones']++;
            $phoneLog = [
                'id' => (string) ($phone['id'] ?? ''),
                'number' => (string) ($phone['display_phone_number'] ?? ''),
                'verified_name' => (string) ($phone['verified_name'] ?? ''),
                'connection_status' => (string) ($phone['status'] ?? ''),
                'otp_status' => (string) ($phone['code_verification_status'] ?? ''),
                'display_name_status' => (string) ($phone['name_status'] ?? $phone['new_name_status'] ?? ''),
                'quality' => (string) ($phone['quality_rating'] ?? ''),
                'is_coexistence' => !empty($phone['is_on_biz_app']),
            ];
            \Log::write('Number sync PHONE: tenant=' . $tenantId . ' waba=' . $wabaId . ' data=' . json_encode($phoneLog, JSON_UNESCAPED_SLASHES), 'wadesk', 'number-sync');
            if (!empty($phone['is_on_biz_app'])) { $hasCoex = true; $stats['coex_phones']++; }
        }
        if ($phones['data'] !== [] && $metaPhoneIds === []) {
            $stats['errors'][] = 'Meta mengembalikan data nomor tanpa Phone Number ID; cleanup lokal dilewati untuk keamanan.';
            \Log::write("Number sync CLEANUP SKIPPED: tenant={$tenantId} waba={$wabaId} reason=no_valid_phone_ids", 'wadesk', 'number-sync');
        } else {
            $removedIds = $this->removeMissingPhonesForWaba($tenantId, $wabaId, $metaPhoneIds);
            $stats['phones_removed'] = count($removedIds);
            if ($removedIds !== []) {
                \Log::write('Number sync CLEANUP: tenant=' . $tenantId . ' waba=' . $wabaId . ' removed_phone_ids=' . json_encode($removedIds, JSON_UNESCAPED_SLASHES), 'wadesk', 'number-sync');
            }
        }
        $this->syncWabaTeamsToChannels($tenantId, $wabaId, $this->wabaTeamIds($tenantId, $wabaId));
        if (!$hasCoex) {
            \Log::write('Number sync DONE: tenant=' . $tenantId . ' waba=' . $wabaId . ' stats=' . json_encode($stats, JSON_UNESCAPED_SLASHES), 'wadesk', 'number-sync');
            return $stats;
        }

        $row = $this->db($this->db_index)->query(
            'SELECT id, coex_subscription_status, coex_subscription_checked_at FROM wa_wabas WHERE tenant_id = ? AND meta_waba_id = ? LIMIT 1',
            [$tenantId, $wabaId]
        )->row_array();
        $status = (string) ($row['coex_subscription_status'] ?? '');
        $checkedAt = strtotime((string) ($row['coex_subscription_checked_at'] ?? ''));
        $recentFailure = $status === 'failed' && $checkedAt !== false && $checkedAt > time() - 3600;
        if ($status === 'subscribed' || $recentFailure) {
            $stats['coex_subscriptions_skipped']++;
            \Log::write("Number sync COEX SKIPPED: tenant={$tenantId} waba={$wabaId} cached_status={$status}", 'wadesk', 'number-sync');
            \Log::write('Number sync DONE: tenant=' . $tenantId . ' waba=' . $wabaId . ' stats=' . json_encode($stats, JSON_UNESCAPED_SLASHES), 'wadesk', 'number-sync');
            return $stats;
        }
        $subscription = $meta->subscribeCurrentAppToWaba($wabaId);
        $newStatus = $subscription['success'] ? 'subscribed' : 'failed';
        if ($row) $this->db($this->db_index)->update('wa_wabas', ['coex_subscription_status' => $newStatus, 'coex_subscription_checked_at' => date('Y-m-d H:i:s')], ['id' => (int) $row['id']]);
        if ($subscription['success']) $stats['coex_subscriptions']++; else $stats['errors'][] = 'Subscribe Coex: ' . $subscription['error'];
        \Log::write('Number sync COEX ' . strtoupper($newStatus) . ': tenant=' . $tenantId . ' waba=' . $wabaId . ($subscription['success'] ? '' : ' err=' . $subscription['error']), 'wadesk', 'number-sync');
        \Log::write('Number sync DONE: tenant=' . $tenantId . ' waba=' . $wabaId . ' stats=' . json_encode($stats, JSON_UNESCAPED_SLASHES), 'wadesk', 'number-sync');
        return $stats;
    }

    /** Remove local Meta channels absent from the authoritative phone_numbers response. */
    private function removeMissingPhonesForWaba(int $tenantId, string $wabaId, array $metaPhoneIds): array
    {
        $db = $this->db($this->db_index);
        $rows = $db->query(
            "SELECT id, meta_phone_number_id FROM wa_channels WHERE tenant_id = ? AND waba_id = ? AND provider = 'meta'",
            [$tenantId, $wabaId]
        )->result_array();
        $knownIds = array_fill_keys(array_map('strval', $metaPhoneIds), true);
        $removedIds = [];
        foreach ($rows as $row) {
            $phoneId = (string) ($row['meta_phone_number_id'] ?? '');
            if ($phoneId !== '' && isset($knownIds[$phoneId])) continue;
            $db->delete('wa_channels', ['id' => (int) $row['id']]);
            $removedIds[] = $phoneId !== '' ? $phoneId : ('local:' . (int) $row['id']);
        }
        return $removedIds;
    }

    /** @return array{templates:int,removed:int,errors:array} */
    private function syncTemplatesForWaba(int $tenantId, string $wabaId, Meta $meta): array
    {
        $stats = ['templates' => 0, 'removed' => 0, 'errors' => []];
        $templates = $meta->listTemplates($wabaId);
        if (!$templates['success']) { $stats['errors'][] = $templates['error']; return $stats; }
        $metaTemplateIds = [];
        $metaTemplateNames = [];
        foreach ($templates['data'] as $template) {
            if (!is_array($template)) continue;
            $metaId = trim((string) ($template['id'] ?? ''));
            if ($metaId !== '') $metaTemplateIds[$metaId] = true;
            $name = trim((string) ($template['name'] ?? ''));
            $language = trim((string) ($template['language'] ?? 'id')) ?: 'id';
            if ($name !== '') $metaTemplateNames[$name . "\0" . $language] = true;
            if ($this->upsertTemplate($tenantId, $wabaId, $template)) $stats['templates']++;
        }
        // This runs only after listTemplates() has completed every Meta page
        // successfully. Match by Meta ID first, then name+language for a newly
        // created row whose API create response did not contain an ID.
        $stats['removed'] = $this->removeTemplatesMissingFromMeta($tenantId, $wabaId, $metaTemplateIds, $metaTemplateNames);
        \Log::write('Template sync DONE: tenant=' . $tenantId . ' waba=' . $wabaId . ' stats=' . json_encode($stats, JSON_UNESCAPED_SLASHES), 'wadesk', 'template_sync');
        return $stats;
    }

    /**
     * Remove only template rows of the selected WABA that Meta did not return.
     * @param array<string,true> $metaTemplateIds
     * @param array<string,true> $metaTemplateNames
     */
    private function removeTemplatesMissingFromMeta(int $tenantId, string $wabaId, array $metaTemplateIds, array $metaTemplateNames): int
    {
        $db = $this->db($this->db_index);
        $rows = $db->query(
            'SELECT id, meta_template_id, template_name, language FROM wa_templates WHERE tenant_id = ? AND meta_waba_id = ?',
            [$tenantId, $wabaId]
        )->result_array();
        $removed = 0;
        foreach ($rows as $row) {
            $metaId = trim((string) ($row['meta_template_id'] ?? ''));
            $name = trim((string) ($row['template_name'] ?? ''));
            $language = trim((string) ($row['language'] ?? 'id')) ?: 'id';
            $existsInMeta = ($metaId !== '' && isset($metaTemplateIds[$metaId]))
                || ($name !== '' && isset($metaTemplateNames[$name . "\0" . $language]));
            if ($existsInMeta) continue;
            $db->delete('wa_templates', ['id' => (int) $row['id']]);
            $removed++;
        }
        return $removed;
    }

    private function upsertPhone(int $tenantId, string $wabaId, array $phone): bool
    {
        $phoneId = trim((string) ($phone['id'] ?? ''));
        if ($phoneId === '') {
            return false;
        }
        $number = preg_replace('/\D+/', '', (string) ($phone['display_phone_number'] ?? '')) ?? '';
        $label = trim((string) ($phone['verified_name'] ?? $phone['display_phone_number'] ?? $phoneId));
        $codeStatus = strtoupper(trim((string) ($phone['code_verification_status'] ?? '')));
        $nameStatus = strtoupper(trim((string) ($phone['name_status'] ?? $phone['new_name_status'] ?? '')));
        $providerStatus = strtoupper(trim((string) ($phone['status'] ?? '')));
        // OTP/display-name verification is not phone registration. A number is
        // usable only after Meta reports it CONNECTED (after /register + PIN).
        $channelStatus = $providerStatus === 'CONNECTED' ? 'active' : 'inactive';
        $db = $this->db($this->db_index);
        $existing = $db->query(
            'SELECT id FROM wa_channels WHERE tenant_id = ? AND meta_phone_number_id = ? LIMIT 1',
            [$tenantId, $phoneId]
        )->row_array();
        $data = [
            'waba_id' => $wabaId,
            'phone_number' => $number !== '' ? $number : $phoneId,
            'label' => $label,
            // Kolom ini menyimpan status asli dari Meta; `status` menyimpan active/inactive.
            'meta_provider_status' => $providerStatus !== '' ? $providerStatus : null,
            // Kolom ini menyimpan status OTP saja; status koneksi disimpan pada `status`.
            'meta_verification_status' => $codeStatus,
            'meta_display_name_status' => $nameStatus,
            'meta_quality_rating' => strtoupper(trim((string) ($phone['quality_rating'] ?? ''))),
            'meta_platform_type' => strtoupper(trim((string) ($phone['platform_type'] ?? ''))),
            'is_coexistence' => !empty($phone['is_on_biz_app']) ? 1 : 0,
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
            'SELECT id, body_preview FROM wa_templates WHERE tenant_id = ? AND meta_waba_id = ? AND template_name = ? AND language = ? LIMIT 1',
            [$tenantId, $wabaId, $name, $language]
        )->row_array();
        // Templates created from WaDesk keep readable labels (for example
        // {{customer_name}}) locally, while Meta returns its required {{1}} form.
        $keepFriendlyPreview = $existing
            && preg_match('/\{\{\s*\d+\s*\}\}/', (string) $preview)
            && preg_match('/\{\{\s*[a-zA-Z][a-zA-Z0-9_]*\s*\}\}/', (string) ($existing['body_preview'] ?? ''));
        $data = [
            'body_preview' => $keepFriendlyPreview ? $existing['body_preview'] : $preview,
            'meta_template_id' => (string) ($template['id'] ?? ''),
            'meta_status' => strtoupper((string) ($template['status'] ?? '')),
            'meta_quality_rating' => strtoupper((string) ($template['quality_score'] ?? $template['quality_rating'] ?? '')),
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
        $oldRows = $db->query(
            'SELECT component, param_index, param_name, label FROM wa_template_params WHERE template_id = ? ORDER BY component, param_index',
            [$templateId]
        )->result_array();
        $oldByPosition = [];
        foreach ($oldRows as $old) {
            $oldByPosition[strtolower((string) $old['component']) . ':' . (int) $old['param_index']] = $old;
        }
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
                $position = $index + 1;
                $old = $oldByPosition[$type . ':' . $position] ?? null;
                // Preserve WaDesk labels when Meta returns the numeric schema.
                if (preg_match('/^\d+$/', $name) && is_array($old) && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', (string) ($old['param_name'] ?? ''))) {
                    $name = (string) $old['param_name'];
                }
                $db->insert('wa_template_params', [
                    'template_id' => $templateId,
                    'component' => $type,
                    'param_index' => $position,
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

    /** Remove local WABA records absent from META_WA_WABA_IDS after dependent data is removed. */
    private function removeWabasOutsideConfiguredList(int $tenantId, array $wabaIds): int
    {
        $db = $this->db($this->db_index);
        if ($wabaIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($wabaIds), '?'));
        $rows = $db->query(
            "SELECT id FROM wa_wabas WHERE tenant_id = ? AND meta_waba_id NOT IN ({$placeholders})",
            array_merge([$tenantId], $wabaIds)
        )->result_array();
        foreach ($rows as $row) {
            // wa_waba_teams is removed by its foreign-key cascade.
            $db->delete('wa_wabas', ['id' => (int) $row['id']]);
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

    private function requireOtpAttemptTable(): void
    {
        $table = $this->db($this->db_index)->query("SHOW TABLES LIKE 'wa_otp_attempts'")->row_array();
        if (!$table) $this->error('Migration OTP belum dijalankan. Jalankan 044_otp_attempts.sql.', 503);
    }

    private function otpAttempt(int $tenantId, string $phoneId): array
    {
        return $this->db($this->db_index)->query(
            'SELECT last_request_at, verify_fail_count, verify_locked_until FROM wa_otp_attempts WHERE tenant_id = ? AND phone_number_id = ? LIMIT 1',
            [$tenantId, $phoneId]
        )->row_array() ?: [];
    }

    private function secondsUntil(?string $timestamp, int $minimumSeconds = 0): int
    {
        if (!$timestamp) return 0;
        $until = strtotime($timestamp) + $minimumSeconds;
        return max(0, $until - time());
    }

    private function touchOtpRequest(int $tenantId, string $phoneId): void
    {
        $this->db($this->db_index)->query(
            'INSERT INTO wa_otp_attempts (tenant_id, phone_number_id, last_request_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_request_at = NOW()',
            [$tenantId, $phoneId]
        );
    }

    private function recordOtpVerifyFailure(int $tenantId, string $phoneId): int
    {
        $db = $this->db($this->db_index);
        $db->query(
            'INSERT INTO wa_otp_attempts (tenant_id, phone_number_id, verify_fail_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE verify_fail_count = verify_fail_count + 1',
            [$tenantId, $phoneId]
        );
        $row = $this->otpAttempt($tenantId, $phoneId);
        $fails = (int) ($row['verify_fail_count'] ?? 0);
        if ($fails >= 3) $this->lockOtpVerification($tenantId, $phoneId, 600);
        return $fails;
    }

    private function lockOtpVerification(int $tenantId, string $phoneId, int $seconds): void
    {
        $this->db($this->db_index)->query(
            'INSERT INTO wa_otp_attempts (tenant_id, phone_number_id, verify_locked_until) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE verify_locked_until = CASE WHEN verify_locked_until IS NULL OR verify_locked_until < NOW() THEN DATE_ADD(NOW(), INTERVAL ? SECOND) ELSE verify_locked_until END',
            [$tenantId, $phoneId, $seconds, $seconds]
        );
    }

    private function clearOtpVerifyFailures(int $tenantId, string $phoneId): void
    {
        $this->db($this->db_index)->query(
            'INSERT INTO wa_otp_attempts (tenant_id, phone_number_id, verify_fail_count, verify_locked_until) VALUES (?, ?, 0, NULL) ON DUPLICATE KEY UPDATE verify_fail_count = 0, verify_locked_until = NULL',
            [$tenantId, $phoneId]
        );
    }

    private function isMetaOtpRateLimit(array $res): bool
    {
        $text = strtolower((string) ($res['error'] ?? '') . ' ' . json_encode($res['data'] ?? [], JSON_UNESCAPED_SLASHES));
        return str_contains($text, '136025') || str_contains($text, 'too many times');
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

    /** Resolve a Team Leader's only permitted WABA on the server. */
    private function managedWabaId(array $user, string $requestedWabaId = ''): string
    {
        $requestedWabaId = trim($requestedWabaId);
        $isAdmin = (($user['role'] ?? '') === 'admin');
        if ($requestedWabaId !== '' && $isAdmin) return $requestedWabaId;

        $row = $this->db($this->db_index)->query(
            'SELECT w.meta_waba_id FROM wa_wabas w INNER JOIN wa_waba_teams wt ON wt.waba_id = w.id WHERE wt.tenant_id = ? AND wt.team_id = ? LIMIT 1',
            [(int) $user['tenant_id'], (int) ($user['team_id'] ?? 0)]
        )->row_array();
        $wabaId = trim((string) ($row['meta_waba_id'] ?? ''));
        if ($wabaId === '') {
            $this->error($isAdmin ? 'Admin harus masuk team yang terhubung WABA terlebih dahulu.' : 'Team belum di-assign ke WABA.', 422);
        }
        if ($requestedWabaId !== '' && $requestedWabaId !== $wabaId) $this->error('WABA tidak dapat diubah oleh Team Leader.', 403);
        return $wabaId;
    }

    private function assertManagedPhone(array $user, string $phoneId): void
    {
        if (($user['role'] ?? '') === 'admin') return;
        $row = $this->db($this->db_index)->query(
            "SELECT waba_id FROM wa_channels WHERE tenant_id = ? AND meta_phone_number_id = ? AND provider = 'meta' LIMIT 1",
            [(int) $user['tenant_id'], $phoneId]
        )->row_array();
        if (!$row) $this->error('Phone Number ID tidak berada pada WABA team Anda.', 403);
        $this->managedWabaId($user, (string) ($row['waba_id'] ?? ''));
    }

    private function recordTeamPendingPhone(array $user, string $wabaId, string $phoneId, string $phone, string $label): void
    {
        $db = $this->db($this->db_index);
        $exists = $db->query('SELECT id FROM wa_channels WHERE tenant_id = ? AND meta_phone_number_id = ? LIMIT 1', [(int) $user['tenant_id'], $phoneId])->row_array();
        if (!$exists) {
            $db->insert('wa_channels', [
                'tenant_id' => (int) $user['tenant_id'], 'waba_id' => $wabaId,
                'meta_phone_number_id' => $phoneId, 'device_id' => $phoneId,
                'phone_number' => '62' . $phone, 'label' => $label,
                'meta_verification_status' => 'PENDING', 'channel_type' => 'waba',
                'provider' => 'meta', 'status' => 'inactive',
            ]);
        }
        $this->syncWabaTeamsToChannels((int) $user['tenant_id'], $wabaId, [(int) $user['team_id']]);
    }
}
