<?php

namespace App\Controllers\WaDesk;

/**
 * Auth — login, check, logout
 */
class Auth extends WaDeskController
{
    /** POST /WaDesk/Auth/register — pendaftaran publik dinonaktifkan. */
    public function register()
    {
        $this->error('Pendaftaran admin saat ini ditutup', 403);
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

            $public = $this->loadPublicUser((int) $user['id']);
            if (!$public) {
                $this->error('Akun tidak aktif', 403);
            }
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
        $user = $this->currentUser();
        $fresh = $this->loadPublicUser((int) ($user['id'] ?? 0));
        if ($fresh) {
            $this->establishSession($fresh);
            $user = $fresh;
        }
        $this->success(['user' => $user], 'Session aktif');
    }

    /** POST /WaDesk/Auth/joinTeam — admin bergabung ke team untuk operasional WA */
    public function joinTeam()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $teamId = (int) ($this->getBody()['team_id'] ?? 0);
        if ($teamId <= 0) {
            $this->error('team_id wajib', 400);
        }

        $team = $this->db($this->db_index)->query(
            "SELECT id, name FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$teamId, (int) $admin['tenant_id']]
        )->row_array();
        if (!$team) {
            $this->error('Team tidak ditemukan', 404);
        }

        $this->db($this->db_index)->update('users', [
            'team_id' => $teamId,
        ], ['id' => (int) $admin['id']]);

        $public = $this->loadPublicUser((int) $admin['id']);
        $this->establishSession($public);

        $this->success(['user' => $public], 'Bergabung ke team ' . $team['name']);
    }

    /** POST /WaDesk/Auth/leaveTeam — admin keluar dari team operasional */
    public function leaveTeam()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $this->db($this->db_index)->update('users', [
            'team_id' => null,
        ], ['id' => (int) $admin['id']]);

        $public = $this->loadPublicUser((int) $admin['id']);
        $this->establishSession($public);

        $this->success(['user' => $public], 'Keluar dari team operasional');
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
