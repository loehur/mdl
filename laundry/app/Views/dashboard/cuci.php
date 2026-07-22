<?php
$rows = $data['rows'] ?? [];
$layananOk = !empty($data['layanan_ok']);
?>

<?php if (!$layananOk) { ?>
  <div class="alert alert-warning">Layanan <b>Cuci</b> tidak ditemukan di master layanan. Periksa nama layanan di database.</div>
<?php } ?>

<div class="card dash-card shadow-sm border-0">
  <div class="card-header py-2 px-3">
    <div class="fw-bold">Antri Cuci — Ringkasan per Cabang</div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-sm table-hover mb-0 dash-table">
      <thead class="table-light">
        <tr>
          <th class="ps-3">Cabang</th>
          <th class="text-end">Prioritas</th>
          <th class="text-end">Hari Ini</th>
          <th class="text-end pe-3">Kemarin</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) === 0) { ?>
          <tr>
            <td colspan="4" class="text-center text-muted py-4">Tidak ada cabang operasional.</td>
          </tr>
        <?php } else {
          foreach ($rows as $row) { ?>
            <tr>
              <td class="ps-3 fw-semibold"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end"><span class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($row['prioritas_qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span></td>
              <td class="text-end"><span class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($row['today_qty']), ENT_QUOTES, 'UTF-8') ?></span></td>
              <td class="text-end pe-3"><span class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($row['yesterday_qty']), ENT_QUOTES, 'UTF-8') ?></span></td>
            </tr>
          <?php }
        } ?>
      </tbody>
    </table>
  </div>
</div>
