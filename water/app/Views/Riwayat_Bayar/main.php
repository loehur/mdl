<?php $day = $data['day']; ?>
<div class="row mx-0 mb-2 mt-2">
  <div class="col"><a href="<?= URL::BASE_URL ?>Riwayat_Bayar/index/0"><span class="btn-sm btn btn-<?= $day != '0' ? 'outline-' : '' ?>info w-100">Hari ini</span></a></div>
  <div class="col"><a href="<?= URL::BASE_URL ?>Riwayat_Bayar/index/1"><span class="btn-sm btn btn-<?= $day != '1' ? 'outline-' : '' ?>info w-100">Kemarin</span></a></div>
</div>

<div class="row mx-0 mb-2 mt-3">
  <div class="col text-center">
    <?= "Total" ?>
    <h5 class="fw-bold">Rp<?= number_format($data['total'] - $data['keluar']) ?></h5>
  </div>
</div>

<div style="height: 550px; overflow-y:scroll">
  <?php
  $total = 0;
  foreach ($data['bayar'] as $key => $r) {
    $jenis = $r['jenis_transaksi'] ?>

    <?php if ($jenis == 1) { ?>
      <div data-ref="<?= $r['ref'] ?>" class="row mx-0 border-bottom py-1 cekPesanan" style="cursor: pointer;" aria-controls="offcanvasRight">
        <div class="col">
          <?= strtoupper($data['pelanggan'][$r['id_client']]['nama']) ?> <small><span class="text-secondary"><?= $r['note_primary'] ?> <?= $r['note'] ?></span></small>
        </div>
        <div class="col-auto text-end">
          <?= number_format($r['jumlah']) ?>
        </div>
      </div>
    <?php } else { ?>
      <div class="row mx-0 border-bottom py-1">
        <div class="col">
          <span class="badge bg-danger"><?= $r['note_primary'] ?></span> <?= $r['note'] ?>
        </div>
        <div class="col-auto text-end text-danger">
          -<?= number_format($r['jumlah']) ?>
        </div>
      </div>
    <?php } ?>
  <?php } ?>
</div>

<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="px-1" id="cart"></div>
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
      $("div#cart").load('<?= URL::BASE_URL ?>Riwayat/cart/' + ref);
    });
  })
</script>