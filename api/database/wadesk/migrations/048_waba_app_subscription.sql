-- Cache a successful Meta app ↔ WABA subscription. Failed subscriptions stay
-- NULL and will be retried by the next Sync WABA.
ALTER TABLE wa_wabas
  ADD COLUMN meta_app_subscribed_at DATETIME NULL AFTER status;
