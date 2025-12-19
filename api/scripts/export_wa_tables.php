<?php
// Generate SQL export for YCloud WhatsApp tables

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'mdl_main';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Generating SQL Export ===\n\n";

$tables = ['wa_webhooks', 'wa_customers', 'wa_conversations', 'wa_messages'];
$sql_export = "-- YCloud WhatsApp Tables Export\n";
$sql_export .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

$sql_export .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
    echo "Exporting $table...\n";
    
    // Drop table
    $sql_export .= "-- Table: $table\n";
    $sql_export .= "DROP TABLE IF EXISTS `$table`;\n\n";
    
    // Get CREATE TABLE statement
    $result = $mysqli->query("SHOW CREATE TABLE `$table`");
    if ($result) {
        $row = $result->fetch_row();
        $sql_export .= $row[1] . ";\n\n";
    }
}

$sql_export .= "SET FOREIGN_KEY_CHECKS = 1;\n";

// Save to file
$filename = 'wa_tables_export_' . date('Ymd_His') . '.sql';
file_put_contents(__DIR__ . '/' . $filename, $sql_export);

echo "\n✓ Export complete!\n";
echo "File: api/scripts/$filename\n\n";
echo "Upload this file to server and run:\n";
echo "mysql -u username -p mdl_main < $filename\n";

$mysqli->close();
