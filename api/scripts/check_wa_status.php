<?php
// Check YCloud WhatsApp tables and data

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'mdl_main';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== YCloud WhatsApp Status ===\n\n";
echo "Database: $db\n\n";

$tables = ['wa_webhooks', 'wa_conversations', 'wa_messages'];

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
                    
                case 'wa_conversations':
                    echo "Conversations:\n";
                    $result = $mysqli->query("
                        SELECT id, wa_number, contact_name, last_message, status, last_message_at 
                        FROM wa_conversations 
                        ORDER BY last_message_at DESC 
                        LIMIT 5
                    ");
                    while ($r = $result->fetch_assoc()) {
                        $msg = substr($r['last_message'] ?? '-', 0, 40);
                        echo "  #{$r['id']} - {$r['wa_number']} ({$r['contact_name']})\n";
                        echo "           Last: $msg\n";
                        echo "           Status: {$r['status']} | {$r['last_message_at']}\n";
                    }
                    break;
                    
                case 'wa_messages':
                    echo "Latest 5 messages:\n";
                    $result = $mysqli->query("
                        SELECT id, conversation_id, direction, message_type, 
                               SUBSTRING(text, 1, 50) as text, status, created_at 
                        FROM wa_messages 
                        ORDER BY id DESC 
                        LIMIT 5
                    ");
                    while ($r = $result->fetch_assoc()) {
                        $dir = $r['direction'] == 'in' ? '←' : '→';
                        $text = $r['text'] ?? "[{$r['message_type']}]";
                        $status = $r['status'] ? " ({$r['status']})" : '';
                        echo "  #{$r['id']} $dir Conv#{$r['conversation_id']} - $text$status\n";
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

$mysqli->close();
