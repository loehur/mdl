<?php
/**
 * Public Log Viewer
 * Access: https://nalju.com/check_wa_log.php
 */

$logFile = __DIR__ . '/api/logs/wa_outbound_errors.log';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>WA Outbound Log</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; font-weight: bold; }
        .info { color: #569cd6; }
        pre { background: #252526; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>📝 WhatsApp Outbound Log</h1>
    <p>File: <?php echo $logFile; ?></p>
    <hr>
    
<?php
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    
    // Show last 100 lines
    $recentLines = array_slice($lines, -100);
    
    echo "<pre>";
    foreach ($recentLines as $line) {
        if (empty(trim($line))) continue;
        
        $class = '';
        if (stripos($line, 'ERROR') !== false || stripos($line, 'EXCEPTION') !== false) {
            $class = 'error';
        } elseif (stripos($line, 'SUCCESS') !== false || stripos($line, '✓') !== false) {
            $class = 'success';
        } elseif (stripos($line, '===') !== false) {
            $class = 'info';
        }
        
        echo '<span class="' . $class . '">' . htmlspecialchars($line) . '</span>' . "\n";
    }
    echo "</pre>";
    
    echo "<hr>";
    echo "<p>Total lines: " . count($lines) . "</p>";
    echo "<p>File size: " . number_format(filesize($logFile)) . " bytes</p>";
    echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "</p>";
    
} else {
    echo "<pre class='error'>Log file not found: $logFile</pre>";
    echo "<p>Make sure api/logs/ directory exists and is writable.</p>";
}
?>

    <hr>
    <p><a href="?refresh=1">🔄 Refresh</a></p>
    <p style="color: #808080;">Auto-refresh every 5 seconds...</p>
    
    <script>
        setTimeout(() => window.location.reload(), 5000);
    </script>
</body>
</html>
