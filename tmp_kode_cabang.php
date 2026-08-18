<?php
require '/www/wwwroot/mdl/api/app/Config/Env.php';
require '/www/wwwroot/mdl/api/app/Config/DBC.php';
$cfg = DBC::getDbConfig(0);
$mysqli = new mysqli(DBC::db_host, $cfg['user'], $cfg['pass'], $cfg['db']);
if ($mysqli->connect_error) {
    die('crm connect fail: ' . $mysqli->connect_error . "\n");
}

$needles = ['082117278457', '6282117278457', '+6282117278457', '82117278457'];
$in = implode(',', array_map(function ($n) use ($mysqli) {
    return "'" . $mysqli->real_escape_string($n) . "'";
}, $needles));

echo "=== wa_conversations ===\n";
$q = $mysqli->query("SELECT id, wa_number, contact_name, assigned_user_id, code, cust_id, partner, last_message, last_in_at, last_message_at, updated_at
 FROM wa_conversations
 WHERE wa_number IN ($in)
    OR REPLACE(wa_number,'+','') LIKE '%82117278457'
 ORDER BY id DESC");
while ($row = $q->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== wa_fonnte_conversations ===\n";
$q2 = $mysqli->query("SELECT id, phone, contact_name, assigned_user_id, code, cust_id, last_message, last_in_at, last_message_at
 FROM wa_fonnte_conversations
 WHERE phone IN ($in) OR REPLACE(phone,'+','') LIKE '%82117278457'");
while ($row = $q2->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== aliases ===\n";
$t = $mysqli->query("SHOW TABLES LIKE 'wa_conversation_aliases'");
if ($t && $t->num_rows > 0) {
    $q3 = $mysqli->query("SELECT * FROM wa_conversation_aliases WHERE alias_value LIKE '%82117278457%' OR conversation_id IN (
      SELECT id FROM wa_conversations WHERE REPLACE(wa_number,'+','') LIKE '%82117278457'
    )");
    while ($row = $q3->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

$cfg1 = DBC::getDbConfig(1);
$laundry = new mysqli(DBC::db_host, $cfg1['user'], $cfg1['pass'], $cfg1['db']);
if ($laundry->connect_error) {
    echo "\nlaundry connect fail: " . $laundry->connect_error . "\n";
    exit(0);
}

echo "\n=== laundry pelanggan ===\n";
$q4 = $laundry->query("SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan, id_cabang FROM pelanggan WHERE REPLACE(REPLACE(REPLACE(nomor_pelanggan,'+',''),' ',''),'-','') LIKE '%82117278457' LIMIT 20");
if (!$q4) {
    echo "query fail: " . $laundry->error . "\n";
} else {
    while ($row = $q4->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        $idCabang = (int) ($row['id_cabang'] ?? 0);
        if ($idCabang > 0) {
            $c = $laundry->query("SELECT id_cabang, kode_cabang, nama FROM cabang WHERE id_cabang = $idCabang LIMIT 1");
            if ($c && $cabs = $c->fetch_assoc()) {
                echo "  cabang_pelanggan=" . json_encode($cabs, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        $idp = (int) $row['id_pelanggan'];
        $s = $laundry->query("SELECT id_sale, id_pelanggan, id_cabang, insertTime FROM sale WHERE bin = 0 AND id_pelanggan = $idp ORDER BY insertTime DESC LIMIT 3");
        if ($s) {
            while ($sale = $s->fetch_assoc()) {
                echo "  last_sale=" . json_encode($sale, JSON_UNESCAPED_UNICODE) . "\n";
                $sc = (int) ($sale['id_cabang'] ?? 0);
                if ($sc > 0) {
                    $c2 = $laundry->query("SELECT id_cabang, kode_cabang, nama FROM cabang WHERE id_cabang = $sc LIMIT 1");
                    if ($c2 && $cabs2 = $c2->fetch_assoc()) {
                        echo "  cabang_sale=" . json_encode($cabs2, JSON_UNESCAPED_UNICODE) . "\n";
                    }
                }
            }
        }
    }
}
