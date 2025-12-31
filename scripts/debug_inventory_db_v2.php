<?php
// Debug script to verify DB queries used in settleInventory.

// 1. Mock Env class required by DB/DBC
class Env { 
    const MODE = 'development'; 
}

// 2. Require the DB class (which will require DBC.php)
// We need to match the path relative to THIS script (in /scripts)
// DB is in ../api/app/Core/DB.php
require_once __DIR__ . '/../api/app/Core/DB.php';

use App\Core\DB;

try {
    echo "Initializing DB connection to index 5...\n";
    $db = new DB(5);
    echo "DB Connected.\n";
    
    // 3. Test the SELECT query (item_id lookup)
    $salon_id = 1; // Assuming salon 1 exists
    $itemName = 'Shampoo Test';
    $buyPrice = 50000;
    
    echo "Testing item_id lookup query...\n";
    // We use get_where or raw query. The controller uses raw query with params.
    $sql = "SELECT ip.item_id 
            FROM inventory_purchases ip 
            JOIN cash_transactions ct ON ip.transaction_id = ct.id 
            WHERE ct.salon_id = ? AND LOWER(ip.item_name) = LOWER(?) AND ip.buy_price = ? 
            LIMIT 1";
            
    // Note: DB::query prepares and executes.
    // Params: salon_id (int/string), name (string), price (int/float)
    $db->query($sql, [$salon_id, $itemName, $buyPrice]);
    
    $row = $db->row_array();
    echo "Lookup Query Executed. Result: " . json_encode($row) . "\n";
    
    // 4. Test INVENTORY INSERT
    // We need a transaction ID.
    $trx = $db->query("SELECT id FROM cash_transactions WHERE salon_id = ? LIMIT 1", [$salon_id])->row_array();
    if (!$trx) {
        // Create a dummy transaction to test with if none exist
        echo "No transactions found, cannot test insert with FK.\n";
    } else {
        $trxId = $trx['id'];
        echo "Using Transaction ID: $trxId for Insert Test\n";
        
        $newItemId = 'ITEM-DEBUG-' . time();
        
        $insertData = [
            'transaction_id' => $trxId,
            'item_id' => $newItemId,
            'item_name' => $itemName,
            'qty' => 1,
            'buy_price' => $buyPrice,
            'total_price' => $buyPrice,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        echo "Attempting Insert...\n";
        $insertId = $db->insert('inventory_purchases', $insertData);
        
        if ($insertId) {
            echo "INSERT SUCCESS! New ID: $insertId\n";
            // Delete it
            $db->query("DELETE FROM inventory_purchases WHERE id = ?", [$insertId]);
            echo "Cleaned up debug record.\n";
        } else {
            echo "INSERT FAILED.\n";
            // Check if we can get error info? DB class throws Exception on prepare failure 
            // but returns false on execute failure in insert().
        }
    }

} catch (Throwable $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
