-- Database: mdl_invoice (API db_index = 6)
CREATE DATABASE IF NOT EXISTS mdl_invoice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mdl_invoice;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    business_name VARCHAR(200) DEFAULT NULL,
    business_phone VARCHAR(50) DEFAULT NULL,
    business_address TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    public_token CHAR(32) NOT NULL,
    customer_name VARCHAR(200) NOT NULL,
    customer_email VARCHAR(200) DEFAULT NULL,
    customer_phone VARCHAR(50) DEFAULT NULL,
    issue_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    status ENUM('draft','sent','paid','cancelled') NOT NULL DEFAULT 'sent',
    payment_status ENUM('unpaid','pending','paid') NOT NULL DEFAULT 'unpaid',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_public_token (public_token),
    INDEX idx_user_id (user_id),
    INDEX idx_issue_date (issue_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_id (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'qris',
    payment_ref VARCHAR(100) NOT NULL,
    payment_status ENUM('pending','success','failed','expired') NOT NULL DEFAULT 'pending',
    qr_string TEXT DEFAULT NULL,
    trx_id VARCHAR(100) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_payment_ref (payment_ref),
    INDEX idx_invoice_id (invoice_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Salin user dari mdl_investasi jika ada, atau buat default
INSERT INTO users (name, email, password, business_name, business_phone)
SELECT u.name, u.email, u.password, 'MDL Invoice', '08123456789'
FROM mdl_investasi.users u
WHERE u.email = 'loehur@gmail.com'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'loehur@gmail.com')
LIMIT 1;
