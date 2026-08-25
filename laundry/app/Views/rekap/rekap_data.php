<?php
// Partial data Rekap — dirender via AJAX (Rekap/i?ajax=1) untuk lazy load.
// Variabel tersedia: $data (dataTanggal, data_main, kasLaundry, kasMember, kas_keluar, gaji, prepost_cost, barang_pakai, margin_penjualan, rekap_mode).
$currentMonth = isset($data['dataTanggal']['bulan']) ? $data['dataTanggal']['bulan'] : date('m');
$currentYear = isset($data['dataTanggal']['tahun']) ? $data['dataTanggal']['tahun'] : date('Y');
$currentDay = isset($data['dataTanggal']['tanggal']) ? $data['dataTanggal']['tanggal'] : date('d');
$target_page_rekap = (int) ($data['rekap_mode'] ?? 1);
?>
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
