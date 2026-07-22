<?php
$rows = $data['rows'] ?? [];
$totals = $data['totals'] ?? [];
$today = $data['today'] ?? date('Y-m-d');
$yesterday = $data['yesterday'] ?? date('Y-m-d', strtotime('-1 day'));
$layananOk = !empty($data['layanan_ok']);
?>

<?php if (!$layananOk) { ?>
  <div class="alert alert-warning">Layanan <b>Cuci</b> tidak ditemukan di master layanan. Periksa nama layanan di database.</div>
<?php } ?>

<div class="row g-2 mb-3">
  <div class="col-md-4">
    <div class="dash-kpi dash-kpi--today">
      <div class="dash-kpi__label">Hari Ini</div>
      <div class="dash-kpi__value"><?= htmlspecialchars($this->fmtDecMax2($totals['today']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dash-kpi__sub"><?= (int) ($totals['today']['nota'] ?? 0) ?> nota · <?= htmlspecialchars(date('d/m/Y', strtotime($today)), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="dash-kpi dash-kpi--yesterday">
      <div class="dash-kpi__label">Kemarin</div>
      <div class="dash-kpi__value"><?= htmlspecialchars($this->fmtDecMax2($totals['yesterday']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dash-kpi__sub"><?= (int) ($totals['yesterday']['nota'] ?? 0) ?> nota · <?= htmlspecialchars(date('d/m/Y', strtotime($yesterday)), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="dash-kpi">
      <div class="dash-kpi__label">Total 2 Hari</div>
      <div class="dash-kpi__value"><?= htmlspecialchars($this->fmtDecMax2($totals['total']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dash-kpi__sub"><?= (int) ($totals['total']['nota'] ?? 0) ?> nota · seluruh cabang</div>
    </div>
  </div>
</div>

<div class="card dash-card shadow-sm border-0">
  <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center">
    <div class="fw-bold">Antrian Cuci — Ringkasan per Cabang</div>
    <small class="text-muted">Order mengandung Cuci · by tanggal order</small>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-sm table-hover mb-0 dash-table">
      <thead class="table-light">
        <tr>
          <th class="ps-3">Cabang</th>
          <th class="text-end">Hari Ini</th>
          <th class="text-end">Kemarin</th>
          <th class="text-end pe-3">Total</th>
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
              <td class="text-end">
                <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($row['today_qty']), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="dash-nota"><?= (int) $row['today_nota'] ?> nota</div>
              </td>
              <td class="text-end">
                <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($row['yesterday_qty']), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="dash-nota"><?= (int) $row['yesterday_nota'] ?> nota</div>
              </td>
              <td class="text-end pe-3">
                <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($row['total_qty']), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="dash-nota"><?= (int) $row['total_nota'] ?> nota</div>
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
              <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($totals['today']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="dash-nota"><?= (int) ($totals['today']['nota'] ?? 0) ?> nota</div>
            </th>
            <th class="text-end">
              <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($totals['yesterday']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="dash-nota"><?= (int) ($totals['yesterday']['nota'] ?? 0) ?> nota</div>
            </th>
            <th class="text-end pe-3">
              <div class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($totals['total']['qty'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="dash-nota"><?= (int) ($totals['total']['nota'] ?? 0) ?> nota</div>
            </th>
          </tr>
        </tfoot>
      <?php } ?>
    </table>
  </div>
</div>
