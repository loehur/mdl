<?php
if ($data['id_pelanggan'] > 0) {
  $id_pelanggan = $data['id_pelanggan'];
} else {
  $id_pelanggan = 0;
}
$modeOperasi = (int) $data['mode'];
?>

<style>
  .operasi-filter {
    position: sticky;
    top: var(--operasi-sticky-top, 50px);
    z-index: 100;
    margin: 0 0 12px;
  }
  .operasi-filter-card {
    background: #fff;
    border: 1px solid #dde3ea;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(30, 41, 59, 0.08);
    padding: 10px 12px;
  }
  .operasi-filter-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    flex-wrap: wrap;
  }
  .operasi-field {
    flex: 1 1 220px;
    min-width: 180px;
  }
  .operasi-field label,
  .operasi-actions label,
  .operasi-periods-wrap label {
    display: block;
    margin: 0 0 4px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #64748b;
  }
  .operasi-actions {
    display: inline-flex;
    align-items: flex-end;
    gap: 6px;
    flex: 0 0 auto;
  }
  .operasi-btn {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    min-width: 42px;
    padding: 0 12px;
    border-radius: 9px;
    border: 1.5px solid #d5dde6;
    background: #f8fafc;
    color: #334155;
    font-family: 'fontku', sans-serif;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
  }
  .operasi-btn:hover {
    background: #fff;
    border-color: #94a3b8;
    color: #0f172a;
    text-decoration: none;
  }
  .operasi-btn--op {
    background: #475569;
    border-color: #475569;
    color: #fff;
  }
  .operasi-btn--op:hover {
    background: #334155;
    border-color: #334155;
    color: #fff;
  }
  .operasi-btn--sp {
    border-color: #93c5fd;
    background: #eff6ff;
    color: #1d4ed8;
  }
  .operasi-btn--sd {
    border-color: #86efac;
    background: #f0fdf4;
    color: #15803d;
  }

  /* Selectize height align */
  .operasi-filter .selectize-control.single .selectize-input {
    min-height: 36px !important;
    height: 36px !important;
    padding: 6px 12px !important;
    border: 1.5px solid #d5dde6 !important;
    border-radius: 9px !important;
    background: #f8fafc !important;
    box-shadow: none !important;
    font-weight: 600;
  }
  .operasi-filter .selectize-control.single .selectize-input.focus {
    border-color: #3b82f6 !important;
    background: #fff !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
  }
  .operasi-filter .selectize-dropdown {
    border-radius: 10px;
    border-color: #d5dde6;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
  }

  .operasi-subrow {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    flex-wrap: wrap;
  }
  .operasi-periods {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px;
    background: #e8eef5;
    border-radius: 9px;
  }
  .operasi-period {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 30px;
    padding: 0 12px;
    border-radius: 7px;
    border: 0;
    background: transparent;
    color: #64748b;
    font-family: 'fontku', sans-serif;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
    transition: background .15s ease, color .15s ease, box-shadow .15s ease;
  }
  .operasi-period:hover {
    color: #1e293b;
    background: rgba(255,255,255,.55);
    text-decoration: none;
  }
  .operasi-year-nav {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .operasi-year-btn {
    box-sizing: border-box;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: 1.5px solid #d5dde6;
    background: #fff;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .operasi-year-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
  .operasi-year-btn:hover:not(:disabled) {
    border-color: #94a3b8;
    background: #f8fafc;
  }
  .operasi-year-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 72px;
    height: 34px;
    padding: 0 14px;
    border-radius: 9px;
    background: #2563eb;
    color: #fff;
    font-weight: 800;
    font-size: 14px;
  }

  #load.operasi-load {
    padding-top: 2px;
  }

  #fabOperasiButtons {
    z-index: 1020;
    display: flex;
    gap: 8px;
    bottom: 20px;
    right: 20px;
  }
  #fabOperasiButtons .operasi-fab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 44px;
    padding: 0 16px;
    border: 0;
    border-radius: 12px;
    font-family: 'fontku', sans-serif;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
  }
  #fabOperasiButtons .operasi-fab--order {
    background: linear-gradient(135deg, #f59e0b, #ea580c);
    color: #1a1a1a;
  }
  #fabOperasiButtons .operasi-fab--pay {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff;
  }
  #fabOperasiButtons.is-fab-hidden { display: none !important; }
  #offcanvasBukaOrderOp,
  #offcanvasPayment { z-index: 1100 !important; }
  .offcanvas-backdrop { z-index: 1090 !important; }
  .modal { z-index: 1200 !important; }
  .modal-backdrop { z-index: 1190 !important; }
</style>

<div class="operasi-filter" id="operasiFilter">
  <div class="operasi-filter-card">
    <div class="operasi-filter-row">
      <div class="operasi-field">
        <label>Pelanggan</label>
        <select name="pelanggan" data-id="<?= $id_pelanggan ?>" class="id_pelanggan tize form-control form-control-sm" required>
          <option value="" selected disabled>...</option>
          <?php foreach ($this->pelanggan as $a) { ?>
            <option value="<?= $a['id_pelanggan'] ?>" <?= $a['id_pelanggan'] == $id_pelanggan ? 'selected' : '' ?>><?= (strlen($a['nama_pelanggan']) > 10 ? strtoupper(substr($a['nama_pelanggan'], 0, 10)) . '...' : strtoupper($a['nama_pelanggan'])) ?> | <?= $a['nomor_pelanggan'] ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="operasi-actions">
        <div>
          <label>&nbsp;</label>
          <span onclick="cekData()" class="operasi-btn operasi-btn--op" title="Reload operasi">OP</span>
        </div>
        <div>
          <label>&nbsp;</label>
          <a class="hrfsp operasi-btn operasi-btn--sp" href="<?= URL::BASE_URL ?>Member/tambah_paket/<?= $id_pelanggan ?>" title="Saldo Paket">SP</a>
        </div>
        <div>
          <label>&nbsp;</label>
          <a class="hrfsd operasi-btn operasi-btn--sd" href="<?= URL::BASE_URL ?>SaldoTunai/tambah/<?= $id_pelanggan ?>" title="Saldo Deposit">SD</a>
        </div>
      </div>
    </div>

    <div class="operasi-subrow">
      <form id="main" class="m-0">
        <?php if ($modeOperasi == 1) {
          $currentYear = isset($data['currentYear']) ? $data['currentYear'] : date('Y');
          $selectedYear = isset($data['selectedYear']) ? $data['selectedYear'] : $currentYear;
          $minYear = isset($data['minYear']) ? $data['minYear'] : 2021;
        ?>
          <div class="operasi-year-nav">
            <button type="button" id="btnPrevYear" class="operasi-year-btn" <?= ($selectedYear <= $minYear) ? 'disabled' : '' ?>>
              <i class="fas fa-chevron-left"></i>
            </button>
            <span id="currentYearDisplay" class="operasi-year-label"><?= $selectedYear ?></span>
            <button type="button" id="btnNextYear" class="operasi-year-btn" <?= ($selectedYear >= $currentYear) ? 'disabled' : '' ?>>
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        <?php } else { ?>
          <div class="operasi-periods">
            <a href="<?= URL::BASE_URL ?>Antrian/index/1" class="operasi-period">Terkini</a>
            <a href="<?= URL::BASE_URL ?>Antrian/index/6" class="operasi-period">Minggu</a>
            <a href="<?= URL::BASE_URL ?>Antrian/index/7" class="operasi-period">Bulan</a>
          </div>
        <?php } ?>
      </form>
    </div>
  </div>
</div>

<div id="load" class="operasi-load"></div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
  (function syncOperasiStickyTop() {
    function apply() {
      var top = 0;
      var banner = document.querySelector('.training-banner');
      var bar = document.querySelector('.mdl-topbar');
      if (banner) top += banner.offsetHeight;
      if (bar) top += bar.offsetHeight;
      top += 2;
      document.documentElement.style.setProperty('--operasi-sticky-top', top + 'px');
    }
    apply();
    window.addEventListener('resize', apply);
  })();

  $(document).ready(function() {
    $('select.tize').selectize();

    <?php if ($modeOperasi == 1) { ?>
    var currentYear = <?= isset($data['currentYear']) ? $data['currentYear'] : date('Y') ?>;
    var selectedYear = <?= isset($data['selectedYear']) ? $data['selectedYear'] : date('Y') ?>;
    var minYear = <?= isset($data['minYear']) ? $data['minYear'] : 2021 ?>;
    <?php } ?>

    var pelanggan = <?= (int) $id_pelanggan ?>;
    if (pelanggan && pelanggan.length != 0) {
      <?php if ($modeOperasi == 1) { ?>
      loadDataOnly(pelanggan, selectedYear);
      <?php } else { ?>
      loadDataOnly(pelanggan);
      <?php } ?>
    }

    <?php if ($modeOperasi == 1) { ?>
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
        window.location.href = '<?= URL::BASE_URL ?>Operasi/i/<?= $modeOperasi ?>/' + value;
      }
    },
  });

  $('.tize').click(function() {
    $("select.tize")[0].selectize.clear();
  })

  function loadDataOnly(id, year) {
    $('.hrfsp').attr('href', '<?= URL::BASE_URL ?>Member/tambah_paket/' + id);
    $('.hrfsd').attr('href', '<?= URL::BASE_URL ?>SaldoTunai/tambah/' + id);

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

    var url = "<?= URL::BASE_URL ?>Operasi/loadData/" + id + "/" + <?= $modeOperasi ?>;
    if (year) {
      url += "?year=" + year;
    }
    url += (url.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now();

    $("div#load").load(url);
  }

  function load_data_operasi(id) {
    window.location.href = '<?= URL::BASE_URL ?>Operasi/i/<?= $modeOperasi ?>/' + id;
  }

  function cekData() {
    var pelanggan = <?= (int) $id_pelanggan ?>;

    if (pelanggan.length == 0) {
      return;
    } else {
      load_data_operasi(pelanggan);
    }
  }
</script>

<!-- Floating Action Buttons -->
<div id="fabOperasiButtons" class="position-fixed">
  <button id="btnBukaOrderOp" class="operasi-fab operasi-fab--order" type="button">
    <i class="fas fa-cash-register fa-lg"></i>
    <span>Order</span>
  </button>
  <?php if ($id_pelanggan > 0) { ?>
  <button id="btnTriggerPayment" class="operasi-fab operasi-fab--pay" type="button">
    <i class="fas fa-wallet fa-lg"></i>
    <span>Pay</span>
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

<script>
  document.addEventListener('show.bs.modal', function (e) {
      var el = e.target;
      if (!el || !el.classList.contains('modal')) return;
      var wrapper = el.closest('form') || el;
      if (wrapper.parentNode !== document.body) {
          document.body.appendChild(wrapper);
      }
  });

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
                      setTimeout(function() {
                          $('#bukaOrderContentOp .modal').appendTo("body");
                      }, 500);
                  }
              });
          }
      });

      offcanvasBukaOrderEl.addEventListener('hidden.bs.offcanvas', showFabOperasi);
  }

  $(document).on('click', '#btnTriggerPayment', function() {
      hideFabOperasi();
      var offcanvasPaymentEl = document.getElementById('offcanvasPayment');
      if (offcanvasPaymentEl) {
          var paymentOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasPaymentEl);
          paymentOffcanvas.toggle();
      }
  });

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
