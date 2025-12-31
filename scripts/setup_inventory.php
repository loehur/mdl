<?php
// Script to setup Inventory Purchases table and Category
// Run this by visiting in browser or CLI

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'beauty_salon_db'); // Assuming DB name based on context, will verify or use connection details from config if possible. 
// Actually, I don't know the DB name for sure from previous context, but likely 'beauty_salon_db' or similar.
// Wait, the controller uses $this->db(5). 
// I should use the existing DB connection structure or just try standard generic logic if I can run it via existing app context.
// But I can't easily run arbitrary PHP in app context without a route.
// I will assume standard XAMPP credentials and try to connect to 'beauty_salon_db' or 'mdl_beauty_salon'.
// Let's check a config file if possible? No time.
// I'll try to use a standalone script that connects to 'localhost', 'root', '' and lists dbs or tries 'beauty_salon'.

$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Find DB
$dbName = 'mdl_salon'; // Explicitly set based on Config/DBC.php

$conn->select_db($dbName);
echo "Selected Database: $dbName\n";

// 1. Create table inventory_purchases
$sql = "CREATE TABLE IF NOT EXISTS inventory_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    buy_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (transaction_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table inventory_purchases created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// 2. Insert Category 'Persediaan Barang' if not exists
// Check if exists
$catName = 'Persediaan Barang';
$check = $conn->query("SELECT id FROM expense_categories WHERE name = '$catName'");
if ($check->num_rows == 0) {
    // is_expense = 0 (Non Biaya), is_active = 1
    $sql = "INSERT INTO expense_categories (name, is_expense, description, is_active, created_at) 
            VALUES ('$catName', 0, 'Pembelian stok barang untuk dijual kembali', 1, NOW())";
    if ($conn->query($sql) === TRUE) {
        echo "Category '$catName' added successfully\n";
    } else {
        echo "Error adding category: " . $conn->error . "\n";
    }
} else {
    echo "Category '$catName' already exists\n";
}

// 3. Add 'stock' column to products if not exists
$checkCol = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
if ($checkCol->num_rows == 0) {
    $sql = "ALTER TABLE products ADD COLUMN stock INT DEFAULT 0 AFTER price";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'stock' added to products successfully\n";
    } else {
        echo "Error adding stock column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'stock' already exists in products\n";
}

$conn->close();
?>
