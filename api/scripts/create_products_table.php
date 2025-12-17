<?php
// Create products table
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    $sql = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        salon_id VARCHAR(20) NOT NULL,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        work_steps JSON NULL COMMENT 'Array of work_step IDs',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        INDEX idx_salon (salon_id),
        FOREIGN KEY (salon_id) REFERENCES salon(salon_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql);
    
    echo "✓ Table 'products' created successfully!\n";
    echo "\nColumns:\n";
    echo "- id (INT, AUTO_INCREMENT, PRIMARY KEY)\n";
    echo "- salon_id (VARCHAR 20, FOREIGN KEY to salon.salon_id)\n";
    echo "- name (VARCHAR 255) - Nama produk/layanan\n";
    echo "- price (DECIMAL 10,2) - Harga jual\n";
    echo "- work_steps (JSON) - Array of work_step IDs\n";
    echo "- created_at (DATETIME)\n";
    echo "- updated_at (DATETIME)\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
