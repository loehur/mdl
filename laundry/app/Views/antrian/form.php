<?php $modeView = $data['modeView']; ?>

<style>
  .antrian-filter {
    position: sticky;
    top: var(--antrian-sticky-top, 50px);
    z-index: 100;
    margin: 0 0 12px;
  }
  .antrian-filter-card {
    background: #fff;
    border: 1px solid #dde3ea;
    border-radius: 0;
    box-shadow: none;
    padding: 10px 12px;
  }
  .antrian-filter-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .antrian-search {
    position: relative;
    flex: 1 1 200px;
    min-width: 170px;
  }
  .antrian-search i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 14px;
    pointer-events: none;
  }
  .antrian-search input {
    box-sizing: border-box;
    width: 100%;
    height: 40px;
    padding: 0 12px 0 36px;
    border: 1px solid #d5dde6;
    border-radius: 0;
    background: #fff;
    color: #0f172a;
    font-family: 'fontku', sans-serif;
    font-size: 15px;
    font-weight: 700;
    outline: none;
    transition: border-color .12s ease, box-shadow .12s ease;
  }
  .antrian-search input::placeholder {
    color: #64748b;
    font-weight: 600;
  }
  .antrian-search input:focus {
    background: #fff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
  }
  .antrian-periods {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px;
    background: #e8eef5;
    border: 1px solid #d5dde6;
    border-radius: 0;
    flex: 0 0 auto;
  }
  .antrian-period {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 34px;
    padding: 0 14px;
    border-radius: 0;
    border: 0;
    background: transparent;
    color: #334155;
    font-family: 'fontku', sans-serif;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.01em;
    text-decoration: none;
    white-space: nowrap;
    transition: background .12s ease, color .12s ease;
  }
  .antrian-period:hover {
    color: #0f172a;
    background: #f8fafc;
    text-decoration: none;
  }
  .antrian-period.is-active {
    background: #0f172a;
    color: #fff;
    box-shadow: none;
  }
  .antrian-period.is-active[data-tone="blue"] { background: #1d4ed8; color: #fff; }
  .antrian-period.is-active[data-tone="green"] { background: #15803d; color: #fff; }
  .antrian-period.is-active[data-tone="cyan"] { background: #0e7490; color: #fff; }

  .antrian-rekap {
    margin-top: 6px;
    min-height: 0;
    color: #0f172a;
  }
  .antrian-rekap:empty {
    display: none;
  }
  .antrian-rekap-board {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    align-items: center;
  }
  .antrian-rekap-group {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0;
    max-width: 100%;
    padding: 0;
    border: 0;
    background: transparent;
  }
  .antrian-rekap-group:last-child {
    border-right: 0;
    padding-right: 0;
  }
  .antrian-rekap-group__head {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    height: 26px;
    padding: 0 10px;
    color: #fff;
    flex: 0 0 auto;
  }
  .antrian-rekap-group.is-clickable {
    cursor: pointer;
  }
  .antrian-rekap-group.is-clickable:hover .antrian-rekap-group__head {
    filter: brightness(1.08);
  }
  .antrian-rekap-group__label {
    display: inline-flex;
    align-items: center;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 0.01em;
    text-transform: uppercase;
    white-space: nowrap;
    color: #fff;
  }
  .antrian-rekap-group__total {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 14px;
    height: auto;
    padding: 0;
    background: transparent;
    border: 0;
    font-size: 13px;
    font-weight: 600;
    line-height: 1;
    color: #fff;
  }
  .antrian-rekap-group__items {
    display: none;
  }
  .antrian-rekap-item {
    display: inline-flex;
    align-items: baseline;
    gap: 2px;
    padding: 0;
    border: 0;
    background: transparent;
    color: #0f172a;
    font-family: 'fontku', sans-serif;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.2;
    white-space: nowrap;
  }
  .antrian-rekap-item b {
    color: #020617;
    font-size: 13px;
    font-weight: 600;
  }
  .antrian-rekap-group--today .antrian-rekap-group__head { background: #c2410c; }
  .antrian-rekap-group--rak .antrian-rekap-group__head { background: #b91c1c; }
  .antrian-rekap-group--miss .antrian-rekap-group__head { background: #9f1239; }
  .antrian-rekap-group--besok .antrian-rekap-group__head { background: #1d4ed8; }
  .antrian-rekap-group--kerja .antrian-rekap-group__head { background: #0e7490; }
  .antrian-rekap-group--antri .antrian-rekap-group__head { background: #15803d; }

  #load.antrian-load {
    padding-top: 2px;
    width: 100%;
    max-width: 100%;
    margin: 0;
    box-sizing: border-box;
  }
  #load.antrian-load .mdl-nota-grid,
  #load.antrian-load .mdl-nota-grid__item,
  #load.antrian-load .mdl-nota-card {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    box-sizing: border-box;
  }
  #load.antrian-load .mdl-nota-grid {
    display: grid !important;
    grid-template-columns: 1fr;
    gap: 8px !important;
  }
  .antrian-filter {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
  @media (min-width: 1100px) {
    #load.antrian-load .mdl-nota-grid {
      grid-template-columns: repeat(auto-fill, 500px) !important;
      gap: 8px !important;
    }
    #load.antrian-load .mdl-nota-grid__item {
      width: 500px !important;
      max-width: 500px !important;
      margin: 0 !important;
    }
  }

  #btnBukaOrderAntrian {
    bottom: 24px;
    right: 24px;
    z-index: 1020;
    padding: 10px 18px;
    font-weight: 700;
    border: 0;
    border-radius: 0;
    background: linear-gradient(135deg, #f59e0b, #ea580c);
    color: #1a1a1a;
    box-shadow: 0 8px 20px rgba(234, 88, 12, 0.35);
  }
  #btnBukaOrderAntrian:hover {
    filter: brightness(1.05);
    color: #1a1a1a;
  }
  #btnBukaOrderAntrian.is-fab-hidden { display: none !important; }
  #offcanvasBukaOrder { z-index: 1100 !important; }
  .offcanvas-backdrop { z-index: 1090 !important; }
  .modal { z-index: 1200 !important; }
  .modal-backdrop { z-index: 1190 !important; }
</style>

<div class="antrian-filter" id="antrianFilter">
  <div class="antrian-filter-card">
    <div class="antrian-filter-row">
      <div class="antrian-search">
        <i class="fas fa-search"></i>
        <input id="searchInput" type="text" placeholder="Cari pelanggan..." autocomplete="off">
      </div>
      <div class="antrian-periods">
        <a href="<?= URL::BASE_URL ?>Antrian/index/1"
           class="antrian-period<?= ($modeView == 1) ? ' is-active' : '' ?>"
           data-tone="blue">Terkini</a>
        <a href="<?= URL::BASE_URL ?>Antrian/index/6"
           class="antrian-period<?= ($modeView == 6) ? ' is-active' : '' ?>"
           data-tone="green">Minggu</a>
        <a href="<?= URL::BASE_URL ?>Antrian/index/7"
           class="antrian-period<?= ($modeView == 7) ? ' is-active' : '' ?>"
           data-tone="cyan">Bulan</a>
      </div>
    </div>
    <div class="antrian-rekap" id="rekapAntri"></div>
  </div>
</div>

<script>
  (function syncAntrianStickyTop() {
    function apply() {
      var top = 0;
      var banner = document.querySelector('.training-banner');
      var bar = document.querySelector('.mdl-topbar');
      if (banner) top += banner.offsetHeight;
      if (bar) top += bar.offsetHeight;
      // sedikit jarak di bawah topbar
      top += 2;
      document.documentElement.style.setProperty('--antrian-sticky-top', top + 'px');
    }
    apply();
    window.addEventListener('resize', apply);
  })();

  $("input#searchInput").on("keyup change", function() {
    search();
  });

  function search() {
    var pelanggan = $("input#searchInput").val().toUpperCase();
    if (pelanggan.length > 0) {
      $("div.backShow").addClass('d-none');
      $("[class*=" + pelanggan + "]").removeClass('d-none');
    } else {
      $(".backShow").removeClass('d-none');
    }
  }
</script>

<!-- Floating Action Button - Buka Order -->
<button id="btnBukaOrderAntrian" class="position-fixed"
   type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasBukaOrder">
  <i class="fas fa-cash-register fa-lg me-2"></i>Order
</button>

<!-- Offcanvas Buka Order -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasBukaOrder" aria-labelledby="offcanvasBukaOrderLabel" data-bs-backdrop="true" style="z-index: 1100; --bs-offcanvas-width: min(820px, 100vw);">
  <div class="offcanvas-header" style="background:linear-gradient(145deg,#2f61bc,#3f74d4);color:#fff;border-bottom:0;">
    <h5 class="offcanvas-title fw-bold mb-0" id="offcanvasBukaOrderLabel" style="font-family:'fontku',sans-serif;letter-spacing:-0.01em;">
      <i class="fas fa-cash-register me-2"></i>Buka Order
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0" id="bukaOrderContent" style="background:#F4F7FB;">
    <div class="d-flex justify-content-center align-items-center py-5">
        <div class="spinner-border text-primary" role="status">
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
    var orderOffcanvas = document.getElementById('offcanvasBukaOrder');

    orderOffcanvas.addEventListener('show.bs.offcanvas', function () {
        $('#btnBukaOrderAntrian').addClass('is-fab-hidden');
        if(!orderLoaded) {
            $('#bukaOrderContent').load('<?= URL::BASE_URL ?>Penjualan', function(response, status, xhr) {
                if (status == "error") {
                    $('#bukaOrderContent').html('<div class="alert alert-danger m-3">Gagal memuat halaman order: ' + xhr.status + " " + xhr.statusText + '</div>');
                } else {
                    orderLoaded = true;
                    setTimeout(function() {
                        $('#bukaOrderContent .modal').appendTo("body");
                    }, 500);
                }
            });
        }
    });

    orderOffcanvas.addEventListener('hidden.bs.offcanvas', function () {
        $('#btnBukaOrderAntrian').removeClass('is-fab-hidden');
    });
</script>
