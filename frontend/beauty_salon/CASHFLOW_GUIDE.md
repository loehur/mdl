# 💰 Cash Flow - Dokumentasi Fitur

## 📊 Overview

Halaman **Cash Flow** menampilkan laporan kas masuk dari transaksi tunai yang sudah selesai (completed).

---

## ✨ Fitur Utama

### 1. 📅 **Filter Periode**
- Pilih tanggal mulai dan tanggal akhir
- Default: Bulan berjalan (dari tanggal 1 hingga hari ini)
- Klik tombol **Filter** untuk apply

### 2. 💵 **Saldo Periode** (Card Hijau)
- Menampilkan total kas tunai untuk **periode tertentu** yang dipilih
- Warna: Gradient Emerald/Teal
- Icon: Calendar
- Info tambahan:
  - Rentang tanggal yang dipilih
  - Jumlah transaksi tunai dalam periode

### 3. 💎 **Saldo Total** (Card Ungu)
- Menampilkan total kas tunai **keseluruhan waktu** (tanpa filter periode)
- Warna: Gradient Violet/Purple
- Icon: Dollar Sign
- Info tambahan:
  - Label "Keseluruhan waktu"
  - Akumulasi semua transaksi tunai sejak awal

### 4. 📋 **Tabel Riwayat Transaksi**
- Menampilkan detail transaksi tunai dalam periode yang dipilih
- Kolom:
  - Tanggal & Waktu
  - Order ID
  - Nama Pelanggan
  - Metode Pembayaran
  - Nominal Masuk (Tunai)
- Sorting: Terbaru di atas

---

## 🔍 Logika Perhitungan

### Saldo Periode (`totalCashPeriod`)
```javascript
// Hanya menghitung order yang:
1. Status = 'completed'
2. pay_cash > 0
3. Tanggal order dalam rentang [startDate, endDate]
```

### Saldo Total (`totalCashAllTime`)
```javascript
// Menghitung SEMUA order yang:
1. Status = 'completed'
2. pay_cash > 0
3. Tanpa filter tanggal (all time)
```

---

## 🎨 Design

### Saldo Periode (Emerald)
- Background: `from-emerald-500 to-teal-600`
- Lebih fokus ke **periode terpilih**
- Cocok untuk analisis periode tertentu

### Saldo Total (Violet)
- Background: `from-violet-500 to-purple-600`
- Menunjukkan **akumulasi total**
- Berguna untuk melihat gambaran besar

---

## 💡 Use Case

| Skenario | Saldo Periode | Saldo Total |
|----------|---------------|-------------|
| Laporan bulan ini | ✅ Cocok | ❌ Terlalu luas |
| Total keseluruhan | ❌ Terlalu sempit | ✅ Cocok |
| Perbandingan periode | ✅ Bisa diubah | ✅ Referensi tetap |
| Target bulanan | ✅ Tracking progress | ✅ Context |

---

## 📱 Responsive Design

- **Mobile (< 768px)**: Cards ditumpuk vertikal (1 kolom)
- **Desktop (≥ 768px)**: Cards berdampingan (2 kolom)
- Tabel: Horizontal scroll jika konten terlalu lebar

---

## 🛠️ Troubleshooting

### Saldo Total = 0 padahal ada transaksi
- ✓ Cek apakah transaksi sudah status `completed`
- ✓ Cek kolom `pay_cash` di database tidak null/0
- ✓ Periksa console untuk error API

### Saldo Periode tidak update setelah filter
- ✓ Pastikan klik tombol **Filter**
- ✓ Cek format tanggal valid (YYYY-MM-DD)
- ✓ Refresh halaman jika perlu

### Perbedaan Saldo Periode vs Saldo Total
- Ini **normal**! Saldo Total mencakup SEMUA waktu
- Saldo Periode hanya untuk rentang tanggal terpilih

---

## 🔄 Update History

- **2025-12-18**: Added "Saldo Total" feature untuk menampilkan akumulasi keseluruhan waktu
- **Previous**: Single card "Saldo Periode" saja

---

**File**: `src/user_area/CashFlow.vue`
