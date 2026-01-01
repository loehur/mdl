<?php
foreach ($data['data'] as $key => $r) { ?>
  <div class="row mx-0 border-bottom py-1">
    <div class="col">
      <div class="cekPesanan" style="cursor: pointer;" data-ref="<?= $key ?>">
        Cek <i class="fas fa-ellipsis-v"></i> <span class="text-purple fw-bold"><?= $data['pelanggan'][$key]['nama'] ?></span>
      </div>
      <div class="text-end bayarPiutang" style="cursor: pointer;" data-ref="<?= $key ?>">
        <span class="fw-bold">Rp<?= number_format($r) ?></span> <i class="fas fa-ellipsis-v"></i> Bayar
      </div>
    </div>
  </div>
<?php } ?>

<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="px-1 pt-2" id="cart"></div>
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
    <div class="px-1 pt-2" id="bayarPiutang"></div>
  </div>
  <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
    <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
      <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
</div>

<script>
  $(".cekPesanan").click(function() {
    buka_canvas('offcanvasRight');
    var ref = $(this).attr('data-ref');
    $("div#cart").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
      $("div#cart").load('<?= URL::BASE_URL ?>Piutang/cart/' + ref);
    });
  })

  $(".bayarPiutang").click(function() {
    buka_canvas('offcanvasRight2');
    var ref = $(this).attr('data-ref');
    $("div#bayarPiutang").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
      $("div#bayarPiutang").load('<?= URL::BASE_URL ?>Piutang/cek_bayar/' + ref);
    });
  })
</script>