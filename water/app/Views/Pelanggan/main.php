<div class="row p-1 m-1">
  <div class="col pr-0 pl-0">
    <div class="p-1">
      <form action="<?= URL::BASE_URL; ?>Pelanggan/insert" method="POST">
        <div class="row">
          <div class="col">
            <input type="text" id="search" name="f1" class="form-control" placeholder="Nama Pelanggan" required>
          </div>
          <div class="col">
            <input type="text" id="no_hp" name="f2" class="form-control" placeholder="Nomor HP" required>
          </div>
        </div>
        <div class="row mt-2">
          <div class="col">
            <button type="submit" class="btn btn-primary w-100">Tambah</button>
          </div>
        </div>
      </form>
    </div>
    <div class="p-1 mb-1" style="height: 500px; overflow-y:scroll">
      <table id="pelanggan" class="table table-sm w-100">
        <tbody>
          <?php
          $no = 0;
          foreach ($data as $a) {
            $id = $a['id'];
            $f1 = $a['nama'];
            $f2 = $a['hp'];
            $f4 = $a['alamat'];
            $f5 = $a['disc'];
            $no++;

            if ($f1 == "") {
              $f1 = "[ ]";
            }

            if ($f2 == '') {
              $f2 = '08';
            }

            if ($f4 == '') {
              $f4 = '[ ]';
            }
            echo "<tr>";
            echo "<td><small>" . $id . "</small><br><span data-mode='1' data-id_value='" . $id . "' data-value='" . $f1 . "'>" . strtoupper($f1) . "</span></td>";
            echo "<td nowrap><span data-mode='2' data-id_value='" . $id . "' data-value='" . $f2 . "'>" . $f2 . "</span><br>";
            if ($this->id_privilege == 100) {
              echo "Diskon <span data-mode='5' data-id_value='" . $id . "' data-value='" . $f5 . "'>" . $f5 . "</span>%";
            } else {
              echo "Diskon " . $f5 . "%";
            };
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  $("form").on("submit", function(e) {
    e.preventDefault();
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      success: function(response) {
        if (response == 1) {
          location.reload(true);
        } else {
          alert(response);
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
      case '4':
        span.html("<input type='text' id='value_' value='" + value + "'>");
        break;
      case '5':
        span.html("<input type='number' style='width:50px' id='value_' value='" + value + "'>");
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
        if (value_after.length == 0) {
          span.html(value);
          click = 0;
        } else {
          $.ajax({
            url: '<?= URL::BASE_URL ?>Pelanggan/updateCell',
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
      }
    });
  });

  $("input#search").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("table#pelanggan tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });

  $("input#no_hp").on("keyup", function() {
    var value_ = $(this).val().toLowerCase();
    var value = value_.substring(value_.length - 8);
    $("table#pelanggan tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
</script>