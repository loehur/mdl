-- WaDesk: backfill wa_channel_teams dari team utama channel (idempotent).
-- Jalankan setelah 021 jika join table belum lengkap. Backup dulu.

SET NAMES utf8mb4;

INSERT IGNORE INTO wa_channel_teams (channel_id, team_id)
SELECT id, team_id FROM wa_channels WHERE team_id IS NOT NULL;
