<?php
$day = date('D, d M y', strtotime($data['tgl'])); ?>
<label>
  <h5 class="text-<?= $data['mode'] == 'a' ? 'success' : 'danger' ?>"><?= $data['mode'] == 'a' ? 'Stok Awal' : 'Sisa Stok' ?></h5>
  <?= $day ?>
</label>
<?php
foreach ($data['menu'] as $id => $dk) { ?>
  <div class="d-flex flex-row border-bottom justify-content-between">
    <div class="py-1">
      <span class=""><?= $dk['nama'] ?></span><br>
    </div>
    <div class="py-1 align-self-center text-nowrap">
      <input <?= $data['tgl'] <> date("Ymd") ||  $data['mode'] == 'sa' ? "disabled" : "" ?> name="<?= $dk['id'] ?>" style="width: 70px;" value="<?= $data['data'][$id][$data['mode']] ?>" class="border-0 text-end fw-bold border-bottom-1 data" type="number">
    </div>
  </div>
<?php } ?>

<?php if ($data['tgl'] == date("Ymd") && $data['mode'] <> 'sa') { ?>
  <span class="btn btn-success w-100 mt-4" x-on:click="simpan('<?= $data['tgl'] ?>','<?= $data['mode'] ?>')">Simpan</span>
<?php } ?>

<script>
  var val_b;
  $("input.data").focusin(function() {
    val_b = $(this).val();
    $(this).val("");
  })

  $("input.data").focusout(function() {
    const val = $(this).val();
    if (val == "") {
      $(this).val(val_b);
    }
  })
</script>