<?php
/**
 * View WhatsApp Outbound Error Log
 * Access: https://nalju.com/api/view_outbound_log.php
 */

$logFile = __DIR__ . '/logs/wa_outbound_errors.log';

?>
<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp Outbound Log</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .log-container {
            background: #252526;
            border: 1px solid #3e3e42;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            max-height: 600px;
            overflow-y: auto;
        }
        .log-line {
            margin: 5px 0;
            line-height: 1.5;
        }
        .error {
            color: #f48771;
            font-weight: bold;
        }
        .success {
            color: #4ec9b0;
            font-weight: bold;
        }
        .info {
            color: #569cd6;
        }
        .timestamp {
            color: #808080;
        }
        .stats {
            background: #2d2d30;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .stats h3 {
            margin-top: 0;
            color: #ce9178;
        }
        .stat-item {
            margin: 10px 0;
        }
        .button {
            background: #0e639c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .button:hover {
            background: #1177bb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 WhatsApp Outbound Error Log</h1>
        
        <div>
            <a href="?refresh=1" class="button">🔄 Refresh</a>
            <a href="?clear=1" class="button" onclick="return confirm('Clear log file?')">🗑️ Clear Log</a>
        </div>

<?php
// Handle clear
if (isset($_GET['clear'])) {
    file_put_contents($logFile, '');
    echo "<p style='color: #4ec9b0;'>✅ Log cleared!</p>";
}

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    
    // Count stats
    $totalAttempts = substr_count($content, '=== SAVE OUTBOUND MESSAGE START ===');
    $successCount = substr_count($content, '✓✓✓ SUCCESS!');
    $errorCount = substr_count($content, 'ERROR:');
    $exceptionCount = substr_count($content, 'EXCEPTION:');
    
    // Show stats
    echo '<div class="stats">';
    echo '<h3>📊 Statistics</h3>';
    echo '<div class="stat-item">Total Attempts: <strong>' . $totalAttempts . '</strong></div>';
    echo '<div class="stat-item"><span class="success">✓ Success: ' . $successCount . '</span></div>';
    echo '<div class="stat-item"><span class="error">✗ Errors: ' . $errorCount . '</span></div>';
    echo '<div class="stat-item"><span class="error">⚠ Exceptions: ' . $exceptionCount . '</span></div>';
    
    if ($totalAttempts > 0) {
        $successRate = round(($successCount / $totalAttempts) * 100, 1);
        echo '<div class="stat-item">Success Rate: <strong>' . $successRate . '%</strong></div>';
    }
    
    echo '</div>';
    
    // Show log
    echo '<div class="log-container">';
    
    // Get last 200 lines
    $recentLines = array_slice($lines, -200);
    
    foreach ($recentLines as $line) {
        if (empty(trim($line))) continue;
        
        $class = '';
        if (strpos($line, 'ERROR:') !== false || strpos($line, 'EXCEPTION:') !== false) {
            $class = 'error';
        } elseif (strpos($line, '✓') !== false || strpos($line, 'SUCCESS') !== false) {
            $class = 'success';
        } elseif (strpos($line, '===') !== false) {
            $class = 'info';
        }
        
        // Highlight timestamp
        $line = preg_replace('/\[([\d\-\: ]+)\]/', '<span class="timestamp">[$1]</span>', $line);
        
        echo '<div class="log-line ' . $class . '">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    
    echo '</div>';
    
    echo '<p style="color: #808080;">Showing last ' . count($recentLines) . ' lines</p>';
    echo '<p style="color: #808080;">Log file: ' . $logFile . '</p>';
    echo '<p style="color: #808080;">File size: ' . number_format(filesize($logFile)) . ' bytes</p>';
    
} else {
    echo '<div class="log-container">';
    echo '<p class="error">⚠️ Log file not found: ' . $logFile . '</p>';
    echo '<p>No outbound messages attempted yet or logging failed.</p>';
    echo '<p>Make sure api/logs/ directory is writable.</p>';
    echo '</div>';
}
?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #3e3e42; color: #808080;">
            <p>Auto-refresh: <a href="?refresh=1" class="button">Refresh Now</a></p>
            <p>Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
