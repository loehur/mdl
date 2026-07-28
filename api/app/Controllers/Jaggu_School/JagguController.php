<?php

namespace App\Controllers\Jaggu_School;

use App\Core\Controller as BaseController;

abstract class JagguController extends BaseController
{
    protected $db_index = 8;
    protected $session_key = 'jaggu_user_session';
    protected $token_cookie = 'jaggu_token';
    protected $token_lifetime = 604800;

    /**
     * Jam pergantian list (Asia/Jakarta).
     * Sebelum jam ini: hari ini. Mulai jam ini: besok (saling menggantikan).
     */
    protected const SWITCH_HOUR = 8;

    public function __construct()
    {
        $this->handleCors();
    }

    protected function verifyAuth()
    {
        if (!$this->restoreAuth()) {
            $this->error('Unauthorized', 401);
        }
    }

    protected function requireParent()
    {
        $this->verifyAuth();
        $user = $this->currentUser();
        if (($user['role'] ?? '') !== 'parent') {
            $this->error('Hanya orang tua yang dapat mengakses', 403);
        }
        return $user;
    }

    protected function requireChild()
    {
        $this->verifyAuth();
        $user = $this->currentUser();
        if (($user['role'] ?? '') !== 'child') {
            $this->error('Hanya akun anak yang dapat mengakses', 403);
        }
        return $user;
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
        if (!empty($_SERVER['HTTP_X_JAGGU_TOKEN'])) {
            return trim($_SERVER['HTTP_X_JAGGU_TOKEN']);
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
                "SELECT t.user_id, u.id, u.name, u.email, u.role, u.is_active
                 FROM jaggu_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.token_hash = ? AND t.expires_at > NOW()
                 LIMIT 1",
                [$hash]
            )->row_array();

            if (!$row || (int) $row['is_active'] !== 1) {
                return null;
            }

            $this->db($this->db_index)->update('jaggu_tokens', [
                'expires_at' => date('Y-m-d H:i:s', time() + $this->token_lifetime),
            ], ['token_hash' => $hash]);

            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function establishSession(array $user): void
    {
        $_SESSION[$this->session_key] = [
            'user' => $user,
            'logged_in' => true,
        ];
    }

    protected function extendSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$this->session_key]['extended_at'] = time();
        }
    }

    protected function issueAuthToken(int $userId): ?string
    {
        try {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $this->db($this->db_index)->insert('jaggu_tokens', [
                'user_id' => $userId,
                'token_hash' => $hash,
                'expires_at' => date('Y-m-d H:i:s', time() + $this->token_lifetime),
            ]);

            setcookie($this->token_cookie, $token, [
                'expires' => time() + $this->token_lifetime,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            return $token;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function clearAuth(): void
    {
        $token = $this->getRequestToken();
        if ($token !== '') {
            try {
                $this->db($this->db_index)->query(
                    'DELETE FROM jaggu_tokens WHERE token_hash = ?',
                    [hash('sha256', $token)]
                );
            } catch (\Throwable $e) {
                // ignore
            }
        }

        unset($_SESSION[$this->session_key]);
        setcookie($this->token_cookie, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    protected function currentUser(): array
    {
        return $_SESSION[$this->session_key]['user'] ?? [];
    }

    protected function dayNames(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
    }

    /** ISO day 1=Mon … 7=Sun */
    protected function isoDayOfWeek(string $ymd): int
    {
        return (int) date('N', strtotime($ymd . ' 12:00:00'));
    }

    /** List/info hari ini: sebelum jam 08:00. */
    protected function showToday(): bool
    {
        return (int) date('G') < self::SWITCH_HOUR;
    }

    /** List besok: mulai jam 08:00 (bergantian dengan hari ini). */
    protected function showTomorrow(): bool
    {
        return (int) date('G') >= self::SWITCH_HOUR;
    }
    protected function nextSchoolDate(string $fromYmd): ?string
    {
        $ts = strtotime($fromYmd . ' 12:00:00');
        for ($i = 1; $i <= 7; $i++) {
            $candidate = date('Y-m-d', strtotime("+{$i} day", $ts));
            $dow = $this->isoDayOfWeek($candidate);
            if ($dow >= 1 && $dow <= 6) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Ceklist boleh: mulai awal hari (for_date - 1) s/d akhir for_date.
     */
    protected function canChecklistDate(string $forDate): bool
    {
        $today = date('Y-m-d');
        $earliest = date('Y-m-d', strtotime($forDate . ' -1 day'));
        return $today >= $earliest && $today <= $forDate;
    }

    protected function getChildUser(): ?array
    {
        $row = $this->db($this->db_index)->query(
            "SELECT id, name, email, role FROM users WHERE role = 'child' AND is_active = 1 ORDER BY id ASC LIMIT 1"
        )->row_array();

        return $row ?: null;
    }

    protected function subjectsForDate(string $ymd): array
    {
        $dow = $this->isoDayOfWeek($ymd);
        if ($dow < 1 || $dow > 6) {
            return [];
        }

        $rows = $this->db($this->db_index)->query(
            "SELECT id, day_of_week, subject_name, sort_order
             FROM schedule_items
             WHERE day_of_week = ?
             ORDER BY sort_order ASC, id ASC",
            [$dow]
        )->result_array();

        return array_map(static function ($r) {
            return [
                'id' => (int) $r['id'],
                'day_of_week' => (int) $r['day_of_week'],
                'subject_name' => $r['subject_name'],
                'sort_order' => (int) $r['sort_order'],
            ];
        }, $rows ?: []);
    }

    protected function checkedMap(int $childId, string $ymd): array
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT schedule_item_id, checked_at
             FROM checklist_entries
             WHERE child_user_id = ? AND for_date = ?",
            [$childId, $ymd]
        )->result_array();

        $map = [];
        foreach ($rows ?: [] as $r) {
            $map[(int) $r['schedule_item_id']] = $r['checked_at'];
        }
        return $map;
    }

    protected function enrichDay(string $ymd, int $childId, bool $canCheck): array
    {
        $subjects = $this->subjectsForDate($ymd);
        $checked = $this->checkedMap($childId, $ymd);
        $dow = $this->isoDayOfWeek($ymd);
        $items = [];
        $done = 0;

        foreach ($subjects as $s) {
            $isChecked = isset($checked[$s['id']]);
            if ($isChecked) {
                $done++;
            }
            $items[] = array_merge($s, [
                'checked' => $isChecked,
                'checked_at' => $checked[$s['id']] ?? null,
            ]);
        }

        $total = count($items);

        return [
            'date' => $ymd,
            'day_of_week' => $dow,
            'day_name' => $this->dayNames()[$dow] ?? 'Minggu',
            'can_checklist' => $canCheck && $this->canChecklistDate($ymd),
            'total' => $total,
            'done' => $done,
            'pending' => max(0, $total - $done),
            'complete' => $total > 0 && $done === $total,
            'items' => $items,
        ];
    }
}
