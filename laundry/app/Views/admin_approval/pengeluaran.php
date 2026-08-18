<?php
$pending = is_array($data[0] ?? null) ? $data[0] : [];
$riwayat = is_array($data[1] ?? null) ? $data[1] : [];
?>
<?php if (count($pending) > 0) { ?>
  <div class="aa-section-title">Menunggu konfirmasi</div>
  <div class="aa-grid">
    <?php foreach ($pending as $a) {
      $id = (string) ($a['id_kas'] ?? '');
      $idAttr = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      $f1 = $a['insertTime'];
      $f2 = $a['note'];
      $f3 = $a['id_user'];
      $f4 = $a['jumlah'];
      $note = $a['note_primary'];
      $karyawan = '';
      foreach ($this->userMerge as $c) {
        if ($c['id_user'] == $f3) {
          $karyawan = $c['nama_user'];
        }
      }
    ?>
      <div class="aa-card aa-card--pending">
        <div class="aa-card__meta">#<?= $idAttr ?> · <?= htmlspecialchars($karyawan, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $f1, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__title"><?= htmlspecialchars(strtoupper((string) $note), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__meta"><?= htmlspecialchars(ucwords((string) $f2), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__amount">Rp<?= number_format((float) $f4) ?></div>
        <div class="aa-actions">
          <span class="aa-btn aa-btn--danger nTunai" role="button" data-id="<?= $idAttr ?>" data-target="<?= URL::BASE_URL ?>Pengeluaran/operasi/4">Tolak</span>
          <span class="aa-btn aa-btn--ok nTunai" role="button" data-id="<?= $idAttr ?>" data-target="<?= URL::BASE_URL ?>Pengeluaran/operasi/3">Konfirmasi</span>
        </div>
      </div>
    <?php } ?>
  </div>
<?php } else { ?>
  <div class="aa-empty" style="margin-bottom:14px"><i class="fas fa-check-circle"></i>Tidak ada pengeluaran pending</div>
<?php } ?>

<?php if (count($riwayat) > 0) { ?>
  <div class="aa-section-title">Riwayat</div>
  <div class="aa-grid">
    <?php foreach ($riwayat as $a) {
      $sts = (string) ($a['status_mutasi'] ?? '');
      $stBayar = '';
      foreach ($this->dStatusMutasi as $st) {
        if ($sts == $st['id_status_mutasi']) {
          $stBayar = $st['status_mutasi'];
        }
      }
      $cardCls = ($sts === '3') ? 'aa-card--ok' : (($sts === '4') ? 'aa-card--fail' : '');
      $statusCls = ($sts === '3') ? 'is-ok' : (($sts === '4') ? 'is-fail' : '');
      $id = (string) ($a['id_kas'] ?? '');
      $idAttr = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      $f1 = $a['insertTime'];
      $f2 = $a['note'];
      $f3 = $a['id_user'];
      $f4 = $a['jumlah'];
      $note = $a['note_primary'];
      $karyawan = '';
      foreach ($this->userMerge as $c) {
        if ($c['id_user'] == $f3) {
          $karyawan = $c['nama_user'];
        }
      }
    ?>
      <div class="aa-card <?= $cardCls ?>">
        <div class="aa-card__meta">#<?= $idAttr ?> · <?= htmlspecialchars($karyawan, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $f1, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__title"><?= htmlspecialchars(strtoupper((string) $note), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__meta"><?= htmlspecialchars(ucwords((string) $f2), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__amount">Rp<?= number_format((float) $f4) ?></div>
        <div class="aa-card__status <?= $statusCls ?>"><?= htmlspecialchars((string) $stBayar, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    <?php } ?>
  </div>
<?php } ?>

<script>
  $("#load").off("click.aaPengeluaran").on("click.aaPengeluaran", "span.nTunai", function(e) {
    e.preventDefault();
    var $btn = $(this);
    var isOk = $btn.hasClass("aa-btn--ok");
    aaApproveAjax($btn, {
      tabKey: "Pengeluaran",
      okMsg: isOk ? "Pengeluaran dikonfirmasi" : "Pengeluaran ditolak",
      failMsg: isOk ? "Gagal konfirmasi pengeluaran" : "Gagal menolak pengeluaran",
      emptyHtml: '<div class="aa-empty" style="margin-bottom:14px"><i class="fas fa-check-circle"></i>Tidak ada pengeluaran pending</div>'
    });
  });
</script>
