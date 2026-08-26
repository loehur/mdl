-- WaDesk: 1 nomor (channel) bisa di-assign ke beberapa team + routing pesan masuk
-- ke conversation team yang terakhir aktif.
-- Run on mdl_wadesk (after 020 or fresh schema). Backup dulu.

SET NAMES utf8mb4;

-- 1) Tabel relasi many-to-many channel <-> team (source of truth SEMUA team per channel,
--    termasuk team utama wa_channels.team_id).
CREATE TABLE IF NOT EXISTS wa_channel_teams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_channel_team_link (channel_id, team_id),
  INDEX idx_channel_teams_team (team_id),
  CONSTRAINT fk_channel_teams_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE,
  CONSTRAINT fk_channel_teams_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Backfill: semua channel yang sudah punya team utama didaftarkan ke join table.
INSERT IGNORE INTO wa_channel_teams (channel_id, team_id)
SELECT id, team_id FROM wa_channels WHERE team_id IS NOT NULL;

-- 3) Conversations: 1 conversation per (channel, team, phone) — tiap team punya
--    riwayat chat terpisah dengan customer yang sama di nomor yang sama.
--    Aman karena saat ini 1 channel = 1 team (tidak ada duplikat per team).
ALTER TABLE conversations DROP INDEX uq_conv_channel_phone;
ALTER TABLE conversations
  ADD UNIQUE KEY uq_conv_channel_team_phone (channel_id, team_id, phone);

-- 4) Blast: simpan team pembuat, supaya cron memakai team yang benar saat channel
--    dipakai banyak team (fallback ke team utama channel untuk blast lama).
ALTER TABLE wa_blasts
  ADD COLUMN team_id INT UNSIGNED NULL AFTER channel_id;

UPDATE wa_blasts b
INNER JOIN wa_channels c ON c.id = b.channel_id
SET b.team_id = c.team_id
WHERE b.team_id IS NULL;

ALTER TABLE wa_blasts
  ADD INDEX idx_blast_team (team_id);
