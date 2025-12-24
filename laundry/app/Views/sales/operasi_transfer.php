<?php
// Framework tidak auto-extract, semua ada di $data array
$grouped = $data['grouped'] ?? [];
$startDate = $data['startDate'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $data['endDate'] ?? date('Y-m-d');
$cabangMap = $data['cabangMap'] ?? [];
$isEmpty = empty($grouped);
$currentCabang = $this->dCabang['id_cabang'] ?? 0;
?>

<div class="container-fluid p-3">
  <!-- Header & Filter -->
  <div class="card mb-3 shadow-sm border-0">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Transfer Barang</h5>
      </div>
      
      <!-- Filter Tanggal -->
      <form method="GET" action="<?= URL::BASE_URL ?>Sales/operasi_transfer" class="row g-2">
        <div class="col-md-4">
          <label class="form-label small">Dari Tanggal</label>
          <input type="date" name="start" class="form-control" value="<?= $startDate ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small">Sampai Tanggal</label>
          <input type="date" name="end" class="form-control" value="<?= $endDate ?>" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter me-2"></i>Filter
          </button>
          <a href="<?= URL::BASE_URL ?>Sales/operasi_transfer" class="btn btn-secondary ms-2">
            <i class="fas fa-redo me-2"></i>Reset
          </a>
        </div>
      </form>
      
      <div class="alert alert-info mt-3 mb-0 py-2">
        <i class="fas fa-info-circle me-2"></i>
        <small>Maksimal rentang tanggal: 1 bulan (31 hari)</small>
      </div>
    </div>
  </div>

  <!-- Data List -->
  <?php if ($isEmpty) { ?>
    <div class="card shadow-sm border-0">
      <div class="card-body text-center py-5">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
        <p class="text-muted">Tidak ada data transfer untuk periode ini</p>
      </div>
    </div>
  <?php } else { ?>
    <!-- Grid 2 Kolom -->
    <div class="row g-3">
    <?php foreach ($grouped as $ref => $group) { 
      $isReceived = ($group['target_id'] == $currentCabang && $group['source_id'] != $currentCabang);
      $isSent = ($group['source_id'] == $currentCabang && $group['target_id'] != $currentCabang);
      $sourceCabang = $cabangMap[$group['source_id']] ?? 'N/A';
      $targetCabang = $cabangMap[$group['target_id']] ?? 'N/A';
    ?>
      <div class="col-md-6">
      <div class="card mb-3 shadow-sm border-0">
        <!-- Header -->
        <div class="card-header <?= $isReceived ? 'bg-info' : 'bg-primary' ?> text-white py-2 px-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
              <span class="small">Ref:</span> <span class="fw-bold">#<?= $ref ?></span>
              <?php if ($isReceived) { ?>
                <span class="badge bg-light text-dark" style="font-size: 0.7rem;">
                  <i class="fas fa-download me-1"></i>Terima dari <?= $sourceCabang ?>
                </span>
              <?php } elseif ($isSent) { ?>
                <span class="badge bg-light text-dark" style="font-size: 0.7rem;">
                  <i class="fas fa-upload me-1"></i>Kirim ke <?= $targetCabang ?>
                </span>
              <?php } ?>
              
              <?php if ($group['state'] == 1) { ?>
                <span class="badge bg-success" style="font-size: 0.7rem;">
                  <i class="fas fa-check-circle me-1"></i>Diterima
                </span>
              <?php } else { ?>
                <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">
                  <i class="fas fa-clock me-1"></i>Pending
                </span>
              <?php } ?>
            </div>
            <div class="text-end">
              <span class="small"><?= date('d/m/y H:i', strtotime($group['date'])) ?></span>
            </div>
          </div>
        </div>
        
        <!-- Body - Items -->
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <tbody>
              <?php foreach ($group['items'] as $item) { ?>
                <tr>
                  <td class="ps-3 py-2">
                    <span class="fw-medium"><?= $item['nama_barang'] ?></span>
                    <?php if ($item['denom'] != 1) { ?>
                      <span class="badge bg-info ms-1" style="font-size: 0.7rem;">@<?= $item['denom'] ?></span>
                    <?php } ?>
                    <?php 
                      $margin = $item['margin'] ?? 0;
                      $displayPrice = $item['price'] + $margin;
                    ?>
                    <div class="text-muted small"><?= $item['qty'] ?> x Rp<?= number_format($displayPrice) ?></div>
                  </td>
                  <td class="text-end pe-3 py-2 align-middle">
                    <span class="fw-bold">Rp<?= number_format($displayPrice * $item['qty']) ?></span>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        
        <!-- Footer - Total & Route -->
        <div class="card-footer bg-light py-2 px-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="small text-muted">
              <i class="fas fa-arrow-right me-1"></i>
              <strong><?= $sourceCabang ?></strong> → <strong><?= $targetCabang ?></strong>
            </div>
            <span class="fw-bold">Rp<?= number_format($group['total']) ?></span>
          </div>
        </div>
      </div>
      </div> <!-- /col-md-6 -->
    <?php } ?>
    </div> <!-- /row -->
  <?php } ?>
</div>
