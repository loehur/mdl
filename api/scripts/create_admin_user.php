<?php
/**
 * Create Admin User
 * Run once to create initial admin account
 */

require_once __DIR__ . '/../app/init.php';

echo "=== Create Admin User ===\n\n";

// Admin credentials
$name = "Admin";
$phone = "081234567890"; // Ubah sesuai nomor Anda
$email = "admin@example.com";
$password = "admin123"; // Ubah password yang kuat
$role = "admin";

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$db = db(0);

// Check if user already exists
$existing = $db->get_where('users', ['phone_number' => $phone])->row_array();

if ($existing) {
    echo "❌ User dengan nomor $phone sudah ada!\n";
    echo "ID: " . $existing['id'] . "\n";
    echo "Name: " . $existing['name'] . "\n";
    echo "Role: " . $existing['role'] . "\n\n";
    
    // Update password
    echo "Updating password...\n";
    $result = $db->update('users', ['password' => $hashed_password], ['id' => $existing['id']]);
    
    if ($result['errno'] == 0) {
        echo "✅ Password berhasil diupdate!\n";
    } else {
        echo "❌ Error: " . $result['error'] . "\n";
    }
} else {
    // Insert new admin user
    $data = [
        'name' => $name,
        'phone_number' => $phone,
        'email' => $email,
        'password' => $hashed_password,
        'role' => $role,
        'salon_id' => 1, // Default salon
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $result = $db->insert('users', $data);
    
    if ($result['errno'] == 0) {
        echo "✅ Admin user berhasil dibuat!\n\n";
        echo "=== Login Credentials ===\n";
        echo "Phone: $phone\n";
        echo "Password: $password\n";
        echo "Role: $role\n\n";
        echo "⚠️  Jangan lupa ubah password setelah login pertama!\n";
    } else {
        echo "❌ Error: " . $result['error'] . "\n";
    }
}

echo "\n=== Done ===\n";
