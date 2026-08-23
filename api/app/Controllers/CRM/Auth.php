<?php

namespace App\Controllers\CRM;

use App\Core\Controller;

/**
 * CRM Auth Controller
 * - admin: mdl_main.crm_users
 * - crew: mdl_laundry.cabang (username = id_cabang, code = CR)
 * - driver: mdl_laundry.user.no_user (en=1, code = DR)
 * - device lock: mdl_main.crm_device_locks (1 username = 1 device)
 */
class Auth extends Controller
{
    private $db_index = 0;      // mdl_main
    private $db_laundry = 1;    // mdl_laundry
    private $session_key = 'mdl_crm_session';

    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * Login (Simple) + claim device lock
     * POST /CRM/Auth/login
     * Body: { "username": "...", "device_id": "..." }
     */
    public function login()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['username', 'device_id']);

            $username = trim((string) $body['username']);
            $deviceId = trim((string) $body['device_id']);

            if ($deviceId === '' || strlen($deviceId) > 64) {
                $this->error('device_id tidak valid', 400);
            }

            $userData = $this->resolveUser($username);

            if (!$userData) {
                $this->error('Username tidak ditemukan', 401);
            }

            $lockUser = strtoupper((string) $userData['username']);
            $claim = $this->claimDeviceLock($lockUser, $deviceId);

            if (!$claim['ok']) {
                $this->error($claim['message'], 409);
            }

            $userData['username'] = $lockUser;
            $userData['device_id'] = $deviceId;

            $_SESSION[$this->session_key] = [
                'user' => $userData,
                'logged_in' => true,
                'login_time' => time(),
                'device_id' => $deviceId,
            ];

            \Log::write("CRM Login Success: {$lockUser} role={$userData['role']} device=" . substr($deviceId, 0, 8), 'crm_auth', 'Auth');

            $this->json([
                'success' => true,
                'message' => 'Login berhasil',
                'user' => $userData,
                'redirect' => '/dashboard'
            ]);

        } catch (\Exception $e) {
            \Log::write("CRM Login error: " . $e->getMessage(), 'crm_error', 'Auth');
            $this->error('Terjadi kesalahan sistem', 500);
        }
    }

    /**
     * Verify device owns lock (used by wa_server on WS connect)
     * GET /CRM/Auth/verifyDevice?username=X&device=Y
     */
    public function verifyDevice()
    {
        if (!$this->isGet()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $username = strtoupper(trim((string) ($_GET['username'] ?? '')));
            $deviceId = trim((string) ($_GET['device'] ?? ''));

            if ($username === '' || $deviceId === '') {
                $this->json([
                    'success' => false,
                    'ok' => false,
                    'message' => 'username dan device wajib',
                ], 400);
            }

            $result = $this->verifyOrRefreshLock($username, $deviceId);

            $this->json([
                'success' => true,
                'ok' => $result['ok'],
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            \Log::write("CRM verifyDevice error: " . $e->getMessage(), 'crm_error', 'Auth');
            $this->error('Terjadi kesalahan sistem', 500);
        }
    }

    /**
     * Resolve login user:
     * 1) crm_users with role admin
     * 2) cabang by id_cabang → crew, code CR
     * 3) user by no_user → driver, code DR
     */
    private function resolveUser(string $username): ?array
    {
        $user = $this->db($this->db_index)
            ->where('LOWER(username)', strtolower($username))
            ->get('crm_users')
            ->row_array();

        if ($user) {
            $role = strtolower($user['role'] ?? '');
            if ($role === 'admin') {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'name' => $user['name'],
                    'role' => $role,
                    'code' => $user['code'] ?? '',
                ];
            }
        }

        if (is_numeric($username)) {
            $idCabang = (int) $username;
            $cabang = $this->db($this->db_laundry)
                ->where('id_cabang', $idCabang)
                ->get('cabang')
                ->row_array();

            if ($cabang) {
                return [
                    'id' => $idCabang,
                    'username' => (string) $idCabang,
                    'name' => $cabang['kode_cabang'] ?? (string) $idCabang,
                    'role' => 'crew',
                    'code' => 'CR',
                ];
            }
        }

        $driver = $this->resolveDriverUser($username);
        if ($driver !== null) {
            return $driver;
        }

        return null;
    }

    private function resolveDriverUser(string $username): ?array
    {
        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../../Helpers/CRM/WaSenderContext.php';
        }

        $nomor = \App\Helpers\CRM\WaSenderContext::toNomorNasional($username);
        if ($nomor === null) {
            return null;
        }

        $expr = \App\Helpers\CRM\WaSenderContext::sqlDigitsExpr('no_user');
        $row = $this->db($this->db_laundry)->query(
            "SELECT id_user, nama_user, id_cabang FROM user WHERE en = 1 AND {$expr} LIKE ? LIMIT 1",
            ['%' . $nomor]
        )->row_array();

        if (!$row || empty($row['id_user'])) {
            return null;
        }

        $loginKey = strtoupper(\App\Helpers\CRM\WaSenderContext::key($username));
        if ($loginKey === '') {
            $loginKey = strtoupper(preg_replace('/[^0-9]/', '', $username) ?? '');
        }

        return [
            'id' => (int) $row['id_user'],
            'username' => $loginKey,
            'name' => (string) ($row['nama_user'] ?? 'Driver'),
            'role' => 'driver',
            'code' => 'DR',
            'id_cabang' => (int) ($row['id_cabang'] ?? 0),
        ];
    }

    private function ensureLockTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $this->db($this->db_index)->query(
            "CREATE TABLE IF NOT EXISTS crm_device_locks (
                username VARCHAR(64) NOT NULL,
                device_id VARCHAR(64) NOT NULL,
                locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (username),
                KEY idx_device_id (device_id),
                KEY idx_last_seen (last_seen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $ready = true;
    }

    /**
     * Claim lock on login.
     * Same device: always OK. Other device: rejected until logout (or future admin unlock).
     */
    private function claimDeviceLock(string $username, string $deviceId): array
    {
        $this->ensureLockTable();
        $db = $this->db($this->db_index);

        $row = $db
            ->where('username', $username)
            ->get('crm_device_locks')
            ->row_array();

        $now = date('Y-m-d H:i:s');

        if (!$row) {
            $db->insert('crm_device_locks', [
                'username' => $username,
                'device_id' => $deviceId,
                'locked_at' => $now,
                'last_seen' => $now,
            ]);
            return ['ok' => true, 'message' => 'Lock acquired'];
        }

        if (hash_equals((string) $row['device_id'], $deviceId)) {
            $db->update('crm_device_locks', [
                'last_seen' => $now,
            ], ['username' => $username]);
            return ['ok' => true, 'message' => 'Lock refreshed'];
        }

        return [
            'ok' => false,
            'message' => 'ID dikunci di device lain. Logout dari device tersebut untuk membuka kunci.',
        ];
    }

    /**
     * Verify for WebSocket — only the locked device may connect.
     */
    private function verifyOrRefreshLock(string $username, string $deviceId): array
    {
        $this->ensureLockTable();
        $db = $this->db($this->db_index);

        $row = $db
            ->where('username', $username)
            ->get('crm_device_locks')
            ->row_array();

        $now = date('Y-m-d H:i:s');

        if (!$row) {
            return ['ok' => false, 'message' => 'Belum login / lock tidak ada'];
        }

        if (hash_equals((string) $row['device_id'], $deviceId)) {
            $db->update('crm_device_locks', [
                'last_seen' => $now,
            ], ['username' => $username]);
            return ['ok' => true, 'message' => 'OK'];
        }

        return ['ok' => false, 'message' => 'Device lain sedang mengunci ID ini'];
    }

    private function releaseDeviceLock(string $username, string $deviceId): void
    {
        $this->ensureLockTable();
        $db = $this->db($this->db_index);

        $row = $db
            ->where('username', $username)
            ->get('crm_device_locks')
            ->row_array();

        if ($row && hash_equals((string) $row['device_id'], $deviceId)) {
            $db->delete('crm_device_locks', ['username' => $username]);
        }
    }

    /**
     * Logout + release device lock
     * POST /CRM/Auth/logout
     * Body: { "username": "...", "device_id": "..." } (optional but recommended)
     */
    public function logout()
    {
        $body = $this->getBody();
        $deviceId = trim((string) ($body['device_id'] ?? ($_SESSION[$this->session_key]['device_id'] ?? '')));
        $username = strtoupper(trim((string) ($body['username'] ?? ($_SESSION[$this->session_key]['user']['username'] ?? ''))));

        try {
            if ($username !== '' && $deviceId !== '') {
                $this->releaseDeviceLock($username, $deviceId);
            }
        } catch (\Exception $e) {
            \Log::write("CRM Logout lock release error: " . $e->getMessage(), 'crm_error', 'Auth');
        }

        if (isset($_SESSION[$this->session_key])) {
            unset($_SESSION[$this->session_key]);
        }

        $this->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    /**
     * Check Session
     * GET /CRM/Auth/check
     */
    public function check()
    {
        if (isset($_SESSION[$this->session_key]) && $_SESSION[$this->session_key]['logged_in']) {
            $this->json([
                'success' => true,
                'authenticated' => true,
                'user' => $_SESSION[$this->session_key]['user']
            ]);
        } else {
            $this->json([
                'success' => false,
                'authenticated' => false
            ]);
        }
    }
}
