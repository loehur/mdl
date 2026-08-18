<?php
require '/www/wwwroot/mdl/api/app/Config/Env.php';
require '/www/wwwroot/mdl/api/app/Config/DBC.php';
$cfg = DBC::getDbConfig(0);
$mysqli = new mysqli(DBC::db_host, $cfg['user'], $cfg['pass'], $cfg['db']);
if ($mysqli->connect_error) {
    die('connect fail: ' . $mysqli->connect_error . "\n");
}

echo "=== wa_messages_in TES ID ===\n";
$res = $mysqli->query("SELECT id, phone, text, wamid, message_id, created_at FROM wa_messages_in WHERE text LIKE '%TES ID%' ORDER BY id DESC LIMIT 8");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== wa_fonnte_messages_in TES ID ===\n";
$res2 = $mysqli->query("SELECT id, phone, text, inboxid, wa_message_id, created_at FROM wa_fonnte_messages_in WHERE text LIKE '%TES ID%' ORDER BY id DESC LIMIT 8");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "query fail: " . $mysqli->error . "\n";
}

echo "\n=== aliases table exists? ===\n";
$t = $mysqli->query("SHOW TABLES LIKE 'wa_conversation_aliases'");
echo ($t && $t->num_rows > 0) ? "YES\n" : "NO\n";
if ($t && $t->num_rows > 0) {
    $a = $mysqli->query("SELECT * FROM wa_conversation_aliases WHERE alias_value LIKE '%6281268098300%' OR alias_value LIKE '%153283%' OR source IN ('ycloud','fonnte') ORDER BY id DESC LIMIT 20");
    while ($row = $a->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== recent fonnte in 10:30+ ===\n";
$r3 = $mysqli->query("SELECT id, phone, text, inboxid, created_at FROM wa_fonnte_messages_in WHERE created_at >= '2026-08-18 10:30:00' ORDER BY id DESC LIMIT 10");
while ($row = $r3->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
