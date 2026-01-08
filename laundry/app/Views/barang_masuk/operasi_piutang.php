<?php
// Framework tidak auto-extract, semua ada di $data array
$grouped = $data['grouped'] ?? [];
$isEmpty = empty($grouped);
?>

<div class="container-fluid p-3">
  <!-- Header -->
  <div class="card mb-3 shadow-sm border-0">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Daftar Piutang</h5>
        <span class="badge bg-warning text-dark">
          <?= count($grouped) ?> Piutang Aktif
        </span>
      </div>
      <p class="text-muted small mb-0 mt-2">
        <i class="fas fa-info-circle me-1"></i>
        Menampilkan semua piutang yang belum lunas
      </p>
    </div>
  </div>

  <!-- Data List -->
  <?php if ($isEmpty) { ?>
    <div class="card shadow-sm border-0">
      <div class="card-body text-center py-5">
        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
        <p class="text-muted">Tidak ada piutang aktif</p>
        <p class="text-muted small">Semua tagihan sudah lunas!</p>
      </div>
    </div>
  <?php } else { ?>
    <?php 
    $totalPiutang = 0;
    $totalBayar = 0;
    $totalSisa = 0;
    ?>
    
    <div class="row g-3">
    <?php
    foreach ($grouped as $ref => $group) {
      $totalPiutang += $group['total'];
      $totalBayar += $group['total_paid'];
      $totalSisa += $group['sisa'];
    ?>
      <div class="col-md-6">
      <div class="card mb-3 shadow-sm border-0 border-start border-warning border-4">
        <!-- Header -->
        <div class="card-header bg-warning bg-opacity-10 py-2 px-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
              <span class="small">Ref:</span> <span class="fw-bold">#<?= $ref ?></span>
              <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">
                <i class="fas fa-file-invoice-dollar me-1"></i>Piutang
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
              
              <!-- Total, Bayar, Sisa -->
              <tr class="border-top">
                <td class="ps-3 py-2"><strong>TOTAL TAGIHAN</strong></td>
                <td class="text-end pe-3 py-2">
                  <strong class="text-dark">Rp<?= number_format($group['total']) ?></strong>
                </td>
              </tr>
              <tr class="bg-success bg-opacity-10">
                <td class="ps-3 py-2">
                  <i class="fas fa-check-circle text-success me-1"></i>
                  Sudah Dibayar
                </td>
                <td class="text-end pe-3 py-2">
                  <span class="text-success fw-bold">Rp<?= number_format($group['total_paid']) ?></span>
                </td>
              </tr>
              <tr class="bg-danger bg-opacity-10">
                <td class="ps-3 py-2">
                  <i class="fas fa-exclamation-circle text-danger me-1"></i>
                  <strong>SISA PIUTANG</strong>
                </td>
                <td class="text-end pe-3 py-2">
                  <strong class="text-danger fs-6">Rp<?= number_format($group['sisa']) ?></strong>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Progress Bar -->
        <?php 
        $percentPaid = $group['total'] > 0 ? ($group['total_paid'] / $group['total']) * 100 : 0;
        ?>
        <div class="card-footer p-2">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <small class="text-muted">Progress Pembayaran</small>
            <small class="fw-bold"><?= number_format($percentPaid, 1) ?>%</small>
          </div>
          <div class="progress mb-2" style="height: 8px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percentPaid ?>%" aria-valuenow="<?= $percentPaid ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          
          <?php if ($group['sisa'] > 0) { ?>
            <button type="button" class="btn btn-sm btn-success w-100 btnBayar" 
              data-ref="<?= $ref ?>" 
              data-total="<?= $group['total'] ?>"
              data-sisa="<?= $group['sisa'] ?>"
              title="Bayar Cicilan/Pelunasan">
              <i class="fas fa-wallet me-1"></i>Bayar
            </button>
          <?php } else { ?>
            <button class="btn btn-sm btn-outline-secondary w-100" disabled>
              <i class="fas fa-check me-1"></i>Lunas
            </button>
          <?php } ?>
        </div>
      </div>
      </div> <!-- /col-md-6 -->
    <?php } ?>
    </div> <!-- /row -->
    
    <!-- Summary Card -->
    <div class="card shadow border-0 border-warning border-top border-3">
      <div class="card-body">
        <h6 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Ringkasan Piutang</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="p-3 bg-white rounded border text-center h-100 shadow-sm">
              <small class="text-secondary text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Piutang</small>
              <h4 class="mb-0 fw-bold text-dark">Rp<?= number_format($totalPiutang) ?></h4>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 bg-white rounded border border-success border-2 text-center h-100 shadow-sm">
              <small class="text-success text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Sudah Dibayar</small>
              <h4 class="mb-0 fw-bold text-success">Rp<?= number_format($totalBayar) ?></h4>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 bg-white rounded border border-danger border-2 text-center h-100 shadow-sm">
              <small class="text-danger text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Sisa Tagihan</small>
              <h4 class="mb-0 fw-bold text-danger">Rp<?= number_format($totalSisa) ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php } ?>
</div>

<!-- Modal Bayar Sales -->
<div class="modal fade" id="modalSalesBayar" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title"><i class="fas fa-wallet me-2"></i>Pembayaran Piutang</h6>
        <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formSalesBayar">
        <div class="modal-body">
          <div class="alert alert-light border text-center mb-3 py-2">
            <small class="text-muted d-block">No. Referensi</small>
            <strong id="salesBayarRef" class="fs-5 text-dark"></strong>
          </div>
          
          <div class="mb-3">
            <label class="form-label small">Kasir</label>
            <select id="salesKaryawan" class="form-select" disabled>
              <option value="<?= $_SESSION[URL::SESSID]['user']['id_user'] ?>" selected>
                <?= $_SESSION[URL::SESSID]['user']['nama_user'] ?>
              </option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label small">Metode Bayar</label>
            <select id="salesMetodeBayar" class="form-select" required>
               <option value="1">Tunai</option>
               <option value="2">Transfer</option>
            </select>
          </div>
          
          <div class="mb-3" id="divSalesNoteBayar" style="display:none;">
             <label class="form-label small">Tujuan Transfer</label>
             <select id="salesNoteBayar" class="form-select border-warning">
                <option value="">Pilih Tujuan...</option>
                <?php if(defined('URL::MOOTA_BANK_ID')){ 
                   foreach(constant('URL::MOOTA_BANK_ID') as $bank => $id){ ?>
                      <option value="<?= $bank ?>"><?= $bank ?></option>
                   <?php } 
                } ?>
                <option value="QRIS">QRIS</option>
             </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label small d-flex justify-content-between">
              <span>Jumlah Bayar</span>
              <span class="badge bg-warning text-dark pointer" id="btnSalesBayarFull" style="cursor:pointer">Bayar Full</span>
            </label>
            <div class="input-group">
               <span class="input-group-text">Rp</span>
               <input type="number" id="salesBayarAmount" class="form-control fw-bold" min="1" required placeholder="0">
            </div>
            <div class="form-text text-end" id="helperSisa">Sisa Piutang: Rp0</div>
          </div>
        </div>
        <div class="modal-footer py-1">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" id="btnSubmitSalesBayar" class="btn btn-success btn-sm">
            <i class="fas fa-check me-1"></i>Proses Bayar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
  var currentRef = '';
  var currentSisa = 0;
  
  // Open Modal
  $(document).on('click', '.btnBayar', function() {
    currentRef = $(this).data('ref');
    currentSisa = parseInt($(this).data('sisa'));
    var total = parseInt($(this).data('total'));
    
    $('#salesBayarRef').text('#' + currentRef);
    $('#salesBayarAmount').val(''); 
    $('#helperSisa').text('Sisa Piutang: Rp' + new Intl.NumberFormat('id-ID').format(currentSisa));
    
    // Reset Form
    $('#salesMetodeBayar').val(1).trigger('change');
    $('#divSalesNoteBayar').hide();
    $('#salesNoteBayar').val('');
    $('#btnSubmitSalesBayar').prop('disabled', false).html('<i class="fas fa-check me-1"></i>Proses Bayar');
    
    var modal = new bootstrap.Modal(document.getElementById('modalSalesBayar'));
    modal.show();
    
    // Focus input after modal shown
    setTimeout(function() {
       $('#salesBayarAmount').focus();
    }, 500);
  });
  
  // Button Bayar Full / Sisa
  $('#btnSalesBayarFull').click(function() {
     $('#salesBayarAmount').val(currentSisa);
  });
  
  // Toggle Transfer options
  $('#salesMetodeBayar').change(function() {
     if($(this).val() == '2') {
        $('#divSalesNoteBayar').slideDown();
        $('#salesNoteBayar').prop('required', true);
     } else {
        $('#divSalesNoteBayar').slideUp();
        $('#salesNoteBayar').prop('required', false);
     }
  });
  
  // Submit Form
  $('#formSalesBayar').on('submit', function(e) {
    e.preventDefault();
    
    var amount = parseInt($('#salesBayarAmount').val());
    if (amount <= 0) {
       alert('Jumlah bayar harus lebih dari 0');
       return;
    }
    
    // UI Loading
    var btn = $('#btnSubmitSalesBayar');
    var originalBtn = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Loading...');
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/bayar',
      type: 'POST',
      dataType: 'json',
      data: {
        ref: currentRef,
        karyawan: $('#salesKaryawan').val(),
        metode: $('#salesMetodeBayar').val(),
        note: $('#salesNoteBayar').val(),
        dibayar: amount,
        target: 'laundry_sales'
      },
      success: function(res) {
        if (res.status === 'success') {
           // Reload page on success
           location.reload(); 
        } else {
           alert('Gagal: ' + res.message);
           btn.prop('disabled', false).html(originalBtn);
        }
      },
      error: function(xhr, status, error) {
         console.error(xhr.responseText);
         alert('Terjadi kesalahan sistem');
         btn.prop('disabled', false).html(originalBtn);
      }
    });
  });
});
</script>
