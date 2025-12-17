<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-auto">

        <div class="card">
          <div class="card-header">
            <button type="button" class="btn btn-sm btn-primary float-right" data-bs-toggle="modal" data-bs-target="#exampleModal">
              Tambah Item Pengeluaran
            </button>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th class="text-right">#</th>
                  <th>Item Pengeluaran</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no = 0;
                foreach ($data['data_main'] as $a) {
                  $id = $a['id_item_pengeluaran'];
                  $f1 = $a['item_pengeluaran'];
                  $no++;
                  echo "<tr>";
                  echo "<td class='text-right'>" . $no . "</td>";
                  echo "<td><span data-mode='1' data-id_value='" . $id . "' data-value='" . $f1 . "'>" . $f1 . "</span></td>";
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

  <div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Penambahan Item</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <!-- ====================== FORM ========================= -->
          <form action="<?= URL::BASE_URL; ?>Data_List/insert/item_pengeluaran" method="POST">
            <div class="card-body">
              <div class="form-group">
                <label for="exampleInputEmail1">Nama Item</label>
                <input type="text" name="f1" class="form-control" id="exampleInputEmail1" placeholder="" required>
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
          success: function() {
            location.reload(true);
          },
        });
      });

      var click = 0;
      $("span").on('dblclick', function() {
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
        span.html("<input type='text' class='text-center' id='value_' value='" + value + "'>");

        $("#value_").focus();
        $("#value_").focusout(function() {
          var value_after = $(this).val();
          if (value_after === value_before) {
            span.html(value);
            click = 0;
          } else {
            $.ajax({
              url: '<?= URL::BASE_URL ?>Data_List/updateCell/item_pengeluaran',
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