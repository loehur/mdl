# WhatsApp Webhook - Message Status Update Fix

## Masalah yang Ditemukan

Webhook WhatsApp **tidak menangkap event** `whatsapp.message.updated` yang penting untuk tracking status message (sent, delivered, read).

### Event yang Diterima:
```json
{
  "type": "whatsapp.message.updated",
  "whatsappMessage": {
    "wamid": "wamid.HBgNNjI4MTI2ODA5ODMwMBUCABEYEkI2OTlGNjJGQTFFNDJENENGQQA=",
    "status": "read",
    "sendTime": "2025-12-19T17:00:35.000Z",
    "deliverTime": "2025-12-19T17:00:35.000Z",
    "readTime": "2025-12-19T17:00:36.000Z"
  }
}
```

### Perbedaan Event Types:
1. ✅ `whatsapp.inbound_message.received` - Message masuk dari customer
2. ✅ `whatsapp.message.status.updated` - Status update (struktur berbeda)
3. ❌ `whatsapp.message.updated` - **TIDAK DITANGKAP** (struktur: whatsappMessage)

## Solusi yang Diterapkan

### 1. Menambahkan Handler di Switch Statement
File: `api/app/Controllers/Webhook/WhatsApp.php`

```php
case 'whatsapp.message.updated':
    $this->handleMessageUpdated($db, $data);
    break;
```

### 2. Method handleMessageUpdated()
Method baru yang menangkap:
- ✅ Status: sent, delivered, read
- ✅ Timestamps: sendTime, deliverTime, readTime, updateTime
- ✅ Update ke database berdasarkan wamid

```php
private function handleMessageUpdated($db, $data)
{
    $message = $data['whatsappMessage'] ?? [];
    $wamid = $message['wamid'] ?? null;
    $status = $message['status'] ?? null;

    $updateData = [
        'status' => $status,
        'sent_at' => $this->convertTime($message['sendTime']),
        'delivered_at' => $this->convertTime($message['deliverTime']),
        'read_at' => $this->convertTime($message['readTime']),
        'updated_at' => $this->convertTime($message['updateTime'])
    ];

    $db->update('wa_messages', $updateData, ['wamid' => $wamid]);
}
```

### 3. Migrasi Database
File: `api/database/migrations/add_message_timestamps.sql`

**PENTING:** Jalankan SQL migration untuk menambahkan kolom:
```sql
ALTER TABLE `wa_messages`
ADD COLUMN `sent_at` datetime DEFAULT NULL,
ADD COLUMN `delivered_at` datetime DEFAULT NULL,
ADD COLUMN `read_at` datetime DEFAULT NULL,
ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp();
```

## Cara Menjalankan Migration

### Via phpMyAdmin:
1. Buka phpMyAdmin
2. Pilih database yang sesuai (mdl_api atau sesuai config)
3. Buka tab SQL
4. Copy-paste isi file `api/database/migrations/add_message_timestamps.sql`
5. Klik "Go"

### Via Command Line:
```bash
mysql -u root -p nama_database < api/database/migrations/add_message_timestamps.sql
```

## Testing

Setelah migration dijalankan, webhook akan:

1. ✅ Menerima event `whatsapp.message.updated`
2. ✅ Log di file: `✓ Message updated: wamid -> status (event: message.updated)`
3. ✅ Update kolom:
   - `status` → 'sent', 'delivered', 'read'
   - `sent_at` → waktu kirim
   - `delivered_at` → waktu terkirim
   - `read_at` → waktu dibaca
   - `updated_at` → waktu update terakhir

## Monitoring

Cek log webhook di:
- Path: sesuai konfigurasi Log::write()
- Filter: 'WhatsApp'
- Cari: "Message updated" atau "message.updated"

## Struktur Event Types

| Event Type | Field Data | Handler Method |
|------------|-----------|----------------|
| whatsapp.inbound_message.received | whatsappInboundMessage | handleInboundMessage() |
| whatsapp.message.status.updated | whatsappMessageStatusUpdate | handleStatusUpdate() |
| **whatsapp.message.updated** | **whatsappMessage** | **handleMessageUpdated()** ✅ |

---
Updated: 2025-12-20
Status: ✅ FIXED
