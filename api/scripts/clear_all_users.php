<?php
// Clear all users from salon database
require_once __DIR__ . '/app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    $sql = "TRUNCATE TABLE users";
    $db->query($sql);
    
    echo "✓ All users deleted successfully!\n";
    echo "You can now register new users with salon_id.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
