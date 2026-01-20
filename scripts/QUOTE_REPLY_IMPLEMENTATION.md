# Quote/Reply Message Implementation - Complete Guide

## 📋 Overview
Implementasi lengkap untuk menampilkan quote/reply WhatsApp di CRM, baik untuk pesan inbound maupun outbound.

---

## ✅ Backend Changes Completed

### 1. **Inbound Messages (Webhook)**
File: `api/app/Controllers/Webhook/WhatsApp.php`

**Fitur yang ditambahkan:**
- ✅ Extract `quoted_message_id` dari webhook context
- ✅ Extract `quoted_message_from` (sender dari quoted message)
- ✅ Fetch `quoted_message_body` dari database (wa_messages_in atau wa_messages_out)
- ✅ Simpan semua data quote ke table `wa_messages_in`
- ✅ Push data quote ke WebSocket untuk real-time update

**Data yang disimpan:**
```php
[
    'quoted_message_id' => 'wamid.xxx',      // WAMID dari message yang di-quote
    'quoted_message_body' => 'Text asli',   // Konten message yang di-quote
]
```

**WebSocket Payload:**
```javascript
{
    type: 'incoming',
    message: {
        id: 123,
        text: 'Balasan user',
        quoted_message_id: 'wamid.xxx',
        quoted_message_body: 'Text yang di-quote',
        quoted_message_from: '+628123456789'
    }
}
```

---

### 2. **Outbound Messages (WhatsAppService)**
File: `api/app/Helpers/WhatsAppService.php`

**Fitur yang ditambahkan:**
- ✅ Support parameter `$replyToMessageId` di `sendFreeText()`
- ✅ Add `context` payload ke WhatsApp API request
- ✅ Fetch `quoted_message_body` dari database
- ✅ Simpan data quote ke table `wa_messages_out`
- ✅ Push data quote ke WebSocket

**Usage di Controller:**
```php
// Send message with quote/reply
$whatsappService->sendFreeText(
    $phone, 
    $message, 
    $replyToMessageId  // ← Pass WAMID dari message yang mau di-quote
);
```

**WhatsApp API Payload:**
```json
{
    "from": "+628xxx",
    "to": "+628yyy",
    "type": "text",
    "text": {
        "body": "Balasan dari agent"
    },
    "context": {
        "message_id": "wamid.xxx"  // ← Quote reference
    }
}
```

---

## 🗄️ Database Changes Required

### **Langkah 1: Jalankan SQL Script**
```bash
mysql -u root -p mdl_db < scripts/add_quoted_message_columns.sql
```

Atau copy-paste manual SQL berikut:

```sql
-- Add to wa_messages_in
ALTER TABLE `wa_messages_in` 
ADD COLUMN IF NOT EXISTS `quoted_message_id` VARCHAR(255) NULL COMMENT 'WAMID of quoted/replied message' AFTER `wamid`,
ADD COLUMN IF NOT EXISTS `quoted_message_body` TEXT NULL COMMENT 'Text content of quoted message' AFTER `quoted_message_id`;

ALTER TABLE `wa_messages_in`
ADD INDEX IF NOT EXISTS `idx_quoted_message_id` (`quoted_message_id`);

-- Add to wa_messages_out
ALTER TABLE `wa_messages_out` 
ADD COLUMN IF NOT EXISTS `quoted_message_id` VARCHAR(255) NULL COMMENT 'WAMID of quoted/replied message' AFTER `wamid`,
ADD COLUMN IF NOT EXISTS `quoted_message_body` TEXT NULL COMMENT 'Text content of quoted message' AFTER `quoted_message_id`;

ALTER TABLE `wa_messages_out`
ADD INDEX IF NOT EXISTS `idx_quoted_message_id` (`quoted_message_id`);
```

---

## 🎨 Frontend Changes Needed

### **File yang perlu diupdate:**
`frontend/crm/src/components/ChatWindow.vue` (atau file message display)

### **1. Display Quote di Message Bubble**

Tambahkan quote display di template:

```vue
<template>
  <div class="message">
    <!-- Quote/Reply Preview (if exists) -->
    <div v-if="message.quoted_message_body" class="quote-preview">
      <div class="quote-line"></div>
      <div class="quote-content">
        <div class="quote-text">{{ message.quoted_message_body }}</div>
      </div>
    </div>
    
    <!-- Main Message -->
    <div class="message-text">
      {{ message.text }}
    </div>
  </div>
</template>
```

### **2. CSS Styling untuk Quote**

```css
.quote-preview {
  display: flex;
  gap: 8px;
  padding: 8px;
  margin-bottom: 8px;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 8px;
  font-size: 0.9em;
}

.quote-line {
  width: 3px;
  background: #25D366; /* WhatsApp green */
  border-radius: 2px;
  flex-shrink: 0;
}

.quote-content {
  flex: 1;
  overflow: hidden;
}

.quote-text {
  color: #666;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
```

### **3. Send Message dengan Quote**

Update fungsi send message untuk include quoted_message_id:

```javascript
// In Chat component
const replyToMessage = ref(null); // Message yang sedang di-reply

function setReplyTo(message) {
  replyToMessage.value = message;
}

async function sendMessage() {
  const payload = {
    phone: currentConversation.value.wa_number,
    message: messageText.value,
    quoted_message_id: replyToMessage.value?.wamid || null  // ← Add this
  };
  
  await chatStore.sendMessage(payload);
  
  // Clear reply state
  replyToMessage.value = null;
  messageText.value = '';
}
```

### **4. Update Chat API Call**

File: `frontend/crm/src/api/chat.js` (atau store)

```javascript
export async function sendChatMessage(data) {
  return await axios.post('/api/CRM/Chat/send', {
    phone: data.phone,
    message: data.message,
    quoted_message_id: data.quoted_message_id  // ← Pass ke backend
  });
}
```

### **5. Backend Controller Update (if needed)**

File: `api/app/Controllers/CRM/Chat.php`

```php
public function send() {
    $phone = $_POST['phone'] ?? null;
    $message = $_POST['message'] ?? null;
    $quotedMessageId = $_POST['quoted_message_id'] ?? null;  // ← Get from request
    
    $result = $this->whatsappService->sendFreeText(
        $phone, 
        $message, 
        $quotedMessageId  // ← Pass to service
    );
    
    // ... return response
}
```

---

## 🔍 Testing Checklist

### **Test Inbound Quote:**
1. ✅ User reply to message di WhatsApp
2. ✅ Check database: `quoted_message_id` dan `quoted_message_body` tersimpan
3. ✅ Check CRM: Quote preview muncul di chat bubble
4. ✅ Real-time: Quote langsung muncul tanpa refresh

### **Test Outbound Quote:**
1. ✅ Agent click "Reply" di message
2. ✅ Quote preview muncul di input area
3. ✅ Send message
4. ✅ Check database: `quoted_message_id` tersimpan
5. ✅ Check WhatsApp user: Message muncul dengan quote reference
6. ✅ Check CRM: Outbound message show quote preview

---

## 📊 Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    INBOUND MESSAGE                          │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
    ┌───────────────────────────────────────┐
    │   WhatsApp Webhook                    │
    │   - Extract context.id                │
    │   - Fetch quoted message from DB      │
    └───────────────────────────────────────┘
                            │
                            ▼
    ┌───────────────────────────────────────┐
    │   Save to wa_messages_in              │
    │   - quoted_message_id                 │
    │   - quoted_message_body               │
    └───────────────────────────────────────┘
                            │
                            ▼
    ┌───────────────────────────────────────┐
    │   Push to WebSocket                   │
    │   - Real-time update to CRM           │
    └───────────────────────────────────────┘
                            │
                            ▼
    ┌───────────────────────────────────────┐
    │   CRM Frontend Display                │
    │   - Show quote preview bubble         │
    └───────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                   OUTBOUND MESSAGE                          │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
    ┌───────────────────────────────────────┐
    │   Agent Reply to Message              │
    │   - Click reply button                │
    │   - Send with quoted_message_id       │
    └───────────────────────────────────────┘
                            │
                            ▼
    ┌───────────────────────────────────────┐
    │   WhatsAppService                     │
    │   - Add context to API payload        │
    │   - Fetch quoted message body         │
    └───────────────────────────────────────┘
                            │
                            ▼
    ┌───────────────────────────────────────┐
    │   Save to wa_messages_out             │
    │   - quoted_message_id                 │
    │   - quoted_message_body               │
    └───────────────────────────────────────┘
                            │
                            ▼
    ┌───────────────────────────────────────┐
    │   Send to WhatsApp API                │
    │   - User sees quoted message          │
    └───────────────────────────────────────┘
```

---

## 🎯 Summary

**Backend:** ✅ COMPLETE
- Webhook extract quote ✅
- Database save quote ✅
- WebSocket push quote ✅
- Outbound support quote ✅

**Database:** ⚠️ REQUIRES ACTION
- Run SQL script untuk add columns

**Frontend:** ⚠️ REQUIRES IMPLEMENTATION
- Display quote preview di message bubble
- Add "Reply" button functionality
- Pass `quoted_message_id` ketika send message

---

## 📞 Support

Jika ada issue atau pertanyaan:
1. Check log file: `api/logs/`
2. Check WebSocket payload di browser console
3. Check database: `SELECT * FROM wa_messages_in WHERE quoted_message_id IS NOT NULL`

**Happy Coding!** 🎉
