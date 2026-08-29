-- Channel access is now controlled exclusively by wa_waba_teams / wa_channel_teams.

ALTER TABLE wa_channels
  DROP FOREIGN KEY fk_channels_team,
  DROP INDEX idx_channels_team,
  DROP COLUMN team_id;
