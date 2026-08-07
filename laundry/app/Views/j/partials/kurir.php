<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$pending = is_array($data['pendingKurir'] ?? null) ? $data['pendingKurir'] : [];
$pendingJenis = [];
foreach ($pending as $pr) {
  $j = strtolower((string) ($pr['jenis'] ?? ''));
  if ($j !== '') {
    $pendingJenis[$j] = true;
  }
}
$hasAntar = !empty($pendingJenis['antar']);
$hasJemput = !empty($pendingJenis['jemput']);
?>

<section class="j-hero">
  <div class="j-hero-label">Kurir</div>
  <div class="j-hero-name">Pilih jenis kurir</div>
  <div style="font-size:0.78rem;opacity:0.85;position:relative;z-index:1;margin-top:4px">
    Sameday siap dipesan — Instant segera hadir
  </div>
</section>

<?php if (!empty($pending)) { ?>
<section class="j-section j-kurir-pending">
  <div class="j-section-head">
    <h2>Permintaan berjalan</h2>
  </div>
  <div class="j-kurir-pending-list">
    <?php foreach ($pending as $pr) {
      $jenis = strtolower((string) ($pr['jenis'] ?? ''));
      $label = $jenis === 'antar' ? 'Antar' : ($jenis === 'jemput' ? 'Jemput' : strtoupper($jenis));
      $ts = strtotime($pr['insertTime'] ?? '');
      $when = $ts ? date('d M Y H:i', $ts) : '-';
      $lokNama = trim((string) ($pr['lokasi_nama'] ?? ''));
    ?>
      <div class="j-kurir-pending-item">
        <div class="j-kurir-pending-item__ico" aria-hidden="true">
          <i class="fas <?= $jenis === 'jemput' ? 'fa-hand-holding' : 'fa-truck' ?>"></i>
        </div>
        <div class="j-kurir-pending-item__text">
          <strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> Sameday</strong>
          <small>Menunggu driver · <?= htmlspecialchars($when, ENT_QUOTES, 'UTF-8') ?><?= $lokNama !== '' ? ' · ' . htmlspecialchars($lokNama, ENT_QUOTES, 'UTF-8') : '' ?></small>
        </div>
        <span class="j-badge warn">Berjalan</span>
      </div>
    <?php } ?>
  </div>
</section>
<?php } ?>

<section class="j-section j-kurir">
  <div class="j-section-head">
    <h2>Opsi pengantaran</h2>
  </div>

  <!-- Sameday (utama) -->
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
              <?= $hasAntar ? 'disabled' : '' ?>>
        <i class="fas fa-truck"></i>
        <?= $hasAntar ? 'Antar berjalan' : 'Antar' ?>
      </button>
      <button type="button"
              class="j-btn j-btn-soft j-kurir-act"
              data-j-kurir-jenis="jemput"
              <?= $hasJemput ? 'disabled' : '' ?>>
        <i class="fas fa-hand-holding"></i>
        <?= $hasJemput ? 'Jemput berjalan' : 'Jemput' ?>
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
        <small>Super cepat — biasanya tiba &lt; 2 jam</small>
      </div>
    </div>

    <p class="j-kurir-card__lead">
      Pilihan saat butuh laundry berangkat atau sampai secepat mungkin.
    </p>

    <div class="j-kurir-cols">
      <div class="j-kurir-col j-kurir-col--pro">
        <h3><i class="fas fa-check-circle"></i> Kelebihan</h3>
        <ul>
          <li>Cepat — biasanya kurang dari 2 jam</li>
        </ul>
      </div>
      <div class="j-kurir-col j-kurir-col--con">
        <h3><i class="fas fa-minus-circle"></i> Kekurangan</h3>
        <ul>
          <li>Lebih mahal</li>
          <li>Tidak bisa titip laundry berikutnya</li>
          <li>Kadang harus angkat telepon/chat Gojek soal titik lokasi</li>
        </ul>
      </div>
    </div>

    <button type="button" class="j-btn j-btn-soft j-btn-block j-kurir-soon" disabled>
      <i class="fas fa-clock"></i> Segera hadir
    </button>
  </article>

  <p class="j-kurir-note">
    Antar/Jemput wajib pilih lokasi. Antar: pilih item laundry. Jemput: item dipilih petugas saat selesai.
  </p>
</section>
