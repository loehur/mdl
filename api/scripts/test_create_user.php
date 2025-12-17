<?php
// Test create user endpoint
session_start();

// Simulate admin session
$_SESSION['salon_user_session'] = [
    'user' => [
        'id' => 1,
        'salon_id' => '1121717400892',
        'name' => 'Luhur Gunawan',
        'phone_number' => '081268098300',
        'role' => 'admin'
    ],
    'logged_in' => true
];

$url = 'http://localhost/mdl/api/Beauty_Salon/Users/create';
$data = json_encode([
    'name' => 'Kasir Test 2',
    'phone_number' => '08199998888',
    'password' => 'kasir123',
    'role' => 'cashier'
]);

// Store session ID
$session_id = session_id();
session_write_close();

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: PHPSESSID=' . $session_id
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
print_r(json_decode($response, true));
echo "\n\nRaw Response:\n";
echo $response;
