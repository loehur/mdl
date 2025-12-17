<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.css" rel="stylesheet" />

<?php
$masterList = $data['z']['data_master'] ?? [];
$masterMap = [];
foreach ($masterList as $m) {
    // Label: Brand Model (Code?)
    $label = $m['brand'] . ' ' . $m['model'];
    if(!empty($m['description'])) $label .= ' (' . $m['description'] . ')';
    $masterMap[$m['id_barang']] = $label;
}
?>

<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-auto">

        <div class="card">
          <div class="card-header">
            <button type="button" class="btn btn-sm btn-primary float-right" data-bs-toggle="modal" data-bs-target="#modalAdd">
              Tambah Sub Barang
            </button>
          </div>
          <div class="card-body p-1">
            <style>
                .editable {
                    display: block;
                    min-height: 20px;
                    width: 100%;
                }
            </style>
            <table class="table table-sm table-striped" id="dtTable">
              <thead>
                <tr>
                  <th>Master Barang</th>
                  <th>Nama Sub</th>
                  <th>Qty</th>
                  <th>Price</th>
                  <th>Act</th>
                </tr>
              </thead>
              <tbody>
                <?php
                foreach ($data['data_main'] as $a) {
                  $id = $a['id'];
                  $masterName = $masterMap[$a['id_barang']] ?? $a['id_barang'];
                  
                  echo "<tr>";
                  echo "<td><span class='text-uppercase' data-mode='1' data-id_value='" . $id . "' data-value='" . $a['id_barang'] . "'>" . $masterName . "</span></td>";
                  echo "<td><span class='editable text-uppercase' data-mode='2' data-id_value='" . $id . "' data-value='" . $a['nama'] . "'>" . $a['nama'] . "</span></td>";
                  echo "<td><span class='editable' data-mode='3' data-id_value='" . $id . "' data-value='" . $a['qty'] . "'>" . $a['qty'] . "</span></td>";
                  echo "<td><span class='editable' data-mode='4' data-id_value='" . $id . "' data-value='" . $a['price'] . "'>" . number_format($a['price']) . "</span></td>";
                  echo "<td>
                          <button class='btn btn-xs btn-danger delete-btn' data-id='" . $id . "'><i class='fas fa-trash'></i></button>
                        </td>";
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
          <h5 class="modal-title" id="modalAddLabel">Tambah Sub Barang</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <form action="<?= URL::BASE_URL; ?>Data_List/insert/barang_sub" method="POST">
            <div class="form-group mb-2">
              <label>Master Barang</label>
              <select name="f_master" class="form-control" required style="width: 100%;">
                <?php foreach($masterList as $m) { 
                    $label = $m['brand'] . ' ' . $m['model'];
                    if(!empty($m['description'])) $label .= ' (' . $m['description'] . ')';
                ?>
                   <option value="<?= $m['id_barang'] ?>"><?= strtoupper($label) ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group mb-2">
              <label>Nama Sub Barang</label>
              <input type="text" name="f_nama" class="form-control" required>
            </div>
            <div class="d-flex gap-2">
                <div class="form-group mb-2 w-50">
                  <label>Qty</label>
                  <input type="number" name="f_qty" class="form-control" step="any" required>
                </div>
                <div class="form-group mb-2 w-50">
                  <label>Price</label>
                  <input type="number" name="f_price" class="form-control" step="any" required>
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
  <script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
  <script src="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.js"></script>

  <script>
    var listMaster = <?= json_encode($masterList) ?>;
    var mapMaster = <?= json_encode($masterMap) ?>;

    // Helper to generate label in JS
    function getMasterLabel(m) {
        var label = m.brand + ' ' + m.model;
        if(m.description) label += ' (' + m.description + ')';
        return label;
    }

    var dt;
    $(document).ready(function() {
      dt = new DataTable('#dtTable');
      
      // Selectize for modal
      $('select[name="f_master"]').selectize();
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

      if(mode == 4) { // Price unformat
         value = value.toString().replace(/,/g, ''); 
      }

      function executeSave(value_after) {
        if (value_after == value_before) {
             if (mode == 1) {
                span.html(mapMaster[value_before] || value_before);
             } else {
                span.html(value_before);
             }
             click = 0;
             return;
        }

        $.ajax({
            url: '<?= URL::BASE_URL ?>Data_List/updateCell/barang_sub',
            data: {
              'id': id_value,
              'value': value_after,
              'mode': mode
            },
            type: 'POST',
            success: function(response) {
               if(mode == 4) {
                   // Format number display
                   var formatted = new Intl.NumberFormat('id-ID').format(value_after);
                   span.html(formatted);
                   span.attr('data-value', value_after);
                   click = 0;
               } else if (mode == 1) {
                   span.html(mapMaster[value_after] || value_after);
                   span.attr('data-value', value_after);
                   click = 0;
               } else {
                  span.html(value_after);
                  span.attr('data-value', value_after);
                  click = 0;
               }
            },
            error: function() {
                 if (mode == 1) {
                    span.html(mapMaster[value_before] || value_before);
                 } else {
                    span.html(value_before);
                 }
                click = 0;
                alert('Update failed');
            }
          });
      }

      if (mode == 1) { // Master Dropdown Selectize
          var select = "<select id='value_'>";
          listMaster.forEach(function(m) {
              var label = getMasterLabel(m).toUpperCase();
              var selected = (m.id_barang == value) ? 'selected' : '';
              select += "<option value='" + m.id_barang + "' " + selected + ">" + label + "</option>";
          });
          select += "</select>";
          span.html(select);
          
          var $s = $('#value_').selectize({
              onBlur: function() {
                  var val = this.getValue();
                  executeSave(val);
              }
          });
          $s[0].selectize.focus();

      } else {
          span.html("<input type='text' class='text-center form-control form-control-sm' id='value_' value='" + value + "'>");
          $("#value_").focus();
          
          $("#value_").on('blur focusout', function() {
            if(span.find('#value_').length === 0) return;
            var value_after = $(this).val();
            executeSave(value_after);
          });
      }
    });

    // Delete handler
    var deleteId = 0;
    var deleteRow = null;
    
    $('#dtTable').on('click', '.delete-btn', function() {
        deleteId = $(this).data('id');
        deleteRow = $(this).parents('tr');
        $('#modalDelete').modal('show');
    });

    $('#btnConfirmDelete').click(function() {
        if(deleteId) {
            $.post('<?= URL::BASE_URL ?>Data_List/delete/barang_sub', {id: deleteId}, function() {
                dt.row(deleteRow).remove().draw(false);
                $('#modalDelete').modal('hide');
            });
        }
    });
  </script>
