<?php
require __DIR__ . '/_head.php';
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$orders = $data['orders'];
$members = $data['members'];
$summary = $data['summary'];
?>

<div class="j-tagihan">

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
          <div class="j-price">
            <?php if ($it['member']) { ?>
              —
            <?php } elseif (!empty($it['has_diskon'])) { ?>
              <del>Rp<?= number_format((float) $it['total_asli']) ?></del><br>Rp<?= number_format((float) $it['total']) ?>
            <?php } else { ?>
              Rp<?= number_format((float) $it['total']) ?>
            <?php } ?>
          </div>
        </div>
        <div class="j-item-meta">
          <?= htmlspecialchars($it['durasi']) ?> · <?= htmlspecialchars($it['qty_show']) ?>
          <?php if (!empty($it['layanan_done']) || !empty($it['layanan_pending'])) { ?>
            <br>
            <?php foreach ($it['layanan_done'] as $ld) { ?>
              <span class="j-ly-done">✓ <?= htmlspecialchars($ld) ?></span>
            <?php } ?>
            <?php foreach ($it['layanan_pending'] as $lp) { ?>
              <span class="j-ly-pending">○ <?= htmlspecialchars($lp) ?></span>
            <?php } ?>
            <?php if (!empty($it['ambil'])) { ?>
              <span class="j-ly-done">· ✓ Ambil</span>
            <?php } else { ?>
              <span class="j-ly-pending">· ○ Ambil</span>
            <?php } ?>
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

    <?php if (!empty($ord['payments'])) { ?>
      <div class="j-pay-history">
        <?php foreach ($ord['payments'] as $pay) {
          $st = (int) $pay['status'];
          $note = strtoupper(trim((string) ($pay['note'] ?? '')));
          $nominal = 'Rp' . number_format((float) $pay['jumlah']);
          if ($st === 3) {
            $label = '✓' . ($note !== '' ? ' ' . $note : ' Lunas');
            $cls = 'ok';
          } elseif ($st === 2) {
            $label = 'Pending' . ($note !== '' ? ' (' . $note . ')' : '');
            $cls = 'warn';
          } elseif ($st === 4) {
            $label = 'Gagal' . ($note !== '' ? ' (' . $note . ')' : '');
            $cls = 'fail';
            $nominal = '<del>' . $nominal . '</del>';
          } else {
            $label = 'Status #' . $st;
            $cls = '';
          }
        ?>
          <div class="j-pay-history-row <?= $cls ?>">
            <span>
              <?= htmlspecialchars($label) ?>
              <?php if (!empty($pay['id_kas'])) { ?>#<?= (int) $pay['id_kas'] ?><?php } ?>
              · <?= date('d/m H:i', strtotime($pay['time'])) ?>
            </span>
            <span>-<?= $nominal ?></span>
          </div>
        <?php } ?>
      </div>
    <?php } ?>

    <div class="j-foot-row">
      <span>
        <?php if (!empty($ord['has_diskon'])) { ?>
          Subtotal <del>Rp<?= number_format((float) $ord['subtotal_asli']) ?></del>
          Rp<?= number_format((float) $ord['subtotal']) ?>
        <?php } else { ?>
          Subtotal Rp<?= number_format((float) $ord['subtotal']) ?>
        <?php } ?>
      </span>
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

<?php if (!empty($orders) || !empty($members)) {
  $diskonTotal = max(0, (float) ($summary['total_tagihan_asli'] ?? $summary['total_tagihan']) - (float) $summary['total_tagihan']);
?>
<article class="j-card j-rekap">
  <div class="j-card-head">
    <div>
      <strong>Rekap Tagihan</strong>
      <small>
        <?= (int) ($summary['count_order'] ?? 0) ?> nota
        <?php if ((int) ($summary['count_member'] ?? 0) > 0) { ?>
          · <?= (int) $summary['count_member'] ?> paket
        <?php } ?>
      </small>
    </div>
  </div>
  <div class="j-rekap-rows">
    <div class="j-rekap-row">
      <span>Total</span>
      <span>Rp<?= number_format((float) ($summary['total_tagihan_asli'] ?? $summary['total_tagihan'])) ?></span>
    </div>
    <?php if ($diskonTotal > 0) { ?>
    <div class="j-rekap-row">
      <span>Diskon</span>
      <span class="ok">-Rp<?= number_format($diskonTotal) ?></span>
    </div>
    <?php } ?>
    <div class="j-rekap-row">
      <span>Sudah dibayar</span>
      <span>-Rp<?= number_format((float) ($summary['total_dibayar'] ?? 0)) ?></span>
    </div>
    <div class="j-rekap-row total">
      <span>Sisa tagihan</span>
      <span class="sisa">Rp<?= number_format((float) $summary['sisa']) ?></span>
    </div>
  </div>
</article>
<?php } ?>

</div>

<?php require __DIR__ . '/_foot.php'; ?>
