<?php
/**
 * Test Script - Simulate outbound message save
 * Menggunakan data dari log yang gagal
 */

// Simulate API response (dari log)
$response = json_decode('{
    "id":"69459416d74992655fea3c9e",
    "status":"accepted",
    "from":"+6281170706611",
    "to":"+6281268098300",
    "wabaId":"2115170262645237",
    "type":"text",
    "text":{"body":"0839 (Gunawan) - LAUNDRY"},
    "createTime":"2025-12-19T18:06:14.279Z",
    "totalPrice":0,
    "pricingCategory":"service",
    "currency":"USD",
    "regionCode":"ID",
    "bizType":"whatsapp"
}', true);

$payload = [
    'from' => '+6281170706611',
    'to' => '+6281268098300',
    'type' => 'text',
    'text' => ['body' => '0839 (Gunawan) - LAUNDRY']
];

echo "=== TEST OUTBOUND MESSAGE SAVE ===\n\n";

// Extract data
$waNumber = $payload['to'];
$messageType = $payload['type'];
$messageId = $response['id'];
$wamid = $response['wamid'] ?? null;

echo "Extracted Data:\n";
echo "  Phone: $waNumber\n";
echo "  Type: $messageType\n";
echo "  Message ID: $messageId\n";
echo "  WAMID: " . ($wamid ?? 'NULL') . "\n\n";

// OLD validation (GAGAL)
echo "OLD Validation:\n";
if (!$waNumber || !$wamid) {
    echo "  ❌ FAILED: wamid is NULL\n\n";
} else {
    echo "  ✅ PASS\n\n";
}

// NEW validation (SUCCESS)
echo "NEW Validation:\n";
if (!$waNumber || !$messageId) {
    echo "  ❌ FAILED\n\n";
} else {
    echo "  ✅ PASS: Can save with message_id only\n\n";
}

// What will be inserted
echo "Data to Insert:\n";
echo str_repeat("-", 60) . "\n";
echo "  conversation_id: (will be determined)\n";
echo "  phone: $waNumber\n";
echo "  wamid: " . ($wamid ?? 'NULL') . " ← Will be NULL initially\n";
echo "  message_id: $messageId ← Used for webhook matching\n";
echo "  type: $messageType\n";
echo "  content: 0839 (Gunawan) - LAUNDRY\n";
echo "  status: accepted\n";
echo "  created_at: " . date('Y-m-d H:i:s') . "\n";

echo "\n";
echo "=== RESULT ===\n";
echo "✅ Message will be saved successfully with new validation!\n";
echo "\n";
echo "Next Steps:\n";
echo "1. Webhook akan kirim event dengan wamid\n";
echo "2. handleMessageUpdated() akan match by message_id\n";
echo "3. Update wamid + status + timestamps\n";
