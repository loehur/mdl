<?php
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$pelangganByRef = is_array($data['pelangganByRef'] ?? null) ? $data['pelangganByRef'] : [];

$this->view('non_tunai_admin/_filter', [
    'startDate' => $data['startDate'] ?? date('Y-m-d', strtotime('-6 days')),
    'endDate' => $data['endDate'] ?? date('Y-m-d'),
    'maxRangeDays' => $data['maxRangeDays'] ?? 7,
    'filterAction' => URL::BASE_URL . 'NonTunaiAdmin/bcaMutasi',
    'filterTitle' => 'BCA Mutasi — Data Binding',
    'filterIcon' => 'fa-university',
    'rowCount' => count($rows),
]);
?>

    <?php if ($rows === []) { ?>
      <div class="nta-empty">
        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
        Tidak ada data binding mutasi BCA untuk periode ini
      </div>
    <?php } else { ?>
      <div class="nta-table-wrap">
        <table class="table table-sm table-bordered table-hover nta-table mb-0">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Nominal</th>
              <th>Keterangan</th>
              <th>DB/CR</th>
              <th>Tipe</th>
              <th>Referensi</th>
              <th>Pelanggan</th>
              <th>Waktu Bind</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row) {
              if (!is_array($row)) {
                  continue;
              }
              $entityType = (string) ($row['entity_type'] ?? '');
              $entityRef = (string) ($row['entity_ref'] ?? '');
              $isKasLaundry = ($entityType === 'kas_laundry');
              $pelanggan = '';
              if ($isKasLaundry && isset($pelangganByRef[$entityRef])) {
                  $p = $pelangganByRef[$entityRef];
                  $pelanggan = trim((string) ($p['nama_pelanggan'] ?? ''));
                  if ($pelanggan === '') {
                      $pelanggan = (string) ($p['id_pelanggan'] ?? '');
                  }
              }
              $tanggalLabel = (string) ($row['tanggal'] ?? '');
              if (strtoupper($tanggalLabel) !== 'PEND' && !empty($row['tanggal_iso'])) {
                  $tanggalLabel = date('d/m/Y', strtotime((string) $row['tanggal_iso']));
              }
              $ket = trim((string) ($row['keterangan'] ?? ''));
              $linkedAt = !empty($row['linked_at']) ? date('d/m/Y H:i', strtotime((string) $row['linked_at'])) : '-';
            ?>
            <tr>
              <td><?= htmlspecialchars($tanggalLabel) ?></td>
              <td class="text-end fw-bold">Rp <?= number_format((float) ($row['nominal'] ?? 0), 0, ',', '.') ?></td>
              <td class="nta-ket" title="<?= htmlspecialchars($ket) ?>"><?= htmlspecialchars($ket) ?></td>
              <td><span class="nta-badge"><?= htmlspecialchars((string) ($row['mutasi'] ?? '')) ?></span></td>
              <td><span class="nta-badge nta-badge--type"><?= htmlspecialchars($entityType) ?></span></td>
              <td><code><?= htmlspecialchars($entityRef) ?></code></td>
              <td><?= $pelanggan !== '' ? htmlspecialchars($pelanggan) : '<span class="text-muted">—</span>' ?></td>
              <td><?= htmlspecialchars($linkedAt) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</div>

<script>
(function () {
  var maxDays = <?= (int) ($data['maxRangeDays'] ?? 7) ?> - 1;
  var $form = document.getElementById('ntaFilterForm');
  if (!$form) return;

  $form.addEventListener('submit', function (e) {
    var start = $form.querySelector('[name=start]').value;
    var end = $form.querySelector('[name=end]').value;
    if (!start || !end) return;

    var d1 = new Date(start + 'T00:00:00');
    var d2 = new Date(end + 'T00:00:00');
    var diff = Math.round((d2 - d1) / 86400000);

    if (diff < 0) {
      e.preventDefault();
      alert('Tanggal akhir harus setelah tanggal awal');
      return;
    }
    if (diff >= maxDays + 1) {
      e.preventDefault();
      alert('Rentang tanggal maksimal ' + (maxDays + 1) + ' hari');
    }
  });
})();
</script>
