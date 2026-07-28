<?php

namespace App\Controllers\Jaggu_School;

/**
 * Auth — POST /Jaggu_School/Auth/login, GET check, POST logout
 */
class Auth extends JagguController
{
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

            unset($user['password']);
            $user['id'] = (int) $user['id'];

            $this->establishSession($user);
            $this->extendSession();
            $token = $this->issueAuthToken((int) $user['id']);

            $redirect = ($user['role'] ?? '') === 'parent' ? '/monitor' : '/today';

            $payload = [
                'success' => true,
                'message' => 'Login berhasil',
                'user' => $user,
                'redirect' => $redirect,
            ];
            if ($token !== null) {
                $payload['token'] = $token;
            }

            $this->json($payload);
        } catch (\Throwable $e) {
            $this->error('Login gagal: ' . $e->getMessage(), 500);
        }
    }

    public function check()
    {
        if (!$this->restoreAuth()) {
            $this->error('Unauthorized', 401);
        }

        $this->success([
            'user' => $this->currentUser(),
        ], 'OK');
    }

    public function logout()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $this->clearAuth();
        $this->success(null, 'Logout berhasil');
    }
}
