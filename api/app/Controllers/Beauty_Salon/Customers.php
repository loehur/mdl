<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class Customers extends Controller
{
    private $db_index = 5;

    /**
     * GET - List all customers for salon
     */
    public function index()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $customers = $this->db($this->db_index)
                ->query("SELECT * FROM customers WHERE salon_id = ? ORDER BY nama ASC", [$salon_id])
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $customers
            ]);
        } catch (\Exception $e) {
            error_log("Customers index error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Create customer
     */
    public function create()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['nama', 'no_hp']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $data = [
                'salon_id' => $salon_id,
                'nama' => $body['nama'],
                'no_hp' => $body['no_hp'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->db($this->db_index)->insert('customers', $data);

            if ($id) {
                $data['id'] = $id;
                $this->json([
                    'success' => true,
                    'message' => 'Pelanggan berhasil ditambahkan',
                    'data' => $data
                ]);
            } else {
                $this->error('Gagal menambahkan pelanggan', 500);
            }
        } catch (\Exception $e) {
            error_log("Customers create error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Update customer
     */
    public function update($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['nama', 'no_hp']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Verify ownership
            $existing = $this->db($this->db_index)
                ->get_where('customers', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Pelanggan tidak ditemukan', 404);
            }

            $data = [
                'nama' => $body['nama'],
                'no_hp' => $body['no_hp'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db($this->db_index)->update('customers', $data, ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Data pelanggan berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            error_log("Customers update error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Delete customer
     */
    public function delete($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Verify ownership
            $existing = $this->db($this->db_index)
                ->get_where('customers', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Pelanggan tidak ditemukan', 404);
            }

            // Check if customer is used in any orders
            $usedInOrders = $this->db($this->db_index)
                ->query("
                    SELECT COUNT(*) as count 
                    FROM orders 
                    WHERE salon_id = ? 
                    AND customer_id = ?
                ", [$salon_id, $id])
                ->row_array();

            if ($usedInOrders && $usedInOrders['count'] > 0) {
                $this->error('Pelanggan tidak dapat dihapus karena sudah memiliki ' . $usedInOrders['count'] . ' order', 400);
            }

            $this->db($this->db_index)->delete('customers', ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Pelanggan berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            error_log("Customers delete error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }
}
