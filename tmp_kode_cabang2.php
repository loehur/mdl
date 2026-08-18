<?php
require '/www/wwwroot/mdl/api/app/Config/Env.php';
require '/www/wwwroot/mdl/api/app/Config/DBC.php';
$cfg1 = DBC::getDbConfig(1);
$laundry = new mysqli(DBC::db_host, $cfg1['user'], $cfg1['pass'], $cfg1['db']);
if ($laundry->connect_error) {
    die($laundry->connect_error);
}

echo "=== cabang 3,11,13 ===\n";
$c = $laundry->query("SELECT id_cabang, kode_cabang, nama FROM cabang WHERE id_cabang IN (3,11,13)");
while ($row = $c->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== all pelanggan match ===\n";
$q = $laundry->query("SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan, id_cabang FROM pelanggan WHERE REPLACE(REPLACE(REPLACE(nomor_pelanggan,'+',''),' ',''),'-','') LIKE '%82117278457'");
$ids = [];
while ($row = $q->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    $ids[] = (int) $row['id_pelanggan'];
}

echo "\n=== pelanggan ids 13346, 11570, 11467 ===\n";
$q2 = $laundry->query("SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan, id_cabang FROM pelanggan WHERE id_pelanggan IN (13346,11570,11467,11467)");
while ($row = $q2->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

$cols = $laundry->query("SHOW COLUMNS FROM sale");
$names = [];
while ($col = $cols->fetch_assoc()) {
    $names[] = $col['Field'];
}
echo "\nsale columns sample: " . implode(',', array_slice($names, 0, 25)) . "\n";

$idCol = in_array('id', $names, true) ? 'id' : (in_array('id_penjualan', $names, true) ? 'id_penjualan' : $names[0]);
$timeCol = in_array('insertTime', $names, true) ? 'insertTime' : (in_array('insert_time', $names, true) ? 'insert_time' : 'id');

$idsIn = $ids ? implode(',', $ids) : '0';
echo "\n=== last sales for matched pelanggan ($idsIn) ===\n";
$sql = "SELECT $idCol AS sid, id_pelanggan, id_cabang, $timeCol AS t FROM sale WHERE bin = 0 AND id_pelanggan IN ($idsIn) ORDER BY $timeCol DESC LIMIT 8";
$s = $laundry->query($sql);
if (!$s) {
    echo $laundry->error . "\n$sql\n";
} else {
    while ($row = $s->fetch_assoc()) {
        $sc = (int) $row['id_cabang'];
        $cab = '';
        if ($sc > 0) {
            $cx = $laundry->query("SELECT kode_cabang FROM cabang WHERE id_cabang = $sc LIMIT 1")->fetch_assoc();
            $cab = $cx['kode_cabang'] ?? '';
        }
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . " kode=$cab\n";
    }
}

echo "\n=== last sale 13346 / 11570 ===\n";
$sql2 = "SELECT $idCol AS sid, id_pelanggan, id_cabang, $timeCol AS t FROM sale WHERE bin = 0 AND id_pelanggan IN (13346,11570,11467) ORDER BY $timeCol DESC LIMIT 10";
$s2 = $laundry->query($sql2);
if ($s2) {
    while ($row = $s2->fetch_assoc()) {
        $sc = (int) $row['id_cabang'];
        $cx = $laundry->query("SELECT kode_cabang FROM cabang WHERE id_cabang = $sc LIMIT 1")->fetch_assoc();
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . " kode=" . ($cx['kode_cabang'] ?? '') . "\n";
    }
}
