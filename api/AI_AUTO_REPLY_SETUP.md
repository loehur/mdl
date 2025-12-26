# Auto-Reply AI Configuration Guide

## Masalah: Log AI Tidak Lengkap

Jika Anda melihat log seperti ini:
```
16:13:53 AI handler called for message: 'Kira kira siap kapan itu kak'
```

Tanpa ada log lanjutan, berarti **AI handler tidak berjalan dengan benar**.

---

## Penyebab Umum

Ada beberapa kemungkinan:

### 1. **File AI.php Tidak Ada**
File konfigurasi AI belum dibuat.

**Solusi:**
```bash
# Copy file example ke AI.php
cp app/Config/AI.php.example app/Config/AI.php
```

### 2. **AI Disabled (Default)**
By default, AI fallback dinonaktifkan untuk menghemat API calls.

**Solusi:**
Edit `app/Config/AI.php`:
```php
private static $aiEnabled = true; // Ubah dari false ke true
```

### 3. **API Key Belum Dikonfigurasi**
Gemini API key belum diisi.

**Solusi:**
1. Dapatkan API key dari: https://aistudio.google.com/api-keys
2. Edit `app/Config/AI.php`:
```php
private static $geminiApiKey = 'AIzaSy...'; // Paste API key Anda
```

### 4. **Error Saat Load Config**
Syntax error atau path issue.

**Solusi:**
Cek log terbaru setelah update. Sekarang ada logging detail:
```
Loading AI Config from: /path/to/Config/AI.php
✅ AI Config loaded successfully
Checking if AI is enabled...
❌ AI is DISABLED
```

---

## Cara Mengaktifkan AI

### Step 1: Copy Config File
```bash
cd c:\xampp82\htdocs\mdl\api
copy app\Config\AI.php.example app\Config\AI.php
```

### Step 2: Edit Konfigurasi
Edit `app/Config/AI.php`:

```php
// 1. Enable AI
private static $aiEnabled = true;

// 2. Tambahkan API Key
private static $geminiApiKey = 'YOUR_ACTUAL_API_KEY_HERE';

// 3. (Opsional) Sesuaikan model
private static $geminiModel = 'gemini-1.5-flash'; // atau gemini-1.5-pro
```

### Step 3: Test
Kirim pesan yang tidak match dengan keyword pattern, misalnya:
```
"Kira kira siap kapan itu kak"
```

Cek log di `logs/auto_reply_ai.log`:
```
✅ AI is enabled, preparing prompt...
Calling Gemini API...
AI Response: 'STATUS' (Intent: 'STATUS')
✅ Valid intent detected: STATUS
✅ Cooldown OK, calling handler...
✅ AI SUCCESS: Executing handleStatus
```

---

## Troubleshooting

### Log: "AI Config file NOT FOUND"
File AI.php tidak ada di `app/Config/`.

**Fix:**
```bash
copy app\Config\AI.php.example app\Config\AI.php
```

### Log: "AI is DISABLED"
Setting `$aiEnabled` masih false atau API key belum di-set.

**Fix:**
```php
private static $aiEnabled = true;
private static $geminiApiKey = 'AIzaSy...'; // API key valid
```

### Log: "Exception during AI config check"
Syntax error di AI.php atau class name salah.

**Fix:**
Pastikan namespace dan class name benar:
```php
namespace App\Config;

class AI
{
    // ...
}
```

### Error: "Gemini API error: HTTP 400"
API key invalid atau request body salah.

**Fix:**
- Periksa API key di https://aistudio.google.com/api-keys
- Pastikan API key aktif dan belum expired

### Error: "Gemini API cURL error"
Network issue atau timeout.

**Fix:**
Tingkatkan timeout di AI.php:
```php
private static $timeout = 15; // dari 10 ke 15 detik
```

---

## Monitoring Log

Log AI ada di:
```
logs/auto_reply_ai.log
```

Untuk melihat log real-time:
```bash
# Windows
type logs\auto_reply_ai.log

# Atau gunakan view_logs.bat jika ada
.\view_logs.bat
```

---

## Kapan AI Diaktifkan?

AI fallback **hanya dijalankan jika**:
1. Message tidak match dengan keyword pattern apapun
2. Message length tidak melebihi `max_length` dari pattern
3. Semua handler pattern tidak cocok

**Contoh pesan yang akan menggunakan AI:**
- "Kira kira siap kapan itu kak" → AI deteksi sebagai STATUS
- "Mau lihat tagihannya dong" → AI deteksi sebagai NOTA
- "Jam berapa buka ya?" → AI deteksi sebagai JAM_BUKA

**Pesan yang TIDAK perlu AI (sudah match regex):**
- "bon" → keyword match langsung (NOTA)
- "cek" → keyword match langsung (STATUS)
- "p" → single char match (PEMBUKA)

---

## Update Terbaru (26 Des 2025)

✅ **Enhanced Error Handling & Logging**
- Tambahan try-catch untuk catch all errors
- Logging detail di setiap step
- File existence check sebelum require
- Error message yang lebih jelas

**Sekarang log akan menunjukkan:**
```
AI handler called for message: 'Kira kira siap kapan itu kak'
Loading AI Config from: /path/to/Config/AI.php
✅ AI Config loaded successfully
Checking if AI is enabled...
[✅ atau ❌] AI status
```

Jika ada error, stack trace akan di-log untuk debugging.

---

## Kontak

Jika masih ada masalah, cek:
1. Log di `logs/auto_reply_ai.log`
2. Error log di `logs/webhook/wa_local/`
3. PHP error log

Happy Auto-Replying! 🚀
