<?php
// Script to add sell_price and updated_at columns to inventory_purchases table
// Run this by visiting: http://localhost/mdl/scripts/add_inventory_sell_price.php

$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$dbName = 'mdl_salon';
$conn->select_db($dbName);
echo "Selected Database: $dbName\n<br>";

// 1. Add 'sell_price' column if not exists
$checkCol = $conn->query("SHOW COLUMNS FROM inventory_purchases LIKE 'sell_price'");
if ($checkCol->num_rows == 0) {
    $sql = "ALTER TABLE inventory_purchases ADD COLUMN sell_price DECIMAL(15,2) DEFAULT NULL AFTER buy_price";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'sell_price' added to inventory_purchases successfully\n<br>";
    } else {
        echo "Error adding sell_price column: " . $conn->error . "\n<br>";
    }
} else {
    echo "Column 'sell_price' already exists in inventory_purchases\n<br>";
}

// 2. Add 'updated_at' column if not exists
$checkCol = $conn->query("SHOW COLUMNS FROM inventory_purchases LIKE 'updated_at'");
if ($checkCol->num_rows == 0) {
    $sql = "ALTER TABLE inventory_purchases ADD COLUMN updated_at DATETIME DEFAULT NULL AFTER created_at";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'updated_at' added to inventory_purchases successfully\n<br>";
    } else {
        echo "Error adding updated_at column: " . $conn->error . "\n<br>";
    }
} else {
    echo "Column 'updated_at' already exists in inventory_purchases\n<br>";
}

// 3. Add 'item_id' column if not exists (for grouping)
$checkCol = $conn->query("SHOW COLUMNS FROM inventory_purchases LIKE 'item_id'");
if ($checkCol->num_rows == 0) {
    $sql = "ALTER TABLE inventory_purchases ADD COLUMN item_id VARCHAR(32) DEFAULT NULL AFTER transaction_id";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'item_id' added to inventory_purchases successfully\n<br>";
    } else {
        echo "Error adding item_id column: " . $conn->error . "\n<br>";
    }
} else {
    echo "Column 'item_id' already exists in inventory_purchases\n<br>";
}

// 4. Add index on item_id if not exists
$checkIndex = $conn->query("SHOW INDEX FROM inventory_purchases WHERE Key_name = 'idx_item_id'");
if ($checkIndex->num_rows == 0) {
    $sql = "ALTER TABLE inventory_purchases ADD INDEX idx_item_id (item_id)";
    if ($conn->query($sql) === TRUE) {
        echo "Index 'idx_item_id' added successfully\n<br>";
    } else {
        echo "Error adding index: " . $conn->error . "\n<br>";
    }
} else {
    echo "Index 'idx_item_id' already exists\n<br>";
}

echo "\n<br><strong>Done!</strong> Migration completed successfully.";

$conn->close();
?>
