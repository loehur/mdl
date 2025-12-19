<?php
// Simple test to insert data directly (bypassing webhook)
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

echo "=== Test Direct Insert to wh_whatsapp ===\n\n";

try {
    $db = DB::getInstance(0);
    
    $testData = [
        'wa_id'       => 'test_' . time(),
        'phone'       => '+6281234567890',
        'sender_name' => 'Test User',
        'type'        => 'message',
        'body'        => 'This is a test message',
        'status'      => 'received',
        'timestamp'   => date('Y-m-d H:i:s'),
        'raw_data'    => '{"test": true}'
    ];
    
    echo "Attempting to insert test data...\n";
    $inserted = $db->insert('wh_whatsapp', $testData);
    
    if ($inserted) {
        echo "✓ SUCCESS: Record inserted with ID: $inserted\n";
        
        // Verify insertion
        $check = $db->query("SELECT * FROM wh_whatsapp WHERE id = ?", [$inserted]);
        $record = $check->row_array();
        echo "\nVerified record:\n";
        print_r($record);
    } else {
        $error = $db->conn()->error;
        echo "✗ FAILED: " . $error . "\n";
        echo "\nData attempted:\n";
        print_r($testData);
    }
    
} catch (Exception $e) {
    echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
}
