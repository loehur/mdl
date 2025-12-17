<?php

/**
 * Admin Auth Controller
 * Handle login dan OTP verification untuk Admin
 */
class Auth extends Controller
{
    private $db_index = 2000;
    
    // ==============================
    // CONFIGURATION
    // ==============================
    private $session_key = 'mdl_admin_session';

    public function __construct()
    {
        session_start();
        $this->handleCors();
    }

    /**
     * Login - Step 1: Validate credentials and send OTP
     * POST /Admin/Auth/login
     */
    public function login()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['phone_number', 'password']);

        $phone = $body['phone_number'];
        $password = $body['password'];

        // Try to find user with different phone formats
        // Format 1: as entered (e.g., 08123456789)
        $user = $this->db($this->db_index)
            ->get_where('users', ['phone_number' => $phone], 1)
            ->row_array();

        // Format 2: with 62 prefix (e.g., 6281234567890)
        if (!$user) {
            $phone62 = preg_replace('/^0/', '62', $phone);
            $phone62 = preg_replace('/^\+/', '', $phone62);
            $user = $this->db($this->db_index)
                ->get_where('users', ['phone_number' => $phone62], 1)
                ->row_array();
        }

        // Format 3: without 0 prefix (e.g., 81234567890)
        if (!$user) {
            $phoneNoZero = preg_replace('/^0/', '', $phone);
            $user = $this->db($this->db_index)
                ->get_where('users', ['phone_number' => $phoneNoZero], 1)
                ->row_array();
        }

        if (!$user) {
            $this->error('Nomor telepon tidak terdaftar', 401);
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            $this->error('Kata sandi salah', 401);
        }

        // Check if user is admin
        $role = $user['role'] ?? null;
        if ($role !== 'admin') {
            $this->error('Anda tidak memiliki akses admin', 403);
        }

        // Generate OTP (6 digits)
        $otp = sprintf('%06d', mt_rand(0, 999999));
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Store OTP in database
        $this->db($this->db_index)->update(
            'users',
            [
                'otp' => $otp,
                'otp_expiry' => $otp_expiry
            ],
            ['id' => $user['id']]
        );

        // In development, return OTP directly (remove in production)
        $response = [
            'success' => true,
            'message' => 'OTP telah dikirim ke nomor Anda',
            'otp_required' => true,
        ];

        // DEV only - show OTP for testing
        if (defined('DEV_MODE') && DEV_MODE === true) {
            $response['dev_otp'] = $otp;
        } else {
            // For now, show OTP for development
            $response['dev_otp'] = $otp;
        }

        $this->json($response);
    }

    /**
     * Verify OTP - Step 2: Verify OTP and create session
     * POST /Admin/Auth/verify-otp
     */
    public function verify_otp()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['phone_number', 'otp']);

        $phone = $body['phone_number'];
        $otp = $body['otp'];

        // Try to find user with different phone formats
        // Format 1: as entered (e.g., 08123456789)
        $user = $this->db($this->db_index)
            ->get_where('users', ['phone_number' => $phone], 1)
            ->row_array();

        // Format 2: with 62 prefix (e.g., 6281234567890)
        if (!$user) {
            $phone62 = preg_replace('/^0/', '62', $phone);
            $phone62 = preg_replace('/^\+/', '', $phone62);
            $user = $this->db($this->db_index)
                ->get_where('users', ['phone_number' => $phone62], 1)
                ->row_array();
        }

        // Format 3: without 0 prefix (e.g., 81234567890)
        if (!$user) {
            $phoneNoZero = preg_replace('/^0/', '', $phone);
            $user = $this->db($this->db_index)
                ->get_where('users', ['phone_number' => $phoneNoZero], 1)
                ->row_array();
        }

        if (!$user) {
            $this->error('Nomor telepon tidak terdaftar', 401);
        }

        // Verify OTP
        if ($user['otp'] !== $otp) {
            $this->error('Kode OTP salah', 401);
        }

        // Check OTP expiry
        if (strtotime($user['otp_expiry']) < time()) {
            $this->error('Kode OTP sudah kadaluarsa', 401);
        }

        // Clear OTP
        $this->db($this->db_index)->update(
            'users',
            [
                'otp' => null,
                'otp_expiry' => null
            ],
            ['id' => $user['id']]
        );

        // Create session
        $_SESSION[$this->session_key] = [
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'phone' => $user['phone_number'],
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? 'admin',
            ],
            'logged_in' => true,
            'login_time' => time()
        ];

        // Return user data for frontend storage
        $userData = [
            'id' => $user['id'],
            'name' => $user['name'],
            'phone' => $user['phone_number'],
            'email' => $user['email'] ?? null,
            'role' => $user['role'] ?? 'admin',
        ];

        $this->json([
            'success' => true,
            'message' => 'Login berhasil',
            'user' => $userData,
            'redirect' => '/dashboard'
        ]);
    }

    /**
     * Logout
     * POST /Admin/Auth/logout
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
     * Check current session
     * GET /Admin/Auth/check
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
