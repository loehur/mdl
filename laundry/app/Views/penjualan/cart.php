<style>
  #cart .ord-cart-empty {
    padding: 18px 8px;
    text-align: center;
    color: #5a6a7c;
    font-size: 0.8rem;
  }
  #cart .ord-cart-item {
    background: #fff;
    border: 1px solid #D5DEEA;
    border-radius: 12px;
    padding: 10px;
    margin-bottom: 8px;
    box-shadow: 0 4px 12px rgba(36, 48, 65, 0.04);
  }
  #cart .ord-cart-item:last-child { margin-bottom: 0; }
  #cart .ord-cart-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
  }
  #cart .ord-cart-top strong {
    display: block;
    font-size: 0.84rem;
    font-weight: 800;
    color: #1e3a5f;
    letter-spacing: -0.01em;
  }
  #cart .ord-cart-meta {
    display: block;
    margin-top: 2px;
    font-size: 0.72rem;
    color: #5a6a7c;
    line-height: 1.35;
  }
  #cart .ord-cart-price {
    text-align: right;
    white-space: nowrap;
    font-size: 0.82rem;
    font-weight: 800;
    color: #1e3a5f;
    line-height: 1.3;
  }
  #cart .ord-cart-price del {
    display: block;
    color: #8a96a3;
    font-size: 0.7rem;
    font-weight: 600;
  }
  #cart .ord-cart-qty {
    font-size: 0.72rem;
    font-weight: 700;
    color: #3f74d4;
  }
  #cart .ord-cart-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #D5DEEA;
  }
  #cart .ord-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 750;
    text-decoration: none;
    border: 0;
    cursor: pointer;
  }
  #cart .ord-chip--soft {
    background: #d9e6fa;
    color: #2f61bc;
  }
  #cart .ord-chip--warn {
    background: rgba(233, 163, 25, 0.16);
    color: #9A6700;
  }
  #cart .ord-chip--danger {
    background: rgba(231, 111, 81, 0.12);
    color: #C24A30;
    margin-left: auto;
  }
  #cart .ord-item-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
  }
  #cart .ord-item-badges .badge {
    background: #EEF2F7 !important;
    color: #1e3a5f !important;
    font-weight: 650;
    border-radius: 8px;
  }
</style>

<?php
$no = 0;
if (empty($data['data_main'])) {
  echo '<div class="ord-cart-empty">Keranjang masih kosong. Pilih layanan di atas.</div>';
} else {
  foreach ($data['data_main'] as $a) {
    $no++;
    $id = $a['id_penjualan'];
    $f10 = $a['id_penjualan_jenis'];
    $f3 = $a['id_item_group'];
    $f4 = $a['list_item'];
    $f5 = $a['list_layanan'];
    $f11 = $a['id_durasi'];
    $f6 = round((float) $a['qty'], 2);
    $f7 = $a['harga'];
    $f14 = $a['diskon_qty'];
    $f15 = $a['diskon_partner'];
    $f16 = round(isset($a['min_order']) ? (float) $a['min_order'] : 0.0, 2);

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

    $qty_real = 0;
    if ($f6 < $f16) {
      $qty_real = $f16;
      $show_qty = $this->fmtDecMax2($f6) . $satuan . " (Min. " . $this->fmtDecMax2($f16) . $satuan . ")";
    } else {
      $qty_real = $f6;
      $show_qty = $this->fmtDecMax2($f6) . $satuan;
    }

    $durasi = "";
    foreach ($this->dDurasi as $b) {
      if ($b['id_durasi'] == $f11) {
        $durasi = strtoupper($b['durasi']);
      }
    }

    $list_layanan = "";
    $arrList_layanan = unserialize($f5);
    foreach ($arrList_layanan as $b) {
      foreach ($this->dLayanan as $c) {
        if ($c['id_layanan'] == $b) {
          $list_layanan = trim($list_layanan . " " . $c['layanan']);
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
    $plus = ($diskon_qty > 0 && $diskon_partner > 0) ? " + " : "";
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
      if (count($arrItemList) > 0) {
        foreach ($arrItemList as $key => $qtyItem) {
          foreach ($this->dItem as $b) {
            if ($b['id_item'] == $key) {
              $itemList .= "<span id='item" . $id . $key . "' class='badge'>" . htmlspecialchars($b['item']) . "[" . $qtyItem . "] <a id='" . $id . "' data-key='" . $key . "' class='text-danger removeItem' href='#'><i class='fas fa-times-circle'></i></a></span>";
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

    $priceHtml = number_format($total);
    if (strlen($show_diskon) > 0) {
      $priceHtml = "<del>" . number_format($f7 * $qty_real) . "</del>" . number_format($total);
    }
?>
  <div class="ord-cart-item tr<?= $id ?>">
    <div class="ord-cart-top">
      <div style="min-width:0">
        <strong><?= htmlspecialchars($kategori) ?></strong>
        <span class="ord-cart-meta"><?= htmlspecialchars($list_layanan) ?> · <?= htmlspecialchars($durasi) ?></span>
        <span class="ord-cart-qty"><?= $show_qty ?><?= strlen($show_diskon) ? ' · Disc ' . $show_diskon : '' ?></span>
      </div>
      <div class="ord-cart-price"><?= $priceHtml ?></div>
    </div>
    <?php if ($itemList !== '') { ?>
      <div class="ord-item-badges"><?= $itemList ?></div>
    <?php } ?>
    <div class="ord-cart-actions">
      <a href="#" class="ord-chip ord-chip--soft addItem" data-id_group="<?= $f3 ?>" data-id_penjualan="<?= $id ?>" data-bs-toggle="modal" data-bs-target="#exampleModal2">
        <i class="fas fa-plus-circle"></i> Item
      </a>
      <a href="#" class="ord-chip ord-chip--warn setDiskonBtn" data-id_penjualan="<?= $id ?>" data-harga="<?= $f7 ?>" data-harga_diskon="<?= round($harga_diskon_now, 2) ?>" data-bs-toggle="modal" data-bs-target="#modalDiskonHarga">
        <i class="fas fa-percent"></i> Diskon
      </a>
      <a href="#" class="ord-chip ord-chip--danger removeRow" data-id_value="<?= $id ?>">
        <i class="fas fa-trash-alt"></i>
      </a>
    </div>
  </div>
<?php
  }
}
?>

<script>
  $(document).ready(function() {
    var no = <?= (int) $no ?>;
    if (no > 0) {
      $("button#proses").prop('disabled', false);
    } else {
      $("button#proses").prop('disabled', true);
    }

    $("#cart #modalDiskonHarga").remove();
    if ($("#modalDiskonHarga").length && $("#modalDiskonHarga").parent()[0] !== document.body) {
      $("#modalDiskonHarga").appendTo("body");
    }

    $(".removeRow").on("click", function(e) {
      e.preventDefault();
      var id_value = $(this).attr('data-id_value');
      $.ajax({
        url: "<?= URL::BASE_URL ?>Penjualan/RemoveRow",
        data: { 'id': id_value },
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
        data: { 'id': idNya, 'key': keyNya },
        type: 'POST',
        success: function() {
          $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');
        },
      });
    });
  });

  $(document).off("click.setDiskon", ".setDiskonBtn").on("click.setDiskon", ".setDiskonBtn", function(e) {
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

  $(document).off("submit.setDiskon", "#formDiskonHarga").on("submit.setDiskon", "#formDiskonHarga", function(e) {
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
      data: { 'id': id, 'harga_diskon': hargaDiskon },
      type: 'POST',
      success: function(res) {
        if (res == 0) {
          var modalEl = document.getElementById('modalDiskonHarga');
          if (modalEl && modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
          }
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
</script>
