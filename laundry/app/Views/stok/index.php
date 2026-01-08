<div class="px-1 mt-2">
  <div class="card shadow-sm border-0" style="max-width: 500px;">
    <div class="card-header bg-primary text-white py-2">
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="mb-0 overflow-hidden text-nowrap me-2"><i class="fas fa-boxes me-2"></i>Stok</h6>
        <div class="d-flex gap-2 align-items-center">
            <div class="input-group input-group-sm" style="max-width: 200px;">
                <span class="input-group-text bg-white border-0 text-muted"><i class="fas fa-search"></i></span>
                <input type="text" id="searchStok" class="form-control border-0" placeholder="Cari barang...">
            </div>
            <span class="badge bg-white text-primary d-none d-md-block"><?= date('d M') ?></span>
        </div>
      </div>
    </div>
    <div class="card-body p-0" style="height: 80vh; overflow-y: auto;">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle" id="tableStok">
          <thead class="bg-light">
            <tr>
              <th class="ps-3 py-3">Nama Barang</th>
              <th class="text-end pe-3 py-3" style="width: 120px;">Stok</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($data['barang'])) { ?>
              <tr>
                <td colspan="2" class="text-center py-4 text-muted">Belum ada data barang</td>
              </tr>
            <?php } else { ?>
              <?php foreach ($data['barang'] as $b) { 
                  $stok = $b['stok'];
                  $stokClass = $stok > 0 ? 'text-success' : ($stok < 0 ? 'text-danger' : 'text-muted');
                  $unit = $b['unit_nama'] ?? '';
              ?>
                <tr>
                  <td class="ps-3 py-2">
                    <div class="fw-bold productName text-dark"><?= $b['nama'] ?></div>
                  </td>
                  <td class="text-end pe-3">
                    <span class="fw-bold fs-5 <?= $stokClass ?>">
                      <?= number_format($stok) ?>
                      <small class="text-muted fw-normal ms-1" style="font-size: 0.75rem;"><?= $unit ?></small>
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

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
  $("#searchStok").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#tableStok tbody tr").filter(function() {
      // Hanya cari di kolom nama barang
      var text = $(this).find(".productName").text().toLowerCase();
      $(this).toggle(text.indexOf(value) > -1)
    });
  });
});
</script>
