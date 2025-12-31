<?php
// Script to create inventory_sales table
// Run this by visiting: http://localhost/mdl/scripts/create_inventory_sales.php

$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$dbName = 'mdl_salon';
$conn->select_db($dbName);
echo "Selected Database: $dbName\n<br>";

// Create inventory_sales table
$sql = "CREATE TABLE IF NOT EXISTS inventory_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL,
    order_id INT NOT NULL,
    item_id VARCHAR(32) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    sell_price DECIMAL(15,2) NOT NULL,
    buy_price DECIMAL(15,2) DEFAULT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_salon_id (salon_id),
    INDEX idx_order_id (order_id),
    INDEX idx_item_id (item_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table inventory_sales created successfully\n<br>";
} else {
    echo "Error creating table: " . $conn->error . "\n<br>";
}

echo "\n<br><strong>Done!</strong> Migration completed successfully.";

$conn->close();
?>
