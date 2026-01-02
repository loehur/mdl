<div class="row mx-0">
  <div class="col-auto">
    <button type="button" class="btn btn-primary btn-sm bg-gradient float-right mb-2" data-bs-toggle="modal" data-bs-target="#exampleModal">
      Tambah Cabang
    </button>
    <table class="table table-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>Kode</th>
          <th>Alamat</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data['data_cabang'] as $a) {
          $id = $a['id_cabang'];
          $kode = $a['kode_cabang'];
          $alamat = $a['alamat'];
          echo "<tr>";
          echo "<td class='text-right'>" . $id . "</td>";
          echo "<td><span class='cell' data-mode='1' data-id_value='" . $id . "' data-value='" . $kode . "'>" . $kode . "</span></td>";
          echo "<td><span class='cell' data-mode='2' data-id_value='" . $id . "' data-value='" . $alamat . "'>" . $alamat . "</span></td>";
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
              <label for="exampleInputEmail1">Kode Cabang</label>
              <input type="text" name="kode_cabang" class="form-control form-control-sm" placeholder="" required>
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

<!-- SCRIPT -->
<script src="<?= URL::ASSETS_URL ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::ASSETS_URL ?>js/popper.min.js"></script>
<script src="<?= URL::ASSETS_URL ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::ASSETS_URL ?>plugins/select2/select2.min.js"></script>

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

    $(".cell").on('dblclick', function() {
      var id_value = $(this).attr('data-id_value');
      var value = $(this).attr('data-value');
      var mode = $(this).attr('data-mode');
      var value_before = value;
      var span = $(this);

      var valHtml = $(this).html();
      if (mode == 3) {
        span.html('<select id="value_" required><option value="' + value + '" selected>' + valHtml + '</option><?php foreach ($this->dKota as $a) { ?><option value="<?= $a['id_kota'] ?>"><?= $a['nama_kota'] ?></option><?php } ?></select>');
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