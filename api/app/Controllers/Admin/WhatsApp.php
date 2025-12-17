<?php

/**
 * Admin WhatsApp Controller
 * Handle WhatsApp session management
 */
class WhatsApp extends Controller
{
    private $db_index = 2000;
    private $wa_server = 'http://127.0.0.1:8033';

    public function __construct()
    {
        session_start();
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

        // Generate unique session ID (timestamp only)
        $session_id = (string) time();

        try {
            // Create session on WA server - WA server expects sessionId
            $response = $this->waRequest('/create-session', [
                'sessionId' => $session_id
            ]);

            if (!$response || !isset($response['status']) || $response['status'] !== true) {
                $error_msg = $response['message'] ?? 'Gagal membuat session di server WA';
                $this->error($error_msg, 500);
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
            try {
                $response = $this->waRequest('/cek-status', [
                    'sessionId' => $session_id
                ]);
            } catch (Exception $e) {
                // Jika error (misal 404), asumsi session mati/hilang di server
                $response = null;
            }

            // 2. Jika session tidak ditemukan ATAU stuck (tidak login & tidak ada QR)
            // Maka kita reset session agar generate QR baru
            $is_stuck = false;
            if ($response && 
                (isset($response['status']) && $response['status'] === true) && 
                empty($response['logged_in']) && 
                empty($response['qr_ready'])
            ) {
                $is_stuck = true;
            }

            if (!$response || (isset($response['status']) && $response['status'] === false) || $is_stuck) {
                try {
                    // Gunakan /reset-session jika stuck, atau /create-session jika belum ada
                    $endpoint = $is_stuck ? '/reset-session' : '/create-session';
                    
                    $this->waRequest($endpoint, [
                        'sessionId' => $session_id
                    ]);
                    // Beri waktu sedikit untuk inisiasi
                    usleep(1000000); // 1s wait
                    
                    // Cek status lagi setelah reset/create
                    $response = $this->waRequest('/cek-status', [
                        'sessionId' => $session_id
                    ]);
                } catch (Exception $e) {
                    // Masih gagal, return default
                }
            }

            $logged_in = $response['logged_in'] ?? false;
            $qr_ready = $response['qr_ready'] ?? false;
            $qr_string = $response['qr_string'] ?? '';

            // Update status in database
            if ($user_id) {
                $status = $logged_in ? 'active' : 'pending';
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
