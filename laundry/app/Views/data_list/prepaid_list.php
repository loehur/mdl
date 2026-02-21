<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.css" rel="stylesheet" />

<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title">Prepaid List</h4>
            <button type="button" class="btn btn-primary float-right" data-bs-toggle="modal" data-bs-target="#addModal">
              + Tambah
            </button>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm" id="dtTable">
              <thead>
                <tr>
                  <th class="text-right">#</th>
                  <th>Product Code</th>
                  <th>Product Name</th>
                  <th class="text-start">Customer ID</th>
                  <th>Nominal</th>
                  <th>Description</th>
                  <th>Monthly Limit</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no = 0;
                foreach ($data['data_main'] as $a) {
                  $id = $a['pre_id'];
                  $no++;
                  $desc = !empty($a['description']) ? $a['description'] : '-';
                  echo "<tr>";
                  echo "<td class='text-right'>" . $no . "</td>";
                  echo "<td><span class='cell' data-mode='1' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['product_code']) . "'>" . htmlspecialchars($a['product_code']) . "</span></td>";
                  echo "<td><span class='cell' data-mode='2' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['product_name']) . "'>" . htmlspecialchars($a['product_name']) . "</span></td>";
                  echo "<td class='text-start'><span class='cell' data-mode='3' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['customer_id']) . "'>" . htmlspecialchars($a['customer_id']) . "</span></td>";
                  echo "<td><span class='cell' data-mode='4' data-id_value='" . $id . "' data-value='" . $a['nominal'] . "'>" . number_format($a['nominal']) . "</span></td>";
                  echo "<td><span class='cell' data-mode='5' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['description']) . "'>" . htmlspecialchars($desc) . "</span></td>";
                  echo "<td><span class='cell' data-mode='6' data-id_value='" . $id . "' data-value='" . $a['monthly_limit'] . "'>" . $a['monthly_limit'] . "</span></td>";
                  echo "<td><button type='button' class='btn btn-sm btn-danger btn-delete' data-id='" . $id . "' data-name='" . htmlspecialchars($a['product_name']) . "'>Hapus</button></td>";
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
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Prepaid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div id="info"></div>
                <form action="<?= URL::BASE_URL; ?>PrepaidList/insert" method="POST" id="formAdd">
                  <div class="mb-2">
                    <label class="form-label">Product Code</label>
                    <input type="text" name="product_code" class="form-control form-control-sm" placeholder="hpln500000" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="product_name" class="form-control form-control-sm" placeholder="PLN Token 500,000" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Customer ID</label>
                    <input type="text" name="customer_id" class="form-control form-control-sm" placeholder="No. meter" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Nominal</label>
                    <input type="number" name="nominal" class="form-control form-control-sm" placeholder="500000" min="0" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="Token MDL MW">
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Monthly Limit</label>
                    <input type="number" name="monthly_limit" class="form-control form-control-sm" placeholder="0" min="0" value="0">
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
  new DataTable('#dtTable', { order: [[2, 'asc']] });

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
      url: "<?= URL::BASE_URL ?>PrepaidList/delete",
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

    if (mode == 4 || mode == 6) {
      span.html("<input type='number' id='value_' class='form-control form-control-sm' value='" + value + "' min='0'>");
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
          url: '<?= URL::BASE_URL ?>PrepaidList/update',
          data: { id: id_value, value: value_after, mode: mode },
          type: 'POST',
          dataType: 'html',
          success: function(response) {
            if (response == '0') {
              span.attr('data-value', value_after);
              var disp = (mode == 4) ? (parseInt(value_after) || 0).toLocaleString('id-ID') : (value_after === '' && mode == 5 ? '-' : $('<div/>').text(value_after).html());
              span.html(disp);
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
