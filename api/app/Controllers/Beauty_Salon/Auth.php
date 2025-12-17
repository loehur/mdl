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
            $this->validate($body, ['id_user', 'password']);

            $id_user = $body['id_user'];
            $password = $body['password'];

            // Log the attempt
            error_log("Login attempt for: " . $id_user);

            $user = $this->db($this->db_index)
                ->get_where('users', ['phone_number' => $id_user], 1)
                ->row_array();

            if (!$user) {
                error_log("User not found: " . $id_user);
                $this->error('User tidak ditemukan dengan nomor HP: ' . $id_user, 401);
            }

            error_log("User found: " . $user['name'] . " | Role: " . $user['role']);

            if (!password_verify($password, $user['password'])) {
                error_log("Password verification failed for: " . $id_user);
                $this->error('Kata sandi salah', 401);
            }

            // Check if user is cashier - they can login directly without OTP
            if ($user['role'] === 'cashier') {
                error_log("Cashier login - bypassing OTP");
                // Set active and create session directly
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

                error_log("Cashier login successful for: " . $id_user);

                $this->json([
                    'success' => true,
                    'message' => 'Login berhasil',
                    'user' => $user,
                    'redirect' => '/order'
                ]);
            }

            // For admin role: send OTP
            error_log("Admin login - sending OTP");
            // Generate OTP
            $otp = sprintf('%06d', mt_rand(0, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Update OTP to user
            $this->db($this->db_index)->update('users', [
                'otp' => $otp,
                'otp_expiry' => $otp_expiry
            ], ['id' => $user['id']]);

            // Send OTP
            $this->sendOtpWa($user['phone_number'], $otp);

            $this->json([
                'success' => true,
                'message' => 'Kode OTP login telah dikirim',
                'otp_required' => true,
                'phone_number' => $user['phone_number']
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
        $this->validate($body, ['phone_number', 'otp']);
        
        $phone = $body['phone_number'];
        $otp = $body['otp'];

        $user = $this->db($this->db_index)
            ->get_where('users', ['phone_number' => $phone], 1)
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
        $this->validate($body, ['name', 'phone_number', 'password']);

        $name = $body['name'];
        $phone = $body['phone_number'];
        $password = $body['password'];
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        // Check if exists
        $exists = $this->db($this->db_index)
            ->get_where('users', ['phone_number' => $phone], 1)
            ->row_array();

        if ($exists) {
            // If exists but not active, maybe resend OTP?
            if (isset($exists['is_active']) && $exists['is_active'] == 0) {
                // Allows re-register or resend logic
                $user_id = $exists['id'];
            } else {
                $this->error('Nomor telepon sudah terdaftar', 400);
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
                'phone_number' => $phone,
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
        
        // Send OTP via WA using main_notif session
        $this->sendOtpWa($phone, $otp);

        $this->json([
            'success' => true, 
            'message' => 'OTP telah dikirim via WhatsApp',
            'otp_required' => true,
            'phone_number' => $phone
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
        $this->validate($body, ['phone_number', 'otp']);
        
        $phone = $body['phone_number'];
        $otp = $body['otp'];

        $user = $this->db($this->db_index)
            ->get_where('users', ['phone_number' => $phone], 1)
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

    private function sendOtpWa($phone, $otp)
    {
        // Find main session from mdl_main
        $session = $this->db($this->mdl_main_db)
            ->get_where('wa_sessions', ['main_notif' => 1, 'wa_status' => 'active'], 1)
            ->row_array();

        if (!$session) {
            \Log::write("OTP Failed: No active main_notif session found.", 'salon', 'Auth');
            return false;
        }

        $session_id = $session['auth'];
        $message = "Salon OTP: *$otp*.\nJangan berikan kode ini kepada siapapun.";

        \Log::write("Sending OTP to $phone via Session $session_id...", 'salon', 'Auth');

        try {
            $url = $this->wa_server . '/send-message';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // Format number: remove 0 or 62 prefix, add country code if needed or just send
            // WA server expects clean number. Let's ensure it.
            // If phone starts with 0, replace with 62
            if (substr($phone, 0, 1) === '0') {
               $phone = '62' . substr($phone, 1);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'sessionId' => $session_id,
                'number' => $phone,
                'message' => $message
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                \Log::write("OTP Curl Error: $error", 'salon', 'Auth');
            } else {
                \Log::write("OTP Sent Result ($httpcode): $result", 'salon', 'Auth');
            }

        } catch (Exception $e) {
            \Log::write("OTP Exception: " . $e->getMessage(), 'salon', 'Auth');
        }
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
