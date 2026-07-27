<?php
require __DIR__ . '/_head.php';
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$list = $data['listPaket'];
?>

<section class="j-section" style="margin-top:0">
  <div class="j-section-head"><h2>Paket Anda</h2></div>

  <?php if (empty($list)) { ?>
    <div class="j-empty">
      <b>Belum ada paket</b>
      Paket member akan muncul di sini setelah topup.
    </div>
  <?php } else { ?>
    <div class="j-paket-grid">
      <?php foreach ($list as $lp) { ?>
        <a class="j-card j-paket-card" href="<?= $base ?>J/paketDetail/<?= $id ?>/<?= (int) $lp['id_harga'] ?>">
          <strong>M<?= (int) $lp['id_harga'] ?></strong>
          <small><?= htmlspecialchars($lp['label']) ?></small>
          <span class="j-badge ok">Sisa <?= $this->fmtDecMax2($lp['saldo']) ?><?= htmlspecialchars($lp['satuan']) ?></span>
        </a>
      <?php } ?>
    </div>
  <?php } ?>
</section>

<?php require __DIR__ . '/_foot.php'; ?>
