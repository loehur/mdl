<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="card-header">
            <div class="row">
              <div class="col">
                <h4 class="card-title text-success">Diskon <b>Khusus</b></h4>
                <button type="button" class="btn btn-sm py-0 btn-primary float-right" data-bs-toggle="modal" data-bs-target="#exampleModal">
                  +
                </button>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <span class="text-warning">Diskon Khusus akan menggantikan diskon Partner</span>
              </div>
            </div>

          </div>
          <!-- /.card-header -->
          <div class="card-body p-0">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Pelanggan</th>
                  <th>Jenis Laundry</th>
                  <th>Diskon</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($data['data_main'] as $dm) {
                  $id = $dm['id_diskon_khusus'];
                  $pelanggan = $dm['id_pelanggan'];
                  $diskon = $dm['diskon'];

                  //NAMA PELANGGAN
                  foreach ($this->pelanggan as $a) {
                    if ($a['id_pelanggan'] == $pelanggan) {
                      $pelanggan_nama = $a['nama_pelanggan'];
                    }
                  }

                  //JENIS LAUNDRY
                  foreach ($this->harga as $a) {
                    if ($a['id_harga'] == $dm['id_harga']) {
                      $layanan = "";
                      foreach ($this->dPenjualan as $pj) {
                        if ($pj['id_penjualan_jenis'] == $a['id_penjualan_jenis']) {
                          $jenis = $pj['penjualan_jenis'];
                        }
                      }

                      foreach (unserialize($a['list_layanan']) as $b) {
                        foreach ($this->dLayanan as $c) {
                          if ($b == $c['id_layanan']) {
                            $layanan = $layanan . " " . $c['layanan'];
                          }
                        }
                      }

                      foreach ($this->dDurasi as $c) {
                        if ($a['id_durasi'] == $c['id_durasi']) {
                          $durasi = $c['durasi'];
                        }
                      }

                      foreach ($this->itemGroup as $c) {
                        if ($a['id_item_group'] == $c['id_item_group']) {
                          $kategori = $c['item_kategori'];
                        }
                      }
                    }
                  }
                  $jenis_laundry = $jenis . " - " . $kategori . ", " . $layanan . " - " . $durasi;

                  echo "<tr>";
                  echo "<td>" . strtoupper($pelanggan_nama) . "</td>";
                  echo "<td>" . $jenis_laundry . "</td>";
                  echo "<td class='text-end'><span class='cell' data-id_value='" . $id . "' data-value='" . $diskon . "'>" . $diskon . "</span>%</td>";
                  echo "</tr>";
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
                <h5 class="modal-title">Set Diskon Khusus</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
              </div>
              <div class="modal-body">
                <form action="<?= URL::BASE_URL; ?>SetDiskon_Khusus/insert" method="POST">
                  <div class="card-body">

                    <!-- ======================================================== -->
                    <div class="form-group">
                      <label>Pelanggan</label>
                      <select id="pelanggan_submit" name="pelanggan" class="pelanggan form-control form-control-sm" style="width: 100%;" required>
                        <option value="" selected disabled></option>
                        <?php foreach ($this->pelanggan as $a) { ?>
                          <option id=" <?= $a['id_pelanggan'] ?>" value="<?= $a['id_pelanggan'] ?>"><?= strtoupper($a['nama_pelanggan']) . ", " . $a['nomor_pelanggan']  ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Jenis Laundry</label>
                      <select name="id_harga" class="order form-control form-control-sm" id='kiloan' style="width: 100%;" required>
                        <option value="">---</option>
                        <?php foreach ($this->harga as $a) {
                          $kategori = "";
                          $layanan = "";
                          $durasi = "";

                          foreach ($this->dPenjualan as $pj) {
                            if ($pj['id_penjualan_jenis'] == $a['id_penjualan_jenis']) {
                              $jenis = $pj['penjualan_jenis'];
                            }
                          }

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
                          <option id="op<?= $a['id_harga'] ?>" data-harga="<?= $harga ?>" value="<?= $a['id_harga'] ?>"><?= $jenis ?> - <?= $kategori ?>, <?= $layanan ?> - <?= $durasi ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Diskon %</label>
                      <input type="number" min="0" name="diskon" class="form-control form-control-sm" placeholder="" required>
                    </div>
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

<script>
  $(document).ready(function() {
    selectList();
  });

  $("form").on("submit", function(e) {
    e.preventDefault();
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      success: function(response) {
        if (response == 0) {
          location.reload(true);
        } else {
          alert(response);
        }
      },
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
    span.html("<input type='number' min='0' class='form-control-sm text-center' style='width:50px' id='value_' value='" + value + "'>");

    $("#value_").focus();
    $("#value_").focusout(function() {
      var value_after = $(this).val();
      if (value_after === value_before) {
        span.html(valHtml);
        click = 0;
      } else {
        $.ajax({
          url: '<?= URL::BASE_URL ?>SetDiskon_Khusus/updateCell',
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

  $(".cell_s").on('dblclick', function() {
    var id_value = $(this).attr('data-id_value');
    var value = $(this).attr('data-value');
    if (value == 0) {
      value = 1;
    } else {
      value = 0;
    }
    $.ajax({
      url: '<?= URL::BASE_URL ?>SetDiskon/updateCell_s',
      data: {
        'id': id_value,
        'value': value,
      },
      type: 'POST',
      dataType: 'html',
      success: function(response) {
        location.reload(true);
      },
    });
  });

  function selectList() {
    $('select.pelanggan').select2({
      dropdownParent: $("#exampleModal")
    });
    $('select.order').select2({
      dropdownParent: $("#exampleModal")
    });
  }

  $(document).on('select2:open', () => {
    document.querySelector('.select2-search__field').focus();
  });
</script>