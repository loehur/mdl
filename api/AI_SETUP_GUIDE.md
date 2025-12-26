# 🤖 AI-Powered WhatsApp Auto-Reply Setup Guide

## 📋 Fitur yang Sudah Diimplementasi

✅ **2-Layer Intent Detection:**
1. **Layer 1:** Regex Pattern Matching (cepat & gratis)
2. **Layer 2:** AI-Powered Classification (smart fallback)

✅ **Google Gemini API Integration:**
- FREE tier (15 request/menit, 1,500 request/hari)
- Model: `gemini-2.0-flash-exp` (tercepat & terbaru)
- Auto rate limiting & cooldown
- Comprehensive error handling

---

## 🚀 Cara Setup (5 Menit)

### Step 1: Dapatkan Gemini API Key (GRATIS)

1. **Buka:** https://aistudio.google.com/app/apikey
2. **Login** dengan Google Account Anda
3. **Klik:** "Create API Key"
4. **Copy** API Key yang dihasilkan

### Step 2: Konfigurasi API Key

Buka file: `api/app/Config/AI.php`

Edit baris berikut:

```php
// SEBELUM (kosong):
private static $geminiApiKey = '';
private static $aiEnabled = false;

// SESUDAH (isi dengan API key Anda):
private static $geminiApiKey = 'AIzaSy...your-api-key-here';
private static $aiEnabled = true;  // ← AKTIFKAN INI!
```

**PENTING:** 
- Jangan share API key ke publik
- Jangan commit ke git (tambahkan ke .gitignore jika perlu)

### Step 3: Test!

Sekarang sistem sudah bisa menangani pesan natural language yang tidak match regex!

---

## 📊 Cara Kerja Sistem

### Flow Diagram:

```
User Message: "tolong kirim bon dong kak"
        │
        ▼
┌───────────────────────┐
│ LAYER 1: Regex Check  │
└───────┬───────────────┘
        │
        ├─ ✅ MATCH → Execute Handler
        │
        └─ ❌ NO MATCH
                │
                ▼
     ┌──────────────────────────┐
     │ LAYER 2: AI Classification│
     │ (Gemini API)              │
     └──────┬───────────────────┘
            │
            ├─ Intent: NOTA → handleNota()
            ├─ Intent: STATUS → handleStatus()
            ├─ Intent: JAM_BUKA → handleJam_buka()
            ├─ Intent: PEMBUKA → handlePembuka()
            ├─ Intent: PENUTUP → handlePenutup()
            └─ Intent: UNKNOWN → No reply
```

---

## 🎯 Contoh Test Cases

### ✅ Akan Ditangani AI (sebelumnya gagal):

| User Message | Sebelum | Sekarang | Intent AI |
|-------------|---------|----------|-----------|
| "tolong kirim bon dong" | ❌ No reply | ✅ Kirim nota | NOTA |
| "laundry saya udah bisa diambil belum?" | ❌ No reply | ✅ Cek status | STATUS |
| "jam berapa tutup?" | ❌ No reply | ✅ Info jam buka | JAM_BUKA |
| "halo kak" | ❌ No reply | ✅ Greeting | PEMBUKA |
| "terima kasih banyak ya" | ❌ No reply | ✅ "Baik 👌" | PENUTUP |

### ⚡ Masih Ditangani Regex (lebih cepat):

| User Message | Layer | Handler |
|-------------|-------|---------|
| "bon" | Regex | handleNota() |
| "cek" | Regex | handleStatus() |
| "kapan buka" | Regex | handleJam_buka() |
| "p" | Regex | handlePembuka() |
| "makasih" | Regex | handlePenutup() |

---

## 📈 Monitoring & Logging

### Log Files (di folder logs/):

**1. wa_ai_success.log** - AI berhasil klasifikasi
```
2025-12-26 15:30:45 | AI SUCCESS: Intent='NOTA' | Message='tolong kirim bon dong'
```

**2. wa_ai_error.log** - Error dari API
```
2025-12-26 15:31:12 | AI Intent Detection Error: Gemini API error: HTTP 403
```

**3. wa_ai_invalid_intent.log** - AI return intent tidak valid
```
2025-12-26 15:32:00 | AI returned invalid intent: 'SPAM' for message: 'promo casino'
```

**4. wa_ai_cooldown.log** - Handler dalam cooldown
```
2025-12-26 15:33:15 | AI detected intent 'NOTA' but handler is in cooldown
```

---

## 💰 Estimasi Biaya & Usage

### Free Tier Limits:
- ✅ 15 requests per minute
- ✅ 1,500 requests per day
- ✅ **GRATIS SELAMANYA**

### Estimasi Usage:
```
Asumsi: 10% pesan tidak match regex (butuh AI)
Total pesan: 100/hari
AI request: 10/hari

FREE TIER: 1,500/hari
USAGE: 10/hari (0.67%)

STATUS: ✅ SANGAT AMAN
```

---

## 🔧 Advanced Configuration

### File: `api/app/Config/AI.php`

```php
// Ubah model (opsional):
private static $geminiModel = 'gemini-2.0-flash-exp'; // Tercepat
// private static $geminiModel = 'gemini-pro';        // Lebih akurat

// Ubah temperature (opsional):
private static $temperature = 0.1;  // Konsisten (recommended)
// private static $temperature = 0.5;  // Lebih kreatif

// Ubah timeout (opsional):
private static $timeout = 10;  // 10 detik (recommended)
```

---

## 🐛 Troubleshooting

### Problem: AI tidak berfungsi

**Check 1:** Pastikan `$aiEnabled = true` di `Config/AI.php`
```php
private static $aiEnabled = true;  // ← Harus true!
```

**Check 2:** Pastikan API key sudah diisi
```php
private static $geminiApiKey = 'AIza...';  // ← Harus ada isinya
```

**Check 3:** Cek log error
```bash
tail -f logs/wa_ai_error.log
```

### Problem: API error 403 (Forbidden)

**Solusi:** API key salah atau expired
1. Generate API key baru di https://aistudio.google.com/app/apikey
2. Replace di `Config/AI.php`

### Problem: API error 429 (Rate Limit)

**Solusi:** Sudah melebihi free tier (15 req/menit)
- Tunggu 1 menit
- Atau upgrade ke paid tier (opsional)

---

## 🎉 Selesai!

Sistem AI-powered auto-reply sudah siap digunakan!

**Next Steps:**
1. Setup API key (5 menit)
2. Test dengan pesan natural language
3. Monitor logs untuk melihat performa
4. Enjoy smart auto-reply! 🚀

---

## 📞 Support

Jika ada masalah, cek:
1. Log files di folder `logs/`
2. Error message di response webhook
3. Documentation di file ini

Happy coding! 💻
