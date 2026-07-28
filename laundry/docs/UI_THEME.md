# MDL UI Theme — Pedoman Tampilan

**Sumber kebenaran** untuk style Operasi / Offcanvas / form laundry.  
Setiap pembuatan atau perubahan tampilan **wajib merujuk file ini**.

Referensi implementasi yang sudah sesuai tema:
- Offcanvas Order → `laundry/app/Views/penjualan/penjualan_main.php` (`#ord-root`)
- Offcanvas Pembayaran → `laundry/app/Views/operasi/partials/modals.php` (`#offcanvasPayment`)
- Modal Operasi → `laundry/app/Views/operasi/partials/modals.php` (`.op-modal` + `window.OpModal`)
- Top nav + Sidebar → `laundry/app/Views/layout.php` (`.mdl-topbar`, `.main-sidebar`)
- Antrian view → `laundry/app/Views/antrian/view_content.php` + `form.php` (warna token; layout/spacing dipertahankan)
- Login → `laundry/app/Views/login.php`
- Absen → `laundry/app/Views/Absen/form.php` (`#absen-root`) + `content.php`
- Operan → `laundry/app/Views/operan/form.php` (`#operan-root`) + `content.php`

---

## 1. Prinsip

1. **Tajam, bukan soft** — teks gelap, font tebal, kontras tinggi.
2. **Penuh warna** — biru, hijau, kuning, merah dipakai dengan jelas (bukan pastel pudar).
3. **Satu komposisi** — panel berwarna dengan **border tipis 1px**, judul + ikon solid.
4. **State jelas** — opsi terpilih vs tidak harus langsung terbaca (centang, border sedikit lebih tegas, opsi lain pudar).
5. **Satu border saja** — jangan double border bertumpuk (input dalam card, form-control + selectize, dll.).
6. **Semua sudut runcing** — **tidak ada round** di seluruh style. Panel, modal, tombol, input, select, badge, icon box, radio mark, chip, FAB: semuanya kotak dengan `border-radius: 0`. Dilarang `rounded`, `rounded-*`, `pill`, `50%`, radius `> 0`.
7. **Border tipis** — default **`1px`**. Dilarang border `2px`/`3px`/`1.5px` sebagai default panel/input/tombol. Focus ring: `0 0 0 2px …`. Opsi terpilih boleh `border-width: 2px` (hanya 1 langkah lebih tegas dari default).
8. **Jangan default AI look** — hindari ungu/indigo generik, cream terracotta, tipografi soft abu-abu.

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

## 4. Sudut & border — wajib siku + tipis

```css
--ui-radius: 0;
--ui-border: 1px;

.panel, .modal-content, .btn, button, input, select, textarea,
.selectize-input, .badge, .chip, .icon-box, .radio-mark {
  border-radius: 0 !important;
  border-width: 1px; /* default */
}
```

- **Dilarang**: `border-radius` selain `0`, class Bootstrap `rounded` / `rounded-*` / `rounded-pill`, lingkaran `50%` / `999px`.
- **Dilarang**: border default `2px` / `3px` / `1.5px` pada panel, input, tombol, chip.
- Marker radio / centang / ikon: **kotak**, bukan lingkaran.
- **Pengecualian**: indikator loading/spinner boleh `border-radius: 50%` agar tidak terlihat seperti kotak error.
- Focus: `box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22)` (bukan 3px).
- Offcanvas: `.offcanvas { border-radius: 0; }`.

---

## 5. Komponen standar

### Panel / card

- Border **`1px`**, radius **`0`**, padding `14px`
- Shadow: `0 10px 24px rgba(15, 23, 42, 0.08)`
- Judul: ikon `30×30`, radius **`0`**, warna solid putih di atas warna token

### Modal / dialog / offcanvas panel

- **Sudut runcing** — `border-radius: 0` pada `.modal-content`, panel dialog, tombol, input di dalamnya
- Shadow tetap boleh keras: mis. `0 24px 48px rgba(15, 23, 42, 0.3)`
- Header modal: gradient token (biru/hijau/kuning/merah) + teks putih tebal, sama semangat offcanvas
- **Operasi:** jangan pakai Bootstrap Modal. Pakai `.op-modal` + `window.OpModal.open/close` (lihat `modals.php` / `view_load.js`)

```html
<div class="op-modal" id="…" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel" role="dialog" aria-modal="true">
    <div class="op-modal__head op-modal__head--blue|green|yellow|red">…</div>
    <div class="op-modal__body">…</div>
    <div class="op-modal__foot">…</div>
  </div>
</div>
```

```js
OpModal.open("modalAlert", { static: true }); // static = backdrop/Escape tidak menutup
OpModal.close("modalAlert");
OpModal.closeAll();
```

- Trigger UI: `data-op-target="#idModal"` (bukan `data-bs-toggle` / `data-bs-target`)
- Z-index `.op-modal`: `5200` (di atas offcanvas Payment/Order ~1100)
- Backdrop milik tiap modal — **jangan** membuat `.modal-backdrop` Bootstrap

```css
.modal-content,
.ord-plg-modal__panel,
.ord-order-modal__panel,
.op-modal__panel,
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

Radius tombol: **`0`**. Border (jika ada): **`1px`**. Weight: `900`.

### Input / Selectize

#### Aturan wajib — SATU BORDER SAJA

Selectize membungkus `<select>` menjadi `.selectize-control` + `.selectize-input`.  
**Hanya `.selectize-input` yang boleh punya border.** Semua lapisan lain harus `border: 0`.

| Lapisan | Border? |
|---------|---------|
| `<select class="tize">` / `.selectized` | **Tidak** (`border: 0`) |
| `.selectize-control` | **Tidak** (`border: 0`) |
| `.selectize-input` | **Ya** — `1px solid #94a3b8` |
| `.selectize-input:after` (panah) | **Tidak** (`border: 0`) |
| Card / panel / field-wrapper di sekitar select tunggal | **Tidak** |

```text
❌ SALAH — double / triple border
┌─ .selectize-control (border) ─────┐
│ ┌─ .selectize-input (border) ───┐ │
│ │ nilai                         │ │
│ └───────────────────────────────┘ │
└───────────────────────────────────┘
+ class form-control / op-input / pay-input pada <select>

✅ BENAR — satu border
.selectize-control  (border:0, transparan)
  └─ .selectize-input  ← satu-satunya 1px border
       nilai
```

#### HTML

```html
<!-- Selectize: JANGAN form-control / op-input / pay-input -->
<label class="…">Penerima</label>
<select name="…" class="tize" style="width:100%;" required>…</select>

<!-- Select native (tanpa selectize): boleh satu class input -->
<label class="…">Durasi</label>
<select id="…" class="op-input">…</select>
```

**Dilarang pada select yang memakai `.tize` / Selectize:**
- `form-control`, `form-control-sm`
- `op-input`, `pay-input`, atau class lain yang menambah `border`
- membungkus select tunggal dengan card / panel / kotak ber-border

#### CSS wajib (salin ke scope halaman, ganti `#scope`)

```css
/* Satu border selectize — wajib di setiap scope yang pakai .tize */
#scope select.tize,
#scope select.selectized {
  border: 0 !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0 !important;
}
#scope .selectize-control,
#scope .selectize-control.single {
  border: 0 !important;
  box-shadow: none !important;
  background: transparent !important;
  margin: 0;
}
#scope .selectize-control.single .selectize-input {
  border: 1px solid #94a3b8 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  background: #fff !important;
  font-weight: 800;
}
#scope .selectize-control.single .selectize-input.focus {
  border-color: #2563eb !important;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
}
#scope .selectize-control.single .selectize-input:after {
  border: 0 !important;
}
```

Referensi yang sudah benar:
- Pembayaran → `#offcanvasPayment` + `#karyawanBill` di `operasi/partials/modals.php`
- Modal Operasi → `.op-modal` selectize rules di file yang sama
- Filter Pelanggan → `.operasi-filter` di `operasi/form_proses.php`

Input native (bukan selectize): border **`1px solid #94a3b8`**, radius **`0`**, weight `800`.  
Focus: border biru + ring `0 0 0 2px rgba(37, 99, 235, 0.22)`.  
Readonly khusus: background `#fef3c7`, border kuning.

### Input / select tunggal — JANGAN dibungkus card

Jika field hanya **satu** input atau select yang sudah punya border sendiri:

- **Jangan** bungkus lagi dengan card / panel / kotak ber-border.
- **Jangan** menumpuk dua kerangka berdekatan.
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
- Kotak kosong di pojok kanan atas (`border-radius: 0`, border `1px`)

**Dipilih**
- Opacity `1`, border **`2px`** warna token (hanya sedikit lebih tegas dari default `1px`)
- Background gradient saturasi tinggi
- Ring luar `0 0 0 2px` + shadow berwarna
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
- [ ] Panel pakai border **1px** + tint berwarna (untuk kelompok konten)
- [ ] Input/select tunggal **tanpa** card pembungkus ber-border
- [ ] Select `.tize`: **tanpa** `form-control` / `op-input` / `pay-input`
- [ ] Selectize: border **hanya** di `.selectize-input`; `.selectize-control` + `<select>` = `border: 0`
- [ ] Tidak ada double border bertumpuk (card + input, form-control + selectize, selectize-control + selectize-input)
- [ ] **Semua** elemen `border-radius: 0` — tidak ada round/pill/lingkaran
- [ ] Tidak memakai class Bootstrap `rounded` / `rounded-*`
- [ ] Border default **1px**; focus ring **2px**; selected radio max **2px**
- [ ] Tombol primary hijau / info biru / warn kuning / danger merah
- [ ] Radio/option terpilih sangat jelas vs yang tidak
- [ ] Header offcanvas memakai gradient multiwarna (jika offcanvas)
- [ ] Modal Operasi memakai `.op-modal` / `OpModal` (bukan Bootstrap Modal + backdrop)
- [ ] Konsisten dengan Order / Pembayaran yang sudah ada

---

## 7. Anti-pola

- Soft muted labels `#5a6a7c` sebagai teks utama
- Border tebal `2px`/`3px` sebagai default panel/input (kecuali selected radio = `2px`)
- Shadow lembut generik saja tanpa warna token
- Selected radio hanya beda ring tipis (sulit dibedakan)
- `form-control` Bootstrap + class border kustom + selectize
- **Select `.tize` sekaligus `form-control` / `op-input` / `pay-input`** — dilarang (double border)
- **Border di `.selectize-control` sekaligus `.selectize-input`** — dilarang; hanya input
- Tema ungu/indigo default, cream terracotta, atau dark glow
- Card di dalam card tanpa alasan (border ganda)
- **Input/select tunggal dibungkus card** — dilarang
- Field wrapper ber-border di sekitar kontrol yang sudah ber-border
- **Sudut membulat / round / pill / `border-radius: 50%`** — dilarang
- Class Bootstrap `rounded`, `rounded-pill`, `rounded-3`, dll. pada elemen bertema

---

## 8. Cara memakai di kode baru

1. Baca file ini dulu.
2. Salin pola CSS dari `#ord-root`, `#offcanvasPayment`, atau `.op-modal` (bukan bootstrap default).
   Untuk selectize: salin blok **“CSS wajib (satu border)”** di section Input/Selectize — jangan improvise.
3. Pakai nama variabel lokal konsisten, contoh:

```css
--ui-ink: #0f172a;
--ui-blue: #2563eb;
--ui-green: #16a34a;
--ui-yellow: #f59e0b;
--ui-red: #dc2626;
--ui-radius: 0;
--ui-border: 1px;
```

4. Prefiks scope per halaman (`#ord-root`, `#offcanvasPayment`, dll.) agar tidak bocor ke global.
5. Jika menambah pola baru yang bagus, **update file pedoman ini**.
