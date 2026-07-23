# Print Bridge (Android)

App Android yang meniru API [`arsip/printer_server`](../../arsip/printer_server) di `http://127.0.0.1:3000`, lalu mengirim ESC/POS ke printer thermal **Bluetooth Classic (SPP)**.

## Alur

1. PC Windows menjalankan XAMPP (laundry).
2. Android buka laundry via WiFi LAN (`http://192.168.x.x/...`).
3. Jalankan **Print Bridge**, pilih printer bonded, Start Server.
4. Tombol Cetak di web → `POST http://localhost:3000/print` → bridge → printer Bluetooth.

## Build APK

Buka folder ini di Android Studio, sync Gradle, lalu **Build > Build APK(s)** atau:

```bash
./gradlew assembleDebug
```

APK: `app/build/outputs/apk/debug/app-debug.apk`

## Endpoint (kompatibel PC)

| Method | Path | Keterangan |
|--------|------|------------|
| GET | `/` | Info server |
| GET | `/health` | Health check |
| POST | `/print` | `{ text, margin_top?, feed_lines? }` atau `{ data_b64 }` |
| POST | `/printqr` | `{ qr_string, text?, margin_top?, feed_lines? }` |

## Setup kasir

1. Pair printer thermal di **Settings > Bluetooth** Android.
2. Buka Print Bridge → pilih printer → Simpan → Start Server.
3. Biarkan notifikasi “Print Bridge running” aktif saat pakai Chrome.
4. Buka laundry di Chrome (HTTP LAN), cetak seperti biasa.

## Catatan

- Listen hanya di `127.0.0.1:3000` (bukan jaringan).
- Butuh Classic Bluetooth SPP (bukan BLE/Web Bluetooth).
- Laundry harus HTTP (bukan HTTPS) agar browser boleh memanggil `http://localhost`.
- Chrome (halaman LAN → localhost) butuh CORS Private Network Access; bridge mengirim `Access-Control-Allow-Private-Network: true`.
- Setelah update APK: uninstall/install ulang, Start Server, lalu **hard refresh** laundry.

## Checklist uji manual

1. Install `app/build/outputs/apk/debug/app-debug.apk` ke HP.
2. Pair printer thermal di Settings Bluetooth.
3. Buka Print Bridge → pilih printer → Simpan → **Test Print** (harus keluar kertas).
4. **Start Server** (notifikasi aktif).
5. Di Chrome HP, buka laundry LAN (`http://IP-PC/...`).
6. Cetak nota operasi, cetak QR, cetak Pack Label.
7. Matikan server di app → tekan Cetak lagi → harus muncul toast/alert **cepat** (~≤1 detik, via probe `/health`) bahwa Print Bridge tidak aktif.
