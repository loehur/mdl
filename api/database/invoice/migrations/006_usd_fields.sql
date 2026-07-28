-- mdl_invoice: USD pedoman per item + kurs harian
-- Jalankan di database mdl_invoice

CREATE TABLE IF NOT EXISTS exchange_rates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  base_currency CHAR(3) NOT NULL,
  quote_currency CHAR(3) NOT NULL,
  rate DECIMAL(18, 6) NOT NULL,
  rate_date DATE NOT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'freecurrencyapi',
  fetched_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exchange_rate_day (base_currency, quote_currency, rate_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE invoice_items
  ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'IDR' AFTER description,
  ADD COLUMN unit_price_usd DECIMAL(18, 4) NULL DEFAULT NULL AFTER unit_price,
  ADD COLUMN amount_usd DECIMAL(18, 4) NULL DEFAULT NULL AFTER amount;

ALTER TABLE invoices
  ADD COLUMN exchange_rate DECIMAL(18, 6) NULL DEFAULT NULL AFTER total,
  ADD COLUMN total_usd DECIMAL(18, 4) NULL DEFAULT NULL AFTER exchange_rate;
