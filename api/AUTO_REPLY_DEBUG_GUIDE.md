# 📊 Auto-Reply Debug Logging Guide

## 📁 File Log yang Dibuat

Setelah ada pesan masuk, sistem akan membuat log di folder `logs/`:

### Log Files:
```
logs/
├── auto_reply_process.log        - Flow utama proses
├── auto_reply_pattern_check.log  - Detail pengecekan pattern
├── auto_reply_match.log          - Pattern yang berhasil match
├── auto_reply_success.log        - Handler yang berhasil dijalankan
├── auto_reply_cooldown.log       - Handler yang kena cooldown
├── auto_reply_skip.log           - Handler yang diskip
├── auto_reply_error.log          - Error yang terjadi
└── auto_reply_ai.log             - Log AI fallback
```

---

## 📖 Cara Membaca Log

### 1. **Cek apakah pesan masuk ke sistem:**

```bash
tail -f logs/auto_reply_process.log
```

**Output yang diharapkan:**
```
2025-12-26 15:45:00 | ========== AUTO-REPLY START ==========
2025-12-26 15:45:00 | Phone: +6281234567890
2025-12-26 15:45:00 | Message: 'bon'
2025-12-26 15:45:00 | Message (lowercase): 'bon'
2025-12-26 15:45:00 | Message Length: 3
2025-12-26 15:45:00 | Total handlers to check: 5
```

✅ **Jika muncul log ini** → Pesan masuk ke sistem  
❌ **Jika tidak ada log** → Pesan TIDAK masuk ke WAReplies::process()

---

### 2. **Cek pattern matching:**

```bash
tail -f logs/auto_reply_pattern_check.log
```

**Output yang diharapkan:**
```
2025-12-26 15:45:00 | --- Checking handler: PEMBUKA ---
2025-12-26 15:45:00 | Max length: 20 | Patterns count: 2
2025-12-26 15:45:01 | Testing pattern #0: /^\s*(ping|ka*k|...)/i
2025-12-26 15:45:01 | ❌ Pattern NOT matched
2025-12-26 15:45:01 | Testing pattern #1: /(pa*gi|so*re|...)/i
2025-12-26 15:45:01 | ❌ Pattern NOT matched
2025-12-26 15:45:02 | --- Checking handler: NOTA ---
2025-12-26 15:45:02 | Max length: 100 | Patterns count: 6
2025-12-26 15:45:02 | Testing pattern #0: /^\s*(bon|nota+|...)/i
2025-12-26 15:45:02 | ✅ PATTERN MATCHED!
```

---

### 3. **Cek apakah handler dipanggil:**

```bash
tail -f logs/auto_reply_success.log
```

**Output yang diharapkan:**
```
2025-12-26 15:45:02 | ✅ Handler executed: handleNota
```

---

### 4. **Cek error (jika ada):**

```bash
tail -f logs/auto_reply_error.log
```

**Output yang mungkin:**
```
2025-12-26 15:45:02 | ❌ ERROR: Method handleNota does NOT exist!
```

---

## 🔍 Troubleshooting Berdasarkan Log

### Problem 1: Tidak ada log sama sekali

**Kemungkinan:**
- Method `process()` tidak dipanggil
- Webhook tidak memanggil WAReplies

**Solusi:**
- Cek file yang memanggil `WAReplies::process()`
- Pastikan webhook handler memanggil method ini

---

### Problem 2: Log muncul tapi pattern tidak match

**Contoh log:**
```
Testing pattern #0: /^\s*(bon|nota+|...)/i
❌ Pattern NOT matched
```

**Kemungkinan:**
- Pattern regex salah
- Message format tidak sesuai

**Solusi:**
1. Lihat message actual di log:
   ```
   Message (lowercase): 'bon '  <- Ada spasi di akhir!
   ```
2. Update pattern atau trim message

---

### Problem 3: Pattern match tapi handler tidak jalan

**Contoh log:**
```
✅ PATTERN MATCHED!
Handler: NOTA
❌ Handler NOTA in COOLDOWN - skipping
```

**Kemungkinan:**
- Handler dalam cooldown (baru saja dipanggil)
- Rate limiting aktif

**Solusi:**
- Tunggu cooldown selesai (default 5 menit)
- Atau hapus log cooldown di database `wa_auto_reply_log`

---

### Problem 4: Method tidak ditemukan

**Contoh log:**
```
Calling method: handleNota
❌ ERROR: Method handleNota does NOT exist!
```

**Kemungkinan:**
- Method name salah
- Typo di nama method

**Solusi:**
- Cek nama method di WAReplies.php
- Pastikan nama sesuai: `handleNota`, `handleStatus`, dll

---

## 📊 Live Monitoring (Recommended)

Gunakan command ini untuk live monitoring semua log:

### Linux/Mac:
```bash
tail -f logs/auto_reply_*.log
```

### Windows (PowerShell):
```powershell
Get-Content logs\auto_reply_*.log -Wait -Tail 50
```

---

## 🧹 Clear Logs (untuk testing fresh)

```bash
# Linux/Mac
rm logs/auto_reply_*.log

# Windows
del logs\auto_reply_*.log
```

---

## 📋 Checklist Debugging

Ikuti checklist ini step by step:

- [ ] **Step 1:** Kirim pesan test (misal: "bon")
- [ ] **Step 2:** Cek `auto_reply_process.log` → Apakah pesan masuk?
- [ ] **Step 3:** Cek `auto_reply_pattern_check.log` → Apakah ada pattern yang di-test?
- [ ] **Step 4:** Cek `auto_reply_match.log` → Apakah ada pattern yang match?
- [ ] **Step 5:** Cek `auto_reply_cooldown.log` → Apakah handler kena cooldown?
- [ ] **Step 6:** Cek `auto_reply_success.log` → Apakah handler berhasil dijalankan?
- [ ] **Step 7:** Cek `auto_reply_error.log` → Apakah ada error?

---

## 💡 Tips

1. **Gunakan Live Tail** untuk realtime monitoring
2. **Hapus log lama** sebelum testing untuk hasil yang clean
3. **Catat timestamp** untuk tracking issue
4. **Screenshot log** jika perlu share untuk debugging

---

## 🎯 Contoh Flow Normal (Success)

```
[auto_reply_process.log]
========== AUTO-REPLY START ==========
Phone: +628123456789
Message: 'bon'
Message (lowercase): 'bon'
Message Length: 3
Total handlers to check: 5

[auto_reply_pattern_check.log]
--- Checking handler: PEMBUKA ---
Max length: 20 | Patterns count: 2
Testing pattern #0: /^\s*(ping|ka*k|...)/i
❌ Pattern NOT matched
Testing pattern #1: /(pa*gi|so*re|...)/i
❌ Pattern NOT matched

--- Checking handler: NOTA ---
Max length: 100 | Patterns count: 6
Testing pattern #0: /^\s*(bon|nota+|...)/i

[auto_reply_match.log]
✅ PATTERN MATCHED!
Handler: NOTA
Pattern: /^\s*(bon|nota+|...)/i

[auto_reply_process.log]
✅ Rate limit OK - proceeding to call handler
Calling method: handleNota

[auto_reply_success.log]
✅ Handler executed: handleNota
========== AUTO-REPLY END (SUCCESS) ==========
```

**Hasil:** Auto-reply berhasil! ✅

---

## 📞 Jika Masih Bermasalah

Kirim screenshot dari:
1. `auto_reply_process.log` (50 baris terakhir)
2. `auto_reply_pattern_check.log` (semua untuk 1 pesan)
3. `auto_reply_error.log` (jika ada)

Happy debugging! 🐛🔍
