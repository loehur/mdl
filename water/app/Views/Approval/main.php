<div x-data="data">
  <?php foreach ($data['mode'] as $key => $c) {
    $jsonData[$key] = $data[$c]; ?>
    <div class="d-flex align-items-center border btn mb-2" x-on:click="cek(<?= $key ?>)">
      <div class="p-1 align-self-center" style="width: 40px;"><span :class="c[<?= $key ?>] > 0 ? 'bg-danger' : 'bg-success'" class="badge bg-gradient" x-text="c[<?= $key ?>]"></span></div>
      <div class="p-1 text-start rounded"><?= ucwords($c) ?></div>
    </div>

    <div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="canvas<?= $key ?>">
      <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
        <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
          <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
        </div>
      </div>
      <div class="offcanvas-body pt-0">
        <div class="px-1 pt-2" id="load<?= $key ?>"></div>
      </div>
      <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
        <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
          <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
        </div>
      </div>
    </div>
  <?php } ?>
</div>
<?php $jsonData = json_encode($jsonData) ?>

<script src="<?= URL::ASSETS_URL ?>mine/luhur.js"></script>
<script src="<?= URL::ASSETS_URL ?>js/alpine.min.js" defer></script>
<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('data', () => ({
      c: <?= $jsonData ?>,

      verify(id, v, key) {
        $.ajax({
          url: "<?= URL::BASE_URL ?>Approval/verify",
          data: {
            id: id,
            v: v,
            key: key
          },
          type: "POST",
          success: function(res) {
            if (is_numeric(res)) {
              $("#tr" + id).fadeOut('fast');
              this.c[key] = res;
            } else {
              console.log(res);
            }
          }.bind(this),
        });
      },

      cek(key) {
        buka_canvas("canvas" + key);
        $("div#load" + key).load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
          $("div#load" + key).load('<?= URL::BASE_URL ?>Approval/cek/' + key);
        });
      },
    }))
  })
</script>