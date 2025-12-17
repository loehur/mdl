<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class Products extends Controller
{
    private $db_index = 5;

    /**
     * GET - List all products for salon
     */
    public function index()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $products = $this->db($this->db_index)
                ->query("SELECT * FROM products WHERE salon_id = ? ORDER BY name ASC", [$salon_id])
                ->result_array();

            // Decode JSON work_steps
            foreach ($products as &$product) {
                $product['work_steps'] = json_decode($product['work_steps'] ?? '[]', true);
            }

            $this->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            error_log("Products index error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Create product
     */
    public function create()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['name', 'price']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Convert work_steps array to JSON
            $work_steps = isset($body['work_steps']) && is_array($body['work_steps']) 
                ? json_encode($body['work_steps']) 
                : json_encode([]);

            $data = [
                'salon_id' => $salon_id,
                'name' => $body['name'],
                'price' => $body['price'],
                'work_steps' => $work_steps,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->db($this->db_index)->insert('products', $data);

            if ($id) {
                $data['id'] = $id;
                $data['work_steps'] = json_decode($work_steps, true);
                
                $this->json([
                    'success' => true,
                    'message' => 'Produk berhasil ditambahkan',
                    'data' => $data
                ]);
            } else {
                $this->error('Gagal menambahkan produk', 500);
            }
        } catch (\Exception $e) {
            error_log("Products create error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Update product
     */
    public function update($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['name', 'price']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Verify ownership
            $existing = $this->db($this->db_index)
                ->get_where('products', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Produk tidak ditemukan', 404);
            }

            // Convert work_steps array to JSON
            $work_steps = isset($body['work_steps']) && is_array($body['work_steps']) 
                ? json_encode($body['work_steps']) 
                : json_encode([]);

            $data = [
                'name' => $body['name'],
                'price' => $body['price'],
                'work_steps' => $work_steps,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db($this->db_index)->update('products', $data, ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            error_log("Products update error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Delete product
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
                ->get_where('products', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Produk tidak ditemukan', 404);
            }

            $this->db($this->db_index)->delete('products', ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            error_log("Products delete error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }
}
