-- BCA-only top-up orders for WaDesk tenant Dev Fee quota.
CREATE TABLE IF NOT EXISTS wa_tenant_dev_fee_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  payment_ref VARCHAR(80) NOT NULL,
  quota_amount INT UNSIGNED NOT NULL,
  base_amount INT UNSIGNED NOT NULL,
  amount INT UNSIGNED NOT NULL,
  payment_method ENUM('bca') NOT NULL DEFAULT 'bca',
  payment_status ENUM('pending','success','expired','cancelled') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dev_fee_payment_ref (payment_ref),
  INDEX idx_dev_fee_payment_pending (payment_status, created_at),
  INDEX idx_dev_fee_payment_tenant (tenant_id, id),
  CONSTRAINT fk_dev_fee_payment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_dev_fee_payment_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
