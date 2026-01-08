<div class="px-1 mt-2">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white py-2">
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-boxes me-2"></i>Stok Barang</h6>
        <span class="badge bg-white text-primary"><?= date('d M Y') ?></span>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="bg-light">
            <tr>
              <th class="ps-3">Nama Barang</th>
              <th class="text-center">Masuk</th>
              <th class="text-center">Keluar</th>
              <th class="text-end pe-3">Stok Akhir</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($data['barang'])) { ?>
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">Belum ada data barang</td>
              </tr>
            <?php } else { ?>
              <?php foreach ($data['barang'] as $b) { 
                  $stokClass = $b['stok'] > 0 ? 'text-success' : ($b['stok'] < 0 ? 'text-danger' : 'text-muted');
                  $unit = $b['unit_nama'] ?? '';
              ?>
                <tr>
                  <td class="ps-3 py-2">
                    <div class="fw-bold"><?= $b['nama'] ?></div>
                    <small class="text-muted"><?= $b['brand'] ?? '' ?></small>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-success bg-opacity-10 text-success">
                      <?= number_format($b['total_in']) ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-danger bg-opacity-10 text-danger">
                      <?= number_format($b['total_out']) ?>
                    </span>
                  </td>
                  <td class="text-end pe-3">
                    <span class="fw-bold fs-6 <?= $stokClass ?>">
                      <?= number_format($b['stok']) ?>
                      <small class="text-muted fw-normal" style="font-size: 0.7rem;"><?= $unit ?></small>
                    </span>
                  </td>
                </tr>
              <?php } ?>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
