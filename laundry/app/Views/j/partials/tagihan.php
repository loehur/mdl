<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$orders = $data['orders'];
$members = $data['members'];
$summary = $data['summary'];
$unpaid = $data['unpaid'] ?? [];
$finance = $data['finance_history'] ?? [];
$customer = $data['customer'] ?? ['id' => $id, 'nama' => $p['nama_pelanggan'], 'hp' => $p['nomor_pelanggan'] ?? ''];
$hp = $customer['hp'] ?? ($p['nomor_pelanggan'] ?? '');
$hasUnpaid = !empty($unpaid);
?>

<script type="application/json" id="jPayConfig"><?= json_encode([
  'id_pelanggan' => (int) $id,
  'nama' => $customer['nama'] ?? $p['nama_pelanggan'],
  'hp' => $hp,
  'unpaid' => $unpaid,
  'nonTunai' => $data['nonTunai'] ?? URL::NON_TUNAI,
  'nonTunaiGuide' => $data['nonTunaiGuide'] ?? URL::NON_TUNAI_GUIDE,
  'base' => $base,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div class="j-bill-bar<?= $hasUnpaid ? ' has-pay' : '' ?>">
  <div class="j-bill-stat">
    <small>Total</small>
    <b>Rp<?= number_format((float) $summary['total_tagihan']) ?></b>
  </div>
  <div class="j-bill-stat">
    <small>Sisa</small>
    <b class="sisa">Rp<?= number_format((float) $summary['sisa']) ?></b>
  </div>
  <?php if ($hasUnpaid) { ?>
  <button type="button" class="j-bill-pay j-open-bayar">Bayar</button>
  <?php } ?>
</div>

<?php if (!empty($finance)) { ?>
<section class="j-section" style="margin-top:0">
  <div class="j-section-head"><h2>Menunggu pembayaran</h2></div>
  <?php foreach ($finance as $fh) {
    $isQRIS = strtoupper($fh['note'] ?? '') === 'QRIS';
    $canManage = (int) ($fh['id_user'] ?? 0) === 0;
  ?>
  <div class="j-card">
    <div class="j-card-head">
      <div>
        <strong><?= htmlspecialchars($fh['note'] ?: 'Non-Tunai') ?></strong>
        <small>Pending · <?= date('d M Y H:i', strtotime($fh['insertTime'])) ?></small>
      </div>
      <span class="j-badge warn">Rp<?= number_format((float) $fh['total']) ?></span>
    </div>
    <?php if ($canManage) { ?>
    <div class="j-chip-row" style="margin-top:4px">
      <button type="button" class="j-btn j-btn-primary j-tokopay"
        data-ref="<?= htmlspecialchars($fh['ref_finance']) ?>"
        data-total="<?= (int) $fh['total'] ?>"
        data-note="<?= htmlspecialchars($fh['note']) ?>">
        <?= $isQRIS ? 'Scan QR' : 'Cek Status' ?>
      </button>
      <button type="button" class="j-btn j-btn-soft j-cancel-pay"
        data-ref="<?= htmlspecialchars($fh['ref_finance']) ?>"
        data-total="<?= number_format((float) $fh['total']) ?>"
        data-note="<?= htmlspecialchars($fh['note']) ?>">
        <i class="fas fa-trash-alt"></i> Batalkan
      </button>
    </div>
    <?php } ?>
  </div>
  <?php } ?>
</section>
<?php } ?>

<?php if (empty($orders) && empty($members)) { ?>
  <div class="j-empty">
    <b>Tidak ada tagihan</b>
    Semua order sudah tuntas / tidak ada data.
  </div>
<?php } ?>

<?php foreach ($orders as $ord) {
  $sisa = (float) $ord['sisa'];
  $badge = $sisa <= 0 ? ['ok', 'Lunas'] : ['danger', 'Belum lunas'];
  $canNota = !empty($ord['can_send_nota']);
?>
  <article class="j-card">
    <div class="j-card-head">
      <div>
        <div class="j-ref-line">
          <strong>REF #<?= htmlspecialchars($ord['no_ref']) ?></strong>
          <?php if ($canNota) { ?>
            <button type="button" class="j-send-nota"
              title="Kirim Nota WA"
              data-id-pelanggan="<?= $id ?>"
              data-hp="<?= htmlspecialchars($hp) ?>"
              data-ref="<?= htmlspecialchars($ord['no_ref']) ?>"
              data-time="<?= htmlspecialchars($ord['insertTime']) ?>">
              <i class="fab fa-whatsapp"></i>
            </button>
          <?php } ?>
        </div>
        <small><?= date('d M Y H:i', strtotime($ord['insertTime'])) ?><?= $ord['letak'] !== '' ? ' · Rak ' . htmlspecialchars(strtoupper($ord['letak'])) : '' ?></small>
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
          <?php if (!empty($it['layanan'])) { ?>
            <br>
            <?php foreach ($it['layanan'] as $ly) { ?>
              <?= !empty($ly['done']) ? '✓' : '○' ?> <?= htmlspecialchars($ly['nama']) ?>
            <?php } ?>
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

<?php if ($hasUnpaid) { ?>
<div class="j-bayar-bottom">
  <button type="button" class="j-bayar-now j-open-bayar">Bayar Sekarang</button>
</div>
<?php } ?>
