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
            <tr>
              <td>Margin Penjualan Barang</td>
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
            <tr>
              <td>Barang Pakai</td>
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

<div class="modal fade" id="modalPrepostDetail" tabindex="-1" aria-labelledby="modalPrepostDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="modalPrepostDetailLabel">Rincian Pre/Post Paid</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-2">
        <p class="small text-muted mb-2" id="modalPrepostPeriod"></p>
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
  var row = document.getElementById('rekapPrepostRow');
  if (!row || typeof bootstrap === 'undefined') return;

  var fmt = function (n) {
    try {
      return new Intl.NumberFormat('id-ID').format(n);
    } catch (e) {
      return String(n);
    }
  };

  row.addEventListener('click', function () {
    var mode = row.getAttribute('data-rekap-mode') || '1';
    var y = row.getAttribute('data-y') || '';
    var m = row.getAttribute('data-m') || '';
    var d = row.getAttribute('data-d') || '';
    var url = '<?= URL::BASE_URL ?>Rekap/prepost_detail/' + encodeURIComponent(mode)
      + '?y=' + encodeURIComponent(y) + '&m=' + encodeURIComponent(m) + '&d=' + encodeURIComponent(d);

    var modalEl = document.getElementById('modalPrepostDetail');
    var loading = document.getElementById('modalPrepostLoading');
    var errEl = document.getElementById('modalPrepostError');
    var tbody = document.getElementById('modalPrepostTbody');
    var tfoot = document.getElementById('modalPrepostTfoot');
    var emptyEl = document.getElementById('modalPrepostEmpty');
    var periodEl = document.getElementById('modalPrepostPeriod');

    tbody.innerHTML = '';
    errEl.classList.add('d-none');
    errEl.textContent = '';
    emptyEl.classList.add('d-none');
    tfoot.classList.add('d-none');
    loading.classList.remove('d-none');

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        loading.classList.add('d-none');
        if (!data || !data.ok) {
          errEl.textContent = (data && data.msg) ? data.msg : 'Gagal memuat data.';
          errEl.classList.remove('d-none');
          return;
        }
        periodEl.textContent = 'Periode: ' + (data.period_label || '');
        var rows = data.rows || [];
        if (rows.length === 0) {
          emptyEl.classList.remove('d-none');
          return;
        }
        rows.forEach(function (x) {
          var tr = document.createElement('tr');
          var td0 = document.createElement('td');
          td0.textContent = x.nama || '';
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
          tbody.appendChild(tr);
        });
        var g = data.grand || {};
        document.getElementById('modalPrepostFootPre').textContent = 'Rp' + fmt(g.prepaid || 0);
        document.getElementById('modalPrepostFootPost').textContent = 'Rp' + fmt(g.postpaid || 0);
        document.getElementById('modalPrepostFootSum').textContent = 'Rp' + fmt(g.total || 0);
        tfoot.classList.remove('d-none');
      })
      .catch(function () {
        loading.classList.add('d-none');
        errEl.textContent = 'Gagal memuat data.';
        errEl.classList.remove('d-none');
      });
  });
})();
</script>
</div>