<?php
/**
 * Simple SQL Log Viewer
 * Access: https://nalju.com/debug_sql.php
 */

$logFile = __DIR__ . '/api/logs/db_debug.log';

echo "<h1>🔍 Database Query Log</h1>";
echo "<p>Menampilkan query UPDATE ke tabel wa_messages_out</p>";
echo "<hr>";
echo "<pre style='background:#eee; padding:10px; border:1px solid #ccc; white-space:pre-wrap;'>";

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if (empty(trim($content))) {
        echo "Log file kosong. Coba kirim WA dulu.";
    } else {
        echo htmlspecialchars($content);
    }
} else {
    echo "File log belum ada ($logFile). Pastikan Anda sudah kirim WA baru setelah update kode.";
}

echo "</pre>";
