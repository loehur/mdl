<?php
// Test register endpoint
$url = 'http://localhost/mdl/api/Beauty_Salon/Auth/register';
$data = json_encode([
    'name' => 'Test Admin',
    'phone_number' => '081234567899',
    'password' => 'password123'
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
print_r(json_decode($response, true));
echo "\n\nRaw Response:\n";
echo $response;
