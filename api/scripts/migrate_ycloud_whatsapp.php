<?php
/**
 * YCloud WhatsApp API - Database Migration
 * Creates table for storing all webhook events
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'mdl_main';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Creating YCloud WhatsApp webhook table...\n\n";

// Drop existing table if exists
$mysqli->query("DROP TABLE IF EXISTS wh_whatsapp");

$sql = "CREATE TABLE wh_whatsapp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Event Information
    event_id VARCHAR(100) UNIQUE,
    event_type VARCHAR(50) NOT NULL,
    api_version VARCHAR(20),
    event_time DATETIME,
    
    -- Message Information
    message_id VARCHAR(100),
    wamid VARCHAR(255),
    waba_id VARCHAR(100),
    
    -- Contact Information
    phone_from VARCHAR(20),
    phone_to VARCHAR(20),
    contact_name VARCHAR(255),
    
    -- Message Content
    message_type VARCHAR(20),
    text_body TEXT,
    media_url TEXT,
    media_caption TEXT,
    media_mime_type VARCHAR(100),
    media_id VARCHAR(255),
    
    -- Status Information (for outbound messages)
    status VARCHAR(50),
    error_code VARCHAR(50),
    error_message TEXT,
    
    -- Timestamps
    send_time DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Raw Data
    raw_json LONGTEXT,
    
    -- Indexes
    INDEX idx_event_id (event_id),
    INDEX idx_wamid (wamid),
    INDEX idx_phone_from (phone_from),
    INDEX idx_phone_to (phone_to),
    INDEX idx_event_type (event_type),
    INDEX idx_message_type (message_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($mysqli->query($sql) === TRUE) {
    echo "✓ Table 'wh_whatsapp' created successfully in '$db'\n\n";
    
    // Show table structure
    echo "Table Structure:\n";
    $result = $mysqli->query("DESCRIBE wh_whatsapp");
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "✗ Error creating table: " . $mysqli->error . "\n";
}

$mysqli->close();
