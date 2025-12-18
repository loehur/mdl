# WhatsApp Multi-Session Service - Configuration Guide

## Quick Start

### Development (Local)
File: `.env`
```env
NODE_ENV=development
PORT=8033
```

Webhook akan otomatis mengarah ke:
```
http://localhost/mdl/api/Webhook/WA_Local/update
```

### Production (VPS)
File: `.env`
```env
NODE_ENV=production
PORT=8033
```

Webhook akan otomatis mengarah ke:
```
https://ml.nalju.com/WH_Local/update
```

---

## Cara Menggunakan

### 1. **Copy .env.example ke .env** (jika belum ada)
```bash
cp .env.example .env
```

### 2. **Edit .env sesuai environment**

**Untuk Development (Local):**
```env
NODE_ENV=development
```

**Untuk Production (VPS):**
```env
NODE_ENV=production
```

### 3. **Start Server**
```bash
npm start
# atau
node index.js
```

Server akan otomatis mendeteksi environment dan menggunakan webhook URL yang sesuai.

---

## Konfigurasi Webhook URL

Webhook URL dikonfigurasi di `config.js` dan akan otomatis switch berdasarkan `NODE_ENV`:

| Environment | Webhook URL |
|-------------|-------------|
| `development` | `http://localhost/mdl/api/Webhook/WA_Local/update` |
| `production` | `https://ml.nalju.com/WH_Local/update` |

### Menambah Environment Baru

Edit file `config.js`:
```javascript
const WEBHOOK_URLS = {
  development: "http://localhost/mdl/api/Webhook/WA_Local/update",
  production: "https://ml.nalju.com/WH_Local/update",
  staging: "https://staging.nalju.com/WH_Local/update"  // Tambah environment baru
};
```

---

## Tips

1. **Jangan commit file `.env`** - File ini sudah ada di `.gitignore`
2. **Commit file `.env.example`** - Sebagai template untuk tim lain
3. **Cek environment saat startup** - Server akan menampilkan:
   ```
   [Config] Environment: development
   [Config] Webhook URL: http://localhost/mdl/api/Webhook/WA_Local/update
   ```

---

## Troubleshooting

### Webhook masih ke URL lama?
1. Pastikan `.env` sudah diupdate dengan `NODE_ENV` yang benar
2. Restart server Node.js
3. Cek log startup untuk memastikan webhook URL sudah benar

### Environment tidak terdeteksi?
1. Pastikan package `dotenv` sudah terinstall: `npm install`
2. Pastikan file `.env` ada di root directory `wa_server`
3. Cek apakah ada typo di nama environment variable

---

## Support

For any issues, please contact MDL Team.
