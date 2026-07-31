<?php
$dataTanggal = $data['dataTanggal'];
if (count($dataTanggal) > 0) {
  $currentMonth = $dataTanggal['bulan'];
  $currentYear = $dataTanggal['tahun'];
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
              <td>
                <label>Tahun</label>
                <select name="y" class="form-control form-control-sm">
                  <?php $start_year = 2021;
                    while($start_year <= date('Y')) {?>
                      <option value="<?php echo $start_year; ?>" <?= $start_year == $dataTanggal['tahun'] ? 'selected' : '' ?>><?php echo $start_year; ?></option>
                      <?php
                      $start_year++;
                    }
                  ?>
                </select>
              </td>
              <td style="vertical-align: bottom;">
                <button class="btn btn-sm btn-outline-success w-100">Cek</button>
              </td>
            </tr>
          </table>
        </form>
        <?php
        $isMonthlyRekap = !isset($data['dataTanggal']['tanggal']);
        $rekapMode = (int) ($data['rekap_mode'] ?? $target_page_rekap);
        $canSnapshotUi = $isMonthlyRekap && in_array($rekapMode, [2, 3], true);
        $periodeUi = sprintf('%04d-%02d', (int) $currentYear, (int) $currentMonth);
        $isPastMonth = $periodeUi < date('Y-m');
        $hasSnapshot = !empty($data['snapshot']);
        $snapshotMeta = $data['snapshot_meta'] ?? null;
        $snapshotAt = '';
        if ($hasSnapshot && is_array($data['snapshot'] ?? null)) {
          $snapshotAt = ($data['snapshot']['updated_at'] ?? '') ?: ($data['snapshot']['created_at'] ?? '');
        }
        if ($canSnapshotUi) {
          $statusHint = '';
          if (!$isPastMonth) {
            $statusHint = 'Snapshot hanya untuk bulan yang sudah berlalu';
          } elseif ($hasSnapshot) {
            if ($rekapMode === 3 && is_array($snapshotMeta)) {
              $statusHint = 'Tersimpan semua cabang (' . (int) ($snapshotMeta['count'] ?? 0) . '/' . (int) ($snapshotMeta['total'] ?? 0) . ')';
            } else {
              $statusHint = 'Tersimpan' . ($snapshotAt ? ': ' . htmlspecialchars($snapshotAt, ENT_QUOTES, 'UTF-8') : '');
            }
          } else {
            if ($rekapMode === 3 && is_array($snapshotMeta) && (int) ($snapshotMeta['count'] ?? 0) > 0) {
              $statusHint = 'Sebagian: ' . (int) $snapshotMeta['count'] . '/' . (int) $snapshotMeta['total'] . ' cabang';
            } else {
              $statusHint = 'Belum ada snapshot';
            }
          }
        ?>
          <div class="d-flex align-items-center flex-wrap gap-2 px-1 pb-2" id="rekapSnapshotBar"
            data-mode="<?= $rekapMode ?>"
            data-periode="<?= htmlspecialchars($periodeUi, ENT_QUOTES, 'UTF-8') ?>"
            data-past="<?= $isPastMonth ? '1' : '0' ?>"
            data-has="<?= $hasSnapshot ? '1' : '0' ?>">
            <button type="button" id="btnSnapshotRekap"
              class="btn btn-sm <?= $hasSnapshot ? 'btn-outline-secondary' : 'btn-outline-primary' ?>"
              <?= ($isPastMonth && !$hasSnapshot) ? '' : 'disabled' ?>
              <?= !$isPastMonth ? 'title="Hanya bulan yang telah berlalu"' : ($hasSnapshot ? 'title="Snapshot sudah ada"' : '') ?>>
              Snapshot
            </button>
            <small id="snapshotStatus" class="text-muted"><?= $statusHint ?></small>
          </div>
        <?php } ?>
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
              <th colspan="2" class="text-success border-success">Pendapatan</th>
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

      <?php $total_pendapatan = $data['kasLaundry'] + $data['kasMember'] + ($data['margin_penjualan'] ?? 0); ?>

      <br>
      <div class="card-body p-0 table-responsive-sm">
        <table class="table table-sm w-100">
          <tbody>
            <tr>
              <td>Pendapatan Laundry <span class="text-primary">Umum</span></td>
              <td class="text-right">Rp<?= number_format($data['kasLaundry']) ?></td>
            </tr>
            <tr>
              <td>Pendapatan Laundry <span class="text-success">Member</span></td>
              <td class="text-right">Rp<?= number_format($data['kasMember']) ?></td>
            </tr>
            <tr role="button" class="align-middle" id="rekapMarginRow" style="cursor:pointer" title="Klik untuk rincian"
              data-rekap-mode="<?= (int) $target_page_rekap ?>"
              data-y="<?= htmlspecialchars((string) $currentYear, ENT_QUOTES, 'UTF-8') ?>"
              data-m="<?= htmlspecialchars((string) $currentMonth, ENT_QUOTES, 'UTF-8') ?>"
              data-d="<?= htmlspecialchars((string) $currentDay, ENT_QUOTES, 'UTF-8') ?>">
              <td>Margin Penjualan Barang <i class="fas fa-list-ul text-secondary small ms-1" aria-hidden="true"></i></td>
              <td class="text-right">Rp<?= number_format($data['margin_penjualan'] ?? 0) ?></td>
            </tr>
            <tr class="table-success">
              <td class="fw-bold">Total Pendapatan</td>
              <td class="text-right fw-bold">Rp<?= number_format($total_pendapatan) ?></td>
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
              <th colspan="2" class="text-danger border-danger">Pengeluaran</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $total_keluar = 0;
            foreach ($data['kas_keluar'] as $a) {
              echo "<tr>";
              echo "<td class=''>" . $a['note_primary'] . "</td>";
              echo "<td class='text-right'>Rp" . number_format($a['total']) . "</td>";
              echo "</tr>";
              $total_keluar += $a['total'];
            }

            $gaji = $data['gaji'];
            $gaji = (int)$gaji;

            if ($gaji > 0) {
              echo "<tr>";
              echo "<td class=''>Gaji Karyawan</td>";
              echo "<td class='text-right'>Rp" . number_format($gaji) . "</td>";
              echo "</tr>";
              $total_keluar += $gaji;
            }

            $total_keluar += $data['prepost_cost'];
            ?>
            <tr role="button" class="align-middle" id="rekapPrepostRow" style="cursor:pointer" title="Klik untuk rincian per cabang"
              data-rekap-mode="<?= (int) $target_page_rekap ?>"
              data-y="<?= htmlspecialchars((string) $currentYear, ENT_QUOTES, 'UTF-8') ?>"
              data-m="<?= htmlspecialchars((string) $currentMonth, ENT_QUOTES, 'UTF-8') ?>"
              data-d="<?= htmlspecialchars((string) $currentDay, ENT_QUOTES, 'UTF-8') ?>">
              <td>Pre/Post Paid <i class="fas fa-list-ul text-secondary small ms-1" aria-hidden="true"></i></td>
              <td class="text-end">Rp<?= number_format($data['prepost_cost']) ?></td>
            </tr>
            <?php
            $barang_pakai = $data['barang_pakai'] ?? 0;
            if ($barang_pakai > 0) {
              $total_keluar += $barang_pakai;
            }
            ?>
            <tr role="button" class="align-middle<?= $barang_pakai > 0 ? '' : ' text-muted' ?>" id="rekapBarangPakaiRow" style="cursor:pointer" title="Klik untuk rincian"
              data-rekap-mode="<?= (int) $target_page_rekap ?>"
              data-y="<?= htmlspecialchars((string) $currentYear, ENT_QUOTES, 'UTF-8') ?>"
              data-m="<?= htmlspecialchars((string) $currentMonth, ENT_QUOTES, 'UTF-8') ?>"
              data-d="<?= htmlspecialchars((string) $currentDay, ENT_QUOTES, 'UTF-8') ?>">
              <td>Barang Pakai <i class="fas fa-list-ul text-secondary small ms-1" aria-hidden="true"></i></td>
              <td class="text-right">Rp<?= number_format($barang_pakai) ?></td>
            </tr>
            <tr class="table-danger">
              <td><b>Total Pengeluaran</b></td>
              <td class="text-right"><b>Rp<?= number_format($total_keluar) ?></b></td>
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
            echo "</tr>";
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
</div>

<div class="modal fade" id="modalMarginDetail" tabindex="-1" aria-labelledby="modalMarginDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="modalMarginDetailLabel">Rincian Margin Penjualan Barang</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-2">
        <p class="small text-muted mb-2" id="modalMarginPeriod"></p>
        <div id="modalMarginLoading" class="text-center py-3 d-none">Memuat…</div>
        <div id="modalMarginError" class="alert alert-danger py-2 small d-none"></div>
        <div id="modalMarginRekapWrap" class="d-none mb-2">
          <p class="small fw-semibold text-success mb-1">Rekapitulasi per Barang</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th>Barang</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Jumlah</th>
                </tr>
              </thead>
              <tbody id="modalMarginRekapTbody"></tbody>
              <tfoot class="table-secondary fw-bold">
                <tr>
                  <td>Total</td>
                  <td class="text-end" id="modalMarginRekapQty">0</td>
                  <td class="text-end" id="modalMarginRekapSum">0</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <p class="small fw-semibold mb-1 mt-3">Rincian Transaksi</p>
        </div>
        <div id="modalMarginDetailWrap" class="d-none">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr id="modalMarginHeadRow">
                <th>Ref</th>
                <th>Tanggal</th>
                <th class="modalMarginColCabang d-none">Cabang</th>
                <th>Barang</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Margin</th>
                <th class="text-end">Jumlah</th>
              </tr>
            </thead>
            <tbody id="modalMarginTbody"></tbody>
            <tfoot id="modalMarginTfoot" class="table-secondary fw-bold d-none">
              <tr>
                <td colspan="6" id="modalMarginFootLabel">Total</td>
                <td class="text-end" id="modalMarginFootSum">0</td>
              </tr>
            </tfoot>
          </table>
        </div>
        </div>
        <p class="small text-muted mb-0 mt-2 d-none" id="modalMarginEmpty">Tidak ada transaksi pada periode ini.</p>
      </div>
      <div class="modal-footer py-1">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalBarangPakaiDetail" tabindex="-1" aria-labelledby="modalBarangPakaiDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="modalBarangPakaiDetailLabel">Rincian Barang Pakai</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-2">
        <p class="small text-muted mb-2" id="modalBarangPakaiPeriod"></p>
        <div id="modalBarangPakaiLoading" class="text-center py-3 d-none">Memuat…</div>
        <div id="modalBarangPakaiError" class="alert alert-danger py-2 small d-none"></div>
        <div id="modalBarangPakaiRekapWrap" class="d-none mb-2">
          <p class="small fw-semibold text-danger mb-1">Rekapitulasi per Barang</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th>Barang</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Jumlah</th>
                </tr>
              </thead>
              <tbody id="modalBarangPakaiRekapTbody"></tbody>
              <tfoot class="table-secondary fw-bold">
                <tr>
                  <td>Total</td>
                  <td class="text-end" id="modalBarangPakaiRekapQty">0</td>
                  <td class="text-end" id="modalBarangPakaiRekapSum">0</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <p class="small fw-semibold mb-1 mt-3">Rincian Transaksi</p>
        </div>
        <div id="modalBarangPakaiDetailWrap" class="d-none">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>Ref</th>
                <th>Tanggal</th>
                <th class="modalPakaiColCabang d-none">Cabang</th>
                <th>Barang</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Harga</th>
                <th class="text-end">Jumlah</th>
              </tr>
            </thead>
            <tbody id="modalBarangPakaiTbody"></tbody>
            <tfoot id="modalBarangPakaiTfoot" class="table-secondary fw-bold d-none">
              <tr>
                <td colspan="6" id="modalBarangPakaiFootLabel">Total</td>
                <td class="text-end" id="modalBarangPakaiFootSum">0</td>
              </tr>
            </tfoot>
          </table>
        </div>
        </div>
        <p class="small text-muted mb-0 mt-2 d-none" id="modalBarangPakaiEmpty">Tidak ada transaksi pada periode ini.</p>
      </div>
      <div class="modal-footer py-1">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalPrepostDetail" tabindex="-1" aria-labelledby="modalPrepostDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="modalPrepostDetailLabel">Rincian Pre/Post Paid</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-2">
        <p class="small text-muted mb-1" id="modalPrepostPeriod"></p>
        <p class="small text-muted mb-2 d-none" id="modalPrepostHint">Klik baris cabang untuk melihat rincian Pre/Post Paid.</p>
        <div id="modalPrepostLoading" class="text-center py-3 d-none">Memuat…</div>
        <div id="modalPrepostError" class="alert alert-danger py-2 small d-none"></div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>Cabang</th>
                <th class="text-end">Prepaid</th>
                <th class="text-end">Postpaid</th>
                <th class="text-end">Jumlah</th>
              </tr>
            </thead>
            <tbody id="modalPrepostTbody"></tbody>
            <tfoot id="modalPrepostTfoot" class="table-secondary fw-bold d-none">
              <tr>
                <td>Total</td>
                <td class="text-end" id="modalPrepostFootPre">0</td>
                <td class="text-end" id="modalPrepostFootPost">0</td>
                <td class="text-end" id="modalPrepostFootSum">0</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p class="small text-muted mb-0 mt-2 d-none" id="modalPrepostEmpty">Tidak ada transaksi pada periode ini.</p>
      </div>
      <div class="modal-footer py-1">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  if (typeof bootstrap === 'undefined') return;

  var fmt = function (n) {
    try {
      return new Intl.NumberFormat('id-ID').format(n);
    } catch (e) {
      return String(n);
    }
  };

  var fmtQty = function (n) {
    var x = parseFloat(n);
    if (isNaN(x)) return String(n);
    if (Math.abs(x - Math.round(x)) < 0.0001) {
      return fmt(Math.round(x));
    }
    return String(n);
  };

  var periodParams = function (row) {
    return '?y=' + encodeURIComponent(row.getAttribute('data-y') || '')
      + '&m=' + encodeURIComponent(row.getAttribute('data-m') || '')
      + '&d=' + encodeURIComponent(row.getAttribute('data-d') || '');
  };

  var resetModal = function (ids) {
    document.getElementById(ids.tbody).innerHTML = '';
    if (ids.rekapTbody) {
      document.getElementById(ids.rekapTbody).innerHTML = '';
    }
    if (ids.rekapWrap) {
      document.getElementById(ids.rekapWrap).classList.add('d-none');
    }
    if (ids.detailWrap) {
      document.getElementById(ids.detailWrap).classList.add('d-none');
    }
    var errEl = document.getElementById(ids.error);
    errEl.classList.add('d-none');
    errEl.textContent = '';
    document.getElementById(ids.empty).classList.add('d-none');
    document.getElementById(ids.tfoot).classList.add('d-none');
    document.getElementById(ids.loading).classList.remove('d-none');
  };

  var openFetchModal = function (row, url, ids, onSuccess) {
    resetModal(ids);
    bootstrap.Modal.getOrCreateInstance(document.getElementById(ids.modal)).show();
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        document.getElementById(ids.loading).classList.add('d-none');
        if (!data || !data.ok) {
          var errEl = document.getElementById(ids.error);
          errEl.textContent = (data && data.msg) ? data.msg : 'Gagal memuat data.';
          errEl.classList.remove('d-none');
          return;
        }
        document.getElementById(ids.period).textContent = 'Periode: ' + (data.period_label || '');
        onSuccess(data);
      })
      .catch(function () {
        document.getElementById(ids.loading).classList.add('d-none');
        var errEl = document.getElementById(ids.error);
        errEl.textContent = 'Gagal memuat data.';
        errEl.classList.remove('d-none');
      });
  };

  var bindRow = function (rowId, buildUrl, ids, onSuccess) {
    var row = document.getElementById(rowId);
    if (!row) return;
    row.addEventListener('click', function () {
      var mode = row.getAttribute('data-rekap-mode') || '1';
      openFetchModal(row, buildUrl(mode) + periodParams(row), ids, onSuccess);
    });
  };

  bindRow('rekapPrepostRow', function (mode) {
    return '<?= URL::BASE_URL ?>Rekap/prepost_detail/' + encodeURIComponent(mode);
  }, {
    modal: 'modalPrepostDetail',
    loading: 'modalPrepostLoading',
    error: 'modalPrepostError',
    tbody: 'modalPrepostTbody',
    tfoot: 'modalPrepostTfoot',
    empty: 'modalPrepostEmpty',
    period: 'modalPrepostPeriod'
  }, function (data) {
    var rows = data.rows || [];
    if (rows.length === 0) {
      document.getElementById('modalPrepostEmpty').classList.remove('d-none');
      document.getElementById('modalPrepostHint').classList.add('d-none');
      return;
    }
    document.getElementById('modalPrepostHint').classList.remove('d-none');
    var tbody = document.getElementById('modalPrepostTbody');

    var buildPrepostDetailRow = function (details) {
      var detailTr = document.createElement('tr');
      detailTr.className = 'prepost-detail-row d-none table-light';
      var detailTd = document.createElement('td');
      detailTd.colSpan = 4;
      detailTd.className = 'p-0';
      if (!details || details.length === 0) {
        detailTd.className = 'p-2 small text-muted';
        detailTd.textContent = 'Tidak ada rincian transaksi.';
        detailTr.appendChild(detailTd);
        return detailTr;
      }
      var inner = document.createElement('table');
      inner.className = 'table table-sm table-bordered mb-0 bg-white';
      var iHead = document.createElement('thead');
      iHead.className = 'table-secondary';
      iHead.innerHTML = '<tr><th>Tipe</th><th>Keterangan</th><th>Tanggal</th><th class="text-end">Jumlah</th></tr>';
      inner.appendChild(iHead);
      var iBody = document.createElement('tbody');
      details.forEach(function (d) {
        var itr = document.createElement('tr');
        var it0 = document.createElement('td');
        it0.className = d.tipe === 'Prepaid' ? 'text-primary' : 'text-danger';
        it0.textContent = d.tipe || '-';
        var it1 = document.createElement('td');
        it1.textContent = d.keterangan || '-';
        var it2 = document.createElement('td');
        it2.className = 'text-nowrap';
        it2.textContent = d.tanggal || '-';
        var it3 = document.createElement('td');
        it3.className = 'text-end';
        it3.textContent = 'Rp' + fmt(d.jumlah || 0);
        itr.appendChild(it0);
        itr.appendChild(it1);
        itr.appendChild(it2);
        itr.appendChild(it3);
        iBody.appendChild(itr);
      });
      inner.appendChild(iBody);
      detailTd.appendChild(inner);
      detailTr.appendChild(detailTd);
      return detailTr;
    };

    rows.forEach(function (x) {
      var tr = document.createElement('tr');
      tr.className = 'prepost-branch-row align-middle';
      tr.setAttribute('role', 'button');
      tr.setAttribute('data-expanded', '0');
      tr.style.cursor = 'pointer';
      tr.title = 'Klik untuk rincian';

      var td0 = document.createElement('td');
      var chevron = document.createElement('i');
      chevron.className = 'fas fa-chevron-right prepost-chevron small text-secondary me-1';
      chevron.setAttribute('aria-hidden', 'true');
      td0.appendChild(chevron);
      td0.appendChild(document.createTextNode(x.nama || ''));

      var td1 = document.createElement('td');
      td1.className = 'text-end';
      td1.textContent = 'Rp' + fmt(x.prepaid);
      var td2 = document.createElement('td');
      td2.className = 'text-end';
      td2.textContent = 'Rp' + fmt(x.postpaid);
      var td3 = document.createElement('td');
      td3.className = 'text-end';
      td3.textContent = 'Rp' + fmt(x.total);
      tr.appendChild(td0);
      tr.appendChild(td1);
      tr.appendChild(td2);
      tr.appendChild(td3);

      var detailTr = buildPrepostDetailRow(x.details || []);
      tr.addEventListener('click', function () {
        var expanded = tr.getAttribute('data-expanded') === '1';
        if (expanded) {
          detailTr.classList.add('d-none');
          tr.setAttribute('data-expanded', '0');
          tr.classList.remove('table-active');
          chevron.classList.remove('fa-chevron-down');
          chevron.classList.add('fa-chevron-right');
        } else {
          detailTr.classList.remove('d-none');
          tr.setAttribute('data-expanded', '1');
          tr.classList.add('table-active');
          chevron.classList.remove('fa-chevron-right');
          chevron.classList.add('fa-chevron-down');
        }
      });

      tbody.appendChild(tr);
      tbody.appendChild(detailTr);
    });
    var g = data.grand || {};
    document.getElementById('modalPrepostFootPre').textContent = 'Rp' + fmt(g.prepaid || 0);
    document.getElementById('modalPrepostFootPost').textContent = 'Rp' + fmt(g.postpaid || 0);
    document.getElementById('modalPrepostFootSum').textContent = 'Rp' + fmt(g.total || 0);
    document.getElementById('modalPrepostTfoot').classList.remove('d-none');
  });

  var renderBarangRekap = function (data, opts) {
    var rekap = data.rekap || [];
    if (rekap.length === 0) {
      return;
    }
    document.getElementById(opts.rekapWrapId).classList.remove('d-none');
    var tbody = document.getElementById(opts.rekapTbodyId);
    var totalQty = 0;
    var totalSum = 0;
    rekap.forEach(function (x) {
      totalQty += parseFloat(x.qty) || 0;
      totalSum += parseInt(x.total, 10) || 0;
      var tr = document.createElement('tr');
      var td0 = document.createElement('td');
      td0.textContent = x.barang || '-';
      var td1 = document.createElement('td');
      td1.className = 'text-end';
      td1.textContent = fmtQty(x.qty);
      var td2 = document.createElement('td');
      td2.className = 'text-end';
      td2.textContent = 'Rp' + fmt(x.total);
      tr.appendChild(td0);
      tr.appendChild(td1);
      tr.appendChild(td2);
      tbody.appendChild(tr);
    });
    document.getElementById(opts.rekapQtyId).textContent = fmtQty(totalQty);
    document.getElementById(opts.rekapSumId).textContent = 'Rp' + fmt(totalSum);
  };

  var renderBarangRows = function (data, opts) {
    var rows = data.rows || [];
    if (rows.length === 0) {
      document.getElementById(opts.emptyId).classList.remove('d-none');
      return;
    }
    renderBarangRekap(data, opts);
    document.getElementById(opts.detailWrapId).classList.remove('d-none');
    var showCabang = rows.some(function (x) { return (x.cabang || '').length > 0; });
    document.querySelectorAll(opts.cabangColClass).forEach(function (el) {
      el.classList.toggle('d-none', !showCabang);
    });
    document.getElementById(opts.footLabelId).colSpan = showCabang ? 6 : 5;

    var tbody = document.getElementById(opts.tbodyId);
    rows.forEach(function (x) {
      var tr = document.createElement('tr');
      var cells = [
        { text: x.ref ? '#' + x.ref : '-' },
        { text: x.tanggal || '-' },
      ];
      if (showCabang) {
        cells.push({ text: x.cabang || '-' });
      }
      cells.push({ text: x.barang || '-' });
      cells.push({ text: fmtQty(x.qty), cls: 'text-end' });
      cells.push({ text: 'Rp' + fmt(x[opts.unitField]), cls: 'text-end' });
      cells.push({ text: 'Rp' + fmt(x.subtotal), cls: 'text-end' });
      cells.forEach(function (c) {
        var td = document.createElement('td');
        if (c.cls) td.className = c.cls;
        td.textContent = c.text;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    document.getElementById(opts.footSumId).textContent = 'Rp' + fmt((data.grand && data.grand.total) || 0);
    document.getElementById(opts.tfootId).classList.remove('d-none');
  };

  bindRow('rekapMarginRow', function (mode) {
    return '<?= URL::BASE_URL ?>Rekap/margin_penjualan_detail/' + encodeURIComponent(mode);
  }, {
    modal: 'modalMarginDetail',
    loading: 'modalMarginLoading',
    error: 'modalMarginError',
    tbody: 'modalMarginTbody',
    tfoot: 'modalMarginTfoot',
    empty: 'modalMarginEmpty',
    period: 'modalMarginPeriod',
    rekapWrap: 'modalMarginRekapWrap',
    rekapTbody: 'modalMarginRekapTbody',
    detailWrap: 'modalMarginDetailWrap'
  }, function (data) {
    renderBarangRows(data, {
      tbodyId: 'modalMarginTbody',
      tfootId: 'modalMarginTfoot',
      emptyId: 'modalMarginEmpty',
      footLabelId: 'modalMarginFootLabel',
      footSumId: 'modalMarginFootSum',
      cabangColClass: '.modalMarginColCabang',
      unitField: 'margin',
      rekapWrapId: 'modalMarginRekapWrap',
      rekapTbodyId: 'modalMarginRekapTbody',
      rekapQtyId: 'modalMarginRekapQty',
      rekapSumId: 'modalMarginRekapSum',
      detailWrapId: 'modalMarginDetailWrap'
    });
  });

  bindRow('rekapBarangPakaiRow', function (mode) {
    return '<?= URL::BASE_URL ?>Rekap/barang_pakai_detail/' + encodeURIComponent(mode);
  }, {
    modal: 'modalBarangPakaiDetail',
    loading: 'modalBarangPakaiLoading',
    error: 'modalBarangPakaiError',
    tbody: 'modalBarangPakaiTbody',
    tfoot: 'modalBarangPakaiTfoot',
    empty: 'modalBarangPakaiEmpty',
    period: 'modalBarangPakaiPeriod',
    rekapWrap: 'modalBarangPakaiRekapWrap',
    rekapTbody: 'modalBarangPakaiRekapTbody',
    detailWrap: 'modalBarangPakaiDetailWrap'
  }, function (data) {
    renderBarangRows(data, {
      tbodyId: 'modalBarangPakaiTbody',
      tfootId: 'modalBarangPakaiTfoot',
      emptyId: 'modalBarangPakaiEmpty',
      footLabelId: 'modalBarangPakaiFootLabel',
      footSumId: 'modalBarangPakaiFootSum',
      cabangColClass: '.modalPakaiColCabang',
      unitField: 'harga',
      rekapWrapId: 'modalBarangPakaiRekapWrap',
      rekapTbodyId: 'modalBarangPakaiRekapTbody',
      rekapQtyId: 'modalBarangPakaiRekapQty',
      rekapSumId: 'modalBarangPakaiRekapSum',
      detailWrapId: 'modalBarangPakaiDetailWrap'
    });
  });

  // Snapshot rekap bulanan
  (function () {
    var bar = document.getElementById('rekapSnapshotBar');
    var btn = document.getElementById('btnSnapshotRekap');
    if (!bar || !btn) return;

    var statusEl = document.getElementById('snapshotStatus');
    var mode = bar.getAttribute('data-mode') || '2';

    btn.addEventListener('click', function () {
      if (bar.getAttribute('data-has') === '1') return;

      var form = bar.closest('.card').querySelector('form');
      var mEl = form ? form.querySelector('select[name="m"]') : null;
      var yEl = form ? form.querySelector('select[name="y"]') : null;
      var m = mEl ? mEl.value : '';
      var y = yEl ? yEl.value : '';
      if (!m || !y) return;

      var periode = y + '-' + m;
      var now = new Date();
      var cur = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
      if (periode >= cur) return;

      btn.disabled = true;
      var prevText = btn.textContent;
      btn.textContent = 'Menyimpan…';

      var body = new URLSearchParams();
      body.set('y', y);
      body.set('m', m);

      fetch('<?= URL::BASE_URL ?>Rekap/snapshot/' + encodeURIComponent(mode), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.exists) {
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-outline-secondary');
            btn.textContent = 'Snapshot';
            btn.disabled = true;
            bar.setAttribute('data-has', '1');
            if (statusEl) statusEl.textContent = 'Tersimpan';
            return;
          }
          if (!data || !data.ok) {
            btn.disabled = false;
            btn.textContent = prevText;
            return;
          }
          var allDone = data.complete !== false;
          if (allDone) {
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-outline-secondary');
            btn.disabled = true;
            bar.setAttribute('data-has', '1');
          } else {
            btn.disabled = false;
          }
          btn.textContent = 'Snapshot';
          if (statusEl) {
            statusEl.textContent = data.msg || ('Tersimpan' + (data.periode ? ': ' + data.periode : ''));
          }
        })
        .catch(function () {
          btn.disabled = false;
          btn.textContent = prevText;
        });
    });
  })();
})();
</script>
</div>