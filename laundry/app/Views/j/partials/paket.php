<?php
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
  <?php } ?>

  <?php foreach ($list as $lp) { ?>
    <a class="j-card j-paket-card" href="<?= $base ?>J/paketDetail/<?= $id ?>/<?= (int) $lp['id_harga'] ?>">
      <div class="j-paket-ico">M<?= (int) $lp['id_harga'] ?></div>
      <div class="grow">
        <strong>Paket M<?= (int) $lp['id_harga'] ?></strong>
        <small style="display:block;color:var(--j-muted);margin-top:2px">
          <?= htmlspecialchars($lp['label']) ?>
        </small>
        <div class="j-chip-row">
          <span class="j-badge ok">Sisa <?= $this->fmtDecMax2($lp['saldo']) ?><?= htmlspecialchars($lp['satuan']) ?></span>
        </div>
      </div>
      <i class="fas fa-chevron-right arrow"></i>
    </a>
  <?php } ?>
</section>
