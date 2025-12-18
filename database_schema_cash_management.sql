-- ============================================
-- BEAUTY SALON - DATABASE SCHEMA
-- Sistem Manajemen Kas (Cash Management)
-- Created: 2025-12-18
-- Updated: 2025-12-18 (Added is_expense field)
-- ============================================

-- ============================================
-- 1. TABEL KATEGORI PENGELUARAN
-- ============================================
CREATE TABLE expense_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    is_expense TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Expense (Pengeluaran), 0=Non-Expense (Prive/Aset)',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_is_expense (is_expense)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data awal kategori (is_expense = TRUE / Pengeluaran Operasional)
INSERT INTO expense_categories (name, is_expense, description) VALUES
('Bahan Salon', 1, 'Pembelian bahan dan produk salon'),
('Gaji & Komisi', 1, 'Pembayaran gaji karyawan dan komisi therapist'),
('Listrik', 1, 'Biaya listrik bulanan'),
('Air', 1, 'Biaya air bulanan (PDAM)'),
('Internet', 1, 'Biaya internet dan telekomunikasi'),
('Transport', 1, 'Biaya transport dan pengiriman'),
('ATK', 1, 'Alat Tulis Kantor dan perlengkapan'),
('Perawatan', 1, 'Perawatan dan perbaikan peralatan/gedung'),
('Lain-lain', 1, 'Pengeluaran operasional lainnya');

-- Data kategori (is_expense = FALSE / Bukan Pengeluaran)
INSERT INTO expense_categories (name, is_expense, description) VALUES
('Prive Pemilik', 0, 'Pengambilan uang pribadi pemilik'),
('Pembelian Aset', 0, 'Pembelian aset/investasi jangka panjang');


-- ============================================
-- 2. TABEL TRANSAKSI KAS UTAMA (UNIFIED)
-- ============================================
CREATE TABLE cash_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Salon Info (Multi-tenant)
    salon_id INT NOT NULL COMMENT 'ID Salon (untuk multi-tenant)',
    
    -- Transaction Info
    transaction_type ENUM('income', 'expense', 'transfer') NOT NULL,
    transaction_date DATE NOT NULL,
    
    -- Amount & Cash Source
    amount DECIMAL(15,2) NOT NULL,
    cash_source ENUM('cashier', 'main') NOT NULL COMMENT 'Sumber/tujuan uang: cashier=Kas Kasir, main=Kas Besar',
    
    -- For Transfer
    transfer_from ENUM('cashier', 'main') NULL COMMENT 'Dari kas mana (untuk transfer)',
    transfer_to ENUM('cashier', 'main') NULL COMMENT 'Ke kas mana (untuk transfer)',
    
    -- For Expense
    category_id INT NULL,
    
    -- Description & Notes
    description VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    
    -- Reference
    reference_type VARCHAR(50) NULL COMMENT 'order, manual, transfer, etc',
    reference_id INT NULL COMMENT 'ID dari tabel reference (misal: order_id)',
    
    -- User tracking
    created_by INT NULL COMMENT 'User ID yang input',
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_salon (salon_id),
    INDEX idx_date (transaction_date),
    INDEX idx_type (transaction_type),
    INDEX idx_cash_source (cash_source),
    INDEX idx_category (category_id),
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 3. VIEW UNTUK SALDO KAS
-- ============================================

-- View: Saldo Kas Kasir (per salon)
CREATE OR REPLACE VIEW v_cashier_balance AS
SELECT 
    salon_id,
    COALESCE(SUM(
        CASE 
            WHEN transaction_type = 'income' AND cash_source = 'cashier' THEN amount
            WHEN transaction_type = 'expense' AND cash_source = 'cashier' THEN -amount
            WHEN transaction_type = 'transfer' AND transfer_to = 'cashier' THEN amount
            WHEN transaction_type = 'transfer' AND transfer_from = 'cashier' THEN -amount
            ELSE 0
        END
    ), 0) as balance,
    COALESCE(SUM(CASE WHEN transaction_type = 'income' AND cash_source = 'cashier' THEN amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN transaction_type = 'expense' AND cash_source = 'cashier' THEN amount ELSE 0 END), 0) as total_expense,
    COALESCE(SUM(CASE WHEN transaction_type = 'transfer' AND transfer_to = 'cashier' THEN amount ELSE 0 END), 0) as total_transfer_in,
    COALESCE(SUM(CASE WHEN transaction_type = 'transfer' AND transfer_from = 'cashier' THEN amount ELSE 0 END), 0) as total_transfer_out
FROM cash_transactions
GROUP BY salon_id;

-- View: Saldo Kas Besar (per salon)
CREATE OR REPLACE VIEW v_main_cash_balance AS
SELECT 
    salon_id,
    COALESCE(SUM(
        CASE 
            WHEN transaction_type = 'income' AND cash_source = 'main' THEN amount
            WHEN transaction_type = 'expense' AND cash_source = 'main' THEN -amount
            WHEN transaction_type = 'transfer' AND transfer_to = 'main' THEN amount
            WHEN transaction_type = 'transfer' AND transfer_from = 'main' THEN -amount
            ELSE 0
        END
    ), 0) as balance,
    COALESCE(SUM(CASE WHEN transaction_type = 'income' AND cash_source = 'main' THEN amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN transaction_type = 'expense' AND cash_source = 'main' THEN amount ELSE 0 END), 0) as total_expense,
    COALESCE(SUM(CASE WHEN transaction_type = 'transfer' AND transfer_to = 'main' THEN amount ELSE 0 END), 0) as total_transfer_in,
    COALESCE(SUM(CASE WHEN transaction_type = 'transfer' AND transfer_from = 'main' THEN amount ELSE 0 END), 0) as total_transfer_out
FROM cash_transactions
GROUP BY salon_id;


-- ============================================
-- 4. STORED PROCEDURES (OPTIONAL)
-- ============================================

-- Procedure: Transfer antar kas
DELIMITER $$
CREATE PROCEDURE sp_transfer_cash(
    IN p_amount DECIMAL(15,2),
    IN p_from_cash ENUM('cashier', 'main'),
    IN p_to_cash ENUM('cashier', 'main'),
    IN p_description VARCHAR(255),
    IN p_notes TEXT,
    IN p_created_by INT
)
BEGIN
    -- Validasi: tidak bisa transfer ke diri sendiri
    IF p_from_cash = p_to_cash THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tidak bisa transfer ke kas yang sama';
    END IF;
    
    -- Validasi: amount harus positif
    IF p_amount <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Amount harus lebih dari 0';
    END IF;
    
    -- Insert transaksi transfer
    INSERT INTO cash_transactions (
        transaction_type, 
        transaction_date, 
        amount, 
        cash_source, 
        transfer_from, 
        transfer_to, 
        description, 
        notes,
        reference_type,
        created_by
    ) VALUES (
        'transfer',
        CURDATE(),
        p_amount,
        p_from_cash,
        p_from_cash,
        p_to_cash,
        p_description,
        p_notes,
        'transfer',
        p_created_by
    );
    
    SELECT LAST_INSERT_ID() as transaction_id;
END$$
DELIMITER ;


-- ============================================
-- 5. MIGRASI DATA (Jika ada data lama)
-- ============================================

-- Jika tabel cashier_expenses sudah ada, migrate ke cash_transactions
-- INSERT INTO cash_transactions (
--     transaction_type, transaction_date, amount, cash_source, 
--     category_id, description, notes, reference_type, created_at
-- )
-- SELECT 
--     'expense', date, amount, 'cashier',
--     NULL, description, notes, 'manual', created_at
-- FROM cashier_expenses;


-- ============================================
-- 6. SAMPLE DATA (untuk testing)
-- NOTE: Ganti salon_id = 1 dengan ID salon Anda
-- ============================================

-- Income dari order ke Kas Kasir (simulasi)
INSERT INTO cash_transactions (salon_id, transaction_type, transaction_date, amount, cash_source, description, reference_type) VALUES
(1, 'income', '2025-12-01', 500000, 'cashier', 'Pembayaran Order #1', 'order'),
(1, 'income', '2025-12-02', 750000, 'cashier', 'Pembayaran Order #2', 'order'),
(1, 'income', '2025-12-03', 1000000, 'cashier', 'Pembayaran Order #3', 'order');

-- Expense dari Kas Kasir
INSERT INTO cash_transactions (salon_id, transaction_type, transaction_date, amount, cash_source, category_id, description, notes, reference_type) VALUES
(1, 'expense', '2025-12-01', 50000, 'cashier', 1, 'Beli sabun cuci', 'Untuk operasional', 'manual'),
(1, 'expense', '2025-12-02', 25000, 'cashier', 6, 'Transport', 'Antar produk', 'manual');

-- Transfer dari Kas Kasir ke Kas Besar
INSERT INTO cash_transactions (salon_id, transaction_type, transaction_date, amount, cash_source, transfer_from, transfer_to, description, reference_type) VALUES
(1, 'transfer', '2025-12-03', 1000000, 'cashier', 'cashier', 'main', 'Transfer ke Kas Besar', 'transfer');

-- Expense dari Kas Besar
INSERT INTO cash_transactions (salon_id, transaction_type, transaction_date, amount, cash_source, category_id, description, reference_type) VALUES
(1, 'expense', '2025-12-05', 2500000, 'main', 2, 'Gaji Bulanan', 'manual'),
(1, 'expense', '2025-12-10', 500000, 'main', 3, 'Bayar Listrik', 'manual');


-- ============================================
-- 7. INDEXES TAMBAHAN (Performance)
-- ============================================
CREATE INDEX idx_created_at ON cash_transactions(created_at);
CREATE INDEX idx_composite_filter ON cash_transactions(transaction_date, transaction_type, cash_source);


-- ============================================
-- QUERY EXAMPLES
-- ============================================

-- 1. Cek saldo Kas Kasir
-- SELECT * FROM v_cashier_balance;

-- 2. Cek saldo Kas Besar  
-- SELECT * FROM v_main_cash_balance;

-- 3. Riwayat transaksi Kas Kasir bulan ini
-- SELECT * FROM cash_transactions 
-- WHERE cash_source = 'cashier' 
--   AND MONTH(transaction_date) = MONTH(CURDATE())
-- ORDER BY transaction_date DESC;

-- 4. Total pengeluaran per kategori
-- SELECT 
--     ec.name as category,
--     SUM(ct.amount) as total
-- FROM cash_transactions ct
-- JOIN expense_categories ec ON ct.category_id = ec.id
-- WHERE ct.transaction_type = 'expense'
-- GROUP BY ec.name
-- ORDER BY total DESC;

-- 5. Transfer history
-- SELECT * FROM cash_transactions
-- WHERE transaction_type = 'transfer'
-- ORDER BY transaction_date DESC;
