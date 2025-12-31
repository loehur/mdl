<?php
$db = new mysqli('localhost', 'root', '', 'mdl_salon');
if ($db->connect_error) die('Connect Error');

// Add item_id if not exists
$check = $db->query("SHOW COLUMNS FROM inventory_purchases LIKE 'item_id'");
if ($check->num_rows == 0) {
    $db->query("ALTER TABLE inventory_purchases ADD COLUMN item_id VARCHAR(50) AFTER transaction_id");
    echo "Column item_id added successfully." . PHP_EOL;
} else {
    echo "Column item_id already exists." . PHP_EOL;
}

$res = $db->query('DESCRIBE inventory_purchases');
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}
