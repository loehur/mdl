<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$saldo = (float) $data['saldoTunai'];
$history = $data['history'];
$tampil = (int) $data['tampil'];
$finance = $data['finance_history'] ?? [];
$maxSaldo = (int) ($data['maxSaldo'] ?? 5000000);
$topupRoom = (int) ($data['topupRoom'] ?? 0);
$blocked = !empty($data['topupBlocked']);
$selfPending = (int) ($data['selfPendingCount'] ?? 0);
$maxPending = (int) ($data['maxPending'] ?? 1);
$customer = $data['customer'] ?? ['id' => $id, 'nama' => $p['nama_pelanggan'], 'hp' => $p['nomor_pelanggan'] ?? ''];
?>

<script type="application/json" id="jPayConfig"><?= json_encode([
  'id_pelanggan' => (int) $id,
  'nama' => $customer['nama'] ?? $p['nama_pelanggan'],
  'hp' => $customer['hp'] ?? ($p['nomor_pelanggan'] ?? ''),
  'unpaid' => [],
  'nonTunai' => $data['nonTunai'] ?? URL::NON_TUNAI,
  'nonTunaiGuide' => $data['nonTunaiGuide'] ?? BankAccountsApi::accounts(),
  'base' => $base,
  'maxSaldo' => $maxSaldo,
  'topupRoom' => $topupRoom,
  'topupBlocked' => $blocked,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<section class="j-hero">
  <div class="j-hero-label">Saldo deposit tersedia</div>
  <div class="j-hero-name">Rp<?= number_format($saldo) ?></div>
  <div style="font-size:0.78rem;opacity:0.8;position:relative;z-index:1">
    Maks. Rp<?= number_format($maxSaldo) ?> · Sisa kapasitas Rp<?= number_format($topupRoom) ?>
  </div>
</section>

<div style="margin:12px 0">
  <?php if ($blocked) { ?>
    <button type="button" class="j-btn j-btn-soft j-btn-block" disabled>
      <?php if ($selfPending >= $maxPending) { ?>
        Batas topup pending
      <?php } else { ?>
        Saldo penuh / kapasitas kurang
      <?php } ?>
    </button>
  <?php } else { ?>
    <button type="button" class="j-btn j-btn-primary j-btn-block j-open-saldo-topup">
      <i class="fas fa-plus"></i> Topup Saldo
    </button>
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
    <?php } else { ?>
      <small style="color:var(--j-muted)">Dibuat staff — hubungi outlet jika perlu dibatalkan.</small>
    <?php } ?>
  </div>
  <?php } ?>
</section>
<?php } ?>

<section class="j-section">
  <div class="j-section-head"><h2>Riwayat</h2></div>
  <div class="j-card">
    <?php if (empty($history)) { ?>
      <div class="j-empty" style="padding:20px 8px">
        <b>Belum ada mutasi</b>
        Saldo deposit masih kosong.
      </div>
    <?php } ?>

    <?php foreach ($history as $a) {
      $jenis = (int) $a['jenis_transaksi'];
      $mutasi = (int) $a['jenis_mutasi'];
      $label = 'Laundry';
      if ($jenis === 3) $label = 'Topup Paket';
      if ($jenis === 6 && $mutasi === 1) $label = 'Topup Deposit';
      if ($jenis === 6 && $mutasi === 2) $label = 'Refund';
      if ($jenis === 1) $label = 'Bayar Laundry';
      if ($jenis === 3 && $mutasi === 2) $label = 'Bayar Paket';

      $isPlus = $mutasi === 1;
    ?>
      <div class="j-list-row">
        <div>
          <strong><?= htmlspecialchars($label) ?></strong>
          <small style="display:block;color:var(--j-muted)">
            #<?= htmlspecialchars((string) $a['id_kas']) ?> · <?= date('d M Y H:i', strtotime($a['insertTime'])) ?>
          </small>
          <small style="display:block;color:var(--j-muted)">Saldo: Rp<?= number_format((float) $a['saldo']) ?></small>
        </div>
        <div class="<?= $isPlus ? 'amt-plus' : 'amt-min' ?>">
          <?= $isPlus ? '+' : '-' ?>Rp<?= number_format((float) $a['jumlah']) ?>
        </div>
      </div>
    <?php } ?>
  </div>
</section>
