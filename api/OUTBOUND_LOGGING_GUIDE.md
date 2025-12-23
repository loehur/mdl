# Panduan Log Outbound WhatsApp

## Ringkasan
File ini berisi panduan untuk memantau log outbound WhatsApp dan troubleshooting jika pesan tidak tersimpan ke database.

## Lokasi Log

### 1. Log Utama Outbound
Log detail disimpan di file: `api/logs/outbound_log.txt`

File ini mencatat setiap langkah proses penyimpanan pesan outbound:
- ✓ Validasi data (phone number, message ID)
- ✓ Ekstraksi konten pesan (text, template, media)
- ✓ Loading database class
- ✓ Pencarian/pembuatan conversation
- ✓ Insert data ke tabel `wa_messages_out`
- ✓ Error detail jika terjadi kegagalan

### 2. Log Error
Log error disimpan di: `api/logs/wa_error.txt`

### 3. Log Debug Umum
Log debug WhatsApp: `api/logs/wa_debug.txt`

## Format Log

### Setiap proses outbound akan menghasilkan log seperti ini:

```
[OUTBOUND_SAVE] ======== START SAVE OUTBOUND MESSAGE ========
[OUTBOUND_SAVE] Payload: {"from":"+6281234567890","to":"+6287654321098","type":"text"...}
[OUTBOUND_SAVE] Response: {"id":"msg_abc123","wamid":"wamid.xyz789"...}
[OUTBOUND_SAVE] Extracted Data - Phone: +6287654321098, Type: text, MsgID: msg_abc123, WAMID: wamid.xyz789
[OUTBOUND_SAVE] ✓ Validation passed
[OUTBOUND_SAVE] Content Type: TEXT - Content: Halo, terima kasih telah menghubungi kami
[OUTBOUND_SAVE] Last Message Text: Halo, terima kasih telah menghubungi kami
[OUTBOUND_SAVE] ✓ DB class already available
[OUTBOUND_SAVE] Creating DB instance...
[OUTBOUND_SAVE] ✓ DB instance created successfully
[OUTBOUND_SAVE] Looking for conversation with wa_number: +6287654321098
[OUTBOUND_SAVE] Conversation query result: Found 1 rows
[OUTBOUND_SAVE] ✓ Found existing conversation ID: 42
[OUTBOUND_SAVE] Updating conversation with: {"last_message":"Halo, terima kasih...","last_out_at":"2025-12-23 06:54:30"}
[OUTBOUND_SAVE] ✓ Conversation updated
[OUTBOUND_SAVE] ✓ Conversation ID confirmed: 42
[OUTBOUND_SAVE] Preparing to insert message to wa_messages_out
[OUTBOUND_SAVE] Message Data: {"conversation_id":42,"phone":"+6287654321098"...}
[OUTBOUND_SAVE] Insert result: SUCCESS - ID: 1234
[OUTBOUND_SAVE] ✓✓✓ MESSAGE SUCCESSFULLY SAVED TO DATABASE ✓✓✓
[OUTBOUND_SAVE] Local ID: 1234, Message ID: msg_abc123, Phone: +6287654321098
[OUTBOUND_SAVE] ======== END SAVE OUTBOUND MESSAGE (SUCCESS) ========
```

## Troubleshooting

### Jika pesan TIDAK tersimpan, cek log untuk menemukan titik kegagalan:

#### 1. **Validation Failed**
```
[OUTBOUND_SAVE] !! VALIDATION FAILED - Phone: EMPTY, MessageID: msg_abc123
```
**Penyebab:** Nomor telepon kosong atau message ID tidak ada
**Solusi:** Pastikan payload API memiliki field `to` dan response memiliki field `id`

#### 2. **DB Class Not Found**
```
[OUTBOUND_SAVE] !! DB.php NOT FOUND at /path/to/DB.php
```
**Penyebab:** File DB.php tidak ditemukan
**Solusi:** Pastikan file `api/app/Core/DB.php` ada

#### 3. **DB Instance Failed**
```
[OUTBOUND_SAVE] !! DB instance creation FAILED or missing get_where method
```
**Penyebab:** Database connection gagal
**Solusi:** Cek koneksi database di config

#### 4. **Conversation Not Created**
```
[OUTBOUND_SAVE] !! CRITICAL: conversationId is NULL or 0
```
**Penyebab:** Gagal membuat atau menemukan conversation
**Solusi:** Cek tabel `wa_conversations` dan pastikan bisa insert/update

#### 5. **Insert Failed**
```
[OUTBOUND_SAVE] !! INSERT TO wa_messages_out FAILED
[OUTBOUND_SAVE] Database Error: Table 'mdl.wa_messages_out' doesn't exist
```
**Penyebab:** Tabel tidak ada atau ada error SQL
**Solusi:** 
- Pastikan tabel `wa_messages_out` ada
- Cek struktur tabel sesuai dengan field yang di-insert
- Lihat database error untuk detail lebih lanjut

#### 6. **Exception**
```
[OUTBOUND_SAVE] !! EXCEPTION CAUGHT IN SAVE OUTBOUND
[OUTBOUND_SAVE] Exception Message: Call to undefined method...
[OUTBOUND_SAVE] Exception File: /path/to/file.php
[OUTBOUND_SAVE] Exception Line: 123
[OUTBOUND_SAVE] Stack Trace: ...
```
**Penyebab:** Error PHP/coding
**Solusi:** Lihat stack trace untuk detail error dan perbaiki kode

## Cara Menggunakan Log

### 1. **Monitor Real-time**
Gunakan command berikut untuk melihat log secara real-time:

```bash
# Windows PowerShell
Get-Content api/logs/outbound_log.txt -Wait -Tail 50

# Atau gunakan tail di Git Bash/WSL
tail -f api/logs/outbound_log.txt
```

### 2. **Cari Log Spesifik**
```bash
# Cari berdasarkan nomor telepon
Select-String -Path api/logs/outbound_log.txt -Pattern "+628123456789"

# Cari semua error
Select-String -Path api/logs/outbound_log.txt -Pattern "!!"

# Cari pesan yang berhasil
Select-String -Path api/logs/outbound_log.txt -Pattern "SUCCESSFULLY SAVED"
```

### 3. **Clear Log (Optional)**
Jika file log terlalu besar, bisa dihapus/clear:
```bash
# Backup dulu
Copy-Item api/logs/outbound_log.txt api/logs/outbound_log_backup.txt

# Clear isi file
Clear-Content api/logs/outbound_log.txt
```

## Struktur Tabel yang Dibutuhkan

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

## Checklist Debug

Gunakan checklist ini untuk debug masalah outbound:

- [ ] Periksa `api/logs/outbound_log.txt` untuk log terbaru
- [ ] Pastikan payload memiliki `to` dan `type`
- [ ] Pastikan response API memiliki `id`
- [ ] Cek apakah DB class berhasil di-load
- [ ] Cek apakah DB instance berhasil dibuat
- [ ] Cek apakah conversation ditemukan/dibuat
- [ ] Cek apakah insert ke `wa_messages_out` berhasil
- [ ] Jika ada exception, lihat stack trace
- [ ] Cek struktur tabel `wa_messages_out` dan `wa_conversations`
- [ ] Cek koneksi database

## Contact
Jika masih ada masalah setelah mengecek log, hubungi developer dengan menyertakan:
1. Isi file `api/logs/outbound_log.txt` (yang relevan)
2. Isi file `api/logs/wa_error.txt` (jika ada)
3. Nomor telepon yang digunakan untuk testing
4. Waktu (timestamp) ketika pesan dikirim
