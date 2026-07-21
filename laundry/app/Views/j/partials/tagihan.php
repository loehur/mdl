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
    <?php if (!empty($summary['has_diskon'])) { ?>
      <b class="j-price">
        <del>Rp<?= number_format((float) $summary['total_tagihan_asli']) ?></del><br>
        Rp<?= number_format((float) $summary['total_tagihan']) ?>
      </b>
    <?php } else { ?>
      <b>Rp<?= number_format((float) $summary['total_tagihan']) ?></b>
    <?php } ?>
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
      <span>Rp<?= number_format((float) ($summary['total_dibayar'] ?? 0)) ?></span>
    </div>
    <div class="j-rekap-row total">
      <span>Sisa tagihan</span>
      <span class="sisa">Rp<?= number_format((float) $summary['sisa']) ?></span>
    </div>
  </div>
</article>
<?php } ?>

<?php if ($hasUnpaid) { ?>
<div class="j-bayar-bottom">
  <button type="button" class="j-bayar-now j-open-bayar">Bayar Sekarang</button>
</div>
<?php } ?>
