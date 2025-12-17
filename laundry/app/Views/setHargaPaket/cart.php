<div class="col">
  <div class="card">
    <div class="card-body p-0">
      <table id="table_id" class="table table-sm">
        <thead>
          <tr>
            <th>Paket</th>
            <th>Qty</th>
            <th>Rp (A)</th>
            <th>Rp (B)</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = 0;
          foreach ($data['data_main'] as $a) {
            $id = $a['id_harga_paket'];
            $f1 = $a['id_harga'];
            $f2 = $a['qty'];
            $f3 = $a['harga'];
            $f3_b = $a['harga_b'];

            foreach ($this->harga as $h) {
              $kategori = "";
              $layanan = "";
              $durasi = "";

              if ($h['id_harga'] == $f1) {
                foreach (unserialize($h['list_layanan']) as $b) {
                  foreach ($this->dLayanan as $c) {
                    if ($b == $c['id_layanan']) {
                      $layanan = $layanan . " " . $c['layanan'];
                    }
                  }
                }
                foreach ($this->dDurasi as $c) {
                  if ($h['id_durasi'] == $c['id_durasi']) {
                    $durasi = $durasi . " " . $c['durasi'];
                  }
                }

                foreach ($this->itemGroup as $c) {
                  if ($h['id_item_group'] == $c['id_item_group']) {
                    $kategori = $kategori . " " . $c['item_kategori'];
                  }
                }

                foreach ($this->dPenjualan as $dp) {
                  if ($h['id_penjualan_jenis'] == $dp['id_penjualan_jenis']) {
                    foreach ($this->dSatuan as $b) {
                      if ($b['id_satuan'] == $dp['id_satuan']) {
                        $unit = $b['nama_satuan'];
                      }
                    }
                  }
                }
                break;
              }
            }
            $paket = $kategori . " * " . $layanan . " * " . $durasi;

            echo "<tr class='tr" . $id . "'>";
            echo "<td>" . $paket . "</td>";
            echo "<td class='text-right'>" . $f2 . $unit . "</td>";
            echo "<td class='text-right'><span class='cell' data-mode='a' data-id_value='" . $id . "' data-value='" . $f3 . "'>" . $f3 . "</span></td>";
            echo "<td class='text-right'><span class='cell' data-mode='b' data-id_value='" . $id . "' data-value='" . $f3_b . "'>" . $f3_b . "</span></td>";
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.1/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>
<script>
  $(document).ready(function() {
    var no = <?= $no ?>;
    if (no > 0) {
      $("button#proses").prop('disabled', false);
    } else {
      $("button#proses").prop('disabled', true);
    }

    $(".removeRow").on("click", function(e) {
      e.preventDefault();
      var id_value = $(this).attr('data-id_value');
      $.ajax({
        url: "<?= URL::BASE_URL ?>Penjualan/RemoveRow",
        data: {
          'id': id_value,
        },
        type: 'POST',
        success: function(response) {
          $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');
        },
      });
    });

    $(".addItem").on("click", function(e) {
      e.preventDefault();
      var id_group = $(this).attr('data-id_group');
      var id_penjualan = $(this).attr('data-id_penjualan');
      var data = id_group + "|" + id_penjualan;
      $('div.addItemForm').load('<?= URL::BASE_URL ?>Penjualan/addItemForm/' + data);
    });

    $("a.removeItem").on('click', function(e) {
      e.preventDefault();
      var idNya = $(this).attr('id');
      var keyNya = $(this).attr('data-key');

      $.ajax({
        url: '<?= URL::BASE_URL ?>Penjualan/removeItem',
        data: {
          'id': idNya,
          'key': keyNya
        },
        type: 'POST',
        success: function() {
          $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');
        },
      });
    });
  });

  var click = 0;
  $(".cell").on('dblclick', function() {
    click = click + 1;
    if (click != 1) {
      return;
    }

    var id_value = $(this).attr('data-id_value');
    var value = $(this).attr('data-value');
    var value_before = value;
    var mode = $(this).attr('data-mode');
    var span = $(this);

    var valHtml = $(this).html();
    span.html("<input type='number' min='0' id='value_' value='" + value + "'>");

    $("#value_").focus();
    $("#value_").focusout(function() {
      var value_after = $(this).val();
      if (value_after === value_before) {
        span.html(valHtml);
        click = 0;
      } else {
        $.ajax({
          url: '<?= URL::BASE_URL ?>SetHargaPaket/updateCell',
          data: {
            'id': id_value,
            'mode': mode,
            'value': value_after,
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
</script>