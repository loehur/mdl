<?php
/**
 * Test YCloud Webhook Handler Locally
 * Simulates webhook POST request with real data
 */

// Simulate webhook POST data
$_SERVER['REQUEST_METHOD'] = 'POST';

// Use the real JSON from your log
$testJson = '{
    "id":"evt_6945428020926b6570b50e23",
    "type":"whatsapp.inbound_message.received",
    "apiVersion":"v2",
    "createTime":"2025-12-19T12:18:08.337Z",
    "whatsappInboundMessage":{
        "id":"694542806f1ad47617c6738c",
        "wamid":"wamid.HBgNNjI4MTI2ODA5ODMwMBUCABIYIEFDRkI2NUUyQzEwRTU0RTJCODI0OUUwN0U5MjJFQzBBAA==",
        "wabaId":"2115170262645237",
        "from":"+6281268098300",
        "customerProfile":{"name":"loehur"},
        "to":"+6281170706611",
        "sendTime":"2025-12-19T12:18:07.000Z",
        "type":"text",
        "text":{"body":"AYAH JAGGU IBU SAYANG"}
    }
}';

// Simulate php://input
file_put_contents('php://temp/testinput', $testJson);

echo "=== Testing YCloud Webhook Handler ===\n\n";
echo "Test JSON:\n" . $testJson . "\n\n";
echo str_repeat("-", 80) . "\n\n";

// Now test the actual webhook processing
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

$db = DB::getInstance(0);

echo "Testing direct insert...\n\n";

$data = json_decode($testJson, true);
$msg = $data['whatsappInboundMessage'];

$insertData = [
    'event_id'      => $data['id'],
    'event_type'    => $data['type'],
    'api_version'   => $data['apiVersion'],
    'event_time'    => date('Y-m-d H:i:s', strtotime($data['createTime'])),
    
    'message_id'    => $msg['id'],
    'wamid'         => $msg['wamid'],
    'waba_id'       => $msg['wabaId'],
    
    'phone_from'    => $msg['from'],
    'phone_to'      => $msg['to'],
    'contact_name'  => $msg['customerProfile']['name'],
    
    'message_type'  => $msg['type'],
    'text_body'     => $msg['text']['body'] ?? null,
    'send_time'     => date('Y-m-d H:i:s', strtotime($msg['sendTime'])),
    'raw_json'      => $testJson
];

echo "Data to insert:\n";
print_r($insertData);
echo "\n";

try {
    $inserted = $db->insert('wh_whatsapp', $insertData);
    
    if ($inserted) {
        echo "✓ SUCCESS! Record inserted with ID: $inserted\n\n";
        
        // Verify
        $check = $db->query("SELECT * FROM wh_whatsapp WHERE id = ?", [$inserted]);
        $record = $check->row_array();
        
        echo "Verified record:\n";
        echo "  ID: {$record['id']}\n";
        echo "  Event: {$record['event_type']}\n";
        echo "  From: {$record['phone_from']} ({$record['contact_name']})\n";
        echo "  Message: {$record['text_body']}\n";
        echo "  Created: {$record['created_at']}\n";
    } else {
        $error = $db->conn()->error;
        echo "✗ FAILED to insert!\n";
        echo "MySQL Error: $error\n";
    }
} catch (Exception $e) {
    echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
