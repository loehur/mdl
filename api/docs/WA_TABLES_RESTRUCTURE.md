# WhatsApp Tables Restructure - Complete Guide

## 📋 Overview

Memisahkan `wa_messages` menjadi 2 tabel terpisah untuk kemudahan penggunaan:
- **wa_messages_out** - Pesan yang kita kirim ke customer
- **wa_messages_in** - Pesan yang masuk dari customer

## 🗄️ Database Changes

### Tables Modified/Created:

#### 1. **wa_messages** (DROPPED)
Tabel lama yang menggabungkan inbound & outbound - DIHAPUS

#### 2. **wa_messages_out** (NEW)
Struktur untuk outbound messages:
```sql
- id (BIGINT AUTO_INCREMENT)
- conversation_id (BIGINT FK)
- phone (VARCHAR 20)
- wamid (VARCHAR 255) - WhatsApp Message ID
- message_id (VARCHAR 100) - Provider ID
- type (ENUM: text, template, image, document, video, audio)
- content (TEXT) - Message text atau template name
- template_params (TEXT) - JSON params untuk template
- media_url (TEXT) - URL media
- status (ENUM: pending, accepted, sent, delivered, read, failed)
- error_message (TEXT)
- created_at (DATETIME)
- sent_at (DATETIME) - dari webhook
- delivered_at (DATETIME) - dari webhook
- read_at (DATETIME) - dari webhook
```

#### 3. **wa_messages_in** (NEW)
Struktur untuk inbound messages:
```sql
- id (BIGINT AUTO_INCREMENT)
- conversation_id (BIGINT FK)
- customer_id (BIGINT FK)
- phone (VARCHAR 20)
- wamid (VARCHAR 255)
- message_id (VARCHAR 100)
- type (ENUM: text, image, document, audio, video, voice, location, contacts, sticker)
- text (TEXT)
- media_id (VARCHAR 100)
- media_url (TEXT)
- media_mime_type (VARCHAR 100)
- media_caption (TEXT)
- contact_name (VARCHAR 255)
- status (VARCHAR 50) - default: 'received'
- received_at (DATETIME)
```

#### 4. **wa_conversations** (MODIFIED)
Changed fields:
```sql
REMOVED:
- last_message (TEXT)
- last_message_at (DATETIME)

ADDED:
- last_in_at (DATETIME) - Last inbound message time
- last_out_at (DATETIME) - Last outbound message time
```

## 🔄 Code Changes

### 1. Webhook Controller (`api/app/Controllers/Webhook/WhatsApp.php`)

**handleInboundMessage():**
- ✅ Insert to `wa_messages_in` (bukan `wa_messages`)
- ✅ Field changes: `direction` removed, `type` instead of `message_type`
- ✅ Update `wa_conversations.last_in_at`

**handleMessageUpdated():**
- ✅ Update `wa_messages_out` (bukan `wa_messages`)
- ✅ Update status: accepted, sent, delivered, read
- ✅ Update timestamps: sent_at, delivered_at, read_at

**handleStatusUpdate():**
- Keep as is (different event type)

### 2. WhatsAppService (`api/app/Helpers/WhatsAppService.php`)

**saveOutboundMessage():**
- ✅ Insert to `wa_messages_out`
- ✅ Field mapping:
  - `content` = text body atau template name
  - `template_params` = JSON params
  - `type` instead of `message_type`
- ✅ Initial status: `'accepted'`
- ✅ Update `wa_conversations.last_out_at`

## 📤 Migration Steps

### On SERVER:

1. **Backup data** (IMPORTANT!):
```bash
mysqldump -u mdl_main -p mdl_main wa_messages > wa_messages_backup.sql
```

2. **Run migration**:
```bash
mysql -u mdl_main -p mdl_main < api/database/migrations/restructure_wa_tables.sql
```

3. **Verify**:
```sql
SHOW TABLES LIKE 'wa_messages%';
-- Should show: wa_messages_in, wa_messages_out

SHOW COLUMNS FROM wa_messages_out;
SHOW COLUMNS FROM wa_messages_in;
SHOW COLUMNS FROM wa_conversations;
```

4. **Pull latest code**:
```bash
git pull origin main
```

5. **Test**:
   - Kirim pesan WA → cek `wa_messages_out`
   - Customer reply → cek `wa_messages_in`
   - Customer read → cek `wa_messages_out` status updated

## 🧪 Testing Queries

### Check outbound messages:
```sql
SELECT id, phone, type, content, status, created_at, sent_at, delivered_at, read_at
FROM wa_messages_out
ORDER BY id DESC
LIMIT 10;
```

### Check inbound messages:
```sql
SELECT id, phone, type, text, contact_name, received_at
FROM wa_messages_in
ORDER BY id DESC
LIMIT 10;
```

### Check conversations:
```sql
SELECT id, wa_number, contact_name, last_in_at, last_out_at, status
FROM wa_conversations
ORDER BY last_in_at DESC
LIMIT 10;
```

### Full conversation view:
```sql
SELECT 
    'OUT' as direction,
    id,
    phone,
    type,
    content as message,
    status,
    created_at as time
FROM wa_messages_out
WHERE conversation_id = 123

UNION ALL

SELECT 
    'IN' as direction,
    id,
    phone,
    type,
    text as message,
    status,
    received_at as time
FROM wa_messages_in
WHERE conversation_id = 123

ORDER BY time DESC;
```

## ⚡ Benefits

1. **Cleaner Structure**: Outbound dan inbound terpisah
2. **Easier Queries**: Tidak perlu filter `direction`
3. **Different Fields**: Setiap tabel punya field sesuai kebutuhan
4. **Better Performance**: Index lebih optimal
5. **Simpler Code**: Logika lebih jelas

## ⚠️ Important Notes

1. **Data Loss**: Migration akan DROP `wa_messages` - pastikan backup!
2. **Downtime**: Saat migration, WhatsApp service akan error sementara
3. **Foreign Keys**: Akan di-drop dan di-create ulang
4. **Testing**: Test di development dulu sebelum ke production

---

Created: 2025-12-20
Status: ✅ READY TO DEPLOY
