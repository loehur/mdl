<div class="mb-3">
  <label class="form-label fw-bold">Pilih Barang</label>
  <select id="selectBarang">
    <option value="" selected disabled>-- Pilih Barang --</option>
    <?php foreach ($data['barang_data'] as $item) { ?>
      <option value="<?= $item['id_barang'] ?>" data-harga="<?= $item['price'] ?>"><?= strtoupper($item['brand']." ".$item['model']. " ".$item['description'])?></option>
    <?php } ?>
  </select>
</div>

<!-- Container untuk detail barang dan sub items -->
<div id="barangDetail" class="d-none">
  <div class="card mb-3 border-0">
    <div class="card-body p-0">
      <!-- Items list (Main + Sub) -->
      <div id="subItemsList"></div>
    </div>
  </div>
</div>

<!-- Cart Section -->
<div class="border-top pt-3 mt-3">
  <label class="form-label fw-bold"><i class="fas fa-shopping-cart me-1"></i> Keranjang</label>
  <div id="cartContainer" style="max-height: 200px; overflow-y: auto;">
    <div class="text-center text-muted py-3">
      <i class="fas fa-cart-arrow-down"></i> Keranjang kosong
    </div>
  </div>
  <div id="cartTotal" class="d-none border-top pt-2 mt-2">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-bold">Total:</span>
      <span id="totalHarga" class="fw-bold text-success fs-5">Rp0</span>
    </div>
    <button type="button" id="btnCheckout" class="btn btn-success w-100">
      <i class="fas fa-check-circle me-1"></i> Checkout
    </button>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
$(document).ready(function() {
  // Init selectize
  $('#selectBarang').selectize();

  // Load cart on init
  loadCart();
  
  // Handle barang selection
  $('#selectBarang').on('change', function() {
    var id = $(this).val();
    if (id) {
      $.ajax({
        url: '<?= URL::BASE_URL ?>Sales/get_sub/' + id,
        type: 'GET',
        dataType: 'json',
        success: function(res) {
          showBarangDetail(res);
        }
      });
    } else {
      $('#barangDetail').addClass('d-none');
    }
  });
  
  // Add main item to cart (New)
  $(document).on('click', '.addToCartMainNew', function() {
    var btn = $(this);
    var id_barang = $('#selectBarang').val();
    var qty = $('#qtyMain').val();
    
    // Disable button and show loading
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/add_main_to_cart',
      type: 'POST',
      data: { id_barang: id_barang, qty: qty },
      dataType: 'json',
      success: function(res) {
        loadCart();
        showToast('Barang utama ditambahkan');
        btn.prop('disabled', false).html('<i class="fas fa-plus"></i>');
      },
      error: function(xhr, status, error) {
        showToast('Error: ' + error);
        btn.prop('disabled', false).html('<i class="fas fa-plus"></i>');
      }
    });
  });
  
  // Add sub item to cart (Existing)
  $(document).on('click', '.addToCartSub', function() {
    var btn = $(this);
    var id_barang = $('#selectBarang').val();
    var id_sub = btn.data('id');
    var qty = btn.closest('.list-group-item').find('.qtySub').val() || 1;
    addToCart(id_barang, id_sub, qty);
  });
  
  // Remove from cart
  $(document).on('click', '.removeFromCart', function() {
    var key = $(this).data('key');
    $.ajax({
      url: '<?= URL::BASE_URL ?>Sales/remove_from_cart',
      type: 'POST',
      data: { key: key },
      dataType: 'json',
      success: function(res) {
        loadCart();
      }
    });
  });
  
  // Checkout
  $(document).on('click', '#btnCheckout', function() {
    var btn = $(this);
    var originalHtml = btn.html();
    
    showConfirmModal('Checkout sekarang?', function() {
      btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
      
      $.ajax({
        url: '<?= URL::BASE_URL ?>Sales/checkout',
        type: 'POST',
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
             loadCart();
             // Reset barang selection
             $('#selectBarang')[0].selectize.clear();
             $('#barangDetail').addClass('d-none');
             
             // Reload page to show new order
             location.reload();
           } else {
            showModalAlert(res.message, 'error');
          }
        },
        error: function(xhr, status, error) {
          showModalAlert('Error: ' + error, 'error');
        },
        complete: function() {
          btn.prop('disabled', false).html(originalHtml);
        }
      });
    });
  });
});

function showBarangDetail(res) {
  $('#barangDetail').removeClass('d-none');
  
  var nama = res.barang.nama;
  if(!nama && res.barang.brand) {
      nama = [res.barang.brand, res.barang.model, res.barang.description].filter(Boolean).join(' ').toUpperCase();
  }
  var hargaMain = (parseFloat(res.barang.price) || parseFloat(res.barang.harga) || 0) + (parseFloat(res.barang.margin) || 0);
  var unit = res.barang.unit_nama ? ' / ' + res.barang.unit_nama : '';
  
  var listHtml = '<div class="list-group list-group-flush border rounded">';
  
  // --- MAIN ITEM ROW ---
  listHtml += '<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-2 bg-light">';
  listHtml += '<div class="flex-grow-1">';
  listHtml += '<span class="fw-bold">' + nama + '</span>';
  listHtml += '<br><small class="text-success fw-bold">Rp' + numberFormat(hargaMain) + unit + '</small>';
  listHtml += '</div>';
  listHtml += '<div class="d-flex align-items-center gap-2">';
  listHtml += '<input type="number" class="form-control form-control-sm text-center" style="width:60px" value="1" min="1" id="qtyMain">';
  listHtml += '<button type="button" class="btn btn-sm btn-success addToCartMainNew">';
  listHtml += '<i class="fas fa-plus"></i>';
  listHtml += '</button>';
  listHtml += '</div>';
  listHtml += '</div>';

  // --- SUB ITEMS ROWS ---
  if (res.sub && res.sub.length > 0) {
    res.sub.forEach(function(item) {
      var subHarga = parseFloat(item.price) || 0; // Tanpa margin di list
      listHtml += '<div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 lh-sm">';
      listHtml += '<div class="flex-grow-1">';
      listHtml += '<span class="fw-medium ps-3 border-start border-3 border-info">' + item.nama.toUpperCase() + '</span>'; // Indent sub items
      listHtml += '<br><small class="text-success ps-3">Rp' + numberFormat(subHarga) + unit + '</small>';
      listHtml += '</div>';
      listHtml += '<div class="d-flex align-items-center gap-2">';
      listHtml += '<input type="number" class="form-control form-control-sm text-center qtySub" style="width:60px" value="1" min="1">';
      listHtml += '<button type="button" class="btn btn-sm btn-outline-success addToCartSub" data-id="' + item.id + '">';
      listHtml += '<i class="fas fa-plus"></i>';
      listHtml += '</button>';
      listHtml += '</div>';
      listHtml += '</div>';
    });
  }
  
  listHtml += '</div>'; // Close list-group
  
  $('#subItemsList').html(listHtml);
}

function addToCart(id_barang, id_sub, qty) {
  $.ajax({
    url: '<?= URL::BASE_URL ?>Sales/add_to_cart',
    type: 'POST',
    data: { id_barang: id_barang, id_sub: id_sub, qty: qty },
    dataType: 'json',
    success: function(res) {
      loadCart();
      // Show feedback
      showToast('Item ditambahkan ke keranjang');
    }
  });
}

function loadCart() {
  // Show loading state
  $('#cartContainer').html('<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>');
  
  $.ajax({
    url: '<?= URL::BASE_URL ?>Sales/cart',
    type: 'GET',
    cache: false,
    success: function(html) {
      $('#cartContainer').html(html);
      calculateTotal();
    },
    error: function(xhr, status, error) {
      $('#cartContainer').html('<div class="text-danger">Error: ' + error + '</div>');
    }
  });
}

function calculateTotal() {
  var total = 0;
  $('.cart-item').each(function() {
    var harga = parseFloat($(this).data('harga')) || 0;
    var qty = parseInt($(this).data('qty')) || 0;
    total += harga * qty;
  });
  
  if (total > 0) {
    $('#cartTotal').removeClass('d-none');
    $('#totalHarga').text('Rp' + numberFormat(total));
  } else {
    $('#cartTotal').addClass('d-none');
  }
}

function numberFormat(num) {
  return new Intl.NumberFormat('id-ID').format(num);
}

function showToast(msg) {
  // Simple toast notification
  var toast = $('<div class="position-fixed bottom-0 start-50 translate-middle-x mb-3 p-2 bg-success text-white rounded shadow" style="z-index:9999">' + msg + '</div>');
  $('body').append(toast);
  setTimeout(function() { toast.fadeOut(function() { $(this).remove(); }); }, 1500);
}

// Show modal alert (success/error)
function showModalAlert(message, type) {
  type = type || 'info';
  var iconClass = 'fa-info-circle text-primary';
  var headerClass = 'bg-primary';
  var title = 'Informasi';
  
  if (type === 'success') {
    iconClass = 'fa-check-circle text-success';
    headerClass = 'bg-success';
    title = 'Berhasil';
  } else if (type === 'error') {
    iconClass = 'fa-times-circle text-danger';
    headerClass = 'bg-danger';
    title = 'Error';
  }
  
  $('#salesModalAlertIcon').attr('class', 'fas ' + iconClass + ' fa-3x mb-2');
  $('#salesModalAlertTitle').text(title);
  $('#salesModalAlertMessage').html(message);
  $('#salesModalAlertHeader').attr('class', 'modal-header text-white ' + headerClass);
  
  var modalEl = document.getElementById('salesModalAlert');
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
}

// Show confirm modal
function showConfirmModal(message, onConfirm) {
  $('#confirmModalMessage').text(message);
  window.confirmCallback = onConfirm;
  
  var modalEl = document.getElementById('salesConfirmModal');
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
}

// Handle confirm button
$(document).on('click', '#btnConfirmYes', function() {
  var modalEl = document.getElementById('salesConfirmModal');
  var modal = bootstrap.Modal.getInstance(modalEl);
  modal.hide();
  
  if (typeof window.confirmCallback === 'function') {
    window.confirmCallback();
  }
});
</script>

<!-- Modal Konfirmasi Checkout -->
<div class="modal fade" id="salesConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning text-dark py-2">
        <h6 class="modal-title"><i class="fas fa-question-circle me-1"></i> Konfirmasi</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="fas fa-shopping-cart fa-3x text-warning mb-3"></i>
        <p id="confirmModalMessage" class="mb-0">Checkout sekarang?</p>
      </div>
      <div class="modal-footer border-0 justify-content-center py-2">
        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success btn-sm px-4" id="btnConfirmYes">
          <i class="fas fa-check me-1"></i> Ya, Checkout
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Alert (Success/Error) -->
<div class="modal fade" id="salesModalAlert" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header py-2 text-white bg-success" id="salesModalAlertHeader">
        <h6 class="modal-title" id="salesModalAlertTitle">Berhasil</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="fas fa-check-circle text-success fa-3x mb-2" id="salesModalAlertIcon"></i>
        <p id="salesModalAlertMessage" class="mb-0"></p>
      </div>
      <div class="modal-footer border-0 justify-content-center py-2">
        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
