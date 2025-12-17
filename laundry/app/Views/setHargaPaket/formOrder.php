<?php
$idPenjualan = $data;
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
?>

<form action="<?= URL::BASE_URL ?>SetHargaPaket/insert" method="POST">
  <div class="modal-header">
    <h5 class="modal-title" id="exampleModalLabel"></h5>
  </div>
  <div class="modal-body">
    <div class="card-body">
      <div class="row">
        <div class="col">
          <div class="form-group">
            <label for="exampleInputEmail1">Paket <?= $paket ?></label>
            <font color='green'>
              <?php
              foreach ($this->diskon as $f) {
                if ($f['id_penjualan_jenis'] == $idPenjualan) {
                  if ($f['qty_disc'] > 0) {
                    echo "Laundry " . $f['qty_disc'] . $unit . " Diskon " . $f['disc_qty'] . "%";
                  }
                }
              }
              ?>
            </font>
            </small></label>
            <select name="f1" class="order form-control form-control-sm" id='kiloan' required>
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
              ?>
                  <option id="op<?= $a['id_harga'] ?>" data-harga="<?= $a['harga'] ?>" value="<?= $a['id_harga'] ?>"><?= $kategori ?> * <?= $layanan ?> * <?= $durasi ?></option>
              <?php }
              } ?>
            </select>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <div class="form-group">
            <label for="exampleInputEmail1">Quantity (<?= $unit ?>)</label>
            <input type="number" step="0.01" name="f2" class="form-control float" id="exampleInputEmail1" placeholder="" required>
          </div>
        </div>
        <div class="col">
          <div class="form-group">
            <label for="exampleInputEmail1">Harga</label>
            <input id="harga" name="f3" class="form-control" id="exampleInputEmail1" placeholder="" required>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="submit" class="btn btn-sm btn-primary">Tambah</button>
    </div>
  </div>
  </div>
</form>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.1/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>

<script>
  $(document).ready(function() {
    $('.float').keypress(function(event) {
      if ((event.which != 46 || $(this).val().indexOf('.') != -1) && (event.which < 48 || event.which > 57)) {
        event.preventDefault();
      }
    });

    $('select.order').select2({
      dropdownParent: $("#exampleModal")
    });
  });

  function harga() {
    var id = $("select#kiloan").val();
    var harga = $('option#op' + id).attr('data-harga');
    $('input#harga').val(harga);
  }
</script>