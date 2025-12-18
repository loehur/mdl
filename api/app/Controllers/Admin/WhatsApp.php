<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin WhatsApp Controller
 * Handle WhatsApp session management
 */
class WhatsApp extends Controller
{
    private $db_index = 0;
    private $wa_server = 'http://127.0.0.1:8033';

    public function __construct()
    {
        // Session already started in init.php
        $this->handleCors();
    }

    /**
     * Create new WhatsApp session
     * POST /Admin/WhatsApp/create-session
     */
    public function create_session()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $device_name = $body['device_name'] ?? 'Default Device';
        $user_id = $body['user_id'] ?? null;

        if (!$user_id) {
            $this->error('User ID diperlukan', 400);
        }

        // Generate unique session ID
        $session_id = uniqid('wa_');

        try {
            // Create session on WA server
            $response = $this->waRequest('/create-session', [
                'sessionId' => $session_id
            ]);

            if (!$response || !isset($response['status']) || $response['status'] !== true) {
                // If session already exists, we can use it or fail. 
                // Let's assume conflict means we retry or just use it (if we use uniqid this is rare)
                // But if we use uniqid, conflict is impossible unless collision.
                
                // If error is other than "already exists", throw.
                if (isset($response['message']) && strpos($response['message'], 'already exists') === false) {
                     $error_msg = $response['message'] ?? 'Gagal membuat session di server WA';
                     $this->error($error_msg, 500);
                }
            }

            // Save to database
            $this->db($this->db_index)->insert('wa_sessions', [
                'user_id' => $user_id,
                'auth' => $session_id,
                'device_name' => $device_name,
                'wa_status' => 'pending',
                'created_at' => $GLOBALS['now']
            ]);

            $this->json([
                'success' => true,
                'session_id' => $session_id,
                'message' => 'Session berhasil dibuat'
            ]);
        } catch (Exception $e) {
            $this->error('Tidak dapat terhubung ke WA Server (' . $this->wa_server . '). Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check session status and get QR
     * POST /Admin/WhatsApp/cek-status
     */
    public function cek_status()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $session_id = $body['session_id'] ?? null;
        $user_id = $body['user_id'] ?? null;

        if (!$session_id) {
            $this->error('Session ID diperlukan', 400);
        }

        try {
            // 1. Cek status session
            $response = null;
            try {
                $response = $this->waRequest('/cek-status', [
                    'sessionId' => $session_id
                ]);
            } catch (Exception $e) {
                // Jika error 404/500, response null
                $response = null;
            }

            // 2. Jika session tidak ditemukan di Node server (misal restart server), buat ulang
            if (!$response || (isset($response['status']) && $response['status'] === false && strpos($response['message'] ?? '', 'not found') !== false)) {
                try {
                    $this->waRequest('/create-session', [
                        'sessionId' => $session_id
                    ]);
                    usleep(500000); // 0.5s wait
                    $response = $this->waRequest('/cek-status', [
                        'sessionId' => $session_id
                    ]);
                } catch (Exception $e) {
                    // Ignore
                }
            }

            // REMOVED: Aggressive auto-reset logic that kills session during "Connecting" state.
            // When user scans QR, qr_ready becomes false while connecting. 
            // Previous logic interpreted (logged_in=false && qr_ready=false) as STUCK, and reset it.
            // This interrupted the login process.

            $logged_in = $response['logged_in'] ?? false;
            $qr_ready = $response['qr_ready'] ?? false;
            $qr_string = $response['qr_string'] ?? '';

            // Update status in database
            if ($user_id) {
                $status = $logged_in ? 'active' : 'pending';
                // Only update if status changes to avoid unnecessary writes? 
                // Or just update always to keep heartbeat if we add last_seen.
                // For now keep simple.
                $this->db($this->db_index)->update(
                    'wa_sessions',
                    ['wa_status' => $status],
                    ['auth' => $session_id, 'user_id' => $user_id]
                );
            }

            $this->json([
                'success' => true,
                'logged_in' => $logged_in,
                'qr_ready' => $qr_ready,
                'qr_string' => $qr_string
            ]);
        } catch (Exception $e) {
            $this->json([
                'success' => true,
                'logged_in' => false,
                'qr_ready' => false,
                'qr_string' => '',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * List saved sessions
     * GET /Admin/WhatsApp/list-saved
     */
    public function list_saved()
    {
        $user_id = $this->query('user_id');

        if (!$user_id) {
            $this->error('User ID diperlukan', 400);
        }

        try {
            $sessions = $this->db($this->db_index)
                ->get_where('wa_sessions', ['user_id' => $user_id])
                ->result_array();

            $this->json([
                'success' => true,
                'sessions' => $sessions
            ]);
        } catch (Exception $e) {
            $this->json([
                'success' => true,
                'sessions' => []
            ]);
        }
    }

    /**
     * Login existing session
     * POST /Admin/WhatsApp/login-session
     */
    public function login_session()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $auth = $body['auth'] ?? null;
        $user_id = $body['user_id'] ?? null;
        $device_name = $body['device_name'] ?? '';

        if (!$auth) {
            $this->error('Auth diperlukan', 400);
        }

        try {
            $response = $this->waRequest('/login-session', [
                'sessionId' => $auth
            ]);

            if ($user_id) {
                $this->db($this->db_index)->update(
                    'wa_sessions',
                    ['wa_status' => 'pending'],
                    ['auth' => $auth, 'user_id' => $user_id]
                );
            }

            $this->json([
                'success' => true,
                'message' => 'Login session dimulai'
            ]);
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete session
     * POST /Admin/WhatsApp/delete-session
     */
    public function delete_session()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $auth = $body['auth'] ?? null;
        $user_id = $body['user_id'] ?? null;

        if (!$auth || !$user_id) {
            $this->error('Auth dan User ID diperlukan', 400);
        }

        try {
            // Delete from WA server
            $this->waRequest('/delete-session', [
                'sessionId' => $auth
            ]);
        } catch (Exception $e) {
            // Ignore WA server errors
        }

        // Delete from database
        $this->db($this->db_index)->delete('wa_sessions', [
            'auth' => $auth,
            'user_id' => $user_id
        ]);

        $this->json([
            'success' => true,
            'message' => 'Session dihapus'
        ]);
    }

    /**
     * Set device as main notification sender
     * POST /Admin/WhatsApp/set-main
     */
    public function set_main()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $auth = $body['auth'] ?? null;
        $user_id = $body['user_id'] ?? null;

        if (!$auth || !$user_id) {
            $this->error('Auth dan User ID diperlukan', 400);
        }

        // 1. Reset all main_notif to 0 for this user
        $this->db($this->db_index)->update(
            'wa_sessions', 
            ['main_notif' => 0], 
            ['user_id' => $user_id]
        );

        // 2. Set selected auth as main_notif 1
        $this->db($this->db_index)->update(
            'wa_sessions', 
            ['main_notif' => 1], 
            ['auth' => $auth, 'user_id' => $user_id]
        );

        $this->json([
            'success' => true,
            'message' => 'Perangkat utama notifikasi berhasil diubah'
        ]);
    }

    /**
     * Helper: Make request to WA server
     */
    private function waRequest($endpoint, $data = [])
    {
        $url = $this->wa_server . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception($error);
        }
        
        return json_decode($response, true);
    }
}
