-- Coexistence is no longer supported by WaDesk. Remove its obsolete local state.
ALTER TABLE wa_channels
  DROP COLUMN is_coexistence;

ALTER TABLE wa_wabas
  DROP COLUMN coex_subscription_status,
  DROP COLUMN coex_subscription_checked_at;
