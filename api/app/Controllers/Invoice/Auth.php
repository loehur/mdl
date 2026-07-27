<?php

namespace App\Controllers\Invoice;

/**
 * Auth — POST /Invoice/Auth/login, GET /Invoice/Auth/check, POST /Invoice/Auth/logout
 */
class Auth extends InvoiceController
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

            if (empty($user['business_name'])) {
                $user['business_name'] = $this->defaultBusinessName;
            }

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
