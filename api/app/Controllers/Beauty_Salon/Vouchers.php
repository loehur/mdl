<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

/**
 * Vouchers Controller
 * 
 * Handles loyalty voucher system:
 * - Every 10 completed orders = 1 voucher
 * - Voucher can be redeemed for 1 free service (any product, not inventory items)
 * - No expiration
 */
class Vouchers extends Controller
{
    private $db_index = 4;

    /**
     * Create vouchers table if not exists
     */
    private function ensureTableExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `customer_vouchers` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `salon_id` INT(11) NOT NULL,
            `customer_id` INT(11) NOT NULL,
            `voucher_code` VARCHAR(50) NOT NULL,
            `status` ENUM('available','used') DEFAULT 'available',
            `earned_at` DATETIME NOT NULL,
            `earned_from_order_id` INT(11) DEFAULT NULL COMMENT 'The 10th order that earned this voucher',
            `used_at` DATETIME DEFAULT NULL,
            `used_in_order_id` INT(11) DEFAULT NULL,
            `redeemed_product_name` VARCHAR(255) DEFAULT NULL,
            `redeemed_product_value` DECIMAL(15,2) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_salon_customer` (`salon_id`, `customer_id`),
            KEY `idx_status` (`status`),
            KEY `idx_voucher_code` (`voucher_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->db($this->db_index)->query($sql);
    }

    /**
     * GET - Get voucher statistics for a customer
     */
    public function customerStats($customer_id)
    {
        try {
            $this->ensureTableExists();
            $this->ensureProgressTableExists();
            
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Count completed orders for this customer
            $completed = $this->db($this->db_index)
                ->query("SELECT COUNT(*) as count FROM orders 
                        WHERE salon_id = ? AND customer_id = ? AND status = 'completed'", 
                        [$salon_id, $customer_id])
                ->row_array();
            
            $actualCount = (int)($completed['count'] ?? 0);
            
            // Get adjustment from manual migration (if any)
            $adjustment = $this->db($this->db_index)
                ->query("SELECT order_count_adjustment FROM customer_loyalty_adjustments 
                        WHERE salon_id = ? AND customer_id = ?", 
                        [$salon_id, $customer_id])
                ->row_array();
            
            $adjustmentValue = (int)($adjustment['order_count_adjustment'] ?? 0);
            $completedCount = $actualCount + $adjustmentValue;
            
            // Count available vouchers
            $availableVouchers = $this->db($this->db_index)
                ->query("SELECT * FROM customer_vouchers 
                        WHERE salon_id = ? AND customer_id = ? AND status = 'available'
                        ORDER BY earned_at ASC", 
                        [$salon_id, $customer_id])
                ->result_array();
            
            // Count used vouchers
            $usedVouchers = $this->db($this->db_index)
                ->query("SELECT COUNT(*) as count FROM customer_vouchers 
                        WHERE salon_id = ? AND customer_id = ? AND status = 'used'", 
                        [$salon_id, $customer_id])
                ->row_array();
            
            // Calculate progress to next voucher
            $progressToNext = $completedCount % 10;
            $ordersNeeded = 10 - $progressToNext;
            if ($progressToNext === 0 && $completedCount > 0) {
                $ordersNeeded = 10; // Just earned one, need 10 more for next
            }

            $this->json([
                'success' => true,
                'data' => [
                    'customer_id' => $customer_id,
                    'completed_orders' => $completedCount,
                    'actual_orders' => $actualCount,
                    'adjustment' => $adjustmentValue,
                    'available_vouchers' => $availableVouchers,
                    'available_vouchers_count' => count($availableVouchers),
                    'used_vouchers_count' => (int)($usedVouchers['count'] ?? 0),
                    'progress_to_next' => $progressToNext,
                    'orders_needed_for_next' => $ordersNeeded
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Vouchers customerStats error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Check and grant voucher after order completion
     * Called internally when an order is marked as completed
     */
    public function checkAndGrantVoucher($customer_id, $order_id)
    {
        try {
            $this->ensureTableExists();
            
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                return ['success' => false, 'message' => 'Salon ID tidak ditemukan'];
            }

            // Count completed orders for this customer
            $completed = $this->db($this->db_index)
                ->query("SELECT COUNT(*) as count FROM orders 
                        WHERE salon_id = ? AND customer_id = ? AND status = 'completed'", 
                        [$salon_id, $customer_id])
                ->row_array();
            
            $completedCount = (int)($completed['count'] ?? 0);
            
            // Check if this completes a set of 10
            if ($completedCount > 0 && $completedCount % 10 === 0) {
                // Check if voucher already exists for this milestone
                $existingVoucher = $this->db($this->db_index)
                    ->query("SELECT id FROM customer_vouchers 
                            WHERE salon_id = ? AND customer_id = ? AND earned_from_order_id = ?",
                            [$salon_id, $customer_id, $order_id])
                    ->row_array();
                
                if (!$existingVoucher) {
                    // Generate voucher code
                    $voucherCode = 'VCH-' . strtoupper(substr(md5($salon_id . $customer_id . time()), 0, 8));
                    
                    // Create new voucher
                    $this->db($this->db_index)->insert('customer_vouchers', [
                        'salon_id' => $salon_id,
                        'customer_id' => $customer_id,
                        'voucher_code' => $voucherCode,
                        'status' => 'available',
                        'earned_at' => date('Y-m-d H:i:s'),
                        'earned_from_order_id' => $order_id
                    ]);
                    
                    return [
                        'success' => true, 
                        'voucher_granted' => true,
                        'voucher_code' => $voucherCode,
                        'message' => 'Selamat! Customer mendapat 1 voucher gratis layanan!'
                    ];
                }
            }
            
            return [
                'success' => true, 
                'voucher_granted' => false,
                'completed_count' => $completedCount,
                'progress' => $completedCount % 10
            ];
        } catch (\Exception $e) {
            error_log("Vouchers checkAndGrantVoucher error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * POST - Use/redeem a voucher
     */
    public function redeem()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['voucher_id', 'order_id', 'product_name', 'product_value']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Find the voucher
            $voucher = $this->db($this->db_index)
                ->query("SELECT * FROM customer_vouchers 
                        WHERE id = ? AND salon_id = ? AND status = 'available'",
                        [$body['voucher_id'], $salon_id])
                ->row_array();

            if (!$voucher) {
                $this->error('Voucher tidak ditemukan atau sudah digunakan', 404);
            }

            // Update voucher as used
            $this->db($this->db_index)->update('customer_vouchers', [
                'status' => 'used',
                'used_at' => date('Y-m-d H:i:s'),
                'used_in_order_id' => $body['order_id'],
                'redeemed_product_name' => $body['product_name'],
                'redeemed_product_value' => $body['product_value']
            ], ['id' => $body['voucher_id']]);

            $this->json([
                'success' => true,
                'message' => 'Voucher berhasil digunakan'
            ]);
        } catch (\Exception $e) {
            error_log("Vouchers redeem error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - List all vouchers for the salon (admin view)
     */
    public function index()
    {
        try {
            $this->ensureTableExists();
            
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $status = $_GET['status'] ?? 'all';
            
            $sql = "SELECT cv.*, c.nama as customer_name, c.no_hp as customer_phone
                    FROM customer_vouchers cv
                    JOIN customers c ON cv.customer_id = c.id
                    WHERE cv.salon_id = ?";
            $params = [$salon_id];
            
            if ($status !== 'all') {
                $sql .= " AND cv.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY cv.earned_at DESC";

            $vouchers = $this->db($this->db_index)
                ->query($sql, $params)
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $vouchers
            ]);
        } catch (\Exception $e) {
            error_log("Vouchers index error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Get loyalty program summary
     */
    public function summary()
    {
        try {
            $this->ensureTableExists();
            
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Total vouchers issued
            $totalIssued = $this->db($this->db_index)
                ->query("SELECT COUNT(*) as count FROM customer_vouchers WHERE salon_id = ?", [$salon_id])
                ->row_array();

            // Available vouchers
            $available = $this->db($this->db_index)
                ->query("SELECT COUNT(*) as count FROM customer_vouchers WHERE salon_id = ? AND status = 'available'", [$salon_id])
                ->row_array();

            // Used vouchers
            $used = $this->db($this->db_index)
                ->query("SELECT COUNT(*) as count FROM customer_vouchers WHERE salon_id = ? AND status = 'used'", [$salon_id])
                ->row_array();

            // Total value redeemed
            $totalValue = $this->db($this->db_index)
                ->query("SELECT SUM(redeemed_product_value) as total FROM customer_vouchers WHERE salon_id = ? AND status = 'used'", [$salon_id])
                ->row_array();

            $this->json([
                'success' => true,
                'data' => [
                    'total_issued' => (int)($totalIssued['count'] ?? 0),
                    'available' => (int)($available['count'] ?? 0),
                    'used' => (int)($used['count'] ?? 0),
                    'total_value_redeemed' => (float)($totalValue['total'] ?? 0)
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Vouchers summary error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Grant voucher manually to a customer (for migration from paper records)
     * 
     * Body: { customer_id, qty (optional, default 1), notes (optional) }
     */
    public function grantManual()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $this->ensureTableExists();
            
            $body = $this->getBody();
            $this->validate($body, ['customer_id']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $customer_id = $body['customer_id'];
            $qty = isset($body['qty']) ? (int)$body['qty'] : 1;
            $notes = $body['notes'] ?? 'Migrasi dari catatan manual';

            // Verify customer exists
            $customer = $this->db($this->db_index)
                ->query("SELECT id, nama FROM customers WHERE id = ? AND salon_id = ?", [$customer_id, $salon_id])
                ->row_array();

            if (!$customer) {
                $this->error('Customer tidak ditemukan', 404);
            }

            $grantedVouchers = [];

            for ($i = 0; $i < $qty; $i++) {
                // Generate voucher code
                $voucherCode = 'VCH-' . strtoupper(substr(md5($salon_id . $customer_id . time() . $i . rand()), 0, 8));
                
                // Create new voucher
                $id = $this->db($this->db_index)->insert('customer_vouchers', [
                    'salon_id' => $salon_id,
                    'customer_id' => $customer_id,
                    'voucher_code' => $voucherCode,
                    'status' => 'available',
                    'earned_at' => date('Y-m-d H:i:s'),
                    'earned_from_order_id' => null // Manual grant, no order ID
                ]);

                $grantedVouchers[] = [
                    'id' => $id,
                    'voucher_code' => $voucherCode
                ];
            }

            $this->json([
                'success' => true,
                'message' => "Berhasil memberikan {$qty} voucher ke {$customer['nama']}",
                'data' => [
                    'customer_id' => $customer_id,
                    'customer_name' => $customer['nama'],
                    'vouchers_granted' => $qty,
                    'vouchers' => $grantedVouchers
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Vouchers grantManual error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Set/adjust customer's loyalty progress (for migration)
     * This creates "virtual" completed orders to set their progress
     * 
     * Body: { customer_id, completed_orders_count }
     * 
     * Example: If customer already has 7 orders from paper record,
     * call this with completed_orders_count = 7
     * System will calculate: 7 % 10 = 7 progress, needs 3 more for voucher
     */
    public function setProgress()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $this->ensureTableExists();
            $this->ensureProgressTableExists();
            
            $body = $this->getBody();
            $this->validate($body, ['customer_id', 'completed_orders_count']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            $customer_id = $body['customer_id'];
            $targetCount = (int)$body['completed_orders_count'];

            // Verify customer exists
            $customer = $this->db($this->db_index)
                ->query("SELECT id, nama FROM customers WHERE id = ? AND salon_id = ?", [$customer_id, $salon_id])
                ->row_array();

            if (!$customer) {
                $this->error('Customer tidak ditemukan', 404);
            }

            // Get current actual completed orders from database
            $actualOrders = $this->db($this->db_index)
                ->query("SELECT COUNT(*) as count FROM orders 
                        WHERE salon_id = ? AND customer_id = ? AND status = 'completed'", 
                        [$salon_id, $customer_id])
                ->row_array();
            $actualCount = (int)($actualOrders['count'] ?? 0);

            // Store the adjustment in a separate table
            // The adjustment = target - actual
            $adjustment = $targetCount - $actualCount;

            // Check if adjustment record exists
            $existing = $this->db($this->db_index)
                ->query("SELECT id FROM customer_loyalty_adjustments 
                        WHERE salon_id = ? AND customer_id = ?", [$salon_id, $customer_id])
                ->row_array();

            if ($existing) {
                $this->db($this->db_index)->update('customer_loyalty_adjustments', [
                    'order_count_adjustment' => $adjustment,
                    'notes' => 'Migrasi dari catatan manual - ' . date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $existing['id']]);
            } else {
                $this->db($this->db_index)->insert('customer_loyalty_adjustments', [
                    'salon_id' => $salon_id,
                    'customer_id' => $customer_id,
                    'order_count_adjustment' => $adjustment,
                    'notes' => 'Migrasi dari catatan manual - ' . date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Calculate resulting progress
            $totalCount = $actualCount + $adjustment;
            $progress = $totalCount % 10;
            $ordersNeeded = 10 - $progress;
            if ($progress === 0 && $totalCount > 0) {
                $ordersNeeded = 10;
            }

            // Auto-grant vouchers if total >= 10 and not yet granted
            $vouchersEarned = floor($totalCount / 10);
            $existingVouchers = $this->db($this->db_index)
                ->query("SELECT COUNT(*) as count FROM customer_vouchers 
                        WHERE salon_id = ? AND customer_id = ?", [$salon_id, $customer_id])
                ->row_array();
            $existingVoucherCount = (int)($existingVouchers['count'] ?? 0);

            $newVouchersToGrant = $vouchersEarned - $existingVoucherCount;
            $grantedVouchers = [];

            if ($newVouchersToGrant > 0) {
                for ($i = 0; $i < $newVouchersToGrant; $i++) {
                    $voucherCode = 'VCH-' . strtoupper(substr(md5($salon_id . $customer_id . time() . $i . rand()), 0, 8));
                    $id = $this->db($this->db_index)->insert('customer_vouchers', [
                        'salon_id' => $salon_id,
                        'customer_id' => $customer_id,
                        'voucher_code' => $voucherCode,
                        'status' => 'available',
                        'earned_at' => date('Y-m-d H:i:s'),
                        'earned_from_order_id' => null
                    ]);
                    $grantedVouchers[] = $voucherCode;
                }
            }

            $this->json([
                'success' => true,
                'message' => "Progress loyalty untuk {$customer['nama']} berhasil diatur",
                'data' => [
                    'customer_id' => $customer_id,
                    'customer_name' => $customer['nama'],
                    'actual_orders_in_system' => $actualCount,
                    'adjustment_applied' => $adjustment,
                    'total_effective_orders' => $totalCount,
                    'current_progress' => $progress,
                    'orders_needed_for_next_voucher' => $ordersNeeded,
                    'vouchers_auto_granted' => count($grantedVouchers),
                    'new_voucher_codes' => $grantedVouchers
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Vouchers setProgress error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Get all customers with their loyalty status (for admin migration view)
     */
    public function allCustomersLoyalty()
    {
        try {
            $this->ensureTableExists();
            $this->ensureProgressTableExists();
            
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Get all customers with their order count, adjustment, and voucher count
            $sql = "SELECT 
                        c.id, c.nama, c.no_hp,
                        COALESCE(o.order_count, 0) as actual_orders,
                        COALESCE(adj.order_count_adjustment, 0) as adjustment,
                        COALESCE(o.order_count, 0) + COALESCE(adj.order_count_adjustment, 0) as effective_orders,
                        COALESCE(v.available_count, 0) as available_vouchers,
                        COALESCE(v.used_count, 0) as used_vouchers
                    FROM customers c
                    LEFT JOIN (
                        SELECT customer_id, COUNT(*) as order_count 
                        FROM orders 
                        WHERE salon_id = ? AND status = 'completed' 
                        GROUP BY customer_id
                    ) o ON c.id = o.customer_id
                    LEFT JOIN customer_loyalty_adjustments adj ON c.id = adj.customer_id AND adj.salon_id = ?
                    LEFT JOIN (
                        SELECT customer_id, 
                               SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count,
                               SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_count
                        FROM customer_vouchers 
                        WHERE salon_id = ?
                        GROUP BY customer_id
                    ) v ON c.id = v.customer_id
                    WHERE c.salon_id = ?
                    ORDER BY c.nama ASC";

            $customers = $this->db($this->db_index)
                ->query($sql, [$salon_id, $salon_id, $salon_id, $salon_id])
                ->result_array();

            // Calculate progress for each
            foreach ($customers as &$cust) {
                $effective = (int)$cust['effective_orders'];
                $cust['progress'] = $effective % 10;
                $cust['orders_needed'] = 10 - $cust['progress'];
                if ($cust['progress'] === 0 && $effective > 0) {
                    $cust['orders_needed'] = 10;
                }
            }

            $this->json([
                'success' => true,
                'data' => $customers
            ]);
        } catch (\Exception $e) {
            error_log("Vouchers allCustomersLoyalty error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create loyalty adjustments table if not exists
     */
    private function ensureProgressTableExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `customer_loyalty_adjustments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `salon_id` INT(11) NOT NULL,
            `customer_id` INT(11) NOT NULL,
            `order_count_adjustment` INT(11) DEFAULT 0 COMMENT 'Adjustment to add to actual order count',
            `notes` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_salon_customer` (`salon_id`, `customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->db($this->db_index)->query($sql);
    }
}
