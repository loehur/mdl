# WhatsApp Outbound Messages - Error Logging

## 📝 Log File Location

Log khusus untuk debug outbound messages:
```
api/logs/wa_outbound_errors.log
```

Log ini akan otomatis dicreate saat ada attempt untuk save outbound message.

---

## 🔍 Cara Check Log

### Di Server:
```bash
# View log
cat api/logs/wa_outbound_errors.log

# Tail log (real-time)
tail -f api/logs/wa_outbound_errors.log

# Last 50 lines
tail -n 50 api/logs/wa_outbound_errors.log

# Search for errors
grep "ERROR" api/logs/wa_outbound_errors.log

# Search for success
grep "SUCCESS" api/logs/wa_outbound_errors.log
```

### Via Browser/phpMyAdmin:
Upload file ini ke server dan akses via browser:
```
https://nalju.com/api/view_log.php
```

---

## 📊 Log Format

Setiap attempt untuk save message akan log:

### Success Flow:
```
[2025-12-20 01:15:00] === SAVE OUTBOUND MESSAGE START ===
[2025-12-20 01:15:00] Data: phone=+6281268098300, msg_id=abc123, type=text
[2025-12-20 01:15:00] ✓ Validation passed
[2025-12-20 01:15:00] ✓ DB class loaded
[2025-12-20 01:15:00] ✓ DB connected
[2025-12-20 01:15:00] ✓ Customer found: ID=1
[2025-12-20 01:15:00] ✓ Conversation found: ID=1
[2025-12-20 01:15:00] Content extracted: Test message
[2025-12-20 01:15:00] Inserting to wa_messages_out...
[2025-12-20 01:15:00] ✓✓✓ SUCCESS! Message saved: ID=123
[2025-12-20 01:15:00] ✓ Conversation updated
[2025-12-20 01:15:00] === END ===
```

### Error Examples:

#### Validation Error:
```
[2025-12-20 01:15:00] === SAVE OUTBOUND MESSAGE START ===
[2025-12-20 01:15:00] Data: phone=NULL, msg_id=abc123, type=text
[2025-12-20 01:15:00] ERROR: Validation failed - missing phone or message_id
```

#### DB Load Error:
```
[2025-12-20 01:15:00] === SAVE OUTBOUND MESSAGE START ===
[2025-12-20 01:15:00] Data: phone=+6281268098300, msg_id=abc123, type=text
[2025-12-20 01:15:00] ✓ Validation passed
[2025-12-20 01:15:00] Loading DB from: /path/to/DB.php
[2025-12-20 01:15:00] ERROR: DB.php not found at /path/to/DB.php
```

#### Insert Error:
```
[2025-12-20 01:15:00] === SAVE OUTBOUND MESSAGE START ===
[2025-12-20 01:15:00] Data: phone=+6281268098300, msg_id=abc123, type=text
[2025-12-20 01:15:00] ✓ Validation passed
[2025-12-20 01:15:00] ✓ DB class loaded
[2025-12-20 01:15:00] ✓ DB connected
[2025-12-20 01:15:00] ✓ Customer found: ID=1
[2025-12-20 01:15:00] ✓ Conversation found: ID=1
[2025-12-20 01:15:00] Content extracted: Test message
[2025-12-20 01:15:00] Inserting to wa_messages_out...
[2025-12-20 01:15:00] ERROR: Message insert FAILED!
[2025-12-20 01:15:00] DB Error: Table 'mdl_main.wa_messages_out' doesn't exist
[2025-12-20 01:15:00] Data: {"conversation_id":1,"phone":"+628...","message_id":"abc123",...}
```

#### Exception:
```
[2025-12-20 01:15:00] === SAVE OUTBOUND MESSAGE START ===
[2025-12-20 01:15:00] EXCEPTION: Call to undefined method
[2025-12-20 01:15:00] File: /path/to/WhatsAppService.php Line: 365
[2025-12-20 01:15:00] Trace: #0 /path/...
[2025-12-20 01:15:00] === END (with exception) ===
```

---

## 🛠️ Troubleshooting Based on Log

### Log Says: "Validation failed"
**Problem:** Missing phone or message_id in API response

**Check:**
- YCloud API response format
- Is `response['id']` present?

---

### Log Says: "DB.php not found"
**Problem:** Path to DB class incorrect

**Fix:**
```bash
# Check actual path
find . -name "DB.php"

# Should be: api/app/Core/DB.php
```

---

### Log Says: "Customer insert failed"
**Problem:** Database issue with wa_customers table

**Check:**
```sql
DESC wa_customers;
SHOW CREATE TABLE wa_customers;
```

**Common causes:**
- Table doesn't exist
- Missing required fields
- Foreign key constraint

---

### Log Says: "Table 'wa_messages_out' doesn't exist"
**Problem:** Table not created

**Fix:**
```bash
mysql -u mdl_main -p mdl_main < api/database/wa_tables_complete.sql
```

---

### Log Says: Message insert FAILED with DB Error
**Problem:** SQL error

**Check full error message in log**

Common errors:
- `Column 'xxx' doesn't exist` → Run migration to add column
- `Foreign key constraint fails` → Parent record missing
- `Duplicate entry` → Unique constraint violation

---

## 📦 Helper: View Log via Web

Create `api/view_log.php`:

```php
<?php
$logFile = __DIR__ . '/logs/wa_outbound_errors.log';

header('Content-Type: text/plain');

if (file_exists($logFile)) {
    $lines = file($logFile);
    $recent = array_slice($lines, -100); // Last 100 lines
    echo implode('', $recent);
} else {
    echo "Log file not found: $logFile\n";
    echo "No outbound messages attempted yet or logging failed.\n";
}
```

Access: `https://nalju.com/api/view_log.php`

---

## ✅ What Success Looks Like

If everything works correctly, you should see:
1. "=== SAVE OUTBOUND MESSAGE START ==="
2. Multiple "✓" checkmarks
3. "✓✓✓ SUCCESS! Message saved: ID=XXX"
4. "=== END ==="

**No "ERROR" anywhere!**

---

Updated: 2025-12-20
