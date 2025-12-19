<?php
/**
 * Direct ALTER TABLE execution
 */

$conn = new mysqli('localhost', 'root', '', 'mdl_main');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Adding timestamp columns to wa_messages...\n\n";

$sql = "ALTER TABLE `wa_messages`
ADD COLUMN `sent_at` datetime DEFAULT NULL COMMENT 'When message was sent' AFTER `status`,
ADD COLUMN `delivered_at` datetime DEFAULT NULL COMMENT 'When message was delivered' AFTER `sent_at`,
ADD COLUMN `read_at` datetime DEFAULT NULL COMMENT 'When message was read' AFTER `delivered_at`,
ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Last update time' AFTER `read_at`,
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_read_at` (`read_at`)";

if ($conn->query($sql)) {
    echo "✅ SUCCESS! Columns added.\n\n";
    
    // Verify
    echo "Verifying columns:\n";
    $result = $conn->query("SHOW COLUMNS FROM wa_messages WHERE Field IN ('sent_at', 'delivered_at', 'read_at', 'updated_at')");
    
    while ($row = $result->fetch_assoc()) {
        echo "  ✓ {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "❌ ERROR: " . $conn->error . "\n";
}

$conn->close();
