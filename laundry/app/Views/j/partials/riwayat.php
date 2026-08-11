<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$orders = $data['orders'] ?? [];
$customer = $data['customer'] ?? ['id' => $id, 'nama' => $p['nama_pelanggan'], 'hp' => $p['nomor_pelanggan'] ?? ''];
$cabang = $data['cabang'] ?? [];
$namaLaundry = $cabang['nama'] ?? ($cabang['nama_cabang'] ?? 'Laundry');
$kodeCabang = $cabang['kode_cabang'] ?? '00';
$alamatCabang = trim((string) ($cabang['alamat'] ?? ''));
$telpCabang = trim((string) ($cabang['phone_number'] ?? ''));
$namaPelanggan = strtoupper($customer['nama'] ?? $p['nama_pelanggan']);
$filterYm = $data['filter_ym'] ?? date('Y-m');
$monthOptions = $data['month_options'] ?? [];
$bulanNama = [
  1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
  5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
  9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$bulanSingkat = [
  1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
  5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
  9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
];
$ymParts = explode('-', $filterYm);
$filterYear = (int) ($ymParts[0] ?? date('Y'));
$filterMonth = (int) ($ymParts[1] ?? date('n'));
$filterLabel = ($bulanNama[$filterMonth] ?? $filterMonth) . ' ' . $filterYear;
$minYm = !empty($monthOptions) ? (string) $monthOptions[count($monthOptions) - 1]['value'] : date('Y-m', strtotime('-23 months'));
$maxYm = !empty($monthOptions) ? (string) $monthOptions[0]['value'] : date('Y-m');
?>

<div class="j-tagihan j-riwayat">

<div class="j-tagihan-switch">
  <a href="<?= $base ?>J/tagihan/<?= $id ?>" class="j-tagihan-switch__link">Tagihan Berjalan</a>
  <span class="j-tagihan-switch__sep" aria-hidden="true">|</span>
  <a href="<?= $base ?>J/riwayat/<?= $id ?>" class="j-tagihan-switch__link is-active">Riwayat Tagihan</a>
</div>

<div class="j-riwayat-toolbar">
  <div class="j-month-picker"
    id="jRiwayatMonthPicker"
    data-ym="<?= htmlspecialchars($filterYm, ENT_QUOTES, 'UTF-8') ?>"
    data-min="<?= htmlspecialchars($minYm, ENT_QUOTES, 'UTF-8') ?>"
    data-max="<?= htmlspecialchars($maxYm, ENT_QUOTES, 'UTF-8') ?>">
    <span class="j-riwayat-filter-label">Periode</span>
    <button type="button"
      class="j-month-picker__trigger"
      id="jRiwayatBulanBtn"
      aria-haspopup="dialog"
      aria-expanded="false"
      aria-controls="jRiwayatMonthPanel">
      <i class="fas fa-calendar-alt" aria-hidden="true"></i>
      <span class="j-month-picker__label" id="jRiwayatBulanLabel"><?= htmlspecialchars($filterLabel, ENT_QUOTES, 'UTF-8') ?></span>
      <i class="fas fa-chevron-down j-month-picker__caret" aria-hidden="true"></i>
    </button>
    <div class="j-month-picker__panel" id="jRiwayatMonthPanel" hidden role="dialog" aria-label="Pilih bulan">
      <div class="j-month-picker__year">
        <button type="button" class="j-month-picker__nav" id="jRiwayatYearPrev" aria-label="Tahun sebelumnya">
          <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
        <span class="j-month-picker__year-label" id="jRiwayatYearLabel"><?= (int) $filterYear ?></span>
        <button type="button" class="j-month-picker__nav" id="jRiwayatYearNext" aria-label="Tahun berikutnya">
          <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
      </div>
      <div class="j-month-picker__grid" id="jRiwayatMonthGrid">
        <?php for ($m = 1; $m <= 12; $m++) {
          $cellYm = sprintf('%04d-%02d', $filterYear, $m);
          $disabled = ($cellYm < $minYm || $cellYm > $maxYm);
          $selected = ($m === $filterMonth);
        ?>
          <button type="button"
            class="j-month-picker__month<?= $selected ? ' is-selected' : '' ?>"
            data-month="<?= $m ?>"
            <?= $disabled ? ' disabled' : '' ?>>
            <?= htmlspecialchars($bulanSingkat[$m], ENT_QUOTES, 'UTF-8') ?>
          </button>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<?php if (empty($orders)) { ?>
  <div class="j-empty">
    <b>Belum ada riwayat</b>
    Tidak ada order tuntas di bulan ini.
  </div>
<?php } ?>

<?php foreach ($orders as $ord) {
  $sisa = (float) $ord['sisa'];
  $badge = $sisa <= 0 ? ['ok', 'Lunas'] : ['danger', 'Belum lunas'];
  $refSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $ord['no_ref']);
?>
  <article class="j-card">
    <div class="j-card-head">
      <div>
        <div class="j-ref-line">
          <span class="j-ref">REF #<?= htmlspecialchars($ord['no_ref']) ?></span>
          <button type="button"
            class="j-nota-preview-btn"
            data-preview-src="jPreviewSrc<?= htmlspecialchars($refSafe, ENT_QUOTES, 'UTF-8') ?>"
            title="Preview invoice"
            aria-label="Preview invoice">
            <i class="fas fa-file-invoice"></i>
          </button>
          <button type="button" class="j-nota-detail-btn" data-ref="<?= htmlspecialchars($ord['no_ref'], ENT_QUOTES, 'UTF-8') ?>" title="Detail nota" aria-label="Detail nota">
            <i class="fas fa-stream"></i>
          </button>
        </div>
        <strong class="j-nota-date"><?= date('d M Y H:i', strtotime($ord['insertTime'])) ?></strong>
      </div>
      <span class="j-badge <?= $badge[0] ?>"><?= $badge[1] ?></span>
    </div>

    <?php foreach ($ord['items'] as $it) { ?>
      <div class="j-item j-item-grid">
        <div class="j-item-main">
          <div class="j-item-id">#<?= (int) $it['id'] ?></div>
          <strong><?= htmlspecialchars($it['kategori'] ?: 'Item') ?></strong>
          <div class="j-item-meta">
            <span class="<?= !empty($it['durasi_urgent']) ? 'j-durasi-urgent' : '' ?>"><?= htmlspecialchars($it['durasi']) ?></span>
            · <?= htmlspecialchars($it['qty_show']) ?>
          </div>
        </div>
        <div class="j-item-checks">
          <?php if (!empty($it['layanan'])) { ?>
            <?php foreach ($it['layanan'] as $ly) { ?>
              <?php if (!empty($ly['done'])) { ?>
                <span class="j-ly-done">✓ <?= htmlspecialchars($ly['nama']) ?></span>
              <?php } else { ?>
                <span class="j-ly-pending">○ <?= htmlspecialchars($ly['nama']) ?></span>
              <?php } ?>
            <?php } ?>
            <?php if (!empty($it['ambil'])) { ?>
              <span class="j-ly-done">✓ Ambil</span>
            <?php } else { ?>
              <span class="j-ly-pending">○ Ambil</span>
            <?php } ?>
          <?php } ?>
        </div>
        <div class="j-price">
          <?php if ($it['member']) { ?>
            <span class="j-price-member">Member</span>
          <?php } elseif (!empty($it['has_diskon'])) { ?>
            <del>Rp<?= number_format((float) $it['total_asli']) ?></del><br>Rp<?= number_format((float) $it['total']) ?>
          <?php } else { ?>
            Rp<?= number_format((float) $it['total']) ?>
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

  <div class="j-preview-nota-src" id="jPreviewSrc<?= htmlspecialchars($refSafe, ENT_QUOTES, 'UTF-8') ?>" hidden>
    <div class="j-preview-nota">
      <div class="j-preview-nota-head">
        <b>REF #<?= htmlspecialchars($ord['no_ref']) ?></b>
        <span><?= date('d/m/y H:i', strtotime($ord['insertTime'])) ?></span>
      </div>
      <?php foreach ($ord['items'] as $it) { ?>
      <div class="j-preview-line">
        <span>
          <?= htmlspecialchars($it['kategori'] ?: 'Item') ?>
          <small><span class="<?= !empty($it['durasi_urgent']) ? 'j-durasi-urgent' : '' ?>"><?= htmlspecialchars($it['durasi']) ?></span> · <?= htmlspecialchars($it['qty_show']) ?><?= !empty($it['member']) ? ' · Member' : '' ?></small>
        </span>
        <span>
          <?php if (!empty($it['member'])) { ?>
            —
          <?php } elseif (!empty($it['has_diskon'])) { ?>
            <del>Rp<?= number_format((float) $it['total_asli']) ?></del> Rp<?= number_format((float) $it['total']) ?>
          <?php } else { ?>
            Rp<?= number_format((float) $it['total']) ?>
          <?php } ?>
        </span>
      </div>
      <?php } ?>
      <?php foreach ($ord['surcas'] as $sc) { ?>
      <div class="j-preview-line">
        <span><?= htmlspecialchars($sc['nama']) ?></span>
        <span>Rp<?= number_format((float) $sc['jumlah']) ?></span>
      </div>
      <?php } ?>
      <div class="j-preview-nota-foot">
        <span>Subtotal Rp<?= number_format((float) $ord['subtotal']) ?></span>
        <span class="<?= $sisa > 0 ? 'sisa' : 'ok' ?>"><?= $sisa > 0 ? 'Sisa Rp' . number_format($sisa) : 'Lunas' ?></span>
      </div>
    </div>
  </div>
<?php } ?>

<?php if (!empty($orders)) { ?>
<div class="j-preview-overlay" id="jInvoicePreview" hidden>
  <div class="j-preview-sheet">
    <div class="j-preview-toolbar">
      <button type="button" class="j-preview-share" id="jSharePreview" title="Bagikan ke WA" aria-label="Bagikan">
        <i class="fas fa-share-alt"></i>
      </button>
      <button type="button" class="j-preview-dl" id="jDownloadPreview" title="Download gambar" aria-label="Download gambar">
        <i class="fas fa-download"></i>
      </button>
      <button type="button" class="j-preview-close" id="jClosePreview" aria-label="Tutup">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="j-preview-page" id="jPreviewPage" data-nama="<?= htmlspecialchars($namaPelanggan) ?>">
      <div class="j-preview-head">
        <div class="j-preview-branch"><?= htmlspecialchars($namaLaundry) ?> - <?= htmlspecialchars($kodeCabang) ?></div>
        <?php if ($alamatCabang !== '') { ?>
          <div class="j-preview-address"><?= htmlspecialchars($alamatCabang) ?></div>
        <?php } ?>
        <?php if ($telpCabang !== '') { ?>
          <div class="j-preview-address"><?= htmlspecialchars($telpCabang) ?></div>
        <?php } ?>
        <div class="j-preview-customer"><?= htmlspecialchars($namaPelanggan) ?></div>
      </div>
      <div id="jPreviewNotaSlot"></div>
    </div>
  </div>
</div>
<?php } ?>

</div>
