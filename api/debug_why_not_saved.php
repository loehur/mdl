<?php
/**
 * Debug Script - Check WhatsApp Outbound Message Save
 * Upload ke server dan jalankan untuk debug
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== WHATSAPP OUTBOUND DEBUG ===\n\n";

// 1. Check if new tables exist
echo "1. Checking database tables...\n";
echo str_repeat("-", 60) . "\n";

$host = 'localhost';
$user = 'mdl_main';
$pass = 'wB5KjfjRYfPXBtFF';
$db = 'mdl_main';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error . "\n");
}

$tables = ['wa_messages_out', 'wa_messages_in', 'wa_conversations', 'wa_customers'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        $count = $conn->query("SELECT COUNT(*) as c FROM $table")->fetch_assoc()['c'];
        echo "✅ $table exists ($count records)\n";
    } else {
        echo "❌ $table NOT FOUND!\n";
    }
}

// 2. Check if old wa_messages still exists
echo "\n2. Checking old table...\n";
echo str_repeat("-", 60) . "\n";
$result = $conn->query("SHOW TABLES LIKE 'wa_messages'");
if ($result->num_rows > 0) {
    echo "⚠️  wa_messages (old) STILL EXISTS - should be dropped!\n";
    $count = $conn->query("SELECT COUNT(*) as c FROM wa_messages")->fetch_assoc()['c'];
    echo "   Contains $count records\n";
} else {
    echo "✅ wa_messages (old) not found - good!\n";
}

// 3. Check recent API logs
echo "\n3. Recent API logs from YCloud...\n";
echo str_repeat("-", 60) . "\n";

// Try to read log file
$logDirs = [
    '/var/www/html/api/logs',
    __DIR__ . '/logs',
    __DIR__ . '/../logs'
];

$logFound = false;
foreach ($logDirs as $dir) {
    $logFile = $dir . '/messages_' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        echo "Log file: $logFile\n";
        $content = file_get_contents($logFile);
        $logs = explode("---", $content);
        $recent = array_slice($logs, -3); // Last 3 logs
        
        foreach ($recent as $log) {
            $data = json_decode(trim($log), true);
            if ($data && isset($data['response'])) {
                $resp = json_decode($data['response'], true);
                echo "\nMessage ID: " . ($resp['id'] ?? 'N/A') . "\n";
                echo "Status: " . ($resp['status'] ?? 'N/A') . "\n";
                echo "To: " . ($resp['to'] ?? 'N/A') . "\n";
            }
        }
        $logFound = true;
        break;
    }
}

if (!$logFound) {
    echo "⚠️  Log file not found in common locations\n";
}

// 4. Try to simulate saveOutboundMessage
echo "\n\n4. Testing saveOutboundMessage logic...\n";
echo str_repeat("-", 60) . "\n";

// Simulate the data
$payload = [
    'to' => '+6281268098300',
    'type' => 'text',
    'text' => ['body' => 'Test message']
];

$response = [
    'id' => '694595423fbb994559471770',
    'status' => 'accepted'
];

$waNumber = $payload['to'];
$messageType = $payload['type'];
$messageId = $response['id'];
$wamid = $response['wamid'] ?? null;

echo "Extracted:\n";
echo "  Phone: $waNumber\n";
echo "  Message ID: $messageId\n";
echo "  WAMID: " . ($wamid ?? 'NULL') . "\n\n";

// Validation
if (!$waNumber || !$messageId) {
    echo "❌ Validation FAILED\n";
} else {
    echo "✅ Validation PASSED\n";
}

// 5. Check if DB class exists
echo "\n5. Checking DB class...\n";
echo str_repeat("-", 60) . "\n";

$dbPath = __DIR__ . '/app/Core/DB.php';
if (file_exists($dbPath)) {
    echo "✅ DB.php exists at: $dbPath\n";
    require_once $dbPath;
    
    if (class_exists('DB')) {
        echo "✅ DB class loaded\n";
        
        try {
            $dbInstance = new DB(0);
            echo "✅ DB instance created\n";
            
            if (method_exists($dbInstance, 'insert')) {
                echo "✅ insert() method exists\n";
            }
        } catch (Exception $e) {
            echo "❌ DB instantiation error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ DB class not found after require\n";
    }
} else {
    echo "❌ DB.php not found at: $dbPath\n";
    echo "   Try different path...\n";
    
    $altPath = dirname(__DIR__) . '/app/Core/DB.php';
    if (file_exists($altPath)) {
        echo "✅ Found at: $altPath\n";
    }
}

// 6. Check PHP error log
echo "\n6. Recent PHP errors...\n";
echo str_repeat("-", 60) . "\n";

$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    echo "Error log: $errorLog\n";
    $lines = file($errorLog);
    $recentErrors = array_slice($lines, -10);
    
    $found = false;
    foreach ($recentErrors as $line) {
        if (stripos($line, 'whatsapp') !== false || 
            stripos($line, 'saveOutbound') !== false) {
            echo $line;
            $found = true;
        }
    }
    
    if (!$found) {
        echo "No WhatsApp-related errors in last 10 lines\n";
    }
} else {
    echo "Error log not accessible\n";
}

echo "\n\n=== SUMMARY ===\n";
echo "If wa_messages_out doesn't exist:\n";
echo "  → Run: mysql -u mdl_main -p mdl_main < api/database/wa_tables_complete.sql\n";
echo "\nIf code is old:\n";
echo "  → Run: git pull origin main\n";
echo "\nIf DB class issue:\n";
echo "  → Check path in WhatsAppService.php\n";

$conn->close();
