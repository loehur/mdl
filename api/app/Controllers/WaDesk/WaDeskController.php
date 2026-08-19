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

    protected function requireAdmin(): array
    {
        $user = $this->currentUser();
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            $this->error('Admin only', 403);
        }
        return $user;
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
}
