<table class="table table-sm">
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
              $show_diskon_qty = rtrim(rtrim(number_format($diskon_qty, 2, '.', ''), '0'), '.') . "%";
            }
            $show_diskon_partner = "";
            if ($diskon_partner > 0) {
              $show_diskon_partner = rtrim(rtrim(number_format($diskon_partner, 2, '.', ''), '0'), '.') . "%";
            }
            $plus = "";
            if ($diskon_qty > 0 && $diskon_partner > 0) {
              $plus = " + ";
            }
            $show_diskon = $show_diskon_qty . $plus . $show_diskon_partner;
            $harga_diskon_now = $f7;
            if ($diskon_partner > 0) {
              $harga_diskon_now = $f7 - ($f7 * ($diskon_partner / 100));
            } elseif ($diskon_qty > 0) {
              $harga_diskon_now = $f7 - ($f7 * ($diskon_qty / 100));
            }

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

            $total = $f7 * $qty_real;
            if ($diskon_qty > 0) {
              $total = $total - ($total * ($diskon_qty / 100));
            }
            if ($diskon_partner > 0) {
              $total = $total - ($total * ($diskon_partner / 100));
            }

            if (strlen($show_diskon) > 0) {
              $show_total = "<del>" . number_format($f7 * $qty_real) . "</del><br>" . number_format($total);
            } else {
              $show_total = number_format($total);
            }

            echo "<tr class='tr" . $id . "'>";
            echo "<td style='min-width:200px'>" . $kategori . "<br><span class='fw-bold'>" . $list_layanan . "</span> | " . $durasi . "</td>";
            echo "<td class='text-right'>" . $show_total . "<br><b>" . $show_qty . "</b> " .  $show_diskon . "</td>";
            echo "<td class='text-end'><a href='#' class='badge btn-outline-warning setDiskonBtn mb-1' data-id_penjualan='" . $id . "' data-harga='" . $f7 . "' data-harga_diskon='" . round($harga_diskon_now, 2) . "' data-bs-toggle='modal' data-bs-target='#modalDiskonHarga'><i class='fas fa-percent'></i> Diskon</a><br><a data-id_value='" . $id . "' class='text-danger removeRow' href='#'><i class='fas fa-times-circle'></i></a></td>";
            echo "</tr>";
            echo "<tr class='tr" . $id . "' style='background-color:aliceblue;'>";
            echo "<td colspan='7' class='d-none border-top-0 border-bottom-0 m-0 p-1'><a data-id_group='" . $f3 . "' data-id_penjualan='" . $id . "' class='addItem badge btn-outline-primary' data-bs-toggle='modal' data-bs-target='#exampleModal2' href='#'><i class='fas fa-plus-circle'></i> Item</a> " . $itemList . "</td>";
            echo "</tr>";
          }
          ?>
      </table>

<div class="modal fade" id="modalDiskonHarga" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title m-0">Atur Diskon</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formDiskonHarga">
        <div class="modal-body">
          <input type="hidden" id="diskon_id_penjualan" name="id" value="">
          <input type="hidden" id="diskon_harga_asli" value="">
          <div class="mb-2">
            <label class="form-label form-label-sm mb-1">Harga Asli / item-kg-unit</label>
            <input type="text" id="diskon_harga_asli_view" class="form-control form-control-sm" readonly>
          </div>
          <div>
            <label class="form-label form-label-sm mb-1">Harga Setelah Diskon / item-kg-unit</label>
            <input type="number" min="0" step="0.01" id="diskon_harga_input" name="harga_diskon" class="form-control form-control-sm" required>
          </div>
          <small class="text-muted">Harga asli tidak diubah, sistem hanya mengisi nilai diskon.</small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>
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

    $(".setDiskonBtn").on("click", function(e) {
      e.preventDefault();
      var id = $(this).attr('data-id_penjualan');
      var harga = parseFloat($(this).attr('data-harga'));
      var hargaDiskon = parseFloat($(this).attr('data-harga_diskon'));
      if (isNaN(hargaDiskon) || hargaDiskon <= 0) {
        hargaDiskon = harga;
      }

      $("#diskon_id_penjualan").val(id);
      $("#diskon_harga_asli").val(harga);
      $("#diskon_harga_asli_view").val(harga.toLocaleString('id-ID'));
      $("#diskon_harga_input").val(hargaDiskon);
    });

    $("#formDiskonHarga").on("submit", function(e) {
      e.preventDefault();
      var id = $("#diskon_id_penjualan").val();
      var hargaDiskon = parseFloat($("#diskon_harga_input").val());
      var hargaAsli = parseFloat($("#diskon_harga_asli").val());

      if (isNaN(hargaDiskon) || hargaDiskon < 0) {
        alert("Harga diskon tidak valid");
        return;
      }

      if (!isNaN(hargaAsli) && hargaDiskon > hargaAsli) {
        alert("Harga diskon tidak boleh lebih besar dari harga asli");
        return;
      }

      $.ajax({
        url: "<?= URL::BASE_URL ?>Penjualan/setDiskonHarga",
        data: {
          'id': id,
          'harga_diskon': hargaDiskon
        },
        type: 'POST',
        success: function(res) {
          if (res == 0) {
            var modalEl = document.getElementById('modalDiskonHarga');
            var modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) {
              modalInstance.hide();
            }
            $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');
          } else {
            alert(res);
          }
        },
      });
    });
  });
</script>