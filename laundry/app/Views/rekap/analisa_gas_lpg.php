<?php
$analysis = is_array($data['analysis'] ?? null) ? $data['analysis'] : [];
$bestRatio = $data['bestRatio'] ?? null;
$periode = (string) ($data['periode'] ?? '');
?>
<div class="row mx-0">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-1">Analisa Efisiensi Gas LPG</h5>
        <small class="text-muted">Periode: <?= htmlspecialchars($periode, ENT_QUOTES, 'UTF-8') ?>. Urutan berdasarkan biaya Gas LPG per layanan setrika dari paling hemat.</small>
      </div>
      <div class="card-body">
        <?php if ($bestRatio !== null) { ?>
          <div class="alert alert-success py-2">
            Benchmark cabang paling hemat:
            <strong>Rp<?= number_format($bestRatio, 2, ',', '.') ?></strong> per layanan setrika.
          </div>
        <?php } ?>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Cabang</th>
                <th class="text-end">Total Gas LPG</th>
                <th class="text-end">Qty Setrika</th>
                <th class="text-end">Biaya / Layanan</th>
                <th class="text-end">Gap dari Terhemat</th>
                 <th class="text-end">Sumber</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($analysis === []) { ?>
                 <tr><td colspan="7" class="text-center text-muted">Belum ada data snapshot maupun aktual.</td></tr>
              <?php } else { foreach ($analysis as $index => $item) { ?>
                <tr>
                  <td><?= $item['rasio'] === null ? '-' : (int) $index + 1 ?></td>
                  <td><?= htmlspecialchars((string) $item['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-end">Rp<?= number_format((int) $item['gas']) ?></td>
                  <td class="text-end"><?= number_format((int) $item['setrika']) ?></td>
                  <td class="text-end">
                    <?= $item['rasio'] === null ? '<span class="text-muted">Tidak cukup data</span>' : 'Rp' . number_format($item['rasio'], 2, ',', '.') ?>
                  </td>
                  <td class="text-end">
                    <?= $item['gap_percent'] === null ? '-' : number_format($item['gap_percent'], 2, ',', '.') . '%' ?>
                  </td>
                   <td class="text-end">
                     <?php if (($item['source'] ?? '') === 'snapshot') { ?>
                       Snapshot (<?= (int) $item['snapshot_count'] ?>)
                     <?php } else { ?>
                       Aktual
                     <?php } ?>
                   </td>
                </tr>
              <?php } } ?>
            </tbody>
          </table>
        </div>
        <p class="small text-muted mt-3 mb-0">
          Gap dihitung terhadap rasio cabang paling hemat: (rasio cabang - benchmark) / benchmark x 100%.
        </p>
      </div>
    </div>
  </div>
</div>
