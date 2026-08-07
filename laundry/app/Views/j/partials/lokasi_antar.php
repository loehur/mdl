<?php
$lokasi = is_array($data['lokasi'] ?? null) ? $data['lokasi'] : [];
$lokasiError = trim((string) ($data['lokasi_error'] ?? ''));
?>

<section class="j-section j-lokasi-antar" data-j-lokasi-antar>
  <div class="j-section-head">
    <h2>Lokasi</h2>
  </div>

  <?php if ($lokasiError !== '') { ?>
    <div class="j-empty j-lokasi-antar__empty">
      <b>Tidak bisa hitung tarif</b>
      <?= htmlspecialchars($lokasiError, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php } elseif (empty($lokasi)) { ?>
    <div class="j-empty j-lokasi-antar__empty">
      <b>Belum ada lokasi</b>
      Simpan lokasi pengantaran dulu agar tarif bisa dihitung.
    </div>
  <?php } else { ?>
    <div class="j-lokasi-antar__list" role="list">
      <?php foreach ($lokasi as $loc) {
         $idLok = (int) ($loc['id_lokasi'] ?? 0);
         $nama = (string) ($loc['nama'] ?? '');
         $detail = (string) ($loc['detail'] ?? '');
         $km = (float) ($loc['km'] ?? 0);
         $tarif = (int) ($loc['tarif'] ?? 0);
         $kmShow = rtrim(rtrim(number_format($km, 1, ',', ''), '0'), ',');
         if ($kmShow === '') {
            $kmShow = '0';
         }
         ?>
        <label class="j-lokasi-antar__item" role="listitem">
          <input
            type="radio"
            name="j_id_lokasi"
            value="<?= $idLok ?>"
            data-tarif="<?= $tarif ?>"
            data-km="<?= htmlspecialchars((string) $km, ENT_QUOTES, 'UTF-8') ?>"
          >
          <span class="j-lokasi-antar__body">
            <span class="j-lokasi-antar__name"><?= htmlspecialchars($nama !== '' ? $nama : ('Lokasi #' . $idLok), ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($detail !== '') { ?>
              <span class="j-lokasi-antar__detail"><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></span>
            <?php } ?>
            <span class="j-lokasi-antar__meta"><?= htmlspecialchars($kmShow, ENT_QUOTES, 'UTF-8') ?> km</span>
          </span>
          <span class="j-lokasi-antar__tarif">
            <span class="j-lokasi-antar__tarif-label">Tarif</span>
            <strong>Rp<?= number_format($tarif, 0, ',', '.') ?></strong>
          </span>
        </label>
      <?php } ?>
    </div>
  <?php } ?>
</section>
