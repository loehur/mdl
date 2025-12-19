<?php
/**
 * Public Log Viewer Dashboard
 * Access: https://nalju.com/check_wa_log.php
 */

date_default_timezone_set('Asia/Jakarta');
$logDir = __DIR__ . '/api/logs/';

// Map readable names to filenames
$logFiles = [
    'Outbound Logs' => 'wa_outbound_errors.log', 
    'SQL Debug (DATABASE)' => 'db_debug.log',     // <--- INI YG KITA CARI
    'Webhook Payload' => 'wa_webhook_debug.log',
    'Webhook Access' => 'wa_webhook_access.log',
    'API Failures' => 'wa_debug_api.log',
    'Login Failures' => 'wa_debug_login.log'
];

$refreshInterval = 5; // seconds
?>
<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp Integration Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; margin:0; }
        .container { max-width: 100%; margin: 0 auto; }
        h1 { color: #4ec9b0; margin-bottom: 20px; border-bottom: 1px solid #333; padding-bottom: 10px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; } 
        .card { background: #252526; border: 1px solid #3e3e42; border-radius: 5px; overflow: hidden; display: flex; flex-direction: column; height: 600px; }
        .card-header { background: #333333; padding: 10px 15px; font-weight: bold; border-bottom: 1px solid #3e3e42; display: flex; justify-content: space-between; align-items: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-body { padding: 0; overflow-y: auto; flex-grow: 1; font-size: 11px; }
        .log-entry { padding: 4px 8px; border-bottom: 1px solid #2d2d2d; white-space: pre-wrap; word-break: break-all; }
        .log-entry:hover { background: #2a2d2e; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; font-weight: bold; }
        .info { color: #569cd6; }
        .sql { color: #ffd700; font-weight: bold; }
        .timestamp { color: #808080; margin-right: 5px; }
        .badge { background: #444; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
        .empty { padding: 20px; text-align: center; color: #666; font-style: italic; }
    </style>
    <script>
        window.onload = function() {
            document.querySelectorAll('.card-body').forEach(el => el.scrollTop = el.scrollHeight);
        }
    </script>
</head>
<body>
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>📝 WhatsApp Logs Dashboard</h1>
            <div>
                <a href="?refresh=1" style="background:#0e639c; color:white; padding:8px 15px; text-decoration:none; border-radius:3px;">🔄 Refresh</a>
            </div>
        </div>

        <div class="grid">
            <?php foreach ($logFiles as $title => $filename): ?>
            <?php 
                $path = $logDir . $filename;
                $exists = file_exists($path);
                $content = $exists ? file_get_contents($path) : '';
                $lines = $content ? explode("\n", trim($content)) : [];
                $count = count($lines);
                // Get last 50 lines
                $recent = array_slice($lines, -50);
            ?>
            <div class="card" style="<?php echo strpos($title, 'SQL')!==false ? 'border: 2px solid #ffd700;' : ''; ?>">
                <div class="card-header">
                    <span title="<?php echo $title; ?>"><?php echo $title; ?></span>
                    <span class="badge"><?php echo $count; ?></span>
                </div>
                <div class="card-body">
                    <?php if (!$exists || empty($content)): ?>
                        <div class="empty">Log empty<br><small><?php echo $filename; ?></small></div>
                    <?php else: ?>
                        <?php foreach ($recent as $line): ?>
                            <?php 
                                if (empty(trim($line))) continue;
                                $class = '';
                                if (stripos($line, 'success') !== false) $class = 'success';
                                elseif (stripos($line, 'error') !== false) $class = 'error';
                                elseif (stripos($line, 'SQL') !== false) $class = 'sql';
                                
                                $lineHtml = htmlspecialchars($line);
                                $lineHtml = preg_replace('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', '<span class="timestamp">$1</span>', $lineHtml);
                            ?>
                            <div class="log-entry <?php echo $class; ?>"><?php echo $lineHtml; ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>setTimeout(() => window.location.reload(), <?php echo $refreshInterval * 1000; ?>);</script>
</body>
</html>
