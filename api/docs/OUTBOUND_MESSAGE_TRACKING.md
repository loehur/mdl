# WhatsApp Outbound Message Tracking - SOLUTION

## ❌ Masalah yang Ditemukan

**Outbound messages** (pesan yang kita kirim) **TIDAK** tersimpan di database `wa_messages`.

### Alur Masalah:
1. ✅ Kirim WA via `WhatsAppService::sendFreeText()` → API YCloud
2. ❌ **TIDAK ADA** insert ke `wa_messages`
3. ✅ Webhook terima event `whatsapp.message.updated`
4. ❌ Webhook coba UPDATE berdasarkan `wamid`
5. ❌ **GAGAL** - Record tidak ditemukan!

### Hasil:
- `sent_at`, `delivered_at`, `read_at` tetap NULL
- Tidak ada tracking status untuk pesan keluar

---

## ✅ Solusi yang Diterapkan

### File Modified: `api/app/Helpers/WhatsAppService.php`

#### 1. Method `sendRequest()` - Ditambahkan Auto-Save
Setelah berhasil kirim pesan ke YCloud API, otomatis save ke database:

```php
// Save outbound message to database if successful
if ($success && isset($responseData['id'])) {
    $this->saveOutboundMessage($payload, $responseData);
}
```

#### 2. Method Baru: `saveOutboundMessage()`
Method ini akan:
- ✅ Get/Create customer berdasarkan nomor WA
- ✅ Get/Create conversation
- ✅ Insert outbound message ke `wa_messages` dengan:
  - `direction` = 'out'
  - `wamid` = dari response API
  - `status` = 'sent' (initial status)
  - `message_type`, `text`, `media_url`, dll

---

## 🔄 Alur Lengkap Sekarang

### Kirim Pesan:
1. ✅ Call `WhatsAppService::sendFreeText()`
2. ✅ Kirim ke YCloud API
3. ✅ **Dapat response dengan `wamid`**
4. ✅ **INSERT ke `wa_messages`** dengan status 'sent'

### Webhook Update Status:
1. ✅ Customer baca pesan
2. ✅ YCloud kirim webhook `whatsapp.message.updated`
3. ✅ Webhook handler cari message berdasarkan `wamid`
4. ✅ **KETEMU!** Update kolom:
   - `status` → 'delivered' / 'read'
   - `sent_at`, `delivered_at`, `read_at`
   - `updated_at`

---

## 📤 Deployment

### 1. Commit & Push (SUDAH DILAKUKAN)
```bash
git add .
git commit -m "Add outbound message tracking to wa_messages"
git push
```

### 2. Di SERVER, jalankan:
```bash
cd /path/to/your/app
git pull origin main
```

### 3. Test
Kirim pesan WhatsApp:
```php
$wa = new WhatsAppService();
$wa->sendFreeText('+6281234567890', 'Test message tracking');
```

Cek database:
```sql
SELECT * FROM wa_messages 
WHERE direction = 'out' 
ORDER BY id DESC LIMIT 5;
```

Harusnya ada record dengan:
- `wamid` terisi
- `status` = 'sent'
- `direction` = 'out'

### 4. Tunggu Customer Baca
Setelah customer baca, cek lagi:
```sql
SELECT id, wamid, status, sent_at, delivered_at, read_at, updated_at
FROM wa_messages 
WHERE direction = 'out' 
ORDER BY id DESC LIMIT 5;
```

Harusnya kolom timestamp sudah terisi!

---

## 🧪 Debug Script

### Check Outbound Messages
```php
// check_outbound.php
$conn = new mysqli('localhost', 'mdl_main', 'password', 'mdl_main');

$result = $conn->query("
    SELECT id, direction, status, wamid, sent_at, delivered_at, read_at, created_at
    FROM wa_messages 
    WHERE direction = 'out'
    ORDER BY id DESC 
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    print_r($row);
}
```

---

## ⚠️ Catatan Penting

1. **Initial Status**: Outbound message di-save dengan status `'sent'`
2. **Webhook Update**: Status akan berubah menjadi `'delivered'` atau `'read'` via webhook
3. **Error Handling**: `saveOutboundMessage()` menggunakan try-catch, jadi tidak akan break flow kirim pesan
4. **Database Requirement**: Kolom timestamp (`sent_at`, `delivered_at`, `read_at`) harus sudah ada

---

Updated: 2025-12-20
Status: ✅ FIXED & READY TO DEPLOY
