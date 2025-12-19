<?php
/**
 * Test webhook handler manually
 * Upload this to server and access via browser: yourdomain.com/api/test_webhook.php
 */

// Simulate webhook data
$webhookData = json_encode([
    "id" => "evt_test_" . time(),
    "type" => "whatsapp.message.updated",
    "apiVersion" => "v2",
    "createTime" => date('c'),
    "whatsappMessage" => [
        "id" => "test_msg_" . time(),
        "wamid" => "wamid.TEST123456789",
        "status" => "read",
        "from" => "+6281170706611",
        "to" => "+6281268098300",
        "wabaId" => "2115170262645237",
        "type" => "text",
        "text" => [
            "body" => "Test message"
        ],
        "createTime" => date('c', strtotime('-5 minutes')),
        "updateTime" => date('c'),
        "sendTime" => date('c', strtotime('-4 minutes')),
        "deliverTime" => date('c', strtotime('-3 minutes')),
        "readTime" => date('c'),
        "totalPrice" => 0,
        "pricingCategory" => "service",
        "pricingType" => "free_customer_service",
        "pricingModel" => "PMP",
        "currency" => "USD",
        "regionCode" => "ID",
        "bizType" => "whatsapp"
    ]
]);

echo "=== WEBHOOK TEST ===\n\n";
echo "Sending webhook data:\n";
echo $webhookData . "\n\n";

// Send to webhook endpoint
$url = "https://nalju.com/api/Webhook/WhatsApp"; // CHANGE THIS TO YOUR DOMAIN

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $webhookData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode == 200) {
    echo "✅ Webhook received successfully\n";
    echo "\nNow check:\n";
    echo "1. Database wa_webhooks for new entry\n";
    echo "2. Database wa_messages for wamid: wamid.TEST123456789\n";
    echo "3. Log files for 'Message updated' entry\n";
} else {
    echo "❌ Webhook failed with code: $httpCode\n";
}
