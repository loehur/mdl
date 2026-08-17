# MDL UI Theme — Pedoman Tampilan

**Sumber kebenaran** untuk style Operasi / Offcanvas / form laundry.  
Setiap pembuatan atau perubahan tampilan **wajib merujuk file ini**.

Referensi implementasi yang sudah sesuai tema:
- Offcanvas Order → `laundry/app/Views/penjualan/penjualan_main.php` (`#ord-root`)
- Offcanvas Pembayaran → `laundry/app/Views/operasi/partials/modals.php` (`#offcanvasPayment`)
- Modal Operasi → `laundry/app/Views/operasi/partials/modals.php` (`.op-modal` + `window.OpModal`)
- Top nav + Sidebar → `laundry/app/Views/layout.php` (`.mdl-topbar`, `.main-sidebar`)
- Toast → `laundry/app/Views/layout.php` (`.mdl-toast` + `window.MdlToast`)
- Offcanvas Notifikasi (lonceng) → `laundry/app/Views/layout.php` (`#offcanvasNotif` + `#btnNotifBell`); data via `Estimasi` controller (`db(100)` = `mdl_main`); termasuk section **Permintaan Pelanggan** + modal chat `#mdlChatHistoryModal` + konfirmasi `#mdlChatConfirmModal` / `#mdlPermintaanTolakModal` + panel hapus inline `.mdl-notif-hapus-panel` (tanpa `alert`/`confirm`/`prompt` browser)
- Riwayat chat WA (shared) → Helper `WaChatHistory` + JS `in_assets/js/mdl-wa-chat.js` (`window.MdlWaChat`) + CSS `.mdl-wa-chat` di `layout.php` (Delivery + Notifikasi permintaan)
- Antrian view → `laundry/app/Views/antrian/view_content.php` + `form.php` (warna token; layout/spacing dipertahankan)
- Login → `laundry/app/Views/login.php`
- Admin Approval → `laundry/app/Views/admin_approval/admin_approval_main.php` (`#aa-root`) + tab AJAX (Setoran / NonTunai / HapusOrder / HapusDeposit / Pengeluaran)
- Absen → `laundry/app/Views/Absen/form.php` (`#absen-root`) + `content.php`
- Operan → `laundry/app/Views/operan/form.php` (`#operan-root`) + `content.php`
- Tiket → `laundry/app/Views/tiket/form.php` (`#tiket-root`) + `view_load.php`
- Data Pelanggan → `laundry/app/Views/pelanggan/index.php` (`#plg-root`) + `form_tambah.php`

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
9. **Header satu hue** — top nav, offcanvas header, modal head: **satu warna** (deep → light). **Dilarang** gradient pelangi 3+ warna (biru+hijau+kuning, merah+oranye, kuning+merah, dll.).
10. **Dilarang dialog bawaan browser** — **jangan pernah** pakai `alert()`, `confirm()`, atau `prompt()` (dan `window.alert` / `window.confirm` / `window.prompt`). Feedback singkat → **toast** (`MdlToast`). Butuh keputusan / input teks / Ya–Batal → **modal** bertema (`.op-modal`, `.mdl-chat-modal`, dll.).

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

### Header (top nav / offcanvas / modal) — satu hue

**Wajib** gradient 2 stop dalam **satu keluarga warna**. Variasikan hue antar konteks (biru default, hijau sukses, kuning warning, merah bahaya), jangan campur dalam satu bar.

```css
/* Default / info (biru) — pakai ini untuk topnav kasir, offcanvas, modal head default */
background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
color: #fff;
font-weight: 900;
letter-spacing: -0.02em;
text-shadow: 0 1px 0 rgba(0,0,0,.18);

/* Varian lain (pilih satu, jangan campur) */
/* hijau  */ background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
/* kuning */ background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
/* merah  */ background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
/* cyan   */ background: linear-gradient(105deg, #0e7490 0%, #0891b2 100%);
```

### Top nav (chrome) — beda privilege (warna soft)

Body class di `layout.php`: `mode-priv-100` / `mode-priv-12`. Mode Training (`mode-training`) tetap override.  
Gradient **satu hue**, nada **soft** (lebih terang / kurang saturated), tetap teks putih tebal.

| Privilege | Body class | Gradient soft |
|-----------|------------|---------------|
| Kasir / default | — | biru `#5b8def → #7aa7f5` |
| 100 (admin) | `mode-priv-100` | merah `#e86a6a → #f08b8b` |
| 12 (kurir) | `mode-priv-12` | cyan `#2eb8cc → #5ccfde` |
| Training | `mode-training` | kuning `#e8b03a → #f0c65c` |

**Notifikasi pending:** class `.has-notif` pada `.mdl-topbar` → **border-bottom merah pekat tebal 4px** (`#7f1d1d` ↔ `#dc2626`) **berkedip**; tanpa notif → border tipis biasa (bukan tebal). Badge lonceng tetap.

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
- Header modal: gradient **satu hue** (biru/hijau/kuning/merah) + teks putih tebal — sama aturan offcanvas / topnav
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

### Badge / chip (label status — bukan tombol)

**Wajib bedakan** dari tombol. Label meta (cabang, jenis, status, count) **bukan** aksi klik → pakai **tint soft cerah**, bukan fill solid putih-di-atas-warna seperti tombol.

| Varian | Background | Border | Teks |
|--------|------------|--------|------|
| Neutral | `#f1f5f9` | `#cbd5e1` | `#0f172a` |
| Blue | `#eff6ff` | `#93c5fd` | `#1d4ed8` |
| Green | `#f0fdf4` | `#86efac` | `#15803d` |
| Yellow | `#fffbeb` | `#fcd34d` | `#b45309` |
| Red | `#fef2f2` | `#fca5a5` | `#b91c1c` |

Aturan:
- Badge/chip: soft tint + border token + teks berwarna gelap/token — **bukan** `background: #2563eb; color: #fff`.
- Tombol: fill solid/gradient + teks kontras (putih / `#111`) — hanya untuk elemen `button` / aksi klik.
- **Dilarang** membuat label status tampak seperti tombol (solid block penuh warna).
- Radius tetap `0`, border `1px`, weight `800–900`.

Referensi: Tiket `#tiket-root` `.tk-badge` / `.tk-chip` di `tiket/view_load.php`.

### Timeline detail (Operasi)

Modal detail nota (`#modalNotaDetail`, kelas `.ndt-*`):
- Soft badge status (Lunas / Sisa), bukan tombol solid.
- Dot timeline berwarna per jenis: jemput kuning, antar biru, ambil/layanan hijau; pending abu.
- Radius `0`, border `1px`, teks tajam weight ≥ 750.
- Implementasi: `Operasi/nota_detail` + `operasi/partials/modals.php`.

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

#### Selectize vs select native — kapan pakai mana

| Situasi | Pakai | Alasan |
|---------|--------|--------|
| Opsi sedikit & tetap (mis. Jenis: 4 item) | **Select native** + class input (`tk-input` / `op-input`) | Lebih rapi, tanpa overhead selectize |
| Opsi banyak / perlu cari (Karyawan, Pelanggan, Item) | **Selectize** `class="tize"` | Searchable, pola standar app |

#### Select Karyawan (pola wajib)

Ikuti pola Order / Absen. Jangan inventaris ulang.

**HTML** (optgroup cabang aktif + cabang lain):

```html
<label>Karyawan</label>
<select name="…" class="tize" style="width: 100%;" required>
  <option value="" selected disabled></option>
  <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
    <?php foreach ($this->user as $a) { ?>
      <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
    <?php } ?>
  </optgroup>
  <?php if (count($this->userCabang) > 0) { ?>
    <optgroup label="----- Cabang Lain -----">
      <?php foreach ($this->userCabang as $a) { ?>
        <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
      <?php } ?>
    </optgroup>
  <?php } ?>
</select>
```

- Sumber data: `$this->user` (cabang aktif) + `$this->userCabang` (cabang lain).
- Label opsi: `{id_user}-{NAMA}` uppercase.
- `value` biasanya `id_user` (Order/Absen). Jika kolom DB menyimpan nama teks, `value` boleh `nama_user` — tetap pakai markup optgroup yang sama.

**Init JS — wajib sederhana:**

```js
// ✅ BENAR — pola penjualan / absen / operasi
$(".scope .tize").selectize();
// atau per elemen:
$("#tiketKaryawan").selectize();
```

```js
// ❌ SALAH — jangan pakai dropdownParent ke modal / body
$("#tiketKaryawan").selectize({
  dropdownParent: $("#modalTiketForm") // dropdown loncat ke pojok kiri layar
});
$("#tiketKaryawan").selectize({
  dropdownParent: "body" // sama berisikonya di modal fixed/flex
});
```

**Kenapa `dropdownParent` dilarang di modal MDL:**  
Modal `.op-modal` memakai `position: fixed` + flex full-viewport. Selectize menghitung posisi dropdown relatif ke parent itu → daftar opsi muncul di **pojok kiri atas layar**, terlepas dari field. Biarkan selectize menempel ke `.selectize-control` (default).

**Script:** muat `<?= URL::EX_ASSETS ?>js/selectize.min.js` di halaman yang memakai `.tize` (jika belum ada di layout).

**Di dalam modal:** pastikan panel/body modal tidak `overflow: hidden` yang memotong dropdown; `overflow: visible` pada panel modal jika dropdown terpotong. Z-index dropdown cukup `30` di dalam scope modal (jangan `5300` ke body kecuali ada alasan kuat).

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
  min-height: 42px;
  padding: 8px 12px !important;
}
#scope .selectize-control.single .selectize-input.focus {
  border-color: #2563eb !important;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
}
#scope .selectize-control.single .selectize-input:after {
  border: 0 !important;
}
#scope .selectize-dropdown {
  border: 1px solid #94a3b8 !important;
  border-radius: 0 !important;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.16) !important;
  z-index: 30 !important;
}
#scope .selectize-dropdown .option {
  font-weight: 700;
  color: #0f172a;
}
```

Referensi yang sudah benar:
- **Karyawan Order** → `#ord-root` + `$(".orderProses .tize").selectize()` di `penjualan/penjualan_main.php` (**acuan utama select karyawan**)
- Karyawan Absen → `#absen-root select.tize` di `Absen/form.php`
- Karyawan Tiket → `#tiket-root` di `tiket/form.php`
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

### Toast vs Modal (pilih yang tepat)

**Inti:** toast = info singkat non-blocking; modal = butuh perhatian / keputusan / aksi.

| Situasi | Pakai | Contoh |
|---------|--------|--------|
| Berhasil singkat (simpan, cetak terkirim, item masuk keranjang) | **Toast** `ok` | “Nota dikirim ke printer” |
| Validasi / error teknis 1–2 baris, user bisa lanjut kerja | **Toast** `warn` / `error` | “Lengkapi ID Outlet”, “Gagal menyimpan” |
| Info ringan / status sementara | **Toast** `info` | “Menunggu pembayaran…” |
| Konfirmasi destruktif / keputusan Ya–Batal | **Modal** | Hapus order, batalkan pembayaran |
| Form multi-field / alur langkah | **Modal** / offcanvas | Pilih karyawan + pack/hanger |
| Instruksi yang harus dibaca & ditindaklanjuti dulu | **Modal** | Print server offline → jalankan printer server |
| Error lingkungan yang menghentikan aksi (cetak, bridge) | **Modal** (`PrintServer.showAlert` / `OpModal`) | “Print server tidak aktif…” |
| Paragraf panjang / kebijakan / panduan | **Modal** | Syarat, panduan setup |

**Aturan cepat**
1. Bisa diabaikan sambil lanjut kerja → **toast**.
2. Harus baca / klik OK / pilih Ya–Batal sebelum lanjut → **modal**.
3. Satu kalimat status → **toast**. Multi kalimat + aksi perbaikan → **modal**.
4. **Dilarang keras** `alert()` / `confirm()` / `prompt()` bawaan browser — **tidak ada pengecualian** (termasuk fallback “kalau toast belum load”).
5. **Jangan** modal penuh hanya untuk “Berhasil” 1 kata — pakai toast.
6. **Jangan** toast untuk konfirmasi hapus / checkout / print-server-offline / aksi destruktif.
7. Butuh input teks (catatan hapus, alasan, dll.) → **modal bertema** + field, **bukan** `prompt()`.

**Notifikasi Permintaan (lonceng):** Penuhi / Tolak memakai modal konfirmasi (`.mdl-chat-modal`); **Hapus** memakai panel catatan **inline di kartu** (di dalam offcanvas, agar input bisa diketik — hindari modal di luar focus-trap). Validasi & hasil → `MdlToast`. Referensi: `#mdlChatConfirmModal`, `#mdlPermintaanTolakModal`, `.mdl-notif-hapus-panel` di `layout.php`.

#### Toast (feedback singkat)

Toast untuk **pesan singkat** — sukses, error, warning, info — **tanpa memaksa klik OK**.  
Jangan otomatis pakai modal/`alert()` untuk feedback sederhana.

| Situasi (ringkas) | Pakai |
|---------|--------|
| Berhasil simpan / tambah / hapus singkat | **Toast** `ok` / `success` |
| Validasi gagal, request gagal singkat | **Toast** `warn` / `error` |
| Info ringan | **Toast** `info` |

Aturan singkat:
- **1 kalimat**, max ~2 baris — bukan paragraf.
- Auto hilang ~2.5–4 detik (error boleh sedikit lebih lama).
- Boleh ditutup manual (klik / tombol X).
- **Jangan** stack 10 toast — ganti toast yang sama atau antri singkat (max 2–3).
- **Jangan** toast `rounded` / Bootstrap Toast default — ikut token tema (siku, border 1px, warna solid).

#### Modal (perhatian / keputusan / aksi)

- Konfirmasi aksi (hapus, checkout, ganti yang merusak data)
- Alur multi-field (pilih karyawan, isi pack, dll.)
- Instruksi setup / perbaikan yang harus ditindaklanjuti (contoh: **jalankan Print Server / Print Bridge**)
- Dialog OK yang memblokir sampai user paham (print offline)

Untuk error print server, pakai `PrintServer.showAlert(msg, "warning")` (atau `OpModal` `#modalAlert` di halaman Operasi) — **bukan** fallback `window.print()` / dialog browser.
#### Varian warna

| Type | Border / accent | Background | Teks | Ikon saran |
|------|-----------------|------------|------|------------|
| `ok` / `success` | `#86efac` / hijau | `#f0fdf4 → #fff` | `#0f172a` | `fa-check` |
| `warn` / `warning` | `#fcd34d` / kuning | `#fffbeb → #fff` | `#0f172a` | `fa-exclamation-triangle` |
| `error` / `danger` | `#fca5a5` / merah | `#fef2f2 → #fff` | `#0f172a` (+ aksen `#b91c1c` opsional) | `fa-times` |
| `info` | `#93c5fd` / biru | `#eff6ff → #fff` | `#0f172a` | `fa-info-circle` |

Posisi default: **bawah tengah** (mobile-friendly) atau bawah kanan desktop. `z-index` di atas konten (~`5400`), di bawah atau setara modal penting.

#### API standar

Global di layout laundry: `window.MdlToast`.

```js
MdlToast.show("Item ditambahkan ke keranjang", "ok");
MdlToast.show("Gagal menyimpan", "error");
MdlToast.show("Lengkapi ID Outlet", "warn");
MdlToast.show("Menunggu pembayaran…", "info");

// alias
MdlToast.ok("Berhasil");
MdlToast.warn("Perhatian");
MdlToast.error("Gagal");
MdlToast.info("Info");
```

Referensi: `laundry/app/Views/layout.php` (`.mdl-toast` + `window.MdlToast`).

Halaman khusus (portal J, dll.) boleh punya toast sendiri, tapi **visual harus mengikuti token** di atas — bukan Bootstrap `rounded` toast generik.

#### Anti-pola toast

- `alert()` untuk error validasi biasa
- Modal penuh hanya untuk “Berhasil” / “Gagal” 1 kalimat
- Toast hijau muda pastel tanpa border token
- Toast bulat / pill / shadow ungu
- Toast yang tidak hilang sendiri tanpa tombol tutup jelas

---

## 6. Checklist sebelum merge UI

- [ ] Teks tajam (`#0f172a` / weight ≥ 800), bukan abu soft
- [ ] Ada minimal 2–3 warna token (bukan monokrom)
- [ ] Panel pakai border **1px** + tint berwarna (untuk kelompok konten)
- [ ] Input/select tunggal **tanpa** card pembungkus ber-border
- [ ] Select `.tize`: **tanpa** `form-control` / `op-input` / `pay-input`
- [ ] Selectize: border **hanya** di `.selectize-input`; `.selectize-control` + `<select>` = `border: 0`
- [ ] Selectize init: **tanpa** `dropdownParent` (modal/body) — dropdown tidak loncat ke pojok kiri
- [ ] Select Karyawan: optgroup `$this->user` + `$this->userCabang` seperti Order (`penjualan_main.php`)
- [ ] Opsi sedikit/tetap memakai select **native**, bukan selectize
- [ ] Tidak ada double border bertumpuk (card + input, form-control + selectize, selectize-control + selectize-input)
- [ ] **Semua** elemen `border-radius: 0` — tidak ada round/pill/lingkaran
- [ ] Tidak memakai class Bootstrap `rounded` / `rounded-*`
- [ ] Border default **1px**; focus ring **2px**; selected radio max **2px**
- [ ] Tombol primary hijau / info biru / warn kuning / danger merah
- [ ] Badge/chip status pakai **tint soft** (bukan solid seperti tombol); hanya tombol yang fill solid
- [ ] Radio/option terpilih sangat jelas vs yang tidak
- [ ] Header (topnav / offcanvas / modal) gradient **satu hue** — bukan pelangi 3+ warna
- [ ] Modal Operasi memakai `.op-modal` / `OpModal` (bukan Bootstrap Modal + backdrop)
- [ ] Feedback singkat memakai **toast** (`MdlToast`), bukan `alert()` / modal OK-only
- [ ] **Tidak ada** `alert()` / `confirm()` / `prompt()` bawaan browser di kode baru atau yang diubah
- [ ] Print server offline / instruksi aksi memakai **modal** (`PrintServer.showAlert` / `OpModal`), bukan toast singkat & bukan `window.print`
- [ ] Konfirmasi destruktif / multi-step / input catatan tetap modal (bukan toast, bukan `prompt`)
- [ ] Cetak hanya via Print Server — tanpa fallback browser / bluetooth / serial langsung
---

## 7. Anti-pola

- Soft muted labels `#5a6a7c` sebagai teks utama
- **Badge/chip solid penuh warna seperti tombol** (mis. biru `#2563eb` + teks putih) untuk label status — dilarang; pakai tint soft
- Border tebal `2px`/`3px` sebagai default panel/input (kecuali selected radio = `2px`)
- Shadow lembut generik saja tanpa warna token
- Selected radio hanya beda ring tipis (sulit dibedakan)
- `form-control` Bootstrap + class border kustom + selectize
- **Select `.tize` sekaligus `form-control` / `op-input` / `pay-input`** — dilarang (double border)
- **Border di `.selectize-control` sekaligus `.selectize-input`** — dilarang; hanya input
- Tema ungu/indigo default, cream terracotta, atau dark glow
- **Header pelangi** (biru+hijau+kuning / merah+oranye / kuning+merah dalam satu bar) — dilarang; pakai satu hue
- Card di dalam card tanpa alasan (border ganda)
- **Input/select tunggal dibungkus card** — dilarang
- Field wrapper ber-border di sekitar kontrol yang sudah ber-border
- **Sudut membulat / round / pill / `border-radius: 50%`** — dilarang
- Class Bootstrap `rounded`, `rounded-pill`, `rounded-3`, dll. pada elemen bertema
- Modal/`alert()` untuk pesan sukses atau error 1 baris — **pakai toast**
- `alert()` / `confirm()` / `prompt()` / `window.alert` / `window.confirm` / `window.prompt` — **dilarang total**
- Toast untuk print-server-offline / instruksi “jalankan server dulu” — **pakai modal**
- Fallback cetak browser (`window.print` / `window.open` + print) saat print server mati — **dilarang**; tampilkan warning modal
- Bootstrap Toast default (rounded + shadow generik) tanpa token warna tema

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
