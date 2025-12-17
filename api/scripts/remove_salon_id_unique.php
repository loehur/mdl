<?php
// Remove unique constraint from salon_id
require_once __DIR__ . '/app/init.php';

use App\Core\DB;

$db = DB::getInstance(5); // Salon DB

try {
    // Drop unique index
    $sql = "ALTER TABLE users DROP INDEX idx_salon_id";
    $db->query($sql);
    
    echo "✓ Unique constraint removed from salon_id!\n";
    echo "Now multiple users can have the same salon_id.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "(Index might not exist)\n";
}
