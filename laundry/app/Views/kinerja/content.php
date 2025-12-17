<?php
if (count($data['dataTanggal']) > 0) {
  if ($data['mode'] == 0) {
    $currentDay = $data['dataTanggal']['tanggal'];
  }
  $currentMonth =   $data['dataTanggal']['bulan'];
  $currentYear =   $data['dataTanggal']['tahun'];
} else {
  if ($data['mode'] == 0) {
    $currentDay =   date('d');
  }
  $currentMonth = date('m');
  $currentYear = date('Y');
}

$r = array();
foreach ($data['data_main'] as $a) {
  $user = $a['id_user_operasi'];
  $cabang = $a['id_cabang'];
  $jenis_operasi = $a['jenis_operasi'];
  $jenis = $a['id_penjualan_jenis'];

  if (isset($r[$user][$jenis][$jenis_operasi][$cabang]) ==  TRUE) {
    $r[$user][$jenis][$jenis_operasi][$cabang] =  $r[$user][$jenis][$jenis_operasi][$cabang] + $a['qty'];
  } else {
    $r[$user][$jenis][$jenis_operasi][$cabang] = $a['qty'];
  }
}

foreach ($data['dTerima'] as $a) {
  $user = $a['id_user'];
  $cabang = $a['id_cabang'];
  $jenis_operasi = 9000;
  $jenis = "9000";

  if (isset($r[$user][$jenis][$jenis_operasi][$cabang]) ==  TRUE) {
    $r[$user][$jenis][$jenis_operasi][$cabang] =  $r[$user][$jenis][$jenis_operasi][$cabang] + $a['terima'];
  } else {
    $r[$user][$jenis][$jenis_operasi][$cabang] = $a['terima'];
  }
}

foreach ($data['dKembali'] as $a) {
  $user = (isset($a['id_user'])) ? $id['id_user'] : 0;
  $cabang = $a['id_cabang'];
  $jenis_operasi = 9001;
  $jenis = "9001";

  if (isset($r[$user][$jenis][$jenis_operasi][$cabang]) ==  TRUE) {
    $r[$user][$jenis][$jenis_operasi][$cabang] =  $r[$user][$jenis][$jenis_operasi][$cabang] + $a['kembali'];
  } else {
    $r[$user][$jenis][$jenis_operasi][$cabang] = $a['kembali'];
  }
}
?>

<div class="row mx-0">
  <div class="col" style="max-width:400px">
    <div class="card">
      <div class="content ms-2 me-1">
        <form action="<?= URL::BASE_URL; ?>Kinerja/index/<?= $data['mode'] ?>" method="POST">
          <table class="table table-sm table-borderless mb-2">
            <tr>
              <?php if ($data['mode'] == 0) { ?>
                <td>
                  <label>Tanggal</label>
                  <select name="d" class="form-control form-control-sm">
                    <option class="text-right" value="01" <?php if ($currentDay == '01') {
                                                            echo 'selected';
                                                          } ?>>01</option>
                    <option class="text-right" value="02" <?php if ($currentDay == '02') {
                                                            echo 'selected';
                                                          } ?>>02</option>
                    <option class="text-right" value="03" <?php if ($currentDay == '03') {
                                                            echo 'selected';
                                                          } ?>>03</option>
                    <option class="text-right" value="04" <?php if ($currentDay == '04') {
                                                            echo 'selected';
                                                          } ?>>04</option>
                    <option class="text-right" value="05" <?php if ($currentDay == '05') {
                                                            echo 'selected';
                                                          } ?>>05</option>
                    <option class="text-right" value="06" <?php if ($currentDay == '06') {
                                                            echo 'selected';
                                                          } ?>>06</option>
                    <option class="text-right" value="07" <?php if ($currentDay == '07') {
                                                            echo 'selected';
                                                          } ?>>07</option>
                    <option class="text-right" value="08" <?php if ($currentDay == '08') {
                                                            echo 'selected';
                                                          } ?>>08</option>
                    <option class="text-right" value="09" <?php if ($currentDay == '09') {
                                                            echo 'selected';
                                                          } ?>>09</option>
                    <option class="text-right" value="10" <?php if ($currentDay == '10') {
                                                            echo 'selected';
                                                          } ?>>10</option>
                    <option class="text-right" value="11" <?php if ($currentDay == '11') {
                                                            echo 'selected';
                                                          } ?>>11</option>
                    <option class="text-right" value="12" <?php if ($currentDay == '12') {
                                                            echo 'selected';
                                                          } ?>>12</option>
                    <option class="text-right" value="13" <?php if ($currentDay == '13') {
                                                            echo 'selected';
                                                          } ?>>13</option>
                    <option class="text-right" value="14" <?php if ($currentDay == '14') {
                                                            echo 'selected';
                                                          } ?>>14</option>
                    <option class="text-right" value="15" <?php if ($currentDay == '15') {
                                                            echo 'selected';
                                                          } ?>>15</option>
                    <option class="text-right" value="16" <?php if ($currentDay == '16') {
                                                            echo 'selected';
                                                          } ?>>16</option>
                    <option class="text-right" value="17" <?php if ($currentDay == '17') {
                                                            echo 'selected';
                                                          } ?>>17</option>
                    <option class="text-right" value="18" <?php if ($currentDay == '18') {
                                                            echo 'selected';
                                                          } ?>>18</option>
                    <option class="text-right" value="19" <?php if ($currentDay == '19') {
                                                            echo 'selected';
                                                          } ?>>19</option>
                    <option class="text-right" value="20" <?php if ($currentDay == '20') {
                                                            echo 'selected';
                                                          } ?>>20</option>
                    <option class="text-right" value="21" <?php if ($currentDay == '21') {
                                                            echo 'selected';
                                                          } ?>>21</option>
                    <option class="text-right" value="22" <?php if ($currentDay == '22') {
                                                            echo 'selected';
                                                          } ?>>22</option>
                    <option class="text-right" value="23" <?php if ($currentDay == '23') {
                                                            echo 'selected';
                                                          } ?>>23</option>
                    <option class="text-right" value="24" <?php if ($currentDay == '24') {
                                                            echo 'selected';
                                                          } ?>>24</option>
                    <option class="text-right" value="25" <?php if ($currentDay == '25') {
                                                            echo 'selected';
                                                          } ?>>25</option>
                    <option class="text-right" value="26" <?php if ($currentDay == '26') {
                                                            echo 'selected';
                                                          } ?>>26</option>
                    <option class="text-right" value="27" <?php if ($currentDay == '27') {
                                                            echo 'selected';
                                                          } ?>>27</option>
                    <option class="text-right" value="28" <?php if ($currentDay == '28') {
                                                            echo 'selected';
                                                          } ?>>28</option>
                    <option class="text-right" value="29" <?php if ($currentDay == '29') {
                                                            echo 'selected';
                                                          } ?>>29</option>
                    <option class="text-right" value="30" <?php if ($currentDay == '30') {
                                                            echo 'selected';
                                                          } ?>>30</option>
                    <option class="text-right" value="31" <?php if ($currentDay == '31') {
                                                            echo 'selected';
                                                          } ?>>31</option>
                  </select>
                </td>
              <?php } ?>
              <td>
                <label>Bulan</label>
                <select name="m" class="form-control form-control-sm">
                  <option class="text-right" value="01" <?php if ($currentMonth == '01') {
                                                          echo 'selected';
                                                        } ?>>01</option>
                  <option class="text-right" value="02" <?php if ($currentMonth == '02') {
                                                          echo 'selected';
                                                        } ?>>02</option>
                  <option class="text-right" value="03" <?php if ($currentMonth == '03') {
                                                          echo 'selected';
                                                        } ?>>03</option>
                  <option class="text-right" value="04" <?php if ($currentMonth == '04') {
                                                          echo 'selected';
                                                        } ?>>04</option>
                  <option class="text-right" value="05" <?php if ($currentMonth == '05') {
                                                          echo 'selected';
                                                        } ?>>05</option>
                  <option class="text-right" value="06" <?php if ($currentMonth == '06') {
                                                          echo 'selected';
                                                        } ?>>06</option>
                  <option class="text-right" value="07" <?php if ($currentMonth == '07') {
                                                          echo 'selected';
                                                        } ?>>07</option>
                  <option class="text-right" value="08" <?php if ($currentMonth == '08') {
                                                          echo 'selected';
                                                        } ?>>08</option>
                  <option class="text-right" value="09" <?php if ($currentMonth == '09') {
                                                          echo 'selected';
                                                        } ?>>09</option>
                  <option class="text-right" value="10" <?php if ($currentMonth == '10') {
                                                          echo 'selected';
                                                        } ?>>10</option>
                  <option class="text-right" value="11" <?php if ($currentMonth == '11') {
                                                          echo 'selected';
                                                        } ?>>11</option>
                  <option class="text-right" value="12" <?php if ($currentMonth == '12') {
                                                          echo 'selected';
                                                        } ?>>12</option>
                </select>
              </td>
              <td>
                <label>Tahun</label>
                <select name="Y" class="form-control form-control-sm">
                  <?php
                  for ($x = URL::FIRST_YEAR; $x <= date('Y'); $x++) { ?>
                    <option class="text-right" value="<?= $x ?>" <?php if ($currentYear == $x) {
                                                                    echo 'selected';
                                                                  } ?>><?= $x ?></option>
                  <?php  }
                  ?>
                </select>
              </td>
              <td style="vertical-align: bottom;">
                <button class="btn btn-sm btn-outline-success w-100">Cek</button>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="row mx-0">
  <?php
  foreach ($r as $userID => $arrJenisJual) {
    foreach ($data['karyawan'] as $uc) {
      if ($uc['id_user'] == $userID) {

        $user = "<small>" . $uc['id_user'] . "</small> - <b>" . $uc['nama_user'] . "<b>";

        echo '<div class="col">';
        echo '<div class="card p-1">';
        echo '<table class="table table-sm table-borderless mb-0">';
        echo '<tbody>';

        echo "<tr>";
        echo "<td colspan='3'>" . strtoupper($user) . "</td>";
        echo "</tr>";


        foreach ($arrJenisJual as $jenisJualID => $arrLayanan) {
          $penjualan = "Non";
          $satuan = "";
          foreach ($this->dPenjualan as $jp) {
            if ($jp['id_penjualan_jenis'] == $jenisJualID) {
              $penjualan = $jp['penjualan_jenis'];
              foreach ($this->dSatuan as $js) {
                if ($js['id_satuan'] == $jp['id_satuan']) {
                  $satuan = $js['nama_satuan'];
                }
              }
            }
          }

          if ($penjualan == "Non") {
            continue;
          }

          echo "<tr class='table-primary'>";
          echo "<td colspan='3'>" . $penjualan . "</td>";
          echo "</tr>";

          foreach ($arrLayanan as $layananID => $arrCabang) {
            $layanan = "Non";
            $totalPerUser = 0;
            foreach ($this->dLayanan as $dl) {
              if ($dl['id_layanan'] == $layananID) {
                $layanan = $dl['layanan'];
                foreach ($arrCabang as $cabangID => $c) {
                  $totalPerUser = $totalPerUser + $c;
                  foreach ($this->listCabang as $lc) {
                    if ($lc['id_cabang'] == $cabangID) {
                      $cabang = $lc['kode_cabang'];
                    }
                  }
                  echo "<tr>";
                  echo "<td nowrap>" . $layanan . " <small>" . $cabang . "</small></td>";
                  echo "<td class='text-right'>" . $c . "</td>";
                  echo "</tr>";
                }
              }
            }
            echo "<tr style='background-color:#F0F8FF'>";
            echo "<td nowrap><small><b>Total </b>" . $penjualan . " " . $layanan . "</small></td>";
            echo "<td class='text-right'><b>" . $totalPerUser . "</b></td>";
            echo "</tr>";
            echo "<tr>";
            echo "<td colspan='3'></td>";
            echo "</tr>";
          }
        }

        echo "<tr class='table-primary'>";
        echo "<td colspan='3'>Pelayanan</td>";

        $totalTerima = 0;
        foreach ($data['dTerima'] as $a) {
          if ($uc['id_user'] == $a['id_user']) {
            foreach ($this->listCabang as $lc) {
              if ($lc['id_cabang'] == $a['id_cabang']) {
                $cabang = $lc['kode_cabang'];
              }
            }
            $totalTerima = $totalTerima + $a['terima'];
            echo "<tr>";
            echo "<td nowrap>Terima " . $cabang . "</td>";
            echo "<td class='text-right'>" . $a['terima'] . "</td>";
            echo "</tr>";
          }
        }
        echo "<tr style='background-color:#F0F8FF'>";
        echo "<td nowrap><small><b>Total </b>Terima</small></td>";
        echo "<td class='text-right'><b>" . $totalTerima . "</b></td>";
        echo "</tr>";

        $totalKembali = 0;
        foreach ($data['dKembali'] as $a) {
          if ($uc['id_user'] == $a['id_user_ambil']) {
            foreach ($this->listCabang as $lc) {
              if ($lc['id_cabang'] == $a['id_cabang']) {
                $cabang = $lc['kode_cabang'];
              }
            }
            $totalKembali = $totalKembali + $a['kembali'];
            echo "<tr>";
            echo "<td nowrap>Kembali " . $cabang . "</td>";
            echo "<td class='text-right'>" . $a['kembali'] . "</td>";
            echo "</tr>";
          }
        }
        echo "<tr style='background-color:#F0F8FF'>";
        echo "<td nowrap><small><b>Total </b>Kembali</small></td>";
        echo "<td class='text-right'><b>" . $totalKembali . "</b></td>";
        echo "</tr>";
        echo "<tr>";
        echo "<td colspan='3'></td>";
        echo "</tr>";

        if (isset($data['absen'][$uc['id_user']])) {
          echo "<tr class='table-primary'>";
          echo "<td colspan='3'>Kehadiran</td>";
          echo "</tr>";

          if ($data['absen'][$uc['id_user']]['harian'] > 0) {
            echo "<tr>";
            echo "<td nowrap>Harian</td>";
            echo "<td class='text-right'><b>" . $data['absen'][$uc['id_user']]['harian'] . "</b></td>";
            echo "</tr>";
          }
          if ($data['absen'][$uc['id_user']]['malam'] > 0) {
            echo "<tr>";
            echo "<td nowrap>Jaga Malam</td>";
            echo "<td class='text-right'><b>" . $data['absen'][$uc['id_user']]['malam'] . "</b></td>";
            echo "</tr>";
          }
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div></div>';
      }
    }
  }
  ?>
</div>