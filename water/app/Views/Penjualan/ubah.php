<?php
foreach ($data['order'] as $dk) { ?>
  <div class="d-flex flex-row border-bottom justify-content-between">
    <div class="py-1">
      <span class="fw-bold"><?= $data['menu'][$dk['id_menu']]['nama'] ?></span><br>
      <span class="text-success">Diskon:</span> <input style="width: 60px;" data-val="<?= $dk['diskon'] ?>" data-max="<?= $dk['harga'] * $dk['qty'] ?>" class="border-0 text-success border-bottom-1 diskon" data-id="<?= $dk['id'] ?>" value="<?= $dk['diskon'] ?>" type="number">
    </div>
    <div class="py-1 align-self-center text-nowrap">
      <button data-id="<?= $dk['id_menu'] ?>" data-kat="<?= $data['menu'][$dk['id_menu']]['id_kategori'] ?>" data-add="-1" class="btn btn-outline-danger fw-bold tambah_ubah" style="width: 40px;">-</button>
      <input data-id="<?= $dk['id_menu'] ?>" data-kat="<?= $data['menu'][$dk['id_menu']]['id_kategori'] ?>" style="width: 35px;" value="<?= $dk['qty'] ?>" class="manual_qty_ubah qty<?= $dk['id_menu'] ?> border-0 text-center fw-bold border-bottom-1" type="number">
      <button data-id="<?= $dk['id_menu'] ?>" data-kat="<?= $data['menu'][$dk['id_menu']]['id_kategori'] ?>" data-add="1" class="btn btn-outline-success fw-bold tambah_ubah" style="width: 40px;">+</button>
    </div>
  </div>
<?php } ?>

<script>
  var val_before;
  var milidetik = 0;
  var id, id_kat, qty;
  var interval;

  function update_qty() {
    milidetik += 1;
    if (milidetik == 100) {
      clearInterval(interval);
      milidetik = 0;
      tambahMenuManual(id, qty, id_kat);
    }
  }

  $(".tambah_ubah").click(function() {
    const add = $(this).attr("data-add");

    const id_baru = $(this).attr("data-id");
    if (milidetik == 0) {
      id = 0;
    }

    if (id != 0 && id != id_baru) {
      console.log(id, id, milidetik);
      return;
    }

    id = id_baru;

    id_kat = $(this).attr("data-kat");
    qty = $(".qty" + id).val();
    if (qty == 0 && add == -1) {
      return;
    } else {
      $(".qty" + id).val(parseInt(qty) + parseInt(add));
      qty = $(".qty" + id).val();
      if (milidetik == 0) {
        interval = setInterval(update_qty, 1);
      } else {
        milidetik = 0;
      }
    }
  })

  $("input.manual_qty_ubah").focusin(function() {
    val_before = $(this).val();
  });

  $("input.manual_qty_ubah").focusout(function() {
    qty = $(this).val();
    if (val_before == qty) {
      console.log('Tidak ada perubahan qty');
      return;
    }
    id = $(this).attr("data-id");
    id_kat = $(this).attr("data-kat");
    tambahMenuManual(id, qty, id_kat);
  });

  var diskon_before = 0;
  $("input.diskon").focusin(function() {
    diskon_before = $(this).val();
    $(this).val('');
  });

  $("input.diskon").focusout(function() {
    const max = $(this).attr("data-max");
    const id = $(this).attr("data-id");
    const val = $(this).attr("data-val");
    const diskon = $(this).val();
    if (diskon != diskon_before && diskon != '') {
      if (parseInt(diskon) > parseInt(max)) {
        $(this).val(val);
      } else {
        setDiskon(id, diskon)
      }
    } else {
      $(this).val(diskon_before);
    }
  });
</script>