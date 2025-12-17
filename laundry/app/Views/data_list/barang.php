<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.css" rel="stylesheet" />

<?php
$satuanList = $data['z']['data_satuan'] ?? [];
$satuanMap = [];
foreach ($satuanList as $s) {
    $satuanMap[$s['id']] = $s['nama'];
}
?>

<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-auto">

        <div class="card">
          <div class="card-header">
            <button type="button" class="btn btn-sm btn-primary float-right" data-bs-toggle="modal" data-bs-target="#modalAdd">
              Tambah Barang
            </button>
          </div>
          <div class="card-body p-1">
            <table class="table table-sm table-striped" id="dtTable">
              <thead>
                <tr>
                  <th>Brand</th>
                  <th>Model</th>
                  <th>Description</th>
                  <th>Price</th>
                  <th>Margin</th>
                  <th>Unit</th>
                  <th>St</th>
                </tr>
              </thead>
              <tbody>
                <?php
                foreach ($data['data_main'] as $a) {
                  $id = $a['id_barang'];
                  $unitName = $satuanMap[$a['unit']] ?? $a['unit'];
                  $stateVal = $a['state'];
                  $btnClass = ($stateVal == 1) ? 'btn-success' : 'btn-secondary';
                  
                  echo "<tr>";
                  echo "<td><span class='editable text-uppercase' data-mode='2' data-id_value='" . $id . "' data-value='" . $a['brand'] . "'>" . $a['brand'] . "</span></td>";
                  echo "<td><span class='editable text-uppercase' data-mode='3' data-id_value='" . $id . "' data-value='" . $a['model'] . "'>" . $a['model'] . "</span></td>";
                  echo "<td><span class='editable text-uppercase' data-mode='4' data-id_value='" . $id . "' data-value='" . $a['description'] . "'>" . $a['description'] . "</span></td>";
                  echo "<td><span class='editable' data-mode='5' data-id_value='" . $id . "' data-value='" . $a['price'] . "'>" . number_format($a['price']) . "</span></td>";
                  echo "<td><span class='editable' data-mode='6' data-id_value='" . $id . "' data-value='" . $a['margin'] . "'>" . $a['margin'] . "</span></td>";
                  echo "<td><span class='editable' data-mode='7' data-id_value='" . $id . "' data-value='" . $a['unit'] . "'>" . $unitName . "</span></td>";
                  echo "<td><button class='btn btn-xs " . $btnClass . " toggle-state' data-id='" . $id . "' data-val='" . $stateVal . "'>" . $stateVal . "</button></td>";
                  echo "</tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Add -->
  <div class="modal" id="modalAdd" tabindex="-1" aria-labelledby="modalAddLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalAddLabel">Tambah Barang</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <form action="<?= URL::BASE_URL; ?>Data_List/insert/barang" method="POST">
            <div class="form-group mb-2">
              <label>Code</label>
              <input type="text" name="f1" class="form-control" required>
            </div>
            <div class="form-group mb-2">
              <label>Brand</label>
              <input type="text" name="f2" class="form-control">
            </div>
            <div class="form-group mb-2">
              <label>Model</label>
              <input type="text" name="f3" class="form-control">
            </div>
            <div class="form-group mb-2">
              <label>Description</label>
              <input type="text" name="f4" class="form-control">
            </div>
            <div class="d-flex gap-2">
                <div class="form-group mb-2 w-50">
                  <label>Price</label>
                  <input type="number" name="f5" class="form-control" step="any" required>
                </div>
                <div class="form-group mb-2 w-50">
                  <label>Unit</label>
                  <select name="f_unit" class="form-control" required>
                    <?php foreach($satuanList as $s) { ?>
                       <option value="<?= $s['id'] ?>"><?= $s['nama'] ?></option>
                    <?php } ?>
                  </select>
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

  <!-- Modal Delete -->
  <div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Konfirmasi Hapus</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Yakin ingin menghapus data ini?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDelete">Hapus</button>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
  <script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
  <script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
  <script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>
  <script src="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.js"></script>

  <script>
    var listSatuan = <?= json_encode($satuanList) ?>;
    var mapSatuan = <?= json_encode($satuanMap) ?>;

    $(document).ready(function() {
      new DataTable('#dtTable');
    });

    $("form").on("submit", function(e) {
      e.preventDefault();
      $.ajax({
        url: $(this).attr('action'),
        data: $(this).serialize(),
        type: $(this).attr("method"),
        success: function() {
          location.reload(true);
        },
        error: function(xhr, status, error) {
            alert("Error: " + error);
        }
      });
    });

    var click = 0;
    $(".editable").on('dblclick', function() {
      click = click + 1;
      if (click != 1) return;

      var span = $(this);
      var id_value = span.attr('data-id_value');
      var value = span.attr('data-value');
      var mode = span.attr('data-mode');
      var value_before = value;

      // Unformat helper for price if needed
      if(mode == 5) { // Price
         value = value.toString().replace(/,/g, ''); 
      }

      if (mode == 7) { // Unit Dropdown
          var select = "<select class='form-control form-control-sm' id='value_'>";
          listSatuan.forEach(function(s) {
              var selected = (s.id == value) ? 'selected' : '';
              select += "<option value='" + s.id + "' " + selected + ">" + s.nama + "</option>";
          });
          select += "</select>";
          span.html(select);
      } else {
          span.html("<input type='text' class='text-center form-control form-control-sm' id='value_' value='" + value + "'>");
      }

      $("#value_").focus();
      
      // Handle blur for select and input
      $("#value_").on('blur focusout', function() {
        if(span.find('#value_').length === 0) return;

        var value_after = $(this).val();
        
        if (value_after == value_before) {
             if (mode == 7) {
                span.html(mapSatuan[value_before] || value_before);
             } else {
                span.html(value_before);
             }
             click = 0;
             return;
        }

        $.ajax({
            url: '<?= URL::BASE_URL ?>Data_List/updateCell/barang',
            data: {
              'id': id_value,
              'value': value_after,
              'mode': mode
            },
            type: 'POST',
            success: function(response) {
               if(mode == 5) {
                   location.reload(); 
               } else if (mode == 7) {
                   span.html(mapSatuan[value_after] || value_after);
                   span.attr('data-value', value_after);
                   click = 0;
               } else {
                  span.html(value_after);
                  span.attr('data-value', value_after);
                  click = 0;
               }
            },
            error: function() {
                 if (mode == 7) {
                    span.html(mapSatuan[value_before] || value_before);
                 } else {
                    span.html(value_before);
                 }
                click = 0;
                alert('Update failed');
            }
          });
      });
    });

    // Delete handler
    var deleteId = 0;
    $('.delete-btn').click(function() {
        deleteId = $(this).data('id');
        $('#modalDelete').modal('show');
    });

    $('#btnConfirmDelete').click(function() {
        if(deleteId) {
            $.post('<?= URL::BASE_URL ?>Data_List/delete/barang', {id: deleteId}, function() {
                location.reload();
            });
        }
    });

    // Toggle State Handler
    $('.toggle-state').click(function() {
        var btn = $(this);
        var id = btn.data('id');
        var currentVal = btn.data('val');
        var newVal = (currentVal == 1) ? 0 : 1;
        
        // Optimistic UI Update
        var newClass = (newVal == 1) ? 'btn-success' : 'btn-secondary';
        var oldClass = (currentVal == 1) ? 'btn-success' : 'btn-secondary';
        
        btn.removeClass(oldClass).addClass(newClass).text(newVal);
        btn.data('val', newVal);

        $.ajax({
            url: '<?= URL::BASE_URL ?>Data_List/updateCell/barang',
            data: {
              'id': id,
              'value': newVal,
              'mode': 9 // Mode 9 is State
            },
            type: 'POST',
            error: function() {
                // Revert on error
                alert('Gagal mengubah status');
                btn.removeClass(newClass).addClass(oldClass).text(currentVal);
                btn.data('val', currentVal);
            }
        });
    });
  </script>
