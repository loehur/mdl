<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$pending = is_array($data['pendingKurir'] ?? null) ? $data['pendingKurir'] : [];
?>

<section class="j-hero">
  <div class="j-hero-label">Kurir</div>
  <div class="j-hero-name">Pilih jenis kurir</div>
  <div style="font-size:0.78rem;opacity:0.85;position:relative;z-index:1;margin-top:4px">
    Sameday &amp; Instant siap dipesan
  </div>
</section>

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
          <?php if ($isPay && $refPay !== '') { ?>
            <div class="j-kurir-pending-actions" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
              <button type="button"
                      class="j-btn j-btn-primary j-kurir-pay-instant"
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
  <div class="j-section-head">
    <h2>Opsi pengantaran</h2>
  </div>

  <!-- Sameday -->
  <article class="j-kurir-card j-kurir-card--primary">
    <div class="j-kurir-card__top">
      <div class="j-kurir-card__ico" aria-hidden="true"><i class="fas fa-sun"></i></div>
      <div class="j-kurir-card__title">
        <div class="j-kurir-card__badges">
          <span class="j-badge ok">Direkomendasikan</span>
          <span class="j-badge muted">Sameday</span>
        </div>
        <strong>Kurir Sameday</strong>
        <small>Hemat &amp; nyaman — sampai maksimal keesokan hari</small>
      </div>
    </div>

    <p class="j-kurir-card__lead">
      Cocok untuk Anda yang ingin biaya lebih ringan dan ritme antar-jemput yang teratur.
    </p>

    <div class="j-kurir-cols">
      <div class="j-kurir-col j-kurir-col--pro">
        <h3><i class="fas fa-check-circle"></i> Kelebihan</h3>
        <ul>
          <li>Lebih murah</li>
          <li>Bisa titip laundry berikutnya tanpa biaya ekstra</li>
          <li>Kurir cepat hafal lokasi — jarang perlu telpon atau chat</li>
        </ul>
      </div>
      <div class="j-kurir-col j-kurir-col--con">
        <h3><i class="fas fa-minus-circle"></i> Kekurangan</h3>
        <ul>
          <li>Lebih lambat — maksimal sampai hari berikutnya</li>
          <li>Perlu ada orang di rumah (satpam/keluarga) saat jam kerja driver</li>
        </ul>
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
        <strong>Kurir Instant</strong>
        <small>Super cepat — biasanya tiba &lt; 2 jam · via Biteship</small>
      </div>
    </div>

    <p class="j-kurir-card__lead">
      Pilihan saat butuh laundry berangkat atau sampai secepat mungkin. Ongkir dibayar dulu via QRIS.
    </p>

    <div class="j-kurir-cols">
      <div class="j-kurir-col j-kurir-col--pro">
        <h3><i class="fas fa-check-circle"></i> Kelebihan</h3>
        <ul>
          <li>Cepat — biasanya kurang dari 2 jam</li>
          <li>Bisa dilacak sampai selesai</li>
        </ul>
      </div>
      <div class="j-kurir-col j-kurir-col--con">
        <h3><i class="fas fa-minus-circle"></i> Kekurangan</h3>
        <ul>
          <li>Lebih mahal</li>
          <li>Tidak bisa titip laundry berikutnya</li>
          <li>Kadang harus angkat telepon/chat soal titik lokasi</li>
        </ul>
      </div>
    </div>

    <div class="j-kurir-actions">
      <button type="button"
              class="j-btn j-btn-primary j-kurir-act"
              data-j-kurir-jenis="antar"
              data-j-kurir-layanan="instant">
        <i class="fas fa-truck"></i>
        Antar
      </button>
      <button type="button"
              class="j-btn j-btn-soft j-kurir-act"
              data-j-kurir-jenis="jemput"
              data-j-kurir-layanan="instant">
        <i class="fas fa-hand-holding"></i>
        Jemput
      </button>
    </div>
  </article>

  <p class="j-kurir-note">
    <strong>Antar</strong> — Mengantar Pakaian dari Laundry ke Lokasi Anda.<br>
    <strong>Jemput</strong> — Menjemput Pakaian dari Lokasi Anda dan dikirimkan ke Laundry.<br>
    <strong>Sameday</strong> — tarif jarak internal. <strong>Instant</strong> — ongkir wajib dari cek rate Biteship.<br>
    Antar: item yang sudah/sedang diantar tidak bisa dipilih lagi. Jemput: lokasi yang masih ada jemput berjalan tidak bisa dipilih lagi.
  </p>
</section>
