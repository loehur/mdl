# 🚀 Quick Start Guide - WhatsApp API

Panduan cepat untuk mulai menggunakan WhatsApp API.

## ⚡ 5 Menit Setup

### 1️⃣ Konfigurasi API Key

Edit file `app/Config/WhatsApp.php`:

```php
'api_key' => 'YOUR_YCLOUD_API_KEY_HERE',
'whatsapp_number' => '+6281234567890',
```

### 2️⃣ Test API

Buka browser: `http://localhost/api/whatsapp-tester.html`

### 3️⃣ Kirim Pesan Pertama

**Option A: Via Browser Tester**
1. Isi nomor: `081234567890`
2. Pilih waktu: 2 jam yang lalu
3. Mode: `Free Text`
4. Message: `Hello, test dari API!`
5. Klik **Send**

**Option B: Via cURL**
```bash
curl -X POST http://localhost/api/WhatsApp/send \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "081234567890",
    "last_message_at": "2024-12-19 18:00:00",
    "message_mode": "free",
    "message": "Hello dari API!"
  }'
```

**Option C: Via JavaScript**
```javascript
fetch('http://localhost/api/WhatsApp/send', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    phone: '081234567890',
    last_message_at: '2024-12-19 18:00:00',
    message_mode: 'free',
    message: 'Hello dari API!'
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

---

## 📱 Contoh Use Cases

### Use Case 1: Notifikasi Order Siap
```javascript
// Saat order status berubah jadi "ready"
async function notifyOrderReady(order) {
  const response = await fetch('/api/WhatsApp/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: order.customer_phone,
      last_message_at: order.customer_last_chat,
      message_mode: 'free',
      message: `Halo ${order.customer_name}, pesanan #${order.order_id} sudah siap diambil!`
    })
  });
  
  return await response.json();
}
```

### Use Case 2: Reminder Pembayaran (Template)
```javascript
// Untuk customer yang sudah lama tidak chat (>24 jam)
async function sendPaymentReminder(customer) {
  const response = await fetch('/api/WhatsApp/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: customer.phone,
      last_message_at: customer.last_message_at,
      message_mode: 'template',
      template_name: 'payment_reminder',
      template_language: 'id',
      template_params: [
        customer.name,
        customer.invoice_number,
        customer.amount
      ]
    })
  });
  
  return await response.json();
}
```

### Use Case 3: Kirim Invoice PDF
```javascript
async function sendInvoice(customer, invoicePdfUrl) {
  const response = await fetch('/api/WhatsApp/send-media', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: customer.phone,
      type: 'document',
      media_url: invoicePdfUrl,
      filename: `Invoice-${customer.invoice_number}.pdf`,
      last_message_at: customer.last_message_at
    })
  });
  
  return await response.json();
}
```

---

## 🔍 Cek Status CSW

Sebelum kirim pesan, cek dulu apakah CSW masih aktif:

```javascript
async function checkCSW(lastMessageAt) {
  const response = await fetch('/api/WhatsApp/check-csw', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      last_message_at: lastMessageAt
    })
  });
  
  const result = await response.json();
  
  if (result.data.within_csw) {
    console.log(`CSW masih aktif! Sisa ${result.data.hours_remaining} jam`);
    return true;
  } else {
    console.log('CSW expired, harus pakai template');
    return false;
  }
}
```

---

## 🎯 Smart Pattern (Recommended)

Pattern terbaik untuk handle CSW otomatis:

```javascript
async function sendSmartMessage(customer, message) {
  // Step 1: Check CSW
  const cswCheck = await fetch('/api/WhatsApp/check-csw', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      last_message_at: customer.last_message_at
    })
  }).then(r => r.json());
  
  // Step 2: Auto-select mode
  const mode = cswCheck.data.within_csw ? 'free' : 'template';
  
  // Step 3: Send message
  const payload = {
    phone: customer.phone,
    last_message_at: customer.last_message_at,
    message_mode: mode
  };
  
  if (mode === 'free') {
    payload.message = message;
  } else {
    // Fallback ke template jika CSW expired
    payload.template_name = 'general_notification';
    payload.template_params = [customer.name, message];
  }
  
  const response = await fetch('/api/WhatsApp/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  
  return await response.json();
}

// Usage
sendSmartMessage(customer, 'Pesanan Anda sudah siap!');
```

---

## 📊 Response Handling

```javascript
async function sendWithErrorHandling(payload) {
  try {
    const response = await fetch('/api/WhatsApp/send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    
    const result = await response.json();
    
    if (result.status) {
      // Success
      console.log('✅ Pesan terkirim!', result.data);
      return { success: true, data: result.data };
    } else {
      // API returned error
      console.error('❌ Error:', result.message);
      
      // Check if CSW expired
      if (result.data?.csw_expired) {
        console.log('💡 Suggestion:', result.data.suggestion);
        // Retry dengan template mode
      }
      
      return { success: false, error: result.message };
    }
  } catch (error) {
    // Network or other error
    console.error('❌ Network error:', error);
    return { success: false, error: error.message };
  }
}
```

---

## 🗄️ Database Integration

Simpan `last_message_at` di database:

```sql
-- Update saat customer kirim pesan (via webhook)
UPDATE customer_whatsapp 
SET last_message_at = NOW(),
    total_messages_received = total_messages_received + 1
WHERE phone = '+6281234567890';

-- Update saat kita kirim pesan
UPDATE customer_whatsapp 
SET last_sent_at = NOW(),
    total_messages_sent = total_messages_sent + 1
WHERE phone = '+6281234567890';

-- Check CSW status
SELECT phone, 
       last_message_at,
       is_csw_active,
       csw_expires_at,
       TIMESTAMPDIFF(HOUR, last_message_at, NOW()) as hours_elapsed
FROM customer_whatsapp
WHERE phone = '+6281234567890';
```

---

## 📝 Checklist Implementasi

### Development
- [x] Setup API key di config
- [x] Test endpoint dengan browser tester
- [x] Test endpoint dengan Postman/cURL
- [ ] Integrate dengan aplikasi utama
- [ ] Setup database tracking (optional)
- [ ] Test semua scenario (CSW active/expired)

### Production
- [ ] Update API key production
- [ ] Test di production environment
- [ ] Setup webhook dari yCloud untuk receive messages
- [ ] Monitor logs di `logs/whatsapp/`
- [ ] Setup alert untuk failed messages
- [ ] Document semua template yang digunakan

---

## 🆘 Troubleshooting

### Error: "CSW expired"
**Penyebab:** `last_message_at` lebih dari 24 jam yang lalu

**Solusi:** Ganti `message_mode` ke `"template"`

### Error: "Template not found"
**Penyebab:** Template belum dibuat/approved di WhatsApp Business

**Solusi:** 
1. Login ke WhatsApp Business Manager
2. Create & submit template for approval
3. Tunggu approval (biasanya 1-2 hari)

### Error: "Invalid phone number"
**Penyebab:** Format nomor salah

**Solusi:** Gunakan format `081234567890` atau `+6281234567890` (auto-convert)

### Error: "API Key invalid"
**Penyebab:** API key salah atau expired

**Solusi:** Check di `app/Config/WhatsApp.php` dan update API key

---

## 🎓 Next Steps

1. ✅ **Baca dokumentasi lengkap**: `WHATSAPP_API_README.md`
2. ✅ **Lihat implementation summary**: `WHATSAPP_IMPLEMENTATION_SUMMARY.md`
3. ✅ **Test semua endpoint** dengan browser tester
4. ✅ **Setup database tracking** (optional)
5. ✅ **Create templates** di WhatsApp Business Manager
6. ✅ **Setup webhook** untuk receive messages
7. ✅ **Integrate** dengan aplikasi utama

---

## 📞 Need Help?

- 📚 Full Documentation: `WHATSAPP_API_README.md`
- 📦 Implementation Detail: `WHATSAPP_IMPLEMENTATION_SUMMARY.md`
- 🧪 Testing Tool: `whatsapp-tester.html`
- 📊 Database Schema: `database/whatsapp_tables.sql`

---

**Happy Coding! 🚀**

*Created for nalju.com*
