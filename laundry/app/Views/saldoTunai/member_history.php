<?php
$dPelanggan = $data['data_pelanggan'];
$tampil = 15;
?>

<head>
  <meta charset="utf-8">
  <link rel="icon" href="<?= URL::EX_ASSETS ?>icon/logo.png">
  <title><?= strtoupper($dPelanggan['nama_pelanggan']) ?> | MDL</title>
  <meta name="viewport" content="width=410, user-scalable=no">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>css/ionicons.min.css">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css">
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
  <div class="pt-2 px-1 mb-2 border-bottom shadow-sm" style="position: sticky; top:0px; background-color:white;z-index:2">
    <div class="row p-1 pb-1 mx-0">
      <div class="col m-auto" style="max-width: 480px;">
        Bpk/Ibu. <span class="text-success"><b><?= strtoupper($dPelanggan['nama_pelanggan']) ?></b></span>
        <a href="<?= URL::BASE_URL ?>I/i/<?= $dPelanggan['id_pelanggan'] ?>" class="float-right"><span class='btn btn-sm btn-warning'>Tagihan</span></a>
        <br><span class="text-bold">Saldo Deposit:</span> <span class="text-bold text-primary" id="sisa"></span><br><span><small>Last <?= $tampil ?> transactions, Updated: <?php echo DATE('Y-m-d') ?></small></span>
      </div>
    </div>
  </div>

  <?php

  echo '<div class="row mx-0 p-1">';
  echo "<div class='col px-1 m-auto w-100 backShow " . strtoupper($dPelanggan['nama_pelanggan']) . " p-0 m-1 rounded' style='max-width:460;'><div class='bg-white rounded border border-success'>";
  echo "<table class='table table-sm m-0 rounded w-100'>";

  $baris = count($data['data_main']);
  $buang = $baris - $tampil;
  $no = 0;

  foreach ($data['data_main'] as $a) {
    $no += 1;
    if ($buang > 0) {
      $buang -= 1;
      continue;
    }

    if ($no == $baris) {
      $classLast = 'bg-success';
      $textSaldo = 'Saldo Terkini';
    } else {
      $classLast = '';
      $textSaldo = 'Saldo';
    }

    $id = $a['id_kas'];
    $tgl = $a['insertTime'];
    $jumlah = $a['jumlah'];
    $jenis_mutasi = $a['jenis_mutasi'];
    $jenis_transaksi = $a['jenis_transaksi'];
    $saldo = $a['saldo'];

    $topay = "Laundry";
    switch ($jenis_transaksi) {
      case 1:
        $topay = "Laundry";
        break;
      case 3:
        $topay = "Topup Paket";
        break;
      case 6;
        $topay = "Topup Deposit";
        break;
    }

    if ($jenis_transaksi == 6 && $jenis_mutasi == 1) {
      echo "<tr class='table-success'>";
      echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Deposit<br>Trx ID. [<b>" . $id . "</b>]</small></td>";
      echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Tanggal<br> " . $tgl . "</small></td>";
      echo "<td class='text-right'><small>Topup Rp<br></small><b>" . number_format($jumlah) . "</b></td>";
      echo "<td class='text-right " . $classLast . "'><small>" . $textSaldo . "<br></small><b>" . number_format($saldo) .  "</b></td>";
      echo "</tr>";
    } else if ($jenis_mutasi == 2 && $jenis_transaksi == 1) {
      echo "<tr class='table-light'>";
      echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Bayar " . $topay . "<br>Trx ID. [<b>" . $id . "</b>]</small></td>";
      echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Tanggal<br> " . $tgl . "</small></td>";
      echo "<td class='text-right'><small>Debit Rp<br></small><b>-" . number_format($jumlah) . "</b></td>";
      echo "<td class='text-right " . $classLast . "'><small>" . $textSaldo . "<br></small><b>" . number_format($saldo) .  "</b></td>";
      echo "</tr>";
    } else if ($jenis_mutasi == 2 && $jenis_transaksi == 3) {
      echo "<tr class='table-light'>";
      echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Bayar " . $topay . "<br>Trx ID. [<b>" . $id . "</b>]</small></td>";
      echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Tanggal<br> " . $tgl . "</small></td>";
      echo "<td class='text-right'><small>Debit Rp<br></small><b>-" . number_format($jumlah) . "</b></td>";
      echo "<td class='text-right " . $classLast . "'><small>" . $textSaldo . "<br></small><b>" . number_format($saldo) .  "</b></td>";
      echo "</tr>";
    } else if ($jenis_mutasi == 2 && $jenis_transaksi == 6) {
      echo "<tr class='table-danger'>";
      echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Refund<br>Trx ID. [<b>" . $id . "</b>]</small></td>";
      echo "<td class='pb-0'><span style='white-space: nowrap;'></span><small>Tanggal<br> " . $tgl . "</small></td>";
      echo "<td class='text-right'><small>Debit Rp<br></small><b>-" . number_format($jumlah) . "</b></td>";
      echo "<td class='text-right " . $classLast . "'><small>" . $textSaldo . "<br></small><b>" . number_format($saldo) .  "</b></td>";
      echo "</tr>";
    }
  }

  echo "</table>";
  echo "</div></div></div>";
  ?>
</div>

<!-- SCRIPT -->
<script src=" <?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>

<script>
  $(document).ready(function() {
    $("span#sisa").html("<?= number_format($saldo) ?>");
  })
</script>