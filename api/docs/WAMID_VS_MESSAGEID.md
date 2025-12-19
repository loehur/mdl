# WhatsApp Integration - wamid vs message_id

## 🔍 Problem Found

Saat kirim pesan WhatsApp via YCloud API, response **TIDAK** mengandung `wamid` (WhatsApp Message ID).

### Initial API Response:
```json
{
  "id": "69459416d74992655fea3c9e",  ← Provider message_id (ada)
  "status": "accepted",
  "from": "+6281170706611",
  "to": "+6281268098300",
  // NO wamid field!
}
```

### Webhook Event (whatsapp.message.updated):
```json
{
  "whatsappMessage": {
    "id": "69459416d74992655fea3c9e",        ← Provider message_id
    "wamid": "wamid.HBgNNjI4MTI2ODA5...",   ← WhatsApp wamid (BARU ADA DI SINI!)
    "status": "sent",
    "sendTime": "2025-12-19T18:06:14.279Z"
  }
}
```

**Kesimpulan:**
- `message_id` = Provider (YCloud) ID, tersedia **saat kirim**
- `wamid` = WhatsApp Message ID, tersedia **dari webhook**

---

## ✅ Solution Applied

### 1. WhatsAppService.php - saveOutboundMessage()

**Before:**
```php
$wamid = $response['wamid'] ?? null;
if (!$waNumber || !$wamid) {
    return; // GAGAL! wamid NULL
}
```

**After:**
```php
$messageId = $response['id'] ?? null; // Provider ID (always available)
$wamid = $response['wamid'] ?? null; // May be NULL initially

// Essential: must have phone and message_id
if (!$waNumber || !$messageId) {
    return; // Use message_id as primary identifier
}
```

**Changes:**
- ✅ Gunakan `message_id` sebagai primary requirement
- ✅ `wamid` boleh NULL saat insert
- ✅ `wamid` akan di-update dari webhook nanti

---

### 2. Webhook WhatsApp.php - handleMessageUpdated()

**Before:**
```php
if (!$wamid) {
    return; // GAGAL jika wamid masih NULL di database
}

$db->update('wa_messages_out', $updateData, ['wamid' => $wamid]);
```

**After:**
```php
$wamid = $message['wamid'] ?? null;
$messageId = $message['id'] ?? null;

if (!$wamid && !$messageId) {
    return; // At least one must exist
}

// Add wamid to update data (set it for first time)
if ($wamid) {
    $updateData['wamid'] = $wamid;
}

// Try update by wamid first
$updated = false;
if ($wamid) {
    $updated = $db->update('wa_messages_out', $updateData, ['wamid' => $wamid]);
}

// If not found, try by message_id (for first webhook event)
if (!$updated && $messageId) {
    $updated = $db->update('wa_messages_out', $updateData, ['message_id' => $messageId]);
}
```

**Changes:**
- ✅ Coba match by `wamid` dulu (jika sudah ada)
- ✅ Jika tidak ketemu, match by `message_id`
- ✅ Set `wamid` dari webhook (pertama kali)

---

## 🔄 Message Flow

### Initial Send:
1. **Send API Request**
   ```
   POST /whatsapp/messages
   ```

2. **Receive Response**
   ```json
   {
     "id": "abc123",  ← message_id
     "status": "accepted"
   }
   ```

3. **Insert to Database**
   ```sql
   INSERT INTO wa_messages_out
   (message_id, wamid, status, ...)
   VALUES
   ('abc123', NULL, 'accepted', ...)
   --         ^^^^^ NULL saat ini
   ```

### First Webhook Event:
1. **Receive Webhook**
   ```json
   {
     "type": "whatsapp.message.updated",
     "whatsappMessage": {
       "id": "abc123",        ← match ini
       "wamid": "wamid.XYZ",  ← dapat wamid
       "status": "sent"
     }
   }
   ```

2. **Update Database**
   ```sql
   UPDATE wa_messages_out
   SET wamid = 'wamid.XYZ',  ← SET wamid pertama kali
       status = 'sent',
       sent_at = '2025-12-20 01:06:14'
   WHERE message_id = 'abc123'
   --    ^^^^^^^^^^^ match by message_id
   ```

### Subsequent Webhook Events:
1. **Receive Webhook**
   ```json
   {
     "whatsappMessage": {
       "wamid": "wamid.XYZ",
       "status": "delivered"
     }
   }
   ```

2. **Update Database**
   ```sql
   UPDATE wa_messages_out
   SET status = 'delivered',
       delivered_at = '2025-12-20 01:06:15'
   WHERE wamid = 'wamid.XYZ'
   --    ^^^^^ sekarang match by wamid
   ```

---

## 📊 Database State Evolution

| Event | message_id | wamid | status | sent_at | delivered_at | read_at |
|-------|-----------|-------|--------|---------|--------------|---------|
| **Initial Insert** | abc123 | NULL | accepted | NULL | NULL | NULL |
| **Webhook #1 (sent)** | abc123 | wamid.XYZ | sent | 01:06:14 | NULL | NULL |
| **Webhook #2 (delivered)** | abc123 | wamid.XYZ | delivered | 01:06:14 | 01:06:15 | NULL |
| **Webhook #3 (read)** | abc123 | wamid.XYZ | read | 01:06:14 | 01:06:15 | 01:06:20 |

---

## 🧪 Testing

### Test Outbound Message Save:
```bash
# 1. Kirim pesan via API/app
# 2. Check database:
SELECT id, message_id, wamid, status, created_at 
FROM wa_messages_out 
ORDER BY id DESC LIMIT 1;

# Expected:
# - message_id: filled
# - wamid: NULL (initially)
# - status: 'accepted'
```

### Test Webhook Update:
```bash
# 1. Wait for webhook event
# 2. Check log for: "✓ Outbound message updated"
# 3. Check database:
SELECT id, message_id, wamid, status, sent_at 
FROM wa_messages_out 
ORDER BY id DESC LIMIT 1;

# Expected:
# - message_id: same
# - wamid: NOW FILLED!
# - status: 'sent' or 'delivered'
# - sent_at: filled
```

---

## ⚠️ Important Notes

1. **message_id** = Provider (YCloud) unique ID
   - Available immediately on API response
   - Used as primary identifier for matching

2. **wamid** = WhatsApp Message ID  
   - Only available from webhook events
   - NULL on initial insert
   - Set by first webhook event

3. **Matching Logic**:
   - First webhook: match by `message_id` (wamid NULL in DB)
   - Subsequent webhooks: match by `wamid` (preferred)
   - Fallback: if no match by wamid, try message_id

4. **Both fields indexed** for fast lookup

---

Updated: 2025-12-20
Status: ✅ FIXED
