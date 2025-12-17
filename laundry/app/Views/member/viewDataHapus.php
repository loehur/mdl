<div class="content mt-1">
  <div class="container-fluid">
    <div class="row bg-white" style="max-width: 732px;">
      <div class="col m-2">
        Related : <span id="forbidden"></span>
      </div>
      <div class="col m-2">
        <button class="badge-danger btn-outline-danger rounded clearHapus float-right">Hapus Semua</span>
      </div>
    </div>
    <div class="row" style="max-width: 732px;">
      <?php
      $forbiddenCount  = 0;
      $arrID = array();
      $arrNoref = array();
      foreach ($data['data_manual'] as $z) { ?>
        <div class="col p-0 rounded" style='max-width:470px;'>
          <div class="bg-white rounded">
            <table class="table border-right table-sm w-100">
              <tbody>
                <?php
                $id = $z['id_member'];
                array_push($arrID, $id);
                $harga = $z['harga'];
                $id_user = $z['id_user'];
                $kategori = "";
                $layanan = "";
                $durasi = "";
                $unit = "";

                $pelanggan = $z['id_pelanggan'];
                $nama_pelanggan = "";
                foreach ($this->pelanggan as $dp) {
                  if ($dp['id_pelanggan'] == $pelanggan) {
                    $nama_pelanggan = $dp['nama_pelanggan'];
                  }
                }

                $showMutasi = "";
                $userKas = "";
                foreach ($data['kas'] as $ka) {
                  if ($ka['ref_transaksi'] == $id) {
                    foreach ($this->userMerge as $usKas) {
                      if ($usKas['id_user'] == $ka['id_user']) {
                        $userKas = $usKas['nama_user'];
                      }
                    }
                    $showMutasi = $showMutasi . "<br><small><b>#" . $ka['id_kas'] . " " . $userKas . "</b> " . substr($ka['insertTime'], 5, 11) . "</small> -Rp" . number_format($ka['jumlah']);
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

                $historyBayar = array();
                foreach ($data['kas'] as $k) {
                  if ($k['ref_transaksi'] == $id) {
                    array_push($historyBayar, $k['jumlah']);
                    array_push($arrNoref, $id);
                  }
                }

                $statusBayar = "";
                $totalBayar = array_sum($historyBayar);
                $showSisa = "";
                $sisa = $harga;
                $lunas = false;
                $enHapus = true;
                if ($totalBayar > 0) {
                  $forbiddenCount += 1;
                  $enHapus = false;
                  if ($totalBayar >= $harga) {
                    $lunas = true;
                    $statusBayar = "<b><i class='fas fa-check-circle text-success'></i></b>";
                  } else {
                    $sisa = $harga - $totalBayar;
                    $showSisa = "<b><i class='fas fa-exclamation-circle'></i> Sisa Rp" . number_format($sisa) . "</b>";
                    $lunas = false;
                  }
                } else {
                  $lunas = false;
                }
                $buttonBayar = "<button data-ref='" . $id . "' data-harga='" . $sisa . "' class='btn badge badge-danger bayar' data-bs-toggle='modal' data-bs-target='#exampleModal2'>Bayar</button>";
                if ($lunas == true) {
                  $buttonBayar = "";
                }

                $cs = "";
                foreach ($this->userMerge as $uM) {
                  if ($uM['id_user'] == $id_user) {
                    $cs = $uM['nama_user'];
                  }
                }

                if ($this->id_privilege == 100) {
                  $buttonHapus = "<button data-id='" . $id . "' class='restoreRef badge-success mb-1 rounded btn-outline-success'><i class='fas fa-recycle'></i></button> ";
                } else {
                  $buttonHapus = "";
                }

                ?>
                <tr>
                  <td nowrap><b><?= strtoupper($nama_pelanggan) ?></b><br><?= $z['insertTime'] ?><br><?= $kategori ?> * <?= $layanan ?> * <?= $durasi ?></td>
                  <td nowrap class="text-right">CS: <?= $cs ?><br><b><?= $z['qty'] . $unit ?></b></td>
                </tr>
                <tr>
                  <td colspan="2" class="text-right">
                    <span class="float-left"><?= $buttonHapus ?></span>
                    <span id="statusBayar<?= $id ?>"><?= $statusBayar ?></span>&nbsp;
                    <span class="float-right"><b>Rp<?= number_format($harga) ?></b></span>
                    <span id="historyBayar<?= $id ?>"><?= $showMutasi ?></span>
                    </span><br><span id="sisa<?= $id ?>" class="text-danger"><?= $showSisa ?></span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<div class="modal" id="exampleModal">
  <div class="modal-dialog">
    <div class="modal-content tambahPaket">

    </div>
  </div>
</div>

<form class="ajax" action="<?= URL::BASE_URL; ?>Member/bayar" method="POST">
  <div class="modal" id="exampleModal2">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Pembayaran Topup Paket</h5>
        </div>
        <div class="modal-body">
          <div class="container">
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="exampleInputEmail1">Jumlah (Rp)</label>
                  <input type="number" name="maxBayar" class="form-control float jumlahBayar" id="exampleInputEmail1" readonly>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="exampleInputEmail1">Bayar (Rp) <a class="btn badge badge-primary bayarPas">Bayar Pas (Click)</a></label>
                  <input type="number" name="f1" class="form-control dibayar" id="exampleInputEmail1" required>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="exampleInputEmail1">Kembalian (Rp)</label>
                  <input type="number" class="form-control float kembalian" id="exampleInputEmail1" readonly>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="exampleInputEmail1">Metode</label>
                  <select name="f4" class="bayar form-control form-control-sm" style="width: 100%;" required>
                    <?php foreach ($this->dMetodeMutasi as $a) {
                      if ($a['id_metode_mutasi'] == 1) { ?>
                        <option value="<?= $a['id_metode_mutasi'] ?>" selected><?= $a['metode_mutasi'] ?></option>
                      <?php } else { ?>
                        <option value="<?= $a['id_metode_mutasi'] ?>" disabled><?= $a['metode_mutasi'] ?></option>
                    <?php }
                    } ?>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="exampleInputEmail1">Penerima</label>
                  <select name="f2" class="bayar form-control form-control-sm userChange" style="width: 100%;" required>
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
                  <input type="hidden" class="idItem" name="f3" value="" required>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12">
                <div class="form-group">
                  <div class="form-group">
                    <label for="exampleInputEmail1">Catatan (Optional)</label>
                    <input type="text" name="f5" class="form-control" id="exampleInputEmail1" placeholder="">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-sm btn-primary">Bayar</button>
        </div>
      </div>
    </div>
  </div>
</form>


<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<script>
  $(document).ready(function() {
    $("span#forbidden").html("<?= $forbiddenCount ?>");
  });

  $("button.restoreRef").on('click', function(e) {
    e.preventDefault();
    var idNya = $(this).attr('data-id');
    $.ajax({
      url: '<?= URL::BASE_URL ?>Member/restoreRef',
      data: {
        id: idNya,
      },
      type: "POST",
      success: function(response) {
        location.reload(true);
      },
    });
  });

  $('button.clearHapus').click(function() {
    var dataID = '<?= serialize($arrID) ?>';
    var dataRef = '<?= serialize($arrNoref) ?>';
    var countForbid = <?= $forbiddenCount ?>;
    var countID = <?= count($arrID) ?>;

    if (countForbid > 0) {
      $.ajax({
        url: '<?= URL::BASE_URL ?>HapusOrder/hapusRelated',
        data: {
          'transaksi': 3,
          'dataID': dataID,
          'dataRef': dataRef,
        },
        type: 'POST',
        success: function() {
          location.reload(true);
        },
      });
    }
    if (countForbid == 0 && countID > 0) {
      $.ajax({
        url: '<?= URL::BASE_URL ?>HapusOrder/hapusID',
        data: {
          'table': 'member',
          'kolomID': 'id_member',
          'dataID': dataID,
        },
        type: 'POST',
        success: function() {
          location.reload(true);
        },
      });
    }
  });
</script>