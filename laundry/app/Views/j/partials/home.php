<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$tagihan = $data['tagihan'];
$saldo = (float) $data['saldoTunai'];
$listPaket = $data['listPaket'];
?>

<section class="j-hero">
  <div class="j-hero-label">Halo</div>
  <div class="j-hero-name"><?= strtoupper(htmlspecialchars($p['nama_pelanggan'])) ?></div>
  <div class="j-hero-grid">
    <div class="j-hero-stat">
      <small>Sisa tagihan</small>
      <b>Rp<?= number_format((float) $tagihan['sisa']) ?></b>
    </div>
    <div class="j-hero-stat">
      <small>Saldo deposit</small>
      <b>Rp<?= number_format($saldo) ?></b>
    </div>
  </div>
</section>

<section class="j-section">
  <div class="j-section-head">
    <h2>Menu</h2>
  </div>
  <div class="j-grid-menu">
    <a class="j-menu-tile" href="<?= $base ?>J/tagihan/<?= $id ?>">
      <div class="j-menu-ico tagihan"><i class="fas fa-receipt"></i></div>
      <span>Tagihan</span>
    </a>
    <a class="j-menu-tile" href="<?= $base ?>J/saldo/<?= $id ?>">
      <div class="j-menu-ico saldo"><i class="fas fa-wallet"></i></div>
      <span>Saldo</span>
    </a>
    <a class="j-menu-tile" href="<?= $base ?>J/paket/<?= $id ?>">
      <div class="j-menu-ico paket"><i class="fas fa-box-open"></i></div>
      <span>Paket</span>
    </a>
  </div>
</section>

<section class="j-section">
  <div class="j-section-head">
    <h2>Ringkasan</h2>
  </div>
  <div class="j-card">
    <div class="j-list-row">
      <div>
        <strong>Order berjalan</strong>
        <small style="display:block;color:var(--j-muted)"><?= (int) $tagihan['count_order'] ?> nota belum tuntas</small>
      </div>
      <span class="j-badge <?= $tagihan['sisa'] > 0 ? 'danger' : 'ok' ?>">
        <?= $tagihan['sisa'] > 0 ? 'Ada tagihan' : 'Lunas' ?>
      </span>
    </div>
    <div class="j-list-row">
      <div>
        <strong>Paket member</strong>
        <small style="display:block;color:var(--j-muted)"><?= count($listPaket) ?> paket aktif</small>
      </div>
      <a href="<?= $base ?>J/paket/<?= $id ?>" class="j-badge muted">Lihat</a>
    </div>
    <?php if ((float) $tagihan['sisa'] > 0) { ?>
    <a class="j-btn j-btn-primary j-btn-block" style="margin-top:8px" href="<?= $base ?>J/tagihan/<?= $id ?>">
      Lihat detail tagihan
    </a>
    <?php } ?>
  </div>
</section>

<a class="j-link-classic" href="<?= $base ?>I/<?= $id ?>">Buka halaman klasik (I)</a>
