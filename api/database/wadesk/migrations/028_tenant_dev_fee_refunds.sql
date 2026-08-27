-- Make Dev Fee usage/refund ledger mirror the team template quota lifecycle.
ALTER TABLE wa_tenant_dev_fee_logs
  ADD COLUMN type ENUM('consume','refund') NOT NULL DEFAULT 'consume' AFTER source,
  DROP INDEX uq_dev_fee_message,
  ADD UNIQUE KEY uq_dev_fee_message_type (message_id, type);
