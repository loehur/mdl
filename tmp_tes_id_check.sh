#!/bin/bash
echo '=== FONNTE log last 15 ==='
tail -15 /www/wwwroot/mdl/api/logs/2026-08-18/webhook_fonnte.log
echo
echo '=== AUTOREPLY last 20 with PeeKay phone ==='
grep '6281268098300' /www/wwwroot/mdl/api/logs/2026-08-18/wa_autoreply.log | tail -20
echo
echo '=== conversations for peeKay / lid ==='
php /tmp/tmp_tes_id_conv.php
