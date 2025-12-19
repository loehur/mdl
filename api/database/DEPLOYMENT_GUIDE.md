# WhatsApp Integration - Deployment Guide

## 📦 Files Generated

### SQL Schema:
- **`api/database/wa_tables_complete.sql`** - Complete schema untuk semua tabel WhatsApp

### Installation Scripts:
- **`api/database/install_wa_tables.php`** - Auto-installer (dengan safety confirmation)

### Documentation:
- **`api/docs/WA_TABLES_REFERENCE.md`** - Quick reference semua struktur tabel
- **`api/docs/WA_TABLES_RESTRUCTURE.md`** - Dokumentasi perubahan struktur

### Verification:
- **`api/database/migrations/verify_restructure.php`** - Script verifikasi setelah install

---

## 🚀 Fresh Install (Recommended)

### Step 1: Backup (WAJIB!)
```bash
# Backup semua tabel wa_*
mysqldump -u mdl_main -p mdl_main \
  wa_webhooks wa_customers wa_conversations wa_messages \
  > backup_wa_tables_$(date +%Y%m%d_%H%M%S).sql

# Atau backup semua database
mysqldump -u mdl_main -p mdl_main > backup_full_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Upload Files ke Server
```bash
# Via git
git add .
git commit -m "WhatsApp fresh install - new structure"
git push

# Di server
git pull origin main
```

### Step 3: Run SQL Import

**Option A: Via MySQL Command**
```bash
mysql -u mdl_main -p mdl_main < api/database/wa_tables_complete.sql
```

**Option B: Via PHP Script (Recommended)**
```bash
# 1. Edit install_wa_tables.php
# 2. Uncomment line: $confirmed = true;
# 3. Run:
php api/database/install_wa_tables.php
```

**Option C: Via phpMyAdmin**
1. Login ke phpMyAdmin
2. Select database `mdl_main`
3. Go to Import tab
4. Choose file: `api/database/wa_tables_complete.sql`
5. Click "Go"

### Step 4: Verify Installation
```bash
php api/database/migrations/verify_restructure.php
```

Atau query manual:
```sql
SHOW TABLES LIKE 'wa_%';
-- Harus muncul:
-- wa_webhooks
-- wa_customers  
-- wa_conversations
-- wa_messages_in
-- wa_messages_out
```

### Step 5: Test
1. **Test Outbound**:
   ```bash
   # Kirim pesan via API/app
   # Cek: SELECT * FROM wa_messages_out ORDER BY id DESC LIMIT 5;
   ```

2. **Test Inbound**:
   ```bash
   # Minta customer kirim pesan
   # Cek: SELECT * FROM wa_messages_in ORDER BY id DESC LIMIT 5;
   ```

3. **Test Webhook Update**:
   ```bash
   # Tunggu customer read message
   # Cek: SELECT * FROM wa_messages_out WHERE status='read' ORDER BY id DESC;
   ```

---

## 🔄 Migration dari Struktur Lama

Jika Anda punya data di tabel `wa_messages` lama:

### Step 1: Export Data Lama
```sql
-- Export outbound messages
SELECT * FROM wa_messages WHERE direction = 'out' 
INTO OUTFILE '/tmp/wa_messages_out_backup.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"'
LINES TERMINATED BY '\n';

-- Export inbound messages  
SELECT * FROM wa_messages WHERE direction = 'in'
INTO OUTFILE '/tmp/wa_messages_in_backup.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"'
LINES TERMINATED BY '\n';
```

### Step 2: Fresh Install
Ikuti "Fresh Install" steps di atas.

### Step 3: Import Data Lama (if needed)
Buat script custom untuk migrate data dari backup CSV ke tabel baru.
Mapping field:
- `wa_messages.direction='out'` → `wa_messages_out`
- `wa_messages.direction='in'` → `wa_messages_in`
- `wa_messages.message_type` → `type`
- `wa_messages.provider_message_id` → `message_id`

---

## 📊 Database Schema Changes

### Old Structure:
```
wa_messages (combined in/out)
├─ direction (in/out)
├─ message_type
├─ text
├─ created_at
└─ status

wa_conversations
├─ last_message (text)
└─ last_message_at (datetime)
```

### New Structure:
```
wa_messages_in (inbound only)
├─ type
├─ text  
├─ received_at
└─ status

wa_messages_out (outbound only)
├─ type
├─ content
├─ created_at, sent_at, delivered_at, read_at
└─ status (pending→accepted→sent→delivered→read)

wa_conversations
├─ last_in_at (datetime)
└─ last_out_at (datetime)
```

---

## ⚙️ Configuration Check

After installation, verify:

### 1. Database Config
File: `api/app/Config/DBC.php`
```php
const dbm = [
    'pro' => [
        0 => [
            "db" => "mdl_main",
            "user" => "mdl_main",
            "pass" => "your_password"
        ]
    ]
];
```

### 2. WhatsApp API Config
File: `api/app/Config/WhatsApp.php` (gitignored)
Pastikan config YCloud API sudah benar.

### 3. Webhook URL
Verify di YCloud dashboard:
```
Webhook URL: https://nalju.com/api/Webhook/WhatsApp
Events: 
- whatsapp.inbound_message.received
- whatsapp.message.updated
```

---

## 🐛 Troubleshooting

### Error: Table already exists
```sql
-- Drop old tables manually
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS wa_messages;
DROP TABLE IF EXISTS wa_messages_in;
DROP TABLE IF EXISTS wa_messages_out;
DROP TABLE IF EXISTS wa_conversations;
DROP TABLE IF EXISTS wa_customers;
DROP TABLE IF EXISTS wa_webhooks;
SET FOREIGN_KEY_CHECKS = 1;

-- Then run install again
```

### Error: Foreign key constraint fails
```bash
# Pastikan parent tables dibuat dulu (order penting):
# 1. wa_customers
# 2. wa_conversations
# 3. wa_messages_in & wa_messages_out
```

### Messages not saving
```bash
# Check log
tail -f /var/log/php-error.log

# Check webhook
SELECT * FROM wa_webhooks ORDER BY id DESC LIMIT 10;

# Check DB permissions
SHOW GRANTS FOR 'mdl_main'@'localhost';
```

---

## 📞 Support

Jika ada masalah:
1. Check log file
2. Run verify script
3. Check common queries di `WA_TABLES_REFERENCE.md`
4. Review webhook events di `wa_webhooks` table

---

**Created:** 2025-12-20  
**Version:** 2.0 (Restructured)  
**Status:** ✅ Ready for Production
