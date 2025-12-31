<?php
// Script to fix salon_id column type in inventory_sales

$conn = new mysqli('localhost', 'root', '', 'mdl_salon');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Selected Database: mdl_salon\n<br>";

// 1. Fix column type to BIGINT
$sql = "ALTER TABLE inventory_sales MODIFY COLUMN salon_id BIGINT NOT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Column 'salon_id' changed to BIGINT successfully\n<br>";
} else {
    echo "Error modifying column: " . $conn->error . "\n<br>";
}

// 2. Fix existing records - get the correct salon_id from orders table
$sql = "UPDATE inventory_sales isale 
        JOIN orders o ON isale.order_id = o.id 
        SET isale.salon_id = o.salon_id";

if ($conn->query($sql) === TRUE) {
    echo "Fixed " . $conn->affected_rows . " existing inventory_sales records\n<br>";
} else {
    echo "Error fixing records: " . $conn->error . "\n<br>";
}

echo "\n<br><strong>Done!</strong> Migration completed successfully.";

$conn->close();
?>
