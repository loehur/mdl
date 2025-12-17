<?php
// Script to create test cashier user
require_once __DIR__ . '/app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

// Create test cashier
$phone = '081234567890';
$password = 'test123';
$name = 'Kasir Test';

// Check if exists
$existing = $db->get_where('users', ['phone_number' => $phone], 1)->row_array();

if ($existing) {
    echo "User sudah ada. Updating password...\n";
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $db->update('users', [
        'password' => $hashed,
        'is_active' => 1
    ], ['phone_number' => $phone]);
    echo "Password updated!\n";
} else {
    echo "Creating new user...\n";
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $db->insert('users', [
        'name' => $name,
        'phone_number' => $phone,
        'password' => $hashed,
        'role' => 'cashier',
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    echo "User created!\n";
}

echo "\n==================\n";
echo "Login credentials:\n";
echo "Phone: $phone\n";
echo "Password: $password\n";
echo "Role: cashier\n";
echo "==================\n";
