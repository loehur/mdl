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
            $this->validate($body, ['name', 'email', 'password', 'role']);

            $name = $body['name'];
            $email = $body['email'];
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
                ->get_where('users', ['email' => $email], 1)
                ->row_array();

            if ($exists) {
                $this->error('Email sudah terdaftar', 400);
            }

            $data = [
                'salon_id' => $salon_id, // Copy from admin
                'name' => $name,
                'email' => $email,
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
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $current_user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;
            
            if (!$salon_id || !$current_user_id) {
                $this->error('Unauthorized', 401);
            }

            // Prevent deleting self
            if ($id == $current_user_id) {
                $this->error('Tidak dapat menghapus akun sendiri', 400);
            }

            // Verify user belongs to salon
            $user = $this->db($this->db_index)
                ->get_where('users', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$user) {
                $this->error('User tidak ditemukan', 404);
            }

            // Check if user has created orders
            $hasOrders = $this->db($this->db_index)
                ->query("
                    SELECT COUNT(*) as count 
                    FROM orders 
                    WHERE created_by_user_id = ?
                ", [$id])
                ->row_array();

            if ($hasOrders && $hasOrders['count'] > 0) {
                $this->error('User tidak dapat dihapus karena telah membuat ' . $hasOrders['count'] . ' order', 400);
            }

            // Check if user assigned as worker in orders
            $assignedOrders = $this->db($this->db_index)
                ->query("
                    SELECT COUNT(*) as count 
                    FROM order_workers 
                    WHERE worker_id = ?
                ", [$id])
                ->row_array();

            if ($assignedOrders && $assignedOrders['count'] > 0) {
                $this->error('User tidak dapat dihapus karena sudah ditugaskan di ' . $assignedOrders['count'] . ' layanan', 400);
            }

            $deleted = $this->db($this->db_index)->delete('users', ['id' => $id]);
            
            if ($deleted) {
                $this->json(['success' => true, 'message' => 'User berhasil dihapus']);
            } else {
                $this->error('Gagal menghapus user', 500);
            }
        } catch (\Exception $e) {
            error_log("Users delete error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Change own password
     */
    public function changePassword()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['old_password', 'new_password']);

            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;
            
            if (!$user_id) {
                $this->error('Unauthorized', 401);
            }

            // Get current user
            $user = $this->db($this->db_index)
                ->get_where('users', ['id' => $user_id], 1)
                ->row_array();

            if (!$user) {
                $this->error('User tidak ditemukan', 404);
            }

            // Verify old password
            if (!password_verify($body['old_password'], $user['password'])) {
                $this->error('Password lama tidak sesuai', 400);
            }

            // Validate new password length
            if (strlen($body['new_password']) < 6) {
                $this->error('Password baru minimal 6 karakter', 400);
            }

            // Hash new password
            $new_password_hash = password_hash($body['new_password'], PASSWORD_BCRYPT);

            // Update password
            $updated = $this->db($this->db_index)->update('users', 
                ['password' => $new_password_hash, 'updated_at' => date('Y-m-d H:i:s')], 
                ['id' => $user_id]
            );

            if ($updated) {
                $this->json([
                    'success' => true,
                    'message' => 'Password berhasil diubah'
                ]);
            } else {
                $this->error('Gagal mengubah password', 500);
            }
        } catch (\Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }
}
