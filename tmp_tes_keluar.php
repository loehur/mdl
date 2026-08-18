<?php
require '/www/wwwroot/mdl/api/app/Config/Env.php';
require '/www/wwwroot/mdl/api/app/Config/DBC.php';
$cfg = DBC::getDbConfig(0);
$mysqli = new mysqli(DBC::db_host, $cfg['user'], $cfg['pass'], $cfg['db']);
if ($mysqli->connect_error) {
    die($mysqli->connect_error);
}

echo "=== wa_fonnte_messages_out TES KELUAR ===\n";
$q = $mysqli->query("SELECT id, phone, type, text, fonnte_message_id, fonnte_stateid, source, sender_code, status, created_at FROM wa_fonnte_messages_out WHERE text LIKE '%TES KELUAR%' ORDER BY id DESC LIMIT 10");
if (!$q) {
    echo $mysqli->error . "\n";
} else {
    $n = 0;
    while ($row = $q->fetch_assoc()) {
        $n++;
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "count=$n\n";
}

echo "\n=== wa_fonnte_messages_in TES KELUAR ===\n";
$q2 = $mysqli->query("SELECT id, phone, text, inboxid, created_at FROM wa_fonnte_messages_in WHERE text LIKE '%TES KELUAR%' ORDER BY id DESC LIMIT 5");
while ($q2 && $row = $q2->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== wa_messages_out TES KELUAR ===\n";
$q3 = $mysqli->query("SELECT id, phone, LEFT(IFNULL(content,''),80) AS content, created_at FROM wa_messages_out WHERE content LIKE '%TES KELUAR%' ORDER BY id DESC LIMIT 5");
if (!$q3) {
    echo $mysqli->error . "\n";
} else {
    while ($row = $q3->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
