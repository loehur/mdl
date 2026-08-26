<?php

namespace App\Controllers\WaDesk;

use App\Core\Controller as BaseController;

abstract class WaDeskController extends BaseController
{
    protected $db_index = 7;
    protected $session_key = 'wadesk_user_session';
    protected $token_cookie = 'wadesk_token';
    protected $token_lifetime = 604800;

    public function __construct()
    {
        $this->handleCors();
    }

    protected function verifyAuth(): void
    {
        if (!$this->restoreAuth()) {
            $this->error('Unauthorized', 401);
        }
    }

    protected function restoreAuth(): bool
    {
        if ($this->isSessionLoggedIn()) {
            $this->extendSession();
            return true;
        }

        $user = $this->authenticateByToken();
        if (!$user) {
            return false;
        }

        $this->establishSession($user);
        $this->extendSession();
        return true;
    }

    protected function isSessionLoggedIn(): bool
    {
        return !empty($_SESSION[$this->session_key]['logged_in']);
    }

    protected function getRequestToken(): string
    {
        if (!empty($_SERVER['HTTP_X_WADESK_TOKEN'])) {
            return trim($_SERVER['HTTP_X_WADESK_TOKEN']);
        }
        return trim($_COOKIE[$this->token_cookie] ?? '');
    }

    protected function authenticateByToken(): ?array
    {
        $token = $this->getRequestToken();
        if ($token === '') {
            return null;
        }

        try {
            $hash = hash('sha256', $token);
            $row = $this->db($this->db_index)->query(
                "SELECT t.user_id
                 FROM wadesk_tokens t
                 WHERE t.token_hash = ? AND t.expires_at > NOW()
                 LIMIT 1",
                [$hash]
            )->row_array();

            if (!$row) {
                return null;
            }

            $user = $this->loadPublicUser((int) $row['user_id']);
            if (!$user) {
                return null;
            }

            $this->db($this->db_index)->update('wadesk_tokens', [
                'expires_at' => date('Y-m-d H:i:s', time() + $this->token_lifetime),
            ], ['token_hash' => $hash]);

            return $user;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function publicUser(array $row): array
    {
        $user = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'tenant_id' => (int) $row['tenant_id'],
            'team_id' => $row['team_id'] !== null ? (int) $row['team_id'] : null,
        ];
        if (array_key_exists('team_name', $row)) {
            $user['team_name'] = $row['team_name'] !== null && $row['team_name'] !== ''
                ? (string) $row['team_name']
                : null;
        }
        return $user;
    }

    protected function loadPublicUser(int $userId): ?array
    {
        $row = $this->db($this->db_index)->query(
            "SELECT u.id, u.name, u.email, u.role, u.tenant_id, u.team_id, u.is_active,
                    t.name AS team_name
             FROM users u
             LEFT JOIN teams t ON t.id = u.team_id
             WHERE u.id = ?
             LIMIT 1",
            [$userId]
        )->row_array();

        if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
            return null;
        }

        return $this->publicUser($row);
    }

    protected function hasOperationalTeam(?array $user = null): bool
    {
        $user = $user ?? $this->currentUser();
        return !empty($user['team_id']) && (int) $user['team_id'] > 0;
    }

    /** Admin/TL/agent must join a team before send chat or blast. */
    protected function requireOperationalTeam(): array
    {
        $user = $this->requireChatUser();
        if (!$this->hasOperationalTeam($user)) {
            $this->error(
                'Anda belum bergabung ke team. Admin harus masuk team dulu untuk kirim atau blast WA.',
                403,
                ['code' => 'no_team']
            );
        }
        return $user;
    }

    protected function establishSession(array $user): void
    {
        $_SESSION[$this->session_key] = [
            'user' => $user,
            'logged_in' => true,
        ];
    }

    protected function issueAuthToken(int $userId): ?string
    {
        try {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);

            $this->db($this->db_index)->insert('wadesk_tokens', [
                'user_id' => $userId,
                'token_hash' => $hash,
                'expires_at' => date('Y-m-d H:i:s', time() + $this->token_lifetime),
            ]);

            $this->pruneUserTokens($userId);
            $this->setTokenCookie($token);

            return $token;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function revokeAuthToken(): void
    {
        $token = $this->getRequestToken();
        if ($token !== '') {
            try {
                $hash = hash('sha256', $token);
                $this->db($this->db_index)->delete('wadesk_tokens', ['token_hash' => $hash]);
            } catch (\Throwable $e) {
                /* ignore */
            }
        }
        $this->clearTokenCookie();
    }

    protected function pruneUserTokens(int $userId): void
    {
        try {
            $rows = $this->db($this->db_index)->query(
                "SELECT id FROM wadesk_tokens WHERE user_id = ? ORDER BY id DESC",
                [$userId]
            )->result_array();

            if (count($rows) <= 5) {
                return;
            }

            $keep = array_column(array_slice($rows, 0, 5), 'id');
            $placeholders = implode(',', array_fill(0, count($keep), '?'));
            $this->db($this->db_index)->query(
                "DELETE FROM wadesk_tokens WHERE user_id = ? AND id NOT IN ({$placeholders})",
                array_merge([$userId], $keep)
            );
        } catch (\Throwable $e) {
            /* ignore */
        }
    }

    protected function cookieDomain(): string
    {
        $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        if ($host === 'nalju.com' || str_ends_with($host, '.nalju.com')) {
            return '.nalju.com';
        }
        return '';
    }

    protected function setTokenCookie(string $token): void
    {
        if (headers_sent()) {
            return;
        }
        $domain = $this->cookieDomain();
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $params = [
            'expires' => time() + $this->token_lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ];
        if ($domain !== '') {
            $params['domain'] = $domain;
        }
        setcookie($this->token_cookie, $token, $params);
    }

    protected function clearTokenCookie(): void
    {
        if (headers_sent()) {
            return;
        }
        $domain = $this->cookieDomain();
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $params = [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ];
        if ($domain !== '') {
            $params['domain'] = $domain;
        }
        setcookie($this->token_cookie, '', $params);
    }

    protected function extendSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!empty($_SESSION[$this->session_key]['logged_in'])) {
            $_SESSION[$this->session_key]['expires_at'] = time() + $this->token_lifetime;
        }
    }

    protected function currentUser(): ?array
    {
        return $_SESSION[$this->session_key]['user'] ?? null;
    }

    public const MAX_AGENTS_PER_TEAM = 2;

    protected function requireAdmin(): array
    {
        $user = $this->currentUser();
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            $this->error('Admin only', 403);
        }
        return $user;
    }

    /** Team Leader atau Admin — untuk tab Team & Quota di Account. */
    protected function requireTeamLeaderOrAdmin(): array
    {
        $user = $this->currentUser();
        $role = $user['role'] ?? '';
        if (!$user || !in_array($role, ['admin', 'team_leader'], true)) {
            $this->error('Team Leader atau Admin only', 403);
        }
        return $user;
    }

    protected function countTeamAgents(int $teamId, int $tenantId): int
    {
        if ($teamId <= 0) {
            return 0;
        }
        $row = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS c FROM users
             WHERE team_id = ? AND tenant_id = ? AND role = 'agent' AND is_active = 1",
            [$teamId, $tenantId]
        )->row_array();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Team operasional yang boleh dikelola TL/admin (Account → Team & Quota).
     * TL: team tempat user jadi leader resmi. Admin: team yang sudah di-join.
     */
    protected function resolveManagedTeamId(array $user): int
    {
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $role = $user['role'] ?? '';

        if ($role === 'team_leader') {
            $team = $this->db($this->db_index)->query(
                "SELECT id FROM teams
                 WHERE tenant_id = ? AND team_leader_user_id = ?
                 LIMIT 1",
                [$tenantId, (int) $user['id']]
            )->row_array();
            $teamId = (int) ($team['id'] ?? 0);
            if ($teamId <= 0) {
                $this->error('Anda belum terhubung sebagai Team Leader pada team manapun', 403, ['code' => 'no_team']);
            }
            return $teamId;
        }

        if ($role === 'admin') {
            $teamId = (int) ($user['team_id'] ?? 0);
            if ($teamId <= 0) {
                $this->error(
                    'Admin belum bergabung ke team. Masuk team dulu di Admin.',
                    403,
                    ['code' => 'no_team']
                );
            }
            $team = $this->db($this->db_index)->query(
                "SELECT id FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$teamId, $tenantId]
            )->row_array();
            if (!$team) {
                $this->error('Team tidak ditemukan', 404);
            }
            return $teamId;
        }

        $this->error('Forbidden', 403);
    }

    protected function requireChatUser(): array
    {
        $user = $this->currentUser();
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'team_leader', 'agent'], true)) {
            $this->error('Forbidden', 403);
        }
        return $user;
    }

    /**
     * SQL fragment + binds for conversation visibility.
     * @return array{0:string,1:array}
     */
    protected function visibilitySql(string $alias = 'c'): array
    {
        $user = $this->currentUser();
        if (($user['role'] ?? '') === 'admin' && !$this->hasOperationalTeam($user)) {
            return ['1=0', []];
        }
        if ($this->hasOperationalTeam($user)) {
            return [
                "{$alias}.tenant_id = ? AND {$alias}.team_id = ?",
                [(int) $user['tenant_id'], (int) $user['team_id']],
            ];
        }
        return [
            "{$alias}.tenant_id = ? AND {$alias}.team_id = ?",
            [(int) $user['tenant_id'], (int) ($user['team_id'] ?? 0)],
        ];
    }

    protected function canAccessTeam(int $teamId): bool
    {
        $user = $this->currentUser();
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return (int) ($user['team_id'] ?? 0) === $teamId;
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        if (!str_starts_with($digits, '62') && strlen($digits) >= 9) {
            $digits = '62' . ltrim($digits, '0');
        }
        return $digits;
    }

    /** @return array{api_key:string,api_key_masked:string,configured:bool} */
    protected function getTenantKiriminConfig(int $tenantId): array
    {
        $row = $this->db($this->db_index)->query(
            "SELECT kirimin_api_key FROM tenants WHERE id = ? LIMIT 1",
            [$tenantId]
        )->row_array();
        $apiKey = trim((string) ($row['kirimin_api_key'] ?? ''));
        return [
            'api_key' => $apiKey,
            'api_key_masked' => $this->maskApiKey($apiKey),
            'configured' => $apiKey !== '',
        ];
    }

    protected function getTenantKiriminApiKey(int $tenantId): string
    {
        return $this->getTenantKiriminConfig($tenantId)['api_key'];
    }

    protected function kiriminForTenant(int $tenantId): \App\Helpers\WaDesk\Kirimin
    {
        return \App\Helpers\WaDesk\Kirimin::fromApiKey($this->getTenantKiriminApiKey($tenantId));
    }

    protected function requireKiriminConfigured(int $tenantId): \App\Helpers\WaDesk\Kirimin
    {
        if ($this->getTenantKiriminApiKey($tenantId) === '') {
            $this->error('Kirimin API key belum diatur. Simpan API key di Admin → Channel.', 400);
        }
        return $this->kiriminForTenant($tenantId);
    }

    /** @return array{api_key:string,api_key_masked:string,configured:bool} */
    protected function getTenantOpenAiConfig(int $tenantId): array
    {
        $row = $this->db($this->db_index)->query(
            "SELECT openai_api_key FROM tenants WHERE id = ? LIMIT 1",
            [$tenantId]
        )->row_array();
        $apiKey = trim((string) ($row['openai_api_key'] ?? ''));
        return [
            'api_key' => $apiKey,
            'api_key_masked' => $this->maskApiKey($apiKey),
            'configured' => $apiKey !== '',
        ];
    }

    protected function getTenantOpenAiApiKey(int $tenantId): string
    {
        return $this->getTenantOpenAiConfig($tenantId)['api_key'];
    }

    /**
     * @return array{safe:bool,reason:string,skipped?:bool}
     */
    protected function moderateTemplateParamValues(int $tenantId, array $paramDefs, array $rawParams): array
    {
        $entries = \App\Helpers\WaDesk\TemplateParamModerator::entriesFromDefs($paramDefs, $rawParams);
        $moderator = new \App\Helpers\WaDesk\TemplateParamModerator($this->db($this->db_index));
        return $moderator->moderate($this->getTenantOpenAiApiKey($tenantId), $entries);
    }

    protected function requireTemplateParamsSafe(int $tenantId, array $paramDefs, array $rawParams): void
    {
        if ($this->getTenantOpenAiApiKey($tenantId) === '') {
            $this->error('OpenAI API key belum diatur. Simpan di Admin → OpenAI.', 400);
        }

        $result = $this->moderateTemplateParamValues($tenantId, $paramDefs, $rawParams);
        if (!$result['safe']) {
            $this->error($result['reason'] ?: 'Konten parameter tidak aman', 422, [
                'safe' => false,
                'reason' => $result['reason'],
            ]);
        }
    }

    /**
     * @param array<int,array{phone?:string,params?:array}> $rows
     * @return array{safe:bool,reason:string,skipped?:bool}
     */
    protected function moderateBlastParamValues(int $tenantId, array $paramDefs, array $rows): array
    {
        $values = \App\Helpers\WaDesk\TemplateParamModerator::collectBlastRowValues($rows, $paramDefs);
        $moderator = new \App\Helpers\WaDesk\TemplateParamModerator($this->db($this->db_index));

        return $moderator->moderateBatchValues($this->getTenantOpenAiApiKey($tenantId), $values);
    }

    /**
     * @param array<int,array{phone?:string,params?:array}> $rows
     */
    protected function requireBlastParamsSafe(int $tenantId, array $paramDefs, array $rows): void
    {
        if ($this->getTenantOpenAiApiKey($tenantId) === '') {
            $this->error('OpenAI API key belum diatur. Simpan di Admin → OpenAI.', 400);
        }

        $result = $this->moderateBlastParamValues($tenantId, $paramDefs, $rows);
        if (!$result['safe']) {
            $this->error($result['reason'] ?: 'Konten parameter blast tidak aman', 422, [
                'safe' => false,
                'reason' => $result['reason'],
            ]);
        }
    }

    /**
     * @return array{status:bool,new_words:string,reason:string}
     */
    protected function polishFreeMessageText(int $tenantId, string $message): array
    {
        if ($this->getTenantOpenAiApiKey($tenantId) === '') {
            $this->error('OpenAI API key belum diatur. Simpan di Admin → OpenAI.', 400);
        }

        $polisher = new \App\Helpers\WaDesk\FreeTextPolisher();
        return $polisher->polish($this->getTenantOpenAiApiKey($tenantId), $message);
    }

    /**
     * @return array{duplicate_spam:bool,reason:string}
     */
    protected function checkFreeTextDuplicateSpam(int $tenantId, string $pendingMessage, string $newMessage): array
    {
        if ($this->getTenantOpenAiApiKey($tenantId) === '') {
            return ['duplicate_spam' => false, 'reason' => ''];
        }

        $guard = new \App\Helpers\WaDesk\FreeTextSpamGuard();
        return $guard->check($this->getTenantOpenAiApiKey($tenantId), $pendingMessage, $newMessage);
    }

    protected function maskApiKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        if (strlen($key) <= 12) {
            return str_repeat('*', strlen($key));
        }
        return substr($key, 0, 8) . '…' . substr($key, -4);
    }

    protected function channelsTable(): string
    {
        return 'wa_channels';
    }

    protected function tableExists(string $table): bool
    {
        try {
            $row = $this->db($this->db_index)->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            )->row_array();
            return (int) ($row['cnt'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Map wa_channels row for API (channel_id alias). */
    protected function mapChannelRow(array $row): array
    {
        $row['channel_id'] = (int) ($row['id'] ?? 0);
        unset($row['api_key_enc']);
        return $row;
    }

    protected const TEMPLATE_PARAM_DEFAULT_MAXLENGTH = 20;

    protected function effectiveParamMaxlength(array $def): int
    {
        $max = (int) ($def['maxlength'] ?? 0);
        return $max > 0 ? $max : self::TEMPLATE_PARAM_DEFAULT_MAXLENGTH;
    }

    protected function templateParamKey(array $def): string
    {
        $name = trim((string) ($def['param_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return strtolower((string) ($def['component'] ?? 'body')) . '_' . (int) ($def['param_index'] ?? 0);
    }

    /** @return list<string> */
    protected function validateTemplateParamValues(array $defs, array $rawParams, string $rowLabel = ''): array
    {
        if ($defs === []) {
            return [];
        }

        $errors = [];
        $prefix = $rowLabel !== '' ? ($rowLabel . ': ') : '';
        $isList = $rawParams === [] || array_keys($rawParams) === range(0, count($rawParams) - 1);
        $listCursor = 0;

        foreach ($defs as $def) {
            $component = strtolower((string) ($def['component'] ?? 'body'));
            $paramName = trim((string) ($def['param_name'] ?? ''));
            $idx = (int) ($def['param_index'] ?? 0);
            $csvKey = $component . '_' . $idx;
            $maxLen = $this->effectiveParamMaxlength($def);
            $label = trim((string) ($def['label'] ?? $paramName ?: $csvKey));

            $value = '';
            if ($paramName !== '' && !$isList && array_key_exists($paramName, $rawParams)) {
                $value = (string) $rawParams[$paramName];
            } elseif (!$isList && array_key_exists($csvKey, $rawParams)) {
                $value = (string) $rawParams[$csvKey];
            } elseif (!$isList && array_key_exists((string) $idx, $rawParams)) {
                $value = (string) $rawParams[(string) $idx];
            } elseif ($isList && array_key_exists($listCursor, $rawParams)) {
                $value = (string) $rawParams[$listCursor];
                $listCursor++;
            } elseif ($isList && array_key_exists($idx - 1, $rawParams)) {
                $value = (string) $rawParams[$idx - 1];
            }

            if ($value === '') {
                continue;
            }
            if (mb_strlen($value) > $maxLen) {
                $errors[] = $prefix . "'{$label}' maksimal {$maxLen} karakter (sekarang " . mb_strlen($value) . ')';
            }
        }

        return $errors;
    }

    /** Template usable by any team in tenant (tenant-wide sync). */
    protected function findTemplateForTenant(int $templateId, int $tenantId): ?array
    {
        return $this->db($this->db_index)->query(
            "SELECT * FROM wa_templates WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$templateId, $tenantId]
        )->row_array() ?: null;
    }

    protected function templateDevicesTableExists(): bool
    {
        return $this->tableExists('wa_template_devices');
    }

    protected function templateTeamsTableExists(): bool
    {
        return $this->tableExists('wa_template_teams');
    }

    protected function templateFailLogsTableExists(): bool
    {
        return $this->tableExists('wa_template_fail_logs');
    }

    /** @return list<array{id:int,name:string}> */
    protected function teamsOnWaba(int $tenantId, string $wabaId): array
    {
        $wabaId = trim($wabaId);
        if ($tenantId <= 0 || $wabaId === '') {
            return [];
        }

        $tbl = $this->channelsTable();
        return $this->db($this->db_index)->query(
            "SELECT DISTINCT t.id, t.name
             FROM teams t
             WHERE t.tenant_id = ?
               AND t.id IN (
                 SELECT k.team_id FROM {$tbl} k
                 WHERE k.tenant_id = ? AND TRIM(k.waba_id) = ? AND k.team_id IS NOT NULL
                 UNION
                 SELECT ct.team_id FROM wa_channel_teams ct
                 INNER JOIN {$tbl} k ON k.id = ct.channel_id
                 WHERE k.tenant_id = ? AND TRIM(k.waba_id) = ?
               )
             ORDER BY t.name ASC",
            [$tenantId, $tenantId, $wabaId, $tenantId, $wabaId]
        )->result_array();
    }

    protected function countTeamsOnWaba(int $tenantId, string $wabaId): int
    {
        return count($this->teamsOnWaba($tenantId, $wabaId));
    }

    protected function wabaRequiresTemplateTeamAssignment(int $tenantId, string $wabaId): bool
    {
        return $this->countTeamsOnWaba($tenantId, $wabaId) > 1;
    }

    /** @return list<string> */
    protected function templateWabaIds(int $templateId, int $tenantId): array
    {
        if ($templateId <= 0 || !$this->templateDevicesTableExists()) {
            return [];
        }

        $tbl = $this->channelsTable();
        $rows = $this->db($this->db_index)->query(
            "SELECT DISTINCT NULLIF(TRIM(c.waba_id), '') AS waba_id
             FROM wa_template_devices td
             INNER JOIN {$tbl} c ON c.device_id = td.device_id AND c.tenant_id = ?
             WHERE td.template_id = ? AND NULLIF(TRIM(c.waba_id), '') IS NOT NULL",
            [$tenantId, $templateId]
        )->result_array();

        $out = [];
        foreach ($rows as $row) {
            $wabaId = trim((string) ($row['waba_id'] ?? ''));
            if ($wabaId !== '') {
                $out[$wabaId] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Resolve WABA scope untuk assign template ↔ team (teamOptions & assignTeams harus selaras).
     *
     * @return list<string>
     */
    protected function resolveTemplateAssignWabaIds(int $templateId, int $tenantId, string $wabaScope = ''): array
    {
        $linked = $this->templateWabaIds($templateId, $tenantId);
        $scope = trim($wabaScope);

        if ($scope !== '') {
            foreach ($linked as $wabaId) {
                if ($wabaId === $scope) {
                    return [$wabaId];
                }
            }
            if ($linked === []) {
                return [$scope];
            }
        }

        if ($linked !== []) {
            return $linked;
        }

        return $scope !== '' ? [$scope] : [];
    }

    protected function isTemplateAssignedToTeam(int $templateId, int $teamId): bool
    {
        if ($templateId <= 0 || $teamId <= 0 || !$this->templateTeamsTableExists()) {
            return false;
        }

        $row = $this->db($this->db_index)->query(
            "SELECT 1 AS ok FROM wa_template_teams
             WHERE template_id = ? AND team_id = ?
             LIMIT 1",
            [$templateId, $teamId]
        )->row_array();

        return !empty($row);
    }

    protected function assertTemplateTeamAssignment(
        int $templateId,
        array $channel,
        int $tenantId,
        int $teamId,
        ?array $templateRow = null
    ): void {
        if (!$this->templateTeamsTableExists() || $teamId <= 0) {
            return;
        }

        $wabaId = trim((string) ($channel['waba_id'] ?? ''));
        if ($wabaId === '') {
            return;
        }

        if (!$this->wabaRequiresTemplateTeamAssignment($tenantId, $wabaId)) {
            return;
        }

        if ($this->isTemplateAssignedToTeam($templateId, $teamId)) {
            return;
        }

        $templateRow = $templateRow ?? $this->findTemplateForTenant($templateId, $tenantId);
        $name = trim((string) ($templateRow['template_name'] ?? 'template'));
        $this->error(
            'Template "' . $name . '" belum di-assign ke team Anda. '
            . 'WABA ini dipakai lebih dari 1 team — assign template di Admin → Templates.',
            422,
            ['code' => 'template_not_assigned', 'waba_id' => $wabaId]
        );
    }

    protected function isTemplateAvailableOnDevice(int $templateId, string $deviceId): bool
    {
        $deviceId = trim($deviceId);
        if ($templateId <= 0 || $deviceId === '') {
            return false;
        }
        if (!$this->templateDevicesTableExists()) {
            return true;
        }
        $row = $this->db($this->db_index)->query(
            "SELECT 1 AS ok FROM wa_template_devices WHERE template_id = ? AND device_id = ? LIMIT 1",
            [$templateId, $deviceId]
        )->row_array();
        return !empty($row);
    }

    protected function assertTemplateOnChannel(int $templateId, array $channel, int $tenantId, ?int $teamId = null): void
    {
        $tpl = $this->findTemplateForTenant($templateId, $tenantId);
        if (!$tpl) {
            $this->error('Template tidak ditemukan', 404);
        }
        $deviceId = trim((string) ($channel['device_id'] ?? ''));
        if ($deviceId === '') {
            $this->error('Channel belum punya device_id Kirimin', 400);
        }
        if (!$this->isTemplateAvailableOnDevice($templateId, $deviceId)) {
            $label = trim((string) ($channel['label'] ?? $channel['phone_number'] ?? 'channel ini'));
            $this->error(
                'Template "' . ($tpl['template_name'] ?? '') . '" tidak tersedia di nomor WA '
                . $label . '. Pilih template lain atau sync ulang di Admin.',
                422
            );
        }

        if ($teamId !== null && $teamId > 0) {
            $this->assertTemplateTeamAssignment($templateId, $channel, $tenantId, $teamId, $tpl);
        }
    }

    /** @param array<string,mixed> $ctx */
    protected function logTemplateSendFailure(array $ctx): void
    {
        try {
            $logger = new \App\Helpers\WaDesk\TemplateFailLogger($this->db($this->db_index));
            $logger->log($ctx);
        } catch (\Throwable $e) {
            try {
                \Log::write(
                    'template_fail_log error: ' . $e->getMessage(),
                    'wadesk',
                    'template_fail_log'
                );
            } catch (\Throwable $ignored) {
            }
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        try {
            $row = $this->db($this->db_index)->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            )->row_array();
            return (int) ($row['cnt'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function getTenantDailyUniqueLimit(int $tenantId): int
    {
        $limit = \App\Helpers\WaDesk\DailyKeyLimit::DEFAULT_DAILY_UNIQUE_LIMIT;
        try {
            $row = $this->db($this->db_index)->query(
                "SELECT daily_unique_limit FROM tenants WHERE id = ? LIMIT 1",
                [$tenantId]
            )->row_array();
            $val = (int) ($row['daily_unique_limit'] ?? 0);
            if ($val > 0) {
                $limit = $val;
            }
        } catch (\Throwable $e) {
            /* ignore */
        }
        return $limit;
    }

    protected function requireChannelWabaId(array $channel): string
    {
        $wabaId = trim((string) ($channel['waba_id'] ?? ''));
        if ($wabaId === '') {
            $this->error(
                'WABA ID belum diatur untuk channel ini. Isi manual di Admin → Channel.',
                422,
                ['channel_id' => (int) ($channel['id'] ?? 0)]
            );
        }
        return $wabaId;
    }

    /** @return array{configured:bool,limit:?int,used_today:?int,remaining_today:?int} */
    protected function dailyLimitStatusForTenant(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [
                'configured' => false,
                'limit' => null,
                'used_today' => null,
                'remaining_today' => null,
            ];
        }

        $guard = new \App\Helpers\WaDesk\DailyKeyLimit($this->db($this->db_index));
        $limit = $guard->getLimit($tenantId);
        $used = $guard->countUsedToday($tenantId);

        return [
            'configured' => true,
            'limit' => $limit,
            'used_today' => $used,
            'remaining_today' => max(0, $limit - $used),
        ];
    }

    /** @deprecated Use dailyLimitStatusForTenant() */
    protected function dailyLimitStatusForChannel(?array $channel): array
    {
        $tenantId = (int) ($channel['tenant_id'] ?? 0);
        return $this->dailyLimitStatusForTenant($tenantId);
    }

    protected function findTeamChannel(int $teamId, int $tenantId): ?array
    {
        if ($teamId <= 0) {
            return null;
        }
        $tbl = $this->channelsTable();
        return $this->db($this->db_index)->query(
            "SELECT * FROM {$tbl} k
             WHERE k.tenant_id = ? AND k.status = 'active'
               AND {$this->channelTeamSql($tbl, $teamId)}
             ORDER BY (k.team_id = ?) DESC, k.id DESC
             LIMIT 1",
            [$tenantId, $teamId]
        )->row_array() ?: null;
    }

    /**
     * SQL fragment: channel dipakai oleh team (sebagai team utama ATAU team tambahan).
     * Caller WAJIB pakai alias `k` untuk tabel wa_channels (FROM wa_channels k).
     * $teamId di-inline (int); caller TIDAK perlu menambahkan bind param untuk team_id.
     */
    protected function channelTeamSql(string $table, int $teamId): string
    {
        $alias = $this->channelTeamAlias($table);
        return "({$alias}.team_id = {$teamId}
                 OR EXISTS (
                   SELECT 1 FROM wa_channel_teams ct
                   WHERE ct.channel_id = {$alias}.id AND ct.team_id = {$teamId}
                 ))";
    }

    /** Alias aman untuk channelTeamSql: nama tabel polos -> 'k', selain itu dipakai apa adanya. */
    protected function channelTeamAlias(string $table): string
    {
        $table = trim($table);
        return $table === '' || $table === $this->channelsTable() ? 'k' : $table;
    }

    /** Semua team (id + nama) yang memakai channel, termasuk team utama. */
    protected function channelTeamRows(int $channelId, int $tenantId): array
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT DISTINCT t.id, t.name
             FROM teams t
             INNER JOIN wa_channel_teams ct ON ct.team_id = t.id
             WHERE ct.channel_id = ? AND t.tenant_id = ?
             ORDER BY t.name ASC",
            [$channelId, $tenantId]
        )->result_array();

        // Pastikan team utama selalu ada walau join table belum sinkron
        $main = $this->db($this->db_index)->query(
            "SELECT team_id FROM {$this->channelsTable()} WHERE id = ? LIMIT 1",
            [$channelId]
        )->row_array();
        $mainId = (int) ($main['team_id'] ?? 0);
        if ($mainId > 0) {
            $found = false;
            foreach ($rows as $r) {
                if ((int) $r['id'] === $mainId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $t = $this->db($this->db_index)->query(
                    "SELECT id, name FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
                    [$mainId, $tenantId]
                )->row_array();
                if ($t) {
                    $rows[] = $t;
                }
            }
        }
        return $rows;
    }

    /** @param list<array{id:int,name:string}> $rows */
    protected function formatTeamNames(array $rows): string
    {
        return implode(' + ', array_map(static fn ($r) => (string) ($r['name'] ?? ''), $rows));
    }

    /**
     * Sinkronkan team-set channel di wa_channel_teams.
     * $teamIds: SEMUA team yang boleh memakai channel (termasuk team utama).
     */
    /** Default team tenant — customer baru (belum ada riwayat di nomor) masuk ke sini. */
    protected function getTenantDefaultTeamId(int $tenantId): int
    {
        $row = $this->db($this->db_index)->query(
            "SELECT id FROM teams WHERE tenant_id = ? AND is_default = 1 LIMIT 1",
            [$tenantId]
        )->row_array();

        return (int) ($row['id'] ?? 0);
    }

    protected function syncChannelTeams(int $channelId, array $teamIds): void
    {
        $teamIds = array_values(array_unique(array_map('intval', $teamIds)));
        $teamIds = array_filter($teamIds, static fn ($id) => $id > 0);
        if ($teamIds === []) {
            return;
        }

        $existing = $this->db($this->db_index)->query(
            "SELECT team_id FROM wa_channel_teams WHERE channel_id = ?",
            [$channelId]
        )->result_array();
        $have = array_map('intval', array_column($existing, 'team_id'));

        $toAdd = array_diff($teamIds, $have);
        foreach ($toAdd as $tid) {
            $this->db($this->db_index)->insert('wa_channel_teams', [
                'channel_id' => $channelId,
                'team_id' => $tid,
            ]);
        }

        $toRemove = array_diff($have, $teamIds);
        if ($toRemove !== []) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $binds = array_merge([$channelId], $toRemove);
            $this->db($this->db_index)->query(
                "DELETE FROM wa_channel_teams WHERE channel_id = ? AND team_id IN ({$placeholders})",
                $binds
            );

            // Conversation team yang dicabut ikut dihapus (messages cascade),
            // supaya routing tidak menunjuk ke conversation team yang sudah tidak memakai nomor.
            $this->db($this->db_index)->query(
                "DELETE FROM conversations WHERE channel_id = ? AND team_id IN ({$placeholders})",
                $binds
            );
        }
    }
}
