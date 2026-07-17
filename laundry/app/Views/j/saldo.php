<?php
require __DIR__ . '/_head.php';
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$saldo = (float) $data['saldoTunai'];
$history = $data['history'];
$tampil = (int) $data['tampil'];
?>

<section class="j-hero">
  <div class="j-hero-label">Saldo deposit tersedia</div>
  <div class="j-hero-name">Rp<?= number_format($saldo) ?></div>
  <div style="font-size:0.78rem;opacity:0.8;position:relative;z-index:1">
    Menampilkan <?= min($tampil, count($history)) ?> transaksi terakhir
  </div>
</section>

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
            #<?= (int) $a['id_kas'] ?> · <?= date('d M Y H:i', strtotime($a['insertTime'])) ?>
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

<a class="j-link-classic" href="<?= $base ?>I/s/<?= $id ?>">Buka riwayat klasik</a>

<?php require __DIR__ . '/_foot.php'; ?>
