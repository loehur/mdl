<?php
$qrisUrl = $data['qris_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
   <title>QRIS | Madinah Laundry</title>
   <link rel="icon" href="<?= URL::IN_ASSETS ?>icon/j-icon.svg" type="image/svg+xml">
   <style>
      * { box-sizing: border-box; margin: 0; padding: 0; }
      html, body {
         width: 100%;
         min-height: 100%;
         min-height: 100dvh;
         overflow: hidden;
         background: #fff;
      }
      body {
         display: flex;
         align-items: center;
         justify-content: center;
         padding: max(12px, env(safe-area-inset-top))
                  max(12px, env(safe-area-inset-right))
                  max(12px, env(safe-area-inset-bottom))
                  max(12px, env(safe-area-inset-left));
      }
      .qris-img {
         display: block;
         width: auto;
         height: auto;
         max-width: min(100%, 420px);
         max-height: min(100%, calc(100dvh - 24px));
         object-fit: contain;
         object-position: center;
      }
   </style>
</head>
<body>
   <img class="qris-img" src="<?= htmlspecialchars($qrisUrl) ?>" alt="QRIS Madinah Laundry">
</body>
</html>
