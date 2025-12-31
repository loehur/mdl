<?php
// Test using the actual App\Core\DB class logic
define('ROOT', __DIR__ . '/../api'); 

// Mock Env
class Env {
    const MODE = 'dev';
    const DB_HOST = 'localhost';
    const DB_CREDENTIALS = [
        'dev' => [
            5 => ["db" => "mdl_salon", "user" => "root", "pass" => ""]
        ]
    ];
}

require_once __DIR__ . '/../api/app/Config/DBC.php';
require_once __DIR__ . '/../api/app/Core/DB.php';

use App\Core\DB;

$salon_id = '1122017472595'; // As string
//$salon_id = 1122017472595; // As int

$db = new DB(5);

echo "Testing DB Class with Salon ID: $salon_id (" . gettype($salon_id) . ")\n";

try {
    // Test Order Income Query
    $cash_source = 'cashier';
    
    // Check parameters type detection
    echo "Check Params Binding...\n";
    $params = [$salon_id];
    $types = "";
    foreach ($params as $param) {
        if (is_int($param)) $types .= "i";
        elseif (is_float($param)) $types .= "d";
        else $types .= "s";
    }
    echo "Types for salon_id: $types\n";

    echo "Running Order Query...\n";
    $sql = "SELECT SUM(pay_cash) as total FROM orders WHERE salon_id = ? AND status = 'completed'";
    $res = $db->query($sql, [$salon_id])->row_array();
    
    print_r($res);

    echo "Running Expense Query...\n";
    $sql2 = "SELECT SUM(amount) as total FROM cash_transactions WHERE salon_id = ? AND transaction_type = 'expense' AND cash_source = ?";
    $res2 = $db->query($sql2, [$salon_id, $cash_source])->row_array();
    print_r($res2);

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
