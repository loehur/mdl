<?php
/**
 * Satu baris customer + request di board Delivery.
 * @var array $rq request enriched (siap_selesai, block_hint, …)
 */
$tail = htmlspecialchars((string) ($rq['phone_tail'] ?? ''), ENT_QUOTES, 'UTF-8');
$phoneShow = htmlspecialchars((string) ($rq['phone_display'] ?? $rq['phone_tail'] ?? ''), ENT_QUOTES, 'UTF-8');
$nama = htmlspecialchars(strtoupper((string) ($rq['nama'] ?? 'Customer')), ENT_QUOTES, 'UTF-8');
$kode = htmlspecialchars((string) ($rq['kode_cabang'] ?? '00'), ENT_QUOTES, 'UTF-8');
$jenis = strtolower((string) ($rq['jenis'] ?? ''));
$layanan = strtolower((string) ($rq['layanan'] ?? 'sameday'));
$jenisOk = ($jenis === 'antar' || $jenis === 'jemput');
$jenisLbl = $jenis === 'antar' ? 'Antar' : ($jenis === 'jemput' ? 'Jemput' : '');
$idReq = (int) ($rq['id_request'] ?? 0);
$prefill = implode(',', array_map('intval', $rq['prefill_ids'] ?? []));
$dateRawR = $rq['insertTime'] ?? '';
$dateLblR = $dateRawR !== '' ? date('d/m/y H:i', strtotime($dateRawR)) : '-';
$pillClass = $jenis === 'antar' ? 'dlv-jenis-pill--antar' : ($jenis === 'jemput' ? 'dlv-jenis-pill--jemput' : '');
$jenisIcon = $jenis === 'jemput' ? 'fa-hand-holding' : 'fa-truck';
$lokNama = trim((string) ($rq['lokasi_nama'] ?? ''));
$lokDetail = trim((string) ($rq['lokasi_detail'] ?? ''));
$lokLatt = $rq['lokasi_latt'] ?? null;
$lokLongt = $rq['lokasi_longt'] ?? null;
$mapsHref = '';
if ($lokLatt !== null && $lokLongt !== null && (float) $lokLatt != 0.0 && (float) $lokLongt != 0.0) {
  $mapsHref = 'https://www.google.com/maps?q=' . rawurlencode(((float) $lokLatt) . ',' . ((float) $lokLongt));
}
$hasLokasi = !empty($rq['has_lokasi']) || ($lokNama !== '' || $lokDetail !== '' || $mapsHref !== '');
$tarifSurcas = isset($rq['tarif_surcas']) && $rq['tarif_surcas'] !== null
  ? (int) $rq['tarif_surcas']
  : '';
$isInstant = $layanan === 'instant';
$canSelesai = !$isInstant || $jenis === 'jemput';
$courierName = trim((string) ($rq['courier_name'] ?? ''));
$bsStatus = trim((string) ($rq['biteship_status'] ?? ''));
$trackUrl = trim((string) ($rq['tracking_url'] ?? ''));
$driverName = trim((string) ($rq['driver_name'] ?? ''));
$ongkir = isset($rq['ongkir']) ? (int) $rq['ongkir'] : 0;
$catatanKurir = trim((string) ($rq['catatan_kurir'] ?? ''));
$surcasBound = !empty($rq['surcas_bound']);
$idPelangganRq = (int) ($rq['id_pelanggan'] ?? 0);
$siapSelesai = !empty($rq['siap_selesai']);
$blockHint = trim((string) ($rq['block_hint'] ?? ''));
$siapItemCount = (int) ($rq['siap_item_count'] ?? 0);
?>
<div class="dlv-item dlv-item--customer dlv-item--group" data-phone-tail="<?= $tail ?>" data-phone-display="<?= $phoneShow ?>" data-source="merged">
  <div class="dlv-item__head-row">
    <div class="dlv-item__text">
      <p class="dlv-item__title">
        <?= $nama ?>
        <span class="dlv-kode">· <?= $kode ?></span>
      </p>
      <div class="dlv-item__meta">
        <?= $phoneShow ?>
      </div>
    </div>
    <div class="dlv-item__actions">
      <button type="button" class="dlv-link-cek" data-dlv-cek-customer="<?= $tail ?>" data-phone-display="<?= $phoneShow ?>" title="Chat" aria-label="Chat">
        <i class="fas fa-comments"></i> Chat
      </button>
    </div>
  </div>

  <div class="dlv-item dlv-item--customer dlv-item--request<?= $isInstant ? ' dlv-item--instant' : '' ?><?= $siapSelesai ? ' dlv-item--request-siap' : ' dlv-item--request-belum' ?>"
       data-id-request="<?= $idReq ?>"
       data-id-pelanggan="<?= $idPelangganRq ?>"
       data-phone-tail="<?= $tail ?>"
       data-source="customer"
       data-layanan="<?= htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8') ?>"
       data-tarif-surcas="<?= htmlspecialchars((string) $tarifSurcas, ENT_QUOTES, 'UTF-8') ?>"
       data-surcas-bound="<?= $surcasBound ? '1' : '0' ?>">
    <div class="dlv-item__text">
      <p class="dlv-item__title">
        <?php if ($jenisOk) { ?>
          <span class="dlv-jenis-pill <?= $pillClass ?>">
            <i class="fas <?= $jenisIcon ?>" aria-hidden="true"></i>
            <?= htmlspecialchars($jenisLbl, ENT_QUOTES, 'UTF-8') ?>
          </span>
        <?php } ?>
        <?php if ($isInstant) { ?>
          <span class="dlv-jenis-pill" style="background:#fff3cd;color:#856404">Instant</span>
        <?php } ?>
        <?php if (!$hasLokasi && !$isInstant) { ?>
          <span class="dlv-jenis-pill" style="background:#fef3c7;color:#92400e">Lokasi menyusul</span>
        <?php } ?>
        <?php if ($siapSelesai && $siapItemCount > 0) { ?>
          <span class="dlv-jenis-pill" style="background:#dcfce7;color:#166534"><?= $siapItemCount ?> item siap</span>
        <?php } elseif (!$siapSelesai && $blockHint !== '') { ?>
          <span class="dlv-jenis-pill" style="background:#fee2e2;color:#991b1b"><?= htmlspecialchars($blockHint, ENT_QUOTES, 'UTF-8') ?></span>
        <?php } ?>
        <span class="dlv-kode">#<?= $idReq ?></span>
      </p>
      <div class="dlv-item__meta">
        <?= htmlspecialchars($dateLblR, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($isInstant && $ongkir > 0) { ?>
          · Ongkir Rp<?= number_format($ongkir, 0, ',', '.') ?>
        <?php } elseif ($jenis === 'jemput' && $tarifSurcas !== '' && (int) $tarifSurcas > 0 && !$isInstant) { ?>
          · Tarif Rp<?= number_format((int) $tarifSurcas, 0, ',', '.') ?>
        <?php } ?>
      </div>
      <?php if ($isInstant && ($courierName !== '' || $bsStatus !== '' || $driverName !== '')) { ?>
        <div class="dlv-item__meta">
          <?php if ($courierName !== '') { ?>
            <i class="fas fa-motorcycle"></i> <?= htmlspecialchars($courierName, ENT_QUOTES, 'UTF-8') ?>
          <?php } ?>
          <?php if ($bsStatus !== '') { ?> · <?= htmlspecialchars($bsStatus, ENT_QUOTES, 'UTF-8') ?><?php } ?>
          <?php if ($driverName !== '') { ?> · Driver <?= htmlspecialchars($driverName, ENT_QUOTES, 'UTF-8') ?><?php } ?>
          <?php if ($trackUrl !== '') { ?> · <a href="<?= htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Track</a><?php } ?>
        </div>
      <?php } ?>
      <?php if ($hasLokasi) { ?>
        <div class="dlv-item__meta dlv-item__lokasi">
          <i class="fas fa-map-marker-alt"></i>
          <?= htmlspecialchars($lokNama !== '' ? $lokNama : 'Lokasi', ENT_QUOTES, 'UTF-8') ?>
          <?php if ($lokDetail !== '') { ?> · <?= htmlspecialchars($lokDetail, ENT_QUOTES, 'UTF-8') ?><?php } ?>
          <?php if ($mapsHref !== '') { ?> · <a href="<?= htmlspecialchars($mapsHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Maps</a><?php } ?>
          <?php if ($mapsHref !== '') { ?>
            <button type="button"
                    class="dlv-icon-btn"
                    data-dlv-share-lokasi="<?= $idReq ?>"
                    title="Share Maps"
                    aria-label="Share Maps">
              <i class="fas fa-share-alt"></i>
            </button>
          <?php } ?>
        </div>
      <?php } elseif (!$isInstant) { ?>
        <div class="dlv-item__meta dlv-item__lokasi">
          <i class="fas fa-map-marker-alt"></i>
          Lokasi belum lengkap
          <?php if ($idPelangganRq > 0) { ?>
            <button type="button"
                    class="dlv-icon-btn"
                    data-dlv-tarik-lokasi="<?= $idReq ?>"
                    data-id-pelanggan="<?= $idPelangganRq ?>"
                    title="Tarik lokasi pelanggan"
                    aria-label="Tarik lokasi">
              <i class="fas fa-sync-alt"></i>
            </button>
          <?php } ?>
        </div>
      <?php } ?>
      <?php if ($catatanKurir !== '') { ?>
        <div class="dlv-item__meta">
          <i class="fas fa-sticky-note"></i>
          Catatan: <?= htmlspecialchars($catatanKurir, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php } ?>
    </div>
    <div class="dlv-item__actions">
      <?php if ($canSelesai && $jenis === 'antar' && !$isInstant) { ?>
        <button type="button"
                class="dlv-btn dlv-btn--pending"
                data-dlv-pending-request="<?= $idReq ?>"
                data-nama="<?= $nama ?>">
          <i class="fas fa-pause"></i> Pending
        </button>
      <?php } ?>
      <?php if ($canSelesai && $jenisOk) { ?>
        <button type="button"
                class="dlv-btn dlv-btn--selesai"
                data-dlv-selesai-request="<?= $idReq ?>"
                data-id-pelanggan="<?= $idPelangganRq ?>"
                data-phone-tail="<?= $tail ?>"
                data-phone-display="<?= $phoneShow ?>"
                data-jenis="<?= htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') ?>"
                data-layanan="<?= htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8') ?>"
                data-prefill="<?= htmlspecialchars($prefill, ENT_QUOTES, 'UTF-8') ?>"
                data-tarif-surcas="<?= htmlspecialchars($isInstant ? '' : (string) $tarifSurcas, ENT_QUOTES, 'UTF-8') ?>"
                data-surcas-bound="<?= $surcasBound ? '1' : '0' ?>"
                data-nama="<?= $nama ?>">
          <i class="fas fa-check"></i> Selesai <?= htmlspecialchars($jenisLbl, ENT_QUOTES, 'UTF-8') ?>
        </button>
      <?php } elseif ($canSelesai) { ?>
        <button type="button"
                class="dlv-btn dlv-btn--selesai"
                data-dlv-selesai-request="<?= $idReq ?>"
                data-id-pelanggan="<?= $idPelangganRq ?>"
                data-phone-tail="<?= $tail ?>"
                data-phone-display="<?= $phoneShow ?>"
                data-jenis="<?= htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') ?>"
                data-layanan="<?= htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8') ?>"
                data-prefill="<?= htmlspecialchars($prefill, ENT_QUOTES, 'UTF-8') ?>"
                data-tarif-surcas="<?= htmlspecialchars($isInstant ? '' : (string) $tarifSurcas, ENT_QUOTES, 'UTF-8') ?>"
                data-surcas-bound="<?= $surcasBound ? '1' : '0' ?>"
                data-nama="<?= $nama ?>">
          <i class="fas fa-check"></i> Selesai
        </button>
      <?php } else { ?>
        <span class="dlv-item__meta" style="align-self:center;opacity:.75">Track only</span>
      <?php } ?>
    </div>
  </div>
</div>
