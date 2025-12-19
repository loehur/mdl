<?php
/**
 * Direct DB Row Viewer
 * Access: https://nalju.com/check_db_row.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT', __DIR__);

// Manual loading of required classes since we are outside the framework
require_once ROOT . '/laundry/app/Config/Env.php'; // Use laundry config for Env
// Or try api/app/Config? api structure implies it has its own.
// Let's try raw connection to avoid dependency hell
$host = 'localhost';
$user = 'mdl_main'; // From previous context
$pass = 'wB5KjfjRYfPXBtFF';
$db   = 'mdl_main';

echo "<h1>🕵️ DB Row Inspector</h1>";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("<h2 style='color:red'>Connection Failed: " . $conn->connect_error . "</h2>");
}

echo "<p style='color:green'>Database Connected.</p>";

// Query Table Baru
$sql = "SELECT id, phone, status, sent_at, delivered_at, read_at, wamid, message_id FROM wa_messages_out ORDER BY id DESC LIMIT 10";
$result = $conn->query($sql);

if (!$result) {
    die("<h2 style='color:red'>Query Failed: " . $conn->error . "</h2><p>Pastikan tabel <b>wa_messages_out</b> sudah dibuat.</p>");
}

echo "<h3>Top 10 Rows from 'wa_messages_out' (Tabel Baru)</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse; font-family:sans-serif;'>";
echo "<tr style='background:#ddd'><th>ID</th><th>Phone</th><th>Status</th><th>Sent At</th><th>Delivered At</th><th>Read At</th><th>Message ID (Short)</th></tr>";

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $sentStyle = $row['sent_at'] ? "color:green;font-weight:bold" : "color:red;background:#ffe";
        $delStyle = $row['delivered_at'] ? "color:green" : "color:#aaa";
        $readStyle = $row['read_at'] ? "color:green" : "color:#aaa";
        
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['phone']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td style='$sentStyle'>" . ($row['sent_at'] ?? 'NULL') . "</td>";
        echo "<td style='$delStyle'>" . ($row['delivered_at'] ?? 'NULL') . "</td>";
        echo "<td style='$readStyle'>" . ($row['read_at'] ?? 'NULL') . "</td>";
        echo "<td>" . substr($row['message_id'], 0, 8) . "...</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='7'>Tabel kosong</td></tr>";
}
echo "</table>";

$conn->close();
