<?php
$pending = is_array($data['list'] ?? null) ? $data['list'] : [];
?>
<?php if (count($pending) > 0) { ?>
  <div class="aa-section-title">Menunggu konfirmasi</div>
  <div class="aa-grid">
    <?php foreach ($pending as $a) {
      $id = (string) ($a['id_kas'] ?? '');
      $idAttr = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      $f1 = $a['insertTime'];
      $f2 = trim((string) ($a['note'] ?? ''));
      if ($f2 === '') $f2 = trim((string) ($a['keterangan'] ?? ''));
      $f3 = $a['id_user'];
      $f4 = $a['jumlah'];
      $idCabang = (int) ($a['id_cabang'] ?? 0);
      $karyawan = '';
      foreach ($this->userMerge as $c) {
        if ($c['id_user'] == $f3) {
          $karyawan = $c['nama_user'];
        }
      }
    ?>
      <div class="aa-card aa-card--pending" data-id-cabang="<?= $idCabang ?>">
        <?php if ($idCabang > 0) { ?>
          <span class="aa-cabang-badge"><?= htmlspecialchars($this->cabangKodeById($idCabang), ENT_QUOTES, 'UTF-8') ?></span>
        <?php } ?>
        <div class="aa-card__meta">#<?= $idAttr ?> · <?= htmlspecialchars($karyawan, ENT_QUOTES, 'UTF-8') ?> · <?= date('d/m/Y H:i', strtotime($f1)) ?></div>
        <div class="aa-card__title"><?= htmlspecialchars(strtoupper((string) $f2), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__amount">Rp<?= number_format((float) $f4) ?></div>
        <div class="aa-actions">
          <span class="aa-btn aa-btn--danger nTunai" role="button" data-id="<?= $idAttr ?>" data-id-cabang="<?= $idCabang ?>" data-target="<?= URL::BASE_URL ?>Setoran/operasi/4">Tolak</span>
          <span class="aa-btn aa-btn--ok nTunai" role="button" data-id="<?= $idAttr ?>" data-id-cabang="<?= $idCabang ?>" data-target="<?= URL::BASE_URL ?>Setoran/operasi/3">Konfirmasi</span>
        </div>
      </div>
    <?php } ?>
  </div>
<?php } else { ?>
  <div class="aa-empty" style="margin-bottom:14px"><i class="fas fa-check-circle"></i>Tidak ada setoran pending</div>
<?php } ?>

<script>
  $("#load").off("click.aaSetoran").on("click.aaSetoran", "span.nTunai", function(e) {
    e.preventDefault();
    var $btn = $(this);
    var isOk = $btn.hasClass("aa-btn--ok");
    aaApproveAjax($btn, {
      tabKey: "Setoran",
      okMsg: isOk ? "Setoran dikonfirmasi" : "Setoran ditolak",
      failMsg: isOk ? "Gagal konfirmasi setoran" : "Gagal menolak setoran",
      emptyHtml: '<div class="aa-empty" style="margin-bottom:14px"><i class="fas fa-check-circle"></i>Tidak ada setoran pending</div>'
    });
  });
</script>
