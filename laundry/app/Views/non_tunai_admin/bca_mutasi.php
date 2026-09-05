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
    'filterAction' => URL::BASE_URL . ($isBoundView ? 'NonTunaiAdmin/bcaMutasiTerikat' : 'NonTunaiAdmin/bcaMutasiLepas'),
    'filterTitle' => $isBoundView ? 'BCA Terikat' : 'BCA Lepas',
    'filterIcon' => 'fa-university',
    'rowCount' => count($rows),
    'unboundCount' => $isBoundView ? 0 : count($unboundRows),
    'unboundTotalNominal' => $unboundTotalNominal,
]);
?>

    <?php if (!$isBoundView) { ?><div class="nta-section">
      <h6 class="nta-section-title">
        <span><i class="fas fa-unlink me-1"></i>Belum Bind</span>
         <span class="nta-count nta-count__unbound"><?= count($unboundRows) ?> transaksi · <?= $fmtNominal($unboundTotalNominal) ?></span>
      </h6>

      <?php if ($unboundRows === []) { ?>
        <div class="nta-empty">
          <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
          Tidak ada data BCA Terikat untuk periode ini
        </div>
      <?php } else { ?>
        <div class="nta-table-wrap">
          <table class="table table-sm table-bordered table-hover nta-table mb-0">
            <thead>
              <tr>
                <th>ID Mutasi</th>
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

                $mutasiId = (int) ($row['mutasi_id'] ?? 0);
                $tanggalLabel = (string) ($row['tanggal'] ?? '');
                if (strtoupper($tanggalLabel) !== 'PEND' && !empty($row['tanggal_iso'])) {
                    $tanggalLabel = date('d/m/Y', strtotime((string) $row['tanggal_iso']));
                }

                $nominal = $row['nominal'] ?? null;
                $ket = trim((string) ($row['keterangan'] ?? ''));
                 $mutasiCreated = !empty($row['mutasi_created_at']) ? date('d/m/Y H:i', strtotime((string) $row['mutasi_created_at'])) : '—';

                $detailPayload = [
                    'title' => 'Mutasi BCA Belum Bind #' . $mutasiId,
                    'fields' => [
                        ['label' => 'Status', 'value' => 'Belum bind'],
                        ['label' => 'Tanggal Mutasi', 'value' => $tanggalLabel],
                         ['label' => 'Nominal', 'value' => $fmtNominal($nominal)],
                        ['label' => 'Keterangan', 'value' => $ket !== '' ? $ket : '—'],
                        ['label' => 'Created Mutasi', 'value' => $mutasiCreated],
                        ['label' => 'ID Mutasi', 'value' => (string) $mutasiId],
                    ],
                ];
                $detailJson = json_encode($detailPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($detailJson)) {
                    $detailJson = '{}';
                }
              ?>
              <tr>
                <td><?php $this->view('non_tunai_admin/_link_id_cell', [
                    'linkId' => $mutasiId,
                    'detailJson' => $detailJson,
                ]); ?></td>
                <td><?= htmlspecialchars($tanggalLabel) ?></td>
                <td class="text-end"><span class="nta-nominal-single"><?= $fmtNominal($nominal) ?></span></td>
                <td><span class="nta-badge nta-badge--unbound">Belum bind</span></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } ?>
    </div><?php } ?>

    <?php if ($rows === []) { ?>
      <div class="nta-empty">
        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
         Tidak ada data BCA Lepas untuk periode ini
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
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row) {
              if (!is_array($row)) {
                  continue;
              }
              $entityType = (string) ($row['entity_type'] ?? '');
              $entityRef = (string) ($row['entity_ref'] ?? '');
              $linkId = (int) ($row['link_id'] ?? 0);
              $payerRow = isset($payerByRef[(string) $entityRef]) ? $payerByRef[(string) $entityRef] : null;

              $entityLabels = [
                  'kas_laundry' => 'Laundry',
                  'invoice' => 'Invoice',
                  'salon_subscription' => 'Salon',
              ];
              $entityLabel = $entityLabels[$entityType] ?? $entityType;

              $tanggalLabel = (string) ($row['tanggal'] ?? '');
              if (strtoupper($tanggalLabel) !== 'PEND' && !empty($row['tanggal_iso'])) {
                  $tanggalLabel = date('d/m/Y', strtotime((string) $row['tanggal_iso']));
              }

              $nominal = $row['bind_nominal'] ?? $row['nominal'] ?? null;
              $billNominal = $row['bill_nominal'] ?? null;
              $ket = trim((string) ($row['keterangan'] ?? ''));
              $linkedAt = !empty($row['linked_at']) ? date('d/m/Y H:i', strtotime((string) $row['linked_at'])) : '—';
              $mutasiCreated = !empty($row['mutasi_created_at']) ? date('d/m/Y H:i', strtotime((string) $row['mutasi_created_at'])) : '—';

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
                  'title' => 'Detail Mutasi BCA #' . (int) ($row['link_id'] ?? 0),
                  'fields' => [
                      ['label' => 'Tanggal Mutasi', 'value' => $tanggalLabel],
                       ['label' => 'Nominal Mutasi', 'value' => $fmtNominal($row['nominal'] ?? null)],
                       ['label' => 'Bind Snapshot', 'value' => $fmtNominal($bindNominal)],
                       ['label' => 'Bill Nominal', 'value' => $fmtNominal($billNominal)],
                       ['label' => 'Selisih', 'value' => $selisih !== null ? $fmtNominal($selisih) : '—'],
                      ['label' => 'Keterangan', 'value' => $ket !== '' ? $ket : '—'],
                      ['label' => 'Tipe Entity', 'value' => $entityLabel . ($entityType !== '' ? ' (' . $entityType . ')' : '')],
                      ['label' => 'Referensi', 'value' => $entityRef],
                      ['label' => 'Jenis Kas', 'value' => is_array($payerRow) ? (string) ($payerRow['badge'] ?? '—') : '—'],
                      ['label' => 'Payer', 'html' => $payerHtml],
                      ['label' => 'Waktu Bind', 'value' => $linkedAt],
                      ['label' => 'Created Mutasi', 'value' => $mutasiCreated],
                      ['label' => 'ID Mutasi', 'value' => (string) ($row['mutasi_id'] ?? '')],
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
              <td><?= htmlspecialchars($tanggalLabel) ?></td>
               <td class="text-end"><span class="nta-nominal-single"><?= $fmtNominal($nominal) ?></span></td>
               <td class="text-end"><span class="nta-nominal-single"><?= $fmtNominal($billNominal) ?></span></td>
              <td class="text-start"><?php $this->view('non_tunai_admin/_payer_cell', ['payer' => $payerRow]); ?></td>
              <td class="text-center">
                <button type="button"
                  class="btn btn-outline-danger btn-sm nta-unbind-btn"
                  data-link-id="<?= $linkId ?>"
                  data-entity-type="<?= htmlspecialchars($entityLabel, ENT_QUOTES, 'UTF-8') ?>"
                  data-entity-ref="<?= htmlspecialchars($entityRef, ENT_QUOTES, 'UTF-8') ?>"
                  title="Unbind &amp; blokir entity">
                   <i class="fas fa-unlink"></i>
                </button>
              </td>
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
$this->view('non_tunai_admin/_unbind_modal', [
    'unbindUrl' => URL::BASE_URL . 'NonTunaiAdmin/unbindMutasiLink',
]);
$this->view('non_tunai_admin/_filter_script', ['maxRangeDays' => $data['maxRangeDays'] ?? 7]);
?>
