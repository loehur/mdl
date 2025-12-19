# WhatsApp Tables Structure - Quick Reference

## 📊 Table Overview

```
wa_webhooks          → Raw webhook logs
wa_customers         → Customer data & 24h window tracking  
wa_conversations     → Conversation threads
wa_messages_in       → Incoming messages (customer → us)
wa_messages_out      → Outgoing messages (us → customer)
```

## 📋 Table Structures

### 1. wa_webhooks
```sql
id                  BIGINT AUTO_INCREMENT PRIMARY KEY
provider            VARCHAR(20) = 'ycloud'
event_type          VARCHAR(50)
payload             LONGTEXT (JSON)
received_at         DATETIME
```

**Purpose:** Log semua webhook events dari YCloud

---

### 2. wa_customers
```sql
id                  BIGINT AUTO_INCREMENT PRIMARY KEY
wa_number           VARCHAR(20) UNIQUE
contact_name        VARCHAR(255)
last_message_at     DATETIME (untuk 24h window)
first_contact_at    DATETIME
total_messages      INT
is_active           TINYINT(1)
notes               TEXT
created_at          DATETIME
updated_at          DATETIME
```

**Purpose:** Track customer dan 24-hour customer service window

**24h Window Logic:**
- `last_message_at` = waktu terakhir customer kirim pesan
- Jika < 24 jam → bisa kirim free text
- Jika > 24 jam → harus pakai template

---

### 3. wa_conversations
```sql
id                  BIGINT AUTO_INCREMENT PRIMARY KEY
customer_id         BIGINT FK → wa_customers
wa_number           VARCHAR(20) UNIQUE
contact_name        VARCHAR(255)
status              ENUM('open','closed')
last_in_at          DATETIME (last inbound)
last_out_at         DATETIME (last outbound)
assigned_user_id    BIGINT (optional CS assignment)
created_at          DATETIME
updated_at          DATETIME
```

**Purpose:** Conversation thread dengan customer

**Usage:**
- `last_in_at` → untuk sorting conversations by activity
- `last_out_at` → untuk tracking response time
- Satu customer = satu conversation (UNIQUE wa_number)

---

### 4. wa_messages_in (Inbound)
```sql
id                  BIGINT AUTO_INCREMENT PRIMARY KEY
conversation_id     BIGINT FK → wa_conversations
customer_id         BIGINT FK → wa_customers
phone               VARCHAR(20)
wamid               VARCHAR(255) (WhatsApp Message ID)
message_id          VARCHAR(100) (Provider ID)
type                ENUM (text, image, document, audio, video, voice, location, contacts, sticker)
text                TEXT
media_id            VARCHAR(100)
media_url           TEXT
media_mime_type     VARCHAR(100)
media_caption       TEXT
contact_name        VARCHAR(255)
status              VARCHAR(50) = 'received'
received_at         DATETIME
```

**Purpose:** Pesan yang masuk dari customer

**Type Values:**
- `text` → Text message
- `image` → Foto
- `document` → PDF, doc, dll
- `audio` → Audio file
- `video` → Video
- `voice` → Voice note
- `location` → Location share
- `contacts` → Contact card
- `sticker` → Sticker

---

### 5. wa_messages_out (Outbound)
```sql
id                  BIGINT AUTO_INCREMENT PRIMARY KEY
conversation_id     BIGINT FK → wa_conversations
phone               VARCHAR(20)
wamid               VARCHAR(255) (WhatsApp Message ID)
message_id          VARCHAR(100) (Provider ID)
type                ENUM (text, template, image, document, video, audio)
content             TEXT (text message / template name)
template_params     TEXT (JSON)
media_url           TEXT
status              ENUM (pending, accepted, sent, delivered, read, failed)
error_message       TEXT
created_at          DATETIME (when we send)
sent_at             DATETIME (from webhook)
delivered_at        DATETIME (from webhook)
read_at             DATETIME (from webhook)
```

**Purpose:** Pesan yang kita kirim ke customer

**Type Values:**
- `text` → Free-form text (within 24h window)
- `template` → Template message (anytime)
- `image`, `document`, `video`, `audio` → Media

**Status Flow:**
```
pending → accepted → sent → delivered → read
                    ↓
                  failed
```

**Timestamps:**
- `created_at` → When we initiated send
- `sent_at` → When WhatsApp accepted (webhook update)
- `delivered_at` → When delivered to customer device (webhook)
- `read_at` → When customer opened message (webhook)

---

## 🔄 Data Flow

### Outbound Message (We send):
1. **Send via API** → WhatsAppService.php
2. **Insert** → wa_messages_out (status: 'accepted')
3. **Update** → wa_conversations.last_out_at
4. **Webhook** → whatsapp.message.updated
5. **Update** → wa_messages_out status + timestamps

### Inbound Message (Customer sends):
1. **Webhook** → whatsapp.inbound_message.received
2. **Insert** → wa_messages_in
3. **Update** → wa_customers.last_message_at (for 24h window)
4. **Update** → wa_conversations.last_in_at

---

## 🔍 Common Queries

### Get conversation timeline:
```sql
SELECT 'IN' as dir, text as msg, received_at as time
FROM wa_messages_in WHERE conversation_id = 1
UNION ALL
SELECT 'OUT', content, created_at
FROM wa_messages_out WHERE conversation_id = 1
ORDER BY time DESC;
```

### Check 24h window status:
```sql
SELECT 
    wa_number,
    contact_name,
    last_message_at,
    TIMESTAMPDIFF(HOUR, last_message_at, NOW()) as hours_ago,
    CASE 
        WHEN TIMESTAMPDIFF(HOUR, last_message_at, NOW()) < 24 THEN 'OPEN'
        ELSE 'CLOSED'
    END as window_status
FROM wa_customers
WHERE is_active = 1;
```

### Message delivery stats:
```sql
SELECT 
    phone,
    status,
    COUNT(*) as total,
    AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as avg_delivery_sec
FROM wa_messages_out
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY phone, status;
```

### Active conversations:
```sql
SELECT 
    c.wa_number,
    c.contact_name,
    c.last_in_at,
    c.last_out_at,
    COUNT(DISTINCT mi.id) as total_in,
    COUNT(DISTINCT mo.id) as total_out
FROM wa_conversations c
LEFT JOIN wa_messages_in mi ON mi.conversation_id = c.id
LEFT JOIN wa_messages_out mo ON mo.conversation_id = c.id
WHERE c.status = 'open'
GROUP BY c.id
ORDER BY GREATEST(COALESCE(c.last_in_at, '2000-01-01'), 
                  COALESCE(c.last_out_at, '2000-01-01')) DESC;
```

---

## 📝 Notes

1. **wamid** = WhatsApp Message ID, unique identifier dari WhatsApp
2. **message_id** = Provider (YCloud) message ID
3. **Foreign Keys** = CASCADE delete (delete customer → delete conversations → delete messages)
4. **Indexes** = Pada phone, wamid, status, timestamps untuk performance
5. **ENUM** = Untuk field dengan nilai terbatas (lebih efisien)

---

Created: 2025-12-20
