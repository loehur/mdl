<?php
// Check users in database
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

$users = $db->query("SELECT id, salon_id, name, phone_number, role FROM users")->result_array();

echo "Total users: " . count($users) . "\n\n";

foreach ($users as $user) {
    $salon_status = $user['salon_id'] ? "✓ " . $user['salon_id'] : "✗ NULL";
    echo "ID: {$user['id']} | Salon: $salon_status | {$user['name']} ({$user['phone_number']}) - {$user['role']}\n";
}
