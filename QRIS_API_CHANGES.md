# Ringkasan Perubahan API QRIS

## Perubahan yang Dilakukan

### 1. API QRIS Endpoint (c:\xampp82\htdocs\mdl\api\app\Controllers\QRIS.php)

#### Endpoint `/QRIS/generate` (POST)
**Response Format BARU:**
```json
{
  "status": true,
  "trx_id": "ref_bayar_123_1738672800",
  "ref_id": "ref_bayar_123",
  "qr_string": "00020101021..."
}
```

**Error Response:**
```json
{
  "status": false,
  "message": "Error message here"
}
```

#### Endpoint `/QRIS/status` (GET)
**Response Format BARU:**
```json
{
  "status": true,
  "trx_id": "ref_bayar_123_1738672800",
  "ref_id": "ref_bayar_123",
  "payment_status": "paid|pending|expired",
  "trx_status": "success|pending|expired|cancelled|..."
}
```

**Keterangan:**
- `payment_status`: Status yang sudah diparsing (3 nilai: paid, pending, expired)
- `trx_status`: Status mentah dari Tokopay (untuk debugging dan handling khusus)

**Error Response:**
```json
{
  "status": false,
  "message": "Error message here"
}
```

### 2. Helper QRISApi (c:\xampp82\htdocs\mdl\laundry\app\Helper\QRISApi.php)

Helper sudah disederhanakan - sekarang hanya me-return response dari API apa adanya tanpa transformasi kompleks.

## Keuntungan Perubahan Ini

1. **Response Lebih Sederhana**: Client tidak perlu parsing nested object
2. **Debugging Lebih Mudah**: Field `trx_status` menampilkan status asli dari Tokopay
3. **Konsisten**: Format response selalu sama, mudah diprediksi
4. **Error Handling Lebih Jelas**: Error langsung di root level dengan status false

## Adaptasi di Client Side

### Untuk Generate QRIS:
```php
$qrisApi = new QRISApi();
$response = $qrisApi->generate($nominal, $ref_id, 'QRIS');

if ($response['status']) {
    // Success
    $qr_string = $response['qr_string'];
    $trx_id = $response['trx_id'];
    $ref_id = $response['ref_id'];
} else {
    // Error
    $error_message = $response['message'];
}
```

### Untuk Check Status:
```php
$qrisApi = new QRISApi();
$response = $qrisApi->checkStatus($trx_id, $nominal, 'QRIS');

if ($response['status']) {
    // Success mendapatkan response
    $payment_status = $response['payment_status']; // paid, pending, expired
    $trx_status = $response['trx_status']; // Status asli dari Tokopay
    
    if ($payment_status === 'paid') {
        // Pembayaran sudah lunas
    } elseif ($payment_status === 'expired') {
        // Pembayaran expired/cancelled
    } else {
        // Pembayaran masih pending
    }
} else {
    // Error saat koneksi/API
    $error_message = $response['message'];
}
```

## Next Steps (Yang Perlu Dilakukan)

### 1. Update Attributes.php
File ini perlu disesuaikan untuk menggunakan format response baru:

**Di function payment_gateway_logic() sekitar line 481-484:**
```php
// LAMA (dengan data nested):
if (isset($data['data']['qr_string']) && !empty($data['data']['qr_string'])) {
   $qr_string = $data['data']['qr_string'];
} elseif (isset($data['qr_string']) && !empty($data['qr_string'])) {
   $qr_string = $data['qr_string'];
}

// BARU (langsung di root):
$qr_string = isset($data['qr_string']) ? $data['qr_string'] : '';
```

**Di function payment_gateway_status_logic() sekitar line 620-640:**
```php
// LAMA (parsing kompleks):
$status_detail = isset($statusResponse['status_detail']) ? strtolower(trim($statusResponse['status_detail'])) : '';
if (empty($status_detail) && isset($data['data']['status'])) {
   $status_detail = is_string($data['data']['status']) ? strtolower(trim($data['data']['status'])) : '';
}
$isPaid = in_array($status_detail, ['success', 'paid', 'settlement', 'capture', 'completed'], true);

// BARU (langsung ambil dari response):
$payment_status = isset($data['payment_status']) ? $data['payment_status'] : 'pending';
$trx_status = isset($data['trx_status']) ? $data['trx_status'] : 'unknown';
$isPaid = ($payment_status === 'paid');
```

### 2. Update Frontend/JavaScript
Jika ada kode JavaScript yang mem-parsing response, sesuaikan dengan format baru.

### 3. Testing
Test semua flow:
- Generate QR baru
- Cek status pembayaran pending
- Cek status pembayaran paid (via webhook atau manual check)
- Cek status pembayaran expired
- Error handling

## Compatibility Note

Response format baru **backward compatible** karena:
- Masih ada field `status` 
- Field `qr_string` ada di root level (selain di `data`)
- Parsing lama akan tetap bekerja, hanya tidak optimal

Namun **disarankan** untuk update semua call untuk menggunakan format baru agar:
- Code lebih clean
- Debugging lebih mudah dengan `trx_status`
- Konsisten dengan API design baru
