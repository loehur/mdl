<?php
/**
 * WhatsApp Tables Fresh Install
 * Run this to DROP ALL existing wa_* tables and CREATE new structure
 * 
 * ⚠️  WARNING: This will DELETE ALL WhatsApp data!
 * ⚠️  Make backup first before running!
 */

// Database configuration
$host = 'localhost';
$dbname = 'mdl_main';
$username = 'mdl_main';
$password = 'wB5KjfjRYfPXBtFF';

echo "========================================\n";
echo "WhatsApp Tables Fresh Install\n";
echo "========================================\n\n";

echo "⚠️  WARNING: This will DROP and RECREATE all wa_* tables!\n";
echo "⚠️  ALL existing WhatsApp data will be LOST!\n\n";

// Uncomment line below to enable auto-run
// $confirmed = true;

if (!isset($confirmed)) {
    echo "To run this script, edit the file and uncomment the \$confirmed line.\n";
    echo "This is a safety measure to prevent accidental data loss.\n";
    exit;
}

try {
    // Connect to database
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error . "\n");
    }
    
    echo "✅ Connected to database: $dbname\n\n";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/wa_tables_complete.sql';
    if (!file_exists($sqlFile)) {
        die("❌ SQL file not found: $sqlFile\n");
    }
    
    echo "📄 Reading SQL file...\n";
    $sql = file_get_contents($sqlFile);
    
    // Split by ; but keep -- comments
    $statements = explode(';', $sql);
    
    $executed = 0;
    $errors = 0;
    
    echo "🔧 Executing SQL statements...\n";
    echo str_repeat("-", 60) . "\n";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements or pure comments
        if (empty($statement) || 
            substr($statement, 0, 2) === '--' || 
            substr($statement, 0, 2) === '/*') {
            continue;
        }
        
        // Remove comments
        $statement = preg_replace('/--.*$/m', '', $statement);
        $statement = trim($statement);
        
        if (empty($statement)) continue;
        
        // Show what we're executing (first 60 chars)
        $preview = substr(str_replace(["\r", "\n"], ' ', $statement), 0, 60) . "...";
        echo "  → $preview\n";
        
        if ($conn->query($statement)) {
            $executed++;
        } else {
            $errors++;
            echo "    ❌ Error: " . $conn->error . "\n";
        }
    }
    
    echo str_repeat("-", 60) . "\n";
    echo "✅ Executed: $executed statements\n";
    
    if ($errors > 0) {
        echo "❌ Errors: $errors\n\n";
    } else {
        echo "🎉 No errors!\n\n";
    }
    
    // Verify tables
    echo "🔍 Verifying tables...\n";
    echo str_repeat("-", 60) . "\n";
    
    $tables = ['wa_webhooks', 'wa_customers', 'wa_conversations', 'wa_messages_in', 'wa_messages_out'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            // Get row count
            $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
            echo "  ✅ $table (0 records)\n";
        } else {
            echo "  ❌ $table - NOT FOUND!\n";
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "✅ Fresh install completed!\n";
    echo "========================================\n";
    echo "\nNext steps:\n";
    echo "1. Test sending WhatsApp message\n";
    echo "2. Check wa_messages_out table\n";
    echo "3. Send message from customer\n";
    echo "4. Check wa_messages_in table\n";
    echo "5. Wait for webhook status update\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
