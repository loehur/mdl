# MDL UI Theme — Pedoman Tampilan

**Sumber kebenaran** untuk style Operasi / Offcanvas / form laundry.  
Setiap pembuatan atau perubahan tampilan **wajib merujuk file ini**.

Referensi implementasi yang sudah sesuai tema:
- Offcanvas Order → `laundry/app/Views/penjualan/penjualan_main.php` (`#ord-root`)
- Offcanvas Pembayaran → `laundry/app/Views/operasi/partials/modals.php` (`#offcanvasPayment`)
- Top nav + Sidebar → `laundry/app/Views/layout.php` (`.mdl-topbar`, `.main-sidebar`)

---

## 1. Prinsip

1. **Tajam, bukan soft** — teks gelap, font tebal, kontras tinggi.
2. **Penuh warna** — biru, hijau, kuning, merah dipakai dengan jelas (bukan pastel pudar).
3. **Satu komposisi** — panel berwarna dengan border 2px, judul + ikon solid.
4. **State jelas** — opsi terpilih vs tidak harus langsung terbaca (centang, border tebal, opsi lain pudar).
5. **Satu border saja** — jangan double border bertumpuk (input dalam card, form-control + selectize, dll.).
6. **Semua sudut runcing** — **tidak ada round** di seluruh style. Panel, modal, tombol, input, select, badge, icon box, radio mark, chip, FAB: semuanya kotak dengan `border-radius: 0`. Dilarang `rounded`, `rounded-*`, `pill`, `50%`, radius `> 0`.
7. **Jangan default AI look** — hindari ungu/indigo generik, cream terracotta, tipografi soft abu-abu.

---

## 2. Token warna

| Token | Hex | Pakai untuk |
|--------|------|-------------|
| Ink | `#0f172a` | Teks utama |
| Muted | `#1e293b` | Label uppercase |
| Line | `#cbd5e1` / `#94a3b8` | Border default / input |
| Blue | `#2563eb` | Primer info, Satuan, QRIS, Bayar Pas |
| Blue deep | `#1d4ed8` | Gradient / hover biru |
| Green | `#16a34a` | Sukses, Tunai, BCA, tombol Proses/Bayar |
| Green deep | `#15803d` | Gradient hijau |
| Yellow | `#f59e0b` | Warning, Bidang, BRI, Keranjang, Tanggung Bayar |
| Yellow deep | `#d97706` | Border/centang kuning |
| Red | `#dc2626` | Bahaya, Volume, Total tagihan |
| Red deep | `#b91c1c` | Teks total / error |

### Panel tint (background + border)

| Varian | Border | Background |
|--------|--------|------------|
| Blue | `#93c5fd` | `linear-gradient(180deg, #eff6ff, #fff)` |
| Green | `#86efac` | `linear-gradient(180deg, #f0fdf4, #fff)` |
| Yellow | `#fcd34d` | `linear-gradient(180deg, #fffbeb, #fff)` |
| Red | `#fca5a5` | `linear-gradient(180deg, #fef2f2, #fff)` |

### Header offcanvas

```css
background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 35%, #16a34a 70%, #f59e0b 100%);
color: #fff;
font-weight: 900;
letter-spacing: -0.02em;
text-shadow: 0 1px 0 rgba(0,0,0,.18);
```

### Body offcanvas

```css
background:
  radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.14), transparent 50%),
  radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.14), transparent 45%),
  linear-gradient(180deg, #eef4ff 0%, #f4fff8 50%, #fff8eb 100%);
```

---

## 3. Tipografi

| Elemen | Size | Weight | Warna | Catatan |
|--------|------|--------|-------|---------|
| Judul panel | `0.95rem` | `900` | `#0f172a` | + ikon kotak solid |
| Label field | `0.78rem` | `900` | `#1e293b` | UPPERCASE, letter-spacing `0.04em` |
| Body / nilai | `0.84–0.92rem` | `750–900` | `#0f172a` | Jangan soft gray `#5a6a7c` |
| Tombol | `0.95rem` | `900` | sesuai tombol | Padding `12px 14px` |

Font stack: `'fontku', 'Segoe UI', sans-serif`

---

## 4. Sudut — wajib siku

```css
--ui-radius: 0;

.panel, .modal-content, .btn, button, input, select, textarea,
.selectize-input, .badge, .chip, .icon-box, .radio-mark {
  border-radius: 0 !important;
}
```

- **Dilarang**: `border-radius` selain `0`, class Bootstrap `rounded` / `rounded-*` / `rounded-pill`, lingkaran `50%` / `999px`.
- Marker radio / centang / ikon: **kotak**, bukan lingkaran.
- Offcanvas Bootstrap boleh tetap memakai radius framework-nya di outer shell jika tidak mudah diubah; konten di dalamnya harus siku. Prefer override: `.offcanvas { border-radius: 0; }`.

---

## 5. Komponen standar

### Panel / card

- Border `2px`, radius **`0`**, padding `14px`
- Shadow: `0 10px 24px rgba(15, 23, 42, 0.08)`
- Judul: ikon `30×30`, radius **`0`**, warna solid putih di atas warna token

### Modal / dialog / offcanvas panel

- **Sudut runcing** — `border-radius: 0` pada `.modal-content`, panel dialog, tombol, input di dalamnya
- Shadow tetap boleh keras: mis. `0 24px 48px rgba(15, 23, 42, 0.3)`
- Header modal: gradient token (biru/hijau/kuning) + teks putih tebal, sama semangat offcanvas

```css
.modal-content,
.ord-plg-modal__panel,
.ord-order-modal__panel,
.offcanvas {
  border-radius: 0 !important;
}
```

### Tombol

| Jenis | Background | Teks |
|-------|------------|------|
| Primary (aksi utama) | hijau `#15803d → #16a34a` | putih |
| Pass / info | biru `#1d4ed8 → #2563eb` | putih |
| Warn | kuning `#d97706 → #f59e0b` | `#111` |
| Ghost / batal | `#e2e8f0` | `#0f172a` |
| Danger | merah `#dc2626` | putih |

Radius tombol: **`0`**. Weight: `900`.

### Input / Selectize

- **Satu border saja** — jangan `form-control` + selectize bersamaan
- Border `2px solid #94a3b8`, radius **`0`**, weight `800`
- Focus: border biru + ring `0 0 0 3px rgba(37, 99, 235, 0.22)`
- Readonly khusus: background `#fef3c7`, border kuning

### Input / select tunggal — JANGAN dibungkus card

Jika field hanya **satu** input atau select yang sudah punya border sendiri:

- **Jangan** bungkus lagi dengan card / panel / kotak ber-border.
- **Jangan** menumpuk dua kerangka berdekatan (terlihat seperti dua lingkaran/kotak bertumpuk).
- Cukup: **label + kontrol** di atas background panel induk (atau langsung di body).
- Card/panel hanya untuk **kelompok konten** (beberapa field, list, aksi), bukan untuk 1 kontrol tunggal.

```text
❌ SALAH
┌─ card border ───────────┐
│  ┌─ input border ─────┐ │
│  │ Penerima / select  │ │
│  └────────────────────┘ │
└─────────────────────────┘

✅ BENAR
PENERIMA
┌─ input/select border ───┐
│ nilai                   │
└─────────────────────────┘
```

Contoh yang sudah benar: select **Penerima** di Pembayaran (label saja, tanpa card sendiri).

### Option radio / pilihan kartu (metode, tujuan, layanan)

**Tidak dipilih**
- Opacity ~`0.72`, background abu `#f8fafc`, ikon abu
- Kotak kosong di pojok kanan atas (`border-radius: 0`)

**Dipilih**
- Opacity `1`, border `3px` warna token
- Background gradient saturasi tinggi
- Ring luar + shadow berwarna
- Kotak centang solid (hijau/biru/kuning sesuai opsi) — **bukan lingkaran**

Mapping contoh:
- Tunai / BCA / Kiloan → hijau
- Non Tunai / QRIS / Satuan → biru
- Saldo / BRI / Bidang / Keranjang → kuning
- Volume / Total / Hapus → merah

### Layout offcanvas lebar

```css
--bs-offcanvas-width: min(820px, 100vw);
```

Desktop: **2 kolom**. Mobile (`≤720px` / `≤639px`): 1 kolom.

---

## 6. Checklist sebelum merge UI

- [ ] Teks tajam (`#0f172a` / weight ≥ 800), bukan abu soft
- [ ] Ada minimal 2–3 warna token (bukan monokrom)
- [ ] Panel pakai border 2px + tint berwarna (untuk kelompok konten)
- [ ] Input/select tunggal **tanpa** card pembungkus ber-border
- [ ] Tidak ada double border bertumpuk (card + input, form-control + selectize)
- [ ] **Semua** elemen `border-radius: 0` (panel, tombol, input, badge, icon, radio mark) — tidak ada round/pill/lingkaran
- [ ] Tidak memakai class Bootstrap `rounded` / `rounded-*`
- [ ] Tombol primary hijau / info biru / warn kuning / danger merah
- [ ] Radio/option terpilih sangat jelas vs yang tidak
- [ ] Header offcanvas memakai gradient multiwarna (jika offcanvas)
- [ ] Konsisten dengan Order / Pembayaran yang sudah ada

---

## 7. Anti-pola

- Soft muted labels `#5a6a7c` sebagai teks utama
- Border 1px tipis + shadow lembut generik saja
- Selected radio hanya beda ring tipis (sulit dibedakan)
- `form-control` Bootstrap + class border kustom + selectize
- Tema ungu/indigo default, cream terracotta, atau dark glow
- Card di dalam card tanpa alasan (border ganda)
- **Input/select tunggal dibungkus card** → dua border berdekatan berkeliling — dilarang
- Field wrapper ber-border di sekitar kontrol yang sudah ber-border
- **Sudut membulat / round / pill / `border-radius: 50%`** — dilarang di seluruh UI tema
- Class Bootstrap `rounded`, `rounded-pill`, `rounded-3`, dll. pada elemen bertema

---

## 8. Cara memakai di kode baru

1. Baca file ini dulu.
2. Salin pola CSS dari `#ord-root` atau `#offcanvasPayment` (bukan bootstrap default).
3. Pakai nama variabel lokal konsisten, contoh:

```css
--ui-ink: #0f172a;
--ui-blue: #2563eb;
--ui-green: #16a34a;
--ui-yellow: #f59e0b;
--ui-red: #dc2626;
--ui-radius: 0;
```

4. Prefiks scope per halaman (`#ord-root`, `#offcanvasPayment`, dll.) agar tidak bocor ke global.
5. Jika menambah pola baru yang bagus, **update file pedoman ini**.
