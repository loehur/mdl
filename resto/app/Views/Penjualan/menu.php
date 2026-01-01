<?php foreach ($data['menu'] as $dk) { ?>
  <div class="d-flex flex-row border-bottom justify-content-between">
    <div class="py-1">
      <span class="text-success fw-bold"><?= $dk['nama'] ?></span><br>
      Rp<?= number_format($dk['harga']) ?>
    </div>
    <div class="py-1 align-self-center">
      <button data-id="<?= $dk['id'] ?>" data-kat="<?= $dk['id_kategori'] ?>" data-add="-1" class="btn btn-outline-danger fw-bold tambah" style="width: 40px;">-</button>
      <input data-id="<?= $dk['id'] ?>" data-kat="<?= $dk['id_kategori'] ?>" style="width: 35px;" value="<?= isset($data['order'][$dk['id']]) ? $data['order'][$dk['id']]['qty'] : 0 ?>" class="manual_qty qty<?= $dk['id'] ?> border-0 text-center fw-bold border-bottom-1" type="number">
      <button data-id="<?= $dk['id'] ?>" data-kat="<?= $dk['id_kategori'] ?>" data-add="1" class="btn btn-outline-success fw-bold tambah" style="width: 40px;">+</button>
    </div>
  </div>
<?php } ?>

<script>
  var val_before;
  var id;
  var milidetik = 0;
  var add = 0;
  var id_kat, qty;
  var interval;

  $("input.manual_qty").focusin(function() {
    val_before = $(this).val();
  });

  function update_qty() {
    milidetik += 1;
    if (milidetik == 100) {
      clearInterval(interval);
      milidetik = 0;
      tambahMenuManual(id, qty, id_kat);
    }
  }

  $(".tambah").click(function() {
    const id_baru = $(this).attr("data-id");
    if (milidetik == 0) {
      id = 0;
    }

    if (id != 0 && id != id_baru) {
      console.log(id, id, milidetik);
      return;
    }

    id = id_baru;

    add = $(this).attr("data-add");
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


  $("input.manual_qty").focusout(function() {
    qty = $(this).val();
    if (val_before == qty) {
      console.log('Tidak ada perubahan qty');
      return;
    }
    id = $(this).attr("data-id");
    id_kat = $(this).attr("data-kat");
    tambahMenuManual(id, qty, id_kat);
  });
</script>