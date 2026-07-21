<?php
$idPenjualan = $data[1];
$paket = '';
$unit = '';
foreach ($this->dPenjualan as $a) {
  if ($a['id_penjualan_jenis'] == $idPenjualan) {
    foreach ($this->dSatuan as $b) {
      if ($b['id_satuan'] == $a['id_satuan']) {
        $unit = $b['nama_satuan'];
      }
    }
    $paket = $a['penjualan_jenis'];
  }
}

$id_harga_member = $data[2];
$saldoNya_member = number_format($data[3], 2);

$discHint = '';
foreach ($this->diskon as $f) {
  if ($f['id_penjualan_jenis'] == $idPenjualan) {
    if ($f['qty_disc'] > 0 && $f['disc_qty'] > 0) {
      $discHint = 'Min. ' . $f['qty_disc'] . $unit . ' · Diskon ' . $f['disc_qty'] . '%';
    }
  }
}

$textMax = '';
if ($saldoNya_member > 0) {
  $textMax = 'Saldo: ' . number_format($saldoNya_member, 2) . $unit;
}
?>

<style>
  .ord-fo { font-family: 'fontku', sans-serif; color: #1e3a5f; }
  .ord-fo__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #2f61bc, #3f74d4);
    color: #fff;
  }
  .ord-fo__head h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 0.02em;
  }
  .ord-fo__head small {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    font-weight: 600;
    opacity: 0.88;
  }
  .ord-fo__close {
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: rgba(255,255,255,.15);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .ord-fo__close:hover { background: rgba(255,255,255,.28); }
  .ord-fo__body {
    padding: 14px 16px;
    background: #f4f7fb;
    max-height: min(70vh, 560px);
    overflow-y: auto;
  }
  .ord-fo__label {
    display: block;
    margin: 0 0 5px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #5a6a7c;
  }
  .ord-fo__hint {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    font-weight: 650;
    letter-spacing: 0;
    text-transform: none;
    color: #2f61bc;
  }
  .ord-fo__hint--warn { color: #C24A30; }
  .ord-fo__field { margin-bottom: 10px; }
  .ord-fo__grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr;
    gap: 8px;
  }
  .ord-fo__grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  .ord-fo__grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
  }
  .ord-fo__input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #D5DEEA;
    border-radius: 10px;
    background: #fff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 650;
    color: #1e3a5f;
    outline: none;
  }
  .ord-fo__input:focus {
    border-color: #3f74d4;
    box-shadow: 0 0 0 3px rgba(63, 116, 212, 0.2);
  }
  .ord-fo__input--qty {
    background: #edf3fc;
    font-weight: 800;
    color: #2f61bc;
  }
  .ord-fo__input--total {
    background: #eef7f1;
    font-weight: 800;
    color: #1f7a4d;
    text-align: center;
  }
  .ord-fo__input--ro {
    text-align: center;
    background: #f8fafc;
  }
  .ord-fo .selectize-input {
    border: 1px solid #D5DEEA !important;
    border-radius: 10px !important;
    box-shadow: none !important;
    min-height: 40px;
    padding: 8px 10px !important;
    font-size: 13px !important;
    font-weight: 650;
  }
  .ord-fo .selectize-input.focus {
    border-color: #3f74d4 !important;
    box-shadow: 0 0 0 3px rgba(63, 116, 212, 0.2) !important;
  }
  .ord-fo .selectize-dropdown {
    border-color: #D5DEEA !important;
    border-radius: 10px !important;
    z-index: 5100 !important;
  }
  .ord-fo__foot {
    display: grid;
    grid-template-columns: 1fr 1.4fr;
    gap: 8px;
    padding: 12px 16px 16px;
    background: #f4f7fb;
    border-top: 1px solid #D5DEEA;
  }
  .ord-fo__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 0;
    border-radius: 11px;
    padding: 11px 14px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
  }
  .ord-fo__btn--ghost {
    background: #e8eef6;
    color: #5a6a7c;
  }
  .ord-fo__btn--primary {
    background: linear-gradient(145deg, #2f61bc, #3f74d4);
    color: #fff;
    box-shadow: 0 8px 18px rgba(47, 97, 188, 0.28);
  }
  @media (max-width: 480px) {
    .ord-fo__grid,
    .ord-fo__grid-4 { grid-template-columns: 1fr 1fr; }
  }
</style>

<form class="addOrder ord-fo" action="<?= URL::BASE_URL ?>Penjualan/insert/<?= $idPenjualan ?>" method="POST">
  <div class="ord-fo__head">
    <div>
      <h3>Laundry <?= htmlspecialchars($paket) ?></h3>
      <small><?= $discHint !== '' ? htmlspecialchars($discHint) : 'Pilih layanan & isi quantity' ?></small>
    </div>
    <button type="button" class="ord-fo__close" data-ord-order-close aria-label="Tutup">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <div class="ord-fo__body">
    <div class="ord-fo__field">
      <label class="ord-fo__label">Jenis layanan</label>
      <select name="f1" class="order form-control w-100 2tize" id="kiloan" required>
        <?php foreach ($this->harga as $a) {
          $kategori = "";
          $layanan = "";
          $durasi = "";
          if ($a['id_penjualan_jenis'] == $idPenjualan) {
            foreach (unserialize($a['list_layanan']) as $b) {
              foreach ($this->dLayanan as $c) {
                if ($b == $c['id_layanan']) {
                  $layanan = $layanan . " " . $c['layanan'];
                }
              }
            }
            foreach ($this->dDurasi as $c) {
              if ($a['id_durasi'] == $c['id_durasi']) {
                $durasi = $durasi . " " . $c['durasi'];
              }
            }
            foreach ($this->itemGroup as $c) {
              if ($a['id_item_group'] == $c['id_item_group']) {
                $kategori = $kategori . " " . $c['item_kategori'];
              }
            }
            if ($this->mdl_setting['def_price'] == 0) {
              $harga = $a['harga'];
            } else {
              $harga = $a['harga_b'];
              if ($harga == 0) {
                $harga = $a['harga'];
              }
            }
        ?>
            <option id="op<?= $a['id_harga'] ?>" data-harga="<?= $harga ?>" value="<?= $a['id_harga'] ?>"><?= $kategori ?> - <?= $layanan ?> - <?= $durasi ?> - <?= $harga ?></option>
        <?php }
        } ?>
      </select>
    </div>

    <div class="ord-fo__grid">
      <div class="ord-fo__field">
        <label class="ord-fo__label">
          Quantity (<?= $unit ?>)
          <?php if ($textMax !== '') { ?>
            <span class="ord-fo__hint ord-fo__hint--warn"><?= htmlspecialchars($textMax) ?></span>
          <?php } ?>
        </label>
        <input type="number" step="0.01" name="f2" class="ord-fo__input ord-fo__input--qty float" id="qtyNya" required>
      </div>
      <div class="ord-fo__field">
        <label class="ord-fo__label">Harga /<?= $unit ?></label>
        <input id="harga" class="ord-fo__input ord-fo__input--ro" readonly>
      </div>
      <div class="ord-fo__field">
        <label class="ord-fo__label">Total (Rp)</label>
        <input id="total_harga" class="ord-fo__input ord-fo__input--total" readonly>
      </div>
    </div>

    <?php if ($unit == "m<sup>2</sup>") { ?>
      <div class="ord-fo__grid-2">
        <div class="ord-fo__field">
          <label class="ord-fo__label">Pengali 1</label>
          <input type="number" step="0.01" class="ord-fo__input float bkali" id="bkali1">
        </div>
        <div class="ord-fo__field">
          <label class="ord-fo__label">Pengali 2</label>
          <input type="number" step="0.01" class="ord-fo__input float bkali" id="bkali2">
        </div>
      </div>
    <?php } ?>

    <?php if ($unit == "kg") { ?>
      <div class="ord-fo__grid-4">
        <div class="ord-fo__field">
          <label class="ord-fo__label">Timbang 1</label>
          <input type="number" step="0.01" class="ord-fo__input float timb" id="timb1">
        </div>
        <div class="ord-fo__field">
          <label class="ord-fo__label">Timbang 2</label>
          <input type="number" step="0.01" class="ord-fo__input float timb" id="timb2">
        </div>
        <div class="ord-fo__field">
          <label class="ord-fo__label">Timbang 3</label>
          <input type="number" step="0.01" class="ord-fo__input float timb" id="timb3">
        </div>
        <div class="ord-fo__field">
          <label class="ord-fo__label">Timbang 4</label>
          <input type="number" step="0.01" class="ord-fo__input float timb" id="timb4">
        </div>
      </div>
    <?php } ?>

    <div class="ord-fo__field" style="margin-bottom:0">
      <label class="ord-fo__label">Catatan (opsional)</label>
      <input type="text" name="f3" class="ord-fo__input" placeholder="Catatan order…">
    </div>
  </div>

  <div class="ord-fo__foot">
    <button type="button" class="ord-fo__btn ord-fo__btn--ghost" data-ord-order-close>Batal</button>
    <button type="submit" class="ord-fo__btn ord-fo__btn--primary">
      <i class="fas fa-plus"></i> Tambah ke keranjang
    </button>
  </div>
</form>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
  $(document).ready(function() {
    selectMember(<?= (int) $id_harga_member ?>, <?= (float) $saldoNya_member ?>);

    $(".2tize").selectize({
      sortField: [
        { field: "$order", direction: "asc" },
        { field: "$score", direction: "desc" }
      ]
    });

    $("form.addOrder").on("submit", function(e) {
      $("select.order[name=f1]").removeAttr('disabled');
      e.preventDefault();
      $.ajax({
        url: $(this).attr('action'),
        data: $(this).serialize(),
        type: $(this).attr("method"),
        success: function(res) {
          if (res != 0) {
            alert(res);
          } else {
            if (typeof window.closeOrdOrderModal === 'function') {
              window.closeOrdOrderModal();
            }
            if (typeof hide_modal === 'function') {
              hide_modal();
            }
            $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');
          }
        },
      });
    });

    $('.float').keypress(function(event) {
      if ((event.which != 46 || $(this).val().indexOf('.') != -1) && (event.which < 48 || event.which > 57)) {
        event.preventDefault();
      }
    });

    $('select#kiloan').change(function() {
      harga();
      updateTotal();
    });

    harga();
  });

  $("input#qtyNya").keyup(function() {
    updateTotal();
  });

  function qtyFmtMax2(v) {
    v = Math.round(parseFloat(v) * 100) / 100;
    if (isNaN(v)) return '';
    var s = v.toFixed(2);
    if (s.indexOf('.') >= 0) {
      s = s.replace(/0+$/, '');
      s = s.replace(/\.$/, '');
    }
    return s;
  }

  function updateTotal() {
    var qty = $("input#qtyNya").val();
    var harga = $("input#harga").val();
    var total = parseInt(parseFloat(qty) * parseInt(harga));
    total = total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    if (total != 'NaN') {
      $("input#total_harga").val(total);
    } else {
      $("input#total_harga").val('');
    }
  }

  function selectMember(id_harga, saldoMember) {
    if (id_harga > 0) {
      $("select.order[name=f1] option[value=" + id_harga + "]").attr('selected', 'selected');
      $("select.order[name=f1] option[value=" + id_harga + "]").prop('selected', 'selected');
      $("select.order[name=f1]").attr('disabled', 'true');
      $("select.order[name=f1]").prop('disabled', 'true');
      if (saldoMember > 0) {
        $("input[name=f2]").attr("max", saldoMember);
        $("input[name=f2]").prop("max", saldoMember);
      }
    } else {
      $("select.order[name=f1]").removeAttr('disabled');
    }
  }

  function harga() {
    var id = $("select#kiloan").val();
    var harga = $('option#op' + id).attr('data-harga');
    $('input#harga').val(harga);
  }

  $("input.timb").on("keyup change", function() {
    var t1 = $("#timb1").val() || 0;
    var t2 = $("#timb2").val() || 0;
    var t3 = $("#timb3").val() || 0;
    var t4 = $("#timb4").val() || 0;
    var total = parseFloat(t1) + parseFloat(t2) + parseFloat(t3) + parseFloat(t4);
    $("input#qtyNya").val(qtyFmtMax2(total));
    updateTotal();
  });

  $("input.bkali").on("keyup change", function() {
    var t1 = $("#bkali1").val() || 0;
    var t2 = $("#bkali2").val() || 0;
    var total = parseFloat(t1) * parseFloat(t2);
    $("input#qtyNya").val(qtyFmtMax2(total));
    updateTotal();
  });
</script>
