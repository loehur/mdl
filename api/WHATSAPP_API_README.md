# WhatsApp API Endpoint Documentation

API endpoint untuk mengirim pesan WhatsApp menggunakan **yCloud WhatsApp Business API Official**.

## 📋 Daftar Isi
- [Setup](#setup)
- [Konsep CSW (Customer Service Window)](#konsep-csw)
- [Endpoints](#endpoints)
- [Response Format](#response-format)

---

## 🚀 Setup

### 1. Konfigurasi yCloud API
1. Daftar akun di [yCloud](https://ycloud.com)
2. Dapatkan API Key dari dashboard
3. Setup WhatsApp Business Number

### 2. Environment Variables
Copy file `.env.example` ke `.env`:
```bash
cp .env.example .env
```

Edit `.env`:
```env
YCLOUD_API_KEY=your_actual_api_key_here
WHATSAPP_NUMBER=+628123456789
```

### 3. Konfigurasi
Edit file `app/Config/WhatsApp.php` sesuai kebutuhan.

---

## 📚 Konsep CSW (Customer Service Window)

**CSW = Customer Service Window** adalah window waktu 24 jam sejak customer terakhir mengirim pesan.

### Aturan WhatsApp Business API:
- ✅ **Dalam 24 jam**: Bisa kirim **free-form text** (pesan bebas)
- ❌ **Lewat 24 jam**: Hanya bisa kirim **template message** yang sudah di-approve

### Contoh Skenario:
```
Customer terakhir chat: 19 Des 2024, 10:00 WIB
CSW berlaku sampai:     20 Des 2024, 10:00 WIB

Jam 11:00 (19 Des)  → Masih dalam CSW → Boleh free text ✅
Jam 09:00 (20 Des)  → Masih dalam CSW → Boleh free text ✅
Jam 11:00 (20 Des)  → Lewat CSW      → Harus template ❌
```

---

## 🔌 Endpoints

### Base URL
```
https://your-domain.com/api/WhatsApp
```

---

## 1. **Smart Send** (Recommended)
Auto-detect mode berdasarkan CSW.

**Endpoint:** `POST /WhatsApp/send`

### Request Body (Free Text Mode):
```json
{
  "phone": "081234567890",
  "last_message_at": "2024-12-19 18:00:00",
  "message_mode": "free",
  "message": "Halo kak, pesanan Anda sudah siap diambil!"
}
```

### Request Body (Template Mode):
```json
{
  "phone": "081234567890",
  "last_message_at": "2024-12-18 10:00:00",
  "message_mode": "template",
  "template_name": "order_ready",
  "template_language": "id",
  "template_params": ["John Doe", "ORD-12345"]
}
```

### Response (Success):
```json
{
  "status": true,
  "message": "WhatsApp free text sent successfully",
  "data": {
    "message_id": "wamid.xxxx",
    "status": "sent",
    "mode": "free_text",
    "to": "+6281234567890",
    "csw_status": {
      "within_csw": true,
      "hours_elapsed": 2.5
    }
  }
}
```

### Response (CSW Expired):
```json
{
  "status": false,
  "message": "Customer Service Window (CSW) expired. Last message was 26.5 hours ago. Please use template mode instead.",
  "data": {
    "csw_expired": true,
    "hours_elapsed": 26.5,
    "csw_limit": 24,
    "last_message_at": "2024-12-18 10:00:00",
    "suggestion": "Change message_mode to \"template\""
  }
}
```

---

## 2. **Send Free Text**
Kirim pesan bebas (harus dalam CSW).

**Endpoint:** `POST /WhatsApp/send-text`

### Request:
```json
{
  "phone": "081234567890",
  "message": "Terima kasih sudah order! Pesanan sedang diproses.",
  "last_message_at": "2024-12-19 20:00:00"
}
```

### Response:
```json
{
  "status": true,
  "message": "Message sent successfully",
  "data": {
    "id": "wamid.xxxx",
    "status": "sent"
  }
}
```

---

## 3. **Send Template**
Kirim template message (bisa kapan saja).

**Endpoint:** `POST /WhatsApp/send-template`

### Request:
```json
{
  "phone": "081234567890",
  "template_name": "greeting_customer",
  "template_language": "id",
  "template_params": ["Budi", "Platinum"]
}
```

### Template Example di WhatsApp:
```
Template Name: greeting_customer
Content: "Halo {{1}}, selamat datang di membership {{2}}!"
Result: "Halo Budi, selamat datang di membership Platinum!"
```

---

## 4. **Send Media**
Kirim gambar, video, dokumen, atau audio.

**Endpoint:** `POST /WhatsApp/send-media`

### Request (Image):
```json
{
  "phone": "081234567890",
  "type": "image",
  "media_url": "https://nalju.com/images/product.jpg",
  "caption": "Ini produk terbaru kami!",
  "last_message_at": "2024-12-19 20:00:00"
}
```

### Request (Document):
```json
{
  "phone": "081234567890",
  "type": "document",
  "media_url": "https://nalju.com/files/invoice.pdf",
  "filename": "Invoice-12345.pdf",
  "last_message_at": "2024-12-19 20:00:00"
}
```

### Media Types:
- `image` - JPG, PNG (max 5MB)
- `video` - MP4, 3GP (max 16MB)
- `document` - PDF, DOCX, XLSX, etc (max 100MB)
- `audio` - MP3, OGG, AAC (max 16MB)

---

## 5. **Send Interactive Buttons**
Kirim pesan dengan tombol interaktif.

**Endpoint:** `POST /WhatsApp/send-buttons`

### Request:
```json
{
  "phone": "081234567890",
  "header_text": "Konfirmasi Pesanan",
  "body_text": "Pesanan Anda sudah siap. Pilih opsi pengiriman:",
  "footer_text": "Terima kasih - nalju.com",
  "buttons": [
    {
      "id": "pickup",
      "title": "Ambil Sendiri"
    },
    {
      "id": "delivery",
      "title": "Kirim ke Rumah"
    }
  ],
  "last_message_at": "2024-12-19 20:00:00"
}
```

### Batasan:
- Maximum **3 buttons**
- Button title max **20 karakter**
- Button ID max **256 karakter**

---

## 6. **Check CSW Status**
Cek status Customer Service Window.

**Endpoint:** `POST /WhatsApp/check-csw`

### Request:
```json
{
  "last_message_at": "2024-12-19 18:00:00"
}
```

### Response:
```json
{
  "status": true,
  "message": "CSW status retrieved",
  "data": {
    "within_csw": true,
    "last_message_at": "2024-12-19 18:00:00",
    "current_time": "2024-12-19 20:07:58",
    "hours_elapsed": 2.13,
    "hours_remaining": 21.87,
    "csw_limit_hours": 24,
    "can_send_free_text": true,
    "must_use_template": false,
    "expires_at": "2024-12-20 18:00:00"
  }
}
```

---

## 📝 Response Format

### Success Response:
```json
{
  "status": true,
  "message": "Success message",
  "data": { ... }
}
```

### Error Response:
```json
{
  "status": false,
  "message": "Error message",
  "data": { ... } // Optional error details
}
```

---

## 🔐 Security

API ini sudah dilindungi dengan **CORS protection** yang hanya mengizinkan:
- `nalju.com`
- `*.nalju.com` (semua subdomain)

Request dari domain lain akan **ditolak**.

---

## 📊 Logging

Semua pesan WhatsApp akan di-log di:
```
logs/whatsapp/messages_YYYY-MM-DD.log
```

Log berisi:
- Timestamp
- Endpoint
- Request payload
- Response
- HTTP status code

---

## 🧪 Testing dengan Postman/Insomnia

### Headers:
```
Content-Type: application/json
```

### Example cURL:
```bash
curl -X POST https://your-domain.com/api/WhatsApp/send \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "081234567890",
    "last_message_at": "2024-12-19 20:00:00",
    "message_mode": "free",
    "message": "Test pesan dari API"
  }'
```

---

## ⚠️ Catatan Penting

1. **Format Nomor Telepon**: 
   - Input: `081234567890` atau `+6281234567890`
   - Auto-convert ke: `+6281234567890`

2. **last_message_at Format**:
   - Format: `YYYY-MM-DD HH:MM:SS`
   - Timezone: Asia/Jakarta (WIB)
   - Contoh: `2024-12-19 20:07:58`

3. **Template Messages**:
   - Harus dibuat & di-approve dulu di dashboard WhatsApp Business
   - Nama template case-sensitive
   - Parameter dimulai dari {{1}}, {{2}}, dst

4. **Rate Limiting**:
   - yCloud memiliki rate limit
   - Cek dokumentasi yCloud untuk detail

---

## 📞 Support

Untuk bantuan lebih lanjut:
- Dokumentasi yCloud: https://docs.ycloud.com
- WhatsApp Business API: https://developers.facebook.com/docs/whatsapp

---

## 🎯 Best Practices

1. **Selalu gunakan endpoint `/send`** untuk auto-detect CSW
2. **Simpan `last_message_at`** di database untuk setiap customer
3. **Update `last_message_at`** setiap kali customer mengirim pesan
4. **Siapkan template** untuk berbagai scenario (order, reminder, promo)
5. **Test di environment development** sebelum production

---

**Dibuat dengan ❤️ untuk nalju.com**
