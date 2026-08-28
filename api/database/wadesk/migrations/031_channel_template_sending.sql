SET NAMES utf8mb4;

ALTER TABLE wa_channels
  ADD COLUMN template_sending_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER status;
