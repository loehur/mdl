<?php
// Update existing users with salon_id
require_once __DIR__ . '/app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

// Get all users without salon_id
$users = $db->query("SELECT * FROM users WHERE salon_id IS NULL OR salon_id = ''")->result_array();

echo "Found " . count($users) . " users without salon_id\n\n";

foreach ($users as $user) {
    // Generate salon_id
    $yearOffset = date('Y') - 2024;
    $timestamp = date('mdHis');
    $random = rand(0, 9) . rand(0, 9);
    $salon_id = $yearOffset . $timestamp . $random;
    
    // Update user
    $db->update('users', [
        'salon_id' => $salon_id
    ], ['id' => $user['id']]);
    
    echo "✓ Updated user #{$user['id']} ({$user['name']}) with salon_id: $salon_id\n";
}

echo "\n✓ All users now have salon_id!\n";
