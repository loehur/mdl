<?php
foreach ($data['ref'] as $key => $r) { ?>
  <div data-ref="<?= $key ?>" class="row mx-0 border-bottom py-1 cekPesanan" style="cursor: pointer;" aria-controls="offcanvasRight">
    <div class="col">
      #<?= $r['id'] ?><br>
      <b class="text-purple"><?= strtoupper($data['pelanggan'][$r['pelanggan']]['nama']) ?></b><br>
    </div>
    <div class="col text-end">
      <?= date('d M y, H:i', strtotime($r['tgl'] . " " . $r['jam'] . ":00")) ?><br>
      <span class="fw-bold">Rp<?= number_format($data['total'][$key]) ?></span><br>
      <?php
      switch ($r['step']) {
        case 1:
          echo "<span class='badge bg-gradient bg-success'>Lunas</span>";
          break;
        case 2:
          echo "<span class='badge bg-gradient bg-secondary'>Batal</span>";
          break;
        case 3:
          echo "<span class='badge bg-gradient bg-danger'>Piutang</span>";
          break;
        case 4:
          echo "<span class='badge bg-gradient bg-warning'>Non Tunai</span>";
          break;
        default:
          echo "<span class='badge bg-gradient bg-dark'>???</span>";
          break;
      }
      ?>
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
      $("div#cart").load('<?= URL::BASE_URL ?>Riwayat_A/cart/' + ref);
    });
  })
</script>