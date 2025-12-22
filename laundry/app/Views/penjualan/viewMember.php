<label class="form-label text-primary px-2 mt-2"><b>Saldo Member</b> <small>(Otomatis terpotong jika saldo cukup)</small>
</label>
<table class="table m-0 table-sm" style="width: 100%;">
  <?php
  foreach ($data['data'] as $z) {
    $id = $z['id_harga'];
    $kategori = "";
    $layanan = "";
    $durasi = "";
    $unit = "";
    if ($z['saldo'] > 0) {
      foreach ($this->harga as $a) {
        $jenis = "";
        if ($a['id_harga'] == $id) {
          foreach ($this->dPenjualan as $dp) {
            if ($dp['id_penjualan_jenis'] == $a['id_penjualan_jenis']) {
              $id_penjualan = $a['id_penjualan_jenis'];
              $jenis = $dp['penjualan_jenis'];
              foreach ($this->dSatuan as $ds) {
                if ($ds['id_satuan'] == $dp['id_satuan']) {
                  $unit = $ds['nama_satuan'];
                }
              }
            }
          }
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

          $saldoAwal = $z['saldo'];
          $saldoAkhir = $saldoAwal - $data['pakai'][$id];

          if ($saldoAkhir > 1) {
  ?>
            <tr>
              <td>Paket: <b>M<?= $id ?></b> | Saldo: <b class="text-success"><?= number_format($saldoAkhir, 2) . $unit ?></b>
                <br></b> <span class="text-dark"><b><?= $jenis ?></b></span> <?= $kategori ?> * <?= $layanan ?> * <?= $durasi ?>
              </td>
              <td class="text-end"><span id="pakai" data-saldo="<?= $saldoAkhir ?>" data-id_penjualan="<?= $id_penjualan ?>" data-id_harga="<?= $id ?>" class="btn btn-sm btn-danger" data-bs-target="#modalPenjualan">Pakai</span></td>
            </tr>
      <?php
          }
        }
      }
    }
  } ?>
</table>

<script>
  $("span#pakai").click(function() {
    var id_harga = $(this).attr("data-id_harga");
    var id_penjualan = $(this).attr('data-id_penjualan');
    var saldo = $(this).attr('data-saldo');
    $('div.orderPenjualanForm').load('<?= URL::BASE_URL ?>Penjualan/orderPenjualanForm/' + id_penjualan + '/' + id_harga + '/' + saldo);

    // Manual modal trigger
    var target = $(this).attr('data-bs-target');
    if(target) {
        var modalEl = document.querySelector(target);
        if(modalEl) {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }
  })

  $('span#pakai').each(function() {
    var elem = $(this);
    elem.fadeOut(150)
      .fadeIn(150)
      .fadeOut(150)
      .fadeIn(150)
  });
</script>