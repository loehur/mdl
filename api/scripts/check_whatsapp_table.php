<?php
require_once __DIR__ . '/../app/init.php';

use App\Core\DB;

echo "=== WhatsApp Table Check ===\n\n";

try {
    $db = DB::getInstance(0);
    
    // Get current database name
    $result = $db->query("SELECT DATABASE()");
    $row = $result->row_array();
    echo "Connected to database: " . $row['DATABASE()'] . "\n\n";
    
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'wh_whatsapp'");
    if ($tableCheck->num_rows() > 0) {
        echo "✓ Table 'wh_whatsapp' EXISTS\n\n";
        
        // Show table structure
        echo "Table Structure:\n";
        $structure = $db->query("DESCRIBE wh_whatsapp");
        foreach ($structure->result_array() as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        
        // Count records
        echo "\n";
        $count = $db->query("SELECT COUNT(*) as total FROM wh_whatsapp");
        $countRow = $count->row_array();
        echo "Total records: " . $countRow['total'] . "\n\n";
        
        // Show last 5 records if any
        if ($countRow['total'] > 0) {
            echo "Last 5 records:\n";
            $records = $db->query("SELECT id, phone, sender_name, type, status, created_at FROM wh_whatsapp ORDER BY id DESC LIMIT 5");
            foreach ($records->result_array() as $rec) {
                echo "  ID: {$rec['id']}, Phone: {$rec['phone']}, Name: {$rec['sender_name']}, Type: {$rec['type']}, Status: {$rec['status']}, Created: {$rec['created_at']}\n";
            }
        }
    } else {
        echo "✗ Table 'wh_whatsapp' DOES NOT EXIST\n";
        echo "Run migrate_whatsapp_table.php to create it.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
