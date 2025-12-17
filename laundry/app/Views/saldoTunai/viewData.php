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
<div class="row pl-1">
  <div class="col-auto">
    <span data-id_harga='0' class="btn btn-sm btn-success m-2 mt-0 pl-1 pr-1 pt-0 pb-0 float-right buttonTambah" data-bs-toggle="modal" data-bs-target="#exampleModal">
      (+) Saldo Deposit | <b><?= strtoupper($nama_pelanggan) ?></b>
    </span>
  </div>
  <div class="col-auto">
    <span data-id_harga='0' class="btn btn-sm btn-danger m-2 mt-0 pl-1 pr-1 pt-0 pb-0 float-right" data-bs-toggle="modal" data-bs-target="#exampleModal2">
      (-) Refund <b></b>
    </span>
  </div>
</div>

<div class="row pl-3">
  <?php
  $cols = 0;
  foreach ($data['data_'] as $z) {
    $cols += 1;
    $id = $z['id_kas'];
    $id_user = $z['id_user'];
    $timeRef = $z['insertTime'];
    $userKas = "";
    $jumlah = $z['jumlah'];
    $note = $z['note'];
    $jenis_mutasi = $z['jenis_mutasi'];

    $metode = $z['metode_mutasi'];
    switch ($metode) {
      case '1':
        $met = "Tunai";
        break;
      default:
        $met = "Non Tunai";
        break;
    }

    $class_tr = "";
    if ($jenis_mutasi == 1) {
      $class_tr = "table-success";
    } else {
      $class_tr = "table-danger";
    }

    $cs = "";
    foreach ($this->userMerge as $uM) {
      if ($uM['id_user'] == $id_user) {
        $cs = $uM['nama_user'];
      }
    }

    //BUTTON NOTIF
    $buttonNotif = "<a href='#' data-hp='" . $no_pelanggan . "' data-ref='" . $id . "' data-time='" . $timeRef . "' class='text-dark sendNotifMember bg-white rounded col pl-2 pr-2 mr-1'><i class='fab fa-whatsapp'></i> <span id='notif" . $id . "'></span></a>";
    foreach ($data["notif"] as $notif) {
      if ($notif['no_ref'] == $id) {
        $statusWA = $notif['proses'];
        if ($statusWA == '') {
          $statusWA = 'Pending';
        }
        $stNotif = "<b>" . ucwords($statusWA) . "</b> " . ucwords($notif['state']);
        $buttonNotif = "<span class='bg-white rounded col pl-2 pr-2 mr-1'><i class='fab fa-whatsapp'></i> " . $stNotif . "</span>";
      }
    }

    $cabangKode = $this->dCabang['kode_cabang'];

    $st_mutasi = $z['status_mutasi'];

    $stBayar = "";
    foreach ($this->dStatusMutasi as $st) {
      if ($st_mutasi == $st['id_status_mutasi']) {
        $stBayar = ($st['status_mutasi']);
      }
    }

    switch ($st_mutasi) {
      case '2':
        $statusM = "<span class='text-info'>" . $stBayar . " <b>(" . strtoupper($note) . ")</b></span>";
        break;
      case '3':
        $statusM = "<b><i class='fas fa-check-circle text-success'></i></b> " . strtoupper($note) . " ";
        break;
      case '4':
        $statusM = "<span class='text-danger text-bold'><i class='fas fa-times-circle'></i> " . $stBayar . " <b>(" . strtoupper($note) . ")</b></span> - ";
        break;
      default:
        $statusM = "Non Status - ";
        break;
    }

  ?>

    <div class="col p-0 m-1 mb-0 rounded" style='max-width:400px;'>
      <div class="bg-white rounded">
        <table class="table table-sm w-100 pb-0 mb-0 rounded">
          <tbody>
            <tr class="d-none">
              <td>
                <span class="d-none" id="text<?= $id ?>"><?= strtoupper($nama_pelanggan) ?> _#<?= $cabangKode ?>_ <?= "\n#" . $id ?> Topup Deposit Rp<?= number_format($jumlah) ?><?= "\n" . URL::HOST_URL  ?>/I/s/<?= $id_pelanggan ?></span>
              </td>
            </tr>
            <tr class="<?= $class_tr ?>">
              <td><a href='#' class='ml-1' onclick='Print("<?= $id ?>")'><i class='text-dark fas fa-print'></i></a></td>
              <td><b><?= strtoupper($nama_pelanggan) ?></b></td>
              <td class="text-right">
                <small><span class='buttonNotif'><?= $buttonNotif ?></span></small>
                <small><span class='rounded bg-white border pr-1 pl-1 buttonNotif'>CS: <?= $cs ?></span></small>
              </td>
            </tr>
            <tr>
              <td class="text-center"></td>
              <td nowrap>
                <?= $z['insertTime'] ?><br><?= "#" . $id . " " ?>
              </td>
              <td nowrap class="text-right"><b><?= $jenis_mutasi == 2 ? '-' : '' ?><?= number_format($jumlah) ?></b><br><small><?= $met ?> <?= $statusM ?></small></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <span class="d-none">
      <span id="<?= $id ?>"><?= strtoupper($nama_pelanggan) ?>,</span>
    </span>

    <span class="d-none" id="print<?= $id ?>" style="width:50mm;background-color:white; padding-bottom:10px">
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

        hr {
          border-top: 1px dashed black;
        }
      </style>
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
        <td style="margin: 0;">Topup Deposit</td>
        <td align="right"><?= number_format($jumlah) ?></td>
        <tr>
          <td colspan="2" style="border-bottom:1px dashed black;"></td>
        </tr>
        <tr>
          <td colspan="2"><br><br><br><br>.</td>
        </tr>
      </table>
    </span>
  <?php
    if ($cols == 2) {
      echo '<div class="w-100"></div>';
      $cols = 0;
    }
  } ?>
</div>

<div class="modal" id="exampleModal">
  <div class="modal-dialog">
    <div class="modal-content tambahPaket">

    </div>
  </div>
</div>

<div class="modal" id="exampleModal2">
  <div class="modal-dialog">
    <form action="<?= URL::BASE_URL ?>SaldoTunai/refund/<?= $id_pelanggan ?>" method="POST">
      <div class="modal-content">
        <div class="modal-body">
          <div class="card-body">
            <div class="row">
              <div class="col">
                <label for="exampleInputEmail1">Jumlah Refund</label>
                <input type="number" min="0" name="jumlah" class="form-control form-control-sm border-danger" required>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="exampleInputEmail1">Metode</label>
                  <select name="metode" class="form-control form-control-sm metodeBayar" style="width: 100%;" required>
                    <?php foreach ($this->dMetodeMutasi as $a) {
                      if ($a['id_metode_mutasi'] <> 3) { ?>
                        <option value="<?= $a['id_metode_mutasi'] ?>"><?= $a['metode_mutasi'] ?></option>
                    <?php }
                    } ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12">
                <div class="form-group">
                  <div class="form-group">
                    <label for="exampleInputEmail1">Catatan <small>(Tidak Wajib)</small></label>
                    <input type="text" name="noteBayar" maxlength="10" class="form-control form-control-sm" placeholder="" style="text-transform:uppercase">
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-auto" style="min-width: 200px;">
                <label for="exampleInputEmail1">Karyawan</label>
                <select name="staf" class="tarik2 form-control form-control-sm" style="width: 100%;" required>
                  <option value="" selected disabled></option>
                  <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                    <?php foreach ($this->user as $a) { ?>
                      <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                    <?php } ?>
                  </optgroup>
                  <?php if (count($this->userCabang) > 0) { ?>
                    <optgroup label="----- Cabang Lain -----">
                      <?php foreach ($this->userCabang as $a) { ?>
                        <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                      <?php } ?>
                    </optgroup>
                  <?php } ?>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-sm btn-danger">Refund</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
  $(document).ready(function() {
    $("div#nTunai").hide();
    $("input#searchInput").addClass("d-none");
    $('select.tize').selectize();
    $("td#btnTambah").removeClass("d-none");

    $('td#btnTambah').each(function() {
      var elem = $(this);
      elem.fadeOut(150)
        .fadeIn(150)
        .fadeOut(150)
        .fadeIn(150)
    });

    selectList2();
  });

  function selectList2() {
    $('select.tarik2').select2({
      dropdownParent: $("#exampleModal2"),
    });
  }

  $("a.sendNotifMember").on('click', function(e) {
    e.preventDefault();
    $(".loaderDiv").fadeIn("fast");
    var hpNya = $(this).attr('data-hp');
    var refNya = $(this).attr('data-ref');
    var timeNya = $(this).attr('data-time');
    var textNya = $("span#text" + refNya).html();
    $.ajax({
      url: '<?= URL::BASE_URL ?>SaldoTunai/sendNotifDeposit',
      data: {
        hp: hpNya,
        text: textNya,
        ref: refNya,
        time: timeNya,
      },
      type: "POST",
      success: function(res) {
        if (res == 0) {
          $(".loaderDiv").fadeOut("slow");
          $('div#riwayat').load('<?= URL::BASE_URL ?>SaldoTunai/tampilkan/' + <?= $id_pelanggan ?>);
        } else {
          alert(res);
        }
      },
    });
  });


  $("select.metodeBayar").on("keyup change", function() {
    if ($(this).val() == 2) {
      $("div#nTunai").show();
    } else {
      $("div#nTunai").hide();
    }
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
    $('div.tambahPaket').load("<?= URL::BASE_URL ?>SaldoTunai/orderPaket/<?= $id_pelanggan ?>/" + id_harga);
  });

  $("a.bayarMember").on('click', function(e) {
    e.preventDefault();
    var refNya = $(this).attr('data-ref');
    var bayarNya = $(this).attr('data-harga');
    var id_pelanggan = $(this).attr('data-idPelanggan');
    $("input.idItemMember").val(refNya);
    $("input.jumlahBayarMember").val(bayarNya);
    $("input.idPelangganMember").val(id_pelanggan);
    $("input.jumlahBayarMember").attr({
      'max': bayarNya
    });
  });

  $("a.bayarPasMember").on('click', function(e) {
    e.preventDefault();
    var jumlahPas = $("input.jumlahBayarMember").val();
    $("input.dibayarMember").val(jumlahPas);
    diBayar = $("input.dibayarMember").val();
  });

  $("input.dibayarMember").on("keyup change", function() {
    diBayar = 0;
    diBayar = $(this).val();
    var kembalian = $(this).val() - $('input.jumlahBayarMember').val()
    if (kembalian > 0) {
      $('input.kembalianMember').val(kembalian);
    } else {
      $('input.kembalianMember').val(0);
    }
  });

  function Print(id) {
    var printContents = document.getElementById("print" + id).innerHTML;
    var originalContents = document.body.innerHTML;
    window.document.body.style = 'margin:0';
    window.document.writeln(printContents);
    window.print();
    location.reload(true);
  }
</script>