<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class WorkStep extends Controller
{
    private $db_index = 5;

    /**
     * GET - List all work steps for salon
     */
    public function index()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $steps = $this->db($this->db_index)
                ->query("SELECT * FROM work_step WHERE salon_id = ? ORDER BY id ASC", [$salon_id])
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $steps
            ]);
        } catch (\Exception $e) {
            error_log("WorkStep index error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Create work step
     */
    public function create()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['name', 'fee']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $data = [
                'salon_id' => $salon_id,
                'name' => $body['name'],
                'fee' => $body['fee'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->db($this->db_index)->insert('work_step', $data);

            if ($id) {
                $data['id'] = $id;
                $this->json([
                    'success' => true,
                    'message' => 'Langkah kerja berhasil ditambahkan',
                    'data' => $data
                ]);
            } else {
                $this->error('Gagal menambahkan langkah kerja', 500);
            }
        } catch (\Exception $e) {
            error_log("WorkStep create error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Update work step
     */
    public function update($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['name', 'fee']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Verify ownership
            $existing = $this->db($this->db_index)
                ->get_where('work_step', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Langkah kerja tidak ditemukan', 404);
            }

            $data = [
                'name' => $body['name'],
                'fee' => $body['fee'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db($this->db_index)->update('work_step', $data, ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Langkah kerja berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            error_log("WorkStep update error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Delete work step
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
                ->get_where('work_step', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Langkah kerja tidak ditemukan', 404);
            }

            // Check if work step is used in products
            $usedInProducts = $this->db($this->db_index)
                ->query("
                    SELECT COUNT(*) as count 
                    FROM products 
                    WHERE salon_id = ? 
                    AND JSON_CONTAINS(work_steps, ?, '$.work_step_id')
                ", [$salon_id, $id])
                ->row_array();

            if ($usedInProducts && $usedInProducts['count'] > 0) {
                $this->error('Langkah kerja tidak dapat dihapus karena digunakan di ' . $usedInProducts['count'] . ' produk', 400);
            }

            // Check if work step is used in order workers
            $usedInOrders = $this->db($this->db_index)
                ->query("
                    SELECT COUNT(*) as count 
                    FROM order_workers 
                    WHERE work_step_id = ?
                ", [$id])
                ->row_array();

            if ($usedInOrders && $usedInOrders['count'] > 0) {
                $this->error('Langkah kerja tidak dapat dihapus karena sudah ditugaskan di ' . $usedInOrders['count'] . ' layanan order', 400);
            }

            $this->db($this->db_index)->delete('work_step', ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Langkah kerja berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            error_log("WorkStep delete error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }
}
