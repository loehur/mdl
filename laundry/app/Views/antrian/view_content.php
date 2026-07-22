<?php
$kodeCabang = $this->dCabang['kode_cabang'];
$modeView = $data['modeView'];
?>

<?php if (!empty($data['customersWithOpenCases'])) { ?>
<div class="row mx-0 mt-2 mb-2">
  <div class="col-12">
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($data['customersWithOpenCases'] as $wac) { 
            $p = $wac['pelanggan'];
            $w = $wac['wa'];
            $hp = $p['nomor_pelanggan'];
            $nama = $p['nama_pelanggan'];
        ?>
        <button class="btn btn-outline-danger btn-sm shadow-sm fw-bold open-wa-modal" 
                data-hp="<?= $hp ?>" 
                data-nama="<?= htmlspecialchars($nama) ?>"
                type="button"
                style="border-radius: 20px; font-size: 0.85rem; padding: 2px 12px;">
            <?= strtoupper($nama) ?>
        </button>
        <?php } ?>
    </div>
  </div>
</div>
<?php } ?>

<!-- Modal -->
<div class="modal fade" id="waHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title" id="waHistoryTitle">Riwayat Chat</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="waHistoryBody" style="min-height: 200px; background-color: #1a1a2e;">
        <div class="text-center"><div class="spinner-border text-danger" role="status"></div></div>
      </div>
      <div class="modal-footer bg-light p-1">
        <button type="button" class="btn btn-success w-100" id="btnCloseCase">
            <i class="fas fa-check"></i> Permintaan sudah terpenuhi
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmCloseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center p-3">
        <div class="mb-3">
            <i class="fas fa-question-circle text-success fa-3x"></i>
        </div>
        <h6 class="fw-bold mb-2">Konfirmasi</h6>
        <p class="small text-muted mb-3">Yakin permintaan sudah terpenuhi? Status akan diubah menjadi closed.</p>
        <div class="d-flex gap-2 justify-content-center">
            <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm px-3" id="btnRealConfirmClose">Ya, Selesai</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    var currentHp = '';

    $('.open-wa-modal').click(function() {
        var hp = $(this).data('hp');
        var nama = $(this).data('nama');
        
        currentHp = hp; // Store for close action

        $('#waHistoryTitle').text(nama.toUpperCase());
        $('#waHistoryModal').modal('show');
        $('#waHistoryBody').html('<div class="text-center py-3"><div class="spinner-border text-danger" role="status"></div></div>');
        
        $.ajax({
            url: '<?= URL::BASE_URL ?>Antrian/chat_history',
            type: 'POST',
            data: { hp: hp },
            success: function(response) {
                $('#waHistoryBody').html(response);
                // Auto scroll to bottom to show latest messages
                setTimeout(function() {
                    var chatBody = document.getElementById('waHistoryBody');
                    chatBody.scrollTop = chatBody.scrollHeight;
                }, 100);
            },
            error: function() {
                $('#waHistoryBody').html('<div class="text-center text-danger">Gagal memuat chat</div>');
            }
        });
    });

    // Trigger Confirmation Modal
    $('#btnCloseCase').click(function() {
        if(!currentHp) return;
        $('#confirmCloseModal').modal('show');
    });

    // Handle Real Confirmation
    $('#btnRealConfirmClose').click(function() {
        if(!currentHp) return;

        var btn = $(this);
        var originalText = btn.html();
        btn.prop('disabled', true).html('<div class="spinner-border spinner-border-sm" role="status"></div>');

        $.ajax({
            url: '<?= URL::BASE_URL ?>Antrian/close_case_request',
            type: 'POST',
            data: { hp: currentHp },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#confirmCloseModal').modal('hide');
                    $('#waHistoryModal').modal('hide');
                    location.reload(); 
                } else if (response.status === 'no_change') {
                    alert('Tidak ada case open yang ditemukan.');
                    btn.prop('disabled', false).html(originalText);
                    $('#confirmCloseModal').modal('hide');
                } else {
                    alert('Gagal: ' + (response.message || 'Unknown error'));
                    btn.prop('disabled', false).html(originalText);
                    $('#confirmCloseModal').modal('hide');
                }
            },
            error: function() {
                alert('Terjadi kesalahan koneksi.');
                btn.prop('disabled', false).html(originalText);
                $('#confirmCloseModal').modal('hide');
            }
        });
    });
});
</script>

<?php
if (count($data['data_main']) == 0) {
?>
  <div class="container-fluid">
    <div class="row">
      <div class='col p-0 m-2 rounded'>
        <div class='bg-white p-2 rounded'>
          Tidak ada Data
        </div>
      </div>
    </div>
  </div>

<?php
  exit();
}
?>

<div class="mdl-nota-grid">
  <?php
  $arrRekapAntrian = [];
  $arrRekapAntrianToday = [];
  $arrRekapAntrianBesok = [];
  $arrRekapAntrianMiss = [];
  $arrRekapAntrianRak = [];
  $arrRekapAntrianKerja = [];

  $arrPelangganToday = [];
  $arrPelangganBesok = [];
  $arrPelangganMiss = [];
  $arrPelangganRak = [];
  $arrPelangganKerja = [];

  $tglToday = date('Y-m-d');
  $tglBesok = date('Y-m-d', strtotime('+1 days'));

  foreach ($data['data_main'] as $ref => $data_order) {
    foreach ($data_order as $a) {

      $deadlineSetrikaToday = false;
      $deadlineSetrikaBesok = false;
      $deadlineSetrikaMiss = false;
      $f17 = $a['id_pelanggan'];
      $f18 = $a['id_user'];
      $f1 = $a['insertTime'];

      // Check if pelanggan exists (might be deleted)
      if (isset($this->pelanggan[$f17]) && is_array($this->pelanggan[$f17])) {
        $pelanggan = $this->pelanggan[$f17]['nama_pelanggan'];
      } else {
        $pelanggan = "Pelanggan #" . $f17;
      }
      $pelanggan_show = $pelanggan;
      if (strlen($pelanggan) > 20) {
        $pelanggan_show = substr($pelanggan, 0, 20) . "...";
      }
      if (isset($data['karyawan'][$f18])) {
        $karyawan = $data['karyawan'][$f18]['nama_user'];
      } else {
        $karyawan = "?";
      }
    } ?>

    <div data-id_pelanggan='<?= $f17 ?>' id='grid<?= $ref ?>' class='R-<?= $ref ?> cekOperasi mdl-nota-grid__item backShow <?= strtoupper($pelanggan) ?>' style='cursor:pointer'>
      <div class='mdl-nota-card'>
        <?php
          $totalBayar = 0;
          $subTotal = 0;
          $enHapus = true;

          $dateToday = date("Y-m-d");
          if (strpos($f1, $dateToday) !== FALSE) {
            $classHead = 'mdl-nota-today';
          } else {
            $classHead = 'mdl-nota-past';
          }

          $idLabel = $ref . "100";
          $buttonNotif = '<i class="fab fa-whatsapp"></i>';
          $stNotif = "...";
          $waTone = 'mdl-nota-chip--wa';

          foreach ($data['data_notif'] as $notif) {
            if ($notif['no_ref'] == $ref) {
              $stNotif = ucwords(strtolower($notif['state']));
              $waTone = (stripos($stNotif, 'pending') !== false || stripos($stNotif, 'gagal') !== false)
                ? 'mdl-nota-chip--pending'
                : 'mdl-nota-chip--ok';
            }
          }
          $tgl_terima = date('d/m H:i', strtotime($f1));
        ?>
        <div class="mdl-nota-head <?= $classHead ?> row<?= $ref ?>">
          <div class="mdl-nota-head__top">
            <div class="mdl-nota-head__left">
              <span class="mdl-nota-head__name" style="cursor:pointer" title="<?= htmlspecialchars($pelanggan, ENT_QUOTES, 'UTF-8') ?>">
                <?= strtoupper($pelanggan_show) ?>
                <small style="font-weight:700;opacity:.9"><?= $f17 ?></small>
              </span>
            </div>
            <div class="mdl-nota-head__right">
              <span><?= htmlspecialchars($karyawan, ENT_QUOTES, 'UTF-8') ?></span>
              <span><?= $tgl_terima ?></span>
              <span class="mdl-nota-chip <?= $waTone ?> mdl-nota-head__wa"><?= $buttonNotif ?> <?= $stNotif ?></span>
            </div>
          </div>
        </div>
        <table class='table table-sm m-0 w-100 bg-white'>
          <?php
          foreach ($data_order as $a) {

            $deadlineSetrikaToday = false;
            $deadlineSetrikaBesok = false;
            $deadlineSetrikaMiss = false;

            $id = $a['id_penjualan'];
            $f10 = $a['id_penjualan_jenis'];
            $f3 = $a['id_item_group'];
            $f4 = $a['list_item'];
            $f5 = $a['list_layanan'];
            $f11 = $a['id_durasi'];
            $f6 = round((float) $a['qty'], 2);
            $f7 = $a['harga'];
            $f8 = $a['note'];
            $f9 = $a['id_user'];
            $f12 = $a['hari'];
            $f13 = $a['jam'];
            $f14 = $a['diskon_qty'];
            $f15 = $a['diskon_partner'];
            $f16 = round(isset($a['min_order']) ? (float) $a['min_order'] : 0.0, 2);
            $ref = $a['no_ref'];
            $letak = $a['letak'];
            $id_ambil = $a['id_user_ambil'];
            $tgl_ambil = $a['tgl_ambil'];
            $timeRef = $f1;
            $member = $a['member'];
            $showMember = "";
            $id_harga = $a['id_harga'];

            $deadline = date('Y-m-d', strtotime($f1 . ' + ' . $f12 . ' days'));
            $deadline = date('Y-m-d H:i:s', strtotime($deadline . ' + ' . $f13 . ' hours'));

            if (date('Y-m-d', strtotime($deadline)) == date('Y-m-d', strtotime($tglToday))) {
              $deadlineSetrikaToday = true;
            }

            if (date('Y-m-d', strtotime($deadline)) == date('Y-m-d', strtotime($tglBesok))) {
              $deadlineSetrikaBesok = true;
            }

            if (date('Y-m-d', strtotime($deadline)) < date('Y-m-d', strtotime($tglToday))) {
              $deadlineSetrikaMiss = true;
            }

            if ($f12 <> 0) {
              $tgl_selesai = date('d-m-Y', strtotime($f1 . ' +' . $f12 . ' days +' . $f13 . ' hours'));
            } else {
              $tgl_selesai = date('d-m-Y H:i', strtotime($f1 . ' +' . $f12 . ' days +' . $f13 . ' hours'));
            }

            if ($id_ambil == 0) {
              $karyawan_ambil = "";
            } else {
              if (isset($data['karyawan'][$id_ambil])) {
                $karyawan_ambil = $data['karyawan'][$id_ambil]['nama_user'];
              } else {
                $karyawan_ambil = "?";
              }
            }

            $modeNotif = 1;
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

            $ambil_cek = ($id_ambil > 0) ? "<i class='fas fa-check-circle text-success'></i> <span class='fw-bold'>" . $karyawan_ambil . "</span> Ambil" : "<i class='far fa-circle'></i> Ambil";

            $show_qty = "";
            $qty_real = 0;

            if ($f6 < $f16) {
              $qty_real = $f16;
              $show_qty = $this->fmtDecMax2($f6) . $satuan . " <small>(Min. " . $this->fmtDecMax2($f16) . $satuan . ")</small>";
            } else {
              $qty_real = $f6;
              $show_qty = $this->fmtDecMax2($f6) . $satuan;
            }

            $list_layanan = "";

            $kategori = "";
            foreach ($this->itemGroup as $b) {
              if ($b['id_item_group'] == $f3) {
                $kategori = $b['item_kategori'];
              }
            }

            $durasi = "";
            foreach ($this->dDurasi as $b) {
              if ($b['id_durasi'] == $f11) {
                $durasi = strtoupper($b['durasi']);
              }
            }

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

            $userOperasi = "";
            $arrList_layanan = unserialize($f5);
            $endLayanan = end($arrList_layanan);
            $countLayanan = count($arrList_layanan);

            foreach ($arrList_layanan as $b) {
              foreach ($this->dLayanan as $c) {
                if ($c['id_layanan'] == $b) {
                  $check = 0;
                  foreach ($data['operasi'] as $o) {
                    if ($o['id_penjualan'] == $id && $o['jenis_operasi'] == $b) {
                      $check++;
                      foreach ($data['karyawan'] as $p) {
                        if ($p['id_user'] == $o['id_user_operasi']) {
                          $userOperasi = $p['nama_user'];
                        }
                      }
                    }
                  }

                  if ($check == 0) {
                    $list_layanan = $list_layanan . "<i class='far fa-circle'></i> <span>" . $c['layanan'] . "</span><br>";
                    $layananNow = $c['layanan'];

                    if ($b == $endLayanan) {
                      if (isset($arrRekapAntrian[$layananNow])) {
                        $arrRekapAntrian[$layananNow] += $f6;
                      } else {
                        $arrRekapAntrian[$layananNow] = $f6;
                      }

                      if ($deadlineSetrikaToday == true) {
                        if (isset($arrRekapAntrianToday[$layananNow])) {
                          $arrRekapAntrianToday[$layananNow] += $f6;
                        } else {
                          $arrRekapAntrianToday[$layananNow] = $f6;
                        }
                        array_push($arrPelangganToday, $ref);

                        if (isset($arrRekapAntrianKerja[$layananNow])) {
                          $arrRekapAntrianKerja[$layananNow] += $f6;
                        } else {
                          $arrRekapAntrianKerja[$layananNow] = $f6;
                        }
                        array_push($arrPelangganKerja, $ref);
                      } else {
                        if ($countLayanan == 1) {
                          if (isset($arrRekapAntrianKerja[$layananNow])) {
                            $arrRekapAntrianKerja[$layananNow] += $f6;
                          } else {
                            $arrRekapAntrianKerja[$layananNow] = $f6;
                          }
                          array_push($arrPelangganKerja, $ref);
                        }
                      }

                      if ($deadlineSetrikaBesok == true) {
                        if (isset($arrRekapAntrianBesok[$layananNow])) {
                          $arrRekapAntrianBesok[$layananNow] += $f6;
                        } else {
                          $arrRekapAntrianBesok[$layananNow] = $f6;
                        }
                        array_push($arrPelangganBesok, $ref);
                      }
                      if ($deadlineSetrikaMiss == true) {
                        if (isset($arrRekapAntrianMiss[$layananNow])) {
                          $arrRekapAntrianMiss[$layananNow] += $f6;
                        } else {
                          $arrRekapAntrianMiss[$layananNow] = $f6;
                        }
                        array_push($arrPelangganMiss, $ref);
                      }
                    }
                  } else {
                    $layananNow = $c['layanan'];
                    if ($b == $endLayanan && strlen($letak) == 0) {
                      if (isset($arrRekapAntrianRak[$layananNow])) {
                        $arrRekapAntrianRak[$layananNow] += $f6;
                      } else {
                        $arrRekapAntrianRak[$layananNow] = $f6;
                      }
                      array_push($arrPelangganRak, $ref);
                    }
                    $list_layanan = $list_layanan . "<b><i class='fas fa-check-circle text-success'></i> " . ucfirst($userOperasi) . " </b>" . $c['layanan'] . " <span style='white-space: pre;'></span><br>";
                  }
                }
              }
            }

            $total = $f7 * $qty_real;

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

            if ($member == 0) {
              if ($diskon_qty > 0 && $diskon_partner == 0) {
                $total = $total - ($total * ($diskon_qty / 100));
              } else if ($diskon_qty == 0 && $diskon_partner > 0) {
                $total = $total - ($total * ($diskon_partner / 100));
              } else if ($diskon_qty > 0 && $diskon_partner > 0) {
                $total = $total - ($total * ($diskon_qty / 100));
                $total = $total - ($total * ($diskon_partner / 100));
              } else {
                $total = ($f7 * $qty_real);
              }
              // Round to prevent floating point precision issues
              $total = round($total);
            } else {
              $total = 0;
            }

            $subTotal = $subTotal + $total;
            $show_total = "";
            if ($member == 0) {
              if (strlen($show_diskon) > 0) {
                $tampilDiskon = "(Disc. " . $show_diskon . ")";
                $show_total = "<del>" . number_format($f7 * $qty_real) . "</del><br>" . number_format($total);
              } else {
                $tampilDiskon = "";
                $show_total = "" . number_format($total);
              }
            } else {
              $show_total = "<span class='badge badge-success'>Member</span>";
              $tampilDiskon = "";
            }
            $showNote = "";
            if (strlen($f8) > 0) {
              $showNote = $f8;
            }

            $classDurasi = "";
            if (strpos($durasi, "EKSPRES") !== false || strpos($durasi, "KILAT") !== false || strpos($durasi, "PREMIUM") !== false) {
              $classDurasi = "fw-bold text-danger";
            } ?>

            <tr id='tr"<?= $id ?>' class='border-top'>
              <td nowrap class='pb-0' style="width: 45%;">
                <?php if ($letak <> "") { ?>
                  <b class="text-success border-end me-1">
                    <?= strtoupper($letak) ?>
                  </b>
                <?php } ?>
                <b><?= $kategori ?></b><br><span class="<?= $classDurasi ?>" style='white-space: pre;'><?= $durasi ?></span> <?= $f12 ?>h <?= $f13 ?>j<br>
                <small class="pe-1"><?= $id ?></small> <b><?= $show_qty ?></b>
                <br><?= $itemList ?>
              </td>
              <td class='pb-1' style="width: 23%;"><span style='white-space: pre;'><?= $list_layanan ?><?= $ambil_cek ?></td>
              <td class='pb-0 text-right' style="width: 32%;"><?= $show_total ?></td>
            </tr>

          <?php
            $showMutasi = "";
            foreach ($data['kas'] as $ka) {
              if ($ka['ref_transaksi'] == $ref) {
                $stBayar = "";

                foreach ($this->dStatusMutasi as $st) {
                  if ($ka['status_mutasi'] == $st['id_status_mutasi']) {
                    $stBayar = $st['status_mutasi'];
                  }
                }

                if (isset($data['karyawan'][$ka['id_user']])) {
                  $karyawan_kas = $data['karyawan'][$ka['id_user']]['nama_user'];
                } else {
                  $karyawan_kas = "?";
                }

                $notenya = strtoupper($ka['note']);

                switch ($ka['status_mutasi']) {
                  case '2':
                    $statusM = "<span class='text-info'>" . $stBayar . " <b>(" . $notenya . ")</b></span> - ";
                    break;
                  case '3':
                    $statusM = "<b><i class='fas fa-check-circle text-success'></i></b> " . $notenya . " ";
                    break;
                  case '4':
                    $statusM = "<span class='text-danger'><i class='fas fa-times-circle'></i> " . $stBayar . " <b>(" . $notenya . ")</b></span> - ";
                    break;
                  default:
                    $statusM = "Non Status - ";
                    break;
                }

                if ($ka['status_mutasi'] == 4) {
                  $nominal = "<s>-" . number_format($ka['jumlah']) . "</s>";
                } else {
                  $nominal = "-" . number_format($ka['jumlah']);
                }

                $showMutasi = $showMutasi . "<small>" . $statusM . "#" . $ka['id_kas'] . "</small> <b>" . ucwords($karyawan_kas) . "</b> " . date('d/m H:i', strtotime($ka['insertTime'])) . " " . $nominal . "<br>";
              }
            }
          }
          //SURCAS
          foreach ($data['surcas'] as $sca) {
            if ($sca['no_ref'] == $ref) {
              foreach ($this->surcas as $sc) {
                if ($sc['id_surcas_jenis'] == $sca['id_jenis_surcas']) {
                  $surcasNya = $sc['surcas_jenis'];
                }
              }

              $id_surcas = $sca['id_surcas'];
              $jumlahCas = $sca['jumlah'];
              echo "<tr><td colspan='2'><small>Surcharge</small> " . $surcasNya . "</td><td class='text-right'>" . number_format($jumlahCas) . "</td></tr>";
              $subTotal += $jumlahCas;
            }
          }

          // Use round() to handle floating point precision issues
          $sisaTagihan = intval(round($subTotal)) - intval($totalBayar);

          echo "<tr class='row" . $ref . " d-none'>";
          echo "<td class='text-center'><span class='d-none'>" . $pelanggan . "</span></td>";
          echo "</tr>";

          if (strlen($showMutasi) > 0) {
            echo "<tr class='row" . $ref . " sisaTagihan" . $ref . "'>";
            echo "<td nowrap colspan='4' class='text-right'>";
            echo $showMutasi;
            echo "<span class='text-danger sisaTagihan" . $ref . "'>";
            if (($sisaTagihan < intval($subTotal)) && (intval($sisaTagihan) > 0)) {
              echo  "<b><i class='fas fa-exclamation-circle'></i> Sisa " . number_format($sisaTagihan) . "</b>";
            }
            echo "</span>";
            echo "</td>";
            echo "</tr>";
          }
          ?>

        </table>
      </div>
    </div>
  <?php
    $totalBayar = 0;
    $sisaTagihan = 0;
    $no_urut = 0;
    $subTotal = 0;
  } ?>
</div>

<?php
$listAntri = "";

$buildRekapGroup = function ($tone, $label, $icon, $items, $filterMode = null) {
  if (!is_array($items) || count($items) === 0) {
    return "";
  }

  $total = 0;
  foreach ($items as $qty) {
    $total += (float) $qty;
  }
  $totalShow = (fmod($total, 1) == 0.0) ? (string) intval($total) : rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.');
  $clickable = ($filterMode !== null);
  $groupClass = 'antrian-rekap-group antrian-rekap-group--' . htmlspecialchars($tone, ENT_QUOTES, 'UTF-8');
  if ($clickable) {
    $groupClass .= ' is-clickable';
  }

  $html = "<div class='" . $groupClass . "'";
  if ($clickable) {
    $html .= " onclick='filterDeadline(" . (int) $filterMode . ")' title='Filter " . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "'";
  }
  $html .= ">";
  $html .= "<div class='antrian-rekap-group__head'>";
  $html .= "<span class='antrian-rekap-group__label'><i class='fas " . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . "'></i> " . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</span>";
  $html .= "<span class='antrian-rekap-group__total'>" . htmlspecialchars($totalShow, ENT_QUOTES, 'UTF-8') . "</span>";
  $html .= "</div><div class='antrian-rekap-group__items'>";

  foreach ($items as $layanan => $qty) {
    $qtyShow = (fmod((float) $qty, 1) == 0.0) ? (string) intval($qty) : rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');
    $html .= "<span class='antrian-rekap-item'>" . htmlspecialchars((string) $layanan, ENT_QUOTES, 'UTF-8') . " <b>" . htmlspecialchars($qtyShow, ENT_QUOTES, 'UTF-8') . "</b></span>";
  }

  $html .= "</div></div>";
  return $html;
};

$groupsHtml = "";
$groupsHtml .= $buildRekapGroup('miss', 'Terlewat', 'fa-exclamation-triangle', $arrRekapAntrianMiss, 3);
$groupsHtml .= $buildRekapGroup('today', 'Hari ini', 'fa-bolt', $arrRekapAntrianToday, 1);
$groupsHtml .= $buildRekapGroup('rak', 'Rak', 'fa-archive', $arrRekapAntrianRak, 4);
$groupsHtml .= $buildRekapGroup('besok', 'Besok', 'fa-calendar-day', $arrRekapAntrianBesok, 2);
$groupsHtml .= $buildRekapGroup('kerja', 'Kerja', 'fa-tasks', $arrRekapAntrianKerja, 5);
$groupsHtml .= $buildRekapGroup('antri', 'Antrian', 'fa-layer-group', $arrRekapAntrian, null);

if ($groupsHtml !== "") {
  $listAntri = "<div class='antrian-rekap-board'>" . $groupsHtml . "</div>";
}
?>

<script>
  var view = [];

  $(document).ready(function() {
    $("#rekapAntri").html(<?= json_encode($listAntri) ?>);
    view[1] = <?= json_encode($arrPelangganToday) ?>;
    view[2] = <?= json_encode($arrPelangganBesok) ?>;
    view[3] = <?= json_encode($arrPelangganMiss) ?>;
    view[4] = <?= json_encode($arrPelangganRak) ?>;
    view[5] = <?= json_encode($arrPelangganKerja) ?>;
  });

  $("div.cekOperasi").click(function() {
    var id_pelanggan = $(this).attr('data-id_pelanggan');
    window.location.href = "<?= URL::BASE_URL ?>Operasi/i/0/" + id_pelanggan + "/0";
  })

  function filterDeadline(mode) {
    $("div.backShow").addClass('d-none');
    view[mode].forEach(filterFunction);
  }

  function filterFunction(item) {
    if (item.length > 0) {
      $("[class*=R-" + item + "]").removeClass('d-none');
    }
  }
</script>