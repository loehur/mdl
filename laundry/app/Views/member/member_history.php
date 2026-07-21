<?php
$dPelanggan = $data['data_pelanggan'];
$jumlah_tampil = 15;
$idHarga = $data['id_harga'];

// Index lookup O(1)
$mapLayanan = [];
foreach ($this->dLayanan as $c) {
  $mapLayanan[$c['id_layanan']] = $c['layanan'];
}
$mapDurasi = [];
foreach ($this->dDurasi as $c) {
  $mapDurasi[$c['id_durasi']] = $c['durasi'];
}
$mapItemGroup = [];
foreach ($this->itemGroup as $c) {
  $mapItemGroup[$c['id_item_group']] = $c['item_kategori'];
}
$mapSatuanByJenis = [];
foreach ($this->dPenjualan as $l) {
  $satuan = '';
  foreach ($this->dSatuan as $sa) {
    if ($sa['id_satuan'] == $l['id_satuan']) {
      $satuan = $sa['nama_satuan'];
      break;
    }
  }
  $mapSatuanByJenis[$l['id_penjualan_jenis']] = $satuan;
}

$kategori = '';
$layanan = '';
$durasi = '';
$satuan = '';
foreach ($this->harga as $a) {
  if ($a['id_harga'] != $idHarga) {
    continue;
  }
  $listLayanan = @unserialize($a['list_layanan']);
  if (is_array($listLayanan)) {
    foreach ($listLayanan as $b) {
      if (isset($mapLayanan[$b])) {
        $layanan .= ' ' . $mapLayanan[$b];
      }
    }
  }
  if (isset($mapDurasi[$a['id_durasi']])) {
    $durasi .= ' ' . $mapDurasi[$a['id_durasi']];
  }
  if (isset($mapItemGroup[$a['id_item_group']])) {
    $kategori .= ' ' . $mapItemGroup[$a['id_item_group']];
  }
  break;
}
$jenis_member = $kategori . ',' . $layanan . ',' . $durasi;

// Merge topup + pemakaian (keduanya ASC) — O(n+m), hitung saldo full history
$sales = array_values($data['data_main']);
$topups = array_values($data['data_main2']);
$iSale = 0;
$iTop = 0;
$nSale = count($sales);
$nTop = count($topups);
$saldo_member = 0;
$arrHistory = [];

while ($iSale < $nSale || $iTop < $nTop) {
  $saleTime = ($iSale < $nSale) ? strtotime($sales[$iSale]['insertTime']) : PHP_INT_MAX;
  $topTime = ($iTop < $nTop) ? strtotime($topups[$iTop]['insertTime']) : PHP_INT_MAX;

  // Topup yang lebih awal dari sale berikutnya ikut dulu (sama perilaku lama: topup < tgl_terima)
  if ($iTop < $nTop && $topTime < $saleTime) {
    $m = $topups[$iTop];
    $saldo_member += (float) $m['qty'];
    $arrHistory[] = [
      'tipe' => 1,
      'id' => $m['id_member'],
      'tgl' => date('d-m-Y', $topTime),
      'qty' => $m['qty'],
      'saldo' => $saldo_member,
    ];
    $iTop++;
    continue;
  }

  if ($iSale < $nSale) {
    $a = $sales[$iSale];
    if ($satuan === '' && isset($mapSatuanByJenis[$a['id_penjualan_jenis']])) {
      $satuan = $mapSatuanByJenis[$a['id_penjualan_jenis']];
    }

    $qty = round((float) $a['qty'], 2);
    $minOrder = round(isset($a['min_order']) ? (float) $a['min_order'] : 0.0, 2);
    $qty_real = ($qty < $minOrder) ? round($minOrder, 2) : round($qty, 2);

    // Saldo debit tetap pakai qty (bukan qty_real) — perilaku lama
    $saldo_member -= $qty;

    $arrHistory[] = [
      'tipe' => 0,
      'id' => $a['id_penjualan'],
      'tgl' => date('d-m-Y', $saleTime),
      'qty' => $qty_real,
      'saldo' => $saldo_member,
    ];
    $iSale++;
    continue;
  }

  // Sisa topup setelah semua sale (topup >= last tgl_terima)
  $m = $topups[$iTop];
  $saldo_member += (float) $m['qty'];
  $arrHistory[] = [
    'tipe' => 1,
    'id' => $m['id_member'],
    'tgl' => date('d-m-Y', $topTime),
    'qty' => $m['qty'],
    'saldo' => $saldo_member,
  ];
  $iTop++;
}

$totalHis = count($arrHistory);
$lastSaldo = 'Saldo: <span class="text-success text-bold">0.00' . $satuan . '</span>';
if ($totalHis > 0) {
  $saldoH = $arrHistory[$totalHis - 1]['saldo'];
  $lastSaldo = 'Saldo: <span class="text-success text-bold">' . number_format($saldoH, 2) . $satuan . '</span>';
}

// Hanya render 15 transaksi terakhir
$tampil = ($totalHis > $jumlah_tampil)
  ? array_slice($arrHistory, -$jumlah_tampil)
  : $arrHistory;
$nTampil = count($tampil);
?>

<head>
  <meta charset="utf-8">
  <link rel="icon" href="<?= URL::IN_ASSETS ?>icon/logo.png">
  <title><?= strtoupper($data['data_pelanggan']['nama_pelanggan']) ?> | MDL</title>
  <meta name="viewport" content="width=480px, user-scalable=no">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/adminLTE-3.1.0/css/adminlte.min.css">

  <!-- FONT -->
  <style>
    @font-face {
      font-family: "fontku";
      src: url("<?= URL::EX_ASSETS ?>font/Titillium-Regular.otf");
    }

    html .table {
      font-family: 'fontku', sans-serif;
    }

    html .content {
      font-family: 'fontku', sans-serif;
    }

    html body {
      font-family: 'fontku', sans-serif;
    }

    table {
      border-radius: 15px;
      overflow: hidden
    }
  </style>
</head>

<div class="content">
  <div class="pt-2 mb-2 shadow-sm border-bottom" style="position: sticky; top:0px; background-color:white;z-index:2">
    <div class="row mx-0 px-1 pb-1">
      <div class="col m-auto" style="max-width: 480px;">
        Bpk/Ibu. <span class="text-success"><b><?= strtoupper($dPelanggan['nama_pelanggan']) ?></b></span>
        <a href="<?= URL::BASE_URL ?>J/<?= (int) $dPelanggan['id_pelanggan'] ?>" class="float-right ms-1" title="Mode baru"><span class='btn btn-sm btn-success'>Baru</span></a>
        <a href="<?= URL::BASE_URL ?>I/<?= $dPelanggan['id_pelanggan'] ?>?classic=1" class="float-right"><span class='btn btn-sm btn-warning'>Tagihan</span></a>
        <br><span class='text-bold text-primary'>M<?= $data['id_harga'] ?></span> | <?= $jenis_member ?>,
        <br><span id="sisa"></span> | <span><small>Last <?= $jumlah_tampil ?> transactions | Updated: <?php echo DATE('Y-m-d') ?></small></span>
      </div>
    </div>
  </div>

  <div class="row mx-0 px-1 mb-4">
    <div class="col m-auto w-100 backShow <?= strtoupper($dPelanggan['nama_pelanggan']) ?> p-0 m-1 rounded" style="max-width:460;">
      <div class="bg-white rounded border border-success">
        <table class="table table-sm m-0 rounded w-100">
          <?php foreach ($tampil as $idx => $ok) {
            $isLast = ($idx === $nTampil - 1);
            $classLast = $isLast ? 'bg-success' : '';
            $textSaldo = $isLast ? 'Saldo Terkini' : 'Saldo';
            $tipeH = $ok['tipe'];
            $id = $ok['id'];
            $tgl = $ok['tgl'];
            $qtyH = $ok['qty'];
            $saldoH = $ok['saldo'];

            if ($tipeH == 1) { ?>
              <tr class="table-success">
                <td class="pb-0"><small>Topup<br>Trx ID. [<b><?= $id ?></b>]</small></td>
                <td class="pb-0"><small>Tanggal<br> <?= $tgl ?></small></td>
                <td class="text-right"><small>Topup Qty<br></small><b><?= $qtyH . $satuan ?></b></td>
                <td class="text-right <?= $classLast ?>"><small><?= $textSaldo ?><br></small><b><?= number_format($saldoH, 2) . $satuan ?></b></td>
              </tr>
            <?php } else { ?>
              <tr>
                <td class="pb-0"><small>Laundry Item<br>No. [<b><?= $id ?></b>]</small></td>
                <td class="pb-0"><small>Tanggal<br> <?= $tgl ?></small></td>
                <td class="text-right"><small>Debit Qty<br></small><b>-<?= $qtyH . $satuan ?></b></td>
                <td class="text-right <?= $classLast ?>"><small><?= $textSaldo ?><br></small><b><?= number_format($saldoH, 2) . $satuan ?></b></td>
              </tr>
            <?php }
          } ?>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function() {
    $("span#sisa").html(<?= json_encode($lastSaldo) ?>);
  })
</script>
