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
            <div class="table-responsive">
              <table class="table table-sm table-bordered table-hover mb-0 w-100">
                <thead class="table-light">
                  <tr>
                    <th class="text-center" style="width:3rem">No</th>
                    <th class="text-center" style="width:4rem">ID</th>
                    <th>Nama</th>
                    <th>Cabang</th>
                    <th>Privilege</th>
                    <th>No. HP</th>
                    <th>Rek. Bank</th>
                    <th class="text-center" style="width:4rem">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 0;
                  $cabangRow = null;
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

                    if ($cabangRow === null || $cabangRow != $f2) {
                      echo "<tr class='table-primary'><td colspan='8' class='text-center'>#" . htmlspecialchars((string) $f2) . " <b>" . htmlspecialchars($f2name) . "</b></td></tr>";
                    }

                    $f3 = $a['id_privilege'];
                    $f3name = "";
                    foreach ($this->dPrivilege as $b) {
                      if ($f3 == $b['id_privilege']) {
                        $f3name = $b['privilege'];
                      }
                    }

                    $bankAccName = isset($a['bank_account_name']) ? (string) $a['bank_account_name'] : '';
                    $bankAccNameEsc = htmlspecialchars($bankAccName, ENT_QUOTES, 'UTF-8');
                    $bankAccNameShow = $bankAccName !== '' ? htmlspecialchars($bankAccName) : '<span class="text-muted">-</span>';

                    $classAdmin = ($f3 != 100) ? "" : "row-disabled";
                    $namaEsc = htmlspecialchars((string) $a['nama_user'], ENT_QUOTES, 'UTF-8');
                    $noHpEsc = htmlspecialchars((string) $a['no_user'], ENT_QUOTES, 'UTF-8');

                    echo "<tr class='" . $classAdmin . " tr" . (int) $id . "'>";
                    echo "<td class='text-center'>" . $no . "</td>";
                    echo "<td class='text-center'>" . (int) $id . "</td>";
                    echo "<td><span data-mode='2' data-id_value='" . (int) $id . "' data-value='" . $namaEsc . "'><b>" . htmlspecialchars($a['nama_user']) . "</b></span></td>";
                    echo "<td><span data-mode='4' data-id_value='" . (int) $id . "' data-value='" . (int) $f2 . "'>" . htmlspecialchars($f2name) . "</span></td>";
                    echo "<td><span data-mode='5' data-id_value='" . (int) $id . "' data-value='" . (int) $f3 . "'>" . htmlspecialchars($f3name) . "</span></td>";
                    echo "<td><span data-mode='6' data-id_value='" . (int) $id . "' data-value='" . $noHpEsc . "'>" . htmlspecialchars((string) $a['no_user']) . "</span></td>";
                    echo "<td><span data-mode='11' data-id_value='" . (int) $id . "' data-value='" . $bankAccNameEsc . "'>" . $bankAccNameShow . "</span></td>";
                    if ($data['z']['mode'] == 'aktif') {
                      echo "<td class='text-center'><a data-id_value='" . (int) $id . "' data-value='0' class='text-danger enable' href='#' title='Nonaktifkan'><i class='fas fa-times-circle'></i></a></td>";
                    } else {
                      echo "<td class='text-center'><a data-id_value='" . (int) $id . "' data-value='1' class='text-success enable' href='#' title='Aktifkan kembali'><i class='fas fa-recycle'></i></a></td>";
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
    $('table.table tbody').on('dblclick', 'span[data-mode]', function() {
      click = click + 1;
      if (click != 1) {
        return;
      }

      var id_value = $(this).attr('data-id_value');
      var value = $(this).attr('data-value');
      var mode = String($(this).attr('data-mode'));
      var value_before = value;
      var span = $(this);

      var valHtml = $(this).html();

      switch (mode) {
        case '1':
        case '2':
        case '6':
        case '7':
        case '10':
        case '11':
          span.empty();
          span.append($('<input>', { type: 'text', id: 'value_' }).val(value));
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
          if (mode === '11' && value_before === '') {
            span.html('<span class="text-muted">-</span>');
          } else {
            span.html(value);
          }
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

    $('table.table tbody').on('click', 'a.enable', function(e) {
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