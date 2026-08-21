# BCA Scrapper — KlikBCA mutasi & saldo + QRMS transaksi QRIS

Microservice Node.js untuk:
- **Saldo & mutasi rekening BCA** via KlikBCA Individual ([ibank.klikbca.com](https://ibank.klikbca.com/))
- **Transaksi QRIS merchant** via QR Merchant Service ([qr.klikbca.com](https://qr.klikbca.com/login))

Strategi ibank: **HTTP dulu** (login + enkripsi RSA PIN). Jika gagal → **Puppeteer** fallback.

Strategi QRMS: **HTTP API** ke `mssi.ebanksvc.bca.co.id` — payload login dienkripsi via browser headless singkat (~5–10 detik), lalu token + daftar transaksi murni HTTP.

## Setup

```bash
cd node/bca_scrapper
cp .env.example .env
# isi BCA_USERNAME, BCA_PASSWORD (ibank)
# isi BCA_QRMS_EMAIL, BCA_QRMS_PASSWORD (qr.klikbca.com)
# BCA_SCRAPPER_TOKEN (opsional)
npm install
npm run install:chrome   # wajib di VPS Linux (headless Chrome untuk QRIS + fallback mutasi)
npm start
```

Default: `http://127.0.0.1:3021`

### VPS Linux (Chrome tidak ditemukan / download corrupt)

Error `end of central directory record signature not found` = file Chrome corrupt (download putus).

**Perbaikan cepat (root, user www):**

```bash
rm -rf /home/www/.cache/puppeteer/chrome
cd /www/wwwroot/mdl/node/bca_scrapper
su -s /bin/bash www -c 'PUPPETEER_CACHE_DIR=/home/www/.cache/puppeteer npm run install:chrome'
```

**Atau pakai Chromium system (lebih stabil di VPS):**

```bash
apt update && apt install -y chromium-browser
# edit .env:
# PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser
# hapus PUPPETEER_EXECUTABLE_PATH ke /root/ dan PUPPETEER_CACHE_DIR ke /root/
```

**Otomatis (root):** `bash scripts/setup-chrome-vps-www.sh`

Service aaPanel jalan sebagai **www** — jangan arahkan cache ke `/root/.cache`.

## Endpoints

### `GET /health`

```json
{
  "ok": true,
  "status": "running",
  "service": "bca_scrapper",
  "strategy": "http_first_then_puppeteer"
}
```

### `POST /balance`

```bash
curl -X POST http://127.0.0.1:3021/balance \
  -H "Content-Type: application/json" \
  -H "X-Bca-Token: YOUR_TOKEN" \
  -d "{\"username\":\"USER_KLIKBca\",\"password\":\"PASS\"}"
```

Sukses:

```json
{
  "ok": true,
  "method": "http",
  "balance": {
    "rekening": "8455103793",
    "saldo": 1500000
  }
}
```

### `POST /mutasi`

Body:

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `username` | Ya* | User ID KlikBCA |
| `password` | Ya* | PIN KlikBCA |
| `start_date` | Tidak | `YYYY-MM-DD`, default hari ini |
| `end_date` | Tidak | `YYYY-MM-DD`, default = start_date |

\* Bisa juga lewat env `BCA_USERNAME` / `BCA_PASSWORD`.

```bash
curl -X POST http://127.0.0.1:3021/mutasi \
  -H "Content-Type: application/json" \
  -H "X-Bca-Token: YOUR_TOKEN" \
  -d "{\"username\":\"USER\",\"password\":\"PASS\",\"start_date\":\"2026-08-19\"}"
```

Sukses:

```json
{
  "ok": true,
  "method": "http",
  "start_date": "2026-08-19",
  "end_date": "2026-08-19",
  "count": 2,
  "mutasi": [
    {
      "tanggal": "19/08",
      "keterangan": "TRSF E-BANKING CR",
      "cab": "014",
      "nominal": 50000,
      "mutasi": "CR"
    }
  ]
}
```

Jika HTTP gagal tapi Puppeteer berhasil, field `method` = `"puppeteer"` dan `http_error` berisi alasan kegagalan HTTP.

### `POST /qris/transactions`

Ambil daftar transaksi QRIS merchant dari qr.klikbca.com per rentang tanggal.

Body:

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `email` | Ya* | Email akun QR Merchant Service |
| `password` | Ya* | Password QRMS |
| `start_date` | Tidak | `YYYY-MM-DD`, default hari ini |
| `end_date` | Tidak | `YYYY-MM-DD`, default = start_date |

\* Bisa juga lewat env `BCA_QRMS_EMAIL` / `BCA_QRMS_PASSWORD`.

```bash
curl -X POST http://127.0.0.1:3021/qris/transactions \
  -H "Content-Type: application/json" \
  -H "X-Bca-Token: YOUR_TOKEN" \
  -d "{\"start_date\":\"2026-08-21\"}"
```

Sukses:

```json
{
  "ok": true,
  "method": "puppeteer",
  "start_date": "2026-08-21",
  "end_date": "2026-08-21",
  "count": 1,
  "transactions": [
    {
      "tanggal": "21/08/2026",
      "waktu": "14:30:15",
      "nominal": 50000,
      "status": "SUCCESS",
      "keterangan": null,
      "rrn": "..."
    }
  ]
}
```

Aktifkan `BCA_DEBUG=true` untuk dump HTML + JSON API ke folder `debug/` jika parser perlu disesuaikan.

## PHP

Helper: `laundry/app/Helper/BcaScrapper.php`

```php
$result = BcaScrapper::mutasi('2026-08-19');
if ($result['ok']) {
    foreach ($result['mutasi'] as $row) {
        // auto-match pembayaran
    }
}
```

Env laundry (opsional):

- `BCA_SCRAPPER_URL` = `http://127.0.0.1:3021/mutasi`
- `BCA_SCRAPPER_TOKEN` = sama dengan token di `.env` bca_scrapper

## Debug mode

Aktifkan saat parser saldo/mutasi perlu disesuaikan:

```env
BCA_DEBUG=true
```

Restart server, lalu panggil `/balance` atau `/mutasi` **sekali**.

Output per request:
```
node/bca_scrapper/debug/20260819-114500_http-balance/
├── 02_01_home.html
├── 03_02_login_post.html
├── 04_04_welcome.html
├── 05_05_balance.html      ← HTML saldo mentah
├── balance_parsed.json       ← hasil parser
└── report.json               ← ringkasan step
```

Console server menampilkan log `[bca_debug][...] #1 request:...`.

**Penting:** folder `debug/` berisi data sensitif (saldo, mutasi) — jangan commit (sudah di `.gitignore`).

## Catatan penting

1. **Logout otomatis** setelah setiap request agar tidak kena lock 10 menit KlikBCA.
2. **Satu request aktif** — service menolak request paralel (`429 scraper_busy`).
3. **Interval polling** disarankan ≥ 10 menit antar login.
4. Kredensial disimpan di `.env` server — jangan expose endpoint tanpa token di production.
5. Login ibank memakai **enkripsi RSA PIN** (sama seperti browser). HTTP client meniru `signAndEncrypt()` dari halaman login; Puppeteer fallback mengisi form `#txt_user_id` / `#txt_pswd` langsung.
