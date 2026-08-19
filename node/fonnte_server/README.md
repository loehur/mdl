# fonnte_server

Gateway WhatsApp **self-hosted** kompatibel API Fonnte, memakai [@whiskeysockets/baileys](https://github.com/WhiskeySockets/Baileys).

Menggantikan `api.fonnte.com` untuk:

- **Kirim pesan** (`POST /send`) — teks, gambar/video/dokumen via `url`
- **Webhook masuk** ke PHP `Webhook/WA_Fonnte` dengan **caption media lengkap** + `url`/`extension`
- **Status device** (`POST /device`)

## Setup

```bash
cd node/fonnte_server
cp .env.example .env
# edit .env — FONNTE_TOKEN, WEBHOOK_URL, MEDIA_PUBLIC_BASE_URL
npm install
npm start
```

Scan QR di terminal saat pertama kali jalan. Session disimpan di `auth/`.

## Environment

| Variabel | Keterangan |
|----------|------------|
| `PORT` | Port HTTP (default `3025`) |
| `FONNTE_TOKEN` | Samakan dengan `Env::FONNTE_TOKEN` di API PHP |
| `WEBHOOK_URL` | Contoh: `https://api.nalju.com/Webhook/WA_Fonnte` |
| `MEDIA_PUBLIC_BASE_URL` | URL publik agar PHP bisa unduh media (contoh `https://wa.nalju.com`) |
| `LID_MAP_MIRROR_FILE` | Salin `lid_phone_map.json` ke `api/data/` agar PHP bisa baca (open_basedir) |

## Integrasi PHP (sudah diterapkan)

`api/app/Config/Fonnte.php` default ke `http://127.0.0.1:3025`.
Override opsional di `Env.php`:

```php
const FONNTE_BASE_URL = 'http://127.0.0.1:3025';
const FONNTE_TOKEN = 'token-anda';
```

Webhook **tidak** diubah di PHP — `Webhook/WA_Fonnte` tetap endpoint yang sama.
Yang berubah: sumber webhook dari cloud Fonnte → `fonnte_server` (set `WEBHOOK_URL` di `.env` node).

Token harus **sama** di `Env::FONNTE_TOKEN` dan `fonnte_server/.env` → header `Authorization`.

## Endpoint

| Method | Path | Fungsi |
|--------|------|--------|
| POST | `/send` | Kirim WA (form/json, sama Fonnte) |
| POST | `/device` | Status koneksi device |
| GET | `/health` | Health check |
| GET | `/qr` | QR JSON (jika belum paired) |
| GET | `/media/:file` | File media masuk |

## Webhook payload masuk

Format mengikuti webhook Fonnte yang sudah dipakai `WA_Fonnte.php`:

- `message` / `pesan` — teks atau **caption gambar**
- `url`, `filename`, `extension` — media unduhan lokal
- `inboxid`, `sender`, `timestamp`, `device`, dll.

## Production (VPS)

1. Reverse proxy (nginx) ke port `3025`, expose `/media` untuk URL media.
2. `pm2 start server.js --name fonnte_server`
3. Pastikan `MEDIA_PUBLIC_BASE_URL` bisa diakses dari server API (`api.nalju.com`).

## Reset session

```bash
rm -rf auth/*
npm start
```

Scan QR ulang.
