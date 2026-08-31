ALTER TABLE teams
  ADD COLUMN template_access_expires_at DATE NULL AFTER daily_template_limit;
