<?php
$qrisUrl = $data['qris_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
   <title>QRIS Pembayaran | Madinah Laundry</title>
   <link rel="icon" href="<?= URL::IN_ASSETS ?>icon/logo.png">
   <style>
      * { box-sizing: border-box; margin: 0; padding: 0; }
      body {
         font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
         min-height: 100vh;
         background: linear-gradient(135deg, #1a5f4a 0%, #0d3d32 50%, #0a2e26 100%);
         display: flex;
         flex-direction: column;
         align-items: center;
         justify-content: center;
         padding: 20px;
         color: #fff;
      }
      .qris-card {
         background: rgba(255,255,255,0.98);
         border-radius: 20px;
         padding: 28px;
         max-width: 340px;
         width: 100%;
         box-shadow: 0 20px 60px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
         text-align: center;
      }
      .qris-card h1 {
         color: #1a5f4a;
         font-size: 1.1rem;
         font-weight: 600;
         margin-bottom: 8px;
         letter-spacing: 0.02em;
      }
      .qris-card p {
         color: #5a6c63;
         font-size: 0.8rem;
         margin-bottom: 20px;
         line-height: 1.4;
      }
      .qris-wrap {
         background: #fff;
         border-radius: 16px;
         padding: 16px;
         display: inline-block;
         box-shadow: inset 0 0 0 1px #e8ecea;
      }
      .qris-wrap img {
         display: block;
         width: 100%;
         max-width: 260px;
         height: auto;
         border-radius: 8px;
      }
      .brand {
         margin-top: 20px;
         font-size: 0.85rem;
         font-weight: 600;
         color: #1a5f4a;
      }
      @media (max-width: 360px) {
         .qris-card { padding: 20px; }
         .qris-wrap img { max-width: 220px; }
      }
   </style>
</head>
<body>
   <div class="qris-card">
      <h1>Scan QRIS untuk Pembayaran</h1>
      <p>Buka aplikasi e-wallet atau mobile banking, lalu scan QR code di bawah ini</p>
      <div class="qris-wrap">
         <img src="<?= htmlspecialchars($qrisUrl) ?>" alt="QRIS Madinah Laundry">
      </div>
      <div class="brand">Madinah Laundry</div>
   </div>
</body>
</html>
