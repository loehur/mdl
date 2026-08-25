<?php
if ($data['id_pelanggan'] > 0) {
  $id_pelanggan = $data['id_pelanggan'];
} else {
  $id_pelanggan = 0;
}
$modeOperasi = (int) $data['mode'];
$chatHp = '';
$chatNama = '';
if ($id_pelanggan > 0 && isset($this->pelanggan[$id_pelanggan]) && is_array($this->pelanggan[$id_pelanggan])) {
  $chatHp = (string) ($this->pelanggan[$id_pelanggan]['nomor_pelanggan'] ?? '');
  $chatNama = strtoupper((string) ($this->pelanggan[$id_pelanggan]['nama_pelanggan'] ?? ''));
}
?>

<style>
  .operasi-filter {
    position: sticky;
    top: var(--operasi-sticky-top, 50px);
    z-index: 100;
    margin: 0 0 8px;
  }
  .operasi-filter-card {
    background: #fff;
    border: 1px solid #dde3ea;
    border-radius: 0;
    box-shadow: none;
    padding: 10px 12px;
  }
  .operasi-filter-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .operasi-field-label {
    display: block;
    margin: 0;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #64748b;
  }
  .operasi-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    width: auto;
    max-width: min(420px, 100%);
  }
  .operasi-field {
    flex: 1 1 auto;
    min-width: 140px;
    max-width: min(380px, 100%);
  }
  .operasi-toolbar-btn {
    box-sizing: border-box;
    flex: 0 0 auto;
    width: 36px;
    height: 36px;
    min-width: 36px;
    padding: 0;
    margin: 0;
    border-radius: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 15px;
    text-decoration: none;
  }
  .operasi-toolbar-btn:disabled,
  .operasi-toolbar-btn.is-disabled {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
  }
  .operasi-chat-btn {
    border: 1px solid #93c5fd;
    background: linear-gradient(180deg, #eff6ff, #fff);
    color: #1d4ed8;
  }
  .operasi-chat-btn:hover:not(:disabled):not(.is-disabled) {
    background: linear-gradient(180deg, #2563eb, #1d4ed8);
    border-color: #1d4ed8;
    color: #fff;
  }
  .operasi-label-btn {
    border: 1px solid #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fef3c7);
    color: #d97706;
  }
  .operasi-label-btn:hover:not(:disabled):not(.is-disabled) {
    background: linear-gradient(180deg, #fde68a, #fcd34d);
    border-color: #f59e0b;
    color: #78350f;
  }
  .operasi-bill-btn {
    border: 1px solid #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fef3c7);
    color: #d97706;
  }
  .operasi-bill-btn:hover:not(:disabled):not(.is-disabled) {
    background: linear-gradient(180deg, #fde68a, #fcd34d);
    border-color: #f59e0b;
    color: #78350f;
  }
  /* Selectize height align — sama persis 36px; satu border saja */
  .operasi-filter .id_pelanggan.form-control,
  .operasi-filter select.tize,
  .operasi-filter select.selectized {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0 !important;
  }
  .operasi-filter .selectize-control {
    height: 36px !important;
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
  }
  .operasi-filter .selectize-control.single .selectize-input {
    box-sizing: border-box !important;
    display: flex !important;
    align-items: center !important;
    min-height: 36px !important;
    height: 36px !important;
    max-height: 36px !important;
    padding: 0 12px !important;
    line-height: 1 !important;
    border: 1px solid #d5dde6 !important;
    border-radius: 0 !important;
    background: #f8fafc !important;
    box-shadow: none !important;
    font-size: 13px !important;
    font-weight: 600;
    overflow: hidden !important;
  }
  .operasi-filter .selectize-control.single .selectize-input:after {
    border: 0 !important;
  }
  .operasi-filter .selectize-control.single .selectize-input > * {
    line-height: 1 !important;
  }
  .operasi-filter .selectize-control.single .selectize-input input {
    height: 20px !important;
    line-height: 20px !important;
  }
  .operasi-filter .selectize-control.single .selectize-input.focus {
    border-color: #3b82f6 !important;
    background: #fff !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
  }
  .operasi-filter .selectize-dropdown {
    border-radius: 0;
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
    gap: 6px;
    padding: 0;
    background: transparent;
    border-radius: 0;
  }
  .operasi-period {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 34px;
    padding: 0 14px;
    border-radius: 0;
    border: 1px solid transparent;
    font-family: 'fontku', sans-serif;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.01em;
    text-decoration: none;
    white-space: nowrap;
    color: #fff;
    transition: filter .12s ease, transform .12s ease, opacity .12s ease;
  }
  .operasi-period:hover {
    color: #fff;
    text-decoration: none;
    filter: brightness(1.08);
  }
  .operasi-period:active {
    transform: translateY(1px);
    filter: brightness(0.95);
  }
  .operasi-period--terkini {
    background: #1d4ed8;
    border-color: #1e40af;
  }
  .operasi-period--minggu {
    background: #15803d;
    border-color: #166534;
  }
  .operasi-period--bulan {
    background: #0e7490;
    border-color: #155e75;
  }
  .operasi-period--tahun {
    background: #0f172a;
    border-color: #020617;
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
    border-radius: 0;
    border: 1px solid #d5dde6;
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
    border-radius: 0;
    background: #2563eb;
    color: #fff;
    font-weight: 800;
    font-size: 14px;
  }

  #load.operasi-load {
    padding-top: 0;
    width: 100%;
    max-width: 100%;
    margin: 0;
    box-sizing: border-box;
  }
  #load.operasi-load .mdl-nota-grid,
  #load.operasi-load .mdl-nota-grid__item,
  #load.operasi-load .mdl-nota-card,
  #load.operasi-load .op-dlv-grid__item {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    box-sizing: border-box;
  }
  #load.operasi-load .mdl-nota-grid {
    display: grid !important;
    grid-template-columns: 1fr;
    gap: 8px !important;
  }
  /* Soft badge jemput/antar dari delivery_riwayat */
  #load.operasi-load .mdl-dlv-badge {
    display: inline-block;
    margin-left: 4px;
    padding: 0 5px;
    border: 1px solid #cbd5e1;
    border-radius: 0;
    background: #f1f5f9;
    color: #0f172a;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.02em;
    vertical-align: middle;
    line-height: 1.35;
  }
  #load.operasi-load .mdl-dlv-badge--j {
    background: #fffbeb;
    border-color: #fcd34d;
    color: #b45309;
  }
  #load.operasi-load .mdl-dlv-badge--a {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
  }
  #load.operasi-load .mdl-dlv-badge--ja {
    background: #f0fdf4;
    border-color: #86efac;
    color: #15803d;
  }
  /* Surcas kurir terikat — item tidak eligible di panel Kurir */
  #load.operasi-load .mdl-kurir-bind-badge {
    display: inline-block;
    margin-left: 3px;
    padding: 0 4px;
    border: 1px dashed #c4b5fd;
    border-radius: 0;
    background: #f5f3ff;
    color: #6d28d9;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.01em;
    vertical-align: middle;
    line-height: 1.35;
  }
  #load.operasi-load .mdl-kurir-bind-badge--j {
    border-color: #fcd34d;
    background: #fffbeb;
    color: #92400e;
  }
  #load.operasi-load .mdl-kurir-bind-badge--a {
    border-color: #93c5fd;
    background: #eff6ff;
    color: #1e40af;
  }
  #load.operasi-load .mdl-kurir-bind-badge--ja {
    border-color: #86efac;
    background: #ecfdf5;
    color: #047857;
  }
  .operasi-filter {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
  @media (min-width: 1100px) {
    #load.operasi-load .mdl-nota-grid {
      grid-template-columns: repeat(auto-fill, 460px) !important;
      gap: 8px !important;
    }
    #load.operasi-load .mdl-nota-grid__item,
    #load.operasi-load .op-dlv-grid__item {
      width: 460px !important;
      max-width: 460px !important;
      margin: 0 !important;
    }
  }

  /* FAB always visible; stay behind Order/Pay offcanvas + backdrop */
  #fabOperasiButtons {
    z-index: 1040;
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
    border-radius: 0;
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
  #fabOperasiButtons .operasi-fab--kurir {
    background: linear-gradient(135deg, #a855f7, #7c3aed);
    color: #fff;
    box-shadow: 0 8px 20px rgba(124, 58, 237, 0.35);
  }
  #offcanvasBukaOrderOp,
  #offcanvasPayment,
  #offcanvasKurir { z-index: 1100 !important; }
  .offcanvas-backdrop { z-index: 1090 !important; }
  #offcanvasBukaOrderOp,
  #offcanvasBukaOrderOp .offcanvas-header,
  #offcanvasBukaOrderOp .btn,
  #offcanvasBukaOrderOp button,
  #offcanvasPayment,
  #offcanvasKurir {
    border-radius: 0 !important;
  }
  /* Operasi dialogs use .op-modal (z-index 5200) — above offcanvas */
</style>

<div class="operasi-filter" id="operasiFilter">
  <div class="operasi-filter-card">
    <div class="operasi-filter-row">
      <label class="operasi-field-label">Pelanggan</label>
      <div class="operasi-controls">
        <div class="operasi-field">
          <select name="pelanggan" data-id="<?= $id_pelanggan ?>" class="id_pelanggan tize" required>
            <option value="" selected disabled>...</option>
            <?php foreach ($this->pelanggan as $a) { ?>
              <option value="<?= $a['id_pelanggan'] ?>"
                data-nama="<?= htmlspecialchars(strtoupper($a['nama_pelanggan']), ENT_QUOTES, 'UTF-8') ?>"
                data-hp="<?= htmlspecialchars((string) ($a['nomor_pelanggan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                <?= $a['id_pelanggan'] == $id_pelanggan ? 'selected' : '' ?>><?= (strlen($a['nama_pelanggan']) > 10 ? strtoupper(substr($a['nama_pelanggan'], 0, 10)) . '...' : strtoupper($a['nama_pelanggan'])) ?> | <?= $a['nomor_pelanggan'] ?></option>
            <?php } ?>
          </select>
        </div>
        <button type="button"
          class="operasi-toolbar-btn operasi-chat-btn"
          id="btnOperasiChat"
          title="Riwayat Chat"
          aria-label="Riwayat Chat"
          data-hp="<?= htmlspecialchars($chatHp, ENT_QUOTES, 'UTF-8') ?>"
          data-nama="<?= htmlspecialchars($chatNama, ENT_QUOTES, 'UTF-8') ?>"
          <?= ($id_pelanggan > 0 && $chatHp !== '') ? '' : 'disabled' ?>>
          <i class="fas fa-comments"></i>
        </button>
        <button type="button"
          class="operasi-toolbar-btn operasi-label-btn"
          id="btnOperasiLabel"
          title="Cetak Label"
          aria-label="Cetak Label"
          data-print-id="Label"
          <?= $id_pelanggan > 0 ? '' : 'disabled' ?>>
          <i class="fas fa-tags"></i>
        </button>
        <a class="operasi-toolbar-btn operasi-bill-btn<?= $id_pelanggan > 0 ? '' : ' is-disabled' ?>"
          id="btnOperasiTagihan"
          href="<?= $id_pelanggan > 0 ? URL::BASE_URL . 'I/' . $id_pelanggan : '#' ?>"
          target="_blank"
          title="Tagihan"
          aria-label="Tagihan"
          <?= $id_pelanggan > 0 ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
          <i class="fas fa-receipt"></i>
        </a>
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
            <a href="<?= URL::BASE_URL ?>Antrian/index/1" class="operasi-period operasi-period--terkini">Terkini</a>
            <a href="<?= URL::BASE_URL ?>Antrian/index/6" class="operasi-period operasi-period--minggu">Minggu</a>
            <a href="<?= URL::BASE_URL ?>Antrian/index/7" class="operasi-period operasi-period--bulan">Bulan</a>
            <a href="<?= URL::BASE_URL ?>Antrian/index/8" class="operasi-period operasi-period--tahun">Tahun</a>
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
<script src="<?= URL::IN_ASSETS ?>js/operasi/delivery_requests.js?v=<?= time() ?>"></script>

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

    $('#btnOperasiChat').on('click', function() {
      var $btn = $(this);
      if ($btn.prop('disabled')) return;
      var hp = String($btn.attr('data-hp') || '').trim();
      var nama = String($btn.attr('data-nama') || 'Pelanggan').trim();
      if (!hp) {
        if (window.MdlToast) MdlToast.warn('Nomor pelanggan tidak tersedia');
        return;
      }
      if (window.MdlChatHistory && typeof MdlChatHistory.open === 'function') {
        MdlChatHistory.open(hp, nama, { showCloseCase: false });
      } else if (window.MdlToast) {
        MdlToast.error('Modal chat belum siap');
      }
    });

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
  <button id="btnTriggerKurir" class="operasi-fab operasi-fab--kurir" type="button">
    <i class="fas fa-motorcycle fa-lg"></i>
    <span>Kurir</span>
  </button>
  <button id="btnTriggerPayment" class="operasi-fab operasi-fab--pay" type="button">
    <i class="fas fa-wallet fa-lg"></i>
    <span>Pay</span>
  </button>
  <?php } ?>
</div>

<!-- Offcanvas Buka Order -->
<style>
  #offcanvasBukaOrderOp {
    --bs-offcanvas-width: min(820px, 100vw);
  }
  #offcanvasBukaOrderOp .offcanvas-header {
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    border-bottom: 0;
    padding: 1rem 1.15rem;
  }
  #offcanvasBukaOrderOp .offcanvas-title {
    font-family: 'fontku', sans-serif;
    font-weight: 900;
    letter-spacing: -0.02em;
    margin: 0;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #offcanvasBukaOrderOp .offcanvas-body {
    background:
      radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.14), transparent 50%),
      radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.14), transparent 45%),
      linear-gradient(180deg, #eef4ff 0%, #f4fff8 50%, #fff8eb 100%);
  }
</style>
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasBukaOrderOp" aria-labelledby="offcanvasBukaOrderOpLabel" data-bs-backdrop="true" data-bs-scroll="true" style="z-index: 1100; --bs-offcanvas-width: min(820px, 100vw);">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasBukaOrderOpLabel">
      <i class="fas fa-cash-register me-2"></i>Buka Order
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0" id="bukaOrderContentOp">
    <div class="d-flex justify-content-center align-items-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
  </div>
</div>

<script>
  var orderLoaded = false;
  var offcanvasBukaOrderEl = document.getElementById('offcanvasBukaOrderOp');

  if (offcanvasBukaOrderEl) {
      var bsOffcanvas = new bootstrap.Offcanvas(offcanvasBukaOrderEl);

      $('#btnBukaOrderOp').on('click', function() {
          bsOffcanvas.toggle();
      });

      offcanvasBukaOrderEl.addEventListener('show.bs.offcanvas', function () {
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
  }

  $(document).on('click', '#btnTriggerPayment', function() {
      var offcanvasPaymentEl = document.getElementById('offcanvasPayment');
      if (offcanvasPaymentEl) {
          var paymentOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasPaymentEl);
          paymentOffcanvas.toggle();
      }
  });

  $(document).on('click', '#btnTriggerKurir', function() {
      var el = document.getElementById('offcanvasKurir');
      if (el) {
          bootstrap.Offcanvas.getOrCreateInstance(el).toggle();
      } else if (window.MdlToast) {
          MdlToast.warn('Muat data Operasi pelanggan dulu');
      }
  });
</script>
