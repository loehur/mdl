<?php
// Debug script to check inventory_sales data

$conn = new mysqli('localhost', 'root', '', 'mdl_salon');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h3>Inventory Sales Records:</h3>";
$result = $conn->query("SELECT * FROM inventory_sales ORDER BY id DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    echo "<pre>";
    while($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "No inventory_sales records found<br>";
}

echo "<h3>Recent Completed Orders with Inventory Items:</h3>";
$orders = $conn->query("SELECT id, salon_id, status, order_items FROM orders WHERE status = 'completed' ORDER BY id DESC LIMIT 5");
if ($orders && $orders->num_rows > 0) {
    while($row = $orders->fetch_assoc()) {
        echo "Order #" . $row['id'] . " (salon: " . $row['salon_id'] . ")<br>";
        $items = json_decode($row['order_items'], true);
        foreach ($items as $item) {
            if (isset($item['item_id'])) {
                echo "  - [INVENTORY] item_id: " . $item['item_id'] . ", name: " . ($item['product_name'] ?? 'N/A') . ", qty: " . ($item['qty'] ?? 1) . "<br>";
            } else {
                echo "  - [SERVICE] " . ($item['product_name'] ?? 'N/A') . "<br>";
            }
        }
        echo "<br>";
    }
} else {
    echo "No completed orders found<br>";
}

$conn->close();
?>
