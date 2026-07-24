<?php

namespace App\Controllers\WaDesk;

/**
 * Auth — register admin+tenant, login, check, logout
 */
class Auth extends WaDeskController
{
    /** POST /WaDesk/Auth/register — buat tenant + admin pertama */
    public function register()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['org_name', 'name', 'email', 'password']);

            $email = strtolower(trim($body['email']));
            $orgName = trim($body['org_name']);
            $name = trim($body['name']);
            $password = (string) $body['password'];

            if (strlen($password) < 6) {
                $this->error('Password minimal 6 karakter', 400);
            }

            $db = $this->db($this->db_index);
            $exists = $db->get_where('users', ['email' => $email], 1)->row_array();
            if ($exists) {
                $this->error('Email sudah terdaftar', 409);
            }

            $tenantId = (int) $db->insert('tenants', ['name' => $orgName]);
            if ($tenantId <= 0) {
                $this->error('Gagal membuat tenant', 500);
            }

            $userId = (int) $db->insert('users', [
                'tenant_id' => $tenantId,
                'team_id' => null,
                'email' => $email,
                'name' => $name,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'is_active' => 1,
            ]);
            if ($userId <= 0) {
                $this->error('Gagal membuat user admin', 500);
            }

            $db->update('tenants', ['admin_user_id' => $userId], ['id' => $tenantId]);

            $user = $this->publicUser([
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'admin',
                'tenant_id' => $tenantId,
                'team_id' => null,
            ]);

            $this->establishSession($user);
            $this->extendSession();
            $token = $this->issueAuthToken($userId);

            $this->success([
                'user' => $user,
                'token' => $token,
            ], 'Registrasi berhasil');
        } catch (\Throwable $e) {
            $this->error('Registrasi gagal: ' . $e->getMessage(), 500);
        }
    }

    public function login()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['email', 'password']);

            $email = strtolower(trim($body['email']));
            $user = $this->db($this->db_index)
                ->get_where('users', ['email' => $email], 1)
                ->row_array();

            if (!$user || !password_verify($body['password'], $user['password'])) {
                $this->error('Email atau kata sandi salah', 401);
            }
            if ((int) $user['is_active'] !== 1) {
                $this->error('Akun tidak aktif', 403);
            }

            $public = $this->publicUser($user);
            $this->establishSession($public);
            $this->extendSession();
            $token = $this->issueAuthToken((int) $user['id']);

            $this->success([
                'user' => $public,
                'token' => $token,
            ], 'Login berhasil');
        } catch (\Throwable $e) {
            $this->error('Login gagal: ' . $e->getMessage(), 500);
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

    public function ping()
    {
        try {
            $this->db($this->db_index)->query('SELECT 1 AS ok');
            $config = \DBC::getDbConfig($this->db_index);
            $tables = $this->db($this->db_index)
                ->query("SHOW TABLES LIKE 'users'")
                ->row_array();

            $this->success([
                'database' => $config['db'],
                'mode' => \Env::MODE,
                'users_table' => (bool) $tables,
            ], 'OK');
        } catch (\Throwable $e) {
            $this->error('Database error: ' . $e->getMessage(), 500);
        }
    }
}
