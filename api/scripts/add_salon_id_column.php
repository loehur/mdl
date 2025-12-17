<?php
// Add salon_id column to users table
require_once __DIR__ . '/app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    // Add salon_id column
    $sql = "ALTER TABLE users ADD COLUMN salon_id VARCHAR(20) NULL AFTER id, ADD UNIQUE KEY idx_salon_id (salon_id)";
    $db->query($sql);
    
    echo "✓ Column salon_id added successfully with unique index!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "(Column might already exist)\n";
}
