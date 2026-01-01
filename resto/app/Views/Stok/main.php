<div x-data="data">
  <table class="table">
    <thead>
      <tr>
        <th>Date</th>
        <th class="text-end">Awal</th>
        <th class="text-end">Jual</th>
        <th class="text-end">S (O)</th>
        <th class="text-end">S (M)</th>
        <th class="text-end">Selisih</th>
      </tr>
    </thead>
    <?php foreach ($data['tgl'] as $c) {
      $day = date('d/M', strtotime($c)) ?>
      <tr>
        <td><?= $day ?></td>
        <td style="cursor: pointer;" x-on:click="cek(<?= $c ?>,'a')" x-bind:class="c[<?= $c ?>].a > 0 ? 'text-primary' : 'text-dark'" class="text-end" x-text="c[<?= $c ?>].a"></td>
        <td x-bind:class="c[<?= $c ?>].a > 0 ? 'text-success' : 'text-dark'" class="text-end" x-text="c[<?= $c ?>].t"></td>
        <td style="cursor: pointer;" class="text-end" x-on:click="cek(<?= $c ?>,'sa')" x-text="c[<?= $c ?>].a - c[<?= $c ?>].t"></td>
        <td style="cursor: pointer;" x-on:click="cek(<?= $c ?>,'s')" x-bind:class="c[<?= $c ?>].s > 0 ? 'text-danger' : 'text-dark'" class="text-end" x-text="c[<?= $c ?>].s"></td>
        <td class="text-end" x-bind:class="(c[<?= $c ?>].a - c[<?= $c ?>].s) != 0 ? 'text-success' : 'text-dark'" x-text="c[<?= $c ?>].s - (c[<?= $c ?>].a - c[<?= $c ?>].t)"></td>
      </tr>
    <?php } ?>
  </table>
  <div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="canvas1">
    <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
      <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
        <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
      </div>
    </div>
    <div class="offcanvas-body pt-0">
      <div class="px-1 pt-2" id="load1"></div>
    </div>
    <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
      <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
        <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
      </div>
    </div>
  </div>
</div>

<?php $jsonData = json_encode($data['qty']) ?>

<script src="<?= URL::ASSETS_URL ?>mine/luhur.js"></script>
<script src="<?= URL::ASSETS_URL ?>js/alpine.min.js" defer></script>
<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('data', () => ({
      c: <?= $jsonData ?>,
      last_mode: "c",

      cek(c, mode) {
        buka_canvas("canvas1");

        if (this.last_mode != (c + mode)) {
          this.last_mode = c + mode;

          $("div#load1").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
            $("div#load1").load('<?= URL::BASE_URL ?>Stok/cek/' + c + '/' + mode);
          })
        }
      },

      simpan(tgl, mode) {
        var inputData = {};
        $('input.data').each(function() {
          inputData[$(this).attr("name")] = $(this).val();
        })

        $.ajax({
          url: "<?= URL::BASE_URL ?>Stok/update/" + mode,
          data: {
            tgl: tgl,
            data: inputData
          },

          type: "POST",
          success: function(res) {
            if (is_numeric(res)) {
              this.c[tgl][mode] = res;
              tutup_canvas("canvas1");
            } else {
              console.log(res);
            }
          }.bind(this),
        });
      }
    }))
  })
</script>