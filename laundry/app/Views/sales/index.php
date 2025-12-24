<style>
  table {
    border-radius: 15px;
    overflow: hidden
  }
  
  /* Modal adjustments untuk dropdown */
  #modalTransfer .modal-body {
    overflow: visible !important;
  }
  
  #modalTransfer .modal-content {
    overflow: visible !important;
  }
</style>

<!-- Selectize CSS removed (404) -->


<div class="px-1 mt-2">
  <div class="row">
    <div class="col" style="max-width: 500px;">
      <?php if (empty($data['checkouts'])) { ?>
        <div class="card">
          <div class="card-body">
            <p class="text-muted text-center py-5">
              <i class="fas fa-box-open fa-3x mb-3 d-block"></i>
              Belum ada order. Klik tombol <strong>Order</strong> untuk membuat order baru
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
                  <?php 
                  // Indicator jika nota diterima dari cabang lain (target)
                  $currentCabang = $this->dCabang['id_cabang'] ?? 0;
                  $sourceId = $group['items'][0]['source_id'] ?? 0;
                  $targetId = $group['items'][0]['target_id'] ?? 0;
                  $state = $group['items'][0]['state'] ?? 0;
                  
                  if ($targetId == $currentCabang && $sourceId != $currentCabang) {
                    // Nota diterima dari transfer
                  ?>
                    <span class="badge bg-info" style="font-size: 0.7rem;">
                      <i class="fas fa-download me-1"></i>Terima Transfer
                    </span>
                  <?php } ?>
                  
                  <?php if ($state == 3) { 
                    // Nota piutang
                  ?>
                    <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">
                      <i class="fas fa-file-invoice-dollar me-1"></i>Piutang
                    </span>
                  <?php } ?>
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
                        <?php 
                          $margin = $item['margin'] ?? 0;
                          $displayPrice = $item['price'] + $margin;
                        ?>
                        <div class="text-muted small"><?= $item['qty'] ?> x Rp<?= number_format($displayPrice) ?></div>
                      </td>
                      <td class="text-end pe-3 py-1 align-middle">
                        <span class="fw-bold">Rp<?= number_format($displayPrice * $item['qty']) ?></span>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            
            <!-- Footer Nota - Total -->
            <div class="card-footer bg-warning bg-gradient py-1 px-3">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold">TOTAL</span>
                <span class="fw-bold">Rp<?= number_format($group['total']) ?></span>
              </div>
            </div>
            
            <?php if (!empty($group['payments'])) { ?>
            <!-- Riwayat Pembayaran -->
            <div class="card-footer py-2 px-3">
              <small class="text-muted fw-bold"><i class="fas fa-history me-1"></i>Riwayat Pembayaran</small>
              <table class="table table-sm table-borderless mb-0 mt-1" style="font-size: 0.9rem;">
                <?php foreach ($group['payments'] as $payment) { 
                  $statusClass = $payment['status_mutasi'] == 3 ? 'text-success' : 'text-warning';
                  $statusText = $payment['status_mutasi'] == 3 ? 'Paid' : 'Pending';
                  $isQrisPending = ($payment['status_mutasi'] == 2 && strtoupper($payment['note']) == 'QRIS');
                ?>
                <tr>
                  <td class="py-1 ps-0">
                    <?= date('d/m H:i', strtotime($payment['insertTime'])) ?>
                  </td>
                  <td class="py-1">
                    <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                    <?php if ($payment['note']) { ?>
                      <span class="text-muted">(<?= $payment['note'] ?>)</span>
                    <?php } ?>
                    <?php if ($isQrisPending) { ?>
                      <button type="button" class="btn btn-warning btn-sm py-0 px-1 ms-1 btnCekQR" 
                        data-ref="<?= $payment['ref_finance'] ?? $payment['ref_transaksi'] ?>" 
                        data-total="<?= $payment['jumlah'] ?>">
                        <i class="fas fa-qrcode"></i>
                      </button>
                    <?php } ?>
                    <?php if ($payment['status_mutasi'] != 3) { ?>
                      <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 ms-1 btnHapusPayment" 
                        data-id="<?= $payment['id_kas'] ?>"
                        data-jumlah="<?= number_format($payment['jumlah']) ?>">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    <?php } ?>
                  </td>
                  <td class="py-1 text-end pe-0">
                    <span class="fw-bold">Rp<?= number_format($payment['jumlah']) ?></span>
                  </td>
                </tr>
                <?php } ?>
                <tr class="border-top">
                  <td colspan="2" class="py-1 ps-0"><span class="fw-bold text-danger">SISA</span></td>
                  <td class="py-1 text-end pe-0"><span class="fw-bold text-danger">Rp<?= number_format($group['sisa']) ?></span></td>
                </tr>
              </table>
            </div>
            <?php } ?>
            
            <!-- Action Buttons -->
            <div class="card-footer bg-light py-2 px-3">
              <?php 
              $type = $group['type'] ?? 1;
              $currentCabang = $this->dCabang['id_cabang'] ?? 0;
              $sourceId = $group['items'][0]['source_id'] ?? 0;
              $targetId = $group['items'][0]['target_id'] ?? 0;
              $isTarget = ($targetId == $currentCabang && $sourceId != $currentCabang);
              
              // Jika type = 2 (Transfer)
              if ($type == 2) {
                if ($isTarget) {
                  // CABANG TARGET: Tombol terima barang
                  // Get kode cabang pengirim
                  $sourceCabang = $this->db(0)->get_where_row('cabang', "id_cabang = '$sourceId'");
                  $kodeCabangSource = $sourceCabang['kode_cabang'] ?? 'N/A';
              ?>
                <div class="d-grid">
                  <button type="button" class="btn btn-success btnTerimaBarang" data-ref="<?= $ref ?>" data-source="<?= $kodeCabangSource ?>">
                    <i class="fas fa-check-circle me-2"></i>Terima barang dari <strong><?= $kodeCabangSource ?></strong>
                  </button>
                </div>
              <?php 
                } else {
                  // CABANG SOURCE: Alert peninjauan + tombol hapus
              ?>
                <div class="d-flex align-items-center justify-content-between">
                  <div class="alert alert-info mb-0 py-2 px-3 flex-grow-1" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Transfer barang dalam peninjauan</strong>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-danger btnBatalNota ms-2" data-ref="<?= $ref ?>" title="Hapus Nota">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              <?php 
                }
              } elseif ($type == 3) {
                // Type 3 (Pakai), sembunyikan semua tombol
              ?>
                <div class="alert alert-success mb-0 py-2 px-3" role="alert">
                  <i class="fas fa-check-circle me-2"></i>
                  <strong>Barang sudah dipakai</strong>
                </div>
              <?php 
              } else { 
                // Type 1 (Normal), tampilkan tombol
                $hasPayment = !empty($group['payments']);
                $state = $group['items'][0]['state'] ?? 0;
                $isPiutang = ($state == 3);
              ?>
                <div class="d-flex gap-1 justify-content-end align-items-center">
                  <!-- Dropdown Opsi -->
                  <div class="dropdown">
                    <!-- Manual Javascript Dropdown -->
                    <button class="btn btn-sm btn-secondary dropdown-toggle manual-dropdown-toggle" type="button" aria-expanded="false">
                      Optional
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                      <?php 
                      // Opsi Piutang
                      if (($group['sisa'] ?? $group['total']) > 0 && !$isPiutang) { 
                      ?>
                      <li>
                        <a class="dropdown-item btnPiutang" href="#" data-ref="<?= $ref ?>" data-total="<?= $group['total'] ?>">
                          <i class="fas fa-file-invoice-dollar text-warning me-2"></i>Jadikan Piutang
                        </a>
                      </li>
                      <?php } ?>

                      <?php 
                      // Opsi Transfer & Pakai
                      if (!$hasPayment && !$isPiutang) { 
                      ?>
                      <li>
                        <a class="dropdown-item btnTransfer" href="#" data-ref="<?= $ref ?>">
                          <i class="fas fa-exchange-alt text-primary me-2"></i>Transfer Barang
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item btnPakai" href="#" data-ref="<?= $ref ?>">
                          <i class="fas fa-box-open text-success me-2"></i>Barang Dipakai
                        </a>
                      </li>
                      <?php } ?>

                      <?php if (!$hasPayment) { ?>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <a class="dropdown-item text-danger btnBatalNota" href="#" data-ref="<?= $ref ?>">
                          <i class="fas fa-trash-alt me-2"></i>Hapus Nota
                        </a>
                      </li>
                      <?php } ?>
                    </ul>
                  </div>
                  <!-- Tombol Bayar (Primary Action) -->
                  <?php if (($group['sisa'] ?? $group['total']) > 0) { ?>
                  <button type="button" class="btn btn-sm btn-success btnBayar" data-ref="<?= $ref ?>" data-total="<?= $group['sisa'] ?? $group['total'] ?>" title="Bayar">
                    <i class="fas fa-wallet me-1"></i>Bayar
                  </button>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          </div>
        <?php } ?>
      <?php } ?>
    </div>
  </div>
</div>

<!-- Floating Action Button - Order -->
<button id="btnSalesOrder" class="btn btn-warning bg-gradient rounded-3 shadow-lg position-fixed d-flex align-items-center gap-2 px-3 py-2" 
   type="button" style="bottom: 24px; right: 24px; z-index: 1050;">
  <i class="fas fa-shopping-cart fa-lg"></i>
  <span class="fw-bold fs-6">Order</span>
</button>

<!-- Offcanvas Sales Order -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasSalesOrder" aria-labelledby="offcanvasSalesOrderLabel" data-bs-backdrop="false" data-bs-scroll="true">
  <div class="offcanvas-header bg-warning bg-gradient">
    <h5 class="offcanvas-title fw-bold text-dark" id="offcanvasSalesOrderLabel"><i class="fas fa-shopping-cart me-2"></i>Buat Sales Order</h5>
    <button type="button" class="btn-close text-dark" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-3" id="salesOrderContent">
    <div class="d-flex justify-content-center align-items-center py-5">
      <div class="spinner-border text-warning" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<!-- Selectize JS -->
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
  var kodeCabang = '<?= $this->dCabang['id_cabang'] ?? '' ?>';
  var printerMarginTop = <?= $this->mdl_setting["margin_printer_top"] ?? 0 ?>;
  var printerFeedLines = <?= $this->mdl_setting["margin_printer_bottom"] ?? 0 ?>;
  var formLoaded = false;
  var offcanvasSalesOrderEl = document.getElementById('offcanvasSalesOrder');
  
  if (offcanvasSalesOrderEl) {
      var bsOffcanvas = new bootstrap.Offcanvas(offcanvasSalesOrderEl);
      
      $('#btnSalesOrder').on('click', function() {
          bsOffcanvas.toggle();
      });
      
      // Load form when offcanvas opens
      offcanvasSalesOrderEl.addEventListener('show.bs.offcanvas', function () {
          if(!formLoaded) {
              $('#salesOrderContent').load('<?= URL::BASE_URL ?>Sales/form', function(response, status, xhr) {
                  if (status == "error") {
                      $('#salesOrderContent').html('<div class="alert alert-danger">Gagal memuat form: ' + xhr.status + " " + xhr.statusText + '</div>');
                  } else {
                      formLoaded = true;
                  }
              });
          }
      });
  }
  
  // ========== BAYAR SALES ==========
  var currentRef = '';
  var currentTotal = 0;
  
  // Open modal bayar
  $(document).on('click', '.btnBayar', function() {
    currentRef = $(this).data('ref');
    currentTotal = parseInt($(this).data('total')) || 0;
    
    $('#salesBayarRef').text('#' + currentRef);
    $('#salesTotalTagihan').text(currentTotal.toLocaleString('id-ID'));
    $('#salesBayarAmount').val('');
    $('#salesKembalian').val('0');
    
    var modalEl = document.getElementById('modalSalesBayar');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  // Bayar pas
  $(document).on('click', '#btnSalesBayarPas', function() {
    $('#salesBayarAmount').val(currentTotal);
    hitungKembalian();
  });
  
  // Hitung kembalian
  $(document).on('keyup change', '#salesBayarAmount', function() {
    hitungKembalian();
  });
  
  function hitungKembalian() {
    var dibayar = parseInt($('#salesBayarAmount').val()) || 0;
    var kembalian = dibayar - currentTotal;
    $('#salesKembalian').val(kembalian > 0 ? kembalian : 0);
  }
  
  // Show/hide non tunai field
  $(document).on('change', '#salesMetodeBayar', function() {
    if ($(this).val() == '2') {
      $('#trSalesNonTunai').show();
      $('#salesNoteBayar').prop('required', true);
    } else {
      $('#trSalesNonTunai').hide();
      $('#salesNoteBayar').prop('required', false);
    }
  });
  
  // Submit bayar
  $(document).on('submit', '#formSalesBayar', function(e) {
    e.preventDefault();
    
    var btn = $('#btnSubmitSalesBayar');
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
    
    var karyawan = $('#salesKaryawan').val();
    var metode = $('#salesMetodeBayar').val();
    var note = $('#salesNoteBayar').val() || '';
    var dibayar = $('#salesBayarAmount').val();
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/bayar',
      type: 'POST',
      dataType: 'json',
      data: {
        ref: currentRef,
        karyawan: karyawan,
        metode: metode,
        note: note,
        dibayar: dibayar,
        target: 'laundry_sales'
      },
      success: function(res) {
        if (res.status === 'success') {
          // Close modal bayar
          var modalEl = document.getElementById('modalSalesBayar');
          var modal = bootstrap.Modal.getInstance(modalEl);
          if(modal) modal.hide();
          
          // Jika QRIS, tampilkan QR langsung
          if (res.show_qr && res.qr_string) {
            currentQRRef = res.ref_finance;
            currentQRTotal = currentTotal;
            showSalesQR(res.qr_string, currentTotal, res.ref_finance);
          } else {
            // Show success
            showSalesAlert(res.message, 'success');
            
            // Reload page after delay
            setTimeout(function() {
              location.reload();
            }, 1500);
          }
        } else {
          showSalesAlert(res.message || 'Gagal memproses pembayaran', 'error');
        }
      },
      error: function(xhr, status, error) {
        showSalesAlert('Error: ' + error, 'error');
      },
      complete: function() {
        btn.prop('disabled', false).html(originalHtml);
      }
    });
  });
  
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
  
  // ========== CEK QR QRIS ==========
  var currentQRRef = '';
  var currentQRTotal = 0;
  
  // Open QR modal and get QR code
  $(document).on('click', '.btnCekQR', function() {
    var btn = $(this);
    currentQRRef = btn.data('ref');
    currentQRTotal = parseInt(btn.data('total')) || 0;
    
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    // Get QR code from payment gateway
    $.ajax({
      url: '<?= URL::BASE_URL ?>Operasi/payment_gateway_order/' + currentQRRef + '?nominal=' + currentQRTotal + '&metode=QRIS',
      type: 'GET',
      dataType: 'json',
      success: function(res) {
        btn.prop('disabled', false).html(originalHtml);
        
        if (res.status === 'paid') {
          location.reload();
          return;
        }
        
        var qrString = res.qr_string || res.qr || '';
        if (qrString) {
          showSalesQR(qrString, currentQRTotal, currentQRRef);
        } else {
          showSalesAlert('Gagal mendapatkan QR Code', 'error');
        }
      },
      error: function(xhr, status, error) {
        btn.prop('disabled', false).html(originalHtml);
        showSalesAlert('Error: ' + error, 'error');
      }
    });
  });
  
  // Show QR modal
  var currentQRString = '';
  function showSalesQR(qrString, total, ref) {
    currentQRString = qrString; // Simpan string QR
    
    $('#salesQRRef').text('#' + ref);
    var fmtTotal = total.toLocaleString('id-ID');
    $('#salesQRTotal').text('Rp' + fmtTotal);
    
    // Generate QR code
    var qrEl = document.getElementById('salesQRCode');
    if (qrEl) {
      qrEl.innerHTML = '';
      if (typeof QRCode !== 'undefined') {
        new QRCode(qrEl, {
          text: qrString,
          width: 200,
          height: 200
        });
      } else {
        qrEl.innerHTML = '<div class="alert alert-warning">QRCode library not loaded</div>';
      }
    }
    
    // Send to QR Display (Customer Display) - Optional, silently ignore if unavailable
    if (typeof kodeCabang !== 'undefined' && kodeCabang && qrString) {
      fetch("https://qrs.nalju.com/send-qr", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            kasir_id: kodeCabang,
            qr_string: qrString,
            text: "Sales Order #" + ref + "<br>Rp" + fmtTotal
          })
        })
        .then(res => { if (res.ok) console.log("QR Display: sent"); })
        .catch(() => { /* Silently ignore - QR display server is optional */ });
    }
    
    var modalEl = document.getElementById('modalSalesQR');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
  
  // Print QR
  $(document).on('click', '#btnSalesPrintQR', function() {
      var btn = $(this);
      
      if(!currentQRString) {
          showSalesAlert('QR Data tidak ditemukan', 'error');
          return;
      }
      
      var printText = $('#salesQRTotal').text() + "\n" + $('#salesQRRef').text();
      
      btn.addClass('disabled').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
      
      fetch("http://localhost:3000/printqr", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            qr_string: currentQRString,
            text: printText,
            margin_top: typeof printerMarginTop !== 'undefined' ? printerMarginTop : 0,
            feed_lines: typeof printerFeedLines !== 'undefined' ? printerFeedLines : 0
          })
        })
        .then(res => {
            console.log("Print Res:", res.status);
            if(res.ok) {
                // Optional: success feedback toaster
            } else {
                showSalesAlert('Gagal print', 'error');
            }
        })
        .catch(err => {
            console.error("Print Error:", err);
            showSalesAlert('Gagal koneksi printer', 'error');
        })
        .finally(() => {
            btn.removeClass('disabled').prop('disabled', false).html('<i class="fas fa-print"></i> Print');
        });
  });
  
  // Cek status QR
  $(document).on('click', '#btnSalesCekStatusQR', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Checking...');
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>Operasi/payment_gateway_check_status/' + currentQRRef,
      type: 'GET',
      dataType: 'json',
      success: function(res) {
        if (res.status === 'PAID') {
          $('#salesQRCode').html('<div class="text-success text-center"><i class="fas fa-check-circle fa-5x"></i><h3 class="mt-2">PAID</h3></div>');
          btn.removeClass('btn-warning').addClass('btn-success').html('<i class="fas fa-check"></i> PAID');
          location.reload();
        } else {
          showSalesAlert('Status: ' + (res.status || 'Unknown') + '\nSilahkan cek ulang beberapa saat lagi.', 'error');
          btn.prop('disabled', false).html(originalHtml);
        }
      },
      error: function() {
        showSalesAlert('Gagal mengecek status', 'error');
        btn.prop('disabled', false).html(originalHtml);
      }
    });
  });
  
  // ========== HAPUS RIWAYAT BAYAR ==========
  var deletePaymentId = '';
  var deletePaymentJumlah = '';
  
  // Open modal konfirmasi hapus
  $(document).on('click', '.btnHapusPayment', function() {
    deletePaymentId = $(this).data('id');
    deletePaymentJumlah = $(this).data('jumlah');
    
    $('#deletePaymentInfo').text('ID: ' + deletePaymentId + ' | Rp' + deletePaymentJumlah);
    
    var modalEl = document.getElementById('modalHapusPayment');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  // Konfirmasi hapus
  $(document).on('click', '#btnKonfirmasiHapusPayment', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/hapusPayment',
      type: 'POST',
      dataType: 'json',
      data: {
        id_kas: deletePaymentId
      },
      success: function(res) {
        // Close modal
        var modalEl = document.getElementById('modalHapusPayment');
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
  
  // ========== HAPUS NOTA ==========
  var deleteNotaRef = '';
  
  // Open modal konfirmasi hapus nota
  $(document).on('click', '.btnBatalNota', function() {
    deleteNotaRef = $(this).data('ref');
    
    $('#deleteNotaRef').text('#' + deleteNotaRef);
    
    var modalEl = document.getElementById('modalHapusNota');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  // Konfirmasi hapus nota
  $(document).on('click', '#btnKonfirmasiHapusNota', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/hapusNota',
      type: 'POST',
      dataType: 'json',
      data: {
        ref: deleteNotaRef
      },
      success: function(res) {
        // Close modal
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
  
  // ========== PAKAI (UBAH TYPE = 3, STATE = 0) ==========
  var pakaiNotaRef = '';
  
  // Open modal konfirmasi pakai
  $(document).on('click', '.btnPakai', function() {
    pakaiNotaRef = $(this).data('ref');
    
    $('#pakaiNotaRef').text('#' + pakaiNotaRef);
    
    var modalEl = document.getElementById('modalPakai');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  // Konfirmasi pakai
  $(document).on('click', '#btnKonfirmasiPakai', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/pakai',
      type: 'POST',
      dataType: 'json',
      data: {
        ref: pakaiNotaRef
      },
      success: function(res) {
        // Close modal
        var modalEl = document.getElementById('modalPakai');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        
        if (res.status === 'success') {
          showSalesAlert(res.message || 'Berhasil diubah ke Pakai', 'success');
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
  
  // ========== TRANSFER (UBAH TARGET_ID = ID_CABANG TUJUAN) ==========
  var transferNotaRef = '';
  var transferSelectize;
  
  // Inisialisasi Selectize - Simple seperti di Operasi
  $(document).ready(function() {
    transferSelectize = $('#transferCabang').selectize()[0].selectize;
    console.log('Selectize initialized:', transferSelectize);
  });
  
  // Open modal transfer
  $(document).on('click', '.btnTransfer', function() {
    transferNotaRef = $(this).data('ref');
    
    $('#transferNotaRef').text('#' + transferNotaRef);
    
    // Reset Selectize dropdown
    if (transferSelectize) {
      transferSelectize.clear();
    }
    
    var modalEl = document.getElementById('modalTransfer');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  // Konfirmasi transfer
  $(document).on('click', '#btnKonfirmasiTransfer', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    var targetCabang = $('#transferCabang').val();
    
    if (!targetCabang) {
      showSalesAlert('Pilih cabang tujuan terlebih dahulu', 'error');
      return;
    }
    
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
   
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/transfer',
      type: 'POST',
      dataType: 'json',
      data: {
        ref: transferNotaRef,
        target_id: targetCabang
      },
      success: function(res) {
        // Close modal
        var modalEl = document.getElementById('modalTransfer');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        
        if (res.status === 'success') {
          showSalesAlert(res.message || 'Berhasil transfer ke cabang tujuan', 'success');
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
  
  // ========== TERIMA BARANG (UBAH STATE = 1) ==========
  var terimaBarangNotaRef = '';
  
  // Open modal terima barang
  $(document).on('click', '.btnTerimaBarang', function() {
    terimaBarangNotaRef = $(this).data('ref');
    var sourceCabang = $(this).data('source');
    
    $('#terimaBarangRef').text('#' + terimaBarangNotaRef);
    $('#terimaBarangSource').text(sourceCabang);
    
    var modalEl = document.getElementById('modalTerimaBarang');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  // Konfirmasi terima barang
  $(document).on('click', '#btnKonfirmasiTerimaBarang', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
   
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/terimaBarang',
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
  
  // ========== PIUTANG (UBAH STATE = 3) ==========
  var piutangNotaRef = '';
  var piutangTotal = 0;
  
  // Open modal piutang
  $(document).on('click', '.btnPiutang', function() {
    piutangNotaRef = $(this).data('ref');
    piutangTotal = $(this).data('total');
    
    $('#piutangNotaRef').text('#' + piutangNotaRef);
    $('#piutangTotal').text('Rp' + new Intl.NumberFormat('id-ID').format(piutangTotal));
    
    var modalEl = document.getElementById('modalPiutang');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
  
  // Konfirmasi piutang
  $(document).on('click', '#btnKonfirmasiPiutang', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
   
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/piutang',
      type: 'POST',
      dataType: 'json',
      data: {
        ref: piutangNotaRef
      },
      success: function(res) {
        var modalEl = document.getElementById('modalPiutang');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        
        if (res.status === 'success') {
          showSalesAlert(res.message || 'Berhasil dicatat sebagai piutang', 'success');
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

<!-- Modal Bayar Sales -->
<div class="modal fade" id="modalSalesBayar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title"><i class="fas fa-wallet me-2"></i>Pembayaran Sales</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="formSalesBayar">
        <div class="modal-body">
          <div class="bg-light rounded p-2 mb-3 text-center">
            <small class="text-muted">No. Ref</small><br>
            <strong id="salesBayarRef"></strong>
          </div>
          
          <table class="table mb-0">
            <tr>
              <td class="py-2">Penerima</td>
              <td>
                <select id="salesKaryawan" class="form-select" required>
                  <option value="" disabled selected>Pilih Karyawan</option>
                  <?php foreach ($this->user as $u) { ?>
                    <option value="<?= $u['id_user'] ?>"><?= $u['id_user'] . '-' . strtoupper($u['nama_user']) ?></option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr>
              <td class="py-2">Metode</td>
              <td>
                <select id="salesMetodeBayar" class="form-select" required>
                  <?php foreach ($this->dMetodeMutasi as $m) { ?>
                    <option value="<?= $m['id_metode_mutasi'] ?>"><?= $m['metode_mutasi'] ?></option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr id="trSalesNonTunai" style="display: none;">
              <td class="py-2">Tujuan</td>
              <td>
                <select id="salesNoteBayar" class="form-select border-warning">
                  <option value="">Pilih Pembayaran</option>
                  <?php foreach (URL::NON_TUNAI as $nt) { ?>
                    <option value="<?= $nt ?>"><?= $nt ?></option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr>
              <td colspan="2" class="pt-3">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-bold text-danger">SISA TAGIHAN</span>
                  <span class="fw-bold text-danger fs-5">Rp<span id="salesTotalTagihan">0</span></span>
                </div>
              </td>
            </tr>
            <tr>
              <td colspan="2" class="py-2 text-end">
                <button type="button" id="btnSalesBayarPas" class="btn btn-info">Bayar Pas (Click)</button>
              </td>
            </tr>
            <tr>
              <td class="py-2">Jumlah Bayar</td>
              <td><input type="number" id="salesBayarAmount" class="form-control text-end" min="1" required></td>
            </tr>
            <tr>
              <td class="py-2">Kembalian</td>
              <td><input type="number" id="salesKembalian" class="form-control text-end" readonly></td>
            </tr>
          </table>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" id="btnSubmitSalesBayar" class="btn btn-success">
            <i class="fas fa-wallet me-1"></i>Bayar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Alert Sales -->
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

<!-- Modal QR Code Sales -->
<div class="modal fade" id="modalSalesQR" tabindex="-1" data-bs-backdrop="static" style="z-index: 10050;">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="fas fa-qrcode me-2"></i>Scan QRIS</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <div class="mb-2">
          <small class="text-muted">Ref:</small>
          <strong id="salesQRRef"></strong>
        </div>
        <div id="salesQRCode" class="d-flex justify-content-center mb-3"></div>
        <p class="fw-bold fs-5 text-danger mb-0" id="salesQRTotal"></p>
      </div>
      <div class="modal-footer justify-content-center py-2">
        <div class="d-flex gap-1">
          <button type="button" class="btn btn-sm btn-outline-dark" id="btnSalesPrintQR">
            <i class="fas fa-print"></i> Print
          </button>
          <button type="button" class="btn btn-sm btn-warning" id="btnSalesCekStatusQR">
            <i class="fas fa-sync"></i> Cek Status
          </button>
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Hapus Payment -->
<div class="modal fade" id="modalHapusPayment" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Hapus Pembayaran</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
        <p class="mb-2">Yakin ingin menghapus pembayaran ini?</p>
        <p class="text-muted small mb-0" id="deletePaymentInfo"></p>
        <p class="text-danger small mt-2 mb-0"><i class="fas fa-info-circle me-1"></i>Data tidak dapat dikembalikan</p>
      </div>
      <div class="modal-footer justify-content-center py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="btnKonfirmasiHapusPayment">
          <i class="fas fa-trash-alt me-1"></i>Hapus
        </button>
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

<!-- Modal Konfirmasi Pakai -->
<div class="modal fade" id="modalPakai" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title"><i class="fas fa-box-open me-2"></i>Pakai</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="fas fa-box-open text-success fa-3x mb-3"></i>
        <p class="mb-2">Yakin ingin mengubah nota ini ke status Pakai?</p>
        <p class="fw-bold mb-0" id="pakaiNotaRef"></p>
        <p class="text-muted small mt-2 mb-0"><i class="fas fa-info-circle me-1"></i>Type akan diubah ke 3 dan State ke 0</p>
      </div>
      <div class="modal-footer justify-content-center py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btnKonfirmasiPakai">
          <i class="fas fa-check me-1"></i>Ya, Pakai
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Transfer -->
<div class="modal fade" id="modalTransfer" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Transfer Nota</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-4">
        <div class="bg-light rounded p-2 mb-3 text-center">
          <small class="text-muted">No. Ref</small><br>
          <strong id="transferNotaRef"></strong>
        </div>
        
        <div class="mb-3">
          <label for="transferCabang" class="form-label fw-bold">Pilih Cabang Tujuan</label>
          <select id="transferCabang" required>
            <option value=""></option>
            <?php 
            if (isset($data['listCabang']) && is_array($data['listCabang'])) {
              foreach ($data['listCabang'] as $cabang) { 
                // Skip cabang saat ini
                if ($cabang['id_cabang'] == $this->dCabang['id_cabang']) continue;
            ?>
              <option value="<?= $cabang['id_cabang'] ?>">
                <?= $cabang['kode_cabang'] ?> - <?= $cabang['nama'] ?>
              </option>
            <?php 
              }
            }
            ?>
          </select>
        </div>
        
        <p class="text-muted small mb-0">
          <i class="fas fa-info-circle me-1"></i>
          Target_id akan diubah sesuai cabang tujuan yang dipilih
        </p>
      </div>
      <div class="modal-footer justify-content-center py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnKonfirmasiTransfer">
          <i class="fas fa-exchange-alt me-1"></i>Transfer
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
        <p class="mb-2">Konfirmasi penerimaan barang dari:</p>
        <p class="fw-bold fs-5 mb-2" id="terimaBarangSource"></p>
        <div class="bg-light rounded p-2 mb-3">
          <small class="text-muted">No. Ref</small><br>
          <strong id="terimaBarangRef"></strong>
        </div>
        <p class="text-success small mt-2 mb-0">
          <i class="fas fa-info-circle me-1"></i>Barang akan masuk ke stok cabang ini
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

<!-- Modal Piutang -->
<div class="modal fade" id="modalPiutang" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning py-2">
        <h6 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Catat Piutang</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="fas fa-hand-holding-usd text-warning fa-3x mb-3"></i>
        <p class="mb-2">Catat sebagai piutang:</p>
        <div class="bg-light rounded p-2 mb-3">
          <small class="text-muted">No. Ref</small><br>
          <strong id="piutangNotaRef"></strong>
        </div>
        <div class="bg-warning bg-opacity-10 rounded p-2 mb-3">
          <small class="text-muted">Total Piutang</small><br>
          <strong class="fs-5 text-danger" id="piutangTotal"></strong>
        </div>
        <p class="text-warning small mt-2 mb-0">
          <i class="fas fa-info-circle me-1"></i>Nota akan masuk ke daftar piutang
        </p>
      </div>
      <div class="modal-footer justify-content-center py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="btnKonfirmasiPiutang">
          <i class="fas fa-check me-1"></i>Catat Piutang
        </button>
      </div>
    </div>
  </div>
</div>

<!-- QRCode Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
$(document).ready(function() {
    // Manual Dropdown Handler untuk mengatasi conflict Bootstrap
    $(document).on('click', '.manual-dropdown-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close other dropdowns
        $('.dropdown-menu').not($(this).next('.dropdown-menu')).removeClass('show');
        
        // Toggle current
        $(this).next('.dropdown-menu').toggleClass('show');
    });

    // Close when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').removeClass('show');
        }
    });
});
</script>
