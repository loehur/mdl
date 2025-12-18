<?php
// Quick test to check if admin user exists
require_once __DIR__ . '/app/init.php';

$phone = "081234567890";
$db = db(0);

echo "Testing DB Connection to db(0)...\n";
echo "Database: " . (isset($db) ? "Connected" : "Failed") . "\n\n";

echo "Checking user with phone: $phone\n";
$user = $db->get_where('users', ['phone_number' => $phone])->row_array();

if ($user) {
    echo "✅ User found!\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Name: " . $user['name'] . "\n";
    echo "Phone: " . $user['phone_number'] . "\n";
    echo "Role: " . ($user['role'] ?? 'N/A') . "\n";
    echo "Has password: " . (isset($user['password']) ? 'Yes' : 'No') . "\n";
    
    // Test password
    $test_password = "admin123";
    $verify = password_verify($test_password, $user['password']);
    echo "Password verify result: " . ($verify ? '✅ MATCH' : '❌ NO MATCH') . "\n";
} else {
    echo "❌ User NOT found!\n";
    echo "Creating user now...\n\n";
    
    $hashed = password_hash("admin123", PASSWORD_DEFAULT);
    $data = [
        'name' => 'Admin',
        'phone_number' => $phone,
        'password' => $hashed,
        'role' => 'admin',
        'salon_id' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $result = $db->insert('users', $data);
    if ($result['errno'] == 0) {
        echo "✅ User created successfully!\n";
    } else {
        echo "❌ Error: " . $result['error'] . "\n";
    }
}
