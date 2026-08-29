-- WaDesk Meta WABA catalogue. Run after 031_channel_template_sending.sql.

CREATE TABLE IF NOT EXISTS wa_wabas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  meta_waba_id VARCHAR(64) NOT NULL,
  name VARCHAR(150) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  last_synced_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_waba_tenant_meta (tenant_id, meta_waba_id),
  INDEX idx_waba_tenant (tenant_id),
  CONSTRAINT fk_waba_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE wa_channels
  ADD COLUMN meta_phone_number_id VARCHAR(64) NULL AFTER waba_id,
  ADD COLUMN provider ENUM('kirimin','meta') NOT NULL DEFAULT 'kirimin' AFTER channel_type,
  ADD UNIQUE KEY uq_channel_tenant_meta_phone (tenant_id, meta_phone_number_id),
  ADD INDEX idx_channel_meta_phone (meta_phone_number_id);

ALTER TABLE wa_templates
  ADD COLUMN meta_waba_id VARCHAR(64) NULL AFTER tenant_id,
  ADD COLUMN meta_template_id VARCHAR(64) NULL AFTER language,
  ADD COLUMN meta_status VARCHAR(32) NULL AFTER body_preview,
  ADD COLUMN meta_category VARCHAR(32) NULL AFTER meta_status,
  DROP INDEX uq_tpl_tenant_name_lang,
  ADD UNIQUE KEY uq_tpl_tenant_waba_name_lang (tenant_id, meta_waba_id, template_name, language),
  ADD INDEX idx_tpl_meta_waba (meta_waba_id);
