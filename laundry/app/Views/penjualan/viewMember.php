<style>
  #saldoMember .ord-member {
    background: #fff;
    border: 1px solid #D5DEEA;
    border-radius: 16px;
    padding: 12px;
    margin-bottom: 10px;
    box-shadow: 0 8px 22px rgba(36, 48, 65, 0.05);
  }
  #saldoMember .ord-member-title {
    margin: 0 0 10px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #5a6a7c;
  }
  #saldoMember .ord-member-title small {
    display: block;
    margin-top: 3px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0;
    text-transform: none;
    color: #3f74d4;
  }
  #saldoMember .ord-member-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  #saldoMember .ord-member-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px;
    border: 1px solid #D5DEEA;
    border-radius: 12px;
    background: linear-gradient(180deg, #f7faff 0%, #fff 100%);
  }
  #saldoMember .ord-member-item strong {
    display: block;
    font-size: 0.84rem;
    font-weight: 800;
    color: #1e3a5f;
    letter-spacing: -0.01em;
  }
  #saldoMember .ord-member-saldo {
    color: #2f61bc;
    font-weight: 800;
  }
  #saldoMember .ord-member-meta {
    display: block;
    margin-top: 3px;
    font-size: 0.74rem;
    color: #5a6a7c;
    line-height: 1.35;
  }
  #saldoMember .ord-pakai-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-width: 72px;
    padding: 7px 12px;
    border: 0;
    border-radius: 999px;
    background: linear-gradient(145deg, #2f61bc 0%, #3f74d4 100%);
    color: #fff;
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(47, 97, 188, 0.25);
    transition: transform .12s ease, opacity .12s ease;
  }
  #saldoMember .ord-pakai-btn:active {
    transform: scale(0.97);
    opacity: 0.9;
  }
</style>

<?php
$items = [];
foreach ($data['data'] as $z) {
  $id = $z['id_harga'];
  if (!($z['saldo'] > 0)) {
    continue;
  }
  foreach ($this->harga as $a) {
    if ($a['id_harga'] != $id) {
      continue;
    }
    $kategori = "";
    $layanan = "";
    $durasi = "";
    $unit = "";
    $jenis = "";
    $id_penjualan = "";

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
      $items[] = [
        'id' => $id,
        'id_penjualan' => $id_penjualan,
        'saldo' => $saldoAkhir,
        'unit' => $unit,
        'jenis' => $jenis,
        'meta' => trim($kategori . ' · ' . $layanan . ' · ' . $durasi),
      ];
    }
  }
}

if (count($items) === 0) {
  return;
}
?>

<div class="ord-member">
  <p class="ord-member-title">
    Saldo Member
    <small>Otomatis terpotong jika saldo cukup</small>
  </p>
  <div class="ord-member-list">
    <?php foreach ($items as $it) { ?>
      <div class="ord-member-item">
        <div style="min-width:0">
          <strong>
            Paket M<?= (int) $it['id'] ?>
            · Saldo <span class="ord-member-saldo"><?= number_format($it['saldo'], 2) . $it['unit'] ?></span>
          </strong>
          <span class="ord-member-meta">
            <b><?= htmlspecialchars($it['jenis']) ?></b>
            <?= htmlspecialchars($it['meta']) ?>
          </span>
        </div>
        <button
          type="button"
          class="ord-pakai-btn pakai-btn"
          data-saldo="<?= htmlspecialchars((string) $it['saldo'], ENT_QUOTES, 'UTF-8') ?>"
          data-id_penjualan="<?= (int) $it['id_penjualan'] ?>"
          data-id_harga="<?= (int) $it['id'] ?>"
        >
          <i class="fas fa-bolt"></i> Pakai
        </button>
      </div>
    <?php } ?>
  </div>
</div>

<script>
  $("#saldoMember").off("click.pakai", ".pakai-btn").on("click.pakai", ".pakai-btn", function () {
    var id_harga = $(this).attr("data-id_harga");
    var id_penjualan = $(this).attr("data-id_penjualan");
    var saldo = $(this).attr("data-saldo");
    if (typeof window.openOrdOrderModal === "function") {
      window.openOrdOrderModal();
    }
    $("div.orderPenjualanForm").html('<div style="padding:28px;text-align:center;color:#5a6a7c"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>');
    $("div.orderPenjualanForm").load(
      "<?= URL::BASE_URL ?>Penjualan/orderPenjualanForm/" + id_penjualan + "/" + id_harga + "/" + saldo
    );
  });

  $("#saldoMember .pakai-btn").each(function () {
    var elem = $(this);
    elem.fadeOut(150).fadeIn(150).fadeOut(150).fadeIn(150);
  });
</script>
