<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class Orders extends Controller
{
    private $db_index = 4;

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
            $start_date = $_GET['start_date'] ?? null;
            $end_date = $_GET['end_date'] ?? null;
            $date_by = $_GET['date_by'] ?? null; // 'completed_at' | 'order_date' | 'created_at'
            
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
            
            // Filter by date range (untuk halaman Performance: berdasarkan tanggal selesai order)
            if ($start_date && $end_date) {
                $dateColumn = ($date_by === 'order_date') ? 'o.order_date' : (($date_by === 'created_at') ? 'o.created_at' : 'o.completed_at');
                $sql .= " AND o.status = 'completed' AND {$dateColumn} IS NOT NULL AND DATE({$dateColumn}) >= ? AND DATE({$dateColumn}) <= ?";
                $params[] = $start_date;
                $params[] = $end_date;
            }
            
            $sql .= " ORDER BY o.completed_at DESC, o.order_date DESC";

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
                'booking_date' => $body['booking_date'] ?? null,
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
                
                // Handle voucher redemption if provided
                $voucherRedeemed = false;
                if (isset($body['voucher']) && !empty($body['voucher']['voucher_id'])) {
                    $voucherInfo = $body['voucher'];
                    
                    // Update voucher as used
                    $this->db($this->db_index)->update('customer_vouchers', [
                        'status' => 'used',
                        'used_at' => date('Y-m-d H:i:s'),
                        'used_in_order_id' => $id,
                        'redeemed_product_name' => $voucherInfo['product_name'] ?? null,
                        'redeemed_product_value' => $voucherInfo['product_value'] ?? null
                    ], ['id' => $voucherInfo['voucher_id'], 'salon_id' => $salon_id]);
                    
                    $voucherRedeemed = true;
                }
                
                $this->json([
                    'success' => true,
                    'message' => $voucherRedeemed ? 'Order berhasil dibuat dengan voucher!' : 'Order berhasil dibuat',
                    'voucher_redeemed' => $voucherRedeemed,
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
     * POST - Update existing order (replace items data)
     */
    public function update($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            // Validate basic inputs (similar to create)
            $this->validate($body, ['customer_id', 'order_items']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Session tidak valid', 401);
            }

            // Verify existence
            $existing = $this->db($this->db_index)
                ->get_where('orders', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$existing) {
                $this->error('Order tidak ditemukan', 404);
            }
            
            // Allow update only if not completed/cancelled (optional business rule)
            if (in_array($existing['status'], ['completed', 'cancelled'])) {
                 $this->error('Order tidak dapat diubah karena status sudah ' . $existing['status'], 400);
            }

            // Calculate new total price
            $total_price = 0;
            foreach ($body['order_items'] as $item) {
                $total_price += $item['price'] ?? 0;
            }

            // Prepare Order Items
            // The frontend sends full list of items. 
            // We should use that to replace the current JSON.
            // Note: If we need to preserve some state (like worker_id in steps),
            // the frontend must have sent it back. We assume frontend sends full hydrated object.
            
            $order_items = json_encode($body['order_items']);

            $data = [
                'customer_id' => $body['customer_id'],
                'total_price' => $total_price,
                'booking_date' => $body['booking_date'] ?? null,
                'order_items' => $order_items,
                'notes' => $body['notes'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $user_id
            ];

            $this->db($this->db_index)->update('orders', $data, ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Order berhasil diperbarui',
                'data' => array_merge($existing, $data, ['order_items' => $body['order_items']])
            ]);
            
        } catch (\Exception $e) {
            error_log("Orders update error: " . $e->getMessage());
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

            // Record inventory sales if order contains inventory items
            if ($body['status'] === 'completed') {
                $order_items = json_decode($existing['order_items'], true);
                foreach ($order_items as $item) {
                    // Check if this is an inventory item (has item_id field)
                    if (isset($item['item_id']) && !empty($item['item_id'])) {
                        // Insert into inventory_sales
                        $this->db($this->db_index)->insert('inventory_sales', [
                            'salon_id' => $salon_id,
                            'order_id' => $id,
                            'item_id' => $item['item_id'],
                            'item_name' => $item['product_name'] ?? $item['item_name'] ?? 'Unknown',
                            'qty' => $item['qty'] ?? 1,
                            'sell_price' => $item['price'] ?? 0,
                            'buy_price' => $item['buy_price'] ?? null,
                            'total_price' => ($item['price'] ?? 0) * ($item['qty'] ?? 1),
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                
                // Check and grant voucher (loyalty program: every 10 completed orders = 1 voucher)
                $vouchersController = new Vouchers();
                $voucherResult = $vouchersController->checkAndGrantVoucher($existing['customer_id'], $id);
            }

            // Build response
            $response = [
                'success' => true,
                'message' => 'Status order berhasil diperbarui'
            ];
            
            // Add voucher info if granted
            if (isset($voucherResult) && $voucherResult['voucher_granted']) {
                $response['voucher_granted'] = true;
                $response['voucher_message'] = $voucherResult['message'];
                $response['voucher_code'] = $voucherResult['voucher_code'];
            }

            $this->json($response);
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
     * POST - Update item price
     */
    public function updateItemPrice($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['item_index', 'price']);

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
            
            if ($order['status'] === 'completed') {
                $this->error('Tidak dapat mengubah harga pada order yang sudah selesai', 400);
            }
            if ($order['status'] === 'cancelled') {
                 $this->error('Tidak dapat mengubah harga pada order yang sudah dibatalkan', 400);
            }

            $order_items = json_decode($order['order_items'], true);
            
            if (!isset($order_items[$body['item_index']])) {
                 $this->error('Item tidak ditemukan', 404);
            }

            // Update price
            $order_items[$body['item_index']]['price'] = (float)$body['price'];
            
            // Recalculate total_price
            $total_price = 0;
            foreach ($order_items as $item) {
                $total_price += ($item['price'] ?? 0);
            }

            $this->db($this->db_index)->update('orders', [
                'order_items' => json_encode($order_items),
                'total_price' => $total_price,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Harga item berhasil diperbarui',
                'new_total' => $total_price
            ]);
        } catch (\Exception $e) {
            error_log("Orders updateItemPrice error: " . $e->getMessage());
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

    /**
     * POST - Sync fee in orders with latest work_step settings
     * Only allowed for current month and last month
     * 
     * Body: { start_date, end_date }
     */
    public function syncFee()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['start_date', 'end_date']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $startDate = $body['start_date'];
            $endDate = $body['end_date'];

            // Validate: Only allow current month and last month
            $now = new \DateTime();
            $currentMonthStart = new \DateTime($now->format('Y-m-01'));
            $lastMonthStart = (clone $currentMonthStart)->modify('-1 month');
            $filterStart = new \DateTime($startDate);

            if ($filterStart < $lastMonthStart) {
                $this->error('Sinkronisasi fee hanya dapat dilakukan untuk order di bulan ini dan bulan lalu', 400);
            }

            // Get latest work step fees
            $workSteps = $this->db($this->db_index)
                ->query("SELECT id, name, fee FROM work_step WHERE salon_id = ?", [$salon_id])
                ->result_array();

            // Create lookup map: step_id => fee, step_name => fee
            $feeByStepId = [];
            $feeByStepName = [];
            foreach ($workSteps as $ws) {
                $feeByStepId[$ws['id']] = (float)$ws['fee'];
                $feeByStepName[$ws['name']] = (float)$ws['fee'];
            }

            // Get orders in date range
            $orders = $this->db($this->db_index)
                ->query("SELECT id, order_items, order_date FROM orders 
                        WHERE salon_id = ? 
                        AND DATE(order_date) >= ? 
                        AND DATE(order_date) <= ?
                        AND status != 'cancelled'", 
                        [$salon_id, $startDate, $endDate])
                ->result_array();

            $updatedCount = 0;
            $totalStepsUpdated = 0;

            foreach ($orders as $order) {
                $orderItems = json_decode($order['order_items'], true);
                $modified = false;

                if (!is_array($orderItems)) continue;

                foreach ($orderItems as &$item) {
                    if (!isset($item['work_steps']) || !is_array($item['work_steps'])) continue;

                    foreach ($item['work_steps'] as &$step) {
                        // Try to find fee by step_id first, then by step_name
                        $newFee = null;
                        
                        if (isset($step['step_id']) && isset($feeByStepId[$step['step_id']])) {
                            $newFee = $feeByStepId[$step['step_id']];
                        } elseif (isset($step['step_name']) && isset($feeByStepName[$step['step_name']])) {
                            $newFee = $feeByStepName[$step['step_name']];
                        }

                        if ($newFee !== null) {
                            $oldFee = isset($step['fee']) ? (float)$step['fee'] : 0;
                            if ($oldFee !== $newFee) {
                                $step['fee'] = $newFee;
                                $modified = true;
                                $totalStepsUpdated++;
                            }
                        }
                    }
                }

                if ($modified) {
                    $this->db($this->db_index)->update('orders', [
                        'order_items' => json_encode($orderItems),
                        'updated_at' => date('Y-m-d H:i:s')
                    ], ['id' => $order['id']]);
                    $updatedCount++;
                }
            }

            $this->json([
                'success' => true,
                'message' => "Berhasil menyinkronkan fee untuk {$updatedCount} order ({$totalStepsUpdated} langkah kerja)",
                'data' => [
                    'orders_updated' => $updatedCount,
                    'steps_updated' => $totalStepsUpdated,
                    'total_orders_checked' => count($orders)
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Orders syncFee error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }
}
