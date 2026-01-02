<script src="<?= URL::ASSETS_URL ?>js/alpine.min.js"></script>

<?php
$total = 0;
$data_tgl = [];
?>

<div class="mb-3 mt-2 border-bottom">
  Pak/Bu. <span class="fw-bold"><?= strtoupper($data['nama_pelanggan']) ?></span>
</div>

<div x-data="dataBill" class="px-1">
  <?php foreach ($data['order'] as $key => $d) {
    array_push($data_tgl, $key);
    $total += $data['total'][$key]; ?>
    <div class="form-check w-100">
      <input class="form-check-input cekbox" type="checkbox" x-model="cekbokArray" x-on:change="cek" data-val="<?= $data['total'][$key] ?>" value="<?= $key ?>" id="flexCheckChecked<?= $key ?>">
      <label class="form-check-label w-100" for="flexCheckChecked<?= $key ?>">
        <div class="d-flex justify-content-between">
          <div><?= date('d M y', strtotime($key . " 00:00:00")) ?></div>
          <div>Rp<?= number_format($data['total'][$key]) ?></span></div>
        </div>
      </label>
    </div>
  <?php } ?>
  <div class="d-flex justify-content-between border-top py-1 mt-2">
    <div></div>
    <div class="fw-bold">
      Rp<span x-text="showBill"></span>
    </div>
  </div>
  <div class="w-100 mt-4">
    <div class="text-center">Total</div>
    <div class="text-center"><span class="fs-5 fw-bold" x-text="showBill"></span></div>
  </div>
  <div class="w-100 mt-3">
    <div class="text-center">Input Jumlah Bayar</div>
    <div class="text-center"><input id="inBayar" x-model="total_bayar" x-on:keyup="kembalian" class="border-top-0 border-start-0 border-end-0 border-bottom fs-2 text-success w-100 text-center" type="number"></div>
  </div>
  <div class="w-100 mt-3">
    <div class="d-flex justify-content-center">
      <div class="px-3 border-end">
        <div class="text-end">Dibayar</div>
        <div class="text-end fs-5 fw-bold" x-text="dibayar"></div>
      </div>
      <div class="px-3">
        <div class="text-end">Kembalian</div>
        <div class="text-end fs-5 fw-bold text-danger" x-text="jumKembali"></div>
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

  <div class="w-100 mt-4">
    <div class="text-center fs-5 fw-bold">
      <span class="btn btn-success w-100 bg-gradient rounded-0" x-on:click="bayarOK">Bayar</span>
    </div>
  </div>
</div>

<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('dataBill', () => ({
      bill: parseInt(<?= $total ?>),
      showBill: '<?= number_format($total) ?>',
      jumKembali: '0',
      dibayar: '0',
      jumBayar: 0,
      cekbokArray: <?= json_encode($data_tgl) ?>,
      metodePilih: 1,
      total_bayar: '',

      cek() {
        this.bill = 0;
        var tol = 0;
        $(".cekbox:checked").each(function() {
          let val = $(this).attr('data-val');
          tol += parseInt(val);
        })
        this.bill = tol;
        this.showBill = this.number_format(this.bill);
      },

      kembalian() {
        var jumBayar = this.total_bayar;
        this.jumBayar = jumBayar;

        var jumKembali = jumBayar - this.bill;
        if (jumKembali < 0) {
          this.jumKembali = 0;
        } else {
          this.jumKembali = this.number_format(jumKembali);
        }
        this.dibayar = this.number_format(jumBayar);
      },

      bayarOK() {
        if (this.jumBayar == 0 || this.bill == 0) {
          console.log("bayar/bill 0 diabaikan");
          return;
        }
        let jumBayar = this.jumBayar;
        if (this.jumBayar > this.bill) {
          jumBayar = this.bill;
        }

        let metode = $('input[name="metode"]:checked').val();
        let list_tgl = this.cekbokArray;;

        $.ajax({
          url: "<?= URL::BASE_URL ?>Piutang/bayar/" + <?= $data['pelanggan'] ?>,
          data: {
            metode: metode,
            list_tgl: list_tgl,
            jumBayar: jumBayar,
          },
          type: "POST",
          success: function(res) {
            if (res == 0) {
              location.reload(true);
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