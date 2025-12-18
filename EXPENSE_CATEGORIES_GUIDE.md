# 📊 Kategori Pengeluaran - Dokumentasi

## 🎯 Overview

Sistem kategori pengeluaran menggunakan field `is_expense` untuk membedakan antara:
- **Expense (Pengeluaran)**: Biaya operasional yang mengurangi keuntungan
- **Non-Expense**: Pengambilan uang yang bukan biaya operasional (Prive, Aset)

---

## 🗂️ Struktur Tabel

```sql
CREATE TABLE expense_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    is_expense TINYINT(1) NOT NULL DEFAULT 1,  -- 1=Expense, 0=Non-Expense
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME,
    updated_at DATETIME
);
```

---

## 📝 Kategori Pengeluaran

### ✅ **is_expense = TRUE (Pengeluaran Operasional)**

Biaya yang **mengurangi laba** usaha:

| ID | Nama | Deskripsi | Impact |
|----|------|-----------|--------|
| 1 | **Bahan Salon** | Pembelian bahan dan produk salon | Mengurangi laba |
| 2 | **Gaji & Komisi** | Gaji karyawan & komisi therapist | Mengurangi laba |
| 3 | **Listrik** | Biaya listrik bulanan | Mengurangi laba |
| 4 | **Air** | Biaya air (PDAM) | Mengurangi laba |
| 5 | **Internet** | Internet & telekomunikasi | Mengurangi laba |
| 6 | **Transport** | Transport & pengiriman | Mengurangi laba |
| 7 | **ATK** | Alat Tulis Kantor | Mengurangi laba |
| 8 | **Perawatan** | Maintenance peralatan | Mengurangi laba |
| 9 | **Lain-lain** | Pengeluaran operasional lainnya | Mengurangi laba |

**Total:** 9 kategori

---

### ❌ **is_expense = FALSE (Bukan Pengeluaran)**

Pengeluaran yang **TIDAK mengurangi laba** (dicatat terpisah):

| ID | Nama | Deskripsi | Impact |
|----|------|-----------|--------|
| 10 | **Prive Pemilik** | Pengambilan uang pribadi pemilik | Mengurangi modal |
| 11 | **Pembelian Aset** | Investasi aset jangka panjang | Transfer ke aset |

**Total:** 2 kategori

---

## 🔍 Perbedaan Expense vs Non-Expense

### Expense (is_expense = 1)
```
Karakteristik:
✅ Biaya operasional rutin
✅ Habis pakai (consumable)
✅ Mengurangi laba/rugi
✅ Masuk ke laporan P&L (Profit & Loss)
✅ Untuk operasional sehari-hari

Contoh:
- Beli shampo, cat rambut → Bahan Salon
- Bayar gaji therapist → Gaji & Komisi
- Bayar tagihan listrik → Listrik
```

### Non-Expense (is_expense = 0)
```
Karakteristik:
❌ Bukan biaya operasional
❌ Tidak habis pakai
❌ TIDAK mengurangi laba/rugi
❌ TIDAK masuk laporan P&L
✅ Masuk ke laporan perubahan modal/neraca

Contoh:
- Pemilik ambil uang untuk pribadi → Prive Pemilik
- Beli kursi terapis, AC baru → Pembelian Aset
```

---

## 📊 Pengaruh ke Laporan Keuangan

### Expense (Pengeluaran):
```
Laporan Laba Rugi (P&L):
─────────────────────────
Pendapatan:        10.000.000
Pengeluaran:
  - Bahan Salon     (2.000.000)
  - Gaji & Komisi   (3.000.000)
  - Listrik           (500.000)
  - Air               (200.000)
  - Internet          (300.000)
  - Transport         (100.000)
  - ATK                (50.000)
  - Perawatan         (150.000)
  - Lain-lain         (200.000)
─────────────────────────
Laba Bersih:        3.500.000 ✅
```

### Non-Expense (Prive/Aset):
```
Laporan Perubahan Modal:
─────────────────────────
Modal Awal:        50.000.000
+ Laba Bersih:      3.500.000
- Prive Pemilik:   (1.000.000) ⚠️ TIDAK mengurangi laba
─────────────────────────
Modal Akhir:       52.500.000

Neraca (Balance Sheet):
─────────────────────────
Aset:
  - Kas:             5.000.000
  - Peralatan:      10.000.000 ⚠️ Dari pembelian aset
─────────────────────────
```

---

## 🎯 Use Cases

### Case 1: Beli Bahan Salon (Expense)
```
Transaksi: Beli shampo Rp 500.000
Kategori: "Bahan Salon" (is_expense=1)
Impact: 
  ✅ Kas berkurang Rp 500.000
  ✅ Pengeluaran naik Rp 500.000
  ✅ Laba berkurang Rp 500.000
```

### Case 2: Prive Pemilik (Non-Expense)
```
Transaksi: Pemilik ambil uang Rp 2.000.000
Kategori: "Prive Pemilik" (is_expense=0)
Impact:
  ✅ Kas berkurang Rp 2.000.000
  ❌ Pengeluaran TIDAK naik
  ❌ Laba TIDAK berubah
  ✅ Modal berkurang Rp 2.000.000
```

### Case 3: Beli Aset (Non-Expense)
```
Transaksi: Beli AC baru Rp 5.000.000
Kategori: "Pembelian Aset" (is_expense=0)
Impact:
  ✅ Kas berkurang Rp 5.000.000
  ❌ Pengeluaran TIDAK naik
  ❌ Laba TIDAK berubah
  ✅ Aset (Peralatan) naik Rp 5.000.000
```

---

## 🔄 Migration Script

Jika tabel sudah ada, jalankan script ini untuk menambahkan field:

```sql
-- Add is_expense field to existing table
ALTER TABLE expense_categories 
ADD COLUMN is_expense TINYINT(1) NOT NULL DEFAULT 1 
COMMENT '1=Expense (Pengeluaran), 0=Non-Expense (Prive/Aset)'
AFTER name;

-- Add index
CREATE INDEX idx_is_expense ON expense_categories(is_expense);

-- Update existing data (set all to expense by default)
UPDATE expense_categories SET is_expense = 1;

-- Delete old categories
DELETE FROM expense_categories;

-- Insert new categories (Expense)
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

-- Insert new categories (Non-Expense)
INSERT INTO expense_categories (name, is_expense, description) VALUES
('Prive Pemilik', 0, 'Pengambilan uang pribadi pemilik'),
('Pembelian Aset', 0, 'Pembelian aset/investasi jangka panjang');
```

---

## 📱 Frontend Implementation

### Filter by is_expense:

```javascript
// Get only expense categories
const expenseCategories = categories.filter(cat => cat.is_expense === 1);

// Get only non-expense categories  
const nonExpenseCategories = categories.filter(cat => cat.is_expense === 0);
```

### Display with grouping:

```vue
<select v-model="form.category_id">
  <optgroup label="Pengeluaran Operasional">
    <option v-for="cat in expenseCategories" :value="cat.id">
      {{ cat.name }}
    </option>
  </optgroup>
  
  <optgroup label="Prive & Aset">
    <option v-for="cat in nonExpenseCategories" :value="cat.id">
      {{ cat.name }}
    </option>
  </optgroup>
</select>
```

---

## 📊 Query Examples

### Get total expense (operational costs):
```sql
SELECT SUM(ct.amount) as total_expense
FROM cash_transactions ct
JOIN expense_categories ec ON ct.category_id = ec.id
WHERE ct.transaction_type = 'expense'
  AND ec.is_expense = 1;
```

### Get total prive:
```sql
SELECT SUM(ct.amount) as total_prive
FROM cash_transactions ct
JOIN expense_categories ec ON ct.category_id = ec.id
WHERE ct.transaction_type = 'expense'
  AND ec.is_expense = 0
  AND ec.name = 'Prive Pemilik';
```

### Get expense by category (only operational):
```sql
SELECT 
  ec.name as category,
  SUM(ct.amount) as total
FROM cash_transactions ct
JOIN expense_categories ec ON ct.category_id = ec.id
WHERE ct.transaction_type = 'expense'
  AND ec.is_expense = 1
GROUP BY ec.name
ORDER BY total DESC;
```

---

## ✅ Best Practices

1. **Selalu gunakan is_expense untuk filter:**
   - Laporan P&L → Only `is_expense = 1`
   - Laporan Prive → Only `is_expense = 0` AND `name = 'Prive Pemilik'`
   - Laporan Aset → Only `is_expense = 0` AND `name = 'Pembelian Aset'`

2. **Jangan campur expense dan non-expense dalam laporan laba/rugi**

3. **Prive dan Aset harus dicatat terpisah di neraca**

4. **Validasi kategori saat input:**
   ```javascript
   if (category.is_expense === 0) {
     // Warning: Ini bukan pengeluaran operasional
     // Tidak akan mempengaruhi laba/rugi
   }
   ```

---

## 🆘 Troubleshooting

### Laba/Rugi tidak akurat?
✓ Pastikan filter `is_expense = 1` saat hitung total expense

### Prive masuk ke pengeluaran?
✓ Cek kategori "Prive Pemilik" memiliki `is_expense = 0`

### Pembelian aset mengurangi laba?
✓ Kategori "Pembelian Aset" harus `is_expense = 0`

---

**Created**: 2025-12-18  
**Updated**: 2025-12-18 (Added is_expense field)
