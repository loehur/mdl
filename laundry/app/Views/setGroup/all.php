<?php $page = $data['z']['page'] ?>
<?php $sisa_item = $data['d2']; ?>


<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/dataTables/jquery.dataTables.css" rel="stylesheet" />
<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-auto">

        <div class="card">
          <div class="card-header">
            <h4 class="card-title"><span class="text-success"><?= $data['z']['title'] ?></span></h4>
            <button type="button" class="btn btn-sm btn-primary float-right" data-bs-toggle="modal" data-bs-target="#exampleModal">
              Tambah Produk
            </button>
          </div>
          <!-- card-header -->
          <div class="card-body p-1">
            <table class="table table-sm" id="dtTable" aria-describedby="example2_info">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nama Kategori</th>
                  <th>Item List</th>
                  <th>Opsi</th>
                </tr>
              </thead>
              <tbody>
                <?php $no = 0;
                foreach ($data['data_main'] as $a) {
                  $no++;
                  $id = $a['id_item_group'];
                  $f1 = $a['item_kategori'];
                  $f2 = $a['item_list'];


                  $item = "";
                  $item_ = unserialize($f2);
                  $arrCount = count($item_);

                  foreach ($item_ as $c) {
                    foreach ($data['d2'] as $dkey => $d) {
                      if ($d['id_item'] == $c) {
                        if ($page <> 1) {
                          //unset($sisa_item[$dkey]);
                        }
                        if ($arrCount > 1) {
                          $item = $item . "<span id='item" . $d['id_item'] . "' class='badge badge-light text-dark'>" . $d['item'] . " <a id='" . $id . "' data-idItem='" . $d['id_item'] . "' data-value='" . $f2 . "' class='text-danger removeItem' href='#'><i class='fas fa-times-circle'></i></a></span> ";
                        } else {
                          $item = $item . "<span class='badge badge-light text-dark'>" . $d['item'] . "</span> ";
                        }
                      }
                    }
                  }
                  echo "<tr>";
                  echo "<td class='text-right'>$no</td>";
                  echo "<td><span class='cell' data-mode='1' data-id_value='" . $id . "' data-value='" . $f1 . "'>" . $f1 . "</span></td>";
                  echo "<td>" . $item . "</td>";
                  echo "<td><a data-value='" . $f2 . "' data-id='" . $id . "' class='addItem badge btn-primary' data-bs-toggle='modal' data-bs-target='#exampleModal2' href='#'><i class='fas fa-plus-circle'></i></a></td>";
                  echo "</tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ============================================================= MODAL =============================================================== -->

        <div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><?= $data['z']['title'] ?></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
              </div>
              <div class="modal-body">
                <form action="<?= URL::BASE_URL; ?>SetGroup/insert/<?= $page ?>" method="POST">
                  <div class="card-body">

                    <!-- ======================================================== -->
                    <div class="form-group">
                      <label>Item</label>
                      <select name="f1[]" class="select2 form-control form-control-sm" style="width: 100%" multiple="multiple" required>
                        <?php foreach ($sisa_item as $a) { ?>
                          <option value="<?= $a['id_item'] ?>"><?= $a['item'] ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Nama Produk</label> <small>(Produk yang ditampilkan saat Order)</small>
                      <input type="text" name="f2" class="form-control form-control-sm" placeholder="Contoh: Pakaian Harian" required>
                    </div>
                    <!-- ====================================================================================== -->

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

        <div class="modal" id="exampleModal2" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-sm">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Tambah Item</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
              </div>
              <div class="modal-body">
                <form action="<?= URL::BASE_URL; ?>SetGroup/addItem/<?= $page ?>" method="POST">
                  <div class="card-body">
                    <div class="form-group">
                      <label>Item</label>
                      <select name="f1" class="select2a form-control form-control-sm" style="width: 100%" required>
                        <option value="" selected></option>
                        <?php foreach ($sisa_item as $a) { ?>
                          <option value="<?= $a['id_item'] ?>"><?= $a['item'] ?></option>
                        <?php } ?>
                      </select>
                      <input type="hidden" id="idItem" name="f2" value="" required>
                      <input type="hidden" id="valueItem" name="f3" value="" required>
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
        <script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
        <script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
        <script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
        <script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>
        <script src="<?= URL::EX_ASSETS ?>plugins/dataTables/jquery.dataTables.js"></script>

        <!-- FUNCTION SCRIPTS -->
        <script>
          $(document).ready(function() {

            new DataTable('#dtTable');

            $('.select2').select2({
              theme: "classic"
            });

            $('.select2a').select2({
              placeholder: "Pilih ITEM",
              dropdownParent: $("#exampleModal2")
            });
          })

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
            span.html("<input type='text' style='width:150px;' class='form-control-sm text-center' id='value_' value='" + value + "'>");

            $("#value_").focus();
            $("#value_").focusout(function() {
              var value_after = $(this).val();
              if (value_after === value_before) {
                span.html(valHtml);
              } else {
                $.ajax({
                  url: "<?= URL::BASE_URL ?>SetGroup/updateCell",
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

          $("a.removeItem").on('click', function(e) {
            e.preventDefault();
            var idNya = $(this).attr('id');
            var idItemNya = $(this).attr('data-idItem');
            var valueNya = $(this).attr('data-value');

            $.ajax({
              url: '<?= URL::BASE_URL ?>SetGroup/removeItem',
              data: {
                'id': idNya,
                'id_item': idItemNya,
                'value': valueNya
              },
              type: 'POST',
              success: function() {
                $("#item" + idItemNya).remove();
                location.reload(true);
              },
            });
          });

          $("a.addItem").on('click', function(e) {
            e.preventDefault();
            var idNya = $(this).attr('data-id');
            var valueNya = $(this).attr('data-value');
            $("input#idItem").val(idNya);
            $("input#valueItem").val(valueNya);
          });

          $("a.removeRow").on('click', function(e) {
            e.preventDefault();
            var idNya = $(this).attr('data-id');
            $.ajax({
              url: '<?= URL::BASE_URL ?>SetGroup/removeRow',
              data: {
                'id': idNya
              },
              type: 'POST',
              success: function() {
                location.reload(true);
              },
            });
          });
        </script>