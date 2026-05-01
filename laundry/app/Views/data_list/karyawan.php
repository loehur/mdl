<?php $page = $data['z']['page']; ?>

<div class="row p-1 m-1 border rounded bg-white">
  <div class="col pr-0 pl-0">
    <div class="p-1 mb-1" style="height: 400px; overflow-y:scroll">
      <table class="table table-sm w-100">
        <thead>
          <th>Nama</th>
          <th>Mac Address</th>
          <th>Mac Address 2</th>
        </thead>
        <tbody>
          <?php
          $no = 0;
          foreach ($data['data_main'] as $a) {
            $id = $a['id_user'];
            $f1 = $a['nama_user'];
            $f2 = $a['mac'];
            $f3 = $a['mac_2'];
            $no++;

            if ($f2 == '') {
              $f2 = '[ ]';
            }

            if ($f3 == '') {
              $f3 = '[ ]';
            }

            echo "<tr>";
            echo "<td>" . strtoupper($f1) . "</td>";
            if ($f2 == "[ ]") {
              echo "<td nowrap><span data-mode='2' data-id_value='" . $id . "' data-value='" . $f2 . "'>" . $f2 . "</span>";
            } else {
              echo "<td nowrap>" . $f2 . "</span>";
            }

            if ($f3 == "[ ]") {
              echo "<td nowrap><span data-mode='3' data-id_value='" . $id . "' data-value='" . $f3 . "'>" . $f3 . "</span>";
            } else {
              echo "<td nowrap>" . $f3 . "</span>";
            }
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<script>
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
      case '2':
      case '3':
        if (value == '[ ]') {
          span.html("<input type='text' id='value_' value=''>");
        } else {
          span.html("<input type='text' id='value_' value='" + value + "'>");
        }
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
            url: '<?= URL::BASE_URL ?>Data_List/updateCell/<?= $page ?>',
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
</script>