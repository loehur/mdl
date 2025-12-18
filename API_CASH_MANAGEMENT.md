# 🏦 Kas Besar (Main Cash) - API Documentation

## 📋 Overview

Backend API endpoints yang dibutuhkan untuk fitur **Kas Besar** (Main Cash Management System).

---

## 🗄️ Database Tables

Semua schema sudah ada di file: `database_schema_cash_management.sql`

**Tables:**
1. `expense_categories` - Kategori pengeluaran
2. `cash_transactions` - Transaksi kas unified (income, expense, transfer)
3. `v_cashier_balance` - View saldo kas kasir
4. `v_main_cash_balance` - View saldo kas besar

---

## 🔌 API Endpoints Required

### 1. **GET /api/Beauty_Salon/CashBalance/{type}**

Mendapatkan saldo kas (kasir atau besar).

**Parameters:**
- `{type}` - `cashier` atau `main`

**Response Success:**
```json
{
  "success": true,
  "data": {
    "balance": 5000000,
    "total_income": 6000000,
    "total_expense": 1000000,
    "total_transfer_in": 500000,
    "total_transfer_out": 500000
  }
}
```

**SQL Query:**
```sql
-- For cashier
SELECT * FROM v_cashier_balance;

-- For main
SELECT * FROM v_main_cash_balance;
```

---

### 2. **GET /api/Beauty_Salon/ExpenseCategories**

Mendapatkan semua kategori pengeluaran.

**Response Success:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Operasional",
      "description": "Pengeluaran operasional harian salon",
      "is_active": 1
    },
    {
      "id": 2,
      "name": "Gaji & Upah",
      "description": "Pembayaran gaji therapist dan staff",
      "is_active": 1
    }
  ]
}
```

**SQL Query:**
```sql
SELECT id, name, description, is_active 
FROM expense_categories 
WHERE is_active = 1
ORDER BY name;
```

---

### 3. **GET /api/Beauty_Salon/CashTransactions**

Mendapatkan riwayat transaksi dengan filter.

**Query Parameters:**
- `type` (optional) - `income`, `expense`, atau `transfer`
- `cash` (optional) - `cashier` atau `main`
- `limit` (optional) - default 100
- `offset` (optional) - default 0

**Response Success:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "transaction_type": "expense",
      "transaction_date": "2025-12-18",
      "amount": 500000,
      "cash_source": "main",
      "category_id": 1,
      "category_name": "Operasional",
      "description": "Bayar listrik",
      "notes": "Bulan Desember",
      "reference_type": "manual",
      "created_at": "2025-12-18 10:00:00"
    }
  ]
}
```

**SQL Query:**
```sql
SELECT 
  ct.id,
  ct.transaction_type,
  ct.transaction_date,
  ct.amount,
  ct.cash_source,
  ct.transfer_from,
  ct.transfer_to,
  ct.category_id,
  ec.name as category_name,
  ct.description,
  ct.notes,
  ct.reference_type,
  ct.created_at
FROM cash_transactions ct
LEFT JOIN expense_categories ec ON ct.category_id = ec.id
WHERE 1=1
  AND (? IS NULL OR ct.transaction_type = ?)
  AND (? IS NULL OR ct.cash_source = ?)
ORDER BY ct.transaction_date DESC, ct.created_at DESC
LIMIT ? OFFSET ?;
```

---

### 4. **POST /api/Beauty_Salon/CashTransfer**

Transfer antar kas (Kasir ↔ Besar).

**Request Body:**
```json
{
  "from": "cashier",
  "to": "main",
  "amount": 1000000,
  "description": "Transfer ke kas besar",
  "notes": "Optional notes"
}
```

**Validation:**
- `from` !== `to`
- `amount` > 0
- Check saldo cukup di kas sumber

**Response Success:**
```json
{
  "success": true,
  "message": "Transfer berhasil",
  "data": {
    "transaction_id": 123
  }
}
```

**SQL Query:**
```sql
-- Option 1: Manual INSERT
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
  ?,
  ?,
  ?,
  ?,
  ?,
  ?,
  'transfer',
  ?
);

-- Option 2: Call stored procedure
CALL sp_transfer_cash(?, ?, ?, ?, ?, ?);
```

---

### 5. **POST /api/Beauty_Salon/CashExpense**

Input pengeluaran kas.

**Request Body:**
```json
{
  "cash_source": "main",
  "category_id": 3,
  "amount": 5000000,
  "description": "Bayar sewa gedung",
  "notes": "Sewa bulan Januari 2026"
}
```

**Validation:**
- `category_id` must exist
- `amount` > 0
- Check saldo cukup di kas sumber

**Response Success:**
```json
{
  "success": true,
  "message": "Pengeluaran berhasil disimpan",
  "data": {
    "transaction_id": 124
  }
}
```

**SQL Query:**
```sql
INSERT INTO cash_transactions (
  transaction_type,
  transaction_date,
  amount,
  cash_source,
  category_id,
  description,
  notes,
  reference_type,
  created_by
) VALUES (
  'expense',
  CURDATE(),
  ?,
  ?,
  ?,
  ?,
  ?,
  'manual',
  ?
);
```

---

### 6. **DELETE /api/Beauty_Salon/CashTransactions/{id}**

Hapus transaksi (kecuali yang dari order).

**Validation:**
- Transaction must exist
- `reference_type` !== 'order' (tidak boleh hapus transaksi from order)

**Response Success:**
```json
{
  "success": true,
  "message": "Transaksi berhasil dihapus"
}
```

**SQL Query:**
```sql
DELETE FROM cash_transactions 
WHERE id = ? 
  AND reference_type != 'order';
```

---

## 🔐 Authorization

Semua endpoint **Kas Besar** harus:
- ✅ Require authentication
- ✅ Check role = 'admin'
- ❌ Reject jika bukan admin

**Example (PHP):**
```php
// Check if user is admin
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Admin only.']);
    exit;
}
```

---

## 💾 Auto Income from Orders

Setiap order completed dengan payment tunai, **otomatis** insert ke cash_transactions:

```sql
-- Trigger atau manual insert setelah order completed
INSERT INTO cash_transactions (
  transaction_type,
  transaction_date,
  amount,
  cash_source,
  description,
  reference_type,
  reference_id
) VALUES (
  'income',
  NOW(),
  {order.pay_cash},
  'cashier',
  'Pembayaran Order #{order.id}',
  'order',
  {order.id}
);
```

---

## 🧪 Testing Endpoints

### Test Saldo Balance
```bash
GET /api/Beauty_Salon/CashBalance/cashier
GET /api/Beauty_Salon/CashBalance/main
```

### Test Categories
```bash
GET /api/Beauty_Salon/ExpenseCategories
```

### Test Transactions List
```bash
GET /api/Beauty_Salon/CashTransactions
GET /api/Beauty_Salon/CashTransactions?type=expense
GET /api/Beauty_Salon/CashTransactions?cash=main
```

### Test Transfer
```bash
POST /api/Beauty_Salon/CashTransfer
{
  "from": "cashier",
  "to": "main",
  "amount": 1000000,
  "description": "Test transfer"
}
```

### Test Expense
```bash
POST /api/Beauty_Salon/CashExpense
{
  "cash_source": "main",
  "category_id": 1,
  "amount": 100000,
  "description": "Test expense"
}
```

### Test Delete
```bash
DELETE /api/Beauty_Salon/CashTransactions/123
```

---

## 📊 Sample Responses

### Error Response
```json
{
  "success": false,
  "message": "Saldo tidak cukup",
  "error_code": "INSUFFICIENT_BALANCE"
}
```

### Validation Error
```json
{
  "success": false,
  "message": "Tidak bisa transfer ke kas yang sama",
  "error_code": "INVALID_TRANSFER"
}
```

---

## 🚀 Implementation Priority

1. ✅ **HIGH**: Database schema (sudah ada)
2. ✅ **HIGH**: GET CashBalance
3. ✅ **HIGH**: GET CashTransactions
4. ✅ **HIGH**: GET ExpenseCategories
5. ✅ **MEDIUM**: POST CashTransfer
6. ✅ **MEDIUM**: POST CashExpense
7. ✅ **LOW**: DELETE CashTransactions
8. ✅ **LOW**: Auto income from orders (trigger/hook)

---

**Created**: 2025-12-18  
**For**: Beauty Salon Cash Management System
