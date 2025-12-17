<?php
// Add split payment columns to orders table
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    $sql = "ALTER TABLE orders 
            ADD COLUMN pay_cash DECIMAL(15,2) DEFAULT 0 AFTER payment_notes,
            ADD COLUMN pay_non_cash DECIMAL(15,2) DEFAULT 0 AFTER pay_cash";
    
    $db->query($sql);
    
    echo "✓ Columns pay_cash and pay_non_cash added to orders table!\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
         echo "✓ Columns already exist.\n";
    } else {
         echo "Error: " . $e->getMessage() . "\n";
    }
}
