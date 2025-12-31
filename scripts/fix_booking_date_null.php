<?php
// Script to fix booking_date column to allow NULL
// Run this by visiting: http://localhost/mdl/scripts/fix_booking_date_null.php

$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$dbName = 'mdl_salon';
$conn->select_db($dbName);
echo "Selected Database: $dbName\n<br>";

// Modify booking_date to allow NULL
$sql = "ALTER TABLE orders MODIFY COLUMN booking_date DATETIME DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Column 'booking_date' modified to allow NULL successfully\n<br>";
} else {
    echo "Error modifying column: " . $conn->error . "\n<br>";
}

echo "\n<br><strong>Done!</strong> Migration completed successfully.";

$conn->close();
?>
