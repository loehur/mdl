-- WaDesk: default team = satu per tenant (customer baru masuk ke team ini)
-- wa_channels.team_id tidak lagi dipakai sebagai default per-nomor

SET NAMES utf8mb4;

ALTER TABLE teams
  ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER name;

ALTER TABLE wa_channels
  DROP INDEX uq_channel_team;

ALTER TABLE wa_channels
  MODIFY team_id INT UNSIGNED NULL;

-- Satu default per tenant: team pertama (id terkecil) per tenant
UPDATE teams t
INNER JOIN (
  SELECT tenant_id, MIN(id) AS id FROM teams GROUP BY tenant_id
) pick ON pick.id = t.id
SET t.is_default = 1;
