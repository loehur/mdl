<?php
$no = 0;
foreach ($data['data'] as $a) {
  $kategori = "";
  $layanan = "";
  $durasi = "";
  $jenis = "";
  $id_penjualan = "";

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
  $label = trim($jenis . ' ·' . $kategori . ' ·' . $layanan . ' ·' . $durasi);
?>
  <div class="ord-sering-item">
    <span>#<?= $no ?> <?= htmlspecialchars($label) ?></span>
    <span class="pilih-sering" data-id_penjualan="<?= $id_penjualan ?>" data-id_harga="<?= $id ?>">
      <a href="#">Pilih</a>
    </span>
  </div>
<?php
} ?>

<script>
  $("#sering").off("click.sering", ".pilih-sering").on("click.sering", ".pilih-sering", function (e) {
    e.preventDefault();
    if (typeof window.blockDriverNewOrder === "function" && window.blockDriverNewOrder()) {
      return;
    }
    var id_harga = $(this).attr("data-id_harga");
    var id_penjualan = $(this).attr("data-id_penjualan");
    var saldo = 0;
    if (typeof window.openOrdOrderModal === "function") {
      window.openOrdOrderModal();
    }
    $("div.orderPenjualanForm").html('<div style="padding:28px;text-align:center;color:#5a6a7c"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>');
    $("div.orderPenjualanForm").load(
      "<?= URL::BASE_URL ?>Penjualan/orderPenjualanForm/" + id_penjualan + "/" + id_harga + "/" + saldo
    );
  });
</script>
