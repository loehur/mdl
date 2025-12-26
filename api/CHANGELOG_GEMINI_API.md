# Changelog - Gemini API Implementation

## 2025-12-26 - Updated to Official Google Documentation

### ✅ Fixed Issues

1. **API Key Method** ❌ → ✅
   - **Before**: API key via query string `?key=xxx`
   - **After**: API key via header `x-goog-api-key` (sesuai dokumentasi resmi)
   
2. **Model Version** 📦
   - **Before**: `gemini-1.5-flash` (older)
   - **After**: `gemini-2.5-flash` (latest, recommended)

3. **URL Construction** 🔧
   - **Before**: URL built di config dengan API key
   - **After**: URL built di runtime, API key terpisah di header

---

### 📄 Documentation Reference

Implementasi sekarang **100% sesuai** dengan dokumentasi resmi Google:

```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -H 'Content-Type: application/json' \
  -X POST \
  -d '{
    "contents": [
      {
        "parts": [
          {
            "text": "How does AI work?"
          }
        ]
      }
    ]
  }'
```

Source: https://aistudio.google.com/api-keys

---

### 🔄 Changes Made

#### 1. `app/Models/WAReplies.php`
**Method: `callGemini()`**

```php
// OLD METHOD (Query String)
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' 
     . $model . ':generateContent?key=' . $apiKey;

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// NEW METHOD (Header) ✅
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' 
     . $model . ':generateContent';

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-goog-api-key: ' . $apiKey  // Official method
]);
```

#### 2. `app/Config/AI.php.example`

**Updated Methods:**
```php
// REMOVED
public static function getGeminiApiUrl() { ... }

// ADDED
public static function getApiKey() {
    return self::$geminiApiKey;
}

public static function getModel() {
    return self::$geminiModel;
}
```

**Updated Default Model:**
```php
// OLD
private static $geminiModel = 'gemini-1.5-flash';

// NEW ✅
private static $geminiModel = 'gemini-2.5-flash';
```

#### 3. Documentation Files
- `QUICK_START_AI.md` - Updated model info & implementation details
- `AUTO_REPLY_OVERVIEW.md` - Updated model reference
- All files updated with correct API key URL

---

### 🎯 Benefits

1. **✅ Compliance** - Sesuai dokumentasi resmi Google
2. **🚀 Performance** - gemini-2.5-flash lebih cepat
3. **🔒 Security** - API key di header lebih aman dari URL
4. **📚 Maintainability** - Easier untuk update/debug

---

### 🔧 Migration Guide

Jika Anda sudah punya `AI.php` custom:

1. **Update model** (optional, tapi recommended):
   ```php
   private static $geminiModel = 'gemini-2.5-flash';
   ```

2. **Tambahkan methods baru**:
   ```php
   public static function getApiKey() {
       return self::$geminiApiKey;
   }
   
   public static function getModel() {
       return self::$geminiModel;
   }
   ```

3. **Hapus method lama** (optional):
   ```php
   // Bisa dihapus, tidak dipakai lagi
   public static function getGeminiApiUrl() { ... }
   ```

**Atau copy ulang dari `AI.php.example`** dan sesuaikan API key.

---

### ✅ Testing

Test dengan pesan:
```
"Kira kira siap kapan itu kak"
```

Expected log:
```
AI handler called for message: 'Kira kira siap kapan itu kak'
✅ AI is enabled, preparing prompt...
Calling Gemini API...
AI Response: 'STATUS' (Intent: 'STATUS')
✅ AI SUCCESS: Executing handleStatus
```

Jika muncul error `HTTP 400`, kemungkinan:
- API key invalid
- Model name salah (pastikan `gemini-2.5-flash`)

---

### 📖 References

- **API Documentation**: https://ai.google.dev/gemini-api/docs
- **Get API Key**: https://aistudio.google.com/api-keys
- **Models**: https://ai.google.dev/gemini-api/docs/models/gemini

---

### 🙏 Credits

Thanks to user untuk koreksi dokumentasi resmi Google! 🎉
