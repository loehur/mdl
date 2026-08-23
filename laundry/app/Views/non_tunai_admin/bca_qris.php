<?php
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$pelangganByRef = is_array($data['pelangganByRef'] ?? null) ? $data['pelangganByRef'] : [];
$fmtRp = static function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
};

$this->view('non_tunai_admin/_filter', [
    'startDate' => $data['startDate'] ?? date('Y-m-d', strtotime('-6 days')),
    'endDate' => $data['endDate'] ?? date('Y-m-d'),
    'maxRangeDays' => $data['maxRangeDays'] ?? 7,
    'filterAction' => URL::BASE_URL . 'NonTunaiAdmin/bcaQris',
    'filterTitle' => 'BCA QRIS — Data Binding',
    'filterIcon' => 'fa-qrcode',
    'rowCount' => count($rows),
]);
?>

    <?php if ($rows === []) { ?>
      <div class="nta-empty">
        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
        Tidak ada data binding QRIS BCA untuk periode ini
      </div>
    <?php } else { ?>
      <div class="nta-table-wrap">
        <table class="table table-sm table-bordered table-hover nta-table mb-0">
          <thead>
            <tr>
              <th>ID Link</th>
              <th>Tanggal</th>
              <th>Nominal</th>
              <th>Pelanggan</th>
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
              $pRow = ($isKasLaundry && isset($pelangganByRef[$entityRef])) ? $pelangganByRef[$entityRef] : null;

              $tanggal = (string) ($row['tanggal'] ?? '');
              $waktu = trim((string) ($row['waktu'] ?? ''));
              $dateLabel = $tanggal !== '' ? date('d/m/Y', strtotime($tanggal)) : '—';
              if ($waktu !== '') {
                  $dateLabel .= ' ' . $waktu;
              }

              $nominal = $row['bind_nominal'] ?? $row['nominal'] ?? null;
              $billNominal = $row['bill_nominal'] ?? null;
              $ket = trim((string) ($row['keterangan'] ?? ''));
              $linkedAt = !empty($row['linked_at']) ? date('d/m/Y H:i', strtotime((string) $row['linked_at'])) : '—';

              $pelangganHtml = '—';
              if (is_array($pRow) && !empty($pRow['id_pelanggan'])) {
                  $idPlg = (int) $pRow['id_pelanggan'];
                  $namaPlg = trim((string) ($pRow['nama_pelanggan'] ?? ''));
                  if ($namaPlg === '') {
                      $namaPlg = (string) $idPlg;
                  }
                  $namaUpper = mb_strtoupper($namaPlg, 'UTF-8');
                  $urlPlg = 'https://ml.nalju.com/J/tagihan/' . $idPlg;
                  $pelangganHtml = '<a href="' . htmlspecialchars($urlPlg) . '" target="_blank" rel="noopener noreferrer" class="nta-plg-link">'
                      . htmlspecialchars($namaUpper) . '</a>';
              }

              $bindNominal = $row['bind_nominal'] ?? null;
              $selisih = null;
              if ($billNominal !== null && $billNominal !== '') {
                  $selisih = (float) $billNominal - (float) ($bindNominal ?? $row['nominal'] ?? 0);
              }

              $detailPayload = [
                  'title' => 'Detail BCA QRIS #' . (int) ($row['link_id'] ?? 0),
                  'fields' => [
                      ['label' => 'Tanggal', 'value' => $dateLabel],
                      ['label' => 'RRN', 'value' => (string) ($row['rrn'] ?? '')],
                      ['label' => 'Nominal QRIS', 'value' => $fmtRp($row['nominal'] ?? null)],
                      ['label' => 'Bind Snapshot', 'value' => $fmtRp($bindNominal)],
                      ['label' => 'Bill Nominal', 'value' => $fmtRp($billNominal)],
                      ['label' => 'Selisih', 'value' => $selisih !== null ? $fmtRp($selisih) : '—'],
                      ['label' => 'Status', 'value' => (string) ($row['status'] ?? '')],
                      ['label' => 'Outlet', 'value' => (string) ($row['outlet_name'] ?? '')],
                      ['label' => 'Keterangan', 'value' => $ket !== '' ? $ket : '—'],
                      ['label' => 'Tipe Entity', 'value' => $entityType],
                      ['label' => 'Referensi', 'value' => $entityRef],
                      ['label' => 'Pelanggan', 'html' => $pelangganHtml],
                      ['label' => 'Waktu Bind', 'value' => $linkedAt],
                      ['label' => 'ID QRIS', 'value' => (string) ($row['qris_id'] ?? '')],
                  ],
              ];
              $detailJson = json_encode($detailPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
              if (!is_string($detailJson)) {
                  $detailJson = '{}';
              }
            ?>
            <tr>
              <td><?php $this->view('non_tunai_admin/_link_id_cell', [
                  'linkId' => (int) ($row['link_id'] ?? 0),
                  'detailJson' => $detailJson,
              ]); ?></td>
              <td><?= htmlspecialchars($dateLabel) ?></td>
              <td><?php $this->view('non_tunai_admin/_nominal_bind_cell', [
                  'nominal' => $nominal,
                  'billNominal' => $billNominal,
              ]); ?></td>
              <td><?php $this->view('non_tunai_admin/_pelanggan_cell', ['p' => $pRow]); ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</div>

<?php
$this->view('non_tunai_admin/_detail_modal');
$this->view('non_tunai_admin/_filter_script', ['maxRangeDays' => $data['maxRangeDays'] ?? 7]);
?>
