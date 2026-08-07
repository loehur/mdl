<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
?>

<section class="j-hero">
  <div class="j-hero-label">Kurir</div>
  <div class="j-hero-name">Pilih jenis kurir</div>
  <div style="font-size:0.78rem;opacity:0.85;position:relative;z-index:1;margin-top:4px">
    Bandingkan dulu — pesanan online segera dibuka
  </div>
</section>

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

    <button type="button" class="j-btn j-btn-soft j-btn-block j-kurir-soon" disabled>
      <i class="fas fa-clock"></i> Segera hadir
    </button>
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
    Fitur pemesanan kurir masih dalam tahap pengembangan. Nanti Anda bisa pilih langsung dari halaman ini.
  </p>
</section>
