<?php

namespace App\Controllers\Investasi;

/**
 * Auth — POST /Investasi/Auth/login, GET /Investasi/Auth/check, POST /Investasi/Auth/logout
 */
class Auth extends InvestasiController
{
    public function login()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['email', 'password']);

        $user = $this->db($this->db_index)
            ->get_where('users', ['email' => trim($body['email'])], 1)
            ->row_array();

        if (!$user || !password_verify($body['password'], $user['password'])) {
            $this->error('Email atau kata sandi salah', 401);
        }

        if ((int) $user['is_active'] !== 1) {
            $this->error('Akun tidak aktif', 403);
        }

        unset($user['password']);

        $_SESSION[$this->session_key] = [
            'user' => $user,
            'logged_in' => true,
        ];

        $this->json([
            'success' => true,
            'message' => 'Login berhasil',
            'user' => $user,
            'redirect' => '/dashboard',
        ]);
    }

    public function check()
    {
        if (empty($_SESSION[$this->session_key]['logged_in'])) {
            $this->error('Unauthorized', 401);
        }

        $user = $this->currentUser();
        $this->success(['user' => $user], 'Session aktif');
    }

    public function logout()
    {
        unset($_SESSION[$this->session_key]);
        $this->success(null, 'Logout berhasil');
    }
}
