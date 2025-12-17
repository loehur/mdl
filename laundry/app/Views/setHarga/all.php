<?php
$page = $data['z']['page'];
$id_satuan = $data['z']['unit'];
$satuan = "";
foreach ($this->dSatuan as $a) {
  if ($a['id_satuan'] == $id_satuan) {
    $satuan = $a['nama_satuan'];
  }
}
?>
<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/dataTables/jquery.dataTables.css" rel="stylesheet" />

<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title text-success">Harga <?= $data['z']['set'] ?></h4>
            <button type="button" class="btn btn-sm btn-primary float-right" data-bs-toggle="modal" data-bs-target="#exampleModal">
              Tambah Harga
            </button>
          </div>
          <div class="card-body p-2">
            <table class="table w-100 table-sm" id="price_table">
              <thead>
                <tr>
                  <th>Nama Kategori</th>
                  <th>Layanan</th>
                  <th>Durasi</th>
                  <th>Hari</th>
                  <th>Jam</th>
                  <th class="text-right">Rp/<?= $satuan ?> (A)</th>
                  <th class="text-right">Rp/<?= $satuan ?> (B)</th>
                  <th>Min Order</th>
                  <th class="text-right">Used</th>
                  <th class="text-right">ID</th>
                </tr>
              </thead>
              <tbody>
                <?php

                $IDkategori = "";
                $IDkategoriBefore = "";
                $layananBefore = "";
                $layanan = "";

                foreach ($data['data_main'] as $a) {
                  $id = $a['id_harga'];
                  $f2 = $a['id_item_group'];
                  $f3 = $a['list_layanan'];
                  $f4 = $a['id_durasi'];
                  $f5 = $a['harga'];
                  $f5_b = $a['harga_b'];
                  $f6 = $a['hari'];
                  $f7 = $a['jam'];
                  $f8 = $a['sort'];
                  $f9 = $a['min_order'];

                  $IDkategori = $f2;
                  if ($IDkategori == $IDkategoriBefore) {
                  } else {
                    $kategori = "";
                    foreach ($data['d2'] as $b) {
                      if ($b['id_item_group'] == $f2) {
                        $kategori = $b['item_kategori'];
                      }
                    }
                  }

                  $layanan = $f3;
                  if (($IDkategori == $IDkategoriBefore) && ($layanan == $layananBefore)) {
                  } else {
                    $list_layanan = "";
                    $arrList_layanan = unserialize($f3);
                    foreach ($arrList_layanan as $b) {
                      foreach ($this->dLayanan as $c) {
                        if ($c['id_layanan'] == $b) {
                          $list_layanan = $list_layanan . " " . $c['layanan'];
                        }
                      }
                    }
                  }

                  foreach ($this->dDurasi as $b) {
                    if ($b['id_durasi'] == $f4) {
                      $durasi = $b['durasi'];
                    }
                  }

                  echo "<tr>";
                  echo "<td class='text-primary'><b>" . $kategori . "</b></td>";
                  echo "<td>" . $list_layanan . "</td>";
                  echo "<td>" . $durasi . "</td>";
                  echo "<td class='text-right'> <span class='cell' data-mode='2' data-id_value='" . $id . "' data-value='" . $f6 . "'><b>" . $f6 . "</b></span> Hari</td>";
                  echo "<td class='text-right'> <span class='cell' data-mode='3' data-id_value='" . $id . "' data-value='" . $f7 . "'><b>" . $f7 . "</b></span> Jam</td>";
                  echo "<td class='text-right'>Rp<span class='cell' data-mode='1' data-id_value='" . $id . "' data-value='" . $f5 . "'>" . $f5 . "</span></td>";
                  echo "<td class='text-right'>Rp<span class='cell' data-mode='6' data-id_value='" . $id . "' data-value='" . $f5_b . "'>" . $f5_b . "</span></td>";
                  echo "<td class='text-right'><span class='cell' data-mode='5' data-id_value='" . $id . "' data-value='" . $f9 . "'>" . $f9 . "</span></td>";
                  echo "<td class='text-right'> <span>" . $f8 . "</span></td>";
                  echo "<td class='text-right'>" . $id . "</td>";
                  echo "</tr>";

                  $IDkategoriBefore = $IDkategori;
                  $layananBefore = $layanan;
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Set Harga <?= $data['z']['set'] ?></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
              </div>
              <div class="modal-body">
                <form action="<?= URL::BASE_URL; ?>SetHarga/insert/<?= $page ?>" method="POST">
                  <div class="card-body">

                    <!-- ======================================================== -->
                    <div class="form-group" id="kategori_select">
                      <label>Kategori</label>
                      <select id="sel_kategori" name="f1" class="form-control form-control-sm select2" style="width: 100%" required>
                        <option value="" disabled selected></option>
                        <?php foreach ($data['d2'] as $a) { ?>
                          <option value="<?= $a['id_item_group'] ?>"><?= $a['item_kategori'] ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="form-group" id="layanan_select">
                      <label>Layanan</label>
                      <select id="sel_layanan" class="selectMulti form-control form-control-sm" style="width: 100%" name="f2[]" multiple="multiple" required>
                        <?php foreach ($this->dLayanan as $a) { ?>
                          <option value="<?= $a['id_layanan'] ?>"><?= $a['layanan'] ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Durasi</label>
                      <select name="f3" class="form-control form-control-sm" required>
                        <option value="" disabled selected></option>
                        <?php foreach ($this->dDurasi as $a) { ?>
                          <option value="<?= $a['id_durasi'] ?>"><?= $a['durasi'] ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Minimal Order (<?= $satuan ?>)</label>
                      <input type="number" min="1" name="f5" class="form-control form-control-sm" value="0" placeholder="" required>
                    </div>
                    <div class="form-group">
                      <label>Harga/<?= $satuan ?></label>
                      <input type="number" min="1" name="f4" class="form-control form-control-sm" placeholder="" required>
                    </div>
                    <!-- ======================================================== -->

                  </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-sm btn-primary">Tambah</button>
              </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/dataTables/jquery.dataTables.js"></script>

<script>
  $(document).ready(function() {
    new DataTable('#price_table');

    $('#sel_layanan').select2({
      theme: "classic",
      dropdownParent: $("#layanan_select"),
    });

    $('#sel_kategori').select2({
      dropdownParent: $("#kategori_select"),
    });

    $("form").on("submit", function(e) {
      e.preventDefault();
      $.ajax({
        url: $(this).attr('action'),
        data: $(this).serialize(),
        type: $(this).attr("method"),
        dataType: 'html',

        success: function(response) {
          location.reload(true);
        },
      });
    });
  });

  var click = 0;
  $(".cell").on('dblclick', function() {
    click = click + 1;
    if (click != 1) {
      return;
    }

    var id_value = $(this).attr('data-id_value');
    var value = $(this).attr('data-value');
    var mode = $(this).attr('data-mode');
    var value_before = value;
    var span = $(this);

    var valHtml = $(this).html();
    span.html("<input type='number' min='0' style='width:70px' id='value_' value='" + value + "'>");

    $("#value_").focus();
    $("#value_").focusout(function() {
      var value_after = $(this).val();
      if (value_after === value_before) {
        span.html(valHtml);
        click = 0;
      } else {
        $.ajax({
          url: '<?= URL::BASE_URL ?>SetHarga/updateCell',
          data: {
            'id': id_value,
            'value': value_after,
            'mode': mode
          },
          type: 'POST',
          dataType: 'html',
          success: function(response) {
            span.html(value_after);
            click = 0;
          },
        });
      }
    });
  });
</script>