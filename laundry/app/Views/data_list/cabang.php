<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title">Data Cabang</h4>
            <button type="button" class="btn btn-primary float-right" data-bs-toggle="modal" data-bs-target="#exampleModal">
              +
            </button>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>ID Cabang</th>
                  <th>Kode</th>
                  <th>Alamat</th>
                  <th>Area</th>
                  <th>Phone</th>
                  <th>Wifi</th>
                  <th class="text-end">Rent</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($data['data_cabang'] as $a) {
                  $id = $a['id_cabang'];
                  $kode = $a['kode_cabang'];
                  $alamat = $a['alamat'];
                  $id_kota = $a['id_kota'];
                  $kota = "";
                  $phone = isset($a['phone_number']) ? $a['phone_number'] : '';
                  $phoneDisp = strlen($phone) > 0 ? $phone : '[ ]';
                  $wifi = isset($a['wifi_pass']) ? $a['wifi_pass'] : '';
                  $wifiDisp = strlen($wifi) > 0 ? $wifi : '[ ]';
                  $pmode = 'server';
                  $rent = isset($a['rent']) ? $a['rent'] : 0;
                  $isTraining = !empty($a['is_training']);
                  foreach ($this->dKota as $dk) {
                    if ($dk['id_kota'] == $id_kota) {
                      $kota = $dk['nama_kota'];
                    }
                  }
                  echo "<tr" . ($isTraining ? " class='table-warning'" : "") . ">";
                  echo "<td class='text-right'>" . $id . ($isTraining ? " <span class='badge bg-warning text-dark'>TRAINING</span>" : "") . "</td>";
                  echo "<td><span class='cell' data-mode='1' data-id_value='" . $id . "' data-value='" . htmlspecialchars($kode, ENT_QUOTES) . "'>" . htmlspecialchars($kode) . "</span></td>";
                  echo "<td><span class='cell' data-mode='2' data-id_value='" . $id . "' data-value='" . htmlspecialchars($alamat, ENT_QUOTES) . "'>" . htmlspecialchars($alamat) . "</span></td>";
                  echo "<td><span class='cell' data-mode='3' data-id_value='" . $id . "' data-value='" . $id_kota . "'>" . htmlspecialchars($kota) . "</span></td>";
                  echo "<td><span class='cell' data-mode='4' data-id_value='" . $id . "' data-value='" . htmlspecialchars($phone, ENT_QUOTES) . "' title='Double click to edit'>" . htmlspecialchars($phoneDisp) . "</span></td>";
                  echo "<td><span class='cell' data-mode='7' data-id_value='" . $id . "' data-value='" . htmlspecialchars($wifi, ENT_QUOTES) . "' title='Double click to edit'>" . htmlspecialchars($wifiDisp) . "</span></td>";
                  echo "<td class='text-end'><span class='cell' data-mode='6' data-id_value='" . $id . "' data-value='" . $rent . "' title='Double click to edit'>" . number_format($rent) . "</span></td>";
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
                <h5 class="modal-title" id="exampleModalLabel">Penambahan Cabang</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
              </div>
              <div class="modal-body">
                <div id="info"></div>
                <form action="<?= URL::BASE_URL; ?>Cabang_List/insert" method="POST">
                  <div class="card-body">
                    <div class="form-group">
                      <label for="exampleInputEmail1">Kota Cabang</label>
                      <select id="kota" name="kota" class="form-control" required>
                        <option value="" disabled selected>---</option>
                        <?php foreach ($this->dKota as $a) { ?>
                          <option value="<?= $a['id_kota'] ?>"><?= $a['nama_kota'] ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label for="exampleInputEmail1">Alamat</label>
                      <input type="text" name="alamat" class="form-control form-control-sm" placeholder="" required>
                    </div>
                    <div class="form-group">
                      <label for="exampleInputEmail1">Phone Number</label>
                      <input type="text" name="phone_number" class="form-control form-control-sm" placeholder="" required>
                    </div>
                    <div class="form-group">
                      <label for="exampleInputEmail1">Wifi Password</label>
                      <input type="text" name="wifi_pass" class="form-control form-control-sm" placeholder="">
                    </div>
                    <div class="form-group">
                      <label for="exampleInputEmail1">Kode Cabang</label>
                      <input type="text" name="kode_cabang" class="form-control form-control-sm" placeholder="" required>
                    </div>
                    <div class="form-group">
                      <label for="exampleInputEmail1">Rent</label>
                      <input type="number" name="rent" class="form-control form-control-sm" placeholder="0" min="0" step="1" value="0">
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

    $(".selectRow").click(function() {
      var idNya = $(this).attr('data-id');
      $.ajax({
        url: "<?= URL::BASE_URL ?>Cabang_List/selectCabang",
        data: {
          'id': idNya
        },
        type: "POST",
        success: function(response) {
          location.reload(true);
        },
      });
    });

    $(".cell").on('dblclick', function() {
      var id_value = $(this).attr('data-id_value');
      var value = $(this).attr('data-value');
      var mode = $(this).attr('data-mode');
      var value_before = value;
      var span = $(this);

      var valHtml = $(this).html();
      if (mode == 3) {
        span.html('<select id="value_" required><option value="' + value + '" selected>' + valHtml + '</option><?php foreach ($this->dKota as $a) { ?><option value="<?= $a['id_kota'] ?>"><?= $a['nama_kota'] ?></option><?php } ?></select>');
      } else if (mode == 6) {
        span.html("<input type='number' id='value_' value='" + value + "' min='0' step='1'>");
      } else if (mode == 5) {
        var opts = ['server'];
        var h = '<select id="value_" required>';
        for (var i = 0; i < opts.length; i++) {
          var sel = (opts[i] === value) ? ' selected' : '';
          h += '<option value="' + opts[i] + '"' + sel + '>' + opts[i] + '</option>';
        }
        h += '</select>';
        span.html(h);
      } else {
        span.html("<input type='text' id='value_' value='" + value + "'>");
      }

      $("#value_").focus();
      $("#value_").focusout(function() {
        var value_after = $(this).val();
        if (value_after === value_before) {
          span.html(valHtml);
        } else {
          $.ajax({
            url: '<?= URL::BASE_URL ?>Cabang_List/update',
            data: {
              'id': id_value,
              'value': value_after,
              'mode': mode
            },
            type: 'POST',
            dataType: 'html',
            success: function(response) {
              location.reload(true);
            },
          });
        }
      });
    });

  });
</script>