<script src="<?= URL::ASSETS_URL ?>js/alpine.min.js"></script>
<script src="<?= URL::ASSETS_URL ?>mine/luhur.js"></script>

<?php
$total = 0;
?>

<div x-data="dataBill">
  <?php foreach ($data['order'] as $key => $d) {
    $total += $data['total'][$key]; ?>
    <div class="form-check w-100">
      <input class="form-check-input cekbox" name="list_tgl" type="checkbox" x-on:change="cek" data-val="<?= $data['total'][$key] ?>" value="<?= $key ?>" id="flexCheckChecked" checked>
      <label class="form-check-label w-100" for="flexCheckChecked">
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
    <div class="text-center"><input id="inBayar" x-on:keyup="kembalian" class="border-top-0 border-start-0 border-end-0 border-bottom fs-2 text-success w-100 text-center" type="number"></div>
  </div>
  <div class="w-100 mt-3">
    <div class="text-center">Dibayar</div>
    <div class="text-center fs-5 fw-bold" x-text="dibayar"></div>
  </div>
  <div class="w-100 mt-3">
    <div class="d-flex justify-content-center">
      <div class="px-3 border-end">
        <div class="text-end">Kembalian</div>
        <div class="text-end fs-5 fw-bold text-danger" x-text="jumKembali"></div>
      </div>
      <div class="px-3">
        <div class="text-center">Metode Bayar</div>
        <div class="form-check">
          <input class="form-check-input" type="radio" value="1" name="metode" id="flexRadioDefault2" checked>
          <label class="form-check-label" for="flexRadioDefault2">
            CASH
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" value="2" name="metode" id="flexRadioDefault1">
          <label class="form-check-label" for="flexRadioDefault1">
            QRIS
          </label>
        </div>
      </div>
    </div>
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

      cek() {
        this.bill = 0;
        var tol = 0;
        $(".cekbox:checked").each(function() {
          let val = $(this).attr('data-val');
          tol += parseInt(val);
        })
        this.bill = tol;
        this.showBill = number_format(this.bill);
      },

      kembalian() {
        var jumBayar = parseInt($("#inBayar").val());
        this.jumBayar = jumBayar;

        var jumKembali = jumBayar - this.bill;
        if (jumKembali < 0) {
          this.jumKembali = 0;
        } else {
          this.jumKembali = number_format(jumKembali);
        }
        this.dibayar = number_format(jumBayar);
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
        let list_tgl = checkboxArray('list_tgl');

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
              alert(res);
            }
          },
        });
      }
    }))
  })
</script>