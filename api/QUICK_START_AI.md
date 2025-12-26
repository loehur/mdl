# Quick Start - Enable AI Auto Reply

## Setup AI dalam 3 Langkah:

### 1️⃣ Copy Config File
```bash
cd c:\xampp82\htdocs\mdl\api
copy app\Config\AI.php.example app\Config\AI.php
```

### 2️⃣ Dapatkan API Key
- Kunjungi: **https://aistudio.google.com/api-keys**
- Login dengan Google Account
- Klik "Create API Key"
- Copy API key yang di-generate

### 3️⃣ Edit Config
Edit file `app/Config/AI.php`:

```php
// Line 17: Enable AI
private static $aiEnabled = true; // ← Ubah dari false ke true

// Line 26: Paste API Key
private static $geminiApiKey = 'AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXX'; // ← Paste API key Anda
```

**Save file** dan selesai! ✅

---

## Test AI

Kirim pesan WhatsApp yang tidak match dengan keyword:
```
"Kira kira siap kapan itu kak"
```

Cek log di `logs/auto_reply_ai.log`:
```
✅ AI is enabled, preparing prompt...
Calling Gemini API...
AI Response: 'STATUS' (Intent: 'STATUS')
✅ AI SUCCESS: Executing handleStatus
```

---

## Troubleshooting

### ❌ "AI Config file NOT FOUND"
```bash
# Pastikan sudah copy file
copy app\Config\AI.php.example app\Config\AI.php
```

### ❌ "AI is DISABLED"
Edit `AI.php`, ubah:
```php
private static $aiEnabled = true;
```

### ❌ "Gemini API error: HTTP 400"
API key invalid. Cek di https://aistudio.google.com/api-keys

---

## Model Options

Default menggunakan `gemini-2.5-flash` (terbaru, cepat & murah).

Untuk model lain, ubah di config:
```php
// Fastest & Cheapest (Recommended)
private static $geminiModel = 'gemini-2.5-flash'; // Default

// Alternative models
private static $geminiModel = 'gemini-1.5-flash'; // Older version
private static $geminiModel = 'gemini-1.5-pro';   // More accurate, slower
```

---

## Implementation Details

✅ **Updated sesuai dokumentasi resmi Google:**
- API key via `x-goog-api-key` header (bukan query string)
- Support model terbaru `gemini-2.5-flash`
- Error handling lebih baik

---

## Done! 🎉

AI sekarang akan auto-detect intent untuk pesan yang tidak match dengan keyword pattern.

**Lihat dokumentasi lengkap:** `AI_AUTO_REPLY_SETUP.md`
