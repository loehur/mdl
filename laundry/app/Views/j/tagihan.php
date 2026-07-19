<?php
require __DIR__ . '/_head.php';
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$orders = $data['orders'];
$members = $data['members'];
$summary = $data['summary'];
?>

<div class="j-card" style="margin-bottom:12px">
  <div class="j-hero-grid" style="gap:8px">
    <div class="j-hero-stat" style="background:#F7FBFA;border-color:var(--j-line);color:var(--j-ink)">
      <small style="opacity:1;color:var(--j-muted)">Total</small>
      <b>Rp<?= number_format((float) $summary['total_tagihan']) ?></b>
    </div>
    <div class="j-hero-stat" style="background:#F7FBFA;border-color:var(--j-line);color:var(--j-ink)">
      <small style="opacity:1;color:var(--j-muted)">Sisa</small>
      <b style="color:var(--j-coral)">Rp<?= number_format((float) $summary['sisa']) ?></b>
    </div>
  </div>
</div>

<?php if (empty($orders) && empty($members)) { ?>
  <div class="j-empty">
    <b>Tidak ada tagihan</b>
    Semua order sudah tuntas / tidak ada data.
  </div>
<?php } ?>

<?php foreach ($orders as $ord) {
  $sisa = (float) $ord['sisa'];
  $badge = $sisa <= 0 ? ['ok', 'Lunas'] : ['danger', 'Belum lunas'];
?>
  <article class="j-card">
    <div class="j-card-head">
      <div>
        <strong>REF #<?= htmlspecialchars($ord['no_ref']) ?></strong>
        <small><?= date('d M Y H:i', strtotime($ord['insertTime'])) ?></small>
      </div>
      <span class="j-badge <?= $badge[0] ?>"><?= $badge[1] ?></span>
    </div>

    <?php foreach ($ord['items'] as $it) { ?>
      <div class="j-item">
        <div class="j-item-row">
          <div>
            <strong><?= htmlspecialchars($it['kategori'] ?: 'Item') ?></strong>
            <?php if ($it['member']) { ?> <span class="j-badge ok">Member</span><?php } ?>
          </div>
          <div><?= $it['member'] ? '—' : 'Rp' . number_format((float) $it['total']) ?></div>
        </div>
        <div class="j-item-meta">
          <?= htmlspecialchars($it['durasi']) ?> · <?= htmlspecialchars($it['qty_show']) ?>
          <?php if (!empty($it['layanan_done']) || !empty($it['layanan_pending'])) { ?>
            <br>
            <?php foreach ($it['layanan_done'] as $ld) { ?>✓ <?= htmlspecialchars($ld) ?> <?php } ?>
            <?php foreach ($it['layanan_pending'] as $lp) { ?>○ <?= htmlspecialchars($lp) ?> <?php } ?>
            <?= $it['ambil'] ? '· ✓ Ambil' : '· ○ Ambil' ?>
          <?php } ?>
        </div>
      </div>
    <?php } ?>

    <?php foreach ($ord['surcas'] as $sc) { ?>
      <div class="j-item">
        <div class="j-item-row">
          <div><?= htmlspecialchars($sc['nama']) ?></div>
          <div>Rp<?= number_format((float) $sc['jumlah']) ?></div>
        </div>
      </div>
    <?php } ?>

    <div class="j-foot-row">
      <span>Subtotal Rp<?= number_format((float) $ord['subtotal']) ?></span>
      <span class="<?= $sisa > 0 ? 'sisa' : '' ?>">
        <?= $sisa > 0 ? 'Sisa Rp' . number_format($sisa) : 'Lunas' ?>
      </span>
    </div>
  </article>
<?php } ?>

<?php foreach ($members as $m) { ?>
  <article class="j-card">
    <div class="j-card-head">
      <div>
        <strong>Topup Paket M<?= (int) $m['id_harga'] ?></strong>
        <small>#<?= (int) $m['id_member'] ?> · <?= date('d M Y', strtotime($m['insertTime'])) ?></small>
      </div>
      <span class="j-badge warn">Belum lunas</span>
    </div>
    <div class="j-item-meta"><?= htmlspecialchars($m['label']) ?></div>
    <div class="j-foot-row">
      <span>Rp<?= number_format((float) $m['harga']) ?></span>
      <span class="sisa">Sisa Rp<?= number_format((float) $m['sisa']) ?></span>
    </div>
  </article>
<?php } ?>

<?php require __DIR__ . '/_foot.php'; ?>
