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
    border: 1px solid #cbd5e1;
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
    color: #94a3b8;
    font-size: 14px;
    pointer-events: none;
  }
  .antrian-search input {
    box-sizing: border-box;
    width: 100%;
    height: 40px;
    padding: 0 12px 0 36px;
    border: 1px solid #94a3b8;
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
    color: #94a3b8;
    font-weight: 600;
  }
  .antrian-search input:focus {
    background: #fff;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  .antrian-periods {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px;
    background: #eff6ff;
    border: 1px solid #cbd5e1;
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
    color: #1e293b;
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
    background: #dbeafe;
    text-decoration: none;
  }
  .antrian-period.is-active {
    background: #0f172a;
    color: #fff;
    box-shadow: none;
  }
  .antrian-period.is-active[data-tone="blue"] { background: #2563eb; color: #fff; }
  .antrian-period.is-active[data-tone="green"] { background: #16a34a; color: #fff; }
  .antrian-period.is-active[data-tone="cyan"] { background: #1d4ed8; color: #fff; }
  .antrian-period.is-active[data-tone="black"] { background: #0f172a; color: #fff; }

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
    justify-content: center;
    height: auto;
    min-height: 26px;
    padding: 8px 12px;
    color: #fff;
    flex: 0 0 auto;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 0.01em;
    line-height: 1;
    text-transform: none;
    white-space: nowrap;
  }
  .antrian-rekap-group.is-clickable {
    cursor: pointer;
  }
  .antrian-rekap-group.is-clickable:hover .antrian-rekap-group__head {
    filter: brightness(1.08);
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
  /* Rekap tones → UI theme (nuansa lama: oranye→kuning, merah, biru, cyan→biru deep, hijau) */
  .antrian-rekap-group--today .antrian-rekap-group__head { background: #d97706; }
  .antrian-rekap-group--rak .antrian-rekap-group__head { background: #dc2626; }
  .antrian-rekap-group--miss .antrian-rekap-group__head { background: #b91c1c; }
  .antrian-rekap-group--besok .antrian-rekap-group__head { background: #2563eb; }
  .antrian-rekap-group--kerja .antrian-rekap-group__head { background: #1d4ed8; }
  .antrian-rekap-group--antri .antrian-rekap-group__head { background: #16a34a; }

  .antrian-search-empty {
    margin: 12px 0 0;
    padding: 16px 18px;
    border: 1px solid #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
    color: #0f172a;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    font-size: 0.95rem;
    font-weight: 750;
    line-height: 1.45;
  }
  .antrian-search-empty__period {
    font-weight: 900;
    color: #1d4ed8;
  }
  .antrian-search-empty__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 4px;
    padding: 6px 12px;
    border: 1px solid transparent;
    background: linear-gradient(180deg, #16a34a, #15803d);
    color: #fff !important;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    font-size: 0.88rem;
    font-weight: 900;
    text-decoration: none !important;
    vertical-align: middle;
    cursor: pointer;
  }
  .antrian-search-empty__btn:hover {
    filter: brightness(1.06);
    color: #fff !important;
  }
  .antrian-search-empty__btn[data-tone="cyan"] {
    background: linear-gradient(180deg, #2563eb, #1d4ed8);
  }
  .antrian-search-empty__btn[data-tone="black"] {
    background: linear-gradient(180deg, #334155, #0f172a);
  }
  .antrian-search-empty__final {
    border-color: #fca5a5;
    background: linear-gradient(180deg, #fef2f2, #fff);
    color: #b91c1c;
    font-weight: 800;
  }

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
      grid-template-columns: repeat(auto-fill, 480px) !important;
      gap: 8px !important;
    }
    #load.antrian-load .mdl-nota-grid__item {
      width: 480px !important;
      max-width: 480px !important;
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
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #0f172a;
    box-shadow: 0 8px 20px rgba(217, 119, 6, 0.35);
  }
  #btnBukaOrderAntrian:hover {
    filter: brightness(1.05);
    color: #0f172a;
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
        <a href="<?= URL::BASE_URL ?>Antrian/index/8"
           class="antrian-period<?= ($modeView == 8) ? ' is-active' : '' ?>"
           data-tone="black">Tahun</a>
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
      top += 2;
      document.documentElement.style.setProperty('--antrian-sticky-top', top + 'px');
    }
    apply();
    window.addEventListener('resize', apply);
  })();

  window.ANTRIAN_MODE = <?= (int) $modeView ?>;
  window.ANTRIAN_BASE = <?= json_encode(URL::BASE_URL) ?>;
  window.ANTRIAN_NEXT = {
    1: { mode: 6, current: 'Terkini', next: 'Minggu', tone: 'green' },
    6: { mode: 7, current: 'Minggu', next: 'Bulan', tone: 'cyan' },
    7: { mode: 8, current: 'Bulan', next: 'Tahun', tone: 'black' },
    8: { mode: null, current: 'Tahun', next: null, tone: null }
  };

  $("input#searchInput").on("keyup change input", function() {
    search();
  });

  function clearAntrianSearchEmpty() {
    $("#antrianSearchEmpty").remove();
  }

  function showAntrianSearchEmpty() {
    clearAntrianSearchEmpty();
    var info = window.ANTRIAN_NEXT[window.ANTRIAN_MODE] || window.ANTRIAN_NEXT[1];
    var html;
    if (!info || !info.mode) {
      html = '<div id="antrianSearchEmpty" class="antrian-search-empty antrian-search-empty__final">' +
        'Tidak ada data ditemukan' +
        '</div>';
    } else {
      html = '<div id="antrianSearchEmpty" class="antrian-search-empty">' +
        'Tidak ditemukan di data <span class="antrian-search-empty__period">' + info.current + '</span>, coba cari di ' +
        '<a href="' + window.ANTRIAN_BASE + 'Antrian/index/' + info.mode + '" ' +
        'class="antrian-search-empty__btn" data-tone="' + info.tone + '" data-antrian-next="' + info.mode + '">' +
        info.next + '</a>' +
        '</div>';
    }
    var $load = $("div#load");
    if ($load.length) {
      $load.prepend(html);
    } else {
      $("#antrianFilter").after(html);
    }
  }

  function search() {
    var pelanggan = ($("input#searchInput").val() || "").toUpperCase().trim();
    clearAntrianSearchEmpty();

    if (pelanggan.length === 0) {
      $(".backShow").removeClass('d-none');
      return;
    }

    $("div.backShow").addClass('d-none');
    var matches = [];
    $("div.backShow").each(function() {
      var name = String($(this).attr("data-search-name") || "").toUpperCase();
      if (name.indexOf(pelanggan) !== -1) {
        matches.push(this);
      }
    });
    $(matches).removeClass('d-none');

    if (matches.length === 0) {
      showAntrianSearchEmpty();
    }
  }

  $(document).on('click', '[data-antrian-next]', function() {
    try {
      sessionStorage.setItem('antrianSearchQ', $("input#searchInput").val() || '');
    } catch (e) {}
  });

  window.antrianAfterLoad = function() {
    var q = '';
    try {
      q = sessionStorage.getItem('antrianSearchQ') || '';
      if (q) sessionStorage.removeItem('antrianSearchQ');
    } catch (e) {}
    if (q) {
      $("input#searchInput").val(q);
    }
    if (($("input#searchInput").val() || '').trim().length > 0) {
      search();
    }
  };
</script>

<!-- Floating Action Button - Buka Order -->
<button id="btnBukaOrderAntrian" class="position-fixed"
   type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasBukaOrder">
  <i class="fas fa-cash-register fa-lg me-2"></i>Order
</button>

<!-- Offcanvas Buka Order -->
<style>
  #offcanvasBukaOrder .offcanvas-header {
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    border-bottom: 0;
    padding: 1rem 1.15rem;
  }
  #offcanvasBukaOrder .offcanvas-title {
    font-family: 'fontku', sans-serif;
    font-weight: 900;
    letter-spacing: -0.02em;
    margin: 0;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #offcanvasBukaOrder .offcanvas-body {
    background:
      radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.14), transparent 50%),
      radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.14), transparent 45%),
      linear-gradient(180deg, #eef4ff 0%, #f4fff8 50%, #fff8eb 100%);
  }
</style>
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasBukaOrder" aria-labelledby="offcanvasBukaOrderLabel" data-bs-backdrop="true" style="z-index: 1100; --bs-offcanvas-width: min(820px, 100vw);">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasBukaOrderLabel">
      <i class="fas fa-cash-register me-2"></i>Buka Order
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0" id="bukaOrderContent">
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
