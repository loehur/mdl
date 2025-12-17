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
        <select name="pelanggan" class="id_pelanggan tize form-control form-control-sm" required>
          <option value="" selected disabled>...</option>
          <?php foreach ($this->pelanggan as $a) { ?>
            <option <?php if ($id_pelanggan == $a['id_pelanggan']) {
                      echo "selected";
                    } ?> value="<?= $a['id_pelanggan'] ?>"><?= strtoupper($a['nama_pelanggan'])  ?> | <?= $a['nomor_pelanggan'] ?></option>
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

    <?php if ($_SESSION[URL::SESSID]['user']['book'] == date('Y')) { ?>
      <div class="row mt-1 mr-1 w-100">
        <form id="main">
          <div class="d-flex align-items-start align-items-end pb-1">
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
          </div>
        </form>
      </div>
    <?php } ?>
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

    // Load data saat pertama kali halaman dibuka (tanpa redirect)
    var pelanggan = $("select[name=pelanggan]").val();
    if (pelanggan && pelanggan.length != 0) {
      loadDataOnly(pelanggan);
    }
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
  function loadDataOnly(id) {
    $('.hrfsp').attr('href', '<?= URL::BASE_URL ?>Member/tambah_paket/' + id);
    $('.hrfsd').attr('href', '<?= URL::BASE_URL ?>SaldoTunai/tambah/' + id);
    $("div#load").load("<?= URL::BASE_URL ?>Operasi/loadData/" + id + "/" + <?= $data['mode'] ?>);
  }

  function load_data_operasi(id) {
    // Redirect ke URL baru dengan full page reload
    window.location.href = '<?= URL::BASE_URL ?>Operasi/i/<?= $data['mode'] ?>/' + id;
  }

  function cekData() {
    var pelanggan = $("select[name=pelanggan]").val();

    if (pelanggan.length == 0) {
      return;
    } else {
      load_data_operasi(pelanggan);
    }
  }
</script>

<!-- Floating Action Buttons -->
<div class="position-fixed bottom-0 end-0 p-4 d-flex gap-2" style="z-index: 1050">
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
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasBukaOrderOp" aria-labelledby="offcanvasBukaOrderOpLabel" data-bs-backdrop="false" data-bs-scroll="true">
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
  // Manual Trigger for Buka Order Offcanvas
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
                      // Move modals inside loaded content to body
                      setTimeout(function() {
                          $('#bukaOrderContentOp .modal').appendTo("body");
                      }, 500);
                  }
              });
          }
      });
  }

  // Manual Trigger for Payment Offcanvas
  $(document).on('click', '#btnTriggerPayment', function() {
      var offcanvasPaymentEl = document.getElementById('offcanvasPayment');
      if (offcanvasPaymentEl) {
          var paymentOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasPaymentEl);
          paymentOffcanvas.toggle();
      }
  });
</script>