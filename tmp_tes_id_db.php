<?php
require '/www/wwwroot/mdl/api/app/Config/Env.php';
require '/www/wwwroot/mdl/api/app/Config/DBC.php';
$cfg = DBC::getDbConfig(0);
$mysqli = new mysqli(DBC::db_host, $cfg['user'], $cfg['pass'], $cfg['db']);
if ($mysqli->connect_error) {
    die('connect fail: ' . $mysqli->connect_error);
}

echo "=== wa_messages_in (yCloud) TES ID ===\n";
$res = $mysqli->query("SELECT id, phone, text, wamid, message_id, created_at FROM wa_messages_in WHERE text LIKE '%TES ID%' ORDER BY id DESC LIMIT 5");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== wa_fonnte_messages_in TES ID ===\n";
$res2 = $mysqli->query("SELECT id, phone, text, inboxid, wa_message_id, created_at FROM wa_fonnte_messages_in WHERE text LIKE '%TES ID%' ORDER BY id DESC LIMIT 5");
while ($row = $res2->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== yCloud inbound same phone 10:30-10:32 ===\n";
$res3 = $mysqli->query("SELECT id, phone, text, wamid, message_id, created_at FROM wa_messages_in WHERE phone LIKE '%6281268098300%' AND created_at >= '2026-08-18 10:30:00' AND created_at <= '2026-08-18 10:32:30' ORDER BY id DESC LIMIT 5");
while ($row = $res3->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
