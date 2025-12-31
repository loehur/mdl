<?php
// Debug script to verify DB queries used in settleInventory.

// 1. Mock Env class required by DB/DBC
class Env { 
    const MODE = 'dev'; // Changed to 'dev' based on DBC keys
    const DB_HOST = 'localhost';
    const DB_CREDENTIALS = [
        'dev' => [
            0 => ["db" => "mdl_main", "user" => "root", "pass" => ""],
            1 => ["db" => "mdl_laundry", "user" => "root", "pass" => ""],
            2 => ["db" => "mdl_sale", "user" => "root", "pass" => ""],
            3 => ["db" => "mdl_resto", "user" => "root", "pass" => ""],
            4 => ["db" => "mdl_depot", "user" => "root", "pass" => ""],
            5 => ["db" => "mdl_salon", "user" => "root", "pass" => ""]
        ]
    ];
}

// 2. Require the DB class
require_once __DIR__ . '/../api/app/Core/DB.php';

use App\Core\DB;

try {
    echo "Initializing DB connection to index 5...\n";
    $db = new DB(5);
    echo "DB Connected.\n";
    
    // 3. Test the SELECT query (item_id lookup)
    $salon_id = 9; // Use actual salon id 9 from previous interactions if possible, or try 1
    // Let's try to get a valid salon_id from a transaction
    $trx = $db->query("SELECT salon_id FROM cash_transactions LIMIT 1")->row_array();
    if ($trx) {
        $salon_id = $trx['salon_id'];
        echo "Found valid salon_id: $salon_id\n";
    }

    $itemName = 'Shampoo Test';
    $buyPrice = 50000;
    
    echo "Testing item_id lookup query...\n";
    $sql = "SELECT ip.item_id 
            FROM inventory_purchases ip 
            JOIN cash_transactions ct ON ip.transaction_id = ct.id 
            WHERE ct.salon_id = ? AND LOWER(ip.item_name) = LOWER(?) AND ip.buy_price = ? 
            LIMIT 1";
            
    $db->query($sql, [$salon_id, $itemName, $buyPrice]);
    $row = $db->row_array();
    echo "Lookup Query Executed. Result: " . json_encode($row) . "\n";
    
    // 4. Test INVENTORY INSERT
    $trx = $db->query("SELECT id FROM cash_transactions WHERE salon_id = ? LIMIT 1", [$salon_id])->row_array();
    if (!$trx) {
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
            die("Insert failed. Check MySQL errors if possible.");
        }
    }

} catch (Throwable $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
