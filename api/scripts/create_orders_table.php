<?php
// Create orders table
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        salon_id VARCHAR(20) NOT NULL,
        customer_id INT NOT NULL,
        order_date DATETIME NOT NULL,
        total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(50) NOT NULL DEFAULT 'pending' COMMENT 'pending, in_progress, completed, cancelled',
        order_items JSON NOT NULL COMMENT 'Array of {product_id, product_name, price, work_steps: [{step_id, step_name, fee, worker_id, status}]}',
        notes TEXT NULL,
        created_by INT NOT NULL COMMENT 'User ID who created the order',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        completed_at DATETIME NULL,
        INDEX idx_salon (salon_id),
        INDEX idx_customer (customer_id),
        INDEX idx_status (status),
        INDEX idx_order_date (order_date),
        FOREIGN KEY (salon_id) REFERENCES salon(salon_id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql);
    
    echo "✓ Table 'orders' created successfully!\n";
    echo "\nColumns:\n";
    echo "- id (INT, AUTO_INCREMENT, PRIMARY KEY)\n";
    echo "- salon_id (VARCHAR 20)\n";
    echo "- customer_id (INT, FK to customers)\n";
    echo "- order_date (DATETIME)\n";
    echo "- total_price (DECIMAL 10,2)\n";
    echo "- status (VARCHAR 50): pending, in_progress, completed, cancelled\n";
    echo "- order_items (JSON): Products with work steps\n";
    echo "- notes (TEXT)\n";
    echo "- created_by (INT, FK to users)\n";
    echo "- created_at, updated_at, completed_at (DATETIME)\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
