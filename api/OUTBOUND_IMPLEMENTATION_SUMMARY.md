# SUMMARY: Logging Lengkap Outbound WhatsApp

## ✅ Yang Sudah Dilakukan

### 1. **Menambahkan Logging Komprehensif di WhatsAppService.php**

File yang dimodifikasi: `api/app/Helpers/WhatsAppService.php`

Method `saveOutboundMessage()` sekarang memiliki logging lengkap di setiap langkah:

- **Start/End markers** - Untuk memudahkan tracking satu proses lengkap
- **Input logging** - Payload dan response dari API
- **Validation logging** - Cek nomor telepon dan message ID
- **Content extraction logging** - Log untuk text, template, atau media
- **Database class loading** - Cek apakah DB.php berhasil di-load
- **Database instance creation** - Cek koneksi database
- **Conversation lookup/creation** - Log pencarian dan pembuatan conversation
- **Message insert** - Log insert ke tabel `wa_messages_out`
- **Success/Failure markers** - Jelas menunjukkan sukses atau gagal
- **Exception handling** - Full stack trace jika terjadi error

### 2. **Format Log yang Mudah Dibaca**

Setiap log entry memiliki prefix `[OUTBOUND_SAVE]` sehingga mudah dicari.

Contoh log sukses:
```
06:54:30 [OUTBOUND_SAVE] ======== START SAVE OUTBOUND MESSAGE ========
06:54:30 [OUTBOUND_SAVE] Extracted Data - Phone: +628123456789, Type: text, MsgID: msg_abc, WAMID: wamid_xyz
06:54:30 [OUTBOUND_SAVE] ✓ Validation passed
06:54:30 [OUTBOUND_SAVE] Content Type: TEXT - Content: Halo
06:54:30 [OUTBOUND_SAVE] ✓ DB class already available
06:54:30 [OUTBOUND_SAVE] ✓ DB instance created successfully
06:54:30 [OUTBOUND_SAVE] ✓ Found existing conversation ID: 42
06:54:30 [OUTBOUND_SAVE] ✓✓✓ MESSAGE SUCCESSFULLY SAVED TO DATABASE ✓✓✓
06:54:30 [OUTBOUND_SAVE] ======== END SAVE OUTBOUND MESSAGE (SUCCESS) ========
```

Contoh log gagal:
```
06:55:00 [OUTBOUND_SAVE] ======== START SAVE OUTBOUND MESSAGE ========
06:55:00 [OUTBOUND_SAVE] !! VALIDATION FAILED - Phone: EMPTY, MessageID: msg_123
06:55:00 [OUTBOUND_SAVE] ======== END SAVE OUTBOUND MESSAGE (FAILED) ========
```

### 3. **Panduan Lengkap**

File panduan: `api/OUTBOUND_LOGGING_GUIDE.md`

Berisi:
- Lokasi file log
- Format log yang dihasilkan
- Troubleshooting untuk setiap jenis error
- Struktur tabel database yang dibutuhkan
- Command untuk monitor log real-time
- Checklist debug

## 📍 Lokasi File Log

Berdasarkan Log class yang ada, log akan tersimpan di:

```
api/logs/outbound_log/saveoutbound/2025/12/23.log
```

Format: `logs/{app}/{controller}/{tahun}/{bulan}/{tanggal}.log`

Dimana:
- `{app}` = 'outbound_log'
- `{controller}` = 'saveoutbound'
- `{tahun}` = 2025
- `{bulan}` = 12
- `{tanggal}` = 23.log

## 🧪 Cara Test

### 1. **Kirim Pesan Outbound**

Gunakan endpoint API untuk mengirim pesan WhatsApp (misalnya dari CMS Chat atau endpoint lain yang menggunakan WhatsAppService).

### 2. **Monitor Log Real-time**

Buka PowerShell dan jalankan:

```powershell
cd C:\xampp82\htdocs\mdl\api

# Buat folder logs jika belum ada
New-Item -ItemType Directory -Force -Path "logs/outbound_log/saveoutbound/2025/12"

# Monitor log
Get-Content "logs/outbound_log/saveoutbound/2025/12/23.log" -Wait -Tail 100
```

### 3. **Cek Hasil**

Setelah mengirim pesan, log akan muncul di terminal. Perhatikan:

✅ **Jika berhasil**, akan muncul:
```
[OUTBOUND_SAVE] ✓✓✓ MESSAGE SUCCESSFULLY SAVED TO DATABASE ✓✓✓
```

❌ **Jika gagal**, akan muncul error dengan prefix `!!`:
```
[OUTBOUND_SAVE] !! VALIDATION FAILED - ...
[OUTBOUND_SAVE] !! DB.php NOT FOUND at ...
[OUTBOUND_SAVE] !! INSERT TO wa_messages_out FAILED
```

### 4. **Cek Database**

Jika log menunjukkan sukses, cek tabel:

```sql
-- Cek pesan terakhir di wa_messages_out
SELECT * FROM wa_messages_out ORDER BY id DESC LIMIT 10;

-- Cek conversation yang di-update
SELECT * FROM wa_conversations ORDER BY last_out_at DESC LIMIT 10;
```

## 🔧 Troubleshooting Quick Reference

| Error di Log | Penyebab | Solusi |
|-------------|----------|--------|
| `!! VALIDATION FAILED - Phone: EMPTY` | Nomor telepon kosong | Cek payload API, pastikan ada field `to` |
| `!! VALIDATION FAILED - MessageID: EMPTY` | Message ID kosong | Cek response API, pastikan ada field `id` |
| `!! DB.php NOT FOUND` | File DB.php tidak ada | Pastikan `api/app/Core/DB.php` ada |
| `!! DB instance creation FAILED` | Koneksi DB gagal | Cek konfigurasi database |
| `!! conversationId is NULL` | Gagal buat conversation | Cek tabel `wa_conversations` |
| `!! INSERT TO wa_messages_out FAILED` | Gagal insert | Cek struktur tabel dan error message |
| `!! EXCEPTION CAUGHT` | Error PHP | Lihat stack trace di log |

## 📊 Command Berguna

### Lihat 50 baris terakhir
```powershell
Get-Content "logs/outbound_log/saveoutbound/2025/12/23.log" -Tail 50
```

### Cari semua error
```powershell
Select-String -Path "logs/outbound_log/saveoutbound/2025/12/23.log" -Pattern "!!"
```

### Cari pesan yang berhasil
```powershell
Select-String -Path "logs/outbound_log/saveoutbound/2025/12/23.log" -Pattern "SUCCESSFULLY SAVED"
```

### Cari berdasarkan nomor telepon
```powershell
Select-String -Path "logs/outbound_log/saveoutbound/2025/12/23.log" -Pattern "+628123456789"
```

### Hitung berapa kali sukses hari ini
```powershell
(Select-String -Path "logs/outbound_log/saveoutbound/2025/12/23.log" -Pattern "SUCCESSFULLY SAVED").Count
```

## 🎯 Next Steps

1. **Test kirim pesan outbound** dari aplikasi Anda
2. **Monitor log** untuk melihat apakah ada yang tersimpan
3. **Jika gagal**, lihat error message dan gunakan troubleshooting guide
4. **Jika berhasil**, cek database untuk konfirmasi

## 📝 Notes

- Log ini hanya untuk **outbound messages** (pesan keluar)
- Setiap pesan akan menghasilkan satu set log lengkap dari START sampai END
- File log dibuat per hari (format: `DD.log`)
- Log tidak akan mengganggu proses pengiriman pesan (wrapped dalam try-catch)
- Jika ada exception, tetap akan ter-log dengan detail lengkap

---

**Dibuat:** 2025-12-23
**File terkait:**
- `api/app/Helpers/WhatsAppService.php` (modified)
- `api/OUTBOUND_LOGGING_GUIDE.md` (created)
- `api/OUTBOUND_IMPLEMENTATION_SUMMARY.md` (this file)
