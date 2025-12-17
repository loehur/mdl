<style>
  .row-disabled {
    background-color: rgba(236, 240, 241, 0.5);
    pointer-events: none;
    width: 100%;
  }
</style>
<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <div class="card">
          <div class="card-header">
            <button type="button" class="btn btn-sm btn-primary float-right" data-bs-toggle="modal" data-bs-target="#exampleModal">
              Tambah Karyawan
            </button>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm" style="max-width: 450px;">
              <tbody>
                <?php
                $no = 0;
                foreach ($data['data_main'] as $a) {
                  $no++;

                  $id = $a['id_user'];
                  $f2 = $a['id_cabang'];
                  $f2name = "";
                  foreach ($data['d2'] as $b) {
                    if ($f2 == $b['id_cabang']) {
                      $f2name = $b['kode_cabang'];
                    }
                  }

                  if (!isset($cabangRow)) {
                    echo "<tr class='table-primary'><td colspan=11 class='text-center'>#" . $f2 . " <b>" . $f2name . "</b></td></tr>";
                  }
                  if (isset($cabangRow) && $cabangRow <> $f2) {
                    echo "<tr class='table-primary'><td colspan=11 class='text-center'>#" . $f2 . " <b>" . $f2name . "</b></td></tr>";
                  }

                  $f3 = $a['id_privilege'];
                  $f3name = "";
                  foreach ($this->dPrivilege as $b) {
                    if ($f3 == $b['id_privilege']) {
                      $f3name = $b['privilege'];
                    }
                  }

                  if ($f3 <> 100) {
                    $classAdmin = "";
                  } else {
                    $classAdmin = "row-disabled";
                  }

                  echo "<tr class='" . $classAdmin . "'>";
                  echo "<td>";
                  echo "<span data-mode=2 data-id_value='" . $id . "' data-value='" . $a['nama_user'] . "'><b>" . $a['nama_user'] . "</b></span><br><span data-mode=5 data-id_value='" . $id . "' data-value='" . $f3name . "'>" . $f3name . "</span>";
                  echo "</td>";
                  echo "<td>" . $no . "#" . $id . " <span data-mode=4 data-id_value='" . $id . "' data-value='" . $f2name . "'>" . $f2name . "</span><br><span data-mode=6 data-id_value='" . $id . "' data-value='" . $a['no_user'] . "'>" . $a['no_user'] . "</span></td>";
                  echo "<td class='text-right'>";
                  echo " ";
                  echo "</td>";
                  if ($data['z']['mode'] == 'aktif') {
                    echo "<td><a data-id_value='" . $id . "' data-value='0' class='text-danger enable' href='#'><i class='fas fa-times-circle'></i></a></td>";
                  } else {
                    echo "<td><a data-id_value='" . $id . "' data-value='1' class='text-success enable' href='#'><i class='fas fa-recycle'></i></a></td>";
                  }
                  echo "</tr>";
                  $cabangRow = $f2;
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Penambahan Karyawan</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <!-- ====================== FORM ========================= -->
        <form action="<?= URL::BASE_URL; ?>Data_List/insert/user" method="POST">
          <div class="card-body">
            <div class="form-group">
              <div class="row">
                <div class="col">
                  <label for="exampleInputEmail1">Nama Karyawan</label>
                  <input type="text" name="f1" class="form-control" id="exampleInputEmail1" placeholder="" required>
                </div>
                <div class="col">
                  <label for="exampleInputEmail1">Cabang</label>
                  <select name="f3" class="form-control" required>
                    <option value="" disabled selected>---</option>
                    <?php foreach ($data['d2'] as $a) { ?>
                      <option value="<?= $a['id_cabang'] ?>"><?= $a['kode_cabang'] ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="row">
                <div class="col">
                  <label for="exampleInputEmail1">Nomor HP</label>
                  <input type="text" name="f2" class="form-control" id="exampleInputEmail1" placeholder="" required>
                </div>
                <div class="col">
                  <label for="exampleInputEmail1">Privilege</label>
                  <select name="f4" class="form-control" required>
                    <option value="" disabled selected>---</option>
                    <?php foreach ($this->dPrivilege as $a) {
                      if ($a['id_privilege'] <> 100) { ?>
                        <option value="<?= $a['id_privilege'] ?>"><?= $a['privilege'] ?></option>
                    <?php }
                    } ?>
                  </select>
                </div>
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

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>

<script>
  $(document).ready(function() {

    $('.selectMulti').select2({
      theme: "classic"
    });

    $("form").on("submit", function(e) {
      e.preventDefault();
      $.ajax({
        url: $(this).attr('action'),
        data: $(this).serialize(),
        type: $(this).attr("method"),
        success: function(res) {
          if (res == 0) {
            location.reload(true);
          } else {
            alert(res);
          }
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

      switch (mode) {
        case '1':
        case '2':
        case '6':
        case '7':
        case '10':
          span.html("<input type='text' id='value_' value='" + value + "'>");
          break;
        case '4':
          span.html('<select id="value_"><option value="' + value + '" selected>' + valHtml + '</option><?php foreach ($data['d2'] as $a) { ?><option value="<?= $a['id_cabang'] ?>"><?= $a['kode_cabang'] ?></option><?php } ?></select>');
          break;
        case '5':
          span.html(
            '<select id="value_"><option value="' + value + '" selected>' + valHtml + '</option><?php foreach ($this->dPrivilege as $a) :  if ($a['id_privilege'] <> 100) { ?><option value="<?= $a['id_privilege'] ?>"><?= $a['privilege'] ?></option><?php }
                                                                                                                                                                                                                                                    endforeach ?></select>'
          );
          break;
        default:
      }

      $("#value_").focus();
      $("#value_").focusout(function() {
        var value_after = $(this).val();
        if (value_after === value_before) {
          span.html(value);
          click = 0;
        } else {
          $.ajax({
            url: '<?= URL::BASE_URL ?>Data_List/updateCell/user',
            data: {
              'id': id_value,
              'value': value_after,
              'mode': mode
            },
            type: 'POST',
            dataType: 'html',
            success: function(response) {
              if (response == 0) {
                location.reload(true);
              } else {
                alert(response);
              }
            },
          });
        }
      });
    });

    $(".enable").on("click", function(e) {
      e.preventDefault();
      var id_value = $(this).attr('data-id_value');
      var value = $(this).attr('data-value');
      $.ajax({
        url: "<?= URL::BASE_URL ?>Data_List/enable/" + value,
        data: {
          'id': id_value,
        },
        type: 'POST',
        success: function(response) {
          $('tr.tr' + id_value).remove();
          location.reload(true);
        },
      });
    });
  });
</script>