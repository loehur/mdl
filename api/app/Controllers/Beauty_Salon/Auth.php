<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

// Import Log helper (in global namespace)
// If Log is not in namespace, use \Log; otherwise check actual location

/**
 * Beauty Salon Auth Controller
 * Path: api/app/Controllers/Beauty_Salon/Auth.php
 */
class Auth extends Controller
{
    private $db_index = 5; // mdl_salon
    private $mdl_main_db = 0; // mdl_main
    private $session_key = 'salon_user_session';
    private $wa_server = 'http://127.0.0.1:8033';

    public function __construct()
    {
        // Session already started in init.php
        $this->handleCors();
    }

    /**
     * POST /Beauty_Salon/Auth/login
     */
    public function login()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['email', 'password']);

            $email = $body['email'];
            $password = $body['password'];

            // Log the attempt
            error_log("Login attempt for: " . $email);

            $user = $this->db($this->db_index)
                ->get_where('users', ['email' => $email], 1)
                ->row_array();

            if (!$user) {
                error_log("User not found: " . $email);
                $this->error('User tidak ditemukan dengan Email: ' . $email, 401);
            }

            error_log("User found: " . $user['name'] . " | Role: " . $user['role']);

            if (!password_verify($password, $user['password'])) {
                error_log("Password verification failed for: " . $email);
                $this->error('Kata sandi salah', 401);
            }

            // Create session directly for all users
            if ($user['is_active'] == 0) {
                $this->db($this->db_index)->update('users', [
                    'is_active' => 1
                ], ['id' => $user['id']]);
                $user['is_active'] = 1;
            }

            // Create session
            $_SESSION[$this->session_key] = [
                'user' => $user,
                'logged_in' => true
            ];

            // Remove sensitive data
            unset($user['password']);
            unset($user['otp']);

            error_log("Login successful for: " . $email . " | Role: " . $user['role']);

            $this->json([
                'success' => true,
                'message' => 'Login berhasil',
                'user' => $user,
                'redirect' => $user['role'] === 'cashier' ? '/order' : '/performance' // or dashboard? Performance is common for admin
            ]);
        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage() . " | " . $e->getTraceAsString());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /Beauty_Salon/Auth/verify-login
     */
    public function verify_login()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['email', 'otp']);
        
        $email = $body['email'];
        $otp = $body['otp'];

        $user = $this->db($this->db_index)
            ->get_where('users', ['email' => $email], 1)
            ->row_array();

        if (!$user) {
            $this->error('User tidak ditemukan', 404);
        }

        if ($user['otp'] !== $otp) {
            $this->error('Kode OTP salah', 400);
        }

        if (strtotime($user['otp_expiry']) < time()) {
            $this->error('Kode OTP kadaluarsa', 400);
        }

        // Clear OTP & Activate if not active
        $this->db($this->db_index)->update('users', [
            'otp' => null,
            'otp_expiry' => null,
            'is_active' => 1
        ], ['id' => $user['id']]);

        // Session
        $_SESSION[$this->session_key] = [
            'user' => $user,
            'logged_in' => true
        ];

        $this->json([
            'success' => true,
            'message' => 'Login berhasil',
            'user' => $user,
            'redirect' => '/dashboard'
        ]);
    }

    /**
     * POST /Beauty_Salon/Auth/register
     * Step 1: Validate, Create User (Inactive), Send OTP
     */
    public function register()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['name', 'email', 'password']);

        $name = $body['name'];
        $email = $body['email'];
        $password = $body['password'];
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        // Check if exists
        $exists = $this->db($this->db_index)
            ->get_where('users', ['email' => $email], 1)
            ->row_array();

        if ($exists) {
            // If exists but not active, maybe resend OTP?
            if (isset($exists['is_active']) && $exists['is_active'] == 0) {
                // Allows re-register or resend logic
                $user_id = $exists['id'];
            } else {
                $this->error('Email sudah terdaftar', 400);
            }
        }

        // Generate OTP
        $otp = sprintf('%06d', mt_rand(0, 999999));
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        if (!isset($user_id)) {
            // Generate unique salon_id
            $salon_id = $this->generateUniqueSalonId();
            
            // Insert new user
            $data = [
                'salon_id' => $salon_id,
                'name' => $name,
                'email' => $email,
                'password' => $hashed,
                'role' => 'admin',
                'otp' => $otp,
                'otp_expiry' => $otp_expiry,
                'is_active' => 0,
                'created_at' => $GLOBALS['now']
            ];
            $this->db($this->db_index)->insert('users', $data);
        } else {
            // Update existing inactive user
            $this->db($this->db_index)->update('users', [
                'name' => $name, // update name keys
                'password' => $hashed,
                'otp' => $otp,
                'otp_expiry' => $otp_expiry
            ], ['id' => $user_id]);
        }
        
        // Send OTP via Email
        $this->sendOtpEmail($email, $otp);

        $this->json([
            'success' => true, 
            'message' => 'OTP telah dikirim via Email',
            'otp_required' => true,
            'email' => $email
        ]);
    }

    /**
     * POST /Beauty_Salon/Auth/verify-register
     * Step 2: Verify OTP and Activate
     */
    public function verify_register()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['email', 'otp']);
        
        $email = $body['email'];
        $otp = $body['otp'];

        $user = $this->db($this->db_index)
            ->get_where('users', ['email' => $email], 1)
            ->row_array();

        if (!$user) {
            $this->error('User tidak ditemukan', 404);
        }

        if ($user['otp'] !== $otp) {
            $this->error('Kode OTP salah', 400);
        }

        if (strtotime($user['otp_expiry']) < time()) {
            $this->error('Kode OTP kadaluarsa', 400);
        }

        // Activate
        $this->db($this->db_index)->update('users', [
            'is_active' => 1,
            'otp' => null,
            'otp_expiry' => null
        ], ['id' => $user['id']]);

        // Auto login
        $_SESSION[$this->session_key] = [
            'user' => $user,
            'logged_in' => true
        ];

        $this->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'redirect' => '/dashboard',
            'user' => $user
        ]);
    }

    /**
     * POST /Beauty_Salon/Auth/forgot_password
     * Step 1: Send OTP for password reset
     */
    public function forgot_password()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['email']);
        
        $email = $body['email'];

        $user = $this->db($this->db_index)
            ->get_where('users', ['email' => $email], 1)
            ->row_array();

        if (!$user) {
            $this->error('Email tidak terdaftar', 404);
        }

        // Generate OTP for reset
        $otp = sprintf('%06d', mt_rand(0, 999999));
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Save OTP
        $this->db($this->db_index)->update('users', [
            'otp' => $otp,
            'otp_expiry' => $otp_expiry
        ], ['id' => $user['id']]);

        // Send OTP via Email
        $sent = $this->sendOtpEmail($email, $otp, "RESET PASSWORD");

        $this->json([
            'success' => true,
            'message' => 'Kode OTP untuk reset password telah dikirim via Email',
            'email' => $email
        ]);
    }

    /**
     * POST /Beauty_Salon/Auth/reset_password
     * Step 2: Verify OTP and Set New Password
     */
    public function reset_password()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['email', 'otp', 'new_password']);
        
        $email = $body['email'];
        $otp = $body['otp'];
        $new_password = $body['new_password'];

        if (strlen($new_password) < 6) {
            $this->error('Password minimal 6 karakter', 400);
        }

        $user = $this->db($this->db_index)
            ->get_where('users', ['email' => $email], 1)
            ->row_array();

        if (!$user) {
            $this->error('User tidak ditemukan', 404);
        }

        if ($user['otp'] !== $otp) {
            $this->error('Kode OTP salah', 400);
        }

        if (strtotime($user['otp_expiry']) < time()) {
            $this->error('Kode OTP kadaluarsa', 400);
        }

        // Update password
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $this->db($this->db_index)->update('users', [
            'password' => $hashed,
            'otp' => null,
            'otp_expiry' => null,
            'is_active' => 1 // Ensure user is active after reset
        ], ['id' => $user['id']]);

        $this->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui. Silakan login.'
        ]);
    }

    private function sendOtpEmail($email, $otp, $type = "LOGIN")
    {
        $subject = ($type === "RESET PASSWORD") ? "Reset Password OTP" : "Verification OTP";
        $message = ($type === "RESET PASSWORD") 
            ? "Your OTP for password reset is: $otp. Jangan berikan kode ini kepada siapapun."
            : "Your OTP for verification is: $otp. Jangan berikan kode ini kepada siapapun.";
            
        // Call mail server
        $url = 'https://mailserver.nalju.com/send-email';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'to' => $email,
            'subject' => $subject,
            'text' => $message,
            'html' => "<h3>$subject</h3><p>$message</p>"
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            \Log::write("Email OTP Error: $error", 'salon', 'Auth');
            return false;
        }
        return true;
    }

    /**
     * Generate Salon ID
     * Format: (year - 2024) + mdHis + 2 random digits
     * Example for 2025-12-17 17:35:06: 1 + 1217173506 + 45 = 112171735064 5
     * Note: This ID represents a salon, not a user. Multiple users can share the same salon_id.
     */
    private function generateUniqueSalonId()
    {
        // Year offset from 2024
        $yearOffset = date('Y') - 2024;
        
        // Month, day, Hour, minute, second
        $timestamp = date('mdHis');
        
        // 2 random digits
        $random = rand(0, 9) . rand(0, 9);
        
        // Combine
        $salon_id = $yearOffset . $timestamp . $random;
        
        return $salon_id;
    }
}
