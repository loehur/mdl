<?php
// Debug script to check if controller logic works on CLI
define('ROOT', __DIR__ . '/../api'); // Point to api folder
require_once __DIR__ . '/../api/app/Config/Env.php';
require_once __DIR__ . '/../api/app/Config/DBC.php';

// Mock DB Class simply
class MockDB {
    private $mysqli;
    public function __construct() {
        $this->mysqli = new mysqli('localhost', 'root', '', 'mdl_salon');
    }
    public function query($sql, $params = []) {
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result();
        }
        return $this->mysqli->query($sql);
    }
}

// Controller Logic copy paste for testing
$salon_id = '1122017472595';
$cash_source = 'cashier';
$db = new MockDB();

echo "Testing Logic for Salon ID: $salon_id, Cash Source: $cash_source\n";

$totalIncome = 0;
// Orders
$res = $db->query("SELECT SUM(pay_cash) as total FROM orders WHERE salon_id = ? AND status = 'completed'", [$salon_id]);
$row = $res->fetch_assoc();
echo "Order Income: " . ($row['total'] ?? 'null') . "\n";
$totalIncome += ($row['total'] ?? 0);

// Income Tx
$res = $db->query("SELECT SUM(amount) as total FROM cash_transactions WHERE salon_id = ? AND transaction_type = 'income' AND cash_source = ?", [$salon_id, $cash_source]);
$row = $res->fetch_assoc();
echo "Tx Income: " . ($row['total'] ?? 'null') . "\n";
$totalIncome += ($row['total'] ?? 0);

echo "Total Income Calculated: $totalIncome\n";
