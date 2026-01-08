<?php
// Framework tidak auto-extract, semua ada di $data array
$grouped = $data['grouped'] ?? [];
$startDate = $data['startDate'] ?? date('Y-m-d', strtotime('-6 days'));
$endDate = $data['endDate'] ?? date('Y-m-d');
$isEmpty = empty($grouped);
?>

<div class="container-fluid p-3">
  <!-- Header & Filter -->
  <div class="card mb-3 shadow-sm border-0">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-check-circle me-2 text-success"></i>Order Selesai</h5>
        <span class="badge bg-success">
          <?= count($grouped) ?> Transaksi
        </span>
      </div>
      
      <!-- Filter Tanggal -->
      <form method="GET" action="<?= URL::BASE_URL ?>Sales/operasi_tuntas" class="row g-2">
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
          <a href="<?= URL::BASE_URL ?>Sales/operasi_tuntas" class="btn btn-secondary ms-2">
            <i class="fas fa-redo me-2"></i>Reset
          </a>
        </div>
      </form>
      
      <div class="alert alert-info mt-3 mb-0 py-2">
        <i class="fas fa-info-circle me-2"></i>
        <small>Maksimal rentang tanggal: 1 Minggu (7 hari)</small>
      </div>
    </div>
  </div>

  <!-- Data List -->
  <?php if ($isEmpty) { ?>
    <div class="card shadow-sm border-0">
      <div class="card-body text-center py-5">
        <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
        <p class="text-muted">Tidak ada transaksi selesai pada periode ini</p>
      </div>
    </div>
  <?php } else { ?>
    
    <div class="row g-3">
    <?php foreach ($grouped as $ref => $group) { ?>
      <div class="col-md-6">
      <div class="card mb-3 shadow-sm border-0 border-start border-success border-4">
        <!-- Header -->
        <div class="card-header bg-success bg-opacity-10 py-2 px-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
              <span class="small">Ref:</span> <span class="fw-bold text-dark">#<?= $ref ?></span>
              <span class="badge bg-success" style="font-size: 0.7rem;">
                <i class="fas fa-check me-1"></i>Lunas
              </span>
            </div>
            <div class="text-end">
              <span class="small text-muted"><?= date('d/m/y H:i', strtotime($group['date'])) ?></span>
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
            <tfoot class="border-top bg-light">
              <tr>
                <td class="ps-3 py-2 fw-bold">TOTAL</td>
                <td class="text-end pe-3 py-2 fw-bold text-success">Rp<?= number_format($group['total']) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
        
        <!-- Footer Actions -->
        <div class="card-footer p-2 text-end">
           <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
             <i class="fas fa-print me-1"></i>Cetak
           </button>
        </div>
      </div>
      </div> <!-- /col-md-6 -->
    <?php } ?>
    </div> <!-- /row -->
  <?php } ?>
</div>
