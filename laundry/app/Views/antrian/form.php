<?php $modeView = $data['modeView'];
$isLaundry = ((int) $modeView !== 100);
?>
<div class="position-fixed w-100 bg-light mx-1" style="z-index:1000;top:0px;height:205px">
</div>
<div class="w-100 sticky-top px-1 mb-2" style="top:72px;z-index:1001">
  <div class="bg-white p-1 rounded border mb-2" style="height:127px">
    <div class="row mx-0">
      <div class="col">
        <input id="searchInput" class="form-control border-top-0 border-bottom-1 border-end-0 border-start-0 p-1" type="text" placeholder="Pelanggan" autocomplete="off">
      </div>
        <div class="col">
          <div class="d-flex align-items-start align-items-end pt-1">
            <?php if ($isLaundry) { ?>
            <div class="pl-0 pe-1">
              <a href="<?= URL::BASE_URL ?>Antrian/index/1" type="button" class="btn btn-primary">
                Laundry
              </a>
            </div>
            <?php } else { ?>
            <div class="pl-0 pe-1">
              <span class="btn btn-warning disabled">Piutang</span>
            </div>
            <?php } ?>
          </div>
        </div>
    </div>

    <div class="row ml-0 mt-1 mr-1 w-100">
      <div class="col">
        <span id="rekapAntri"></span>
      </div>
    </div>
  </div>
</div>

<script>
  var antrianSearchTimer = null;

  $("input#searchInput").on("keyup input", function() {
    var pelanggan = $.trim($(this).val());

    <?php if (!$isLaundry) { ?>
    // Mode piutang: filter client-side (data sudah ter-load penuh)
    searchClient(pelanggan);
    return;
    <?php } ?>

    clearTimeout(antrianSearchTimer);

    if (pelanggan.length === 0) {
      antrianSearchTimer = setTimeout(function() {
        if (typeof window.antrianSearch === 'function') {
          window.antrianSearch('');
        }
      }, 300);
      return;
    }

    if (pelanggan.length < 2) {
      return;
    }

    antrianSearchTimer = setTimeout(function() {
      if (typeof window.antrianSearch === 'function') {
        window.antrianSearch(pelanggan);
      }
    }, 450);
  });

  function searchClient(pelanggan) {
    pelanggan = (pelanggan || '').toUpperCase();
    if (pelanggan.length > 0) {
      $("div.backShow").addClass('d-none');
      $("[class*=" + pelanggan + "]").removeClass('d-none');
    } else {
      $(".backShow").removeClass('d-none');
    }
  }
</script>

<!-- Floating Action Button - Buka Order (Offcanvas Trigger) -->
<button id="btnBukaOrderAntrian" class="btn btn-warning rounded-3 shadow-lg position-fixed" 
   type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasBukaOrder"
   style="bottom: 24px; right: 24px; z-index: 1020; padding: 10px 18px; font-weight: 600;">
  <i class="fas fa-cash-register fa-lg me-2"></i>Order
</button>

<!-- Offcanvas Buka Order -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasBukaOrder" aria-labelledby="offcanvasBukaOrderLabel" data-bs-backdrop="true" style="z-index: 1100;">
  <div class="offcanvas-header bg-warning bg-gradient">
    <h5 class="offcanvas-title fw-bold text-dark" id="offcanvasBukaOrderLabel"><i class="fas fa-cash-register me-2"></i>Buka Order Baru</h5>
    <button type="button" class="btn-close text-dark" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0" id="bukaOrderContent">
    <div class="d-flex justify-content-center align-items-center h-100">
        <div class="spinner-border text-warning" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
  </div>
</div>

<style>
  #offcanvasBukaOrder { z-index: 1100 !important; }
  .offcanvas-backdrop { z-index: 1090 !important; }
  .modal { z-index: 1200 !important; }
  .modal-backdrop { z-index: 1190 !important; }
  #btnBukaOrderAntrian.is-fab-hidden { display: none !important; }
</style>

<script>
    // Pastikan modal (atau form pembungkusnya) selalu di body
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
                    // Pindahkan modal yang ada di dalam konten yang baru diload ke body
                    // agar backdrop dan z-index berfungsi dengan benar
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
