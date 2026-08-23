<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$active = $data['active'] ?? 'home';
$title = $data['title'] ?? 'MDL';
$cabang = $data['cabang'] ?? [];
$base = $data['base'];
$assets = $data['assets'];
$namaCabang = $cabang['nama_cabang'] ?? 'MDL Laundry';
$kodeCabang = $cabang['kode_cabang'] ?? '00';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <title><?= htmlspecialchars($title) ?> · <?= strtoupper($p['nama_pelanggan']) ?></title>
  <link rel="icon" href="<?= $assets ?>icon/j-icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= $assets ?>icon/j-icon-192.png">
  <link rel="manifest" href="<?= $base ?>J/manifest/<?= $id ?>">
  <meta name="theme-color" content="#0B3D3A">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="MDL">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?= $assets ?>css/j-customer.css?v=26">
</head>
<body>
<div class="j-app">
  <header class="j-top">
    <div class="j-top-row">
      <span class="j-logo" aria-hidden="true"><i class="fas fa-tshirt"></i></span>
      <div class="j-brand-text">
        <strong>MDL - <?= htmlspecialchars($kodeCabang) ?></strong>
        <span><?= strtoupper(htmlspecialchars($p['nama_pelanggan'])) ?></span>
      </div>
    </div>
  </header>
  <main class="j-main">
