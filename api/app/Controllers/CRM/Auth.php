<?php

namespace App\Controllers\CRM;

use App\Core\Controller;

/**
 * CRM Auth Controller
 * Simplified Login (Username + Password)
 * Menggunakan tabel: crm_user
 */
class Auth extends Controller
{
    private $db_index = 0;
    private $session_key = 'mdl_crm_session';

    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * Login (Simple)
     * POST /CRM/Auth/login
     * Body: { "username": "...", "password": "..." }
     */
    public function login()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['username']);

            $username = $body['username']; // This equals the old "ID"
            // $password = $body['password']; // Password removed as per request

            // Find user by username
            $user = $this->db($this->db_index)
                ->get_where('crm_users', ['username' => $username], 1)
                ->row_array();

            if (!$user) {
                $this->error('Username tidak ditemukan', 401);
            }

            // Verify password REMOVED
            // if (!password_verify($password, $user['password'])) {
            //     $this->error('Kata sandi salah', 401);
            // }

            // Create Session Data
            $userData = [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role'] ?? 'access', // admin, crew, driver
            ];

            // Set Session
            $_SESSION[$this->session_key] = [
                'user' => $userData,
                'logged_in' => true,
                'login_time' => time()
            ];

            \Log::write("CRM Login Success: " . $username, 'crm_auth', 'Auth');

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
     * Logout
     * POST /CRM/Auth/logout
     */
    public function logout()
    {
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
