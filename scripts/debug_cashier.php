<?php
// Debug script for Cashier Balance
$db = new mysqli('localhost', 'root', '', 'mdl_salon');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$salon_id = 1122017472595; // From previous debug output

echo "Using Salon ID: $salon_id\n";

// Check Orders Income for Cashier (pay_cash)
echo "\nChecking Orders (Cash Income)...\n";
$sql = "SELECT id, status, pay_cash, pay_non_cash, total_price FROM orders WHERE salon_id = '$salon_id' ORDER BY id DESC LIMIT 5";
$res = $db->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}

$sumSql = "SELECT SUM(pay_cash) as total FROM orders WHERE salon_id = '$salon_id' AND status = 'completed'";
$sumRes = $db->query($sumSql);
echo "Total Pay Cash (status='completed'): " . ($sumRes->fetch_assoc()['total'] ?? 0) . "\n";

// Check if maybe status is different
$statusSql = "SELECT DISTINCT status FROM orders WHERE salon_id = '$salon_id'";
$statusRes = $db->query($statusSql);
echo "Available Order Statuses: ";
while ($r = $statusRes->fetch_assoc()) {
    echo $r['status'] . ", ";
}
echo "\n";

// Check Cash Transactions for Cashier
echo "\nChecking Cash Transactions (Cashier)...\n";
echo "Income (type=income):\n";
$inc = $db->query("SELECT SUM(amount) as total FROM cash_transactions WHERE salon_id = '$salon_id' AND transaction_type = 'income' AND cash_source = 'cashier'")->fetch_assoc();
print_r($inc);

echo "Expense (type=expense):\n";
$exp = $db->query("SELECT SUM(amount) as total FROM cash_transactions WHERE salon_id = '$salon_id' AND transaction_type = 'expense' AND cash_source = 'cashier'")->fetch_assoc();
print_r($exp);

echo "Transfer In (to cashier):\n";
$trIn = $db->query("SELECT SUM(amount) as total FROM cash_transactions WHERE salon_id = '$salon_id' AND transaction_type = 'transfer' AND transfer_to = 'cashier'")->fetch_assoc();
print_r($trIn);

echo "Transfer Out (from cashier):\n";
$trOut = $db->query("SELECT SUM(amount) as total FROM cash_transactions WHERE salon_id = '$salon_id' AND transaction_type = 'transfer' AND transfer_from = 'cashier'")->fetch_assoc();
print_r($trOut);

$db->close();
