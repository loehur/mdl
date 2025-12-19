<?php
// Check complete WhatsApp data including customers

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'mdl_main';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== YCloud WhatsApp Complete Status ===\n\n";
echo "Database: $db\n\n";

$tables = ['wa_webhooks', 'wa_customers', 'wa_conversations', 'wa_messages'];

foreach ($tables as $table) {
    echo str_repeat("=", 80) . "\n";
    echo "TABLE: $table\n";
    echo str_repeat("=", 80) . "\n";
    
    $check = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows > 0) {
        echo "✓ Table exists\n\n";
        
        // Count
        $count = $mysqli->query("SELECT COUNT(*) as total FROM $table");
        $row = $count->fetch_assoc();
        echo "Total records: {$row['total']}\n\n";
        
        if ($row['total'] > 0) {
            // Show latest records
            switch ($table) {
                case 'wa_webhooks':
                    echo "Latest 5 webhooks:\n";
                    $result = $mysqli->query("
                        SELECT id, event_type, received_at 
                        FROM wa_webhooks 
                        ORDER BY id DESC 
                        LIMIT 5
                    ");
                    while ($r = $result->fetch_assoc()) {
                        echo "  #{$r['id']} - {$r['event_type']} - {$r['received_at']}\n";
                    }
                    break;
                    
                case 'wa_customers':
                    echo "Customers (for 24h window tracking):\n";
                    $result = $mysqli->query("
                        SELECT id, wa_number, contact_name, last_message_at, 
                               total_messages, is_active,
                               TIMESTAMPDIFF(HOUR, last_message_at, NOW()) as hours_since_last
                        FROM wa_customers 
                        ORDER BY last_message_at DESC 
                        LIMIT 5
                    ");
                    while ($r = $result->fetch_assoc()) {
                        $hours = $r['hours_since_last'] ?? 0;
                        $canReplyFreely = $hours < 24 ? '✓ Can reply freely' : '✗ Must use template';
                        echo "  #{$r['id']} - {$r['wa_number']} ({$r['contact_name']})\n";
                        echo "           Last message: {$r['last_message_at']} ({$hours}h ago)\n";
                        echo "           Total messages: {$r['total_messages']} | $canReplyFreely\n";
                    }
                    break;
                    
                case 'wa_conversations':
                    echo "Conversations:\n";
                    $result = $mysqli->query("
                        SELECT id, customer_id, wa_number, contact_name, 
                               last_message, status, last_message_at 
                        FROM wa_conversations 
                        ORDER BY last_message_at DESC 
                        LIMIT 5
                    ");
                    while ($r = $result->fetch_assoc()) {
                        $msg = substr($r['last_message'] ?? '-', 0, 40);
                        echo "  #{$r['id']} (Cust #{$r['customer_id']}) - {$r['wa_number']} ({$r['contact_name']})\n";
                        echo "           Last: $msg\n";
                        echo "           Status: {$r['status']} | {$r['last_message_at']}\n";
                    }
                    break;
                    
                case 'wa_messages':
                    echo "Latest 5 messages:\n";
                    $result = $mysqli->query("
                        SELECT id, customer_id, conversation_id, direction, 
                               message_type, SUBSTRING(text, 1, 50) as text, 
                               status, created_at 
                        FROM wa_messages 
                        ORDER BY id DESC 
                        LIMIT 5
                    ");
                    while ($r = $result->fetch_assoc()) {
                        $dir = $r['direction'] == 'in' ? '←' : '→';
                        $text = $r['text'] ?? "[{$r['message_type']}]";
                        $status = $r['status'] ? " ({$r['status']})" : '';
                        echo "  #{$r['id']} $dir Cust#{$r['customer_id']} Conv#{$r['conversation_id']}\n";
                        echo "           $text$status\n";
                        echo "           {$r['created_at']}\n";
                    }
                    break;
            }
        }
        echo "\n";
    } else {
        echo "✗ Table NOT FOUND\n\n";
    }
}

echo str_repeat("=", 80) . "\n";
echo "KEY INFO:\n";
echo "- wa_customers.last_message_at tracks 24h window for free messaging\n";
echo "- If hours_since_last > 24: Must use template message\n";
echo "- If hours_since_last <= 24: Can send free-form messages\n";

$mysqli->close();
