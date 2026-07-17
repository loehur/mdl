<?php
if ($data['id_pelanggan'] > 0) {
  $id_pelanggan = $data['id_pelanggan'];
} else {
  $id_pelanggan = 0;
}
?>
<div class="position-fixed w-100 bg-light mx-1" style="z-index:1000;top:0px;height:205px">
</div>
<div class="w-100 sticky-top px-1 mb-2" style="top:72px;z-index:1001">
  <div class="bg-white p-1 rounded border" style="height:127px">
    <div class="row mx-0">
      <div class="col px-1" style="max-width: 270px;">
        <label>Pelanggan</label>
        <select name="pelanggan"  data-id="<?= $id_pelanggan ?>" class="id_pelanggan tize form-control form-control-sm" required>
          <option value="" selected disabled>...</option>
          <?php foreach ($this->pelanggan as $a) { ?>
            <option value="<?= $a['id_pelanggan'] ?>" <?= $a['id_pelanggan'] == $id_pelanggan ? 'selected' : '' ?>><?= (strlen($a['nama_pelanggan']) > 10 ? strtoupper(substr($a['nama_pelanggan'], 0, 10)) . '...' : strtoupper($a['nama_pelanggan'])) ?> | <?= $a['nomor_pelanggan'] ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="col-auto pe-0">
        <label>&nbsp;</label>
        <span onclick="cekData()" class="btn btn-secondary form-control form-control-sm" style="height: 34px;">OP</span>
      </div>
      <div class="col-auto pe-0">
        <label>&nbsp;</label>
        <a class="hrfsp" href="<?= URL::BASE_URL ?>Member/tambah_paket/<?= $id_pelanggan ?>"><span class="btn btn-outline-secondary form-control form-control-sm" style="height: 34px;">SP</span></a>
      </div>
      <div class="col-auto">
        <label>&nbsp;</label>
        <a class="hrfsd" href="<?= URL::BASE_URL ?>SaldoTunai/tambah/<?= $id_pelanggan ?>"><span class="btn btn-outline-secondary form-control form-control-sm" style="height: 34px;">SD</span></a>
      </div>
    </div>
      <div class="row mt-1 mr-1 w-100">
        <form id="main">
          <div class="d-flex align-items-start align-items-end pb-1">
            <?php if ($data['mode'] == 1) { 
              // Untuk mode tuntas, tampilkan navigasi tahun
              $currentYear = isset($data['currentYear']) ? $data['currentYear'] : date('Y');
              $selectedYear = isset($data['selectedYear']) ? $data['selectedYear'] : $currentYear;
              $minYear = isset($data['minYear']) ? $data['minYear'] : 2021;
              
              // Note: selectedYear akan diupdate via JavaScript saat data dimuat
            ?>
              <div class="d-flex align-items-center">
                <button type="button" id="btnPrevYear" class="btn btn-outline-secondary me-2" <?= ($selectedYear <= $minYear) ? 'disabled' : '' ?>>
                  <i class="fas fa-chevron-left"></i>
                </button>
                <span id="currentYearDisplay" class="btn btn-primary px-4 fw-bold">
                  <?= $selectedYear ?>
                </span>
                <button type="button" id="btnNextYear" class="btn btn-outline-secondary ms-2" <?= ($selectedYear >= $currentYear) ? 'disabled' : '' ?>>
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            <?php } else { ?>
              <div class="pl-0 pr-1">
                <a href="<?= URL::BASE_URL ?>Antrian/index/1" type="button" class="btn btn-outline-primary">
                  Terkini
                </a>
              </div>
              <div class="pl-0 pr-1">
                <a href="<?= URL::BASE_URL ?>Antrian/index/6" type="button" class="btn btn-outline-success">
                  Minggu
                </a>
              </div>
              <div class="pl-0 pr-1">
                <a href="<?= URL::BASE_URL ?>Antrian/index/7" type="button" class="btn btn-outline-info">
                  Bulan
                </a>
              </div>
            <?php } ?>
          </div>
        </form>
      </div>
  </div>
</div>

<div id="load"></div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
  $(document).ready(function() {
    $('select.tize').selectize();

    // Year navigation handlers (for mode tuntas)
    <?php if ($data['mode'] == 1) { ?>
    var currentYear = <?= isset($data['currentYear']) ? $data['currentYear'] : date('Y') ?>;
    var selectedYear = <?= isset($data['selectedYear']) ? $data['selectedYear'] : date('Y') ?>;
    var minYear = <?= isset($data['minYear']) ? $data['minYear'] : 2021 ?>;
    <?php } ?>

    // Load data saat pertama kali halaman dibuka (tanpa redirect)
    var pelanggan = <?= $id_pelanggan ?>;
    if (pelanggan && pelanggan.length != 0) {
      <?php if ($data['mode'] == 1) { ?>
      // Untuk mode tuntas, load dengan year yang dipilih
      loadDataOnly(pelanggan, selectedYear);
      <?php } else { ?>
      loadDataOnly(pelanggan);
      <?php } ?>
    }
    
    <?php if ($data['mode'] == 1) { ?>
    $('#btnPrevYear').on('click', function() {
      if (selectedYear > minYear) {
        selectedYear--;
        updateYearDisplay();
        reloadDataWithYear();
      }
    });
    
    $('#btnNextYear').on('click', function() {
      if (selectedYear < currentYear) {
        selectedYear++;
        updateYearDisplay();
        reloadDataWithYear();
      }
    });
    
    function updateYearDisplay() {
      $('#currentYearDisplay').text(selectedYear);
      $('#btnPrevYear').prop('disabled', selectedYear <= minYear);
      $('#btnNextYear').prop('disabled', selectedYear >= currentYear);
    }
    
    function reloadDataWithYear() {
      if (pelanggan && pelanggan != 0) {
        loadDataOnly(pelanggan, selectedYear);
      }
    }
    <?php } ?>
  });

  $('select.tize').selectize({
    onChange: function(value) {
      if (value.length != 0) {
        // Redirect ke URL baru dengan full page reload hanya saat user mengubah pilihan
        window.location.href = '<?= URL::BASE_URL ?>Operasi/i/<?= $data['mode'] ?>/' + value;
      }
    },
  });

  $('.tize').click(function() {
    $("select.tize")[0].selectize.clear();
  })

  // Fungsi untuk load data via AJAX (digunakan saat pertama kali halaman dibuka)
  function loadDataOnly(id, year) {
    $('.hrfsp').attr('href', '<?= URL::BASE_URL ?>Member/tambah_paket/' + id);
    $('.hrfsd').attr('href', '<?= URL::BASE_URL ?>SaldoTunai/tambah/' + id);
    
    // Show loading indicator
    $("div#load").html(`
      <div class="d-flex justify-content-center align-items-center py-5">
        <div class="text-center">
          <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-muted mb-0">Memuat data...</p>
        </div>
      </div>
    `);
    
    var url = "<?= URL::BASE_URL ?>Operasi/loadData/" + id + "/" + <?= $data['mode'] ?>;
    if (year) {
      url += "?year=" + year;
    }
    // Add cache busting
    url += (url.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now();
    
    $("div#load").load(url);
  }

  function load_data_operasi(id) {
    // Redirect ke URL baru dengan full page reload
    window.location.href = '<?= URL::BASE_URL ?>Operasi/i/<?= $data['mode'] ?>/' + id;
  }

  function cekData() {
    var pelanggan = <?= $id_pelanggan ?>;

    if (pelanggan.length == 0) {
      return;
    } else {
      load_data_operasi(pelanggan);
    }
  }
</script>

<!-- Floating Action Buttons -->
<div id="fabOperasiButtons" class="position-fixed bottom-0 end-0 p-4 gap-2" style="z-index: 1020; display: flex;">
  <button id="btnBukaOrderOp" class="btn btn-warning bg-gradient rounded-3 shadow d-flex align-items-center gap-2 px-3 py-2" type="button">
    <i class="fas fa-cash-register fa-lg"></i>
    <span class="fw-bold fs-6">Order</span>
  </button>
  <?php if ($id_pelanggan > 0) { ?>
  <button id="btnTriggerPayment" class="btn btn-success bg-gradient rounded-3 shadow d-flex align-items-center gap-2 px-3 py-2" type="button">
    <i class="fas fa-wallet fa-lg"></i>
    <span class="fw-bold fs-6">Pay</span>
  </button>
  <?php } ?>
</div>

<!-- Offcanvas Buka Order -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasBukaOrderOp" aria-labelledby="offcanvasBukaOrderOpLabel" data-bs-backdrop="true" data-bs-scroll="true" style="z-index: 1100;">
  <div class="offcanvas-header bg-warning bg-gradient">
    <h5 class="offcanvas-title fw-bold text-dark" id="offcanvasBukaOrderOpLabel"><i class="fas fa-cash-register me-2"></i>Buka Order Baru</h5>
    <button type="button" class="btn-close text-dark" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0" id="bukaOrderContentOp">
    <div class="d-flex justify-content-center align-items-center h-100">
        <div class="spinner-border text-warning" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
  </div>
</div>

<style>
  #offcanvasBukaOrderOp,
  #offcanvasPayment { z-index: 1100 !important; }
  .offcanvas-backdrop { z-index: 1090 !important; }
  .modal { z-index: 1200 !important; }
  .modal-backdrop { z-index: 1190 !important; }
  #fabOperasiButtons.is-fab-hidden { display: none !important; }
</style>

<script>
  // Pastikan modal (atau form pembungkusnya) selalu di body
  // Modal Ambil dll dibungkus <form>, jangan dipisah dari form-nya
  document.addEventListener('show.bs.modal', function (e) {
      var el = e.target;
      if (!el || !el.classList.contains('modal')) return;
      var wrapper = el.closest('form') || el;
      if (wrapper.parentNode !== document.body) {
          document.body.appendChild(wrapper);
      }
  });

  // Manual Trigger for Buka Order Offcanvas
  var orderLoaded = false;
  var offcanvasBukaOrderEl = document.getElementById('offcanvasBukaOrderOp');
  var $fabOperasi = $('#fabOperasiButtons');

  function hideFabOperasi() {
      $fabOperasi.addClass('is-fab-hidden');
  }
  function showFabOperasi() {
      var orderOpen = offcanvasBukaOrderEl && offcanvasBukaOrderEl.classList.contains('show');
      var paymentEl = document.getElementById('offcanvasPayment');
      var paymentOpen = paymentEl && paymentEl.classList.contains('show');
      if (!orderOpen && !paymentOpen) {
          $fabOperasi.removeClass('is-fab-hidden');
      }
  }

  if (offcanvasBukaOrderEl) {
      var bsOffcanvas = new bootstrap.Offcanvas(offcanvasBukaOrderEl);
      
      $('#btnBukaOrderOp').on('click', function() {
          hideFabOperasi();
          bsOffcanvas.toggle();
      });

      offcanvasBukaOrderEl.addEventListener('show.bs.offcanvas', function () {
          hideFabOperasi();
          if(!orderLoaded) {
              $('#bukaOrderContentOp').load('<?= URL::BASE_URL ?>Penjualan', function(response, status, xhr) {
                  if (status == "error") {
                      $('#bukaOrderContentOp').html('<div class="alert alert-danger m-3">Gagal memuat halaman order: ' + xhr.status + " " + xhr.statusText + '</div>');
                  } else {
                      orderLoaded = true;
                      // Move modals inside loaded content to body
                      setTimeout(function() {
                          $('#bukaOrderContentOp .modal').appendTo("body");
                      }, 500);
                  }
              });
          }
      });

      offcanvasBukaOrderEl.addEventListener('hidden.bs.offcanvas', showFabOperasi);
  }

  // Manual Trigger for Payment Offcanvas
  $(document).on('click', '#btnTriggerPayment', function() {
      hideFabOperasi();
      var offcanvasPaymentEl = document.getElementById('offcanvasPayment');
      if (offcanvasPaymentEl) {
          var paymentOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasPaymentEl);
          paymentOffcanvas.toggle();
      }
  });

  // offcanvasPayment di-load via AJAX, jadi pakai listener di document
  document.addEventListener('show.bs.offcanvas', function (e) {
      if (e.target && e.target.id === 'offcanvasPayment') {
          hideFabOperasi();
      }
  });
  document.addEventListener('hidden.bs.offcanvas', function (e) {
      if (e.target && e.target.id === 'offcanvasPayment') {
          showFabOperasi();
      }
  });
</script>