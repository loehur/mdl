<?php
$rows = $data['rows'] ?? [];
$totals = $data['totals'] ?? [];
$today = $data['today'] ?? date('Y-m-d');
$tomorrow = $data['tomorrow'] ?? date('Y-m-d', strtotime('+1 day'));
$layananOk = !empty($data['layanan_ok']);
?>

<?php if (!$layananOk) { ?>
  <div class="alert alert-warning">Layanan <b>Setrika</b> tidak ditemukan di master layanan. Periksa nama layanan di database.</div>
<?php } ?>

<div class="row g-2 mb-3">
  <div class="col-md-6">
    <div class="dash-kpi dash-kpi--due">
      <div class="dash-kpi__label">Lewat + Hari Ini</div>
      <div class="dash-kpi__value"><?= htmlspecialchars($this->fmtDecMax2($totals['due']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dash-kpi__sub"><?= (int) ($totals['due']['nota'] ?? 0) ?> nota · deadline ≤ <?= htmlspecialchars(date('d/m/Y', strtotime($today)), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="dash-kpi dash-kpi--besok">
      <div class="dash-kpi__label">Besok</div>
      <div class="dash-kpi__value"><?= htmlspecialchars($this->fmtDecMax2($totals['besok']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dash-kpi__sub"><?= (int) ($totals['besok']['nota'] ?? 0) ?> nota · deadline <?= htmlspecialchars(date('d/m/Y', strtotime($tomorrow)), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
</div>

<div class="card dash-card shadow-sm border-0">
  <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center">
    <div class="fw-bold">Antrian Setrika/Pack — Ringkasan per Cabang</div>
    <small class="text-muted">By deadline · Setrika pending + Pack/RAK digabung</small>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-sm table-hover mb-0 dash-table">
      <thead class="table-light">
        <tr>
          <th class="ps-3">Cabang</th>
          <th class="text-end">Lewat + Hari Ini</th>
          <th class="text-end pe-3">Besok</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) === 0) { ?>
          <tr>
            <td colspan="3" class="text-center text-muted py-4">Tidak ada cabang operasional.</td>
          </tr>
        <?php } else {
          foreach ($rows as $row) { ?>
            <tr>
              <td class="ps-3 fw-semibold"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end">
                <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($row['due_qty']), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="dash-nota"><?= (int) $row['due_nota'] ?> nota</div>
              </td>
              <td class="text-end pe-3">
                <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($row['besok_qty']), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="dash-nota"><?= (int) $row['besok_nota'] ?> nota</div>
              </td>
            </tr>
          <?php }
        } ?>
      </tbody>
      <?php if (count($rows) > 0) { ?>
        <tfoot class="table-light">
          <tr>
            <th class="ps-3">Total Laundry</th>
            <th class="text-end">
              <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($totals['due']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="dash-nota"><?= (int) ($totals['due']['nota'] ?? 0) ?> nota</div>
            </th>
            <th class="text-end pe-3">
              <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($totals['besok']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="dash-nota"><?= (int) ($totals['besok']['nota'] ?? 0) ?> nota</div>
            </th>
          </tr>
        </tfoot>
      <?php } ?>
    </table>
  </div>
</div>
