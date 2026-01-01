<div id="loadContent">
  <style>
    td {
      vertical-align: top;
    }
  </style>
  <div class="content mt-1">
    <div class="container-fluid">
      <div class="row bg-white" style="max-width: 732px;">
        <div class="col m-2 mt-auto">
          <span id="forbidden"></span> Operations Linked
        </div>
        <div class="col my-2">
          <button class="badge-danger btn-outline-danger rounded clearHapus float-right">Hapus Semua</span>
        </div>
      </div>
      <div class="row" style="max-width: 732px;">
        <?php
        $arrRef = array();
        $prevRef = '';
        $countRef = 0;
        foreach ($data['data_main'] as $a) {
          $ref = $a['no_ref'];
          if ($prevRef <> $a['no_ref']) {
            $countRef = 0;
            $countRef++;
            $arrRef[$ref] = $countRef;
          } else {
            $countRef++;
            $arrRef[$ref] = $countRef;
          }
          $prevRef = $ref;
        }
        $no = 0;
        $urutRef = 0;
        $arrCount = 0;
        $enHapus = true;
        $arrNoref = [];
        $arrID = [];

        $forbiddenCount = 0;

        foreach ($data['data_main'] as $a) { ?>

          <div class="col bg-white">
            <table class="table table-sm w-100">

              <?php
              $no++;
              $id = $a['id_penjualan'];
              array_push($arrID, $id);

              $f10 = $a['id_penjualan_jenis'];
              $f3 = $a['id_item_group'];
              $f4 = $a['list_item'];
              $f5 = $a['list_layanan'];
              $f11 = $a['id_durasi'];
              $f6 = $a['qty'];
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

              $pelanggan = '';
              $no_pelanggan = '';
              foreach ($this->pelanggan as $c) {
                if ($c['id_pelanggan'] == $f17) {
                  $pelanggan = $c['nama_pelanggan'];
                  $no_pelanggan = $c['nomor_pelanggan'];
                }
              }

              $karyawan = '';
              foreach ($this->user as $c) {
                if ($c['id_user'] == $f18) {
                  $karyawan = $c['nama_user'];
                }
              }

              $durasi = "";
              foreach ($this->dDurasi as $b) {
                if ($b['id_durasi'] == $f11) {
                  $durasi = $b['durasi'];
                }
              }

              if ($no == 1) {
                $enHapus = true;
                $urutRef++;

                foreach ($data['notif_bon'] as $n) {
                  if ($n['no_ref'] == $noref) {
                    $forbiddenCount += 1;
                    array_push($arrNoref, $noref);
                  }
                }

              ?>

                <tr class='table-secondary' id='tr<?= $id ?>'>
                  <td>
                    <b><?= strtoupper($pelanggan) ?></b>
                  </td>
                  <td class="text-end"><i><small><?= $f8 ?></small></i> <?= substr($f1, 5, 11) ?> <small><?= $karyawan ?></small></td>
                </tr>
              <?php $subTotal = 0;
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

              $show_qty = 0;
              $qty_real = 0;
              if ($f6 < $f16) {
                $qty_real = $f16;
                $show_qty = $f6 . $satuan . " (Min. " . $f16 . $satuan . ")";
              } else {
                $qty_real = $f6;
                $show_qty = $f6 . $satuan;
              }

              $kategori = "";
              foreach ($this->itemGroup as $b) {
                if ($b['id_item_group'] == $f3) {
                  $kategori = $b['item_kategori'];
                }
              }


              $list_layanan = "";
              $arrList_layanan = unserialize($f5);
              $doneLayanan = 0;
              $countLayanan = count($arrList_layanan);
              foreach ($arrList_layanan as $b) {
                $check = 0;
                foreach ($this->dLayanan as $c) {
                  if ($c['id_layanan'] == $b) {
                    foreach ($data['operasi'] as $o) {
                      if ($o['id_penjualan'] == $id && $o['jenis_operasi'] == $b) {
                        $user = "";
                        $check++;
                        foreach ($this->user as $p) {
                          if ($p['id_user'] == $o['id_user_operasi']) {
                            $user = $p['nama_user'];
                          }
                        }
                        $list_layanan = $list_layanan . '<b><i class="fas fa-check-circle text-success"></i> ' . $c['layanan'] . "</b> " . $user . " <span style='white-space: pre;'>(" . substr($o['insertTime'], 5, 11) . ")</span><br>";
                        $doneLayanan++;
                        $forbiddenCount++;
                        $enHapus = false;
                      }
                    }
                    if ($check == 0) {
                      $list_layanan = $list_layanan . "<span class='addOperasi mb-1 rounded'>" . $c['layanan'] . "</span><br>";
                    }
                  }
                }
              }

              $diskon_qty = $f14;
              $diskon_partner = $f15;

              $show_diskon_qty = "";
              if ($diskon_qty > 0) {
                $show_diskon_qty = $diskon_qty . "%";
              }
              $show_diskon_partner = "";
              if ($diskon_partner > 0) {
                $show_diskon_partner = $diskon_partner . "%";
              }
              $plus = "";
              if ($diskon_qty > 0 && $diskon_partner > 0) {
                $plus = " + ";
              }
              $show_diskon = $show_diskon_qty . $plus . $show_diskon_partner;

              $itemList = "";
              $itemListPrint = "";
              if (strlen($f4) > 0) {
                $arrItemList = unserialize($f4);
                $arrCount = count($arrItemList);
                if ($arrCount > 0) {
                  foreach ($arrItemList as $key => $k) {
                    foreach ($this->dItem as $b) {
                      if ($b['id_item'] == $key) {
                        $itemList = $itemList . "<span class='badge badge-light text-dark'>" . $b['item'] . "[" . $k . "]</span> ";
                        $itemListPrint = $itemListPrint . $b['item'] . "[" . $k . "]";
                      }
                    }
                  }
                }
              }

              $total = ($f7 * $qty_real) - (($f7 * $qty_real) * ($f14 / 100));
              $subTotal = $subTotal + $total;

              foreach ($arrRef as $key => $m) {
                if ($key == $noref) {
                  $arrCount = $m;
                }
              }

              $show_total = "";
              $show_total_print = "";

              if (strlen($show_diskon) > 0) {
                $show_total = "<del>" . number_format($f7 * $qty_real) . "</del><br>" . number_format($total);
                $show_total_print = "-" . $show_diskon . " <del>" . number_format($f7 * $qty_real) . "</del> " . number_format($total);
              } else {
                $show_total = number_format($total);
                $show_total_print = number_format($total);
              }

              $alasan = $a['bin_note'];

              $showNote = "";
              if (strlen($f8) > 0) {
                $showNote = $f8;
              } ?>


              <tr id='tr<?= $id ?>'>
                <td>
                  #<?= $id ?> <?= $penjualan ?> <?= $kategori ?><br>
                  <?= $durasi ?> <?= $show_qty ?> <?= $show_diskon ?><br>
                  <?= $list_layanan ?>
                </td>
                <td class="text-end">Rp<?= $show_total ?></td>
              </tr>

              <?php

              $showMutasi = "";
              $userKas = "";
              $totalBayar = 0;
              foreach ($data['kas'] as $ka) {
                if ($ka['ref_transaksi'] == $noref) {
                  foreach ($this->user as $usKas) {
                    if ($usKas['id_user'] == $ka['id_user']) {
                      $userKas = $usKas['nama_user'];
                    }
                  }
                  $showMutasi = $showMutasi . number_format($ka['jumlah']) . " | " . $userKas . " (" . substr($ka['insertTime'], 5, 11) . ")<br>";
                  $totalBayar = $totalBayar + $ka['jumlah'];
                }
              }

              if ($totalBayar > 0) {
                $enHapus = false;
              }

              $sisaTagihan = $subTotal - $totalBayar;
              $showSisa = "";
              if ($sisaTagihan < $subTotal && $sisaTagihan > 0) {
                $showSisa = "(Sisa Rp" . $sisaTagihan . ")";
              }

              if ($arrCount == $no) {

                //SURCAS
                foreach ($data['surcas'] as $sca) {
                  if ($sca['no_ref'] == $noref) {
                    $forbiddenCount++;
                    array_push($arrNoref, $noref);
                  }
                }

                $buttonRestore = "<button data-ref='" . $noref . "' class='restoreRef badge-success mb-1 rounded btn-outline-success'><i class='fas fa-recycle'></i></button> ";
                if ($totalBayar > 0) {
                  $forbiddenCount++;
                  array_push($arrNoref, $noref);
                } ?>

                <tr>
                  <td>
                    <?= $buttonRestore ?>
                    <span class="px-2 rounded ms-2 text-danger border border-danger"><strong><?= $alasan ?></strong></span>
                  </td>
                  <td class="text-end">
                    <span class='text-danger'><small><?= $showMutasi ?></small></span>
                    <?= $showSisa ?>
                    <b>Rp<?= number_format($subTotal) ?></b>
                  </td>
                </tr>

            <?php
                $totalBayar = 0;
                $sisaTagihan = 0;
                $no = 0;
                $subTotal = 0;
                $listPrint = "";
                $listNotif = "";
                $enHapus = false;
              }
            }
            ?>
            </table>
          </div>
      </div>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::ASSETS_URL ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::ASSETS_URL ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<script>
  $(document).ready(function() {
    $("span#forbidden").html("<?= $forbiddenCount ?>");
  });

  $("button.restoreRef").on('click', function(e) {
    e.preventDefault();
    var refNya = $(this).attr('data-ref');
    $.ajax({
      url: '<?= URL::BASE_URL ?>Antrian/restoreRef',
      data: {
        ref: refNya,
      },
      type: "POST",
      success: function(response) {
        loadDiv();
      },
    });
  });

  function loadDiv() {
    $("div#loadContent").load("<?= URL::BASE_URL ?>HapusOrder/index/1")
  }

  $('button.clearHapus').click(function() {
    var dataID = '<?= serialize($arrID) ?>';
    var dataRef = '<?= serialize($arrNoref) ?>';
    var countForbid = <?= $forbiddenCount ?>;
    var countID = <?= count($arrID) ?>;

    if (countForbid > 0) {
      $.ajax({
        url: '<?= URL::BASE_URL ?>HapusOrder/hapusRelated',
        data: {
          'transaksi': 1,
          'dataID': dataID,
          'dataRef': dataRef,
        },
        type: 'POST',
        success: function(res) {
          if (res == 0) {
            loadDiv();
          } else {
            alert(res);
          }
        },
      });
    }
    if (countForbid == 0 && countID > 0) {
      $.ajax({
        url: '<?= URL::BASE_URL ?>HapusOrder/hapusID',
        data: {
          'kolomID': 'id_penjualan',
          'dataID': dataID,
        },
        type: 'POST',
        success: function(res) {
          if (res == 0) {
            location.reload(true);
          } else {
            alert(res);
          }
        },
      });
    }
  });
</script>