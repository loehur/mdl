<?php
$idPenjualan = $data[1];
foreach ($this->dPenjualan as $a) {
  if ($a['id_penjualan_jenis'] == $idPenjualan) {
    foreach ($this->dSatuan as $b) {
      if ($b['id_satuan'] == $a['id_satuan']) {
        $unit = $b['nama_satuan'];
      }
    }
    $paket = $a['penjualan_jenis'];
  }
}

$id_harga_member = $data[2];
$saldoNya_member = number_format($data[3], 2);

$textMax = "";
if ($saldoNya_member > 0) {
  $textMax = "<span class='text-danger'>Saldo: " . number_format($saldoNya_member, 2) . $unit . "</span>";
}
?>

<form class="addOrder" action="<?= URL::BASE_URL ?>Penjualan/insert/<?= $idPenjualan ?>" method="POST">
  <div class="modal-header">
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>
  <div class="modal-body">
    <div class="card-body">
      <div class="row">
        <div class="col">
          <div class="form-group">
            <label for="exampleInputEmail1">Laundry <?= $paket ?><br>
              <font color='green'>
                <?php
                foreach ($this->diskon as $f) {
                  if ($f['id_penjualan_jenis'] == $idPenjualan) {
                    if ($f['qty_disc'] > 0 && $f['disc_qty'] > 0) {
                      echo "Min. " . $f['qty_disc'] . $unit . " Diskon " . $f['disc_qty'] . "%";
                    }
                  }
                }
                ?>
              </font>
            </label>
            <select name="f1" class="order form-control w-100 2tize" id='kiloan' required>
              <?php foreach ($this->harga as $a) {
                $kategori = "";
                $layanan = "";
                $durasi = "";
                if ($a['id_penjualan_jenis'] == $idPenjualan) {
                  foreach (unserialize($a['list_layanan']) as $b) {
                    foreach ($this->dLayanan as $c) {
                      if ($b == $c['id_layanan']) {
                        $layanan = $layanan . " " . $c['layanan'];
                      }
                    }
                  }
                  foreach ($this->dDurasi as $c) {
                    if ($a['id_durasi'] == $c['id_durasi']) {
                      $durasi = $durasi . " " . $c['durasi'];
                    }
                  }

                  foreach ($this->itemGroup as $c) {
                    if ($a['id_item_group'] == $c['id_item_group']) {
                      $kategori = $kategori . " " . $c['item_kategori'];
                    }
                  }

                  if ($this->mdl_setting['def_price'] == 0) {
                    $harga = $a['harga'];
                  } else {
                    $harga = $a['harga_b'];
                    if ($harga == 0) {
                      $harga = $a['harga'];
                    }
                  }
              ?>
                  <option id="op<?= $a['id_harga'] ?>" data-harga="<?= $harga ?>" value="<?= $a['id_harga'] ?>"><?= $kategori ?> - <?= $layanan ?> - <?= $durasi ?> - <?= $harga ?></option>
              <?php }
              } ?>
            </select>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <div class="form-group">
            <label for="exampleInputEmail1">Quantity (<?= $unit ?>)<br><?= $textMax ?></label>
            <input type="number" step="0.01" name="f2" class="form-control float bg-success font-weight-bold" id="qtyNya" placeholder="" required>
          </div>
        </div>
        <div class="col mt-auto">
          <div class="form-group">
            <label for="exampleInputEmail1">Harga /<?= $unit ?></label>
            <input id="harga" class="form-control text-center" id="exampleInputEmail1" placeholder="" readonly>
          </div>
        </div>
        <div class="col mt-auto">
          <div class="form-group">
            <label for="exampleInputEmail1">Total (Rp)</label>
            <input id="total_harga" class="form-control text-success text-center" id="exampleInputEmail1" placeholder="" readonly>
          </div>
        </div>
      </div>
      <?php if ($unit == "m<sup>2</sup>") { ?>
        <div>
          <div class="row">
            <div class="col">
              <div class="form-group">
                <label for="exampleInputEmail1">Pengali 1</label>
                <input type="number" step="0.01" class="form-control float bkali" id="bkali1">
              </div>
            </div>
            <div class="col">
              <div class="form-group">
                <label for="exampleInputEmail1">Pengali 2</label>
                <input type="number" step="0.01" class="form-control float bkali" id="bkali2">
              </div>
            </div>
          </div>
        </div>
      <?php } ?>
      <?php if ($unit == "kg") { ?>
        <div>
          <div class="row">
            <div class="col">
              <div class="form-group">
                <label for="exampleInputEmail1">Timbang 1</label>
                <input type="number" step="0.01" class="form-control float timb" id="timb1">
              </div>
            </div>
            <div class="col">
              <div class="form-group">
                <label for="exampleInputEmail1">Timbang 2</label>
                <input type="number" step="0.01" class="form-control float timb" id="timb2">
              </div>
            </div>
            <div class="col">
              <div class="form-group">
                <label for="exampleInputEmail1">Timbang 3</label>
                <input type="number" step="0.01" class="form-control float timb" id="timb3">
              </div>
            </div>
            <div class="col">
              <div class="form-group">
                <label for="exampleInputEmail1">Timbang 4</label>
                <input type="number" step="0.01" class="form-control float timb" id="timb4">
              </div>
            </div>
          </div>
        </div>
      <?php } ?>
      <div class="row">
        <div class="col">
          <div class="form-group">
            <div class="form-group">
              <label for="exampleInputEmail1">Catatan (optional)</label>
              <input type="text" name="f3" class="form-control" id="exampleInputEmail1" placeholder="">
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <div class="row">
        <div class="col"><button type="button" data-bs-dismiss="modal" aria-label="Close" class="btn btn-danger">Batal</button></div>
        <div class="col"><button type="submit" class="btn btn-success w-100">Tambah</button></div>
      </div>
    </div>
  </div>
  </div>
</form>

<script script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
  $(document).ready(function() {
    selectMember(<?= $id_harga_member ?>, <?= $saldoNya_member ?>);
    $(".2tize").selectize();

    $("form.addOrder").on("submit", function(e) {
      $("select.order[name=f1]").removeAttr('disabled');
      e.preventDefault();
      $.ajax({
        url: $(this).attr('action'),
        data: $(this).serialize(),
        type: $(this).attr("method"),
        success: function(res) {
          if (res != 0) {
            alert(res);
          } else {
            hide_modal();
            $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');
            $(".modal").hide();
          }
        },
      });
    });

    $('.float').keypress(function(event) {
      if ((event.which != 46 || $(this).val().indexOf('.') != -1) && (event.which < 48 || event.which > 57)) {
        event.preventDefault();
      }
    });

    $('select#kiloan').change(function() {
      harga();
      updateTotal();
    })

    harga();
  });

  $("input#qtyNya").keyup(function() {
    updateTotal();
  })

  function updateTotal() {
    var qty = $("input#qtyNya").val();
    var harga = $("input#harga").val();
    var total = parseInt(parseFloat(qty) * parseInt(harga));
    total = total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    if (total != 'NaN') {
      $("input#total_harga").val(total);
    } else {
      $("input#total_harga").val('');
    }
  }

  function selectMember(id_harga, saldoMember) {
    if (id_harga > 0) {
      $("select.order[name=f1] option[value=" + id_harga + "]").attr('selected', 'selected');
      $("select.order[name=f1] option[value=" + id_harga + "]").prop('selected', 'selected');
      $("select.order[name=f1]").attr('disabled', 'true');
      $("select.order[name=f1]").prop('disabled', 'true');
      if (saldoMember > 0) {
        $("input[name=f2]").attr("max", saldoMember);
        $("input[name=f2]").prop("max", saldoMember);
      }
    } else {
      $("select.order[name=f1]").removeAttr('disabled');
    }
  }

  function harga() {
    var id = $("select#kiloan").val();
    var harga = $('option#op' + id).attr('data-harga');
    $('input#harga').val(harga);
  }

  $("input.timb").on("keyup change", function() {
    var t1 = $("#timb1").val() || 0;
    var t2 = $("#timb2").val() || 0;
    var t3 = $("#timb3").val() || 0;
    var t4 = $("#timb4").val() || 0;
    var total = parseFloat(t1) + parseFloat(t2) + parseFloat(t3) + parseFloat(t4);
    $("input#qtyNya").val(parseFloat(total).toFixed(2));
    updateTotal();
  });

  $("input.bkali").on("keyup change", function() {
    var t1 = $("#bkali1").val() || 0;
    var t2 = $("#bkali2").val() || 0;
    var total = parseFloat(t1) * parseFloat(t2);
    $("input#qtyNya").val(parseFloat(total).toFixed(2));
    updateTotal();
  });
</script>