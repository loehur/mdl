<?php
/**
 * Check webhook events - USE THIS ON SERVER
 */

// CHANGE THIS FOR PRODUCTION
$host = 'localhost';
$dbname = 'mdl_main';
$username = 'mdl_main';
$password = 'wB5KjfjRYfPXBtFF';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== WEBHOOK EVENTS CHECK ===\n\n";

// Count by event type
echo "Event Types:\n";
echo str_repeat("-", 60) . "\n";
$result = $conn->query("
    SELECT event_type, COUNT(*) as count 
    FROM wa_webhooks 
    GROUP BY event_type 
    ORDER BY count DESC
");

while ($row = $result->fetch_assoc()) {
    printf("%-40s : %d\n", $row['event_type'], $row['count']);
}

// Check for message.updated events
echo "\n\nRecent 'whatsapp.message.updated' events:\n";
echo str_repeat("-", 60) . "\n";

$result = $conn->query("
    SELECT id, event_type, received_at, 
           JSON_EXTRACT(payload, '$.whatsappMessage.wamid') as wamid,
           JSON_EXTRACT(payload, '$.whatsappMessage.status') as status
    FROM wa_webhooks 
    WHERE event_type = 'whatsapp.message.updated'
    ORDER BY id DESC 
    LIMIT 10
");

if ($result->num_rows == 0) {
    echo "❌ NO 'whatsapp.message.updated' events found!\n";
    echo "   Webhook might not be receiving this event type.\n";
} else {
    printf("%-5s %-30s %-15s %-15s\n", "ID", "Received", "WAMID", "Status");
    echo str_repeat("-", 60) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        $wamid = substr(trim($row['wamid'], '"'), 0, 20);
        $status = trim($row['status'], '"');
        printf("%-5s %-30s %-15s %-15s\n", 
            $row['id'],
            $row['received_at'],
            $wamid,
            $status
        );
    }
}

// Check messages table for updates
echo "\n\nRecent messages with status updates:\n";
echo str_repeat("-", 80) . "\n";

$result = $conn->query("
    SELECT id, direction, status, sent_at, delivered_at, read_at, created_at
    FROM wa_messages 
    WHERE updated_at IS NOT NULL OR read_at IS NOT NULL
    ORDER BY id DESC 
    LIMIT 5
");

if ($result->num_rows == 0) {
    echo "❌ NO messages with status updates found!\n";
} else {
    printf("%-5s %-10s %-10s %-20s %-20s\n", "ID", "Direction", "Status", "Delivered", "Read");
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        printf("%-5s %-10s %-10s %-20s %-20s\n", 
            $row['id'],
            $row['direction'],
            $row['status'] ?? 'NULL',
            $row['delivered_at'] ?? '-',
            $row['read_at'] ?? '-'
        );
    }
}

$conn->close();
