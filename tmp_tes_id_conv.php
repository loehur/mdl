<?php
require '/www/wwwroot/mdl/api/app/Config/Env.php';
require '/www/wwwroot/mdl/api/app/Config/DBC.php';
$cfg = DBC::getDbConfig(0);
$mysqli = new mysqli(DBC::db_host, $cfg['user'], $cfg['pass'], $cfg['db']);
if ($mysqli->connect_error) {
    die($mysqli->connect_error);
}

echo "=== wa_conversations matching peeKay / lid ===\n";
$q = $mysqli->query("SELECT id, wa_number, contact_name, last_message, last_in_at, last_message_at, updated_at
 FROM wa_conversations
 WHERE wa_number LIKE '%6281268098300%'
    OR wa_number LIKE '%153283%'
    OR wa_number LIKE '%lid%'
 ORDER BY id DESC LIMIT 15");
while ($row = $q->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== aliases for conv 1, 2910, 2912 ===\n";
$q2 = $mysqli->query("SELECT * FROM wa_conversation_aliases WHERE conversation_id IN (1,2910,2912) ORDER BY conversation_id, id");
while ($row = $q2->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== wa_fonnte_conversations peeKay ===\n";
$q3 = $mysqli->query("SELECT id, phone, contact_name, last_message, last_in_at, last_message_at FROM wa_fonnte_conversations WHERE phone LIKE '%6281268098300%' OR phone LIKE '%153283%' OR phone LIKE '%lid%' LIMIT 10");
while ($row = $q3->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
