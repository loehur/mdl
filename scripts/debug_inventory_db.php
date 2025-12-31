<?php
// Debug script to simulate settleInventory logic

define('ROOT_PATH', __DIR__ . '/../');

// Mock Config
class Env { const MODE = 'development'; }
class DBC { 
    const db_host = 'localhost';
    const dbm = [
        'development' => [
            5 => ['db' => 'mdl_salon', 'user' => 'root', 'pass' => '']
        ]
    ];
}

// Minimal DB Class Mock/Include
require_once __DIR__ . '/../api/app/Core/DB.php';

use App\Core\DB;

try {
    $db = new DB(5);
    echo "DB Connected.\n";
    
    // 1. Test Select with Join (the one used for item_id lookup)
    $salon_id = 1; // Assuming salon id 1 exists
    $itemName = 'Test Item ' . time();
    $buyPrice = 50000;
    
    echo "Testing SELECT query...\n";
    $sql = "SELECT ip.item_id 
            FROM inventory_purchases ip 
            JOIN cash_transactions ct ON ip.transaction_id = ct.id 
            WHERE ct.salon_id = ? AND LOWER(ip.item_name) = LOWER(?) AND ip.buy_price = ? 
            LIMIT 1";
            
    // Try raw query logic from DB.php
    $db->query($sql, [$salon_id, $itemName, $buyPrice]);
    echo "SELECT Query Executed OK.\n";
    
    // 2. Test Insert
    echo "Testing INSERT...\n";
    // Need a valid transaction ID first? Or just try inserting with 0?
    // Foreign key constraint might fail if transaction_id is invalid.
    // Let's create a dummy transaction first if possible, or just assume FK doesn't exist or we fetch one.
    
    $trx = $db->query("SELECT id FROM cash_transactions LIMIT 1")->row_array();
    if (!$trx) {
        die("No transaction found to test insert.\n");
    }
    $trxId = $trx['id'];
    echo "Using Transaction ID: $trxId\n";
    
    $itemId = 'ITEM-TEST-' . time();
    $lineTotal = 1 * $buyPrice;
    
    $insertData = [
        'transaction_id' => $trxId,
        'item_id' => $itemId,
        'item_name' => $itemName,
        'qty' => 1,
        'buy_price' => $buyPrice,
        'total_price' => $lineTotal,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $res = $db->insert('inventory_purchases', $insertData);
    
    if ($res) {
        echo "INSERT Successful. ID: $res\n";
        
        // Cleanup
        $db->query("DELETE FROM inventory_purchases WHERE id = ?", [$res]);
        echo "Cleanup Successful.\n";
    } else {
        echo "INSERT Failed.\n";
    }

} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
