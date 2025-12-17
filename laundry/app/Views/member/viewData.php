<?php
$id_pelanggan = $data['pelanggan'];
$nama_pelanggan = "";
foreach ($this->pelanggan as $dp) {
  if ($dp['id_pelanggan'] == $id_pelanggan) {
    $nama_pelanggan = $dp['nama_pelanggan'];
    $no_pelanggan = $dp['nomor_pelanggan'];
  }
}
?>
<div class="row mx-0">
  <div class="col-auto">
    <span data-id_harga='0' class="btn btn-sm btn-primary m-2 mt-0 pl-1 pr-1 pt-0 pb-0 float-right buttonTambah" data-bs-target="#modalMemberDeposit">
      (+) Saldo Paket | <b><?= strtoupper($nama_pelanggan) ?></b>
    </span>
  </div>
</div>
<div class="row mx-0 pb-5">
  <?php
  $cols = 0;
  foreach ($data['data_manual'] as $z) {
    $cols += 1;
    $id = $z['id_member'];
    $id_harga = $z['id_harga'];
    $harga = $z['harga'];
    $id_user = $z['id_user'];
    $kategori = "";
    $layanan = "";
    $durasi = "";
    $unit = "";
    $timeRef = $z['insertTime'];

    $showMutasi = "";
    $userKas = "";
    foreach ($data['kas'] as $ka) {
      if ($ka['ref_transaksi'] == $id) {
        foreach ($this->userMerge as $usKas) {
          if ($usKas['id_user'] == $ka['id_user']) {
            $userKas = $usKas['nama_user'];
          }
        }

        $stBayar = "";
        foreach ($this->dStatusMutasi as $st) {
          if ($ka['status_mutasi'] == $st['id_status_mutasi']) {
            $stBayar = $st['status_mutasi'];
          }
        }

        $notenya = strtoupper($ka['note']);
        $st_mutasi = $ka['status_mutasi'];

        switch ($st_mutasi) {
          case '2':
            $statusM = "<span class='text-info'>" . $stBayar . " <b>(" . $notenya . ")</b></span> - ";
            break;
          case '3':
            $statusM = "<b><i class='fas fa-check-circle text-success'></i></b> " . $notenya . " ";
            break;
          case '4':
            $statusM = "<span class='text-danger text-bold'><i class='fas fa-times-circle'></i> " . $stBayar . " <b>(" . $notenya . ")</b></span> - ";
            break;
          default:
            $statusM = "Non Status - ";
            break;
        }

        if ($st_mutasi == 4) {
          $nominal = "<s>-Rp" . number_format($ka['jumlah']) . "</s>";
        } else {
          $nominal = "-Rp" . number_format($ka['jumlah']);
        }

        $showMutasi = $showMutasi . "<small>" . $statusM . "<b>#" . $ka['id_kas'] . " " . $userKas . "</b> " . substr($ka['insertTime'], 5, 11) . " " . $nominal . "</small><br>";
      }
    }

    foreach ($this->harga as $a) {
      if ($a['id_harga'] == $z['id_harga']) {
        foreach ($this->dPenjualan as $dp) {
          if ($dp['id_penjualan_jenis'] == $a['id_penjualan_jenis']) {
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
      }
    }

    $totalBayar = 0;
    $adaBayar_M = 0;

    $adaBayar = false;
    $historyBayar = [];
    $hisDibayar = [];

    foreach ($data['kas'] as $k) {
      if ($k['ref_transaksi'] == $id && $k['status_mutasi'] == 3) {
        array_push($historyBayar, $k['jumlah']);
      }
      if ($k['ref_transaksi'] == $id && $k['status_mutasi'] <> 4) {
        array_push($hisDibayar, $k['jumlah']);
      }

      if ($k['ref_transaksi'] == $id) {
        $adaBayar = true;
      }
    }

    $statusBayar = "";
    $totalBayar = array_sum($historyBayar);
    $dibayar_M = array_sum($hisDibayar);

    $showSisa = "";
    $sisa = $harga;
    $lunas = false;
    $enHapus = true;
    if ($totalBayar > 0) {
      $enHapus = false;
      if ($totalBayar >= $harga) {
        $lunas = true;
        $statusBayar = "<b><i class='fas fa-check-circle text-success'></i></b>";
      } else {
        $lunas = false;
      }
    } else {
      $lunas = false;
    }

    $sisa = $harga - $dibayar_M;

    if ($dibayar_M > 0 && $sisa > 0) {
      $showSisa = "<b><i class='fas fa-exclamation-circle'></i> Sisa Rp" . number_format($sisa) . "</b>";
    }

    $buttonBayar = "<a href='" . URL::BASE_URL . "Operasi/i/0/" . $id_pelanggan . "' class='border border-danger pr-1 pl-1 rounded'>Bayar/Cetak</a>";
    if ($dibayar_M >= $harga) {
      $buttonBayar = "";
    }

    $cs = "";
    foreach ($this->userMerge as $uM) {
      if ($uM['id_user'] == $id_user) {
        $cs = $uM['nama_user'];
      }
    }

    if ($enHapus == true || $this->id_privilege == 100) {
      $buttonHapus = "<small><a href='#' data-id='" . $id . "' class='hapusRef text-dark'><i class='fas fa-trash-alt'></i></a></small> ";
    } else {
      $buttonHapus = "";
    }

    foreach ($this->pelanggan as $c) {
      if ($c['id_pelanggan'] == $id_pelanggan) {
        $no_pelanggan = $c['nomor_pelanggan'];
      }
    }

    $cabangKode = $this->dCabang['kode_cabang'];

    $buttonNotif = "<a href='#' data-ref='" . $id . "' class='text-dark sendNotifMember bg-white rounded col pl-2 pr-2 mr-1'><i class='fab fa-whatsapp'></i> <span id='notif" . $id . "'></span></a>";
    foreach ($data['notif_member'] as $notif) {
      if ($notif['no_ref'] == $id) {
        $statusWA = $notif['proses'];
        if ($statusWA == '') {
          $statusWA = 'Pending';
        }
        $stNotif = "<b>" . ucwords($statusWA) . "</b> " . ucwords($notif['state']);
        $buttonNotif = "<span class='bg-white rounded col pl-2 pr-2 mr-1'><i class='fab fa-whatsapp'></i> " . $stNotif . "</span>";
      }
    }
  ?>

    <div class="col p-0 m-1 mb-0 rounded" style='max-width:450px;'>
      <div class="bg-white rounded">
        <table class="table table-sm w-100 pb-0 mb-0">
          <tbody>
            <tr class="table-info">
              <td><a href='#' class='ml-1' onclick='Print("<?= $id ?>")'><i class='text-dark fas fa-print'></i></a></td>
              <td colspan="2">
                <b><?= strtoupper($nama_pelanggan) ?></b>
                <small><span class='rounded float-end bg-white border px-1'>CS: <span><?= $cs ?></span></small>
                <small><span class='buttonNotif px-1'><?= $buttonNotif ?></span></small>
              </td>
            </tr>

            <tr>
              <td class="text-center">
                <?php if ($adaBayar == false || $this->id_privilege == 100) { ?>
                  <span><?= $buttonHapus ?></span>
                <?php } ?>
              </td>
              <td nowrap>
                <?= "#" . $id . " " ?> <?= $z['insertTime'] ?><br>
                <b>[M<?= $id_harga ?>]</b> <?= $kategori ?> * <?= $layanan ?> * <?= $durasi ?>
              </td>
              <td nowrap class="text-right"><br><b><?= $z['qty'] . $unit ?></b></td>
            </tr>
            <tr>
              <td></td>
              <td class="text-right">
                <?php if ($lunas == false) { ?>
                  <span class="float-left"><small><b><?= $buttonBayar ?></b></small></span>
                <?php } ?>
              </td>
              <td nowrap class="text-right"><span id="statusBayar<?= $id ?>"><?= $statusBayar ?></span>&nbsp;
                <span class="float-right"><b>Rp<?= number_format($harga) ?></b></span>
              </td>
            </tr>
            <?php if ($adaBayar == true) { ?>
              <tr>
                <td></td>
                <td colspan="2" class="text-right"><span id="historyBayar<?= $id ?>"><?= $showMutasi ?></span>
                  </span><span id="sisa<?= $id ?>" class="text-danger"><?= $showSisa ?></span></td>
              </tr>
            <?php
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    if ($cols == 2) {
      echo '<div class="w-100"></div>';
      $cols = 0;
    } ?>
    <span class="d-none" id="print<?= $id ?>" style="width:50mm;background-color:white; padding-bottom:10px">
      <table style="width:42mm; font-size:x-small; margin-top:10px; margin-bottom:10px">
        <tr>
          <td colspan="2" style="text-align: center;border-bottom:1px dashed black; padding:6px;">
            <b> <?= $this->dCabang['nama'] ?> - <?= $this->dCabang['kode_cabang'] ?></b><br>
            <?= $this->dCabang['alamat'] ?>
          </td>
        </tr>
        <tr>
          <td colspan="2" style="border-bottom:1px dashed black; padding-top:6px;padding-bottom:6px;">
            <font size='2'><b><?= strtoupper($nama_pelanggan) ?></b></font><br>
            #<?= $id ?><br>
            <?= $z['insertTime'] ?>
          </td>
        </tr>
        <td style="margin: 0;">Topup Paket <b>M<?= $id_harga ?></b><br><?= $kategori ?>, <?= $layanan ?>, <?= $durasi ?>, <?= $z['qty'] . $unit ?></td>
        <tr>
          <td colspan="2" style="border-bottom:1px dashed black;"></td>
        </tr>
        <tr>
          <td>
            Total
          </td>
          <td style="text-align: right;">
            <?= "Rp" . number_format($harga) ?>
          </td>
        </tr>
        <tr>
          <td>
            Bayar
          </td>
          <td style="text-align: right;">
            Rp<?= number_format($totalBayar) ?>
          </td>
        </tr>
        <tr>
          <td>
            Sisa
          </td>
          <td style="text-align: right;">
            Rp<?= number_format($sisa) ?>
          </td>
        </tr>
        <tr>
          <td colspan="2" style="border-bottom:1px dashed black;"></td>
        </tr>
      </table>
    </span>
  <?php } ?>
</div>

<div class="modal" id="modalMemberDeposit">
  <div class="modal-dialog">
    <div class="modal-content tambahPaket">

    </div>
  </div>
</div>

<script>
  $(document).ready(function() {
    $("div#nTunai").hide();
    $("input#searchInput").addClass("d-none");
    if(typeof $.fn.selectize !== 'undefined') {
      $('select.tize').selectize();
    }
    $("td#btnTambah").removeClass("d-none");

    $('td#btnTambah').each(function() {
      var elem = $(this);
      elem.fadeOut(150)
        .fadeIn(150)
        .fadeOut(150)
        .fadeIn(150)
    });
  });

  var klikNotif = 0;
  $("a.sendNotifMember").on('click', function(e) {
    klikNotif += 1;
    if (klikNotif > 1) {
      return;
    }
    $(this).fadeOut("slow");
    e.preventDefault();
    var refNya = $(this).attr('data-ref');
    $.ajax({
      url: '<?= URL::BASE_URL ?>Member/sendNotifDeposit/' + refNya,
      data: {},
      type: "POST",
      beforeSend: function() {

      },
      success: function(res) {
        if (res != 0) {
          alert(res);
        } else {
          $("button#cekR").click();
        }
      },
      complete: function() {
        $(".loaderDiv").fadeOut("slow");
      }
    });
  });

  $("a.hapusRef").on('click dblclick', function(e) {
    e.preventDefault();
    var idnya = $(this).attr('data-id');
    $.ajax({
      url: '<?= URL::BASE_URL ?>Member/bin',
      data: {
        id: idnya,
      },
      type: "POST",
      beforeSend: function() {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function(res) {
        loadDiv();
      },
      complete: function() {
        $(".loaderDiv").fadeOut("slow");
      }
    });
  });

  $("form.ajax").on("submit", function(e) {
    e.preventDefault();
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      success: function() {
        location.reload(true);
      },
    });
  });

  $("span.buttonTambah").on("click", function(e) {
    var id_harga = $(this).attr("data-id_harga");
    $('div.tambahPaket').load("<?= URL::BASE_URL ?>Member/orderPaket/<?= $id_pelanggan ?>/" + id_harga);

    // Manual modal trigger
    var target = $(this).attr('data-bs-target');
    if(target) {
        var modalEl = document.querySelector(target);
        if(modalEl) {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }
  });

  function Print(id) {
    var divContents = document.getElementById("print" + id).innerHTML;
    var a = window.open('');
    a.document.write('<title>Print Page</title>');
    a.document.write('<body style="margin-left: <?= isset($this->mdl_setting['print_ms']) ? $this->mdl_setting['print_ms'] : 0 ?>mm">');
    a.document.write(divContents);
    var window_width = $(window).width();
    a.print();

    if (window_width > 600) {
      a.close()
    } else {
      setTimeout(function() {
        a.close()
      }, 60000);
    }

    loadDiv();
  }
</script>