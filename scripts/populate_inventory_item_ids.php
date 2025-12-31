<?php
$db = new mysqli('localhost', 'root', '', 'mdl_salon');
if ($db->connect_error) die('Connect Error');

// Get all rows with NULL or empty item_id
$res = $db->query("SELECT id, item_name, buy_price FROM inventory_purchases WHERE item_id IS NULL OR item_id = ''");

while ($row = $res->fetch_assoc()) {
    $id = $row['id'];
    $name = $row['item_name'];
    $price = $row['buy_price'];
    
    // Check if we already have an item_id for this combination in the same table (that was already processed)
    $existing = $db->query("SELECT item_id FROM inventory_purchases WHERE LOWER(item_name) = LOWER('" . $db->real_escape_string($name) . "') AND buy_price = " . (float)$price . " AND item_id IS NOT NULL AND item_id != '' LIMIT 1")->fetch_assoc();
    
    if ($existing) {
        $itemId = $existing['item_id'];
    } else {
        $itemId = 'ITEM-' . strtoupper(substr(md5(uniqid(microtime(), true)), 0, 8));
    }
    
    $db->query("UPDATE inventory_purchases SET item_id = '" . $db->real_escape_string($itemId) . "' WHERE id = $id");
}

echo "Populated item_id for existing records." . PHP_EOL;
