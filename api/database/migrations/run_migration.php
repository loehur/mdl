<?php
/**
 * Migration Script: Add Message Timestamps
 * Run this file to add timestamp columns to wa_messages table
 */

// Database configuration
$host = 'localhost';
$dbname = 'mdl_main';
$username = 'root';
$password = '';

try {
    // Connect to database
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to database: $dbname\n\n";
    
    // Check if columns already exist
    $check = $conn->query("SHOW COLUMNS FROM wa_messages LIKE 'sent_at'");
    if ($check->num_rows > 0) {
        echo "⚠️  Columns already exist. Skipping migration.\n";
        exit;
    }
    
    // Read migration SQL
    $sqlFile = __DIR__ . '/add_message_timestamps.sql';
    if (!file_exists($sqlFile)) {
        die("Migration file not found: $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && strpos($stmt, '--') !== 0;
        }
    );
    
    echo "Executing migration...\n\n";
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        // Remove comments
        $statement = preg_replace('/--.*$/m', '', $statement);
        $statement = trim($statement);
        
        if (empty($statement)) continue;
        
        echo "Executing: " . substr($statement, 0, 50) . "...\n";
        
        if ($conn->query($statement)) {
            echo "✅ Success\n";
        } else {
            echo "❌ Error: " . $conn->error . "\n";
        }
    }
    
    echo "\n✅ Migration completed!\n\n";
    
    // Verify columns
    echo "Verifying columns:\n";
    $result = $conn->query("SHOW COLUMNS FROM wa_messages WHERE Field IN ('sent_at', 'delivered_at', 'read_at', 'updated_at')");
    
    while ($row = $result->fetch_assoc()) {
        echo "  ✓ {$row['Field']} ({$row['Type']})\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
