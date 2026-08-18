#!/bin/bash
set -e
LOGDIR=/www/wwwroot/mdl/api/logs/2026-08-18

echo '=== wa_autoreply 10:31 ==='
grep '10:31' "$LOGDIR/wa_autoreply.log" 2>/dev/null | head -20 || true

echo
echo '=== all log files with TES ID ==='
grep -l -i 'TES ID' "$LOGDIR"/*.log 2>/dev/null || true

echo
echo '=== DB via tmp php ==='
cat > /tmp/tes_id_db.php <<'PHPEOF'
<?php
chdir('/www/wwwroot/mdl/api');
require_once 'app/Core/DB.php';
$db = new App\Core\DB(0);

echo "--- wa_messages_in (yCloud) ---\n";
$q = $db->query("SELECT id, phone, text, wamid, message_id, created_at FROM wa_messages_in WHERE text LIKE '%TES ID%' ORDER BY id DESC LIMIT 5");
foreach ($q->result() as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "--- wa_fonnte_messages_in ---\n";
$q2 = $db->query("SELECT id, phone, text, inboxid, wa_message_id, created_at FROM wa_fonnte_messages_in WHERE text LIKE '%TES ID%' ORDER BY id DESC LIMIT 5");
foreach ($q2->result() as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
PHPEOF
php /tmp/tes_id_db.php 2>&1 || echo 'php failed'

echo
echo '=== nginx/api access log grep TES (if any) ==='
grep -i 'Webhook/WhatsApp\|WA_Fonnte' /www/wwwlogs/*.log 2>/dev/null | grep '18/Aug/2026:10:3' | tail -10 || true
