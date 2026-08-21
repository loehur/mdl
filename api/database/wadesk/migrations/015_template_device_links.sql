-- Link approved templates to Kirimin whatsapp_device_id (per WABA / nomor WA).
-- Used to filter templates available for each channel when sending.

CREATE TABLE IF NOT EXISTS wa_template_devices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  device_id VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tpl_device (template_id, device_id),
  INDEX idx_tpl_dev_device (device_id),
  CONSTRAINT fk_tpl_dev_template FOREIGN KEY (template_id) REFERENCES wa_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
