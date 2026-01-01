<?php
$total = 0;
foreach ($data['order'] as $dk) {
  $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
  $total += $subTotal;
}

foreach ($data['bayar'] as $b) {
  $total -= $b['jumlah'];
}

?>

<div class="w-100 mt-5">
  <div class="text-center">Total</div>
  <div class="text-center fs-5 fw-bold"><?= number_format($total) ?></div>
</div>
<div class="w-100 mt-3">
  <div class="text-center">Pilih Pelanggan</div>
  <div class="text-center">
    <select name="pelanggan" class="form-control" required>
      <option selected value="0"></option>
      <?php foreach ($data['pelanggan'] as $p) { ?>
        <option value="<?= $p['id'] ?>"><?= $p['nama'] ?></option>
      <?php } ?>
    </select>
  </div>
</div>

<div class="w-100 mt-5">
  <div class="text-center fs-5 fw-bold">
    <span class="btn btn-danger w-100 rounded-0 bg-gradient" onclick="piutangOK()">Jadikan Piutang</span>
  </div>
</div>

<script>
  function piutangOK() {
    let pelanggan = $('select[name=pelanggan]').val();

    if (pelanggan > 0) {
      $.ajax({
        url: "<?= URL::BASE_URL ?>Penjualan/piutang",
        data: {
          ref: '<?= $data['ref'] ?>',
          pelanggan: pelanggan,
        },
        type: "POST",
        success: function(res) {
          if (res == 0) {
            $('.offcanvas.show').each(function() {
              $(this).offcanvas('hide');
            });
            $('button.pilih[data-group=nomor][data-id=' + nomor + '][data-mode=' + mode_dt + ']').removeClass('border-2 border-dark');
            load_pesanan(mode_dt, nomor);
          } else {
            console.log(res);
          }
        },
      });
    } else {
      console.log("Kurang Bayar");
    }
  }
</script>