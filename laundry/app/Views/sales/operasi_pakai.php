<?php
// Framework tidak auto-extract, semua ada di $data array
$grouped = $data['grouped'] ?? [];
$startDate = $data['startDate'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $data['endDate'] ?? date('Y-m-d');
$isEmpty = empty($grouped);
?>

<div class="container-fluid p-3">
  <!-- Header & Filter -->
  <div class="card mb-3 shadow-sm border-0">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Barang Dipakai</h5>
      </div>
      
      <!-- Filter Tanggal -->
      <form method="GET" action="<?= URL::BASE_URL ?>Sales/operasi_pakai" class="row g-2">
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
          <a href="<?= URL::BASE_URL ?>Sales/operasi_pakai" class="btn btn-secondary ms-2">
            <i class="fas fa-redo me-2"></i>Reset
          </a>
        </div>
      </form>
      
      <p class="text-muted small mt-3 mb-0 py-1">
        <i class="fas fa-info-circle me-1"></i>Maksimal rentang tanggal: 1 minggu (7 hari)
      </p>
    </div>
  </div>

  <!-- Data List -->
  <?php if ($isEmpty) { ?>
    <div class="card shadow-sm border-0">
      <div class="card-body text-center py-5">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
        <p class="text-muted">Tidak ada data barang dipakai untuk periode ini</p>
      </div>
    </div>
  <?php } else { ?>
    <!-- Grid 2 Kolom -->
    <div class="row g-1">
    <?php foreach ($grouped as $ref => $group) { ?>
      <div class="col-md-6">
      <div class="card shadow-sm border-0">
        <!-- Header -->
        <div class="card-header bg-dark text-white py-2 px-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <span class="small">Ref:</span> <span class="fw-bold">#<?= $ref ?></span>
            </div>
            <div class="text-end">
              <span class="small"><?= isset($group['date']) ? date('d/m/y H:i', strtotime($group['date'])) : '-' ?></span>
            </div>
          </div>
        </div>
        
        <!-- Body - Items -->
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <tbody>
              <?php if (isset($group['items']) && is_array($group['items'])) { ?>
              <?php foreach ($group['items'] as $item) {
                $descBar = trim($item['deskripsi_barang'] ?? '');
              ?>
                <tr>
                  <td class="ps-3 py-2 align-top">
                    <span class="fw-medium text-dark"><?= $item['nama_barang'] ?? 'Unknown' ?></span>
                    <?php if ($descBar !== '') { ?>
                    <div class="text-muted text-uppercase" style="font-size: 0.7rem; line-height: 1.25;"><?= htmlspecialchars($descBar, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>
                    <?php if (isset($item['denom']) && $item['denom'] != 1) { ?>
                      <span class="text-muted ms-1" style="font-size: 0.75rem;">@<?= $item['denom'] ?></span>
                    <?php } ?>
                  </td>
                  <td class="text-end pe-3 py-2 align-top">
                     <?php
                       $q = rtrim(rtrim(number_format($item['qty'] ?? 0, 1, ',', '.'), '0'), ',');
                       $u = trim($item['unit_nama'] ?? '');
                     ?>
                     <span class="text-muted small"><?= $q ?><?= $u !== '' ? ' ' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') : '' ?></span>
                  </td>
                </tr>
              <?php } ?>
              <?php } ?>
            </tbody>
          </table>
        </div>
        

      </div>
      </div> <!-- /col-md-6 -->
    <?php } ?>
    </div> <!-- /row -->
  <?php } ?>
</div>
