<?php
// Create salon table
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    $sql = "CREATE TABLE IF NOT EXISTS salon (
        salon_id VARCHAR(20) PRIMARY KEY,
        owner_id INT NOT NULL,
        nama_salon VARCHAR(255) NOT NULL,
        alamat_salon TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
        INDEX idx_owner (owner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql);
    
    echo "✓ Table 'salon' created successfully!\n";
    echo "\nColumns:\n";
    echo "- salon_id (VARCHAR 20, PRIMARY KEY)\n";
    echo "- owner_id (INT, FOREIGN KEY to users.id)\n";
    echo "- nama_salon (VARCHAR 255)\n";
    echo "- alamat_salon (TEXT)\n";
    echo "- created_at (DATETIME)\n";
    echo "- updated_at (DATETIME)\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
