# Debugging WhatsApp Webhook Issues

## Updated Files:
- `WhatsApp.php` - Added comprehensive error logging

## What Changed:
Added detailed logging to track:
1. Customer creation/update attempts
2. Message insertion attempts  
3. All database errors with specific messages
4. Data being attempted when errors occur

## How to Debug:

1. **Push kode terbaru ke production**
2. **Kirim pesan test dari WhatsApp**
3. **Cek log di server production:**
   ```bash
   tail -f /path/to/api/logs/webhook/whatsapp/YYYY/MM/DD.log
   ```

4. **Look for these messages:**
   - ✓ Customer updated/created
   - ✓ Message saved
   - ✗ DB ERROR (akan tampilkan detail error)

5. **Common Issues to Check:**
   - Apakah tabel sudah ada di production? Run `migrate_wa_tables.php`
   - Apakah foreign key constraint error? Cek customer_id exists
   - Apakah column mismatch? Compare table structure

## Test Script:
Run this to test local flow:
```bash
php api/scripts/test_webhook_flow.php
```

## Check Data:
```bash
php api/scripts/check_wa_status.php
```
