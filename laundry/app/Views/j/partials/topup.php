<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$base = $data['base'];
$filter = (int) ($data['filterIdHarga'] ?? 0);
$catalog = $data['catalog'] ?? [];
$backHref = $filter > 0
   ? $base . 'J/paketDetail/' . $id . '/' . $filter
   : $base . 'J/paket/' . $id;
$title = $filter > 0 ? ('Topup Paket M' . $filter) : 'Topup Paket';
$subtitle = $filter > 0
   ? 'Pilih jumlah (qty) untuk paket ini'
   : 'Pilih paket yang ingin ditambahkan';
?>

<section class="j-section" style="margin-top:0">
  <div style="margin-bottom:12px">
    <a class="j-badge muted" href="<?= htmlspecialchars($backHref) ?>"><i class="fas fa-arrow-left"></i> Kembali</a>
  </div>

  <div class="j-section-head">
    <h2><?= htmlspecialchars($title) ?></h2>
  </div>
  <p style="margin:0 0 12px;font-size:0.82rem;color:var(--j-muted)"><?= htmlspecialchars($subtitle) ?></p>

  <?php if (empty($catalog)) { ?>
    <div class="j-empty">
      <b>Belum ada opsi topup</b>
      Paket topup belum tersedia<?= $filter > 0 ? ' untuk M' . $filter : '' ?>.
    </div>
  <?php } else { ?>
    <div class="j-paket-grid">
      <?php foreach ($catalog as $item) {
         $idPaket = (int) $item['id_harga_paket'];
         $idHarga = (int) $item['id_harga'];
         $qtyLabel = $this->fmtDecMax2($item['qty']) . htmlspecialchars($item['satuan']);
         $hargaLabel = 'Rp' . number_format((int) $item['harga']);
      ?>
        <div class="j-card j-topup-card">
          <strong>M<?= $idHarga ?></strong>
          <small><?= htmlspecialchars($item['label']) ?></small>
          <div class="j-chip-row">
            <span class="j-badge ok"><?= $qtyLabel ?></span>
            <span class="j-badge muted"><?= $hargaLabel ?></span>
          </div>
          <button
            type="button"
            class="j-btn j-btn-primary j-btn-block j-topup-pick"
            data-id-harga-paket="<?= $idPaket ?>"
            data-label="M<?= $idHarga ?> · <?= $qtyLabel ?> · <?= $hargaLabel ?>">
            Pilih
          </button>
        </div>
      <?php } ?>
    </div>
  <?php } ?>
</section>
