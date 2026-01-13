<?php
// Test script for Tokopay API

$merchantId = 'M240926BMTGB612';
$secretKey = '4aea0ede516df65d88ccb773a443c61b3b3702fe1b9647deb9293cac07fd72bf';
$apiUrl = "https://api.tokopay.id";

$ref_id = 'TEST_' . time();
$nominal = 10000;
$kodeChannel = 'QRIS';

echo "Testing Tokopay API...\n";
echo "Merchant ID: $merchantId\n";
echo "Ref ID: $ref_id\n";

$url = $apiUrl . "/v1/order?merchant=" . $merchantId . "&secret=" . $secretKey . "&ref_id=" . $ref_id . "&nominal=" . $nominal . "&metode=" . $kodeChannel;
echo "URL: $url\n\n";

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
));

$response = curl_exec($curl);
$error = curl_error($curl);
$info = curl_getinfo($curl);
curl_close($curl);

if ($error) {
    echo "CURL Error: $error\n";
} else {
    echo "Response Code: " . $info['http_code'] . "\n";
    echo "Response Body: " . $response . "\n";
}
