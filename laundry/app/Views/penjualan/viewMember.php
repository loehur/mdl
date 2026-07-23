<style>
  #saldoMember .ord-member {
    background: linear-gradient(180deg, #f0fdf4, #fff);
    border: 2px solid #4ade80;
    border-radius: 0;
    padding: 14px;
    margin-bottom: 10px;
    box-shadow: 0 10px 22px rgba(22, 163, 74, 0.12);
  }
  #saldoMember .ord-member-title {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin: 0 0 12px;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #0f172a;
  }
  #saldoMember .ord-member-title small {
    display: block;
    margin-top: 2px;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: none;
    color: #15803d;
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
    padding: 11px;
    border: 2px solid #86efac;
    border-radius: 0;
    background: #fff;
  }
  #saldoMember .ord-member-item strong {
    display: block;
    font-size: 0.88rem;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: -0.02em;
  }
  #saldoMember .ord-member-saldo {
    color: #16a34a;
    font-weight: 900;
  }
  #saldoMember .ord-member-meta {
    display: block;
    margin-top: 3px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.35;
  }
  #saldoMember .ord-pakai-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-width: 74px;
    padding: 8px 12px;
    border: 0;
    border-radius: 0;
    background: linear-gradient(135deg, #15803d, #16a34a);
    color: #fff;
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 900;
    cursor: pointer;
    box-shadow: 0 8px 16px rgba(22, 163, 74, 0.28);
    transition: transform .12s ease, filter .12s ease;
  }
  #saldoMember .ord-pakai-btn:active {
    transform: scale(0.97);
  }
  #saldoMember .ord-pakai-btn:hover {
    filter: brightness(1.05);
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
