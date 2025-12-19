<?php
// Direct check without using init.php to avoid header issues

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'mdl_main';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== YCloud WhatsApp Table Check ===\n\n";
echo "Database: $db\n\n";

$tableCheck = $mysqli->query("SHOW TABLES LIKE 'wh_whatsapp'");
if ($tableCheck->num_rows > 0) {
    echo "✓ Table 'wh_whatsapp' exists\n\n";
    
    // Count records
    $count = $mysqli->query("SELECT COUNT(*) as total FROM wh_whatsapp");
    $countRow = $count->fetch_assoc();
    echo "Total records: " . $countRow['total'] . "\n\n";
    
    // Show last 5 records if any
    if ($countRow['total'] > 0) {
        echo "Latest 5 records:\n";
        echo str_repeat("-", 100) . "\n";
        $records = $mysqli->query("
            SELECT id, event_type, phone_from, phone_to, contact_name, 
                   message_type, text_body, status, created_at 
            FROM wh_whatsapp 
            ORDER BY id DESC 
            LIMIT 5
        ");
        while ($rec = $records->fetch_assoc()) {
            echo "ID: {$rec['id']}\n";
            echo "  Event: {$rec['event_type']}\n";
            echo "  From: {$rec['phone_from']} ({$rec['contact_name']})\n";
            echo "  To: {$rec['phone_to']}\n";
            echo "  Type: {$rec['message_type']}\n";
            echo "  Message: " . substr($rec['text_body'] ?? '-', 0, 50) . "\n";
            echo "  Status: {$rec['status']}\n";
            echo "  Created: {$rec['created_at']}\n";
            echo str_repeat("-", 100) . "\n";
        }
    } else {
        echo "No records yet. Waiting for webhook data...\n";
    }
} else {
    echo "✗ Table 'wh_whatsapp' NOT FOUND\n";
    echo "Run: php scripts/migrate_ycloud_whatsapp.php\n";
}

$mysqli->close();
