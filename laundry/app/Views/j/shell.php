<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$active = $data['active'] ?? 'home';
$page = $data['page'] ?? 'home';
$extra = $data['extra'] ?? '';
$cabang = $data['cabang'] ?? [];
$base = $data['base'];
$assets = $data['assets'];
$kodeCabang = $cabang['kode_cabang'] ?? '00';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <title>MDL · <?= strtoupper(htmlspecialchars($p['nama_pelanggan'])) ?></title>
  <link rel="icon" href="<?= $assets ?>icon/logo.png">
  <link rel="apple-touch-icon" href="<?= $assets ?>icon/logo.png">
  <link rel="manifest" href="<?= $base ?>J/manifest/<?= $id ?>">
  <meta name="theme-color" content="#0B3D3A">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="MDL">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?= $assets ?>css/j-customer.css?v=5">
</head>
<body>
<div class="j-app"
     id="jApp"
     data-base="<?= htmlspecialchars($base) ?>"
     data-id="<?= $id ?>"
     data-page="<?= htmlspecialchars($page) ?>"
     data-extra="<?= htmlspecialchars((string) $extra) ?>">
  <header class="j-top">
    <div class="j-top-row">
      <img class="j-logo" src="<?= $assets ?>icon/logo.png" alt="MDL">
      <div class="j-brand-text">
        <strong>MDL - <?= htmlspecialchars($kodeCabang) ?></strong>
        <span>Customer Portal</span>
      </div>
    </div>
  </header>

  <main class="j-main" id="jContent">
    <div class="j-loading" id="jLoading">
      <div class="j-spinner"></div>
      <span>Memuat data...</span>
    </div>
  </main>

  <nav class="j-nav" aria-label="Menu utama">
    <a href="<?= $base ?>J/<?= $id ?>" data-nav="home" class="<?= $active === 'home' ? 'active' : '' ?>">
      <i class="fas fa-home"></i> Beranda
    </a>
    <a href="<?= $base ?>J/tagihan/<?= $id ?>" data-nav="tagihan" class="<?= $active === 'tagihan' ? 'active' : '' ?>">
      <i class="fas fa-receipt"></i> Tagihan
    </a>
    <a href="<?= $base ?>J/saldo/<?= $id ?>" data-nav="saldo" class="<?= $active === 'saldo' ? 'active' : '' ?>">
      <i class="fas fa-wallet"></i> Saldo
    </a>
    <a href="<?= $base ?>J/paket/<?= $id ?>" data-nav="paket" class="<?= $active === 'paket' ? 'active' : '' ?>">
      <i class="fas fa-box-open"></i> Paket
    </a>
  </nav>
</div>

<script src="<?= $assets ?>js/j-customer.js?v=1"></script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?= $base ?>Pwa/sw', { scope: '<?= $base ?>' }).catch(function () {});
}
</script>
</body>
</html>
