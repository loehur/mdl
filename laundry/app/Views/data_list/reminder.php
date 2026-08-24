<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.css" rel="stylesheet" />

<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title">Reminder</h4>
            <button type="button" class="btn btn-primary float-right" data-bs-toggle="modal" data-bs-target="#addModal">
              + Tambah
            </button>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm" id="dtTable">
              <thead>
                <tr>
                  <th class="text-right">#</th>
                  <th>Name</th>
                  <th>Note</th>
                  <th>Next Date</th>
                  <th>Cycle</th>
                  <th>Cycle Type</th>
                  <th>Range</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no = 0;
                foreach ($data['data_main'] as $a) {
                  $id = $a['id'];
                  $no++;
                  $note = !empty($a['note']) ? $a['note'] : '-';
                  echo "<tr>";
                  echo "<td class='text-right'>" . $no . "</td>";
                  echo "<td><span class='cell' data-mode='1' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['name']) . "'>" . htmlspecialchars($a['name']) . "</span></td>";
                  echo "<td><span class='cell' data-mode='2' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['note']) . "'>" . htmlspecialchars($note) . "</span></td>";
                  echo "<td><span class='cell' data-mode='3' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['next_date']) . "'>" . htmlspecialchars($a['next_date']) . "</span></td>";
                  echo "<td><span class='cell' data-mode='4' data-id_value='" . $id . "' data-value='" . $a['cycle'] . "'>" . $a['cycle'] . "</span></td>";
                  echo "<td><span class='cell' data-mode='5' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['cycle_type']) . "'>" . htmlspecialchars($a['cycle_type']) . "</span></td>";
                  echo "<td><span class='cell' data-mode='6' data-id_value='" . $id . "' data-value='" . $a['range'] . "'>" . $a['range'] . "</span></td>";
                  echo "<td><button type='button' class='btn btn-sm btn-danger btn-delete' data-id='" . $id . "' data-name='" . htmlspecialchars($a['name']) . "'>Hapus</button></td>";
                  echo "</tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="modal" id="confirmDeleteModal" tabindex="-1">
          <div class="modal-dialog modal-sm">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                Yakin ingin menghapus <strong id="deleteItemName"></strong>?
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDelete">Hapus</button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal" id="addModal" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div id="info"></div>
                <form action="<?= URL::BASE_URL; ?>Reminder/insert" method="POST" id="formAdd">
                  <div class="row">
                    <div class="col-md-6 mb-2">
                      <label class="form-label">Name</label>
                      <input type="text" name="name" class="form-control form-control-sm" placeholder="Kontrakan Mawar, Pajak Motor, dll" required>
                    </div>
                    <div class="col-md-6 mb-2">
                      <label class="form-label">Next Date</label>
                      <input type="date" name="next_date" class="form-control form-control-sm" required>
                    </div>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Note</label>
                    <input type="text" name="note" class="form-control form-control-sm" placeholder="Catatan tambahan (opsional)">
                  </div>
                  <div class="row">
                    <div class="col-md-4 mb-2">
                      <label class="form-label">Cycle</label>
                      <input type="number" name="cycle" class="form-control form-control-sm" placeholder="12" min="1" required>
                    </div>
                    <div class="col-md-4 mb-2">
                      <label class="form-label">Cycle Type</label>
                      <select name="cycle_type" class="form-control form-control-sm" required>
                        <option value="day">day</option>
                        <option value="month" selected>month</option>
                        <option value="year">year</option>
                      </select>
                    </div>
                    <div class="col-md-4 mb-2">
                      <label class="form-label">Range (hari)</label>
                      <input type="number" name="range" class="form-control form-control-sm" placeholder="7" min="1" required>
                    </div>
                  </div>
                </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="formAdd" class="btn btn-sm btn-primary">Tambah</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.js"></script>

<script>
$(document).ready(function() {
  new DataTable('#dtTable', {
    order: [[1, 'asc']]
  });

  $("#formAdd").on("submit", function(e) {
    e.preventDefault();
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      dataType: 'html',
      success: function(response) {
        if (response == '0') {
          location.reload(true);
        } else {
          $("#info").html('<div class="alert alert-danger">' + response + '</div>');
        }
      }
    });
  });

  var deleteId = null;
  $(document).on("click", ".btn-delete", function(e) {
    e.preventDefault();
    deleteId = $(this).data('id');
    var name = $(this).data('name') || '(tanpa nama)';
    $("#deleteItemName").text(name);
    var modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
  });

  $("#btnConfirmDelete").on("click", function() {
    if (!deleteId) return;
    $.ajax({
      url: "<?= URL::BASE_URL ?>Reminder/delete",
      data: { id: deleteId },
      type: "POST",
      dataType: 'html',
      success: function(response) {
        if (response == '0') {
          bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
          location.reload(true);
        } else {
          alert(response);
        }
      }
    });
  });

  $(document).on('dblclick', '.cell', function() {
    var id_value = $(this).attr('data-id_value');
    var value = $(this).attr('data-value');
    var mode = $(this).attr('data-mode');
    var value_before = value;
    var span = $(this);
    var valHtml = $(this).html();

    if (mode == 5) {
      var opts = [{v:'day',l:'day'},{v:'month',l:'month'},{v:'year',l:'year'}];
      var h = '<select id="value_" required>';
      for (var i = 0; i < opts.length; i++) {
        h += '<option value="' + opts[i].v + '">' + opts[i].l + '</option>';
      }
      h += '</select>';
      span.html(h);
      $("#value_").val(value);
    } else if (mode == 3) {
      span.html("<input type='date' id='value_' class='form-control form-control-sm' value='" + value + "'>");
    } else if (mode == 4 || mode == 6) {
      span.html("<input type='number' id='value_' class='form-control form-control-sm' value='" + value + "' min='1'>");
    } else {
      span.html("<input type='text' id='value_' class='form-control form-control-sm' value='" + $('<div/>').text(value).html() + "'>");
    }

    $("#value_").focus();
    $("#value_").on('blur', function() {
      var value_after = $(this).val();
      if (value_after === value_before) {
        span.html(valHtml);
      } else {
        $.ajax({
          url: '<?= URL::BASE_URL ?>Reminder/update',
          data: { id: id_value, value: value_after, mode: mode },
          type: 'POST',
          dataType: 'html',
          success: function(response) {
            if (response == '0') {
              span.attr('data-value', value_after);
              span.html(value_after === '' ? '-' : $('<div/>').text(value_after).html());
            } else {
              span.html(valHtml);
              alert(response);
            }
          }
        });
      }
    });
  });
});
</script>
