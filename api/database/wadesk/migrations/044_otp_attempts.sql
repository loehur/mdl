-- Server-side guard for Meta phone-number OTP requests and verification attempts.
CREATE TABLE IF NOT EXISTS wa_otp_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  phone_number_id VARCHAR(64) NOT NULL,
  last_request_at DATETIME NULL,
  verify_fail_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  verify_locked_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_otp_attempt_tenant_phone (tenant_id, phone_number_id),
  INDEX idx_otp_attempt_lock (verify_locked_until),
  CONSTRAINT fk_otp_attempt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
