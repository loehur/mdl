<?php
/**
 * Update NULL status for existing messages
 */

$conn = new mysqli('localhost', 'root', '', 'mdl_main');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Updating NULL status for existing messages...\n\n";

// Update inbound messages with NULL status
$sql1 = "UPDATE wa_messages 
SET status = 'received' 
WHERE status IS NULL AND direction = 'in'";

if ($conn->query($sql1)) {
    echo "✅ Updated " . $conn->affected_rows . " inbound messages to 'received'\n";
} else {
    echo "❌ Error updating inbound: " . $conn->error . "\n";
}

// Update outbound messages with NULL status based on timestamps
$sql2 = "UPDATE wa_messages 
SET status = CASE 
    WHEN read_at IS NOT NULL THEN 'read'
    WHEN delivered_at IS NOT NULL THEN 'delivered'
    WHEN sent_at IS NOT NULL THEN 'sent'
    ELSE 'pending'
END
WHERE status IS NULL AND direction = 'out'";

if ($conn->query($sql2)) {
    echo "✅ Updated " . $conn->affected_rows . " outbound messages\n";
} else {
    echo "❌ Error updating outbound: " . $conn->error . "\n";
}

echo "\nChecking results:\n";
$result = $conn->query("SELECT direction, status, COUNT(*) as count FROM wa_messages GROUP BY direction, status ORDER BY direction, status");

echo str_repeat("-", 40) . "\n";
printf("%-15s %-15s %s\n", "Direction", "Status", "Count");
echo str_repeat("-", 40) . "\n";

while ($row = $result->fetch_assoc()) {
    printf("%-15s %-15s %d\n", $row['direction'], $row['status'] ?? 'NULL', $row['count']);
}

$conn->close();
