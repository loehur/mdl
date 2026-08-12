<?php
/** @var array $transfers */
$isEmptyCabang = empty($transfers);
?>
<?php if ($isEmptyCabang) { ?>
  <div class="dlv-empty">
    <i class="fas fa-truck" aria-hidden="true"></i>
    <strong>Belum ada order delivery</strong>
    <span>Transfer barang antar cabang yang belum diterima akan tampil di sini.</span>
  </div>
<?php } else { ?>
  <div class="dlv-list">
    <?php foreach ($transfers as $tr) {
      $ref = htmlspecialchars((string) ($tr['ref'] ?? ''), ENT_QUOTES, 'UTF-8');
      $src = htmlspecialchars((string) ($tr['source_kode'] ?? '-'), ENT_QUOTES, 'UTF-8');
      $tgt = htmlspecialchars((string) ($tr['target_kode'] ?? '-'), ENT_QUOTES, 'UTF-8');
      $sourceId = (int) ($tr['source_id'] ?? 0);
      $targetId = (int) ($tr['target_id'] ?? 0);
      $dateRaw = $tr['date'] ?? '';
      $dateLbl = $dateRaw !== '' ? date('d/m/y H:i', strtotime($dateRaw)) : '-';
      $count = (int) ($tr['item_count'] ?? 0);
    ?>
      <div class="dlv-item"
           data-ref="<?= $ref ?>"
           data-source-id="<?= $sourceId ?>"
           data-target-id="<?= $targetId ?>"
           data-source-kode="<?= $src ?>"
           data-target-kode="<?= $tgt ?>">
        <div class="dlv-item__text">
          <p class="dlv-item__title">
            Delivery <span class="dlv-kode dlv-kode-source"><?= $src ?></span>
            → <span class="dlv-kode"><?= $tgt ?></span>
          </p>
          <div class="dlv-item__meta">
            #<?= $ref ?> · <?= htmlspecialchars($dateLbl, ENT_QUOTES, 'UTF-8') ?>
            · <?= $count ?> item
          </div>
        </div>
        <div class="dlv-item__actions">
          <button type="button" class="dlv-btn dlv-btn--cek" data-dlv-cek="<?= $ref ?>">
            <i class="fas fa-search"></i> Cek
          </button>
        </div>
      </div>
    <?php } ?>
  </div>
<?php } ?>
