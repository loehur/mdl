<?php
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$isBoundView = !empty($data['isBoundView']);
$unboundRows = $isBoundView ? [] : $rows;
$unboundTotalNominal = $isBoundView ? 0 : array_sum(array_map(static fn ($row) => (float) ($row['nominal'] ?? 0), $rows));
if (!$isBoundView) { $rows = []; }
$pelangganByRef = is_array($data['pelangganByRef'] ?? null) ? $data['pelangganByRef'] : [];
$payerByRef = is_array($data['payerByRef'] ?? null) ? $data['payerByRef'] : $pelangganByRef;
$fmtNominal = static function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, 0, ',', '.');
};

$this->view('non_tunai_admin/_filter', [
    'startDate' => $data['startDate'] ?? date('Y-m-d', strtotime('-6 days')),
    'endDate' => $data['endDate'] ?? date('Y-m-d'),
    'maxRangeDays' => $data['maxRangeDays'] ?? 7,
    'filterAction' => URL::BASE_URL . ($isBoundView ? 'NonTunaiAdmin/bcaQrisTerikat' : 'NonTunaiAdmin/bcaQrisLepas'),
    'filterTitle' => $isBoundView ? 'QRIS Terikat' : 'QRIS Lepas',
    'filterIcon' => 'fa-qrcode',
    'rowCount' => count($rows),
    'unboundCount' => $isBoundView ? 0 : count($unboundRows),
    'unboundTotalNominal' => $unboundTotalNominal,
]);
?>

    <div class="nta-section">
      <h6 class="nta-section-title">
        <span><i class="fas fa-unlink me-1"></i>Belum Bind</span>
         <span class="nta-count nta-count__unbound"><?= count($unboundRows) ?> transaksi · <?= $fmtNominal($unboundTotalNominal) ?></span>
      </h6>

      <?php if ($unboundRows === []) { ?>
        <div class="nta-empty">
          <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
          Semua transaksi QRIS periode ini sudah ter-bind
        </div>
      <?php } else { ?>
        <div class="nta-table-wrap">
          <table class="table table-sm table-bordered table-hover nta-table mb-0">
            <thead>
              <tr>
                <th>ID QRIS</th>
                <th>Tanggal</th>
                <th>Nominal</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($unboundRows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $qrisId = (int) ($row['qris_id'] ?? 0);
                $tanggal = (string) ($row['tanggal'] ?? '');
                $waktu = trim((string) ($row['waktu'] ?? ''));
                $dateLabel = $tanggal !== '' ? date('d/m/Y', strtotime($tanggal)) : '—';
                if ($waktu !== '') {
                    $dateLabel .= ' ' . $waktu;
                }

                $nominal = $row['nominal'] ?? null;
                $ket = trim((string) ($row['keterangan'] ?? ''));

                $detailPayload = [
                    'title' => 'Mutasi QRIS Belum Bind #' . $qrisId,
                    'fields' => [
                        ['label' => 'Status Bind', 'value' => 'Belum bind'],
                        ['label' => 'Tanggal', 'value' => $dateLabel],
                        ['label' => 'Nominal', 'value' => $fmtNominal($nominal)],
                        ['label' => 'RRN', 'value' => (string) ($row['rrn'] ?? '')],
                        ['label' => 'Status QRIS', 'value' => (string) ($row['status'] ?? '')],
                        ['label' => 'Outlet', 'value' => (string) ($row['outlet_name'] ?? '')],
                        ['label' => 'Keterangan', 'value' => $ket !== '' ? $ket : '—'],
                        ['label' => 'ID QRIS', 'value' => (string) $qrisId],
                    ],
                ];
                $detailJson = json_encode($detailPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($detailJson)) {
                    $detailJson = '{}';
                }
              ?>
              <tr>
                <td><?php $this->view('non_tunai_admin/_link_id_cell', [
                    'linkId' => $qrisId,
                    'detailJson' => $detailJson,
                ]); ?></td>
                <td><?= htmlspecialchars($dateLabel) ?></td>
                <td class="text-end"><span class="nta-nominal-single"><?= $fmtNominal($nominal) ?></span></td>
                <td><span class="nta-badge nta-badge--unbound">Belum bind</span></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<?php
$this->view('non_tunai_admin/_detail_modal');
$this->view('non_tunai_admin/_filter_script', ['maxRangeDays' => $data['maxRangeDays'] ?? 7]);
?>

    <?php if ($rows === []) { ?>
      <div class="nta-empty">
        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
        Tidak ada data binding Mutasi QRIS untuk periode ini
      </div>
    <?php } else { ?>
      <div class="nta-table-wrap">
        <table class="table table-sm table-bordered table-hover nta-table mb-0">
          <thead>
            <tr>
              <th class="text-end">ID Link</th>
              <th>Tanggal</th>
              <th class="text-end">Nominal</th>
              <th class="text-end">Bill</th>
              <th class="text-start">Payer</th>
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
              $payerRow = ($isKasLaundry && isset($payerByRef[(string) $entityRef])) ? $payerByRef[(string) $entityRef] : null;

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

              $payerHtml = '—';
              if (is_array($payerRow) && trim((string) ($payerRow['name'] ?? '')) !== '') {
                  $namaUpper = mb_strtoupper(trim((string) $payerRow['name']), 'UTF-8');
                  $badge = trim((string) ($payerRow['badge'] ?? ''));
                  $urlPayer = trim((string) ($payerRow['url'] ?? ''));
                  if ($urlPayer !== '') {
                      $payerHtml = '<a href="' . htmlspecialchars($urlPayer, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="nta-plg-link">'
                          . htmlspecialchars($namaUpper) . '</a>';
                  } else {
                      $payerHtml = '<span class="nta-plg-link nta-plg-link--plain">' . htmlspecialchars($namaUpper) . '</span>';
                  }
                  if ($badge !== '') {
                      $payerHtml .= ' <small class="text-muted">(' . htmlspecialchars($badge) . ')</small>';
                  }
              }

              $bindNominal = $row['bind_nominal'] ?? null;
              $selisih = null;
              if ($billNominal !== null && $billNominal !== '') {
                  $selisih = (float) $billNominal - (float) ($bindNominal ?? $row['nominal'] ?? 0);
              }

              $detailPayload = [
                  'title' => 'Detail Mutasi QRIS #' . (int) ($row['link_id'] ?? 0),
                  'fields' => [
                      ['label' => 'Tanggal', 'value' => $dateLabel],
                      ['label' => 'RRN', 'value' => (string) ($row['rrn'] ?? '')],
                       ['label' => 'Nominal QRIS', 'value' => $fmtNominal($row['nominal'] ?? null)],
                       ['label' => 'Bind Snapshot', 'value' => $fmtNominal($bindNominal)],
                       ['label' => 'Bill Nominal', 'value' => $fmtNominal($billNominal)],
                       ['label' => 'Selisih', 'value' => $selisih !== null ? $fmtNominal($selisih) : '—'],
                      ['label' => 'Status', 'value' => (string) ($row['status'] ?? '')],
                      ['label' => 'Outlet', 'value' => (string) ($row['outlet_name'] ?? '')],
                      ['label' => 'Keterangan', 'value' => $ket !== '' ? $ket : '—'],
                      ['label' => 'Tipe Entity', 'value' => $entityType],
                      ['label' => 'Referensi', 'value' => $entityRef],
                      ['label' => 'Jenis Kas', 'value' => is_array($payerRow) ? (string) ($payerRow['badge'] ?? '—') : '—'],
                      ['label' => 'Payer', 'html' => $payerHtml],
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
              <td class="text-end"><?php $this->view('non_tunai_admin/_link_id_cell', [
                  'linkId' => (int) ($row['link_id'] ?? 0),
                  'detailJson' => $detailJson,
              ]); ?></td>
              <td><?= htmlspecialchars($dateLabel) ?></td>
               <td class="text-end"><span class="nta-nominal-single"><?= $fmtNominal($nominal) ?></span></td>
               <td class="text-end"><span class="nta-nominal-single"><?= $fmtNominal($billNominal) ?></span></td>
              <td class="text-start"><?php $this->view('non_tunai_admin/_payer_cell', ['payer' => $payerRow]); ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
