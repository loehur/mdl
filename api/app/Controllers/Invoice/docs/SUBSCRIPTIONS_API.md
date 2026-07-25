# Invoice Subscriptions API

Dokumentasi endpoint status langganan untuk aplikasi multi-tenant.

Base URL production: `https://api.nalju.com`  
Base URL local: `http://localhost/mdl/api`

---

## Autentikasi

Semua request wajib menyertakan API key yang sama dengan `Env::INVOICE_API_KEY`.

| Cara | Nilai |
|------|--------|
| Header (disarankan) | `X-Invoice-Api-Key: YOUR_KEY` |
| Query (alternatif) | `?api_key=YOUR_KEY` |

Tanpa key yang valid → HTTP `401`.

---

## GET Status Langganan

Cek apakah layanan untuk `subscription_id` boleh dilanjutkan.

```
GET /Invoice/Subscriptions/status?subscription_id={subscription_id}
```

### Parameter

| Nama | Lokasi | Wajib | Keterangan |
|------|--------|-------|------------|
| `subscription_id` | query | ya | ID langganan (contoh `sub_a1b2c3d4e5f6`) |
| `X-Invoice-Api-Key` | header | ya* | API key (`Env::INVOICE_API_KEY`) |
| `api_key` | query | * | Alternatif jika header tidak dipakai |

### Contoh request

```bash
curl -s -H "X-Invoice-Api-Key: YOUR_KEY" \
  "https://api.nalju.com/Invoice/Subscriptions/status?subscription_id=sub_a1b2c3d4e5f6"
```

---

## Envelope response

Semua response sukses/error memakai format JSON standar API:

```json
{
  "status": true,
  "message": "Subscription status",
  "data": { }
}
```

| Field | Tipe | Keterangan |
|-------|------|------------|
| `status` | boolean | `true` = request berhasil diproses (bukan status langganan) |
| `message` | string | Pesan singkat |
| `data` | object \| null | Payload status langganan |

> Catatan: field root `status` (boolean) **berbeda** dengan `data.status` (enum string langganan).

---

## Field `data`

| Field | Tipe | Keterangan |
|-------|------|------------|
| `subscription_id` | string | ID yang diminta / ditemukan |
| `ok` | boolean | Ringkas: langganan “aman” atau tidak |
| `status` | string | Enum: lihat tabel di bawah |
| `service_allowed` | boolean | Rekomendasi: boleh lanjutkan layanan? |
| `recurring_active` | boolean | Jadwal tagihan berulang masih aktif (`is_active = 1`) |
| `period` | string \| null | `monthly` \| `yearly` \| `null` |
| `next_issue_date` | string \| null | Tanggal invoice berikutnya (YYYY-MM-DD) |
| `invoice` | object \| null | Invoice terkait (lihat di bawah) |

### Enum `data.status`

| Nilai | Kondisi | `ok` | `service_allowed` |
|-------|---------|------|-------------------|
| `active` | Tidak ada invoice unpaid/pending terbuka (belum ada invoice, atau terakhir sudah paid) | `true` | `true` |
| `grace` | Ada unpaid/pending, belum lewat `due_date` (atau tanpa due) | `true` | `true` |
| `overdue` | Ada unpaid/pending dengan `due_date` &lt; hari ini | `false` | `false` |
| `inactive` | Jadwal berulang nonaktif (`is_active = 0`), dan tidak overdue | `false` | `false` |
| `not_found` | `subscription_id` tidak ditemukan | `false` | `false` |

**Prioritas evaluasi:** `not_found` → `overdue` → `inactive` → `grace` → `active`.

### Object `data.invoice`

`null` jika belum ada invoice relevan.

| Field | Tipe | Keterangan |
|-------|------|------------|
| `number` | string | Nomor invoice |
| `issue_date` | string | Tanggal invoice (YYYY-MM-DD) |
| `due_date` | string \| null | Jatuh tempo |
| `payment_status` | string | `unpaid` \| `pending` \| `paid` |
| `total` | number | Total nominal |
| `days_until_due` | number \| null | Sisa hari ke due (negatif = terlambat). `null` jika paid / tidak ada due |
| `public_url` | string | Link bayar publik |

---

## Contoh response per status

### 1. Active — belum ada invoice

```json
{
  "status": true,
  "message": "Subscription status",
  "data": {
    "subscription_id": "sub_a1b2c3d4e5f6",
    "ok": true,
    "status": "active",
    "service_allowed": true,
    "recurring_active": true,
    "period": "monthly",
    "next_issue_date": "2026-08-25",
    "invoice": null
  }
}
```

### 2. Active — invoice terakhir sudah lunas

```json
{
  "status": true,
  "message": "Subscription status",
  "data": {
    "subscription_id": "sub_a1b2c3d4e5f6",
    "ok": true,
    "status": "active",
    "service_allowed": true,
    "recurring_active": true,
    "period": "monthly",
    "next_issue_date": "2026-08-25",
    "invoice": {
      "number": "INV-202607-0003",
      "issue_date": "2026-07-25",
      "due_date": "2026-08-01",
      "payment_status": "paid",
      "total": 220000,
      "days_until_due": null,
      "public_url": "https://invoice.nalju.com/#/i/a4846aca6f5cf63b985523ced8c324d7"
    }
  }
}
```

### 3. Grace — unpaid/pending, masih dalam masa tenggang

```json
{
  "status": true,
  "message": "Subscription status",
  "data": {
    "subscription_id": "sub_a1b2c3d4e5f6",
    "ok": true,
    "status": "grace",
    "service_allowed": true,
    "recurring_active": true,
    "period": "monthly",
    "next_issue_date": "2026-08-25",
    "invoice": {
      "number": "INV-202607-0003",
      "issue_date": "2026-07-25",
      "due_date": "2026-08-01",
      "payment_status": "unpaid",
      "total": 220000,
      "days_until_due": 7,
      "public_url": "https://invoice.nalju.com/#/i/a4846aca6f5cf63b985523ced8c324d7"
    }
  }
}
```

### 4. Overdue — sudah lewat jatuh tempo

```json
{
  "status": true,
  "message": "Subscription status",
  "data": {
    "subscription_id": "sub_a1b2c3d4e5f6",
    "ok": false,
    "status": "overdue",
    "service_allowed": false,
    "recurring_active": true,
    "period": "monthly",
    "next_issue_date": "2026-08-25",
    "invoice": {
      "number": "INV-202607-0003",
      "issue_date": "2026-07-01",
      "due_date": "2026-07-08",
      "payment_status": "unpaid",
      "total": 220000,
      "days_until_due": -10,
      "public_url": "https://invoice.nalju.com/#/i/a4846aca6f5cf63b985523ced8c324d7"
    }
  }
}
```

### 5. Inactive — jadwal berulang dimatikan

```json
{
  "status": true,
  "message": "Subscription status",
  "data": {
    "subscription_id": "sub_a1b2c3d4e5f6",
    "ok": false,
    "status": "inactive",
    "service_allowed": false,
    "recurring_active": false,
    "period": "monthly",
    "next_issue_date": "2026-08-25",
    "invoice": null
  }
}
```

### 6. Not found

```json
{
  "status": true,
  "message": "Subscription status",
  "data": {
    "subscription_id": "sub_xxx",
    "ok": false,
    "status": "not_found",
    "service_allowed": false,
    "recurring_active": false,
    "period": null,
    "next_issue_date": null,
    "invoice": null
  }
}
```

---

## Error HTTP

### 401 Unauthorized

```json
{
  "status": false,
  "message": "Unauthorized",
  "data": null
}
```

### 400 Bad Request — `subscription_id` kosong

```json
{
  "status": false,
  "message": "subscription_id wajib diisi",
  "data": null
}
```

### 500 Server Error

```json
{
  "status": false,
  "message": "Gagal memuat status: ...",
  "data": null
}
```

---

## Panduan di app multi-tenant

```text
if (!data.service_allowed) {
  // tahan / hentikan layanan
} else if (data.status === "grace") {
  // lanjutkan + tampilkan peringatan tagihan
} else {
  // active — lanjutkan normal
}
```

Atau cukup:

```text
lanjutkan_layanan = data.service_allowed === true
```

---

## Asal `subscription_id`

- Diisi manual saat membuat/mengedit invoice dengan **Tagihan berulang**, atau
- Dikosongkan → sistem generate otomatis (`sub_` + hex 24 karakter)
- Tersimpan di tabel `recurring_bills.subscription_id`
- Ditampilkan di detail invoice jika berulang aktif

---

## Konfigurasi server

Di `api/app/Config/Env.php`:

```php
const INVOICE_API_KEY = 'ganti-dengan-key-rahasia';
```

Key yang sama dipakai oleh app tenant di header `X-Invoice-Api-Key`.
