<?php
foreach ($data['bayar'] as $key => $r) { ?>
  <div class="row cek mx-0 border-bottom py-1" style="cursor: pointer;" data-ref="<?= $r['ref_bayar'] ?>">
    <div class="col">
      <span class="text-purple">#<?= $r['ref_bayar'] ?></span>
    </div>
    <div class="text-end col" style="cursor: pointer;" data-ref="<?= $key ?>">
      <span class="fw-bold">Rp<?= number_format($r['jumlah']) ?></span>
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

<script>
  $(".cek").click(function() {
    buka_canvas('offcanvasRight');
    var ref = $(this).attr('data-ref');
    $("div#cart").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
      $("div#cart").load('<?= URL::BASE_URL ?>Riwayat_Bayar/cart_riwayat_bayar/' + ref);
    });
  })
</script>