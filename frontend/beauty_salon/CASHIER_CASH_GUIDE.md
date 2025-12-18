# 💼 Kas Kasir (Cashier Cash) - Dokumentasi

## 📋 Overview

Halaman **Kas Kasir** adalah sistem manajemen kas untuk mencatat dan mengelola:
- 💰 Saldo total kas (tanpa filter periode)
- 💸 Pengeluaran kasir
- 📊 Riwayat transaksi pengeluaran

---

## ✨ Fitur Utama

### 1. 💵 **Card Saldo Kas Kasir**

Menampilkan informasi keuangan real-time:

**Data yang ditampilkan:**
- **Saldo Kas Kasir**: Total kas yang tersedia (Pemasukan - Pengeluaran)
- **↑ Pemasukan**: Total uang tunai dari order completed (all time)
- **↓ Pengeluaran**: Total semua pengeluaran yang dicatat

**Visual:**
- Gradient: Indigo ke Blue
- Icon: Wallet/Cash
- Large display untuk saldo utama

---

### 2. 📝 **Form Input Pengeluaran**

Form untuk mencatat pengeluaran kasir:

**Field yang tersedia:**
1. **Keterangan** (Required)
   - Deskripsi pengeluaran
   - Contoh: "Beli sabun cuci", "Transport", dll

2. **Jumlah** (Required)
   - Nominal dalam Rupiah
   - Min: 0
   - Step: 1000

3. **Tanggal** (Required)
   - Default: Hari ini
   - Format: Date picker

4. **Catatan** (Optional)
   - Catatan tambahan
   - Textarea untuk detail lebih lanjut

**Tombol Submit:**
- Warna: Merah (untuk pengeluaran)
- Loading state saat proses simpan

---

### 3. 📋 **Tabel Riwayat Pengeluaran**

Menampilkan semua pengeluaran yang telah dicatat:

**Kolom Tabel:**
- **Tanggal**: Tanggal transaksi
- **Keterangan**: Deskripsi + catatan tambahan (jika ada)
- **Jumlah**: Nominal dengan format minus (-)
- **Aksi**: Tombol hapus

**Features:**
- Sorting otomatis (terbaru di atas)
- Counter jumlah transaksi
- Empty state jika belum ada data
- Loading state saat fetch data

---

## 🔢 Perhitungan Saldo

### Formula:
```
Saldo Kas Kasir = Total Pemasukan - Total Pengeluaran
```

### Detail:

**Total Pemasukan:**
```javascript
// Dari order completed yang bayar tunai (all time)
totalIncome = SUM(order.pay_cash) 
WHERE order.status = 'completed' 
AND order.pay_cash > 0
```

**Total Pengeluaran:**
```javascript
// Dari semua pengeluaran yang dicatat
totalExpense = SUM(expense.amount)
```

**Saldo Kas:**
```javascript
cashBalance = totalIncome - totalExpense
```

---

## 🗄️ Database / API

### Endpoint yang Digunakan:

#### 1. **GET /api/Beauty_Salon/Orders**
- Mengambil data order untuk hitung total pemasukan
- Filter: `status = 'completed'`
- Field: `pay_cash`

#### 2. **GET /api/Beauty_Salon/CashierExpenses**
- Mengambil semua data pengeluaran kasir
- Return: Array of expenses
- Sorting: DESC by date

#### 3. **POST /api/Beauty_Salon/CashierExpenses**
- Menyimpan pengeluaran baru
- Body:
  ```json
  {
    "description": "string",
    "amount": number,
    "date": "YYYY-MM-DD",
    "notes": "string",
    "created_at": "ISO datetime"
  }
  ```

#### 4. **DELETE /api/Beauty_Salon/CashierExpenses/{id}**
- Menghapus pengeluaran berdasarkan ID
- Konfirmasi sebelum hapus

---

## 📱 Layout

### Desktop (>= 1024px):
```
┌──────────────────────────────────────────┐
│  Kas Kasir                               │
├──────────────────────────────────────────┤
│  [   SALDO KAS KASIR CARD - FULL WIDTH  ]│
├─────────────────┬────────────────────────┤
│ Form Input (1/3)│ Tabel Riwayat (2/3)   │
│                 │                        │
│ [Form Sticky]   │ [Table Scrollable]     │
│                 │                        │
└─────────────────┴────────────────────────┘
```

### Mobile (< 1024px):
```
┌──────────────────┐
│  Kas Kasir       │
├──────────────────┤
│ [SALDO CARD]     │
├──────────────────┤
│ [Form Input]     │
│                  │
├──────────────────┤
│ [Tabel Riwayat]  │
│                  │
└──────────────────┘
```

---

## 🎨 Design Elements

### Color Scheme:

| Element | Color |
|---------|-------|
| Saldo Card | Indigo-Blue gradient |
| Form Submit | Red 600 |
| Pemasukan | Green 200 |
| Pengeluaran | Red 200 |
| Icons | Context-based |

### Typography:
- Saldo: 5xl font-bold
- Labels: sm font-medium
- Values: Semibold

---

## 🔐 Validasi

### Form Validation:
- ✅ Keterangan: Required
- ✅ Jumlah: Required, min=0, step=1000
- ✅ Tanggal: Required, date format
- ❌ Catatan: Optional

### Business Logic:
- Tidak ada validasi minimum/maximum amount
- Tanggal bisa di masa lalu atau hari ini
- Saldo bisa negatif jika pengeluaran > pemasukan

---

## 💡 Use Cases

### 1. **Catat Pengeluaran Operasional**
```
User: Kasir
Action: Input pengeluaran untuk beli supplies
Flow:
1. Isi form (keterangan, jumlah, tanggal)
2. Submit
3. Saldo otomatis berkurang
4. Muncul di riwayat
```

### 2. **Cek Saldo Kas**
```
User: Owner/Manager
Action: Monitoring saldo kas real-time
Flow:
1. Buka halaman Kas Kasir
2. Lihat card saldo (auto-calculate)
3. Cek breakdown pemasukan & pengeluaran
```

### 3. **Audit Pengeluaran**
```
User: Admin
Action: Review semua pengeluaran
Flow:
1. Scroll tabel riwayat
2. Lihat detail (keterangan + catatan)
3. Hapus jika ada kesalahan input
```

---

## 🆚 Perbedaan dengan Cash Flow

| Fitur | Cash Flow | Kas Kasir |
|-------|-----------|-----------|
| **Focus** | Laporan pemasukan | Manajemen kas + pengeluaran |
| **Filter Periode** | ✅ Ada | ❌ Tidak ada (all time) |
| **Saldo** | 2 cards (periode & total) | 1 card (kas kasir) |
| **Input Pengeluaran** | ❌ Tidak ada | ✅ Ada + Form |
| **Tabel** | Riwayat pemasukan | Riwayat pengeluaran |
| **Tujuan** | Reporting/Analytics | Cash management |

---

## 🛠️ Troubleshooting

### Saldo tidak update setelah input pengeluaran
- ✓ Refresh data dengan reload halaman
- ✓ Cek API response di console
- ✓ Pastikan `fetchExpenses()` dipanggil setelah submit

### Tombol hapus tidak muncul
- ✓ Pastikan data expense memiliki `id`
- ✓ Cek permission user (jika ada role-based)

### Saldo negatif
- Ini **normal** jika pengeluaran melebihi pemasukan
- Bukan bug, tapi indikasi kas kurang

---

## 📍 Navigation

**Menu Location**: Main Menu (setelah Laporan Kas)

**Route**: `/cashier-cash`

**Icon**: 💼 Wallet icon

**Title**: "Kas Kasir"

---

## 🔄 Update History

- **2025-12-18**: Initial creation - Kas Kasir feature
  - Saldo total calculation
  - Form input pengeluaran
  - Riwayat pengeluaran dengan delete

---

**File**: `src/user_area/CashierCash.vue`  
**Created**: 2025-12-18
