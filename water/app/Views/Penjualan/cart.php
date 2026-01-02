<link rel="stylesheet" href="<?= URL::ASSETS_URL ?>css/selectize.bootstrap3.min.css" rel="stylesheet" />


<?php if (isset($data['ref']['id'])) {
  $id_pelanggan = $data['ref']['pelanggan'];
  $id = $data['ref']['id'];
} else {
  $id = 0;
  $id_pelanggan = 0;
} ?>

<table class="table table-sm mx-0 mt-2">
  <thead style="cursor: pointer;" id="pesan">
    <tr>
      <th class="text-purple border-top-0">Pesanan (+)</th>
      <th class="text-end border-top-0"></th>
    </tr>
  </thead>

  <tbody id="ubah_pesanan" style="cursor: pointer;">
    <?php
    $total = 0;
    $qty = 0;
    foreach ($data['order'] as $key => $d) { ?>
      <?php
      $total_awal = ($d['harga'] * $d['qty']);
      $qty += $d['qty'];
      $subTotal = ($d['harga'] * $d['qty']) - $d['diskon'];
      $total += $subTotal;
      ?>
      <tr>
        <td>
          <span class="fw-bold"><?= $data['menu'][$key]['nama'] ?></span><br>
          <?= $d['qty'] ?>x @<?= number_format($d['harga']) ?> <?= number_format($total_awal) ?>
        </td>
        <td class="text-end">
          <?php if ($d['diskon'] > 0) { ?>
            <small class="text-success">Disc. <?= number_format($d['diskon']) ?></small><br>
          <?php } ?>
          <?= number_format($subTotal) ?>
        </td>
      </tr>
    <?php } ?>
  </tbody>
  <tr class="table-borderless">
    <th class="text-end">
      TOTAL
    </th>
    <th class="text-end"><?= number_format($total) ?></th>
  </tr>
  <?php
  $dibayar = 0;
  foreach ($data['bayar'] as $b) {
    $dibayar += $b['jumlah'] ?>
    <tr>
      <td class="text-end"><?= URL::METOD_BAYAR[$b['metode_mutasi']] ?></td>
      <td class="text-end">-<?= number_format($b['jumlah'])  ?></td>
    </tr>
  <?php } ?>

  <?php if (count($data['bayar']) > 0) { ?>
    <tr class="table-borderless">
      <th class="text-end">
        SISA
      </th>
      <th class="text-end"><?= number_format($total - $dibayar) ?></th>
    </tr>
  <?php } ?>
</table>

<?php if ($id_pelanggan == 0) { ?>
  <?php if (count($data['order']) > 0) { ?>
    <div class="row mx-0 mt-0">
      <div class="col px-0 mt-auto pb-0">
        <div>Pilih Pelanggan</div>
        <select name="pelanggan" class="tize" required>
          <option selected value="">-</option>
          <?php foreach ($data['pelanggan'] as $p) { ?>
            <option value="<?= $p['id'] ?>"><?= strtoupper($p['nama']) ?> #<?= $p['titip'] ?> | <?= $p['hp'] ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="col-auto pe-0">
        <div class="text-center">Galon Titip</div>
        <div class="mt-1 text-center">
          <input class="form-control text-center" style="width: 80px;" type="number" value="<?= $qty ?>" name="qty_titip">
        </div>
      </div>
    </div>
    <div class="row mx-0 mt-2">
      <div class="col text-end px-0">
        <div class="mt-1"><button class="btn btn-success w-100" onclick="tetapkan()">Tetapkan</button></div>
      </div>
    </div>
  <?php } ?>
<?php } else {
  $nama_pelanggan = $data['pelanggan'][$data['ref']['pelanggan']]['nama'];
?>
  <div class="px-1 w-100">
    <table class="table table-sm">
      <tr>
        <td class=""><span class="">Pelanggan<br></span> <span class="fw-bold text-primary"><?= strtoupper($nama_pelanggan) ?></span></td>
        <td class="text-end">
          <span class="">Galon Titip:</span> <span class="fw-bold text-danger"><?= $data['pelanggan'][$data['ref']['pelanggan']]['titip'] ?></span>
          <?php if (isset($data['piutang'][$data['ref']['pelanggan']])) { ?>
            <br><span class="">Piutang:</span> <span class="fw-bold text-danger">Rp<?= number_format($data['piutang'][$data['ref']['pelanggan']]) ?></span>
          <?php } ?>
        </td>
      </tr>
    </table>
    <?php if (count($data['order_pelanggan']) > 0) { ?>
      <div class="alert alert-light">
        <span class="fw-bold text-danger"><?= strtoupper($nama_pelanggan) ?></span> sudah terinput!
        <br>
        <?php foreach ($data['order_pelanggan'] as $do) { ?>
          <span class="text-purple"><?= $do['qty'] . " " . $data['menu'][$do['id_menu']]['nama'] ?>,&nbsp;</span>
        <?php } ?>
      </div>
    <?php } ?>
  </div>
<?php } ?>

<?php if ($total > 0 && $data['ref']['pelanggan'] <> 0) { ?>
  <div class="d-flex flex-row justify-content-between px-1 mt-3">
    <div class="piutang" onclick="load_piutang('<?= $data['ref']['id'] ?>')"><button class="btn btn-danger">Jadikan Piutang</button></div>
    <div class="bayar" onclick="load_bayar('<?= $data['ref']['id'] ?>')"><button class="btn btn-success">Pembayaran</button></div>
  </div>
<?php } ?>
<div class="pb-5"></div>

<script src="<?= URL::ASSETS_URL ?>js/selectize.min.js"></script>
<script>
  $(document).ready(function() {
    $('select.tize').selectize();
  });

  function tetapkan() {
    var pelanggan = $('select[name="pelanggan"]').val();
    var qty_titip = $('input[name="qty_titip"]').val();
    var nomor = <?= $data['nomor'] ?>;
    var id = '<?= $id ?>';

    if (pelanggan == '') {
      alert('Pilih Pelanggan terlebih dahulu');
      return false;
    }
    $.ajax({
      url: '<?= URL::BASE_URL ?>Penjualan/tetapkan_pelanggan',
      type: 'POST',
      data: {
        id: id,
        pelanggan: pelanggan,
        qty: qty_titip
      },
      success: function(res) {
        if (res == 0) {
          $('button[data-id=' + nomor + ']').click();
        } else {
          alert(res);
        }
      },
      error: function() {
        alert('Terjadi kesalahan saat menghubungi server.');
      }
    });
  }

  $("#pesan").click(function() {
    buka_canvas('offcanvasRight');
  })

  $("#ubah_pesanan").click(function() {
    buka_canvas('offcanvasRight1');
  })


  $(".bayar").click(function() {
    buka_canvas('offcanvasRight2');
  })

  $(".piutang").click(function() {
    buka_canvas('offcanvasRight3');
  })
</script>