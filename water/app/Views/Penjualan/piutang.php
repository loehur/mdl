<link rel="stylesheet" href="<?= URL::ASSETS_URL ?>css/selectize.bootstrap3.min.css" rel="stylesheet" />

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
  <div class="text-center fs-2 fw-bold">Rp<?= number_format($total) ?></div>
</div>
<div class="w-100 mt-5">
  <div class="text-center fs-5 fw-bold">
    <span class="btn btn-danger w-100 rounded-0 bg-gradient" onclick="piutangOK()">Jadikan Piutang</span>
  </div>
</div>

<script src="<?= URL::ASSETS_URL ?>js/selectize.min.js"></script>
<script>
  $(document).ready(function() {
    $('select.tize').selectize();
  });

  function piutangOK() {
    $.ajax({
      url: "<?= URL::BASE_URL ?>Penjualan/piutang",
      data: {
        id: '<?= $data['ref'] ?>',
      },
      type: "POST",
      success: function(res) {
        if (res == 0) {
          $('.offcanvas.show').each(function() {
            $(this).offcanvas('hide');
          });
          $('button.pilih[data-group=nomor][data-id=' + nomor + ']').removeClass('border-2 border-dark');
          load_pesanan(nomor);
        } else {
          console.log(res);
        }
      },
    });
  }
</script>