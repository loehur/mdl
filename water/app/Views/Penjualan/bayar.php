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

<div x-data="data">
  <div class="w-100 mt-2">
    <div class="text-center fs-3 fw-bold">Rp<?= number_format($total) ?></div>
  </div>
  <div class="w-100 mt-3">
    <div class="d-flex justify-content-center">
      <div class="px-1"><span x-on:click="total_bayar = <?= $total ?>" onclick="cash()" class="btn btn-outline-primary">Pas</span></div>
      <div class="px-1"><span x-on:click="total_bayar = 20000" onclick="cash()" class="pilihBayar btn btn-outline-primary">20.000</span></div>
      <div class="px-1"><span x-on:click="total_bayar = 50000" onclick="cash()" class="pilihBayar btn btn-outline-primary">50.000</span></div>
      <div class="px-1"><span x-on:click="total_bayar = 100000" onclick="cash()" class="pilihBayar btn btn-outline-primary">100.000</span></div>
    </div>
  </div>
  <div class="w-100 mt-3">
    <div class="text-center">Input Jumlah Bayar</div>
    <div class="text-center"><input x-bind:value="total_bayar" x-model="total_bayar" class="border-top-0 border-start-0 border-end-0 border-bottom fs-2 text-success w-100 text-center" type="number"></div>
  </div>
  <div class="w-100 mt-3">
    <div class="d-flex justify-content-center">
      <div class="px-3 border-end">
        <div class="text-end">Dibayar</div>
        <div class="text-end fs-5 fw-bold" x-text="number_format(total_bayar)"></div>
      </div>
      <div class="px-3">
        <div class="text-end">Kembalian</div>
        <div class="text-end fs-5 fw-bold text-danger" x-text="total_bayar - bill > 0 ? number_format(total_bayar - bill) : 0"></div>
      </div>
    </div>
  </div>

  <div class="w-100 mt-3 row mx-0 row-cols-2 px-3">
    <?php foreach (URL::METOD_BAYAR as $key => $value) { ?>
      <div class="form-check col">
        <input class="form-check-input" type="radio" value="<?= $key ?>" x-model="metodePilih" name="metode" id="option<?= $key ?>">
        <label class="form-check-label" for="option<?= $key ?>">
          <?= strtoupper($value) ?>
        </label>
      </div>
    <?php } ?>
  </div>

  <div class="w-100 mt-3">
    <div class="text-center">Catatan Bayar</div>
    <div class="text-center"><input name="catatan" x-bind:class="metodePilih == 1 ? '' : 'border-danger'" x-bind:required="metodePilih == 1 ? 0 : metodePilih" class="border-top-0 border-start-0 border-end-0 border-bottom fs-5 text-danger w-100 text-center" type="text"></div>
  </div>

  <div class="w-100 mt-4">
    <div class="text-center fs-5 fw-bold">
      <span class="btn btn-success w-100 bg-gradient rounded-0" x-on:click="bayarOK()">Bayar</span>
    </div>
  </div>
</div>

<script src="<?= URL::ASSETS_URL ?>js/alpine.min.js" defer></script>

<script>
  function cash() {
    $('input#option1').click();
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('data', () => ({
      metodePilih: 1,
      bill: parseInt(<?= $total ?>),
      total_bayar: parseInt(<?= $total ?>),
      kembalian: 0,

      bayarOK() {
        let metode = $('input[name="metode"]:checked').val();
        let note = $('input[name="catatan"]').val();

        if (this.total_bayar <= 0) {
          alert('Jumlah bayar harus lebih besar dari 0');
          return;
        }

        if ((this.metodePilih == 4 || this.metodePilih == 5) && $('input[name="catatan"]').val() == '') {
          alert('Catatan harus diisi');
          return;
        }

        $.ajax({
          url: "<?= URL::BASE_URL ?>Penjualan/bayar",
          data: {
            ref: '<?= $data['ref'] ?>',
            dibayar: this.total_bayar,
            metode: metode,
            note: note
          },
          type: "POST",
          success: function(res) {
            if (res == 0) {
              $('.offcanvas.show').each(function() {
                $(this).offcanvas('hide');
              });
              $('button.pilih[data-group=nomor][data-id=' + nomor + ']').removeClass('border-2 border-dark');
              load_pesanan(nomor);
            } else if (res == 1) {
              $('.offcanvas.show').each(function() {
                $(this).offcanvas('hide');
              });
              load_pesanan(nomor);
            } else {
              console.log(res);
            }
          },
        });
      },

      number_format(number, decimals, dec_point, thousands_sep) {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
          prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
          sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
          dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
          s = '',
          toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
          };
        // Fix for IE parseFloat(0.55).toFixed(0) = 0;
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
          s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
          s[1] = s[1] || '';
          s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
      },
    }))
  })
</script>