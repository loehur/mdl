# WhatsApp Outbound Not Saving - Troubleshooting

## ⚠️ Messages Not Being Saved to wa_messages_out

### Quick Diagnosis (Di SERVER):

```bash
# 1. Upload debug script dan jalankan
php api/debug_why_not_saved.php
```

Akan check:
- ✅ Apakah tabel wa_messages_out exist?
- ✅ Apakah code sudah versi terbaru?
- ✅ Apakah ada error di log?
- ✅ Apakah DB class bisa load?

---

## 🔧 Common Issues & Solutions

### Issue #1: Tabel Belum Dibuat
**Symptom:** `wa_messages_out` tidak ada di database

**Solution:**
```bash
# Option A: Fresh install semua tabel
mysql -u mdl_main -p mdl_main < api/database/wa_tables_complete.sql

# Option B: Via PHP
php api/database/install_wa_tables.php

# Verify
mysql -u mdl_main -p -e "SHOW TABLES LIKE 'wa_messages%'" mdl_main
```

---

### Issue #2: Code Belum Di-pull
**Symptom:** Masih pakai validasi lama (`if (!$wamid)`)

**Solution:**
```bash
cd /path/to/your/app
git pull origin main

# Verify
grep -A 5 "message_id.*Provider ID" api/app/Helpers/WhatsAppService.php
# Should show new code with message_id validation
```

---

### Issue #3: try-catch Menelan Error
**Symptom:** Tidak ada error message tapi tidak save

**Solution - Check PHP Error Log:**
```bash
# Find error log location
php -i | grep error_log

# Or common locations:
tail -f /var/log/php-error.log
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log

# Search for WhatsApp errors
grep -i "whatsapp\|saveoutbound" /var/log/php-error.log
```

---

### Issue #4: Database Connection
**Symptom:** DB class tidak load atau connection failed

**Check:**
```php
// In WhatsAppService.php around line 350
$dbPath = __DIR__ . '/../Core/DB.php';

// Verify file exists:
ls -la api/app/Core/DB.php
```

**Solution:** Pastikan path benar dan DB.php exist

---

### Issue #5: Permissions
**Symptom:** Cannot create/insert to table

**Check:**
```sql
SHOW GRANTS FOR 'mdl_main'@'localhost';
```

**Solution:** User harus punya INSERT privilege

---

## 🧪 Manual Test

### 1. Check Current State
```sql
-- Check if table exists and structure
DESC wa_messages_out;

-- Check current records
SELECT * FROM wa_messages_out ORDER BY id DESC LIMIT 5;
```

### 2. Manual Insert Test
```sql
-- Test manual insert
INSERT INTO wa_messages_out 
(conversation_id, phone, message_id, type, content, status, created_at)
VALUES 
(1, '+6281268098300', 'test123', 'text', 'Test manual insert', 'pending', NOW());

-- Check
SELECT * FROM wa_messages_out WHERE message_id = 'test123';
```

Jika manual insert GAGAL → masalah di database/table
Jika manual insert OK → masalah di PHP code

---

## 📊 Expected vs Actual

### Expected Behavior:
```
1. Send WA via API ✅
2. saveOutboundMessage() called ✅
3. Validation passes (has message_id) ✅
4. Insert to wa_messages_out ✅
5. Record visible in database ✅
```

### Current (Broken):
```
1. Send WA via API ✅
2. saveOutboundMessage() called ✅
3. Validation ??? 
4. Insert ??? 
5. Record NOT in database ❌
```

---

## 🔍 Debug Steps

### Step 1: Add Temporary Logging
Edit `api/app/Helpers/WhatsAppService.php`:

```php
private function saveOutboundMessage($payload, $response)
{
    // ADD THIS at the very start
    error_log("=== saveOutboundMessage START ===");
    error_log("Phone: " . ($payload['to'] ?? 'NULL'));
    error_log("Message ID: " . ($response['id'] ?? 'NULL'));
    
    try {
        // ... rest of code
        
        // ADD THIS before insert
        error_log("About to insert...");
        $msgId = $db->insert('wa_messages_out', $messageData);
        error_log("Insert result: " . ($msgId ? "ID=$msgId" : "FAILED"));
        
    } catch (\Throwable $e) {
        // ADD THIS
        error_log("EXCEPTION: " . $e->getMessage());
        error_log("TRACE: " . $e->getTraceAsString());
    }
}
```

### Step 2: Send Test Message

### Step 3: Check Logs
```bash
tail -f /var/log/php-error.log | grep "saveOutbound"
```

---

## 📞 Quick Actions di Server

```bash
# 1. Pull latest code
git pull origin main

# 2. Check if tables exist
mysql -u mdl_main -p -e "SHOW TABLES LIKE 'wa_%'" mdl_main

# 3. If no tables, run install
mysql -u mdl_main -p mdl_main < api/database/wa_tables_complete.sql

# 4. Verify
php api/debug_why_not_saved.php

# 5. Test send message
# (via your app)

# 6. Check database
mysql -u mdl_main -p -e "SELECT * FROM wa_messages_out ORDER BY id DESC LIMIT 5" mdl_main
```

---

## ✅ When Fixed, You Should See:

```sql
SELECT id, phone, message_id, wamid, status, content, created_at 
FROM wa_messages_out 
ORDER BY id DESC LIMIT 1;

-- Expected output:
-- id: 1
-- phone: +6281268098300
-- message_id: 694595423fbb994559471770
-- wamid: NULL (initially, will be updated by webhook)
-- status: accepted
-- content: 4837 (Gunawan) - LAUNDRY
-- created_at: 2025-12-20 01:11:14
```

---

**Most Common Cause:** Tabel `wa_messages_out` belum dibuat di server!

**Quick Fix:**
```bash
mysql -u mdl_main -p mdl_main < api/database/wa_tables_complete.sql
```
