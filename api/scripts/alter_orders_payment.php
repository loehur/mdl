<?php
// Add payment columns to orders table
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    $sql = "ALTER TABLE orders 
            ADD COLUMN payment_method VARCHAR(20) NULL COMMENT 'tunai, non_tunai' AFTER total_price,
            ADD COLUMN payment_notes TEXT NULL AFTER payment_method";
    
    $db->query($sql);
    
    echo "✓ Columns payment_method and payment_notes added to orders table!\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
         echo "✓ Columns already exist.\n";
    } else {
         echo "Error: " . $e->getMessage() . "\n";
    }
}
