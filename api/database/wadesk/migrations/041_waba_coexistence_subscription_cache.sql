ALTER TABLE wa_wabas
  ADD COLUMN coex_subscription_status VARCHAR(32) NULL AFTER status,
  ADD COLUMN coex_subscription_checked_at DATETIME NULL AFTER coex_subscription_status;
