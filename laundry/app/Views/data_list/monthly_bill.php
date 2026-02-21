<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.css" rel="stylesheet" />

<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title">Monthly Bill (Postpaid List)</h4>
            <button type="button" class="btn btn-primary float-right" data-bs-toggle="modal" data-bs-target="#addModal">
              + Tambah
            </button>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm" id="dtTable">
              <thead>
                <tr>
                  <th class="text-right">#</th>
                  <th>Code</th>
                  <th class="text-start">Customer ID</th>
                  <th>Description</th>
                  <th>Last Bill</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no = 0;
                foreach ($data['data_main'] as $a) {
                  $id = $a['bill_id'];
                  $no++;
                  $lastBill = !empty($a['last_bill']) ? $a['last_bill'] : '-';
                  $enLabel = ($a['en'] == 1) ? 'Enabled' : 'Disabled';
                  echo "<tr>";
                  echo "<td class='text-right'>" . $no . "</td>";
                  echo "<td><span class='cell' data-mode='1' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['code']) . "'>" . htmlspecialchars($a['code']) . "</span></td>";
                  echo "<td class='text-start'><span class='cell' data-mode='2' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['customer_id']) . "'>" . htmlspecialchars($a['customer_id']) . "</span></td>";
                  echo "<td><span class='cell' data-mode='3' data-id_value='" . $id . "' data-value='" . htmlspecialchars($a['description']) . "'>" . htmlspecialchars($a['description']) . "</span></td>";
                  echo "<td>" . $lastBill . "</td>";
                  echo "<td><span class='cell' data-mode='4' data-id_value='" . $id . "' data-value='" . $a['en'] . "'>" . $enLabel . "</span></td>";
                  echo "<td><button type='button' class='btn btn-sm btn-danger btn-delete' data-id='" . $id . "'>Hapus</button></td>";
                  echo "</tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="modal" id="addModal" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Monthly Bill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div id="info"></div>
                <form action="<?= URL::BASE_URL; ?>MonthlyBill/insert" method="POST" id="formAdd">
                  <div class="mb-2">
                    <label class="form-label">Code</label>
                    <input type="text" name="code" class="form-control form-control-sm" placeholder="PLNPOSTPAID, MYREPUBLIC, dll" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Customer ID</label>
                    <input type="text" name="customer_id" class="form-control form-control-sm" placeholder="No. meter / ID pelanggan" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="Listrik RW, MyRepublic KS, dll" required>
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
  new DataTable('#dtTable');

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

  $(".btn-delete").on("click", function() {
    if (!confirm("Yakin hapus data ini?")) return;
    var id = $(this).data('id');
    $.ajax({
      url: "<?= URL::BASE_URL ?>MonthlyBill/delete",
      data: { id: id },
      type: "POST",
      dataType: 'html',
      success: function(response) {
        if (response == '0') {
          location.reload(true);
        } else {
          alert(response);
        }
      }
    });
  });

  $(".cell").on('dblclick', function() {
    var id_value = $(this).attr('data-id_value');
    var value = $(this).attr('data-value');
    var mode = $(this).attr('data-mode');
    var value_before = value;
    var span = $(this);
    var valHtml = $(this).html();

    if (mode == 4) {
      var opts = [{v:0,l:'Disabled'},{v:1,l:'Enabled'}];
      var h = '<select id="value_" required>';
      for (var i = 0; i < opts.length; i++) {
        h += '<option value="' + opts[i].v + '">' + opts[i].l + '</option>';
      }
      h += '</select>';
      span.html(h);
      $("#value_").val(value);
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
          url: '<?= URL::BASE_URL ?>MonthlyBill/update',
          data: { id: id_value, value: value_after, mode: mode },
          type: 'POST',
          dataType: 'html',
          success: function(response) {
            if (response == '0') {
              location.reload(true);
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
