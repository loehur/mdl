<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$idHarga = (int) $data['id_harga'];
$info = $data['info'];
$history = $data['history'];
$lastSaldo = $data['lastSaldo'];
$satuan = $data['satuan'];
?>

<section class="j-hero">
  <div class="j-hero-label">Paket M<?= $idHarga ?></div>
  <div class="j-hero-name"><?= $this->fmtDecMax2($lastSaldo) ?><?= htmlspecialchars($satuan) ?></div>
  <div style="font-size:0.78rem;opacity:0.85;position:relative;z-index:1;max-width:90%">
    <?= htmlspecialchars($info['label']) ?>
  </div>
</section>

<div style="margin:12px 0">
  <a class="j-badge muted" href="<?= $base ?>J/paket/<?= $id ?>"><i class="fas fa-arrow-left"></i> Semua paket</a>
</div>

<section class="j-section" style="margin-top:0">
  <div class="j-section-head"><h2>Riwayat</h2></div>
  <div class="j-card">
    <?php if (empty($history)) { ?>
      <div class="j-empty" style="padding:20px 8px">
        <b>Belum ada transaksi</b>
      </div>
    <?php } ?>

    <?php foreach ($history as $idx => $ok) {
      $isTop = (int) $ok['tipe'] === 1;
      $isLatest = $idx === 0;
    ?>
      <div class="j-list-row">
        <div>
          <strong><?= $isTop ? 'Topup' : 'Pakai Laundry' ?></strong>
          <small style="display:block;color:var(--j-muted)">
            #<?= htmlspecialchars((string) $ok['id']) ?> · <?= htmlspecialchars($ok['tgl']) ?>
          </small>
          <small style="display:block;color:var(--j-muted)">
            <?= $isLatest ? 'Saldo terkini' : 'Saldo' ?>: <?= number_format((float) $ok['saldo'], 2) ?><?= htmlspecialchars($satuan) ?>
          </small>
        </div>
        <div class="<?= $isTop ? 'amt-plus' : 'amt-min' ?>">
          <?= $isTop ? '+' : '-' ?><?= $this->fmtDecMax2($ok['qty']) ?><?= htmlspecialchars($satuan) ?>
        </div>
      </div>
    <?php } ?>
  </div>
</section>
