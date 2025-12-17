<?php
if (count($data['dataTanggal']) > 0) {
  $currentMonth = $data['dataTanggal']['bulan'];
  $currentYear = $data['dataTanggal']['tahun'];
} else {
  $currentMonth = date('m');
  $currentYear = date('Y');
}

$currentDay = isset($data['dataTanggal']['tanggal']) ? $data['dataTanggal']['tanggal'] : date('d');

$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri_segments = explode('/', $uri_path);
$uriCount = count($uri_segments);
$target_page_rekap = $uri_segments[$uriCount - 1];
?>



<div class="row mx-0">
  <div class="col" style="max-width: 500px;">
    <div class="card">
      <div class="content mx-1">
        <form action="<?= URL::BASE_URL; ?>Rekap/i/<?= $target_page_rekap ?>" method="POST">
          <table class="table table-sm table-borderless mb-2">
            <tr>
              <?php if (isset($data['dataTanggal']['tanggal'])) { ?>
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
              <td style="vertical-align: bottom;">
                <button class="btn btn-sm btn-outline-success w-100">Cek</button>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>

    <div class="card">
      <?php
      $rekap = array();
      $rekapQty = array();
      foreach ($data['data_main'] as $a) {
        $serLayanan = $a['list_layanan'];
        if (isset($rekap[$a['id_penjualan_jenis']][$serLayanan]) ==  TRUE) {
          $rekap[$a['id_penjualan_jenis']][$serLayanan] =  $rekap[$a['id_penjualan_jenis']][$serLayanan] + $a['qty'];
        } else {
          $rekap[$a['id_penjualan_jenis']][$serLayanan] = $a['qty'];
        }

        if (isset($rekapQty[$a['id_penjualan_jenis']]) ==  TRUE) {
          $rekapQty[$a['id_penjualan_jenis']] =  $rekapQty[$a['id_penjualan_jenis']] + $a['qty'];
        } else {
          $rekapQty[$a['id_penjualan_jenis']] = $a['qty'];
        }
      }
      ?>
      <div class="card-body mt-1 p-0 table-responsive-sm">
        <table class="table table-sm w-100">
          <thead>
            <tr>
              <th colspan="3" class="text-success border-success">Pendapatan</th>
            </tr>
          </thead>
          <tbody>
            <?php
            foreach ($rekapQty as $keyA => $a) {
              foreach ($this->dPenjualan as $b) {
                if ($b['id_penjualan_jenis'] == $keyA) {
                  $jenisPenjualan = $b['penjualan_jenis'];
                  $unit = "";
                  foreach ($this->dSatuan as $sa) {
                    if ($sa['id_satuan'] == $b['id_satuan']) {
                      $unit = $sa['nama_satuan'];
                    }
                  }
                  echo "<tr>";
                  echo "<td class='text-primary'>" . $jenisPenjualan . "</td>";
                  echo "<td class='text-right'>" . $a . " " . $unit . "</td>";
                  echo '<td style="width: 70px;"></td>';
                  echo "</tr>";
                }
              }
            }
            ?>
          </tbody>
        </table>
      </div>
      <br>
      <div class="card-body p-0 table-responsive-sm">
        <table class="table table-sm w-100">
          <tbody>
            <?php
            $jenisPenjualan = "";
            $jenisPenjualanBefore = "";

            foreach ($rekap as $keyA => $a) {
              foreach ($this->dPenjualan as $b) {
                if ($b['id_penjualan_jenis'] == $keyA) {
                  $unit = "";
                  foreach ($this->dSatuan as $sa) {
                    if ($sa['id_satuan'] == $b['id_satuan']) {
                      $unit = $sa['nama_satuan'];
                    }
                  }

                  foreach ($a as $keyB => $c) {
                    $serLayanan = $keyB;
                    $arrLayanan = unserialize($keyB);
                    $layanan = "";
                    foreach ($arrLayanan as $d) {
                      foreach ($this->dLayanan as $e) {
                        if ($d == $e['id_layanan']) {
                          $layanan = $layanan . " " . $e['layanan'];
                        }
                      }
                    }
                    $jenisPenjualan = $b['penjualan_jenis'];
                    if ($jenisPenjualan == $jenisPenjualanBefore) {
                      $jenisPenjualan = "";
                    }
                    echo "<tr>";
                    echo "<td class='text-primary'>" . $jenisPenjualan . "</td>";
                    echo "<td>" . $layanan . "</td>";
                    echo "<td class='text-right'>" . $c . " " . $unit . "</td>";
                    echo '<td style="width: 70px;"></td>';
                    echo "</tr>";
                    $jenisPenjualanBefore = $b['penjualan_jenis'];
                  }
                }
              }
            }
            ?>
          </tbody>
        </table>
      </div>

      <?php $total_pendapatan = $data['kasLaundry'] + $data['kasMember']; ?>

      <br>
      <div class="card-body p-0 table-responsive-sm">
        <table class="table table-sm w-100">
          <tbody>
            <tr>
              <td>Pendapatan Laundry <span class="text-primary">Umum</span></td>
              <td class="text-right">Rp<?= number_format($data['kasLaundry']) ?></td>
              <td><a href="<?= URL::BASE_URL ?>Rekap/detail/<?= base64_encode($data['whereUmum']) ?>/1">Detail</a></td>
            </tr>
            <tr>
              <td>Pendapatan Laundry <span class="text-success">Member</span></td>
              <td class="text-right">Rp<?= number_format($data['kasMember']) ?></td>
              <td><a href="<?= URL::BASE_URL ?>Rekap/detail/<?= base64_encode($data['whereMember']) ?>/2">Detail</a></td>
            </tr>
            <tr class="table-success">
              <td class="fw-bold">Total Pendapatan</td>
              <td class="text-right fw-bold">Rp<?= number_format($total_pendapatan) ?></td>
              <td class="text-right" style="width: 70px;"></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0 table-responsive-sm">
        <table class="table table-sm w-100">
          <thead>
            <tr>
              <th colspan="3" class="text-danger border-danger">Pengeluaran</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $total_keluar = 0;
            foreach ($data['kas_keluar'] as $a) {
              echo "<tr>";
              echo "<td class=''>" . $a['note_primary'] . "</td>";
              echo "<td class='text-right'>Rp" . number_format($a['total']) . "</td>";
              echo "<td style='width: 70px;'></td>";
              echo "</tr>";
              $total_keluar += $a['total'];
            }

            $gaji = $data['gaji'];
            $gaji = (int)$gaji;

            if ($gaji > 0) {
              echo "<tr>";
              echo "<td class=''>Gaji Karyawan</td>";
              echo "<td class='text-right'>Rp" . number_format($gaji) . "</td>";
              echo "<td style='width: 70px;'></td>";
              echo "</tr>";
              $total_keluar += $gaji;
            }

            $total_keluar += $data['prepost_cost'];
            ?>
            <tr>
              <td>Pre/Post Paid</td>
              <td class="text-end"><?= number_format($data['prepost_cost']) ?></td>
              <td></td>
            </tr>
            <tr class="table-danger">
              <td><b>Total Pengeluaran</b></td>
              <td class="text-right"><b>Rp<?= number_format($total_keluar) ?></b></td>
              <td style="width: 70px;"><a href="<?= URL::BASE_URL ?>Rekap/detail/<?= base64_encode($data['whereKeluar']) ?>/3">Detail</a></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-body m-0 p-0 table-responsive-sm">
        <table class="table table-sm w-100">
          <tbody>

            <?php
            echo "<tr class='table-primary'>";
            echo "<td class='fw-bold'>Laba/Rugi</td>";
            echo "<td class='text-right'><b>Rp " . number_format($total_pendapatan - $total_keluar) . "</b></td>";
            echo "<td style='width: 70px;'></td>";
            echo "</tr>";
            ?>
          </tbody>
        </table>
      </div>
    </div>

    <hr>
    <div class="card">
      <div class="card-body p-0 table-responsive-sm">
        <table class="table table-sm w-100">
          <thead>
            <tr>
              <th colspan="3" class="text-secondary border-secondary">Penarikan</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $total_tarik = 0;
            foreach ($data['kas_tarik'] as $a) {
              $total_tarik += $a['total'];
            }

            ?>
            <tr class="table-secondary">
              <td><b>Total Penarikan</b></td>
              <td class="text-right"><b>Rp<?= number_format($total_tarik) ?></b></td>
              <td style='width: 70px;'><a href="<?= URL::BASE_URL ?>Rekap/detail/<?= base64_encode($data['whereTarik']) ?>/3">Detail</a></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
</div>
</div>