<?php
$dPelanggan = $data['data_pelanggan'];
$jumlah_tampil = 15;

$kategori = "";
$layanan = "";
$durasi = "";
foreach ($this->harga as $a) {
  if ($a['id_harga'] == $data['id_harga']) {
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
  }
}
$jenis_member = $kategori . "," . $layanan . "," . $durasi;
?>

<head>
  <meta charset="utf-8">
  <link rel="icon" href="<?= URL::EX_ASSETS ?>icon/logo.png">
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
        <a href="<?= URL::BASE_URL ?>I/i/<?= $dPelanggan['id_pelanggan'] ?>" class="float-right"><span class='btn btn-sm btn-warning'>Tagihan</span></a>
        <br><span class='text-bold text-primary'>M<?= $data['id_harga'] ?></span> | <?= $jenis_member ?>,
        <br><span id="sisa"></span> | <span><small>Last <?= $jumlah_tampil ?> transactions | Updated: <?php echo DATE('Y-m-d') ?></small></span>
      </div>
    </div>
  </div>

  <?php
  $akum_pakai = 0;
  $saldo_member = 0;
  $arrHistory = array();
  $idHistory = 0;

  $no = 0;
  echo '<div class="row mx-0 px-1 mb-4">';
  echo "<div class='col m-auto w-100 backShow " . strtoupper($dPelanggan['nama_pelanggan']) . " p-0 m-1 rounded' style='max-width:460;'><div class='bg-white rounded border border-success'>";
  echo "<table class='table table-sm m-0 rounded w-100'>";

  foreach ($data['data_main'] as $a) {
    $no++;
    $id = $a['id_penjualan'];
    $f10 = $a['id_penjualan_jenis'];
    $f3 = $a['id_item_group'];
    $f4 = $a['list_item'];
    $f5 = $a['list_layanan'];
    $f11 = $a['id_durasi'];
    $qty = $a['qty'];
    $f7 = $a['harga'];
    $f8 = $a['note'];
    $f9 = $a['id_user'];
    $f1 = $a['insertTime'];
    $f12 = $a['hari'];
    $f13 = $a['jam'];
    $f14 = $a['diskon_qty'];
    $f15 = $a['diskon_partner'];
    $f16 = $a['min_order'];
    $f17 = $a['id_pelanggan'];
    $f18 = $a['id_user'];
    $noref = $a['no_ref'];
    $letak = $a['letak'];
    $id_ambil = $a['id_user_ambil'];
    $tgl_ambil = $a['tgl_ambil'];
    $timeRef = $f1;
    $member = $a['member'];
    $showMember = "";

    if ($f12 <> 0) {
      $tgl_selesai = date('d-m-Y', strtotime($f1 . ' +' . $f12 . ' days +' . $f13 . ' hours'));
    } else {
      $tgl_selesai = date('d-m-Y H:i', strtotime($f1 . ' +' . $f12 . ' days +' . $f13 . ' hours'));
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
    if ($qty < $f16) {
      $qty_real = $f16;
    } else {
      $qty_real = $qty;
    }

    $tgl_terima = strtotime($f1);

    foreach ($data['data_main2'] as $key => $m) {
      $tgl_deposit = strtotime($m['insertTime']);
      $dep = $m['qty'];
      if ($tgl_deposit < $tgl_terima) {
        $saldo_member = $saldo_member + $dep;
        $id_member = $m['id_member'];
        $idHistory += 1;
        $arrHistory[$idHistory]['tipe'] = 1;
        $arrHistory[$idHistory]['id'] = $id_member;
        $arrHistory[$idHistory]['tgl'] = date('d-m-Y', $tgl_deposit);
        $arrHistory[$idHistory]['qty'] = $dep;
        $arrHistory[$idHistory]['saldo'] = $saldo_member;
        unset($data['data_main2'][$key]);
      }
    }

    $saldo_member = $saldo_member - $qty;;

    $idHistory += 1;
    $arrHistory[$idHistory]['tipe'] = 0;
    $arrHistory[$idHistory]['id'] = $id;
    $arrHistory[$idHistory]['tgl'] =  date('d-m-Y', $tgl_terima);
    $arrHistory[$idHistory]['qty'] = $qty_real;
    $arrHistory[$idHistory]['saldo'] = $saldo_member;
  }

  foreach ($data['data_main2'] as $key => $m) {
    $tgl_deposit = strtotime($m['insertTime']);
    $dep = $m['qty'];
    if (!isset($tgl_terima)) {
      $tgl_terima = $tgl_deposit;
    }
    if ($tgl_deposit >= $tgl_terima) {
      $saldo_member = $saldo_member + $dep;
      $id_member = $m['id_member'];
      $idHistory += 1;
      $arrHistory[$idHistory]['tipe'] = 1;
      $arrHistory[$idHistory]['id'] = $id_member;
      $arrHistory[$idHistory]['tgl'] = date('d-m-Y', $tgl_deposit);
      $arrHistory[$idHistory]['qty'] = $dep;
      $arrHistory[$idHistory]['saldo'] = $saldo_member;
      unset($data['data_main2'][$key]);
    }
  }

  $totalHis = count($arrHistory);

  if (!isset($satuan)) {
    $satuan = "";
  }

  foreach ($arrHistory as $key => $ok) {
    $tipeH = $ok['tipe'];
    $id = $ok['id'];
    $tgl = $ok['tgl'];
    $qtyH =  $ok['qty'];
    $saldoH = $ok['saldo'];

    if ($totalHis <= $jumlah_tampil) {
      if ($totalHis == 1) {
        $classLast = 'bg-success';
        $textSaldo = 'Saldo Terkini';
      } else {
        $classLast = '';
        $textSaldo = 'Saldo';
      }
      if ($tipeH == 1) {
        echo "<tr class='table-success'>";
        echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Topup<br>Trx ID. [<b>" . $id . "</b>]</small></td>";
        echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Tanggal<br> " . $tgl . "</small></td>";
        echo "<td class='text-right'><small>Topup Qty<br></small><b>" . $qtyH . $satuan . "</b></td>";
        echo "<td class='text-right " . $classLast . "'><small>" . $textSaldo . "<br></small><b>" . number_format($saldoH, 2) . $satuan .  "</b></td>";
        echo "</tr>";
      } else {
        echo "<tr>";
        echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Laundry Item<br>No. [<b>" . $id . "</b>]</small></td>";
        echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Tanggal<br> " . $tgl . "</small></td>";
        echo "<td class='text-right'><small>Debit Qty<br></small><b>-" . $qtyH . $satuan .  "</b></td>";
        echo "<td class='text-right " . $classLast . "'><small>" . $textSaldo . "<br></small><b>" . number_format($saldoH, 2) . $satuan .  "</b></td>";
        echo "</tr>";
      }
    }

    unset($arrHistory[$key]);
    $totalHis = count($arrHistory);

    $lastSaldo = "Saldo: <span class='text-success text-bold'>" . number_format($saldoH, 2) . $satuan . "</span>";
  }

  echo "</tbody></table>";
  echo "</div></div></div>";
  ?>
</div>

<!-- SCRIPT -->
<script src=" <?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>

<script>
  $(document).ready(function() {
    $("span#sisa").html("<?= $lastSaldo ?>");
  })
</script>