<?php
/**
 * YCloud WhatsApp - New Table Structure
 * Creates 3 tables: webhooks, conversations, messages
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'mdl_main';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Creating YCloud WhatsApp table structure...\n\n";

// Drop existing tables
echo "Dropping old tables...\n";
$mysqli->query("DROP TABLE IF EXISTS wh_whatsapp");
$mysqli->query("DROP TABLE IF EXISTS wa_messages");
$mysqli->query("DROP TABLE IF EXISTS wa_conversations");
$mysqli->query("DROP TABLE IF EXISTS wa_webhooks");
echo "✓ Old tables dropped\n\n";

// 1. Raw webhook logs
echo "Creating wa_webhooks table...\n";
$sql1 = "CREATE TABLE wa_webhooks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(20) DEFAULT 'ycloud',
    event_type VARCHAR(50),
    payload JSON,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_event_type (event_type),
    INDEX idx_received_at (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($mysqli->query($sql1)) {
    echo "✓ wa_webhooks created\n";
} else {
    die("Error: " . $mysqli->error . "\n");
}

// 2. Conversations (one per customer number)
echo "Creating wa_conversations table...\n";
$sql2 = "CREATE TABLE wa_conversations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    wa_number VARCHAR(20) NOT NULL,
    contact_name VARCHAR(255),
    last_message TEXT,
    last_message_at DATETIME,
    status ENUM('open','closed') DEFAULT 'open',
    assigned_user_id BIGINT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_wa_number (wa_number),
    INDEX idx_status (status),
    INDEX idx_last_message_at (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($mysqli->query($sql2)) {
    echo "✓ wa_conversations created\n";
} else {
    die("Error: " . $mysqli->error . "\n");
}

// 3. Individual messages
echo "Creating wa_messages table...\n";
$sql3 = "CREATE TABLE wa_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT NOT NULL,
    direction ENUM('in','out') NOT NULL,
    message_type ENUM('text','image','document','audio','video','voice','location','contacts','sticker') DEFAULT 'text',
    text TEXT NULL,
    media_id VARCHAR(100) NULL,
    media_url TEXT NULL,
    media_mime_type VARCHAR(100) NULL,
    media_caption TEXT NULL,
    provider_message_id VARCHAR(100),
    wamid VARCHAR(255),
    status VARCHAR(50) NULL,
    error_message TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_provider_message_id (provider_message_id),
    INDEX idx_wamid (wamid),
    INDEX idx_direction (direction),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (conversation_id) 
        REFERENCES wa_conversations(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($mysqli->query($sql3)) {
    echo "✓ wa_messages created\n";
} else {
    die("Error: " . $mysqli->error . "\n");
}

echo "\n=== Summary ===\n";
echo "✓ All tables created successfully!\n\n";

// Show table structures
$tables = ['wa_webhooks', 'wa_conversations', 'wa_messages'];
foreach ($tables as $table) {
    echo "\nTable: $table\n";
    echo str_repeat("-", 50) . "\n";
    $result = $mysqli->query("DESCRIBE $table");
    while ($row = $result->fetch_assoc()) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
}

$mysqli->close();
