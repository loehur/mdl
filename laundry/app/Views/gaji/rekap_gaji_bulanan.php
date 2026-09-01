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
                Perbaikan Absen
              </button>
              <div class="dropdown-menu">
                <button type="button" class="dropdown-item btnPerbaikanAbsen" data-jenis="0">Cuci</button>
                <button type="button" class="dropdown-item btnPerbaikanAbsen" data-jenis="1">Jaga Malam</button>
                <button type="button" class="dropdown-item btnPerbaikanAbsen" data-jenis="2">Harian — Delivery</button>
                <button type="button" class="dropdown-item btnPerbaikanAbsen" data-jenis="3">Harian — Maintenance</button>
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
$pengali_ref = isset($data['setup']['pengali_ref']) && is_array($data['setup']['pengali_ref'])
  ? $data['setup']['pengali_ref']
  : [1 => 0, 2 => 0];

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
                      $max_target = 0;
                      $max_target_fill = 0;
                      foreach ($data['setup']['gaji_laundry'] as $gp) {
                        if ($gp['id_layanan'] == $id_layanan && $gp['jenis_penjualan'] == $id_penjualan) {
                          $gaji_laundry = $gp['gaji_laundry'];
                          $target = $gp['target'];
                          $bonus_target = $gp['bonus_target'];
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
                      echo "<td nowrap><small>" . $penjualan . "</small><br>" . $layanan . "<br><small>Target</small><br>" . $target . "</td>";
                      echo "<td class='text-right'><small>Qty</small><br>" . number_format($totalPerUser) . "
                      <br><small>Max Target</small><br>" . $max_target_fill . "
                  </td>";
                      echo "<td class='text-right'><small>Fee</small><br>Rp" . number_format($gaji_laundry) . "
                  <br><small>Bonus/Target</small><br>" . number_format($bonus_target) . "
                  </td>";

                      echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($totalGajiLaundry) . "<br><small>Bonus</small><br>Rp" . number_format($bonus) . "</td>";
                      echo "</tr>";

                      $totalDapat += $totalGajiLaundry;
                      $totalDapat += $bonus;
                    }
                  }
                }
              }
            }

            // Terima / Kembali — selalu preview untuk karyawan terpilih
            $totalTerima = 0;
            foreach ($data['kinerja']['terima'] as $a) {
              if ((int) $id_user === (int) $a['id_user']) {
                $totalTerima = $totalTerima + $a['terima'];
              }
            }
            $feeTerima = (int) ($pengali_ref[1] ?? 0);
            $totalFeeTerima = $totalTerima * $feeTerima;
            echo "<tr>";
            echo "<td nowrap><small>Laundry</small><br>Terima</td>";
            echo "<td class='text-right'><small>Qty</small><br>" . $totalTerima . "</td>";
            echo "<td class='text-right'><small>Fee</small><br>Rp" . number_format($feeTerima) . "</td>";
            echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($totalFeeTerima) . "</td>";
            echo "</tr>";
            if ($totalFeeTerima >= 0) {
              $totalDapat += $totalFeeTerima;
            }

            $totalKembali = 0;
            foreach ($data['kinerja']['kembali'] as $a) {
              if ((int) $id_user === (int) $a['id_user_ambil']) {
                $totalKembali = $totalKembali + $a['kembali'];
              }
            }
            $feeKembali = (int) ($pengali_ref[2] ?? 0);
            $totalFeeKembali = $totalKembali * $feeKembali;
            echo "<tr>";
            echo "<td nowrap class=''><small>Laundry</small><br>Kembali</td>";
            echo "<td class='text-right'><small>Qty</small><br>" . $totalKembali . "</td>";
            echo "<td class='text-right'><small>Fee</small><br>Rp" . number_format($feeKembali) . "</td>";
            echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($totalFeeKembali) . "</td>";
            echo "</tr>";
            if ($totalFeeKembali >= 0) {
              $totalDapat += $totalFeeKembali;
            }

            // Pengali: data ditetapkan + preview absen (sebelum Tetapkan)
            $dGajiHelper = $this->helper('D_Gaji');
            $gajiYm = $currentYear . '-' . $currentMonth;
            $pengaliRows = $dGajiHelper->mergePengaliPreviewRows(
              is_array($data['data']) ? $data['data'] : [],
              (int) $id_user,
              $gajiYm
            );
            foreach ($pengaliRows as $b) {
                  $idPengali = (int) $b['id_pengali'];
                  $idPengaliData = (int) ($b['id_pengali_data'] ?? 0);
                  $isPreview = !empty($b['is_preview']);

                  $pengaliJenis = "";
                  foreach ($pengali_list as $pl) {
                    if ((int) $pl['id_pengali'] == $idPengali) {
                      $pengaliJenis = $pl['pengali_jenis'];
                    }
                  }

                  $qtyCell = function ($qty) use ($isPreview, $idPengaliData, $idPengali) {
                    // Tunjangan: qty tetap 1, tidak diedit
                    if ($idPengali === 4) {
                      $label = $isPreview ? "<br><small class='text-muted'>preview</small>" : "";
                      return "1" . $label;
                    }
                    if ($isPreview || $idPengaliData < 1) {
                      return (int) $qty . "<br><small class='text-muted'>preview</small>";
                    }
                    return (int) $qty;
                  };

                  if ($idPengali === 5) {
                    $malam = $dGajiHelper->hitungJumlahGajiMalam((int) $id_user, $gajiYm);
                    $qty = (int) $malam['qty'];
                    if ($qty < 1) {
                      $qty = (int) $b['qty'];
                    }
                    $feeKaryawan = (int) ($malam['fee_karyawan'] ?? ($r_pengali[$id_user][5] ?? 0));
                    $id_gp = isset($r_pengali_id[$id_user][5]) ? (int) $r_pengali_id[$id_user][5] : 0;
                    $feeP = (int) $malam['fee_display'];
                    $feePTotal = (int) $malam['jumlah'];
                    if ($feePTotal < 1 && $qty > 0) {
                      $feeP = $dGajiHelper->feeEfektifSnapshot(
                        $dGajiHelper->feeMalamDariPendapatan(null),
                        $feeKaryawan
                      );
                      $feePTotal = $qty * $feeP;
                    }
                    $sumber = ($feeKaryawan > 0 && $feeKaryawan >= $feeP) ? 'karyawan' : 'global';
                    echo "<tr>";
                    echo "<td nowrap class=''><small>Laundry</small><br>" . htmlspecialchars($pengaliJenis) . ($isPreview ? " <small class='text-muted'>(preview)</small>" : "") . "</td>";
                    echo "<td class='text-right'><small>Qty</small><br>" . $qtyCell($qty) . "</td>";
                    echo "<td class='text-right'><small>Fee efektif</small><br>Rp" . number_format($feeP) . "
                    <br><small>Karyawan</small><br>Rp
                    <span class='edit' data-table='gaji_pengali' data-col='gaji_pengali' data-id_pengali='5' data-id_karyawan='" . (int) $id_user . "' data-id_edit='" . $id_gp . "'>" . $feeKaryawan . "</span>
                    <br><small class='text-muted'>max(global, karyawan) · " . $sumber . "</small></td>";
                    echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($feePTotal) . "</td>";
                    echo "</tr>";
                    $totalDapat += $feePTotal;
                    continue;
                  }

                  if ($idPengali === 6) {
                    $cuci = $dGajiHelper->hitungJumlahGajiCuci((int) $id_user, $gajiYm);
                    $qty = (int) $cuci['qty'];
                    if ($qty < 1) {
                      $qty = (int) $b['qty'];
                    }
                    $feeKaryawan = (int) ($cuci['fee_karyawan'] ?? ($r_pengali[$id_user][6] ?? 0));
                    $id_gp = isset($r_pengali_id[$id_user][6]) ? (int) $r_pengali_id[$id_user][6] : 0;
                    $feeP = (int) $cuci['fee_display'];
                    $feePTotal = (int) $cuci['jumlah'];
                    if ($feePTotal < 1 && $qty > 0) {
                      $feeP = $dGajiHelper->feeEfektifSnapshot(
                        $dGajiHelper->feeCuciDariPendapatan(null),
                        $feeKaryawan
                      );
                      $feePTotal = $qty * $feeP;
                    }
                    $sumber = ($feeKaryawan > 0 && $feeKaryawan >= $feeP) ? 'karyawan' : 'global';
                    echo "<tr>";
                    echo "<td nowrap class=''><small>Laundry</small><br>" . htmlspecialchars($pengaliJenis) . ($isPreview ? " <small class='text-muted'>(preview)</small>" : "") . "</td>";
                    echo "<td class='text-right'><small>Qty</small><br>" . $qtyCell($qty) . "</td>";
                    echo "<td class='text-right'><small>Fee efektif</small><br>Rp" . number_format($feeP) . "
                    <br><small>Karyawan</small><br>Rp
                    <span class='edit' data-table='gaji_pengali' data-col='gaji_pengali' data-id_pengali='6' data-id_karyawan='" . (int) $id_user . "' data-id_edit='" . $id_gp . "'>" . $feeKaryawan . "</span>
                    <br><small class='text-muted'>max(global, karyawan) · " . $sumber . "</small></td>";
                    echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($feePTotal) . "</td>";
                    echo "</tr>";
                    $totalDapat += $feePTotal;
                    continue;
                  }

                  if (isset($r_pengali[$id_user][$idPengali])) {
                    $feeP = $r_pengali[$id_user][$idPengali];
                    $id_gp = $r_pengali_id[$id_user][$idPengali];
                  } else {
                    $feeP = 0;
                    $id_gp = 0;
                  }

                  $qty = (int) $b['qty'];
                  if ($idPengali === 4) {
                    $qty = 1; // Tunjangan: qty maksimal 1
                  }
                  $feePTotal = $qty * $feeP;

                  echo "<tr>";
                  echo "<td nowrap class=''><small>Laundry</small><br>" . htmlspecialchars($pengaliJenis) . ($isPreview ? " <small class='text-muted'>(preview)</small>" : "") . "</td>";
                  echo "<td class='text-right'><small>Qty</small><br>" . $qtyCell($qty) . "</td>";
                  echo "<td class='text-right'><small>Fee</small><br>Rp
              <span class='edit' data-table='gaji_pengali' data-col='gaji_pengali' data-id_pengali='" . $idPengali . "' data-id_karyawan='" . (int) $id_user . "' data-id_edit='" . $id_gp . "'>" . $feeP . "</span>
              </td>";
                  echo "<td class='text-right'><small>Total</small><br>Rp" . number_format($feePTotal) . "</td>";
                  echo "</tr>";

                  $totalDapat += $feePTotal;
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

<div class="modal fade" id="modalPerbaikanAbsen" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Perbaikan Absen — <span id="perbaikanJenis">-</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="small text-muted">Koreksi Absen dari Gaji dapat dilakukan hingga 31 hari ke belakang. Semua validasi jenis tugas, duplikasi, batas harian, dan Access Key tetap berlaku.</p>
      <div class="row g-2 align-items-end mb-3"><div class="col-sm-3"><label class="form-label">Tanggal</label><input id="perbaikanTgl" type="date" class="form-control" min="<?= date('Y-m-d', strtotime('-31 days')) ?>" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div><div class="col-sm-3"><label class="form-label">Cabang absen</label><select id="perbaikanCabang" class="form-control"><?php foreach (($this->listCabang ?? []) as $cb) { ?><option value="<?= (int) $cb['id_cabang'] ?>"><?= htmlspecialchars((string) ($cb['kode_cabang'] ?? $cb['id_cabang']), ENT_QUOTES, 'UTF-8') ?></option><?php } ?></select></div><div class="col-sm-3"><label class="form-label">Jenis tugas</label><select id="perbaikanJenisEdit" class="form-control"><option value="0">Cuci</option><option value="1">Jaga Malam</option><option value="2">Harian — Delivery</option><option value="3">Harian — Maintenance</option></select></div><div class="col-sm-3"><button type="button" id="btnTambahPerbaikan" class="btn btn-primary w-100">Tambah Absen</button></div></div>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Tanggal</th><th>Jam</th><th>Tugas</th><th></th></tr></thead><tbody id="perbaikanAbsenRows"><tr><td colspan="4" class="text-muted">Pilih jenis absen.</td></tr></tbody></table></div>
      <div class="mt-2"><label class="form-label">Access Key (wajib untuk ubah/hapus)</label><input id="perbaikanAccessKey" type="password" maxlength="4" class="form-control" inputmode="numeric" placeholder="4 digit"></div>
    </div>
  </div></div>
</div>

<div class="modal" id="exampleModal1" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" hidden>
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
                <?php foreach ($pengali_list as $a) {
                  $idP = (int) $a['id_pengali'];
                  // 1/2 global di Pengaturan
                  if (in_array($idP, [1, 2], true)) {
                    continue;
                  }
                ?>
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
              <input type="number" name="qty" min="1" class="form-control" id="qtyPengaliInput" value="1" required>
              <small class="text-muted" id="qtyTunjanganHint" style="display:none">Tunjangan: qty maksimal 1</small>
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

  function syncQtyTunjanganLock() {
    var sel = $('#exampleModal2 select[name="pengali"]');
    var inp = $('#qtyPengaliInput');
    var hint = $('#qtyTunjanganHint');
    if (!sel.length || !inp.length) return;
    if (String(sel.val()) === '4') {
      inp.val(1).prop('max', 1).prop('readonly', true);
      hint.show();
    } else {
      inp.prop('max', null).prop('readonly', false);
      hint.hide();
    }
  }
  $('#exampleModal2 select[name="pengali"]').on('change', syncQtyTunjanganLock);
  $('#exampleModal2').on('shown.bs.modal', syncQtyTunjanganLock);
  syncQtyTunjanganLock();

  (function () {
    var labels = { 0: 'Cuci', 1: 'Jaga Malam', 2: 'Harian — Delivery', 3: 'Harian — Maintenance' };
    var jenisAktif = 0;
    var idKaryawan = <?= (int) $id_user ?>;
    var periode = '<?= htmlspecialchars($currentYear . '-' . $currentMonth, ENT_QUOTES, 'UTF-8') ?>';
    var modalEl = document.getElementById('modalPerbaikanAbsen');
    var modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
    function esc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
    function canChange(tanggal) {
      return tanggal >= '<?= date('Y-m-d', strtotime('-31 days')) ?>' && tanggal <= '<?= date('Y-m-d') ?>';
    }
    function loadPerbaikan() {
      var rows = $('#perbaikanAbsenRows');
      rows.html('<tr><td colspan="4" class="text-muted">Memuat…</td></tr>');
      $.getJSON('<?= URL::BASE_URL ?>Absen/gaji_list', { id_karyawan: idKaryawan, periode: periode, jenis: jenisAktif })
        .done(function (res) {
          if (!res || res.code != 1) { rows.html('<tr><td colspan="4" class="text-danger">' + esc((res && res.msg) || 'Gagal memuat') + '</td></tr>'); return; }
          if (!res.data.length) { rows.html('<tr><td colspan="4" class="text-muted">Belum ada absen ' + esc(labels[jenisAktif]) + ' pada periode ini.</td></tr>'); return; }
          rows.html(res.data.map(function (r) {
            var allowed = canChange(String(r.tanggal));
            var actions = allowed
              ? '<button class="btn btn-sm btn-outline-primary me-1 btnUbahPerbaikan" data-id="' + Number(r.id) + '">Ubah</button><button class="btn btn-sm btn-outline-danger btnHapusPerbaikan" data-id="' + Number(r.id) + '">Hapus</button>'
              : '<small class="text-muted">Di luar batas koreksi</small>';
            return '<tr><td>' + esc(r.tanggal) + '</td><td>' + esc(r.jam) + '</td><td>' + esc(labels[Number(r.jenis)] || '-') + ' <small class="text-muted">' + esc(r.kode_cabang || ('C' + Number(r.id_cabang))) + '</small></td><td class="text-end">' + actions + '</td></tr>';
          }).join(''));
        }).fail(function () { rows.html('<tr><td colspan="4" class="text-danger">Gagal memuat data absen.</td></tr>'); });
    }
    $('.btnPerbaikanAbsen').on('click', function () {
      jenisAktif = Number($(this).data('jenis'));
      $('#perbaikanJenis').text(labels[jenisAktif]);
      $('#perbaikanJenisEdit').val(jenisAktif);
      $('#perbaikanAccessKey').val('');
      if (modal) modal.show();
      loadPerbaikan();
    });
    $('#btnTambahPerbaikan').on('click', function () {
      $.post('<?= URL::BASE_URL ?>Absen/absen', { karyawan: idKaryawan, jenis: $('#perbaikanJenisEdit').val(), tgl: 0, tanggal_koreksi: $('#perbaikanTgl').val(), id_cabang: $('#perbaikanCabang').val(), gaji_koreksi: 1 }, function (res) {
        if (res && Number(res.code) === 1) { showToast(res.msg || 'ABSEN SUKSES', 'success'); loadPerbaikan(); }
        else showToast((res && res.msg) || 'Gagal menambah absen', 'warning');
      }, 'json').fail(function () { showToast('Gagal menambah absen', 'danger'); });
    });
    $('#perbaikanAbsenRows').on('click', '.btnUbahPerbaikan', function () {
      var key = $('#perbaikanAccessKey').val();
      if (!key) { showToast('Access Key wajib untuk ubah absen', 'warning'); return; }
      $.post('<?= URL::BASE_URL ?>Absen/ubah', { id: $(this).data('id'), jenis: $('#perbaikanJenisEdit').val(), access_key: key, gaji_koreksi: 1 }, function (res) {
        if (res && Number(res.code) === 1) { showToast(res.msg, 'success'); loadPerbaikan(); } else showToast((res && res.msg) || 'Gagal memperbaiki absen', 'warning');
      }, 'json');
    }).on('click', '.btnHapusPerbaikan', function () {
      var key = $('#perbaikanAccessKey').val();
      if (!key) { showToast('Access Key wajib untuk hapus absen', 'warning'); return; }
      if (!window.confirm('Hapus absen ini?')) return;
      $.post('<?= URL::BASE_URL ?>Absen/hapus', { id: $(this).data('id'), access_key: key, gaji_koreksi: 1 }, function (res) {
        if (res && Number(res.code) === 1) { showToast(res.msg, 'success'); loadPerbaikan(); } else showToast((res && res.msg) || 'Gagal menghapus absen', 'warning');
      }, 'json');
    });
  }());

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
      var msg = errMsg(err);
      if (window.PrintServer && typeof window.PrintServer.showAlert === 'function') {
        window.PrintServer.showAlert(msg, 'warning');
      } else if (window.MdlToast) {
        MdlToast.warn(msg);
      } else {
        showToast(msg, 'warning');
      }
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
        var id_karyawan = span.attr('data-id_karyawan');
        if (id_karyawan !== undefined && id_karyawan !== '') {
          ajaxData.id_karyawan = id_karyawan;
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
