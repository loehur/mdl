<style>
  table {
    border-radius: 15px;
    overflow: hidden
  }
</style>

<?php $modeView = $data['modeView'];
?>
<div class="position-fixed w-100 bg-light mx-1" style="z-index:1000;top:0px;height:205px">
</div>
<div class="w-100 sticky-top px-1 mb-2" style="top:72px;z-index:1001">
  <div class="bg-white p-1 rounded border mb-2" style="height:127px">
    <div class="row mx-0">
      <div class="col">
        <input id="searchInput" class="form-control border-top-0 border-bottom-1 border-end-0 border-start-0 p-1" type="text" placeholder="Pelanggan">
      </div>
    <?php if ($_SESSION[URL::SESSID]['user']['book'] == date('Y')) { ?>
        <div class="col">
          <div class="d-flex align-items-start align-items-end pt-1">
            <div class="pl-0 pe-1">
              <?php $outline = ($modeView == 1) ? "" : "outline-" ?>
              <a href="<?= URL::BASE_URL ?>Antrian/index/1" type="button" class="btn btn-<?= $outline ?>primary">
                Terkini
              </a>
              <?php $outline = "outline-" ?>
            </div>
            <div class="pl-0 pe-1">
              <?php $outline = ($modeView == 6) ? "" : "outline-" ?>
              <a href="<?= URL::BASE_URL ?>Antrian/index/6" type="button" class="btn btn-<?= $outline ?>success">
                Minggu
              </a>
              <?php $outline = "outline-" ?>
            </div>
            <div class="pl-0 pe-1">
              <?php $outline = ($modeView == 7) ? "" : "outline-" ?>
              <a href="<?= URL::BASE_URL ?>Antrian/index/7" type="button" class="btn btn-<?= $outline ?>info">
                Bulan
              </a>
              <?php $outline = "outline-" ?>
            </div>
          </div>
        </div>
    <?php } ?>
    </div>

    <div class="row ml-0 mt-1 mr-1 w-100">
      <div class="col">
        <span id="rekapAntri"></span>
      </div>
    </div>
  </div>
</div>

<script>
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

<!-- Floating Action Button - Buka Order (Offcanvas Trigger) -->
<button class="btn btn-warning rounded-3 shadow-lg position-fixed" 
   type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasBukaOrder"
   style="bottom: 24px; right: 24px; z-index: 1050; padding: 10px 18px; font-weight: 600;">
  <i class="fas fa-cash-register fa-lg me-2"></i>Order
</button>

<!-- Offcanvas Buka Order -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasBukaOrder" aria-labelledby="offcanvasBukaOrderLabel" data-bs-backdrop="false">
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

<script>
    var orderLoaded = false;
    var orderOffcanvas = document.getElementById('offcanvasBukaOrder');
    
    orderOffcanvas.addEventListener('show.bs.offcanvas', function () {
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
</script>