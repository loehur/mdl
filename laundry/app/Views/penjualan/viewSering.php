<?php
$no = 0;
foreach ($data['data'] as $a) {
  $kategori = "";
  $layanan = "";
  $durasi = "";

  $id = $a['id_harga'];

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
  $no++;
?>
  <div class="pt-1 text-sm" style="white-space: nowrap;">#<?= $no ?> <?= $jenis ?>, <?= $kategori ?>, <?= $layanan ?>, <?= $durasi ?> <span class="m-1" style="white-space: pre; cursor: pointer;" id="pilih_sering" data-id_penjualan="<?= $id_penjualan ?>" data-id_harga="<?= $id ?>"><a href="#" class="border pr-1 pl-1 rounded">Pilih</a></span>
  </div>
<?php
} ?>

<script>
  $("span#pilih_sering").click(function(e) {
    e.preventDefault();
    var id_harga = $(this).attr("data-id_harga");
    var id_penjualan = $(this).attr('data-id_penjualan');
    var saldo = 0;
    $('div.orderPenjualanForm').load('<?= URL::BASE_URL ?>Penjualan/orderPenjualanForm/' + id_penjualan + '/' + id_harga + "/" + saldo, function() {
      // Trigger modal after content is loaded
      var modalEl = document.querySelector('#modalPenjualan');
      if(modalEl) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }
    });
  })
</script>