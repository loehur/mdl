<?php

namespace App\Controllers\Investasi;

/**
 * Auth — POST /Investasi/Auth/login, GET /Investasi/Auth/check, POST /Investasi/Auth/logout
 */
class Auth extends InvestasiController
{
    /** @var string[] */
    private $allowedEmails = [
        'loehur@gmail.com',
        'neliarnisglory@gmail.com',
    ];

    public function login()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['email', 'password']);

            $email = strtolower(trim($body['email']));
            if (!in_array($email, $this->allowedEmails, true)) {
                $this->error('Email atau kata sandi salah', 401);
            }

            $user = $this->db($this->db_index)
                ->get_where('users', ['email' => $email], 1)
                ->row_array();

            if (!$user || !password_verify($body['password'], $user['password'])) {
                $this->error('Email atau kata sandi salah', 401);
            }

            if ((int) $user['is_active'] !== 1) {
                $this->error('Akun tidak aktif', 403);
            }

            unset($user['password']);

            $this->establishSession($user);
            $this->extendSession();
            $token = $this->issueAuthToken((int) $user['id']);

            $payload = [
                'success' => true,
                'message' => 'Login berhasil',
                'user' => $user,
                'redirect' => '/dashboard',
            ];
            if ($token !== null) {
                $payload['token'] = $token;
            }

            $this->json($payload);
        } catch (\Throwable $e) {
            $this->error('Login gagal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /Investasi/Auth/ping — cek koneksi DB (debug deploy)
     */
    public function ping()
    {
        try {
            $db = $this->db($this->db_index);
            $config = \DBC::getDbConfig($this->db_index);
            $db->query('SELECT 1 AS ok');
            $hasUsers = $this->db($this->db_index)
                ->query("SHOW TABLES LIKE 'users'")
                ->row_array();

            $this->success([
                'database' => $config['db'],
                'mode' => \Env::MODE,
                'users_table' => (bool) $hasUsers,
            ], 'Koneksi database OK');
        } catch (\Throwable $e) {
            $this->error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function check()
    {
        if (!$this->restoreAuth()) {
            $this->error('Unauthorized', 401);
        }

        $this->success(['user' => $this->currentUser()], 'Session aktif');
    }

    public function logout()
    {
        $this->revokeAuthToken();
        unset($_SESSION[$this->session_key]);
        $this->success(null, 'Logout berhasil');
    }
}
