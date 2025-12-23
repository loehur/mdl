# SUMMARY: Error-Only Logging untuk Outbound WhatsApp

## ✅ Yang Sudah Dilakukan

### **Simplified Error-Only Logging**

File yang dimodifikasi: `api/app/Helpers/WhatsAppService.php`

Method `saveOutboundMessage()` sekarang **HANYA mencatat ERROR**, tidak ada log untuk proses yang berhasil.

## 📍 Lokasi File Log

Log error akan tersimpan di:

```
api/logs/wa_error/saveoutbound/2025/12/23.log
```

Format: `logs/{app}/{controller}/{tahun}/{bulan}/{tanggal}.log`

## 🔴 Log yang Akan Muncul (Hanya Error)

### 1. **Validation Failed**
```
07:12:30 !! VALIDATION FAILED - Phone: EMPTY, MessageID: msg_123 | Payload: {...}
```
**Artinya:** Nomor telepon atau message ID kosong
**Solusi:** Periksa payload API

### 2. **DB.php Not Found**
```
07:12:30 !! DB.php NOT FOUND at /path/to/DB.php
```
**Artinya:** File database class tidak ditemukan
**Solusi:** Pastikan file `api/app/Core/DB.php` ada

### 3. **DB Class Failed to Load**
```
07:12:30 !! DB class FAILED to load after require
```
**Artinya:** DB class gagal di-load meskipun file ada
**Solusi:** Cek syntax error di DB.php

### 4. **DB Instance Creation Failed**
```
07:12:30 !! DB instance creation FAILED or missing get_where method
```
**Artinya:** Koneksi database gagal
**Solusi:** Cek konfigurasi database

### 5. **Conversation Creation Failed**
```
07:12:30 !! FAILED to get/create Conversation ID for +628123456789 | Payload: {...}
```
**Artinya:** Gagal membuat atau menemukan conversation
**Solusi:** Cek tabel `wa_conversations` dan permission

### 6. **Insert Failed**
```
07:12:30 !! INSERT FAILED to wa_messages_out | Phone: +628123456789, MsgID: msg_123 | DB Error: Table doesn't exist | Data: {...}
```
**Artinya:** Gagal insert ke tabel `wa_messages_out`
**Solusi:** Cek struktur tabel dan database error message

### 7. **Exception**
```
07:12:30 !! EXCEPTION in saveOutboundMessage: Call to undefined method at /path/file.php:123
07:12:30 !! Stack Trace: [full stack trace]
07:12:30 !! Payload was: {...}
```
**Artinya:** Error PHP/coding
**Solusi:** Lihat stack trace dan perbaiki bug

## ✅ Jika Tidak Ada Error = Sukses

**Jika file log kosong atau tidak ada entry untuk pesan tertentu = pesan berhasil disimpan!**

Untuk validasi, cek database:
```sql
SELECT * FROM wa_messages_out ORDER BY id DESC LIMIT 10;
```

## 🧪 Cara Monitor Error

### 1. **Monitor Real-time**
```powershell
cd C:\xampp82\htdocs\mdl\api

# Monitor log error
Get-Content "logs/wa_error/saveoutbound/2025/12/23.log" -Wait -Tail 50
```

### 2. **Lihat Error Hari Ini**
```powershell
Get-Content "logs/wa_error/saveoutbound/2025/12/23.log"
```

### 3. **Cari Error Spesifik**
```powershell
# Cari berdasarkan nomor telepon
Select-String -Path "logs/wa_error/saveoutbound/2025/12/23.log" -Pattern "+628123456789"

# Cari validation error
Select-String -Path "logs/wa_error/saveoutbound/2025/12/23.log" -Pattern "VALIDATION FAILED"

# Cari database error
Select-String -Path "logs/wa_error/saveoutbound/2025/12/23.log" -Pattern "INSERT FAILED"
```

### 4. **Hitung Jumlah Error Hari Ini**
```powershell
(Get-Content "logs/wa_error/saveoutbound/2025/12/23.log" | Measure-Object -Line).Lines
```

## 🎯 Troubleshooting Checklist

Jika ada pesan outbound yang tidak tersimpan:

1. ✅ **Cek log error** di `logs/wa_error/saveoutbound/[tanggal].log`
2. ✅ **Jika ada error**, lihat tipe error dan solusinya di atas
3. ✅ **Jika tidak ada error**, berarti pesan berhasil tersimpan - cek database
4. ✅ **Jika file log tidak ada**, berarti tidak ada error sama sekali

## 📊 Struktur Tabel yang Dibutuhkan

### Table: `wa_conversations`
```sql
CREATE TABLE `wa_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wa_number` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'open',
  `last_message` text,
  `last_in_at` datetime DEFAULT NULL,
  `last_out_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `wa_number` (`wa_number`)
);
```

### Table: `wa_messages_out`
```sql
CREATE TABLE `wa_messages_out` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `wamid` varchar(255) DEFAULT NULL,
  `message_id` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL,
  `content` text,
  `template_params` text,
  `media_url` varchar(500) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `message_id` (`message_id`),
  KEY `phone` (`phone`)
);
```

## 🔧 Quick Reference Error Table

| Error Pattern | Penyebab | Priority | Action |
|--------------|----------|----------|---------|
| `VALIDATION FAILED - Phone: EMPTY` | No phone number | 🔴 High | Check API payload |
| `VALIDATION FAILED - MessageID: EMPTY` | No message ID | 🔴 High | Check API response |
| `DB.php NOT FOUND` | Missing DB file | 🔴 Critical | Restore DB.php |
| `DB class FAILED to load` | DB.php syntax error | 🔴 Critical | Fix DB.php |
| `DB instance creation FAILED` | DB connection error | 🔴 Critical | Check DB config |
| `FAILED to get/create Conversation` | Conversation insert failed | 🟡 Medium | Check table structure |
| `INSERT FAILED to wa_messages_out` | Message insert failed | 🟡 Medium | Check table + SQL |
| `EXCEPTION` | PHP error | 🔴 High | Check stack trace |

## 📝 Keuntungan Error-Only Logging

✅ **File log lebih kecil** - Tidak penuh dengan log sukses
✅ **Lebih mudah troubleshooting** - Hanya fokus ke masalah
✅ **Performance lebih baik** - Tidak banyak write ke disk
✅ **Tidak ada noise** - Semua entry di log adalah masalah yang perlu diperhatikan

## 🎯 Next Steps

1. **Kirim pesan outbound** dari aplikasi
2. **Cek log error** - Jika kosong = bagus!
3. **Jika ada error** - Gunakan tabel di atas untuk troubleshooting
4. **Validasi di database** - Cek tabel `wa_messages_out`

---

**Dibuat:** 2025-12-23
**Updated:** 2025-12-23 07:12
**File terkait:**
- `api/app/Helpers/WhatsAppService.php` (modified - error-only logging)
