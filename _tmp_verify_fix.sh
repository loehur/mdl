#!/bin/bash
ENV_FILE="/www/wwwroot/mdl/api/app/Config/Env.php"
eval "$(php -r "
require '$ENV_FILE';
\$c = Env::DB_CREDENTIALS['pro'][7];
echo 'DB_USER=' . escapeshellarg(\$c['user']) . ';';
echo 'DB_PASS=' . escapeshellarg(\$c['pass']) . ';';
echo 'DB_NAME=' . escapeshellarg(\$c['db']) . ';';
")"

echo "=== Fixed Templates query (team 12, channel 9) ==="
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "
SELECT k.device_id FROM wa_channels k
WHERE k.id=9 AND k.tenant_id=1 AND k.status='active'
AND (k.team_id=12 OR EXISTS (SELECT 1 FROM wa_channel_teams ct WHERE ct.channel_id=k.id AND ct.team_id=12))
LIMIT 1;
" 2>/dev/null

echo "=== Broken query (no alias k) should fail ==="
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "
SELECT device_id FROM wa_channels
WHERE id=9 AND tenant_id=1 AND status='active'
AND (k.team_id=12 OR EXISTS (SELECT 1 FROM wa_channel_teams ct WHERE ct.channel_id=k.id AND ct.team_id=12))
LIMIT 1;
" 2>&1 | head -2
