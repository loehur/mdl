# QRIS Display

Aplikasi Android kiosk untuk tablet kasir yang menampilkan QRIS secara real-time via WebView.

- **URL:** https://qrc.nalju.com/
- **Package:** `com.mdl.qrisdisplay`
- **Layar:** Selalu aktif (tidak sleep)
- **Kiosk:** Keluar hanya dengan tahan **Back + Volume Down** bersamaan selama 3 detik
- **Buka app:** Manual dari launcher (tidak auto-start saat boot)

## Build APK

```bash
cd android/qris_display
gradlew.bat assembleDebug
```

APK: `app/build/outputs/apk/debug/app-debug.apk`

### Sync ke download laundry (Setting → Printer)

Setelah build, jalankan:

```bat
sync-apk.bat
```

Menyalin APK ke `laundry/in_assets/files/qris-display.apk` (auto-build jika APK belum ada).

## Instalasi di Tablet Kasir

1. Install APK ke tablet Android (min SDK 24 / Android 7.0)
2. Pastikan tablet **sudah terhubung internet**, lalu buka aplikasi **QRIS Display** secara manual
3. Izinkan **Notifikasi** (untuk foreground service)
4. Izinkan **Battery unrestricted** saat diminta — atau manual:
   - Settings → Apps → QRIS Display → Battery → **Unrestricted**
5. Masukkan **ID Cabang** di layar login — cookie tersimpan, auto-connect saat app dibuka kembali

## Kiosk Mode

| Aksi | Perilaku |
|------|----------|
| Tombol Back | Diabaikan |
| Home / Recent | Disembunyikan (immersive mode) |
| Keluar app | Tahan **Back + Volume Down** 3 detik |

### Screen Pinning (opsional, kiosk penuh)

Untuk mengunci Home button sepenuhnya:

1. Settings → Security → Screen pinning → ON
2. Buka QRIS Display
3. Recent apps → ikon pin di QRIS Display

## Arsitektur

```
Backend (PHP/JS) ──POST /send-qr──► qrs.nalju.com (Node.js)
                                         │
                                    WebSocket
                                         │
Tablet ◄── WebView qrc.nalju.com ◄───────┘
(QRIS Display app)
```

## Fitur Teknis

- `FLAG_KEEP_SCREEN_ON` — layar tidak pernah mati
- Foreground service — process tetap hidup selama app dibuka
- Native offline overlay — tidak menampilkan error page browser
- WebView connection overlay (qr_client) — UI rapi saat WebSocket putus

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| App tidak connect saat boot | Buka app manual setelah WiFi/internet sudah siap |
| WebSocket putus | Cek koneksi internet, pastikan ID Cabang valid di qr_server |
| Layar mati | Pastikan battery optimization = Unrestricted |
| Tidak bisa keluar | Tahan Back + Volume Down bersamaan 3 detik |
