<?php
$mode = $data['mode'];
$day = $data['day'];
?>
<div class="row mx-0 mb-2 mt-2">
  <div class="col"><a href="<?= URL::BASE_URL ?>Riwayat/index/<?= $mode ?>/0"><span class="btn-sm btn btn-<?= $day != '0' ? 'outline-' : '' ?>info w-100">Hari ini</span></a></div>
  <div class="col"><a href="<?= URL::BASE_URL ?>Riwayat/index/<?= $mode ?>/1"><span class="btn-sm btn btn-<?= $day != '1' ? 'outline-' : '' ?>info w-100">Kemarin</span></a></div>
</div>
<div class="row mx-0 mb-2 mt-2">
  <div class="col"><a href="<?= URL::BASE_URL ?>Riwayat/index/=/<?= $day ?>"><span class="btn-sm btn btn-<?= $mode != '=' ? 'outline-' : '' ?>dark w-100">Saya</span></a></div>
  <div class="col"><a href="<?= URL::BASE_URL ?>Riwayat/index/<>/<?= $day ?>"><span class="btn-sm btn btn-<?= $mode != '<>' ? 'outline-' : '' ?>dark w-100">Tim</span></a></div>
</div>

<div style="height: 550px; overflow-y:scroll">
  <?php
  foreach ($data['ref'] as $key => $r) { ?>
    <div data-ref="<?= $key ?>" class="row mx-0 border-bottom py-1 cekPesanan" style="cursor: pointer;" aria-controls="offcanvasRight">
      <div class="col">
        #<?= $r['id'] ?><br>
        <b class="text-purple"><?= strtoupper($data['pelanggan'][$r['pelanggan']]['nama']) ?></b><br>
        <span id="<?= $r['id'] ?>">
          <?php
          $vhv = $r['v'];
          switch ($vhv) {
            case 1:
              echo "<span class='badge bg-gradient bg-success'>Car</span>";
              break;
            case 2:
              echo "<span class='badge bg-gradient bg-primary'>Bike</span>";
              break;
            default:
              break;
          }
          ?>
        </span>
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
            echo "<span class='badge bg-gradient bg-warning'>Pengecekan</span>";
            break;
          default:
            echo "<span class='badge bg-gradient bg-dark'>???</span>";
            break;
        }
        ?>
      </div>
    </div>
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