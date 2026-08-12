<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$pending = is_array($data['pendingKurir'] ?? null) ? $data['pendingKurir'] : [];
$saldoTunai = (int) round((float) ($data['saldoTunai'] ?? 0));
$instantWindow = is_array($data['instantWindow'] ?? null) ? $data['instantWindow'] : [];
$instantOpen = !empty($instantWindow['ok']);
$instantMsg = (string) ($instantWindow['message'] ?? '');
$instantOpenLabel = (string) ($instantWindow['open_label'] ?? '07.00');
$instantCutoffLabel = (string) ($instantWindow['cutoff_label'] ?? '20.30');
$instantCloseLabel = (string) ($instantWindow['close_label'] ?? '21.00');
?>
<script type="application/json" id="jKurirConfig"><?= json_encode([
  'saldoTunai' => $saldoTunai,
  'instantWindow' => $instantWindow,
], JSON_UNESCAPED_UNICODE) ?></script>

<?php if (!empty($pending)) { ?>
<section class="j-section j-kurir-pending">
  <div class="j-section-head">
    <h2>Permintaan aktif</h2>
  </div>
  <div class="j-kurir-pending-list">
    <?php foreach ($pending as $pr) {
      $jenis = strtolower((string) ($pr['jenis'] ?? ''));
      $layanan = strtolower((string) ($pr['layanan'] ?? 'sameday'));
      $status = strtolower((string) ($pr['delivery_status'] ?? 'berjalan'));
      $labelJenis = $jenis === 'antar' ? 'Antar' : ($jenis === 'jemput' ? 'Jemput' : strtoupper($jenis));
      $labelLayanan = $layanan === 'instant' ? 'Instant' : 'Sameday';
      $ts = strtotime($pr['insertTime'] ?? '');
      $when = $ts ? date('d M Y H:i', $ts) : '-';
      $lokNama = trim((string) ($pr['lokasi_nama'] ?? ''));
      $idReq = (int) ($pr['id_request'] ?? 0);
      $refPay = trim((string) ($pr['payment_ref_finance'] ?? ''));
      $ongkir = (int) ($pr['ongkir'] ?? 0);
      $track = trim((string) ($pr['tracking_url'] ?? ''));
      $bsStatus = trim((string) ($pr['biteship_status'] ?? ''));
      $courier = trim((string) ($pr['courier_name'] ?? ''));
      $catatanKurir = trim((string) ($pr['catatan_kurir'] ?? ''));
      $isPay = $status === 'menunggu_pembayaran';
      $badgeClass = $isPay ? 'warn' : 'ok';
      $badgeText = $isPay ? 'Bayar' : 'Berjalan';
      $meta = $isPay
        ? ('Menunggu pembayaran' . ($ongkir > 0 ? ' · Rp' . number_format($ongkir, 0, ',', '.') : ''))
        : ('Menunggu driver' . ($bsStatus !== '' ? ' · ' . $bsStatus : '') . ($courier !== '' ? ' · ' . $courier : ''));
    ?>
      <div class="j-kurir-pending-item" data-id-request="<?= $idReq ?>">
        <div class="j-kurir-pending-item__ico" aria-hidden="true">
          <i class="fas <?= $jenis === 'jemput' ? 'fa-hand-holding' : 'fa-truck' ?>"></i>
        </div>
        <div class="j-kurir-pending-item__text">
          <strong><?= htmlspecialchars($labelJenis . ' ' . $labelLayanan, ENT_QUOTES, 'UTF-8') ?></strong>
          <small><?= htmlspecialchars($meta . ' · ' . $when, ENT_QUOTES, 'UTF-8') ?><?= $lokNama !== '' ? ' · ' . htmlspecialchars($lokNama, ENT_QUOTES, 'UTF-8') : '' ?></small>
          <?php if ($catatanKurir !== '') { ?>
            <small style="display:block;margin-top:4px">Catatan: <?= htmlspecialchars($catatanKurir, ENT_QUOTES, 'UTF-8') ?></small>
          <?php } ?>
          <?php if ($isPay && $refPay !== '') { ?>
            <div class="j-kurir-pending-actions" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
              <?php if ($saldoTunai >= $ongkir && $ongkir > 0) { ?>
              <button type="button"
                      class="j-btn j-btn-primary j-kurir-pay-saldo-instant"
                      data-id-request="<?= $idReq ?>"
                      data-total="<?= (int) $ongkir ?>">
                <i class="fas fa-wallet"></i> Bayar Saldo
              </button>
              <?php } ?>
              <button type="button"
                      class="j-btn <?= ($saldoTunai >= $ongkir && $ongkir > 0) ? 'j-btn-soft' : 'j-btn-primary' ?> j-kurir-pay-instant"
                      data-ref="<?= htmlspecialchars($refPay, ENT_QUOTES, 'UTF-8') ?>"
                      data-total="<?= (int) $ongkir ?>"
                      data-note="QRIS">
                <i class="fas fa-qrcode"></i> Bayar QRIS
              </button>
              <button type="button"
                      class="j-btn j-btn-soft j-kurir-batal-instant"
                      data-id-request="<?= $idReq ?>">
                Batalkan
              </button>
            </div>
          <?php } elseif ($track !== '') { ?>
            <div style="margin-top:6px">
              <a class="j-btn j-btn-soft" href="<?= htmlspecialchars($track, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <i class="fas fa-map-marked-alt"></i> Lacak
              </a>
            </div>
          <?php } ?>
        </div>
        <span class="j-badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php } ?>
  </div>
</section>
<?php } ?>

<section class="j-section j-kurir">

  <!-- Sameday -->
  <article class="j-kurir-card j-kurir-card--primary">
    <div class="j-kurir-card__top">
      <div class="j-kurir-card__ico" aria-hidden="true"><i class="fas fa-sun"></i></div>
      <div class="j-kurir-card__title">
        <div class="j-kurir-card__badges">
          <span class="j-badge ok">Direkomendasikan</span>
          <span class="j-badge muted">Sameday</span>
        </div>
        <strong>Sameday</strong>
        <small>Kurir laundry · lebih hemat · sampai maks. besok</small>
      </div>
    </div>

    <div class="j-kurir-actions">
      <button type="button"
              class="j-btn j-btn-primary j-kurir-act"
              data-j-kurir-jenis="antar"
              data-j-kurir-layanan="sameday">
        <i class="fas fa-truck"></i>
        Antar
      </button>
      <button type="button"
              class="j-btn j-btn-soft j-kurir-act"
              data-j-kurir-jenis="jemput"
              data-j-kurir-layanan="sameday">
        <i class="fas fa-hand-holding"></i>
        Jemput
      </button>
    </div>
  </article>

  <!-- Instant -->
  <article class="j-kurir-card">
    <div class="j-kurir-card__top">
      <div class="j-kurir-card__ico j-kurir-card__ico--fast" aria-hidden="true"><i class="fas fa-bolt"></i></div>
      <div class="j-kurir-card__title">
        <div class="j-kurir-card__badges">
          <span class="j-badge warn">Instant</span>
        </div>
        <strong>Instant</strong>
        <small>Gojek/Grab · biasanya &lt; 2 jam · bayar QRIS/Saldo</small>
      </div>
    </div>
    <p class="j-kurir-kapasitas">Pastikan laundry yg dijemput/diantar sesuai kapasitas driver.</p>

    <?php if (!$instantOpen && $instantMsg !== '') { ?>
    <p class="j-kurir-card__lead" style="color:#b42318;margin-top:8px">
      <?= htmlspecialchars($instantMsg, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php } ?>

    <div class="j-kurir-actions">
      <button type="button"
              class="j-btn j-btn-primary j-kurir-act"
              data-j-kurir-jenis="antar"
              data-j-kurir-layanan="instant"
              <?= $instantOpen ? '' : 'disabled' ?>>
        <i class="fas fa-truck"></i>
        Antar
      </button>
      <button type="button"
              class="j-btn j-btn-soft j-kurir-act"
              data-j-kurir-jenis="jemput"
              data-j-kurir-layanan="instant"
              <?= $instantOpen ? '' : 'disabled' ?>>
        <i class="fas fa-hand-holding"></i>
        Jemput
      </button>
    </div>
  </article>

  <p class="j-kurir-note">
    <strong>Antar</strong> laundry → lokasi Anda.
    <strong>Jemput</strong> lokasi Anda → laundry.
    Instant: jam <?= htmlspecialchars($instantOpenLabel, ENT_QUOTES, 'UTF-8') ?>–<?= htmlspecialchars($instantCutoffLabel, ENT_QUOTES, 'UTF-8') ?>.
  </p>
</section>

<?php
$riwayat = is_array($data['riwayatKurir'] ?? null) ? $data['riwayatKurir'] : [];
?>
<section class="j-section j-kurir-riwayat" id="jKurirRiwayat">
  <div class="j-section-head">
    <h2>Riwayat kurir</h2>
    <?php if (!empty($riwayat)) { ?>
    <button type="button" class="j-btn j-btn-soft j-kurir-riwayat-toggle" id="jBtnKurirRiwayatToggle" aria-expanded="false">
      Lihat
    </button>
    <?php } ?>
  </div>
  <?php if (empty($riwayat)) { ?>
    <p class="j-sheet-desc" style="margin:0">Belum ada riwayat antar/jemput.</p>
  <?php } else { ?>
  <div class="j-kurir-riwayat-list" id="jKurirRiwayatList" hidden>
    <?php foreach ($riwayat as $rh) {
      $jenis = strtolower((string) ($rh['jenis'] ?? ''));
      $layanan = strtolower((string) ($rh['layanan'] ?? 'sameday'));
      $status = strtolower((string) ($rh['delivery_status'] ?? ''));
      $labelJenis = $jenis === 'antar' ? 'Antar' : ($jenis === 'jemput' ? 'Jemput' : strtoupper($jenis));
      $labelLayanan = $layanan === 'instant' ? 'Instant' : 'Sameday';
      $tsRaw = (string) (($rh['selesaiTime'] ?? '') !== '' ? $rh['selesaiTime'] : ($rh['insertTime'] ?? ''));
      $ts = strtotime($tsRaw);
      $when = $ts ? date('d M Y H:i', $ts) : '-';
      $lokNama = trim((string) ($rh['lokasi_nama'] ?? ''));
      $ongkir = (int) ($rh['ongkir'] ?? 0);
      $courier = trim((string) ($rh['courier_name'] ?? ''));
      $track = trim((string) ($rh['tracking_url'] ?? ''));
      $catatanBatal = trim((string) ($rh['catatan_batal'] ?? ''));
      $refunded = !empty($rh['refunded']);
      $isBatal = $status === 'batal';
      $badgeClass = $isBatal ? 'danger' : 'ok';
      $badgeText = $isBatal ? 'Batal' : 'Selesai';
      $metaParts = [];
      if ($courier !== '') {
        $metaParts[] = $courier;
      }
      if ($ongkir > 0) {
        $metaParts[] = 'Rp' . number_format($ongkir, 0, ',', '.');
      }
      $metaParts[] = $when;
      if ($lokNama !== '') {
        $metaParts[] = $lokNama;
      }
    ?>
      <div class="j-kurir-riwayat-item<?= $isBatal ? ' is-batal' : '' ?>">
        <div class="j-kurir-riwayat-item__ico" aria-hidden="true">
          <i class="fas <?= $jenis === 'jemput' ? 'fa-hand-holding' : 'fa-truck' ?>"></i>
        </div>
        <div class="j-kurir-riwayat-item__text">
          <strong><?= htmlspecialchars($labelJenis . ' ' . $labelLayanan, ENT_QUOTES, 'UTF-8') ?></strong>
          <small><?= htmlspecialchars(implode(' · ', $metaParts), ENT_QUOTES, 'UTF-8') ?></small>
          <?php if ($isBatal && $refunded) { ?>
            <small class="j-kurir-riwayat-refund">Ongkir dikembalikan ke Saldo Deposit</small>
          <?php } elseif ($isBatal && $catatanBatal !== '') { ?>
            <small><?= htmlspecialchars($catatanBatal, ENT_QUOTES, 'UTF-8') ?></small>
          <?php } ?>
          <?php if (!$isBatal && $track !== '') { ?>
            <div style="margin-top:6px">
              <a class="j-btn j-btn-soft" href="<?= htmlspecialchars($track, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <i class="fas fa-map-marked-alt"></i> Lacak
              </a>
            </div>
          <?php } ?>
        </div>
        <span class="j-badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php } ?>
  </div>
  <?php } ?>
</section>
