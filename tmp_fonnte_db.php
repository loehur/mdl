<?php
require '/www/wwwroot/mdl/api/app/Config/Env.php';
require '/www/wwwroot/mdl/api/app/Config/DBC.php';
$cfg = DBC::getDbConfig(0);
$host = DBC::db_host;
$mysqli = new mysqli($host, $cfg['user'], $cfg['pass'], $cfg['db']);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'connect fail: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
echo "db={$cfg['db']} host={$host}\n";
$sql = "SELECT id, phone, type, text, media_url, media_filename, media_extension, inboxid, created_at
        FROM wa_fonnte_messages_in
        WHERE inboxid IN (210702141, 210494735, 210503324, 210588416)
           OR (text LIKE '%cek caption%')
        ORDER BY id DESC
        LIMIT 20";
$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, 'query fail: ' . $mysqli->error . PHP_EOL);
    exit(1);
}
echo "---- match ----\n";
$n = 0;
while ($row = $res->fetch_assoc()) {
    $n++;
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo "count=$n\n";
echo "---- recent media ----\n";
$res2 = $mysqli->query("SELECT id, phone, type, LEFT(IFNULL(text,''),80) AS text, IFNULL(media_url,'') AS media_url, IFNULL(media_extension,'') AS media_extension, inboxid, created_at
                        FROM wa_fonnte_messages_in
                        WHERE type <> 'text' OR media_url IS NOT NULL AND media_url <> ''
                        ORDER BY id DESC LIMIT 8");
while ($row = $res2->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
