-- Dual YCloud business lines: business_phone di message tables + CSW per line.
-- Label A/B hanya di WaLines config, bukan di DB.

SET NAMES utf8mb4;

ALTER TABLE wa_messages_in
  ADD COLUMN business_phone VARCHAR(20) NULL DEFAULT NULL AFTER phone;

ALTER TABLE wa_messages_out
  ADD COLUMN business_phone VARCHAR(20) NULL DEFAULT NULL AFTER phone;

CREATE TABLE IF NOT EXISTS wa_csw_by_line (
  customer_phone VARCHAR(32) NOT NULL,
  business_phone VARCHAR(20) NOT NULL,
  last_in_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_phone, business_phone),
  KEY idx_csw_line_last_in (last_in_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default historis ke line CS (+6281170706611) bila kolom baru
UPDATE wa_messages_in SET business_phone = '+6281170706611' WHERE business_phone IS NULL OR business_phone = '';
UPDATE wa_messages_out SET business_phone = '+6281170706611' WHERE business_phone IS NULL OR business_phone = '';

-- CSW Fonnte lama → line admin
INSERT INTO wa_csw_by_line (customer_phone, business_phone, last_in_at, updated_at)
SELECT phone, '+628117686252', last_in_at, NOW()
FROM wa_fonnte_csw
WHERE last_in_at IS NOT NULL
ON DUPLICATE KEY UPDATE
  last_in_at = GREATEST(wa_csw_by_line.last_in_at, VALUES(last_in_at)),
  updated_at = NOW();

-- CSW yCloud lama (wa_conversations.last_in_at) → line CS
INSERT INTO wa_csw_by_line (customer_phone, business_phone, last_in_at, updated_at)
SELECT wa_number, '+6281170706611', last_in_at, NOW()
FROM wa_conversations
WHERE last_in_at IS NOT NULL
ON DUPLICATE KEY UPDATE
  last_in_at = GREATEST(wa_csw_by_line.last_in_at, VALUES(last_in_at)),
  updated_at = NOW();

ALTER TABLE wa_messages_in
  ADD INDEX idx_wa_in_phone_biz_time (phone, business_phone, created_at);

ALTER TABLE wa_messages_out
  ADD INDEX idx_wa_out_phone_biz_time (phone, business_phone, created_at);
