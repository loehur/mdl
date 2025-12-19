<?php
/**
 * Verify WA Tables Restructure
 * Run this AFTER migration pada server
 */

$host = 'localhost';
$user = 'mdl_main';
$pass = 'wB5KjfjRYfPXBtFF';
$db = 'mdl_main';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "=== WHATSAPP TABLES RESTRUCTURE VERIFICATION ===\n\n";

// 1. Check tables exist
echo "1. Checking tables...\n";
echo str_repeat("-", 60) . "\n";

$tables = ['wa_messages_in', 'wa_messages_out', 'wa_conversations', 'wa_customers', 'wa_webhooks'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✅ $table - EXISTS\n";
    } else {
        echo "❌ $table - NOT FOUND!\n";
    }
}

// 2. Check old wa_messages is gone
$result = $conn->query("SHOW TABLES LIKE 'wa_messages'");
if ($result->num_rows == 0) {
    echo "✅ wa_messages - DROPPED (as expected)\n";
} else {
    echo "⚠️  wa_messages - STILL EXISTS (should be dropped!)\n";
}

// 3. Check wa_messages_out structure
echo "\n2. Checking wa_messages_out structure...\n";
echo str_repeat("-", 60) . "\n";

$requiredCols = ['id', 'conversation_id', 'phone', 'wamid', 'message_id', 'type', 'content', 
                 'template_params', 'media_url', 'status', 'created_at', 'sent_at', 'delivered_at', 'read_at'];

$result = $conn->query("SHOW COLUMNS FROM wa_messages_out");
$existingCols = [];
while ($row = $result->fetch_assoc()) {
    $existingCols[] = $row['Field'];
}

foreach ($requiredCols as $col) {
    if (in_array($col, $existingCols)) {
        echo "✅ $col\n";
    } else {
        echo "❌ $col - MISSING!\n";
    }
}

// 4. Check wa_messages_in structure
echo "\n3. Checking wa_messages_in structure...\n";
echo str_repeat("-", 60) . "\n";

$requiredCols2 = ['id', 'conversation_id', 'customer_id', 'phone', 'wamid', 'type', 
                  'text', 'media_id', 'media_url', 'contact_name', 'status', 'received_at'];

$result = $conn->query("SHOW COLUMNS FROM wa_messages_in");
$existingCols2 = [];
while ($row = $result->fetch_assoc()) {
    $existingCols2[] = $row['Field'];
}

foreach ($requiredCols2 as $col) {
    if (in_array($col, $existingCols2)) {
        echo "✅ $col\n";
    } else {
        echo "❌ $col - MISSING!\n";
    }
}

// 5. Check wa_conversations new fields
echo "\n4. Checking wa_conversations new fields...\n";
echo str_repeat("-", 60) . "\n";

$result = $conn->query("SHOW COLUMNS FROM wa_conversations");
$convCols = [];
while ($row = $result->fetch_assoc()) {
    $convCols[] = $row['Field'];
}

// Should have
if (in_array('last_in_at', $convCols)) {
    echo "✅ last_in_at - EXISTS\n";
} else {
    echo "❌ last_in_at - MISSING!\n";
}

if (in_array('last_out_at', $convCols)) {
    echo "✅ last_out_at - EXISTS\n";
} else {
    echo "❌ last_out_at - MISSING!\n";
}

// Should NOT have
if (!in_array('last_message', $convCols)) {
    echo "✅ last_message - REMOVED (as expected)\n";
} else {
    echo "⚠️  last_message - STILL EXISTS (should be removed!)\n";
}

if (!in_array('last_message_at', $convCols)) {
    echo "✅ last_message_at - REMOVED (as expected)\n";
} else {
    echo "⚠️  last_message_at - STILL EXISTS (should be removed!)\n";
}

// 6. Count records
echo "\n5. Checking record counts...\n";
echo str_repeat("-", 60) . "\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM wa_messages_out");
$count_out = $result->fetch_assoc()['cnt'];
echo "📊 wa_messages_out: $count_out records\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM wa_messages_in");
$count_in = $result->fetch_assoc()['cnt'];
echo "📊 wa_messages_in: $count_in records\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM wa_conversations");
$count_conv = $result->fetch_assoc()['cnt'];
echo "📊 wa_conversations: $count_conv records\n";

// 7. Sample data
if ($count_out > 0) {
    echo "\n6. Sample outbound message:\n";
    echo str_repeat("-", 60) . "\n";
    $result = $conn->query("SELECT * FROM wa_messages_out ORDER BY id DESC LIMIT 1");
    $row = $result->fetch_assoc();
    foreach ($row as $key => $val) {
        echo "  $key: " . ($val ?? 'NULL') . "\n";
    }
}

if ($count_in > 0) {
    echo "\n7. Sample inbound message:\n";
    echo str_repeat("-", 60) . "\n";
    $result = $conn->query("SELECT * FROM wa_messages_in ORDER BY id DESC LIMIT 1");
    $row = $result->fetch_assoc();
    foreach ($row as $key => $val) {
        echo "  $key: " . ($val ?? 'NULL') . "\n";
    }
}

echo "\n\n=== VERIFICATION COMPLETE ===\n";
echo "Check for any ❌ or ⚠️  symbols above.\n";
echo "If all ✅, migration was successful!\n";

$conn->close();
