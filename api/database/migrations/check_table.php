<?php
/**
 * Check wa_messages table structure
 */

$conn = new mysqli('localhost', 'root', '', 'mdl_main');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Checking wa_messages table structure:\n\n";

// Show all columns
$result = $conn->query("SHOW COLUMNS FROM wa_messages");

echo "All columns:\n";
echo str_repeat("-", 80) . "\n";
printf("%-20s %-20s %-10s %-10s\n", "Field", "Type", "Null", "Default");
echo str_repeat("-", 80) . "\n";

while ($row = $result->fetch_assoc()) {
    printf("%-20s %-20s %-10s %-10s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Null'],
        $row['Default'] ?? 'NULL'
    );
}

echo "\n";

// Check for timestamp columns specifically
$timestamp_cols = ['sent_at', 'delivered_at', 'read_at', 'updated_at'];
$missing = [];

foreach ($timestamp_cols as $col) {
    $check = $conn->query("SHOW COLUMNS FROM wa_messages LIKE '$col'");
    if ($check->num_rows == 0) {
        $missing[] = $col;
    }
}

if (empty($missing)) {
    echo "✅ All timestamp columns exist!\n";
} else {
    echo "❌ Missing columns: " . implode(', ', $missing) . "\n";
}

$conn->close();
