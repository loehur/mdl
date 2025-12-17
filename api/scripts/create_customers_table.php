<?php
// Create customers table
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    $sql = "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        salon_id VARCHAR(20) NOT NULL,
        nama VARCHAR(255) NOT NULL,
        no_hp VARCHAR(20) NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        INDEX idx_salon (salon_id),
        INDEX idx_no_hp (no_hp),
        FOREIGN KEY (salon_id) REFERENCES salon(salon_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql);
    
    echo "✓ Table 'customers' created successfully!\n";
    echo "\nColumns:\n";
    echo "- id (INT, AUTO_INCREMENT, PRIMARY KEY)\n";
    echo "- salon_id (VARCHAR 20, FOREIGN KEY to salon.salon_id)\n";
    echo "- nama (VARCHAR 255) - Nama pelanggan\n";
    echo "- no_hp (VARCHAR 20) - Nomor HP\n";
    echo "- created_at (DATETIME)\n";
    echo "- updated_at (DATETIME)\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
