
<table class="table table-sm">
        <tbody>
          <?php
          $no = 0;
          foreach ($data['data_main'] as $a) {
            $no++;
            $id = $a['id_penjualan'];
            $f10 = $a['id_penjualan_jenis'];
            $f3 = $a['id_item_group'];
            $f4 = $a['list_item'];
            $f5 = $a['list_layanan'];
            $f11 = $a['id_durasi'];
            $f6 = $a['qty'];
            $f7 = $a['harga'];
            $f8 = $a['note'];
            $f9 = $a['id_user'];
            $f1 = $a['insertTime'];
            $f12 = $a['hari'];
            $f13 = $a['jam'];
            $f14 = $a['diskon_qty'];
            $f15 = $a['diskon_partner'];
            $f16 = $a['min_order'];

            $kategori = "";
            foreach ($this->itemGroup as $b) {
              if ($b['id_item_group'] == $f3) {
                $kategori = $b['item_kategori'];
              }
            }


            $penjualan = "";
            $satuan = "";
            foreach ($this->dPenjualan as $l) {
              if ($l['id_penjualan_jenis'] == $f10) {
                $penjualan = $l['penjualan_jenis'];
                foreach ($this->dSatuan as $sa) {
                  if ($sa['id_satuan'] == $l['id_satuan']) {
                    $satuan = $sa['nama_satuan'];
                  }
                }
              }
            }

            $show_qty = "";
            $qty_real = 0;
            if ($f6 < $f16) {
              $qty_real = $f16;
              $show_qty = $f6 . $satuan . " (Min. " . $f16 . $satuan . ")";
            } else {
              $qty_real = $f6;
              $show_qty = $f6 . $satuan;
            }

            $kategori = "";
            foreach ($this->itemGroup as $b) {
              if ($b['id_item_group'] == $f3) {
                $kategori = $b['item_kategori'];
              }
            }

            $durasi = "";
            foreach ($this->dDurasi as $b) {
              if ($b['id_durasi'] == $f11) {
                $durasi = "<b>" . strtoupper($b['durasi']) . "</b>";
              }
            }

            $list_layanan = "";
            $arrList_layanan = unserialize($f5);
            foreach ($arrList_layanan as $b) {
              foreach ($this->dLayanan as $c) {
                if ($c['id_layanan'] == $b) {
                  $list_layanan = $list_layanan . " " . $c['layanan'];
                }
              }
            }
            $diskon_qty = $f14;
            $diskon_partner = $f15;

            $show_diskon_qty = "";
            if ($diskon_qty > 0) {
              $show_diskon_qty = $diskon_qty . "%";
            }
            $show_diskon_partner = "";
            if ($diskon_partner > 0) {
              $show_diskon_partner = $diskon_partner . "%";
            }
            $plus = "";
            if ($diskon_qty > 0 && $diskon_partner > 0) {
              $plus = " + ";
            }
            $show_diskon = $show_diskon_qty . $plus . $show_diskon_partner;

            $itemList = "";
            if (strlen($f4) <> 0) {
              $arrItemList = unserialize($f4);
              $arrCount = count($arrItemList);
              if ($arrCount > 0) {
                foreach ($arrItemList as $key => $a) {
                  foreach ($this->dItem as $b) {
                    if ($b['id_item'] == $key) {
                      $itemList = $itemList . "<span id='item" . $id . $key . "' class='badge badge-light text-dark'>" . $b['item'] . "[" . $a . "] <a id='" . $id . "' data-key='" . $key . "' class='text-danger removeItem' href='#'><i class='fas fa-times-circle'></i></a></span> ";
                    }
                  }
                }
              }
            }

            $total = ($f7 * $qty_real) - (($f7 * $qty_real) * ($f14 / 100));

            if (strlen($show_diskon) > 0) {
              $show_total = "<del>" . number_format($f7 * $qty_real) . "</del><br>" . number_format($total);
            } else {
              $show_total = number_format($total);
            }

            echo "<tr class='tr" . $id . "'>";
            echo "<td style='min-width:200px'><b>" . $kategori . "<br>" . $list_layanan . "</b><br>" . $durasi . " (" . $f12 . " Hari " . $f13 . " Jam)<br><b>" . $show_qty . "</b> " .  $show_diskon . "</td>";
            echo "<td class='text-right'>" . $show_total . "</td>";
            echo "<td><a data-id_value='" . $id . "' class='text-danger removeRow' href='#'><i class='fas fa-times-circle'></i></a></td>";
            echo "</tr>";
            echo "<tr class='tr" . $id . "' style='background-color:aliceblue;'>";
            echo "<td colspan='7' class='border-top-0 border-bottom-0 m-0 p-1'><a data-id_group='" . $f3 . "' data-id_penjualan='" . $id . "' class='addItem badge btn-outline-primary' data-bs-toggle='modal' data-bs-target='#exampleModal2' href='#'><i class='fas fa-plus-circle'></i> Item</a> " . $itemList . "</td>";
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
<!-- SCRIPT -->
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
        success: function(res) {
          if (res == 0) {
            $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');
          } else {
            alert(res);
          }
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
</script>