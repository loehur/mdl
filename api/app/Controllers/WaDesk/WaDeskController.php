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
                "SELECT t.user_id, u.id, u.name, u.email, u.role, u.tenant_id, u.team_id, u.is_active
                 FROM wadesk_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.token_hash = ? AND t.expires_at > NOW()
                 LIMIT 1",
                [$hash]
            )->row_array();

            if (!$row || (int) $row['is_active'] !== 1) {
                return null;
            }

            $this->db($this->db_index)->update('wadesk_tokens', [
                'expires_at' => date('Y-m-d H:i:s', time() + $this->token_lifetime),
            ], ['token_hash' => $hash]);

            return $this->publicUser($row);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function publicUser(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'tenant_id' => (int) $row['tenant_id'],
            'team_id' => $row['team_id'] !== null ? (int) $row['team_id'] : null,
        ];
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
        if (($user['role'] ?? '') === 'admin') {
            return ["{$alias}.tenant_id = ?", [(int) $user['tenant_id']]];
        }
        return [
            "{$alias}.tenant_id = ? AND {$alias}.team_id = ?",
            [(int) $user['tenant_id'], (int) $user['team_id']],
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

    /**
     * Ensure ycloud_keys.api_key_hash is set; returns the hash.
     * Same plaintext YCloud credential → same hash → shared templates.
     */
    protected function ensureKeyApiHash(array $key): string
    {
        $existing = trim((string) ($key['api_key_hash'] ?? ''));
        if ($existing !== '' && strlen($existing) === 64) {
            return $existing;
        }
        $enc = (string) ($key['api_key_enc'] ?? '');
        if ($enc === '') {
            return '';
        }
        try {
            $plain = \App\Helpers\WaDesk\Crypto::decrypt($enc);
        } catch (\Throwable $e) {
            return '';
        }
        if ($plain === '') {
            return '';
        }
        $hash = \App\Helpers\WaDesk\Crypto::fingerprint($plain);
        // Only persist if migration has been applied
        try {
            $colCheck = $this->db($this->db_index)->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ycloud_keys' AND COLUMN_NAME = 'api_key_hash'"
            )->row_array();
            if ((int) ($colCheck['cnt'] ?? 0) > 0) {
                $this->db($this->db_index)->update('ycloud_keys', [
                    'api_key_hash' => $hash,
                ], ['id' => (int) $key['id']]);
            }
        } catch (\Throwable $e) {
            // ignore — migration not applied yet
        }
        return $hash;
    }

    /** Template usable with this WaDesk key row (shared by credential hash). */
    protected function findTemplateForKey(int $templateId, array $key): ?array
    {
        $hash = $this->ensureKeyApiHash($key);
        if ($hash !== '') {
            try {
                $colCheck = $this->db($this->db_index)->query(
                    "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wa_templates' AND COLUMN_NAME = 'api_key_hash'"
                )->row_array();
                if ((int) ($colCheck['cnt'] ?? 0) > 0) {
                    $tpl = $this->db($this->db_index)->query(
                        "SELECT * FROM wa_templates
                         WHERE id = ? AND (
                            api_key_hash = ?
                            OR (api_key_hash IS NULL AND ycloud_key_id = ?)
                         )
                         LIMIT 1",
                        [$templateId, $hash, (int) $key['id']]
                    )->row_array();
                    if ($tpl) {
                        return $tpl;
                    }
                }
            } catch (\Throwable $e) {
                // fall through to legacy lookup
            }
        }
        return $this->db($this->db_index)->query(
            "SELECT * FROM wa_templates WHERE id = ? AND ycloud_key_id = ? LIMIT 1",
            [$templateId, (int) $key['id']]
        )->row_array() ?: null;
    }
}
