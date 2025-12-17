<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class Orders extends Controller
{
    private $db_index = 5;

    /**
     * GET - List all orders for salon
     */
    public function index()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Get filter from query string
            $status = $_GET['status'] ?? 'all';
            
            $sql = "SELECT o.*, c.nama as customer_name, c.no_hp as customer_phone, 
                    u.name as created_by_name
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN users u ON o.created_by = u.id
                    WHERE o.salon_id = ?";
            
            $params = [$salon_id];
            
            if ($status !== 'all') {
                $sql .= " AND o.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY o.order_date DESC";

            $orders = $this->db($this->db_index)
                ->query($sql, $params)
                ->result_array();

            // Decode JSON order_items
            foreach ($orders as &$order) {
                $order['order_items'] = json_decode($order['order_items'] ?? '[]', true);
            }

            $this->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            error_log("Orders index error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Create order
     */
    public function create()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['customer_id', 'order_items']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;
            
            if (!$salon_id || !$user_id) {
                $this->error('Session tidak valid', 401);
            }

            // Calculate total price from order_items
            $total_price = 0;
            foreach ($body['order_items'] as $item) {
                $total_price += $item['price'] ?? 0;
            }

            $order_items = json_encode($body['order_items']);

            $data = [
                'salon_id' => $salon_id,
                'customer_id' => $body['customer_id'],
                'order_date' => date('Y-m-d H:i:s'),
                'total_price' => $total_price,
                'status' => 'pending',
                'order_items' => $order_items,
                'notes' => $body['notes'] ?? null,
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->db($this->db_index)->insert('orders', $data);

            if ($id) {
                $data['id'] = $id;
                $data['order_items'] = json_decode($order_items, true);
                
                $this->json([
                    'success' => true,
                    'message' => 'Order berhasil dibuat',
                    'data' => $data
                ]);
            } else {
                $this->error('Gagal membuat order', 500);
            }
        } catch (\Exception $e) {
            error_log("Orders create error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Get order detail
     */
    public function detail($id)
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $order = $this->db($this->db_index)
                ->query("SELECT o.*, c.nama as customer_name, c.no_hp as customer_phone,
                        u.name as created_by_name
                        FROM orders o
                        JOIN customers c ON o.customer_id = c.id
                        JOIN users u ON o.created_by = u.id
                        WHERE o.id = ? AND o.salon_id = ?", [$id, $salon_id])
                ->row_array();

            if (!$order) {
                $this->error('Order tidak ditemukan', 404);
            }

            $order['order_items'] = json_decode($order['order_items'] ?? '[]', true);

            $this->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            error_log("Orders detail error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Update order status
     */
    public function updateStatus($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['status']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Verify ownership
            $existing = $this->db($this->db_index)
                ->get_where('orders', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Order tidak ditemukan', 404);
            }

            $data = [
                'status' => $body['status'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Set completed_at if status is completed
            if ($body['status'] === 'completed') {
                $data['completed_at'] = date('Y-m-d H:i:s');
                // Save payment info if provided
                if (isset($body['payment_method'])) {
                    $data['payment_method'] = $body['payment_method'];
                }
                if (isset($body['payment_notes'])) {
                    $data['payment_notes'] = $body['payment_notes'];
                }
                
                // Handle Split Payment / Amounts
                $data['pay_cash'] = isset($body['pay_cash']) ? (float)$body['pay_cash'] : 0;
                $data['pay_non_cash'] = isset($body['pay_non_cash']) ? (float)$body['pay_non_cash'] : 0;
            }

            $this->db($this->db_index)->update('orders', $data, ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Status order berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            error_log("Orders updateStatus error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Update work step status and assign worker
     */
    public function updateWorkStep($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['item_index', 'step_index', 'worker_id', 'status']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Get order
            $order = $this->db($this->db_index)
                ->get_where('orders', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$order) {
                $this->error('Order tidak ditemukan', 404);
            }

            $order_items = json_decode($order['order_items'], true);
            
            // Update work step
            $order_items[$body['item_index']]['work_steps'][$body['step_index']]['worker_id'] = $body['worker_id'];
            $order_items[$body['item_index']]['work_steps'][$body['step_index']]['status'] = $body['status'];

            $this->db($this->db_index)->update('orders', [
                'order_items' => json_encode($order_items),
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Langkah kerja berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            error_log("Orders updateWorkStep error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Delete order
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

            $existing = $this->db($this->db_index)
                ->get_where('orders', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Order tidak ditemukan', 404);
            }

            // Only allow delete if status is pending or cancelled
            if (!in_array($existing['status'], ['pending', 'cancelled'])) {
                $this->error('Hanya order dengan status pending atau cancelled yang bisa dihapus', 400);
            }

            $this->db($this->db_index)->delete('orders', ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Order berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            error_log("Orders delete error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }
}
