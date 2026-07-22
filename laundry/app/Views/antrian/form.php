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
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(30, 41, 59, 0.08);
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
    flex: 1 1 180px;
    min-width: 160px;
  }
  .antrian-search i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13px;
    pointer-events: none;
  }
  .antrian-search input {
    box-sizing: border-box;
    width: 100%;
    height: 36px;
    padding: 0 12px 0 34px;
    border: 1.5px solid #d5dde6;
    border-radius: 9px;
    background: #f8fafc;
    color: #1e293b;
    font-family: 'fontku', sans-serif;
    font-size: 14px;
    font-weight: 600;
    outline: none;
    transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
  }
  .antrian-search input::placeholder {
    color: #94a3b8;
    font-weight: 500;
  }
  .antrian-search input:focus {
    background: #fff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  }
  .antrian-periods {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px;
    background: #e8eef5;
    border-radius: 9px;
    flex: 0 0 auto;
  }
  .antrian-period {
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
  .antrian-period:hover {
    color: #1e293b;
    background: rgba(255,255,255,.55);
    text-decoration: none;
  }
  .antrian-period.is-active {
    background: #fff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
  }
  .antrian-period.is-active[data-tone="blue"] { color: #1d4ed8; }
  .antrian-period.is-active[data-tone="green"] { color: #15803d; }
  .antrian-period.is-active[data-tone="cyan"] { color: #0e7490; }

  .antrian-rekap {
    margin-top: 8px;
    min-height: 28px;
    font-size: 16px;
    line-height: 1.7;
    color: #475569;
  }
  .antrian-rekap:empty {
    display: none;
  }
  .antrian-rekap b {
    color: #334155;
    font-weight: 800;
  }
  .antrian-rekap .antrian-chip {
    display: inline;
    cursor: pointer;
    font-size: 16px;
    font-weight: 700;
    border-bottom: 1px dashed transparent;
    transition: border-color .15s ease;
  }
  .antrian-rekap .antrian-chip:hover {
    border-bottom-color: currentColor;
  }
  .antrian-chip--today,
  .antrian-chip--rak,
  .antrian-chip--miss { color: #dc2626; }
  .antrian-chip--besok { color: #2563eb; }
  .antrian-chip--kerja { color: #0891b2; }
  .antrian-chip--antri { color: #16a34a; }

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
    border-radius: 12px;
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
