#!/bin/bash
echo '=== TODAY non-text ==='
grep -n 'non-text message' /www/wwwroot/mdl/api/logs/2026-08-18/webhook_fonnte.log
echo '=== TODAY media placeholder ==='
grep -n 'media placeholder' /www/wwwroot/mdl/api/logs/2026-08-18/webhook_fonnte.log
echo '=== RECENT DAYS with real extension ==='
for d in 2026-08-11 2026-08-12 2026-08-13 2026-08-14 2026-08-15 2026-08-16 2026-08-17 2026-08-18; do
  f="/www/wwwroot/mdl/api/logs/$d/webhook_fonnte.log"
  if [ -f "$f" ]; then
    echo "-- $d --"
    grep -E 'extension.:.[^"]+' "$f" | grep -v 'extension.:.""' | grep -v stateid | tail -5
  fi
done
echo '=== SAMPLE IMAGE PAYLOAD ==='
grep -h 'jpg\|jpeg\|png\|webp' /www/wwwroot/mdl/api/logs/2026-08-1*/webhook_fonnte.log | grep inbound | tail -8
