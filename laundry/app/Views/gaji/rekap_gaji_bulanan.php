<?php
if (count($data['tanggal']) > 0) {
  $currentMonth =   $data['tanggal']['bulan'];
  $currentYear =   $data['tanggal']['tahun'];
} else {
  $currentMonth = date('m');
  $currentYear = date('Y');
}

$id_user = $data['user']['id'];
$nama_user = "";
foreach ($this->user as $uc) {
  if ($uc['id_user'] == $data['user']['id']) {
    $nama_user = "<small>" . $uc['id_user'] . "</small> - <b>" . $uc['nama_user'] . "</b>";
  }
}
?>

<div class="row g-2 mb-2">
  <div class="col-12">
    <form action="<?= URL::BASE_URL; ?>Gaji" method="POST">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <select name="user_id" class="form-control form-control-sm karyawan" style="width: 100%;" required>
            <option value="" selected disabled>Karyawan</option>
            <?php if (count($this->user) > 0) {
              foreach ($this->user as $a) { ?>
                <option <?php if ($data['user']['id'] == $a['id_user']) {
                          echo "selected";
                        } ?> id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
            <?php }
            } ?>
          </select>
        </div>
        <div class="col-auto">
          <select name="m" class="form-control form-control-sm">
            <option value="01" <?= $currentMonth == '01' ? 'selected' : '' ?>>01</option>
            <option value="02" <?= $currentMonth == '02' ? 'selected' : '' ?>>02</option>
            <option value="03" <?= $currentMonth == '03' ? 'selected' : '' ?>>03</option>
            <option value="04" <?= $currentMonth == '04' ? 'selected' : '' ?>>04</option>
            <option value="05" <?= $currentMonth == '05' ? 'selected' : '' ?>>05</option>
            <option value="06" <?= $currentMonth == '06' ? 'selected' : '' ?>>06</option>
            <option value="07" <?= $currentMonth == '07' ? 'selected' : '' ?>>07</option>
            <option value="08" <?= $currentMonth == '08' ? 'selected' : '' ?>>08</option>
            <option value="09" <?= $currentMonth == '09' ? 'selected' : '' ?>>09</option>
            <option value="10" <?= $currentMonth == '10' ? 'selected' : '' ?>>10</option>
            <option value="11" <?= $currentMonth == '11' ? 'selected' : '' ?>>11</option>
            <option value="12" <?= $currentMonth == '12' ? 'selected' : '' ?>>12</option>
          </select>
        </div>
        <div class="col-auto">
          <?php
          $year = date('Y');
          $oldYear = 2021;
          ?>
          <select name="Y" class="form-control form-control-sm">
            <?php while ($year >= $oldYear) { ?>
              <option value="<?= $year ?>" <?= $currentYear == $year ? 'selected' : '' ?>><?= $year ?></option>
            <?php $year--; } ?>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-success btn-sm">Cek</button>
        </div>
        <div class="col-auto">
          <?php if ($nama_user <> '') { ?>
            <div class="btn-group">
              <button type="button" class="btn btn-sm btn-warning dropdown-toggle" data-bs-toggle="dropdown">
                Set Gaji
              </button>
              <div class="dropdown-menu">
                <a class="dropdown-item" href="#exampleModal" data-bs-toggle="modal">FEE Layanan Laundry</a>
                <a class="dropdown-item" href="#exampleModal1" data-bs-toggle="modal">FEE Pengali</a>
                <a class="dropdown-item" href="#exampleModal2" data-bs-toggle="modal">QTY Pengali</a>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>
    </form>
  </div>
</div>

<?php

$dateOn =  $currentYear . "-" . $currentMonth;
$aDate = strtotime($dateOn);
$bDate = strtotime(date("Y-m"));
$intervalDate = ($bDate - $aDate) / 60 / 60 / 24;


$r = $data['r'];

$r_pengali = array();
$r_pengali_id = array();
foreach ($data['setup']['gaji_pengali'] as $a) {
  $r_pengali[$a['id_karyawan']][$a['id_pengali']] = $a['gaji_pengali'];
  $r_pengali_id[$a['id_karyawan']][$a['id_pengali']] = $a['id_gaji_pengali'];
}

$pengali_list = $data['setup']['pengali_list'];

$totalDapat = 0;
$totalPotong = 0;
$totalTerima = 0;
?>

<div class="row g-2">
  <?php if ($nama_user <> '' && $intervalDate < 60) { ?>
    <div class="col-auto">
      <?php
      echo '<table class="table table-sm m-0 w-100" style="min-width: 300px;">';
      echo '<tbody>';

            echo "<tr>";
            echo "<td colspan='3' class='pb-3'><span>" . strtoupper($nama_user) . " - <b>" . $this->dCabang['kode_cabang'] . "</b></span></td>";
            echo "<td class='text-right'><a href='#' id='tetapkan' class='btn btn-sm btn-danger'>Tetapkan</a></td>";
            echo "</tr>";

            foreach ($r as $userID => $arrJenisJual) {
              $totalGajiLaundry = 0;
              foreach ($this->user as $uc) {
                if ($uc['id_user'] == $userID) {
                  foreach ($arrJenisJual as $jenisJualID => $arrLayanan) {
                    $id_penjualan = "0";
                    $penjualan = "Non";
                    foreach ($this->dPenjualan as $jp) {
                      if ($jp['id_penjualan_jenis'] == $jenisJualID) {
                        $id_penjualan = $jp['id_penjualan_jenis'];
                        $penjualan = $jp['penjualan_jenis'];
                      }
                    }

                    if ($penjualan == "Non") {
                      continue;
                    }

                    $id_layanan = 0;
                    foreach ($arrLayanan as $layananID => $arrCabang) {
                      $layanan = "Non";
                      $totalPerUser = 0;
                      foreach ($this->dLayanan as $dl) {
                        if ($dl['id_layanan'] == $layananID) {
                          $layanan = $dl['layanan'];
                          $id_layanan = $dl['id_layanan'];
                          foreach ($arrCabang as $cabangID => $c) {
                            $totalPerUser = $totalPerUser + $c;
                          }
                        }
                      }

                      if ($layanan == "Non") {
                        continue;
                      }

                      $gaji_laundry = 0;
                      $bonus_target = 0;
                      $target = 0;
                      $id_gl = 0;
                      $max_target = 0;
                      $max_target_fill = 0;
                      foreach ($data['setup']['gaji_laundry'] as $gp) {
                        if ($gp['id_karyawan'] == $id_user && $gp['id_layanan'] == $id_layanan && $gp['jenis_penjualan'] == $id_penjualan) {
                          $gaji_laundry = $gp['gaji_laundry'];
                          $target = $gp['target'];
                          $bonus_target = $gp['bonus_target'];
                          $id_gl = $gp['id_gaji_laundry'];
                          $max_target = $gp['max_target'];
                          $max_target_fill = $max_target;
                        }
                      }

                      $bonus = 0;
                      $xBonus = 0;
                      if ($max_target <> 0) {
                        if ($totalPerUser <= $max_target) {
                          $max_target = $totalPerUser;
                        }
                      } else {
                        $max_target = $totalPerUser;
                      }

                      if ($target > 0) {
                        if ($totalPerUser > 0) {
                          $xBonus = floor($max_target / $target);
                          $bonus = $xBonus * $bonus_target;
                        }
                      }

                      $totalGajiLaundry = $gaji_laundry * $totalPerUser;

                      echo "<tr>";
                      echo "<td nowrap><small>" . $penjualan . "</small><br>" . $layanan . "<br><small>Target</small><br>
                  
                      <span class='edit' data-table='gaji_laundry' data-col='target' data-id_edit='" . $id_gl . "'>" . $target . "</span></td>";
                      echo "<td class='text-right'><small>Qty</small><br>" . number_format($totalPerUser) . "
                      
                      <br><small>Max Target</small><br>
                      <span class='edit' data-table='gaji_laundry' data-col='max_target' data-id_edit='" . $id_gl . "'>" . $max_target_fill . "</span>                  
                  
                  </td>";
                      echo "<td class='text-right'><small>Fee</small><br>Rp
                  
                  <span class='edit' data-table='gaji_laundry' data-col='gaji_laundry' data-id_edit='" . $id_gl . "'>" . $gaji_laundry . "</span>
                  
                  <br><small>Bonus/Target</small><br>
                      <span class='edit' data-table='gaji_laundry' data-col='bonus_target' data-id_edit='" . $id_gl . "'>" . $bonus_target . "</span>
                  
                  </td>";

                      echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($totalGajiLaundry) . "<br><small>Bonus</small><br>Rp" . number_format($bonus) . "</td>";
                      echo "</tr>";

                      $totalDapat += $totalGajiLaundry;
                      $totalDapat += $bonus;
                    }
                  }

                  $totalTerima = 0;
                  foreach ($data['kinerja']['terima'] as $a) {
                    if ($uc['id_user'] == $a['id_user']) {
                      $totalTerima = $totalTerima + $a['terima'];
                    }
                  }

                  if (isset($r_pengali[$id_user][1])) {
                    $feeTerima = $r_pengali[$id_user][1];
                    $id_gp = $r_pengali_id[$id_user][1];
                  } else {
                    $feeTerima = 0;
                    $id_gp = 0;
                  }

                  $totalFeeTerima = $totalTerima * $feeTerima;


                  echo "<tr>";
                  echo "<td nowrap><small>Laundry</small><br>Terima</td>";
                  echo "<td class='text-right'><small>Qty</small><br>" . $totalTerima . "</td>";
                  echo "<td class='text-right'><small>Fee</small><br>Rp
                
                <span class='edit' data-table='gaji_pengali' data-col='gaji_pengali' data-id_pengali='1' data-id_edit='" . $id_gp . "'>" . $feeTerima . "</span>
  
                </td>";
                  echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($totalFeeTerima) . "</td>";
                  echo "</tr>";

                  if ($totalFeeTerima >= 0) {
                    $totalDapat += $totalFeeTerima;
                  }

                  $totalKembali = 0;
                  foreach ($data['kinerja']['kembali'] as $a) {
                    if ($uc['id_user'] == $a['id_user_ambil']) {
                      $totalKembali = $totalKembali + $a['kembali'];
                    }
                  }

                  if (isset($r_pengali[$id_user][2])) {
                    $feeKembali = $r_pengali[$id_user][2];
                    $id_gp = $r_pengali_id[$id_user][2];
                  } else {
                    $feeKembali = 0;
                    $id_gp = 0;
                  }

                  $totalFeeKembali = $totalKembali * $feeKembali;
                  echo "<tr>";
                  echo "<td nowrap class=''><small>Laundry</small><br>Kembali</td>";
                  echo "<td class='text-right'><small>Qty</small><br>" . $totalKembali . "</td>";
                  echo "<td class='text-right'><small>Fee</small><br>Rp
              
              <span class='edit' data-table='gaji_pengali' data-col='gaji_pengali' data-id_pengali='2' data-id_edit='" . $id_gp . "'>" . $feeKembali . "</span>

              </td>";
                  echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($totalFeeKembali) . "</td>";
                  echo "</tr>";

                  if ($totalFeeKembali >= 0) {
                    $totalDapat += $totalFeeKembali;
                  }
                }
              }
            }

            $dataPengali = $data['data'];
            if (count($dataPengali) > 0) {
              $feePTotal = 0;
              foreach ($dataPengali as $b) {
                if ($b['id_karyawan'] == $id_user) {

                  $idPengali = $b['id_pengali'];
                  $idPengaliData = $b['id_pengali_data'];

                  if (isset($r_pengali[$id_user][$idPengali])) {
                    $feeP = $r_pengali[$id_user][$idPengali];
                    $id_gp = $r_pengali_id[$id_user][$idPengali];
                  } else {
                    $feeP = 0;
                    $id_gp = 0;
                  }

                  $pengaliJenis = "";
                  foreach ($pengali_list as $pl) {
                    if ($pl['id_pengali'] == $idPengali) {
                      $pengaliJenis = $pl['pengali_jenis'];
                    }
                  }

                  $qty = $b['qty'];
                  $feePTotal = $qty * $feeP;

                  echo "<tr>";
                  echo "<td nowrap class=''><small>Laundry</small><br>" . $pengaliJenis . "</td>";
                  echo "<td class='text-right'><small>Qty</small><br>
                  
                  <span class='edit' data-table='gaji_pengali_data' data-col='qty' data-id_edit='" . $idPengaliData . "'>" . $qty . "</span>

                  </td>";
                  echo "<td class='text-right'><small>Fee</small><br>Rp
              
              <span class='edit' data-table='gaji_pengali' data-col='gaji_pengali' data-id_edit='" . $id_gp . "'>" . $feeP . "</span>
    
              </td>";
                  echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($feePTotal) . "</td>";
                  echo "</tr>";

                  $totalDapat += $feePTotal;
                }
              }
            }

            //POTONGAN
            if (count($data['kasbon']) > 0) {
              echo "<tr class='table-danger'>";
              echo "<td colspan='4' class='pt-2'>Potongan</td>";
              echo "</tr>";
              foreach ($data['kasbon'] as $uk) {
                $potKasbon = $uk['jumlah'];
                $id_kas = $uk['id_kas'];
                $tgl = substr($uk['insertTime'], 0, 10);
                echo "<tr>";
                echo "<td colspan='3'>Kasbon " . $tgl . "</td>";
                echo "<td class='text-right'>Rp" . number_format($potKasbon) . "</td>";
                echo "</tr>";

                $totalPotong += $potKasbon;
              }
            }

            $totalTerima = $totalDapat - $totalPotong;

      echo '</tbody>';
      echo '</table>';
      ?>
    </div>
  <?php } ?>

    <?php
  $tr_gaji = "";
  $totalGaji = 0;
  $totalPot = 0;
  $totalTer = 0;
  foreach ($data['fix'] as $gf) {
    $jGaji = $gf['jumlah'];
    if ($gf['tipe'] == 1) {
      $totalGaji += $jGaji;
      $vGaji = "Rp" . number_format($gf['jumlah']);
    } else {
      $totalPot += $jGaji;
      $vGaji = "-Rp" . number_format($gf['jumlah']);
    }

    $tr_gaji = $tr_gaji . "<tr><td>" . $gf['ref'] . "<br>" . $gf['deskripsi'] . "</td><td class='text-right'>" . $gf['qty'] . "<br>" . $vGaji . "</td></tr>";
  }
  $totalTer = $totalGaji - $totalPot;
  ?>

  <?php if ($nama_user <> "") { ?>
    <div class="col-auto" id="tes">
      <div class="d-flex gap-2 mb-2">
        <button type="button" class="btn btn-dark btn-sm" id="btnPrintGaji">
          <i class="fas fa-print me-1"></i> Print Slip Gaji
        </button>
      </div>
      <div id="print">
        <table class="table table-sm" style="min-width: 300px;">
          <tr>
            <td colspan="2" class="text-center"><b><?= $this->dCabang['nama'] ?> - <?= $this->dCabang['kode_cabang'] ?></b></td>
          </tr>
          <tr>
            <td colspan="2" class="text-center">-- SALARY SLIP --</td>
          </tr>
          <tr id="dashRow"><td colspan="2"></td></tr>
          <tr>
            <td colspan="2" class="text-center"><b><?= strtoupper($nama_user) ?></b></td>
          </tr>
          <tr>
            <td colspan="2" class="text-center">Periode: <?= $dateOn ?></td>
          </tr>
          <tr id="dashRow"><td colspan="2"></td></tr>

          <?= $tr_gaji ?>

          <tr id="dashRow"><td colspan="2"></td></tr>
          <tr>
            <td>Total Gaji</td>
            <td class="text-right">Rp<?= number_format($totalGaji) ?></td>
          </tr>
          <tr>
            <td>Total Potongan</td>
            <td class="text-right">-Rp<?= number_format($totalPot) ?></td>
          </tr>
          <tr>
            <td>Gaji Diterima</td>
            <td class="text-right"><b>Rp<?= number_format($totalTer) ?></b></td>
          </tr>
          <tr id="dashRow"><td colspan="2"></td></tr>
          <tr>
            <td colspan="2" class="text-center">Terima Kasih</td>
          </tr>
        </table>
      </div>
    </div>
  <?php } ?>
</div>

<div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">FEE Layanan Laundry</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form class="jq" action="<?= URL::BASE_URL; ?>Gaji/set_gaji_laundry" method="POST">
          <div class="card-body">
            <div class="form-group">
              <label for="exampleInputEmail1">Jenis Penjualan</label>
              <select name='penjualan_jenis' class="form-control form-control-sm userChange" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <?php foreach ($this->dPenjualan as $a) { ?>
                  <option id="<?= $a['id_penjualan_jenis'] ?>" value="<?= $a['id_penjualan_jenis'] ?>"><?= $a['penjualan_jenis'] ?></option>
                <?php } ?>
                </optgroup>
              </select>
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Jenis Layanan</label>
              <select name="layanan" class="form-control form-control-sm userChange" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <?php foreach ($this->dLayanan as $a) { ?>
                  <option id="<?= $a['id_layanan'] ?>" value="<?= $a['id_layanan'] ?>"><?= $a['layanan'] ?></option>
                <?php } ?>
                </optgroup>
              </select>
            </div>
            <input name='id_user' type="hidden" value="<?= $data['user']['id'] ?>" />
            <div class="form-group">
              <label for="exampleInputEmail1">Fee (Rp)</label>
              <input type="number" name="fee" min="1" class="form-control" id="exampleInputEmail1" placeholder="" required>
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Target <small>Berlaku Kelipatan</small></label>
              <input type="number" name="target" min="0" class="form-control" value="0" id="exampleInputEmail1" placeholder="" required>
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Max Target <small>(0 Jika Tanpa Max Target)</small></label>
              <input type="number" name="max_target" min="0" class="form-control" value="0" id="exampleInputEmail1" placeholder="" required>
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Bonus Target</label>
              <input type="number" name="bonus_target" min="0" class="form-control" value="0" id="exampleInputEmail1" placeholder="" required>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="exampleModal1" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">FEE Pengali</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form class="jq" action="<?= URL::BASE_URL; ?>Gaji/set_gaji_pengali" method="POST">
          <div class="card-body">
            <div class="form-group">
              <label for="exampleInputEmail1">Jenis Pengali</label>
              <select name="pengali" class="form-control form-control-sm userChange" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <?php foreach ($pengali_list as $a) { ?>
                  <option value="<?= $a['id_pengali'] ?>"><?= $a['pengali_jenis'] ?></option>
                <?php } ?>
              </select>
            </div>
            <input name='id_user' type="hidden" value="<?= $data['user']['id'] ?>" />
            <div class="form-group">
              <label for="exampleInputEmail1">Fee (Rp)</label>
              <input type="number" name="fee" min="1" class="form-control" id="exampleInputEmail1" placeholder="" required>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="exampleModal2" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">QTY Pengali</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form class="jq" action="<?= URL::BASE_URL; ?>Gaji/set_harian_tunjangan" method="POST">
          <div class="card-body">
            <div class="form-group">
              <label for="exampleInputEmail1">Jenis Pengali</label>
              <select name="pengali" class="form-control form-control-sm userChange" style="width: 100%;" required>
                <?php foreach ($pengali_list as $a) {
                  if ($a['id_pengali'] > 2) { ?>
                    <option <?= ($a['id_pengali'] == 4) ? 'selected' : '' ?> value="<?= $a['id_pengali'] ?>"><?= $a['pengali_jenis'] ?></option>
                <?php }
                } ?>
              </select>
            </div>
            <input name='id_user' type="hidden" value="<?= $data['user']['id'] ?>" />
            <input name='tgl' type="hidden" value="<?= $currentYear . "-" . $currentMonth ?>" />
            <div class="form-group">
              <label for="exampleInputEmail1">Qty (Banyak)</label>
              <input type="number" name="qty" min="1" class="form-control" id="exampleInputEmail1" value="1" required>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
  <div id="gajiToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>
<script src="<?= URL::IN_ASSETS ?>js/print_server.js?v=<?= time() ?>"></script>

<script>
  function showToast(msg, type) {
    type = type || 'info';
    var bg = { success: 'bg-success', danger: 'bg-danger', warning: 'bg-warning', info: 'bg-primary' }[type] || 'bg-primary';
    var toastEl = document.getElementById('gajiToast');
    var toastBody = toastEl.querySelector('.toast-body');
    toastEl.className = 'toast align-items-center text-white border-0 ' + bg;
    toastBody.textContent = msg;
    var t = new bootstrap.Toast(toastEl, { delay: 4500 });
    t.show();
  }

  $("form.jq").on("submit", function(e) {
    e.preventDefault();
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      success: function(response) {
        if (response == 1) {
          location.reload(true);
        } else {
          showToast(response, 'warning');
        }
      },
    });
  });

  $("a#tetapkan").click(function() {
    $(".loaderDiv").fadeIn("fast");
    $.ajax({
      url: '<?= URL::BASE_URL ?>Gaji/tetapkan/1',
      data: {
        user_id: '<?= $data['user']['id'] ?>',
        date: '<?= $dateOn ?>'
      },
      type: "POST",
      success: function(res) {
        if (res == 0) {
          location.reload(true);
        } else {
          $(".loaderDiv").fadeOut("slow");
          $("div#tes").html(res);
        }
      },
    });
  });

  var WindowObject;

  // Print Slip Gaji menggunakan Thermal Printer Server
  $('#btnPrintGaji').on('click', function() {
    var btn = $(this);
    
    // Ambil table HTML
    var printTable = $('#print table').clone();
    
    // Process table rows menjadi format text
    var lines = [];
    printTable.find('tr').each(function() {
      var row = $(this);
      var cells = row.find('td');
      
      // Check if dashRow
      if (row.attr('id') === 'dashRow') {
        lines.push('<tr><td>--------------------------------</td></tr>');
        return;
      }
      
      if (cells.length === 0) return;
      
      // Helper function to clean HTML but preserve <b>, <h1>, etc
      var cleanHtml = function(html) {
        var s = html || '';
        // Replace <br> with newline
        s = s.replace(/<br\s*\/?>/gi, '\n');
        // Remove tags EXCEPT <b>, </b>, <h1>, </h1>
        // Keep only supported tags for printer
        s = s.replace(/<(?!\/?(?:b|h1)\b)[^>]+>/gi, '');
        return s.trim();
      };
      
      if (cells.length === 1) {
        // Single column
        var text = cleanHtml(cells.eq(0).html());
        lines.push('<tr><td>' + text + '</td></tr>');
      } else if (cells.length === 2) {
        // Two columns - left and right
        var left = cleanHtml(cells.eq(0).html());
        var right = cleanHtml(cells.eq(1).html());
        
        // Split by newline if exists
        var leftLines = left.split('\n');
        var rightLines = right.split('\n');
        var maxLines = Math.max(leftLines.length, rightLines.length);
        
        for (var i = 0; i < maxLines; i++) {
          var l = leftLines[i] || '';
          var r = rightLines[i] || '';
          lines.push('<tr><td>' + l + '</td><td>' + r + '</td></tr>');
        }
      }
    });
    
    var printText = lines.join('');
    
    if (!printText) {
      showToast('Tidak ada data untuk dicetak', 'warning');
      return;
    }
    
    // Disable button
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Printing...');
    
    // Kirim ke print server / Android Print Bridge (probe + fail-fast)
    var printFn = (window.PrintServer && window.PrintServer.fetch)
      ? function(path, body) { return window.PrintServer.fetch(path, body); }
      : window.printServerFetch;
    var errMsg = (window.PrintServer && window.PrintServer.errorMessage)
      ? window.PrintServer.errorMessage
      : (window.printServerErrorMessage || function() { return 'Print server tidak aktif'; });

    printFn('/print', {
      text: printText,
      margin_top: <?= $this->mdl_setting["margin_printer_top"] ?? 0 ?>,
      feed_lines: <?= $this->mdl_setting["margin_printer_bottom"] ?? 0 ?>
    })
    .then(function(res) {
      console.log('Print server response:', res.status);
      if (!res.ok) {
        showToast('Gagal print: ' + res.status, 'danger');
      } else {
        btn.html('<i class="fas fa-check"></i> Printed!');
        setTimeout(function() {
          btn.html(originalHtml);
          btn.prop('disabled', false);
        }, 2000);
        return;
      }
      btn.prop('disabled', false).html(originalHtml);
    })
    .catch(function(err) {
      console.error('Print error:', err);
      showToast(errMsg(err), 'danger');
      btn.prop('disabled', false).html(originalHtml);
    });
  });

  var click = 0;
  $("span.edit").on('dblclick', function() {
    click = click + 1;
    if (click != 1) {
      return;
    }

    var id_edit = $(this).attr('data-id_edit');
    var value = $(this).html();
    var col = $(this).attr('data-col');
    var table = $(this).attr('data-table');
    var id_pengali = $(this).attr('data-id_pengali');
    var value_before = value;
    var span = $(this);

    var inputId = 'value' + table + '_' + col + '_' + id_edit + '_' + (id_pengali || '0');
    span.html("<input type='number' style='width:70px' id='" + inputId + "' value='" + value + "'>");

    $('#' + inputId).focus();
    $('#' + inputId).focusout(function() {
      var value_after = $(this).val();
      if (value_after === value_before) {
        span.html(value);
        click = 0;
      } else {
        var ajaxData = {
          'id': id_edit,
          'value': value_after,
          'col': col,
          'table': table
        };
        if (id_pengali !== undefined && id_pengali !== '') {
          ajaxData.id_pengali = id_pengali;
        }
        $.ajax({
          url: '<?= URL::BASE_URL ?>Gaji/updateCell',
          data: ajaxData,
          type: 'POST',
          dataType: 'html',
          success: function(response) {
            location.reload(true);
          },
        });
      }
    });
  });
</script>