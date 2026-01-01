<?php
$no_meja = 13;
$bawa_pulang = 5;
?>


<label class="px-1 text-purple">Dine-In / Take-Away</label><br>
<div class="row mx-0" style="max-width: <?= URL::MAX_WIDTH ?>px;">
  <?php for ($i = 1; $i <= $no_meja; $i++) { ?>
    <div class="col-auto py-1 px-1">
      <button style="width: 65px;" class="btn btn-outline-success pilih <?= isset($data['order_0'][$i]) ? "border-2 border-dark" : "" ?>" data-group="nomor" data-mode="0" data-id="<?= $i ?>">
        D-<b><?= $i ?></b>
      </button>
    </div>
  <?php } ?>
  <?php for ($i = 1; $i <= $bawa_pulang; $i++) { ?>
    <div class="col-auto py-1 px-1">
      <button style="width: 65px;" class="btn btn-outline-primary pilih <?= isset($data['order_1'][$i]) ? "border-2 border-dark" : "" ?>" data-group="nomor" data-mode="1" data-id="<?= $i ?>">
        T-<b><?= $i ?></b>
      </button>
    </div>
  <?php } ?>
</div>

<div class="row mx-0 mt-2">
  <div class="col px-1">
    <div class="px-0" id="cart_load" style="height: 10px;"></div>
    <div id="cart"></div>
  </div>
</div>

<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel" style="transition: 0.3s;">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
    <div class="row px-3 mx-0">
      <div class="col py-1 px-1">
        <button class="btn btn-outline-success text-nowrap w-100 pilih active" data-group="kategori" data-id="0">
          Semua
        </button>
      </div>
      <?php foreach ($data['kat'] as $dk) { ?>
        <div class="col py-1 px-1">
          <button class="btn btn-outline-dark text-nowrap w-100 pilih" data-group="kategori" data-id="<?= $dk['id'] ?>">
            <?= $dk['nama'] ?>
          </button>
        </div>
      <?php } ?>
    </div>
    <div class="row mx-0 mb-2 px-3">
      <div class="col px-1 mt-2 menu_edit_load" style="height: 5px;"></div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="px-1" id="menu"></div>
  </div>
  <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
    <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
      <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
</div>


<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="offcanvasRight1" aria-labelledby="offcanvasRightLabel">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
    <div class="row mx-0 mb-2 px-3">
      <div class="col px-1 mt-2 menu_edit_load" style="height: 5px;"></div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="px-1" id="menu_edit"></div>
  </div>
  <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
    <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
      <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
</div>

<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="offcanvasRight2" aria-labelledby="offcanvasRightLabel">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="px-1" id="bayar"></div>
  </div>
  <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
    <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
      <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
</div>

<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="offcanvasRight3" aria-labelledby="offcanvasRightLabel">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="px-1" id="piutang"></div>
  </div>
  <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
    <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
      <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
</div>

<script>
  var nomor;
  var mode_dt;
  var kat = 0;

  $(".pilih").click(function() {
    var grup = $(this).attr('data-group');

    if (grup == "nomor") {
      $('.offcanvas.show').each(function() {
        $(this).offcanvas('hide');
      });

      nomor = $(this).attr('data-id');
      mode_dt = $(this).attr('data-mode');

      $("button[data-group=" + grup + "]").removeClass('active');
      $(this).addClass("active");

      $("button[data-group=kategori]").removeClass('active');
      $("button[data-group=kategori][data-id=0]").addClass("active");

      load_pesanan(mode_dt, nomor);
      $("div#menu").load('<?= URL::BASE_URL ?>Penjualan/menu/0/' + mode_dt + "/" + nomor);
    }

    if (grup == "kategori") {
      $("button[data-group=" + grup + "]").removeClass('active');
      $(this).addClass("active");

      kat = $(this).attr('data-id');

      $("div.menu_edit_load").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
        $("div#menu").load('<?= URL::BASE_URL ?>Penjualan/menu/' + kat + '/' + mode_dt + "/" + nomor, function() {
          $("div.menu_edit_load").html('');
        });
      });
    }
  })

  function tambahMenuManual(id, qty, id_kat) {
    $("div.menu_edit_load").load('<?= URL::BASE_URL ?>Load/spinner/2');
    $.ajax({
      url: "<?= URL::BASE_URL ?>Penjualan/add_manual/" + mode_dt + "/" + nomor,
      data: {
        qty: qty,
        id: id,
        id_kat: id_kat
      },
      type: "POST",
      success: function(res) {
        if (res == 1) {
          load_pesanan(mode_dt, nomor);
          $('button.pilih[data-group=nomor][data-id=' + nomor + '][data-mode=' + mode_dt + ']').removeClass('border-2 border-dark');
        } else if (res == 0) {
          load_pesanan(mode_dt, nomor);
          $('button.pilih[data-group=nomor][data-id=' + nomor + '][data-mode=' + mode_dt + ']').addClass('border-2 border-dark');
        } else {
          console.log(res);
        }
      },
    });
  }

  function load_pesanan(mode_dt, nomor) {
    $("div#cart_load").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
      $("div#cart").load('<?= URL::BASE_URL ?>Penjualan/cart/' + mode_dt + "/" + nomor, function() {
        $("div#cart_load").html('');
      });
    });

    $("div#menu_edit").load('<?= URL::BASE_URL ?>Penjualan/ubah/' + mode_dt + "/" + nomor, function() {
      $("div.menu_edit_load").html('');
    });

  }

  function load_bayar(ref) {
    $("div#bayar").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
      $("div#bayar").load('<?= URL::BASE_URL ?>Penjualan/cek_bayar/' + ref);
    });
  }

  function load_piutang(ref) {
    $("div#piutang").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
      $("div#piutang").load('<?= URL::BASE_URL ?>Penjualan/cek_piutang/' + ref);
    });
  }

  function setDiskon(id, diskon) {
    $.ajax({
      url: "<?= URL::BASE_URL ?>Penjualan/set_diskon",
      data: {
        id: id,
        diskon: diskon,
      },
      type: "POST",
      success: function(res) {
        if (res == 0) {
          load_pesanan(mode_dt, nomor);
        } else {
          console.log(res);
        }
      },
    });
  }

  function setHarga(id, harga) {
    $.ajax({
      url: "<?= URL::BASE_URL ?>Penjualan/set_harga",
      data: {
        id: id,
        harga: harga,
      },
      type: "POST",
      success: function(res) {
        if (res == 0) {
          load_pesanan(mode_dt, nomor);
        } else {
          console.log(res);
        }
      },
    });
  }
</script>