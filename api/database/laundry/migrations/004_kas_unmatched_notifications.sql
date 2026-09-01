-- mdl_laundry.kas_unmatched_notifications: one-time BCA/QRIS mismatch notifications
USE mdl_laundry;

CREATE TABLE IF NOT EXISTS kas_unmatched_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    method ENUM('BCA', 'QRIS') NOT NULL,
    ref_finance VARCHAR(100) NOT NULL,
    status ENUM('processing', 'sent', 'failed') NOT NULL DEFAULT 'processing',
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL DEFAULT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kas_unmatched_method_ref (method, ref_finance),
    KEY idx_kas_unmatched_status_claimed (status, claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
