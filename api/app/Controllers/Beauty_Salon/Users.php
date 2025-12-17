<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class Users extends Controller
{
    private $db_index = 5; // Using the salon database index

    public function index()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // List only cashier users (exclude active admin from this management list if desired, or show all?)
            // Usually "Users" menu is for Admin to manage Cashiers. So excluding Admin (self) or other admins is common?
            // Original code: role != 'admin'
            // New code: filter by salon_id AND role != 'admin'
            
            $users = $this->db($this->db_index)
                ->query("SELECT * FROM users WHERE salon_id = ? AND role != 'admin'", [$salon_id])
                ->result_array();

            // Remove passwords from output
            foreach ($users as &$u) {
                unset($u['password']);
                unset($u['otp']);
            }

            $this->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            error_log("Users index error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List all potential workers (Admins + Cashiers) for a salon
     */
    public function listWorkers()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $users = $this->db($this->db_index)
                ->query("SELECT id, name, role FROM users WHERE salon_id = ? AND is_active = 1 ORDER BY name ASC", [$salon_id])
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            error_log("Users listWorkers error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    public function create()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['name', 'phone_number', 'password', 'role']);

            $name = $body['name'];
            $phone = $body['phone_number'];
            $password = password_hash($body['password'], PASSWORD_BCRYPT);
            $role = $body['role']; // 'admin', 'cashier', 'customer'

            // Get salon_id from logged-in user (admin)
            $salon_id = null;
            
            // Debug log
            error_log("Session data: " . print_r($_SESSION, true));
            
            if (isset($_SESSION['salon_user_session']['user']['salon_id'])) {
                $salon_id = $_SESSION['salon_user_session']['user']['salon_id'];
                error_log("Salon ID from session: " . $salon_id);
            } else {
                error_log("Salon ID not found in session!");
            }

            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan. Silakan logout dan login ulang untuk refresh session.', 401);
            }

            // Check exists
            $exists = $this->db($this->db_index)
                ->get_where('users', ['phone_number' => $phone], 1)
                ->row_array();

            if ($exists) {
                $this->error('Nomor HP sudah terdaftar', 400);
            }

            $data = [
                'salon_id' => $salon_id, // Copy from admin
                'name' => $name,
                'phone_number' => $phone,
                'password' => $password,
                'role' => $role,
                'is_active' => 1, // Created by admin, assume active
                'created_at' => date('Y-m-d H:i:s')
            ];

            $inserted = $this->db($this->db_index)->insert('users', $data);

            if ($inserted) {
                $data['id'] = $inserted; // insert() returns insert_id
                unset($data['password']);
                $this->json([
                    'success' => true,
                    'message' => 'User berhasil dibuat',
                    'data' => $data
                ]);
            } else {
                $this->error('Gagal membuat user', 500);
            }
        } catch (\Exception $e) {
            error_log("Users create error: " . $e->getMessage());
            $this->error($e->getMessage(), 500);
        }
    }

    public function delete($id)
    {
        if (!$this->isPost()) { // Using POST for delete for simplicity with standard fetch
             // Or allow DELETE method if configured in Route
        }
        
        // Prevent deleting self? (Ideally check session user id)

        $deleted = $this->db($this->db_index)->delete('users', ['id' => $id]);
        
        if ($deleted) {
            $this->json(['success' => true, 'message' => 'User dihapus']);
        } else {
            $this->error('Gagal menghapus user', 500);
        }
    }
}
