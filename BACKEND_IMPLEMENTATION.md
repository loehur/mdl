# 🔧 Backend Implementation Guide

## 📋 Overview

Backend API untuk Cash Management System sudah dibuat! File controller lengkap dengan semua endpoints yang dibutuhkan.

---

## ✅ **Files Created:**

### 1. **Controller**
```
api/app/Controllers/Beauty_Salon/CashManagement.php
```

### 2. **Database Schema**
```
database_schema_cash_management.sql (updated with salon_id)
```

---

## 🚀 **Installation Steps:**

### **Step 1: Import Database Schema**

```bash
# Login to MySQL
mysql -u root -p

# Select database (sesuaikan nama database)
USE beauty_salon;

# Import schema
SOURCE c:/xampp82/htdocs/mdl/database_schema_cash_management.sql;
```

Atau via phpMyAdmin:
1. Buka phpMyAdmin
2. Pilih database `beauty_salon` (atau nama database Anda)
3. Klik tab "SQL"
4. Copy-paste isi file `database_schema_cash_management.sql`
5. Klik "Go"

---

### **Step 2: Verify Tables Created**

```sql
-- Check tables
SHOW TABLES LIKE '%cash%';
SHOW TABLES LIKE '%expense%';

-- Should show:
-- - expense_categories
-- - cash_transactions

-- Check views
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- Should show:
-- - v_cashier_balance
-- - v_main_cash_balance
```

---

### **Step 3: Verify Sample Data**

```sql
-- Check categories
SELECT * FROM expense_categories;
-- Should have 11 rows (9 expense + 2 non-expense)

-- Check sample transactions
SELECT * FROM cash_transactions;
-- Should have sample data if imported
```

---

## 🔌 **API Endpoints:**

Base URL: `/api/Beauty_Salon/CashManagement`

### **1. GET Balance**
```
GET /api/Beauty_Salon/CashManagement/balance/cashier
GET /api/Beauty_Salon/CashManagement/balance/main
```

**Response:**
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

**Authorization:**
- `cashier`: All users
- `main`: Admin only

---

### **2. GET Categories**
```
GET /api/Beauty_Salon/CashManagement/categories
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Bahan Salon",
      "is_expense": 1,
      "description": "Pembelian bahan dan produk salon",
      "is_active": 1
    }
  ]
}
```

---

### **3. GET Transactions**
```
GET /api/Beauty_Salon/CashManagement/transactions
GET /api/Beauty_Salon/CashManagement/transactions?type=expense
GET /api/Beauty_Salon/CashManagement/transactions?cash=main
GET /api/Beauty_Salon/CashManagement/transactions?type=expense&cash=cashier
```

**Query Params:**
- `type`: income | expense | transfer
- `cash`: cashier | main
- `limit`: default 100
- `offset`: default 0

**Response:**
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
      "category_name": "Bahan Salon",
      "is_expense": 1,
      "description": "Beli shampo",
      "notes": null,
      "reference_type": "manual",
      "created_at": "2025-12-18 10:00:00"
    }
  ]
}
```

---

### **4. POST Transfer**
```
POST /api/Beauty_Salon/CashManagement/transfer
```

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

**Response:**
```json
{
  "success": true,
  "message": "Transfer berhasil",
  "data": {
    "transaction_id": 123
  }
}
```

**Authorization:** Admin only

---

### **5. POST Expense**
```
POST /api/Beauty_Salon/CashManagement/expense
```

**Request Body:**
```json
{
  "cash_source": "main",
  "category_id": 3,
  "amount": 5000000,
  "description": "Bayar sewa gedung",
  "date": "2025-12-18",
  "notes": "Sewa bulan Januari"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Pengeluaran berhasil disimpan",
  "data": {
    "transaction_id": 124
  }
}
```

**Authorization:**
- `cash_source=cashier`: All users
- `cash_source=main`: Admin only

---

### **6. POST Delete Transaction**
```
POST /api/Beauty_Salon/CashManagement/deleteTransaction/123
```

**Response:**
```json
{
  "success": true,
  "message": "Transaksi berhasil dihapus"
}
```

**Authorization:** Admin only

**Validation:**
- Cannot delete transactions with `reference_type = 'order'`
- Must be owner (same salon_id)

---

## 🔒 **Authorization Logic:**

### **Admin Check:**
```php
private function isAdmin()
{
    $role = $_SESSION['salon_user_session']['user']['role'] ?? null;
    return $role === 'admin';
}
```

### **Endpoints Authorization:**

| Endpoint | All Users | Admin Only |
|----------|-----------|------------|
| GET balance/cashier | ✅ | ✅ |
| GET balance/main | ❌ | ✅ |
| GET categories | ✅ | ✅ |
| GET transactions | ✅ | ✅ |
| POST transfer | ❌ | ✅ |
| POST expense (cashier) | ✅ | ✅ |
| POST expense (main) | ❌ | ✅ |
| POST deleteTransaction | ❌ | ✅ |

---

## 🗄️ **Database Fields:**

### **cash_transactions:**
```sql
CREATE TABLE cash_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    salon_id INT NOT NULL,              -- ⚠️ IMPORTANT for multi-tenant
    transaction_type ENUM(...),
    transaction_date DATE,
    amount DECIMAL(15,2),
    cash_source ENUM('cashier', 'main'),
    transfer_from ENUM(...),
    transfer_to ENUM(...),
    category_id INT,
    description VARCHAR(255),
    notes TEXT,
    reference_type VARCHAR(50),         -- 'order', 'manual', 'transfer'
    reference_id INT,
    created_by INT,
    created_at DATETIME,
    updated_at DATETIME
);
```

### **expense_categories:**
```sql
CREATE TABLE expense_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    is_expense TINYINT(1),              -- 1=Expense, 0=Non-Expense
    description TEXT,
    is_active TINYINT(1),
    created_at DATETIME,
    updated_at DATETIME
);
```

---

## 🔄 **Auto Income from Orders:**

Tambahkan hook di `Orders.php` method `updateStatus`:

```php
// In Orders::updateStatus() - after line 207
if ($body['status'] === 'completed' && $data['pay_cash'] > 0) {
    // Auto insert income to cash_transactions
    $this->db($this->db_index)->insert('cash_transactions', [
        'salon_id' => $salon_id,
        'transaction_type' => 'income',
        'transaction_date' => date('Y-m-d'),
        'amount' => $data['pay_cash'],
        'cash_source' => 'cashier',
        'description' => 'Pembayaran Order #' . $id,
        'reference_type' => 'order',
        'reference_id' => $id,
        'created_by' => $user_id,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}
```

---

## 🧪 **Testing:**

### **Test via Postman/Thunder Client:**

#### **1. Get Cashier Balance:**
```
GET http://localhost/mdl/api/Beauty_Salon/CashManagement/balance/cashier
```

#### **2. Get Categories:**
```
GET http://localhost/mdl/api/Beauty_Salon/CashManagement/categories
```

#### **3. Transfer:**
```
POST http://localhost/mdl/api/Beauty_Salon/CashManagement/transfer
Content-Type: application/json

{
  "from": "cashier",
  "to": "main",
  "amount": 1000000,
  "description": "Test transfer"
}
```

#### **4. Add Expense:**
```
POST http://localhost/mdl/api/Beauty_Salon/CashManagement/expense
Content-Type: application/json

{
  "cash_source": "main",
  "category_id": 1,
  "amount": 500000,
  "description": "Test expense",
  "date": "2025-12-18"
}
```

---

## ⚠️ **Troubleshooting:**

### **Error: Table doesn't exist**
✓ Import SQL schema terlebih dahulu
✓ Check database name yang digunakan
✓ Verify di phpMyAdmin

### **Error: 403 Forbidden (Admin only)**
✓ Check session role = 'admin'
✓ Login sebagai admin user
✓ Verify `$_SESSION['salon_user_session']['user']['role']`

### **Error: Salon ID tidak ditemukan**
✓ Check session valid
✓ User sudah login
✓ Session `salon_user_session` exists

### **View tidak return data**
✓ Pastikan sudah ada data di `cash_transactions`
✓ Check `salon_id` match
✓ Run sample INSERT statements

---

## 📊 **Sample Testing Data:**

```sql
-- Assuming salon_id = 1

-- Add test income
INSERT INTO cash_transactions 
(salon_id, transaction_type, transaction_date, amount, cash_source, description, reference_type) 
VALUES (1, 'income', CURDATE(), 1000000, 'cashier', 'Test Income', 'manual');

-- Add test expense
INSERT INTO cash_transactions 
(salon_id, transaction_type, transaction_date, amount, cash_source, category_id, description, reference_type) 
VALUES (1, 'expense', CURDATE(), 100000, 'cashier', 1, 'Test Expense', 'manual');

-- Add test transfer
INSERT INTO cash_transactions 
(salon_id, transaction_type, transaction_date, amount, cash_source, transfer_from, transfer_to, description, reference_type) 
VALUES (1, 'transfer', CURDATE(), 500000, 'cashier', 'cashier', 'main', 'Test Transfer', 'transfer');
```

---

## ✅ **Verification Checklist:**

- [ ] Database tables created
- [ ] Sample categories data exists (11 rows)
- [ ] Views working (v_cashier_balance, v_main_cash_balance)
- [ ] Controller file exists in correct location
- [ ] GET balance/cashier returns data
- [ ] GET categories returns 11 items
- [ ] POST transfer works (admin only)
- [ ] POST expense works
- [ ] POST deleteTransaction works (admin only)
- [ ] Authorization checks working
- [ ] Multi-tenant (salon_id) working

---

## 🎯 **Next Steps:**

1. ✅ Import database schema
2. ✅ Test all endpoints via Postman
3. ✅ Verify authorization (admin vs non-admin)
4. ✅ Test frontend integration
5. ✅ Add auto income hook to Orders controller
6. ✅ Production deployment

---

**Created**: 2025-12-18  
**Status**: ✅ Backend Ready for Testing
