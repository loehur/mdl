<style>
  table {
    border-radius: 15px;
    overflow: hidden
  }
</style>

<div class="px-1 mt-2">
  <div class="row">
    <div class="col" style="max-width: 500px;">
      <?php if (empty($data['checkouts'])) { ?>
        <div class="card">
          <div class="card-body">
            <p class="text-muted text-center py-5">
              <i class="fas fa-box-open fa-3x mb-3 d-block"></i>
              Belum ada Barang Masuk pending.<br>Klik tombol <strong>Barang Masuk</strong> untuk mencatat barang masuk baru.
            </p>
          </div>
        </div>
      <?php } else { ?>
        <?php foreach ($data['checkouts'] as $ref => $group) { ?>
          <div class="card mb-3 shadow-sm border-0">
            <!-- Header Nota -->
            <div class="card-header bg-dark text-white py-1 px-2">
              <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                  <span class="text-white-50 small">Ref:</span> 
                  <span class="fw-bold">#<?= $ref ?></span>
                  <span class="badge bg-info" style="font-size: 0.7rem;">
                    <i class="fas fa-arrow-down me-1"></i>Barang Masuk
                  </span>
                </div>
                <div class="text-end">
                  <span class="text-white-50 small"><?= date('d/m/y H:i', strtotime($group['date'])) ?></span>
                </div>
              </div>
            </div>
            
            <!-- Body Nota - List Items -->
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  <?php foreach ($group['items'] as $item) { ?>
                    <tr>
                      <td class="ps-3 py-1">
                        <span class="fw-medium"><?= $item['nama_barang'] ?></span>
                        <?php if ($item['denom'] != 1) { ?>
                          <span class="badge bg-info ms-1" style="font-size: 0.75rem;">@<?= $item['denom'] ?></span>
                        <?php } ?>
                        <div class="text-muted small"><?= $item['qty'] ?> x Rp<?= number_format($item['price']) ?></div>
                      </td>
                      <td class="text-end pe-3 py-1 align-middle">
                        <span class="fw-bold">Rp<?= number_format($item['price'] * $item['qty']) ?></span>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            
            <!-- Footer Nota - Total -->
            <div class="card-footer bg-secondary bg-opacity-10 py-1 px-3">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold">TOTAL</span>
                <span class="fw-bold">Rp<?= number_format($group['total']) ?></span>
              </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="card-footer bg-light py-2 px-3">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="alert alert-info mb-0 py-2 px-3 flex-grow-1" role="alert" style="font-size: 0.9rem;">
                    <i class="fas fa-clock me-2"></i>
                    <strong>Dalam Peninjauan</strong>
                  </div>
                  <button type="button" class="btn btn-outline-danger btnBatalNota ms-2" data-ref="<?= $ref ?>" title="Hapus">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </div>
            </div>
            
            <!-- Hidden Print Template (Optional) -->
            <div id="print<?= $ref ?>" class="d-none">
                <!-- Simple print template if needed -->
            </div>
          </div>
        <?php } ?>
      <?php } ?>
    </div>
  </div>
</div>

<!-- Floating Action Button - Order -->
<button id="btnSalesOrder" class="btn btn-success bg-gradient rounded-3 shadow-lg position-fixed d-flex align-items-center gap-2 px-3 py-2" 
   type="button" style="bottom: 24px; right: 24px; z-index: 1050;">
  <i class="fas fa-plus fa-lg"></i>
  <span class="fw-bold fs-6">Barang Masuk</span>
</button>

<!-- Offcanvas Barang Masuk (Using Sales Form) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasSalesOrder" aria-labelledby="offcanvasSalesOrderLabel" data-bs-backdrop="true" data-bs-scroll="true">
  <div class="offcanvas-header bg-success bg-gradient text-white">
    <h5 class="offcanvas-title fw-bold" id="offcanvasSalesOrderLabel"><i class="fas fa-box-open me-2"></i>Catat Barang Masuk</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-3" id="salesOrderContent">
    <div class="d-flex justify-content-center align-items-center py-5">
      <div class="spinner-border text-success" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<script>
  var formLoaded = false;
  var offcanvasSalesOrderEl = document.getElementById('offcanvasSalesOrder');
  
  if (offcanvasSalesOrderEl) {
      var bsOffcanvas = new bootstrap.Offcanvas(offcanvasSalesOrderEl);
      
      $('#btnSalesOrder').on('click', function() {
          bsOffcanvas.toggle();
      });
      
      // Load form when offcanvas opens - Load to BarangMasuk/form
      offcanvasSalesOrderEl.addEventListener('show.bs.offcanvas', function () {
          if(!formLoaded) {
              $('#salesOrderContent').load('<?= URL::BASE_URL ?>BarangMasuk/form', function(response, status, xhr) {
                  if (status == "error") {
                      $('#salesOrderContent').html('<div class="alert alert-danger">Gagal memuat form: ' + xhr.status + " " + xhr.statusText + '</div>');
                  } else {
                      formLoaded = true;
                  }
              });
          }
      });
  }
  
  // Alert helper
  function showSalesAlert(message, type) {
    var iconClass = 'fa-check-circle text-success';
    var title = 'Berhasil';
    
    if (type === 'error') {
      iconClass = 'fa-times-circle text-danger';
      title = 'Error';
    }
    
    $('#salesAlertIcon').attr('class', 'fas ' + iconClass + ' fa-3x mb-2');
    $('#salesAlertTitle').text(title);
    $('#salesAlertMessage').text(message);
    
    var modalEl = document.getElementById('modalSalesAlert');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
  
  // ========== HAPUS NOTA ==========
  var deleteNotaRef = '';
  
  $(document).on('click', '.btnBatalNota', function() {
    deleteNotaRef = $(this).data('ref');
    $('#deleteNotaRef').text('#' + deleteNotaRef);
    var modalEl = document.getElementById('modalHapusNota');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  $(document).on('click', '#btnKonfirmasiHapusNota', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>BarangMasuk/hapusNota',
      type: 'POST',
      dataType: 'json',
      data: {
        ref: deleteNotaRef
      },
      success: function(res) {
        var modalEl = document.getElementById('modalHapusNota');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        
        if (res.status === 'success') {
          location.reload();
        } else {
          showSalesAlert(res.message || 'Gagal menghapus', 'error');
          btn.prop('disabled', false).html(originalHtml);
        }
      },
      error: function(xhr, status, error) {
        showSalesAlert('Error: ' + error, 'error');
        btn.prop('disabled', false).html(originalHtml);
      }
    });
  });
  
  // ========== TERIMA BARANG (UBAH STATE = 1) ==========
  var terimaBarangNotaRef = '';
  
  $(document).on('click', '.btnTerimaBarang', function() {
    terimaBarangNotaRef = $(this).data('ref');
    
    $('#terimaBarangRef').text('#' + terimaBarangNotaRef);
    
    var modalEl = document.getElementById('modalTerimaBarang');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  $(document).on('click', '#btnKonfirmasiTerimaBarang', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
   
    $.ajax({
      url: '<?= URL::BASE_URL ?>BarangMasuk/terimaBarang',
      type: 'POST',
      dataType: 'json',
      data: {
        ref: terimaBarangNotaRef
      },
      success: function(res) {
        var modalEl = document.getElementById('modalTerimaBarang');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        
        if (res.status === 'success') {
          showSalesAlert(res.message || 'Barang berhasil diterima', 'success');
          setTimeout(function() {
            location.reload();
          }, 1500);
        } else {
          showSalesAlert(res.message || 'Gagal memproses', 'error');
          btn.prop('disabled', false).html(originalHtml);
        }
      },
      error: function(xhr, status, error) {
        showSalesAlert('Error: ' + error, 'error');
        btn.prop('disabled', false).html(originalHtml);
      }
    });
  });
</script>

<!-- Modal Alert -->
<div class="modal fade" id="modalSalesAlert" tabindex="-1" style="z-index: 10060;">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body text-center py-4">
        <i class="fas fa-check-circle text-success fa-3x mb-2" id="salesAlertIcon"></i>
        <h6 id="salesAlertTitle">Berhasil</h6>
        <p id="salesAlertMessage" class="mb-0 text-muted"></p>
      </div>
      <div class="modal-footer border-0 justify-content-center py-2">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Hapus Nota -->
<div class="modal fade" id="modalHapusNota" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title"><i class="fas fa-times me-2"></i>Hapus Nota</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
        <p class="mb-2">Yakin ingin menghapus nota ini?</p>
        <p class="fw-bold mb-0" id="deleteNotaRef"></p>
        <p class="text-danger small mt-2 mb-0"><i class="fas fa-info-circle me-1"></i>Semua item dalam nota akan dihapus</p>
      </div>
      <div class="modal-footer justify-content-center py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="btnKonfirmasiHapusNota">
          <i class="fas fa-trash-alt me-1"></i>Hapus
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Terima Barang -->
<div class="modal fade" id="modalTerimaBarang" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title"><i class="fas fa-check-circle me-2"></i>Terima Barang</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="fas fa-box-open text-success fa-3x mb-3"></i>
        <p class="mb-2">Konfirmasi Barang Masuk?</p>
        <div class="bg-light rounded p-2 mb-3">
          <small class="text-muted">No. Ref</small><br>
          <strong id="terimaBarangRef"></strong>
        </div>
        <p class="text-success small mt-2 mb-0">
          <i class="fas fa-info-circle me-1"></i>Barang akan masuk ke stok
        </p>
      </div>
      <div class="modal-footer justify-content-center py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btnKonfirmasiTerimaBarang">
          <i class="fas fa-check me-1"></i>Terima Barang
        </button>
      </div>
    </div>
  </div>
</div>
