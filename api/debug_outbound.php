<?php
/**
 * Debug script untuk cek error saveOutboundMessage
 * Upload ke server dan jalankan setelah kirim pesan WA
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG OUTBOUND MESSAGE SAVE ===\n\n";

// Check PHP error log location
echo "PHP Error Log Location:\n";
echo ini_get('error_log') . "\n\n";

// Check recent PHP errors (if accessible)
$errorLog = ini_get('error_log');
if (file_exists($errorLog)) {
    echo "Recent errors from PHP log:\n";
    echo str_repeat("-", 80) . "\n";
    $lines = file($errorLog);
    $recentLines = array_slice($lines, -20); // Last 20 lines
    foreach ($recentLines as $line) {
        if (stripos($line, 'saveOutboundMessage') !== false || 
            stripos($line, 'WhatsApp') !== false) {
            echo $line;
        }
    }
    echo "\n";
}

// Check database for outbound messages
echo "Checking database for outbound messages:\n";
echo str_repeat("-", 80) . "\n";

$conn = new mysqli('localhost', 'mdl_main', 'wB5KjfjRYfPXBtFF', 'mdl_main');

if ($conn->connect_error) {
    echo "❌ Database connection failed: " . $conn->connect_error . "\n";
    exit;
}

$result = $conn->query("
    SELECT id, wamid, status, text, created_at 
    FROM wa_messages 
    WHERE direction = 'out' 
    ORDER BY id DESC 
    LIMIT 5
");

if ($result->num_rows == 0) {
    echo "❌ NO outbound messages found!\n";
    echo "   This means saveOutboundMessage is failing silently.\n\n";
} else {
    echo "✅ Found " . $result->num_rows . " outbound messages:\n";
    printf("%-5s %-30s %-10s %-30s\n", "ID", "WAMID", "Status", "Created");
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        printf("%-5s %-30s %-10s %-30s\n", 
            $row['id'],
            substr($row['wamid'], 0, 25) . '...',
            $row['status'],
            $row['created_at']
        );
    }
}

$conn->close();

echo "\n=== TEST DB CLASS ===\n\n";

// Test if DB class can be loaded
try {
    require_once __DIR__ . '/app/Core/DB.php';
    
    if (class_exists('DB')) {
        echo "✅ DB class exists\n";
        
        $db = new DB(0);
        echo "✅ DB instance created\n";
        
        if (method_exists($db, 'get_where')) {
            echo "✅ get_where method exists\n";
        } else {
            echo "❌ get_where method NOT found\n";
        }
        
        if (method_exists($db, 'insert')) {
            echo "✅ insert method exists\n";
        } else {
            echo "❌ insert method NOT found\n";
        }
    } else {
        echo "❌ DB class NOT found after require\n";
    }
} catch (Exception $e) {
    echo "❌ Error loading DB: " . $e->getMessage() . "\n";
}
