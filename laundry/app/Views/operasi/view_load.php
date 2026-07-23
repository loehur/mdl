<?php

$kodeCabang = $this->dCabang['id_cabang'];
$modeView = $data['modeView'];
$id_pelanggan = $data['pelanggan']['id_pelanggan'];
$nama_pelanggan = $data['pelanggan']['nama_pelanggan'];
$no_pelanggan = $data['pelanggan']['nomor_pelanggan'];
$pelanggan_show = $nama_pelanggan;
if (strlen($nama_pelanggan) > 20) {
  $pelanggan_show = substr($nama_pelanggan, 0, 20) . "...";
}
$labeled = false;
?>

<div class="mdl-nota-grid">

  <?php
  $loadRekap = [];
  $arrTuntas = [];

  foreach ($data['data_main'] as $key_ref => $c_list) {
    // Ambil no_ref dari item pertama dalam c_list
    $ref = $key_ref;
    $first_item = reset($c_list);
    if (isset($first_item['no_ref'])) {
      $ref = $first_item['no_ref'];
    }

    $listPrint = "";
    $arrBayar = [];
    $arrBayarAll = [];
    $enHapus = true;
    $countMember[$ref] = 0;

    $adaBayar = false;
    $lunas[$ref] = false;
    $totalBayar = 0;
    $dibayar = 0;
    $subTotal = 0;
    $enHapus = true;

    $countItem[$ref] = count($c_list);
    $countEndLayananDone[$ref] = 0;
    $countAmbil[$ref] = 0;

    // Hapus satu item hanya tersedia untuk nota yang benar-benar belum diproses.
    $canDeleteItemFromRef = ($modeView != 2 && $countItem[$ref] > 1);
    foreach ((array) ($data['kas'] ?? []) as $paymentCheck) {
      if (($paymentCheck['ref_transaksi'] ?? '') == $ref && (int) ($paymentCheck['status_mutasi'] ?? 0) !== 4) {
        $canDeleteItemFromRef = false;
        break;
      }
    }
    if ($canDeleteItemFromRef) {
      foreach ($c_list as $itemCheck) {
        if ((int) ($itemCheck['tuntas'] ?? 0) !== 0) {
          $canDeleteItemFromRef = false;
          break;
        }
        foreach ((array) ($data['operasi'] ?? []) as $operasiCheck) {
          if (($operasiCheck['id_penjualan'] ?? '') == ($itemCheck['id_penjualan'] ?? '')) {
            $canDeleteItemFromRef = false;
            break 2;
          }
        }
      }
    }

    foreach ($c_list as $a) {
      $f18 = $a['id_user'];
      $f1 = $a['insertTime'];
      $timeRef = $f1;

      $cs_penerima = $data['users'][$f18]['nama_user'];
      $cs_code = strtoupper(substr($cs_penerima, 0, 2)) . substr($f18, -1);
      $tgl_terima = date('d/m/Y H:i', strtotime($f1));

      $buttonNotif_londri = "<a href='#' data-urutRef='" . $ref . "' data-hp='" . $no_pelanggan . "' data-ref='" . $ref . "' data-time='" . $timeRef . "' class='mdl-nota-chip mdl-nota-chip--wa mdl-nota-chip--icon sendNotif' title='Kirim WA'><i class='fab fa-whatsapp'></i><span id='notif" . $ref . "'></span></a>";
      foreach ($data['notif_bon'] as $notif) {
        if ($notif['no_ref'] == $ref) {
          $statusWA = $notif['state'];
          if ($statusWA == '') {
            $statusWA = 'Pending';
          }
          $stNotif = ucwords(strtolower($statusWA));
          $waTone = (stripos($stNotif, 'pending') !== false || stripos($stNotif, 'gagal') !== false)
            ? 'mdl-nota-chip--pending'
            : 'mdl-nota-chip--ok';
          $buttonNotif_londri = "<span class='mdl-nota-chip " . $waTone . "' title='Status WA'><i class='fab fa-whatsapp'></i> " . $stNotif . "</span>";
        }
      }

      $dateToday = date("Y-m-d");
      if (strpos($f1, $dateToday) !== FALSE) {
        $classHead = 'mdl-nota-today';
      } else {
        $classHead = 'mdl-nota-past';
      }
      $id = $first_item['id_penjualan'] ?? '';
      break;
    } ?>

    <div class="mdl-nota-grid__item">
      <div class="mdl-nota-card">
        <div class="mdl-nota-head <?= $classHead ?> row<?= $ref ?>" id="tr<?= $id ?>">
          <div class="mdl-nota-head__top">
            <div class="mdl-nota-head__left">
              <a href="#" class="mdl-nota-head__print" data-print-ref="<?= $ref ?>" data-print-pelanggan="<?= $id_pelanggan ?>" title="Cetak">
                <i class="fas fa-print"></i>
              </a>
              <span class="mdl-nota-head__name" style="cursor:pointer" title="<?= htmlspecialchars($nama_pelanggan, ENT_QUOTES, 'UTF-8') ?>">
                <?= strtoupper($pelanggan_show) ?>
              </span>
            </div>
            <div class="mdl-nota-head__right">
              <span><?= htmlspecialchars($cs_penerima, ENT_QUOTES, 'UTF-8') ?></span>
              <span><?= $tgl_terima ?></span>
            </div>
          </div>
          <div class="mdl-nota-head__actions">
            <?= $buttonNotif_londri ?>
            <a href="#" class="mdl-nota-chip mdl-nota-chip--label mdl-nota-chip--icon" data-print-id="Label" title="Cetak Label"><i class="fas fa-tags"></i></a>
            <a href="#" class="mdl-nota-chip mdl-nota-chip--add mdl-nota-chip--icon tambahCas" data-ref="<?= $ref ?>" data-tr="id_transaksi" data-bs-toggle="modal" data-bs-target="#exampleModalSurcas" title="Tambah Surcharge"><i class="fas fa-plus"></i></a>
            <a class="mdl-nota-chip mdl-nota-chip--bill mdl-nota-chip--icon" href="<?= URL::BASE_URL . "I/" . $id_pelanggan ?>" target="_blank" title="Tagihan"><i class="fas fa-receipt"></i></a>
          </div>
        </div>
        <table class='table table-sm m-0 w-100 bg-white'>

          <?php

          foreach ($c_list as $a) {
            $id = $a['id_penjualan'];
            $id_cabang = $a['id_cabang'];
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
            $letak = $a['letak'];
            $pack = $a['pack'];
            $hanger = $a['hanger'];
            $id_ambil = $a['id_user_ambil'];
            $tgl_ambil = $a['tgl_ambil'];
            $member = $a['member'];
            $tuntas = (int) ($a['tuntas'] ?? 0);
            $canEditItem = ($member == 0 && $modeView != 2 && $tuntas === 0);
            $showMember = "";
            $countMember[$ref] += $member;

            if ($f12 <> 0) {
              $tgl_selesai = date('d-m-Y', strtotime($f1 . ' +' . $f12 . ' days +' . $f13 . ' hours'));
            } else {
              $tgl_selesai = date('d-m-Y H:i', strtotime($f1 . ' +' . $f12 . ' days +' . $f13 . ' hours'));
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

            $show_qty = "";
            $qty_real = 0;

            if ($f6 < $f16) {
              $qty_real = $f16;
              $show_qty = $this->fmtDecMax2($f6) . $satuan . " (Min. " . $this->fmtDecMax2($f16) . $satuan . ")";
            } else {
              $qty_real = $f6;
              $show_qty = $this->fmtDecMax2($f6) . $satuan;
            }

            $idKas = "";
            foreach ($data['kas'] as $byr) {
              if ($byr['ref_transaksi'] == $ref && $byr['status_mutasi'] == 3) {
                $idKas = $byr['id_kas'];
                $arrBayar[$ref][$idKas] = $byr['jumlah'];
              }

              if ($byr['ref_transaksi'] == $ref && $byr['status_mutasi'] <> 4) {
                $idKas = $byr['id_kas'];
                $arrBayarAll[$ref][$idKas] = $byr['jumlah'];
              }
              if ($byr['ref_transaksi'] == $ref) {
                $adaBayar = true;
              }
            }

            if (isset($arrBayar[$ref][$idKas])) {
              $totalBayar = array_sum($arrBayar[$ref]);
            }
            if (isset($arrBayarAll[$ref][$idKas])) {
              $dibayar = array_sum($arrBayarAll[$ref]);
            }

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

            $userAmbil = "";
            $endLayananDone = false;
            $list_layanan = "";
            $list_layanan_print = "";
            $arrList_layanan = unserialize($f5);
            $endLayanan = end($arrList_layanan);
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
                      if ($b == $endLayanan) {
                        $endLayananDone = true;
                        if (isset($countEndLayananDone[$ref])) {
                          $countEndLayananDone[$ref] += 1;
                        } else {
                          $countEndLayananDone[$ref] = 1;
                        }
                      }

                      $user = $data['users'][$o['id_user_operasi']]['nama_user'] ?? "Crew";
                      if ($id_ambil > 0) {
                        $userAmbil = $data['users'][$id_ambil]['nama_user'] ?? "Crew";
                      } else {
                        $userAmbil = "";
                      }

                      $buttonNotifSelesai = "";
                      if ($b == $endLayanan && $endLayananDone == true) {
                        foreach ($data['notif_selesai'] as $notif) {
                          if ($notif['no_ref'] == $id) {
                            $stNotif = "<b>" . ucwords(strtolower($notif['state'])) . "</b>";
                            $buttonNotifSelesai = "<span><i class='fas fa-check-circle'></i> " . ucwords($stNotif) . "</span><br>";
                          }
                        }
                      }

                      if ($this->id_privilege >= 100) {
                        $list_layanan .= "<span style='cursor:pointer' data-awal='" . $user . "' data-id='" . $o['id_operasi'] . "' class='gantiOperasi' data-bs-target='#modalGanti'><b><i class='fas fa-check-circle text-success'></i> <span class='fw-bold text-purple'>" . $c['kode'] . "</span> " . $user . "</b> <span style='white-space: pre;'>" . date('d/m H:i', strtotime($o['insertTime'])) . "</span></span><br>" . $buttonNotifSelesai;
                      } else {
                        $list_layanan .= "<b><i class='fas fa-check-circle text-success'></i> <span class='fw-bold text-purple'>" . $c['kode'] . "</span> " . $user . "</b> <span style='white-space: pre;'>" . date('d/m H:i', strtotime($o['insertTime'])) . "</span><br>" . $buttonNotifSelesai;
                      }

                      $doneLayanan++;
                      $enHapus = false;
                    }
                  }
                  if ($check == 0) {
                    if ($b == $endLayanan) {
                      $list_layanan .= "<span style='cursor:pointer' id='" . $id . $b . "' data-layanan='" . $c['layanan'] . "' data-value='" . $c['id_layanan'] . "' data-id='" . $id . "' data-ref='" . $ref . "' data-bs-target='#exampleModal' class='endLayanan'><i class='far fa-circle text-info'></i> " . $c['layanan'] . "</span><br><span class='d-none ambilAfterSelesai" . $id . $b . "'><a href='#' data-id='" . $id . "' data-ref='" . $ref . "' data-bs-target='#exampleModal4' class='ambil text-dark ambil" . $id . "'><i class='far fa-circle'></i> Ambil</a></span>";
                    } else {
                      $list_layanan .= "<span style='cursor:pointer' id='" . $id . $b . "' data-layanan='" . $c['layanan'] . "' data-value='" . $c['id_layanan'] . "' data-id='" . $id . "' data-ref='" . $ref . "' data-bs-target='#exampleModal' class='addOperasi'><i class='far fa-circle text-info'></i> " . $c['layanan'] . "</span><br>";
                    }
                    $layananNow = $c['layanan'];
                  }
                  $list_layanan_print = $list_layanan_print . " " . $c['layanan'];
                }
              }
            }

            $ambilDone = false;
            if ($id_ambil > 0) {
              $list_layanan = $list_layanan . "<b><i class='fas fa-check-circle text-success'></i> " . $userAmbil . "</b> Ambil <span style='white-space: pre;'>" . date('d/m H:i', strtotime($tgl_ambil)) . "</span><br>";
              $ambilDone = true;
              if (isset($countAmbil[$ref])) {
                $countAmbil[$ref] += 1;
              } else {
                $countAmbil[$ref] = 1;
              }
            }

            $buttonAmbil = "";
            if ($id_ambil == 0 && $endLayananDone == true) {
              $buttonAmbil = "<a href='#' data-id='" . $id . "' data-ref='" . $ref . "' data-bs-toggle='modal' data-bs-target='#exampleModal4' class='ambil text-dark ambil" . $id . "'><i class='far fa-circle'></i> Ambil</a>";
            }

            $buttonUbahLayanan = "";
            if ($canEditItem && $doneLayanan == 0 && $id_ambil == 0 && $buttonAmbil === "") {
              $buttonUbahLayanan = "<span style='cursor:pointer' data-id='" . $id . "' data-ref='" . $ref . "' data-bs-target='#modalUbahLayanan' class='editLayanan text-purple'><i class='fas fa-circle text-purple'></i> Ganti</span>";
            }


            $list_layanan = $list_layanan . "<span class='operasiAmbil" . $id . "'></span>";

            $adaDiskon = false;

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

            $total = $f7 * $qty_real;

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
              // Round to prevent floating point precision issues (e.g., 49999.9999 -> 50000)
              $total = round($total);
            } else {
              $total = 0;
            }

            $subTotal = $subTotal + $total;
            $show_total = "";
            $show_total_print = "";
            $show_total_notif = "";

            if ($member == 0) {
              if (strlen($show_diskon) > 0) {
                $tampilDiskon = "(Disc. " . $show_diskon . ")";
                $show_total = "<del>" . number_format($f7 * $qty_real) . "</del><br>" . number_format($total);
                $show_total_print = "(-" . number_format(($f7 * $qty_real) - $total) . ") " . number_format($total);
                $show_total_notif = "~" . number_format($f7 * $qty_real) . "~ " . number_format($total) . " ";
              } else {
                $tampilDiskon = "";
                $show_total = "" . number_format($total);
                $show_total_print = "" . number_format($total);
                $show_total_notif = "" . number_format($total);
              }
            } else {
              $show_total = "<span class='badge badge-success'>Member</span>";
              $show_total_print = "MEMBER";
              $show_total_notif = "MEMBER";
              $tampilDiskon = "";
            }

            $show_total_cell = $show_total;
            if ($canEditItem) {
              $show_total_cell = "<span class='editMember' style='cursor:pointer;' data-id='" . $id . "' data-ref='" . $ref . "' data-bs-target='#modalUbahMember' title='Klik untuk ubah ke member'>" . $show_total . "</span>";
            }

            $showNote = "";
            if (strlen($f8) > 0) {
              $showNote = $f8;
            }

            $classDurasi = "";
            if (strpos($durasi, "EKSPRES") !== false || strpos($durasi, "KILAT") !== false || strpos($durasi, "PREMIUM") !== false) {
              $classDurasi = "fw-bold text-danger";
            }

            $durasiHtml = "<span class='" . $classDurasi . "' style='white-space: pre;'>" . $durasi . $f12 . "h " . $f13 . "j</span>";
            if ($canEditItem) {
              $durasiHtml = "<span class='" . $classDurasi . " editDurasi' style='cursor:pointer; white-space: pre;' data-id='" . $id . "' data-ref='" . $ref . "' data-bs-target='#modalUbahDurasi' title='Klik untuk ubah durasi'>" . $durasi . $f12 . "h " . $f13 . "j</span>";
            }

            $classTRDurasi = "";
            if (strpos($durasi, "-D") !== false) {
              $classTRDurasi = "table-warning";
            } ?>

            <tr id='tr<?= $id ?>' class='row<?= $ref ?> <?= $classTRDurasi ?> table-borderless'>

              <?php
              if ($ambilDone == false) {
                $classs_rak = "text-success editRak";
                $classs_pack = "text-info editPack";
                $classs_hanger = "text-info editHanger";
              } else {
                $classs_rak = "text-secondary";
                $classs_pack = "text-secondary";
                $classs_hanger = "text-secondary";
              }
              ?>
              <td nowrap class='text-center'>
                <a href='#' class='mb-1 text-secondary' data-print-id='<?= $id ?>'><i class='fas fa-print'></i></a><br>
                <?php if ($canDeleteItemFromRef) { ?>
                  <a href="#" class="hapusItemNota text-danger" data-id="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" data-ref="<?= htmlspecialchars((string) $ref, ENT_QUOTES, 'UTF-8') ?>" data-item="<?= htmlspecialchars((string) $kategori, ENT_QUOTES, 'UTF-8') ?>" title="Hapus item" role="button"><i class="fas fa-trash-alt"></i></a><br>
                <?php } ?>
                <?php
                if (strlen($letak) > 0) {
                  $statusRak = "<h6 class='m-0 p-0'><small><span data-id='" . $id . "' data-value='" . strtoupper($letak) . "' class='m-0 p-0 fw-bold " . $classs_rak . " " . $id . "'>" . strtoupper($letak) . "</span></small></h6>";
                } else {
                  $statusRak = "<h6 class='m-0 p-0'></small><span data-ref=" . $ref . " data-id='" . $id . "' data-value='" . strtoupper($letak) . "' class='m-0 p-0 fw-bold " . $classs_rak . " " . $id . "'>[ ]</span><small></h6>";
                }

                if ($endLayananDone == false) {
                  $statusRak = "<span data-ref=" . $ref . " class='" . $classs_rak . " " . $id . "'></span>";
                }

                if ($doneLayanan == true) {
                }

                if ($endLayananDone == true) {
                  $statusPack = "<h6 class='m-0 p-0'><small><b class='" . $classs_pack . "'>P</b><span data-id='" . $id . "' data-value='" . strtoupper($pack) . "' class='m-0 p-0 fw-bold " . $classs_pack . " " . $id . "'>" . strtoupper($pack) . "</span></small></h6>";
                  $statusHanger = "<h6 class='m-0 p-0'><small><b class='" . $classs_hanger . "'>H</b><span data-id='" . $id . "' data-value='" . strtoupper($hanger) . "' class='m-0 p-0 fw-bold " . $classs_hanger . " " . $id . "'>" . strtoupper($hanger) . "</span></small></h6>";
                } else {
                  $statusPack = "";
                  $statusHanger = "";
                }

                echo "<small>";
                echo $statusRak;
                echo $statusPack;
                echo $statusHanger;
                echo "</small>";
                ?>
              </td>

              <td class='pb-0'>
                <small><?= $id ?></small><br><b><span style='white-space: nowrap;'><?= $kategori ?></span></b><span class='badge badge-light'></span><br><?= $durasiHtml ?><br>
                <b><?= $show_qty ?></b> <?= $tampilDiskon ?><br><?= $itemList ?>
              </td>
              <td nowrap><?= $list_layanan . $buttonAmbil . $buttonUbahLayanan ?></td>
              <td class='text-right'><?= $show_total_cell ?></td>
            </tr>
            <tr class='<?= $classTRDurasi ?>'>
              <?php if (strlen($f8) > 0) { ?>
                <td style='border-top:0' colspan='5' class='m-0 pt-0'><span class='badge badge-warning'><?= $f8 ?></span></td>
              <?php } else { ?>
                <td style='border-top:0' colspan='5' class='m-0 pt-0'><span class='badge badge-warning'></span></td>
              <?php } ?>
            </tr>

            <?php
            $showMutasi = "";
            $userKas = "";
            foreach ($data['kas'] as $ka) {
              if ($ka['ref_transaksi'] == $ref) {
                foreach ($this->userMerge as $usKas) {
                  if ($usKas['id_user'] == $ka['id_user']) {
                    $userKas = $usKas['nama_user'];
                  }
                }

                $stBayar = "";
                foreach ($this->dStatusMutasi as $st) {
                  if ($ka['status_mutasi'] == $st['id_status_mutasi']) {
                    $stBayar = $st['status_mutasi'];
                  }
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
                    $statusM = "<span class='text-danger text-bold'><i class='fas fa-times-circle'></i> " . $stBayar . " <b>(" . $notenya . ")</b></span> - ";
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

                $showMutasi = $showMutasi . "<small>" . $statusM . "#" . $ka['id_kas'] . "</small> <b>" . $userKas . "</b> " . date('d/m H:i', strtotime($ka['insertTime'])) . " " . $nominal . "<br>";
              }
            }

            $spkPrint = "";
            $firstid = substr($id, 0, strlen($id) - 3);
            $lastid = substr($id, -3);
            $spkPrint = "<tr><td>ID" . $firstid . "-<b>" . $lastid . "</b> <br>Selesai <b>" . $tgl_selesai . "</b></td><td></td></tr>
            <tr>
              <td>" . $penjualan . "</td>
              <td>" . $kategori . "</td>
            </tr>
            <tr>
              <td><b>" . strtoupper($durasi) . "</b></td>
              <td><b>" . strtoupper($list_layanan_print) . "</b></td>
            </tr>
            <tr>
              <td><b>" . $show_qty . "</b></td>
              <td><b>" . $show_total_print . "</b></td>
            </tr>
            <tr>
              <td>" . $itemListPrint . "</td><td></td>
            </tr>
            <tr>
              <td>" . $showNote . "</td><td></td>
            </tr>
            <tr id='dashRow'><td></td></tr>";

            $listPrint = $listPrint . $spkPrint;
            ?>

            <!-- CETAK NOTA KECIL -->
            <tr class="d-none">
              <td>
                <div class="d-none" id="print<?= $id ?>">
                  <table>
                    <tr>
                      <td>
                        <b><?= $this->dCabang['nama'] ?> - <?= $this->dCabang['kode_cabang'] ?></b><br>
                        <?= $this->dCabang['alamat'] ?><br>
                        <?= $this->dCabang['phone_number'] ?>
                      </td>
                    </tr>
                    <tr id="dashRow">
                      <td></td>
                    </tr>
                    <tr>
                      <td>
                        <h1><b><?= strtoupper($nama_pelanggan) ?></b></h1><br>
                        REF<b><?= $id_cabang ?></b>#<?= $ref ?><br>
                        <?= $f1 ?>
                      </td>
                    </tr>
                    <tr id="dashRow">
                      <td></td>
                    </tr>
                    <?= $spkPrint ?>
                    <tr>
                      <td><?= URL::PACK_ROWS ?><b>- <?= $this->dCabang['kode_cabang'] ?> -</b></td>
                    </tr>
                  </table>
                </div>
              </td>
            </tr>
          <?php } ?>

          <?php
          //SURCAS
          foreach ($data['surcas'] as $sca) {
            if ($sca['no_ref'] == $ref) {
              foreach ($this->surcas as $sc) {
                if ($sc['id_surcas_jenis'] == $sca['id_jenis_surcas']) {
                  $surcasNya = $sc['surcas_jenis'];
                }
              }

              $userCas = "?";
              if (isset($this->userAll[$sca['id_user']]['nama_user'])) {
                $userCas = $this->userAll[$sca['id_user']]['nama_user'];
              }

              $id_surcas = $sca['id_surcas'];
              $jumlahCas = $sca['jumlah'];
              $tglCas = "<b><i class='fas fa-check-circle text-success'></i> " . $userCas . "</b> Input <span style='white-space: pre;'>" . date('d/m H:i', strtotime($sca['insertTime'])) . "</span><br>";
              echo "<tr>
              <td></td>
              <td>" . $surcasNya . "</td>
              <td>" . $tglCas . "</td>
              <td align='right'>" . number_format($jumlahCas) . "</td>
            </tr>";
              $subTotal += $jumlahCas;

              $spkPrint = "<tr>
              <td>S" . $id_surcas . " <br><b>" . $surcasNya . "</b></td><td></td>
            </tr>
            <tr>
              <td></td>
              <td><b>" . number_format($jumlahCas) . "</b></td>
            </tr>
            <tr id='dashRow'>
              <td></td>
            </tr>";
              $listPrint = $listPrint . $spkPrint;
            }
          }

          if ($totalBayar > 0) {
            $enHapus = false;
          }
          // Use round() to handle any remaining floating point issues
          $sisaTagihan = intval(round($subTotal)) - intval($dibayar);
          $sisaTagihanFinal = intval(round($subTotal)) - intval($totalBayar);
          echo "<span class='d-none' id='member" . $ref . "'>" . $countMember[$ref] . "</span>";

          $buttonHapus = "";
          if ($enHapus == true || $this->id_privilege >= 100) {
            $buttonHapus = "<small><a href='#' data-ref='" . $ref . "' class='hapusRef mb-1'><i class='fas fa-trash-alt text-danger'></i></a><small> ";
          }
          if ($sisaTagihanFinal < 1) {
            $lunas[$ref] = true;
          } else {
            if ($sisaTagihan > 0) {
              $loadRekap['U#' . $ref] = $sisaTagihan;
            }
          } ?>
          <tr class='row<?= $ref ?>'>
            <td class='text-center'><span class='d-none'><?= $nama_pelanggan ?></span><?= $buttonHapus ?></td>

            <?php
            if ($lunas[$ref] == true && $countEndLayananDone[$ref] == $countItem[$ref] && $countAmbil[$ref] == $countItem[$ref] && $modeView <> 2) {
              array_push($arrTuntas, $ref);
            }

            if ($lunas[$ref] == false) {
              echo "<td nowrap colspan='3' class='text-right'><span class='showLunas" . $ref . "'></span><b> " . number_format($subTotal) . "</b><br>";
            } else {
              echo "<td nowrap colspan='3' class='text-right'><b><i class='fas fa-check-circle text-success'></i> " . number_format($subTotal) . "</b><br>";
            }
            ?>
            </td>
          </tr>

          <?php
          if ($adaBayar == true) {
            $classMutasi = "";
          } else {
            $classMutasi = "d-none";
          }
          ?>
          <tr class='row<?= $ref ?> sisaTagihan<?= $ref ?> <?= $classMutasi ?>'>
            <td nowrap colspan='4' class='text-right'>
              <?= $showMutasi ?>
              <span class='text-danger sisaTagihan<?= $ref ?>'>
                <?php if (($sisaTagihan < intval($subTotal)) && (intval($sisaTagihan) > 0)) { ?>
                  <b><i class='fas fa-exclamation-circle'></i> Sisa <?= number_format($sisaTagihan) ?></b>
                <?php } ?>
              </span>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
      <?php
      if ($lunas[$ref] == false) {
        $totalText = "*Total/Sisa " . number_format($subTotal - $dibayar) . "*";
      } else {
        $totalText = "*Total/Sisa 0. LUNAS*";
      }
      ?>

      <!-- TOTAL TEXT FOR NOTIFICATION (used by JavaScript to send to controller) -->
      <span class="d-none" id="textTotal<?= $ref ?>"><?= $totalText ?></span>

      <!-- CETAK NOTA BESAR -->
      <div class="d-none" id="print<?= $ref ?>">
        <table>
          <tr>
            <td>
              <b><?= $this->dCabang['nama'] ?> - <?= $this->dCabang['kode_cabang'] ?></b><br>
              <?= $this->dCabang['alamat'] ?><br>
              <?= $this->dCabang['phone_number'] ?>
            </td>
          </tr>
          <tr id="dashRow">
            <td></td>
          </tr>
          <tr>
            <td>
              <h1><b><?= strtoupper($nama_pelanggan) ?></b></h1><br>
              REF<b><?= $id_cabang ?></b>#<?= $ref ?><br>
              <?php
              $tgl_masuk = date('d-m-Y H:i', strtotime($f1));
              echo $tgl_masuk ?>
            </td>
          </tr>
          <tr id="dashRow">
            <td></td>
          </tr>
          <?= $listPrint ?>
          <tr>
            <td>Total</td>
            <td><?= "" . number_format($subTotal) ?></td>
          </tr>
          <tr>
            <td>
              Bayar
            </td>
            <td>
              <?= number_format($totalBayar) ?>
            </td>
          </tr>
          <tr>
            <td>
              Sisa
            </td>
            <td>
              <?= number_format($sisaTagihan) ?>
            </td>
          </tr>
          <?php if ($countMember[$ref] > 0) { ?>
            <tr id="dashRow">
              <td></td>
            </tr>
            <?php if (strlen($countMember[$ref] > 0)) { ?>
              <tr>
                <td class="textMember<?= $ref ?>"></td>
                <td></td>
              </tr>
          <?php }
          } ?>
          <tr id="dashRow">
            <td></td>
          </tr>
          <tr>
            <td>Ketik <b>CEK</b> ke whatsapp kami,<br>untuk info status laundry.</td>
          </tr>
          <tr>
            <td><?= URL::PACK_ROWS ?><b>- <?= $this->dCabang['kode_cabang'] ?> -</b></td>
          </tr>
        </table>
      </div>

      <?php if ($labeled == false) { ?>
        <div class="d-none" id="printLabel">
          <table>
            <tr>
              <td>
                <?= $this->dCabang['nama'] ?> - <b><?= $this->dCabang['kode_cabang'] ?></b><br>
                <?= date("Y-m-d H:i:s") ?>
              </td>
            </tr>
            <tr>
              <td>
                <h1><b><?= strtoupper($nama_pelanggan) ?></b></h1>
              </td>
            </tr>
            <tr>
              <td>
                <?= URL::PACK_ROWS ?><b>- <?= $this->dCabang['kode_cabang'] ?> -</b>
              </td>
            </tr>
          </table>
        </div>
      <?php
        $labeled = true;
        $totalBayar = 0;
        $sisaTagihan = 0;
        $subTotal = 0;
        $listPrint = "";
        $enHapus = true;
      } ?>
    </div>
  <?php } ?>

  <!-- MEMEBR ================================================== -->

  <?php
  foreach ($data['data_member'] as $z) {
    $id = $z['id_member'];
    $ref = $id;
    $id_harga = $z['id_harga'];
    $harga = $z['harga'];
    $id_user = $z['id_user'];
    $kategori = "";
    $layanan = "";
    $durasi = "";
    $unit = "";
    $timeRef = $z['insertTime'];

    $totalBayar = 0;
    $dibayar_M = 0;
    $showMutasi = "";
    $userKas = "";

    if (isset($data['kas_member'][$id])) {
      foreach ($data['kas_member'][$id] as $ka) {
        if ($ka['ref_transaksi'] == $id) {
          foreach ($this->userMerge as $usKas) {
            if ($usKas['id_user'] == $ka['id_user']) {
              $userKas = $usKas['nama_user'];
            }
          }

          $stBayar = "";
          foreach ($this->dStatusMutasi as $st) {
            if ($ka['status_mutasi'] == $st['id_status_mutasi']) {
              $stBayar = $st['status_mutasi'];
            }
          }

          $notenya = strtoupper($ka['note']);
          $st_mutasi = $ka['status_mutasi'];

          switch ($st_mutasi) {
            case '2':
              $statusM = "<span class='text-info'>" . $stBayar . " <b>(" . $notenya . ")</b></span> - ";
              break;
            case '3':
              $statusM = "<b><i class='fas fa-check-circle text-success'></i></b> " . $notenya . " ";
              break;
            case '4':
              $statusM = "<span class='text-danger text-bold'><i class='fas fa-times-circle'></i> " . $stBayar . " <b>(" . $notenya . ")</b></span> - ";
              break;
            default:
              $statusM = "Non Status - ";
              break;
          }

          if ($st_mutasi == 4) {
            $nominal = "<s>-" . number_format($ka['jumlah']) . "</s>";
          } else {
            $nominal = "-" . number_format($ka['jumlah']);
          }

          $showMutasi = $showMutasi . "<small>" . $statusM . "<b>#" . $ka['id_kas'] . "</small> " . $userKas . "</b> " . date('d/m H:i', strtotime($ka['insertTime'])) . " " . $nominal . "<br>";
        }
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
            $durasi = $c['durasi'];
          }
        }

        foreach ($this->itemGroup as $c) {
          if ($a['id_item_group'] == $c['id_item_group']) {
            $kategori = $c['item_kategori'];
          }
        }
      }
    }
    $adaBayar = false;

    $historyBayar = [];
    $hisDibayar = [];
    foreach ($data['kas_member'] as $jdk) {
      foreach ($jdk as $k) {
        if ($k['ref_transaksi'] == $id && $k['status_mutasi'] == 3) {
          array_push($historyBayar, $k['jumlah']);
        }
        if ($k['ref_transaksi'] == $id && $k['status_mutasi'] <> 4) {
          array_push($hisDibayar, $k['jumlah']);
        }
        if ($k['ref_transaksi'] == $id) {
          $adaBayar = true;
        }
      }
    }

    $statusBayar = "";
    $totalBayar = array_sum($historyBayar);
    $dibayar_M = array_sum($hisDibayar);
    $showSisa = "";
    $sisa = intval($harga);
    $lunas[$ref] = false;
    $enHapus = true;
    $sisa = intval($harga) - intval($dibayar_M);

    if ($dibayar_M > 0) {
      $enHapus = false;
    }

    if ($totalBayar >= $harga) {
      $lunas[$ref] = true;
      $statusBayar = "<b><i class='fas fa-check-circle text-success'></i></b>";
    } else {
      $lunas[$ref] = false;
    }

    if ($dibayar_M > 0 && $sisa > 0) {
      $showSisa = "<b><i class='fas fa-exclamation-circle'></i> Sisa " . number_format($sisa) . "</b>";
    }

    $cs = "";
    foreach ($this->userMerge as $uM) {
      if ($uM['id_user'] == $id_user) {
        $cs = $uM['nama_user'];
      }
    }

    if ($enHapus == true || $this->id_privilege >= 100) {
      $buttonHapus = "<small><a href='" . URL::BASE_URL . "Member/bin/" . $id . "' data-ref='" . $id . "' class='hapusRef text-dark'><i class='fas fa-trash-alt'></i></a></small> ";
    } else {
      $buttonHapus = "";
    }

    //BUTTON NOTIF MEMBER
    $buttonNotif_Member = "<a href='#' data-ref='" . $id . "' class='mdl-nota-chip mdl-nota-chip--wa mdl-nota-chip--icon sendNotifMember' title='Kirim WA'><i class='fab fa-whatsapp'></i> <span id='notif" . $id . "'></span></a>";
    foreach ($data['notif_member'] as $notif) {
      if ($notif['no_ref'] == $id) {
        $stNotif = ucwords($notif['state']);
        $waTone = (stripos($stNotif, 'pending') !== false || stripos($stNotif, 'gagal') !== false)
          ? 'mdl-nota-chip--pending'
          : 'mdl-nota-chip--ok';
        $buttonNotif_Member = "<span class='mdl-nota-chip " . $waTone . "' title='Status WA'><i class='fab fa-whatsapp'></i> " . $stNotif . "</span>";
      }
    }

    $cabangKode = $this->dCabang['kode_cabang'];
  ?>

    <?php if ($lunas[$ref] == false) {
      $loadRekap['M#' . $id] = $sisa;
    ?>
      <div class="mdl-nota-grid__item">
        <div class="mdl-nota-card">
          <div class="mdl-nota-head mdl-nota-member">
            <div class="mdl-nota-head__top">
              <div class="mdl-nota-head__left">
                <a href="#" class="mdl-nota-head__print" data-print-id="<?= $id ?>" title="Cetak">
                  <i class="fas fa-print"></i>
                </a>
                <span class="mdl-nota-head__name" title="<?= htmlspecialchars($nama_pelanggan, ENT_QUOTES, 'UTF-8') ?>">
                  <?= strtoupper($pelanggan_show) ?>
                </span>
              </div>
              <div class="mdl-nota-head__right">
                <span><?= htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
            <div class="mdl-nota-head__actions">
              <?= $buttonNotif_Member ?>
              <a class="mdl-nota-chip mdl-nota-chip--bill mdl-nota-chip--icon" href="<?= URL::BASE_URL ?>I/<?= $id_pelanggan ?>" target="_blank" title="Tagihan">
                <i class="fas fa-receipt"></i>
              </a>
            </div>
          </div>
          <table class="table bg-white table-sm w-100 m-0">
            <tbody>
              <tr>
                <td class="text-center">
                  <?php if ($adaBayar == false || $this->id_privilege >= 100) { ?>
                    <span><?= $buttonHapus ?></span>
                  <?php } ?>
                </td>
                <td nowrap>
                  <?= "#" . $id . " " ?> <?= $z['insertTime'] ?><br>
                  <b>M<?= $id_harga ?></b> <?= $kategori ?> * <?= $layanan ?> * <?= $durasi ?>
                </td>
                <td nowrap class="text-right"><br><b><?= $z['qty'] . $unit ?></b></td>
              </tr>
              <tr>
                <td></td>
                <td class="text-right"></td>
                <td nowrap class="text-right"><span id="statusBayar<?= $id ?>"><?= $statusBayar ?></span>&nbsp;
                  <span class="float-right"><b><?= number_format($harga) ?></b></span>
                </td>
              </tr>
              <?php if ($adaBayar == true) { ?>
                <tr>
                  <td></td>
                  <td colspan="2" align="right"><span id="historyBayar<?= $id ?>"><?= $showMutasi ?></span>
                    </span><span id="sisa<?= $id ?>" class="text-danger"><?= $showSisa ?></span></td>
                </tr>
              <?php
              }
              ?>
            </tbody>
          </table>
        </div>

        <!-- CETAK NOTA TOPUP PAKET -->
        <span class="d-none" id="print<?= $id ?>">
          <table>
            <tr>
              <td>
                <b><?= $this->dCabang['nama'] ?> [ <?= $this->dCabang['kode_cabang'] ?></b> ]<br>
                <?= $this->dCabang['alamat'] ?><br>
                <?= $this->dCabang['phone_number'] ?>
              </td>
            </tr>
            <tr id="dashRow">
              <td></td>
            </tr>
            <tr>
              <td>
                <h1><b><?= strtoupper($nama_pelanggan) ?></b></h1><br>
                #<?= $id ?><br>
                <?= $z['insertTime'] ?>
              </td>
            </tr>
            <tr>
              <td>Topup Paket <b>M<?= $id_harga ?></b><br><?= $kategori ?>, <?= $layanan ?>, <?= $durasi ?>,
                <?= $z['qty'] . $unit ?>
              </td>
              <td></td>
            </tr>
            <tr id="dashRow">
              <td></td>
            </tr>
            <tr>
              <td>
                Total
              </td>
              <td>
                <?= "" . number_format($harga) ?>
              </td>
            </tr>
            <tr>
              <td>
                Bayar
              </td>
              <td>
                <?= number_format($totalBayar) ?>
              </td>
            </tr>
            <tr>
              <td>
                Sisa
              </td>
              <td>
                <?= number_format($sisa) ?>
              </td>
            </tr>
            <tr id="dashRow">
              <td></td>
            </tr>
          </table>
        </span>
      </div>
      <?php } ?>
    <?php } ?>
</div>

<div class="py-5"></div>

<!-- Riwayat Pembayaran -->
<div class="position-fixed" style="z-index: 999; bottom: 80px; right: 22px">
  <?php if (isset($data['finance_history']) && count($data['finance_history']) > 0) { ?>
    <div class="mx-1 bg-white px-2 py-2 shadow">
      <table class='table table-sm m-0 table-borderless table-striped'>
        <?php foreach ($data['finance_history'] as $fh) {
          $stName = '';
          foreach ($this->dStatusMutasi as $stx) {
            if ($stx['id_status_mutasi'] == $fh['status']) {
              $stName = $stx['status_mutasi'];
              break;
            }
          }
        ?>
          <tr>
            <td class="text-end">
              <?php
              if ($fh['status'] == 3 || strtolower($stName) == 'sukses') {
                echo "<i class='fas fa-check-circle text-success'></i>";
              } elseif ($fh['status'] == 2 || strtolower($stName) == 'cek') {
                echo "<i class='far fa-circle text-dark'></i>";
              } else {
                echo $stName;
              }
              ?>
            </td>
            <td class=''>
              <?php if ((int) $fh['status'] === 2) { ?>
                <?php $isQRIS = !empty($fh['note']) && strtoupper($fh['note']) === 'QRIS'; ?>
                <button type='button' class='btn btn-warning btn-sm tokopayOrder' data-ref='<?= $fh['ref_finance'] ?>'
                  data-total='<?= (int) $fh['total'] ?>'
                  data-note='<?= $fh['note'] ?>'><?= $isQRIS ? 'Scan QR' : (!empty($fh['note']) ? $fh['note'] : 'Cek') ?></button>
              <?php } else {
                echo $fh['note'];
              } ?>
            </td>
            <td class='text-end'>Rp<?= number_format($fh['total']) ?></td>
            <td class='text-center'>
              <?php if ($fh['status'] != 3) { ?>
                <button type='button' class='btn btn-sm btn-link text-danger cancelPayment p-0'
                  data-ref='<?= $fh['ref_finance'] ?>'
                  data-total='<?= number_format($fh['total']) ?>'
                  data-note='<?= $fh['note'] ?>'
                  title='Batalkan Pembayaran'>
                  <i class="fas fa-trash-alt"></i>
                </button>
              <?php } ?>
            </td>
          </tr>
        <?php } ?>
      </table>
    </div>
  <?php } ?>
</div>

<?php include __DIR__ . '/partials/modals.php'; ?>

<!-- Modal Hapus Order - Inline -->
<div id="modalHapusOrderInline" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:999999;">
  <div class="tutupModalHapusBtn" style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); cursor:pointer;"></div>
  <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; border-radius:0; width:90%; max-width:350px; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
    <div style="background:#dc3545; color:white; padding:12px 16px; border-radius:0; display:flex; justify-content:space-between; align-items:center;">
      <h6 style="margin:0; font-size:14px;"><i class="fas fa-trash-alt"></i> Hapus Order</h6>
      <button class="tutupModalHapusBtn" style="background:none; border:none; color:white; font-size:18px; cursor:pointer;">&times;</button>
    </div>
    <div style="padding:16px;">
      <p style="margin:0 0 12px;">Yakin ingin menghapus order <strong id="hapusRefText"></strong>?</p>
      <div style="margin-bottom:12px;">
        <label style="font-size:12px; margin-bottom:4px; display:block;">Alasan Hapus <span style="color:red;">*</span></label>
        <input type="text" id="inputAlasanHapus" autocomplete="off" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:0; font-size:14px;" placeholder="Masukkan alasan...">
      </div>
      <p style="font-size:11px; color:#dc3545; margin:0;"><i class="fas fa-exclamation-triangle"></i> Data tidak dapat dikembalikan.</p>
    </div>
    <div style="padding:12px 16px; border-top:1px solid #eee; display:flex; gap:8px; justify-content:flex-end;">
      <button class="tutupModalHapusBtn" style="padding:6px 16px; border:1px solid #ccc; background:#f8f9fa; border-radius:0; cursor:pointer;">Batal</button>
      <button id="btnHapusKonfirm" style="padding:6px 16px; border:none; background:#dc3545; color:white; border-radius:0; cursor:pointer;">
        <i class="fas fa-trash-alt"></i> Hapus
      </button>
    </div>
  </div>
</div>

<!-- Modal konfirmasi hapus satu item nota -->
<div id="modalHapusItemNota" class="hapus-item-modal" aria-hidden="true">
  <div class="hapus-item-modal__backdrop" data-close-hapus-item></div>
  <div class="hapus-item-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="hapusItemModalTitle">
    <div class="hapus-item-modal__icon"><i class="fas fa-trash-alt"></i></div>
    <button type="button" class="hapus-item-modal__close" data-close-hapus-item aria-label="Tutup">&times;</button>
    <h5 id="hapusItemModalTitle">Hapus item dari nota?</h5>
    <p>Item <strong id="hapusItemNama"></strong> akan dihapus dari nota <strong id="hapusItemRef"></strong>.</p>
    <div class="hapus-item-modal__notice"><i class="fas fa-shield-alt"></i><span>Tindakan ini hanya berlaku karena nota belum dibayar, belum diproses, dan belum tuntas.</span></div>
    <label for="hapusItemNote">Alasan hapus <span>*</span></label>
    <input type="text" id="hapusItemNote" autocomplete="off" maxlength="255" placeholder="Contoh: salah input item">
    <div class="hapus-item-modal__actions">
      <button type="button" class="btn btn-light" data-close-hapus-item>Batal</button>
      <button type="button" class="btn btn-danger" id="btnKonfirmasiHapusItem"><i class="fas fa-trash-alt me-1"></i> Hapus item</button>
    </div>
  </div>
</div>
<style>
  .hapus-item-modal { display:none; position:fixed; inset:0; top:0; right:0; bottom:0; left:0; z-index:1000000; align-items:center; justify-content:center; padding:18px; }
  .hapus-item-modal.is-open { display:flex !important; }
  .hapus-item-modal__backdrop { position:absolute; inset:0; top:0; right:0; bottom:0; left:0; background:rgba(15,23,42,.58); backdrop-filter:blur(3px); }
  .hapus-item-modal__dialog { position:relative; z-index:1; width:100%; max-width:420px; padding:30px; border-radius:0; background:#fff; box-shadow:0 24px 60px rgba(15,23,42,.3); }
  .hapus-item-modal__icon { display:flex; align-items:center; justify-content:center; width:46px; height:46px; margin-bottom:14px; border-radius:0; background:#fee2e2; color:#dc2626; font-size:20px; }
  .hapus-item-modal__close { position:absolute; top:13px; right:16px; border:0; background:transparent; color:#64748b; font-size:28px; line-height:1; }
  .hapus-item-modal h5 { margin:0 0 8px; color:#172033; font-weight:800; }
  .hapus-item-modal p { margin:0 0 15px; color:#475569; line-height:1.5; }
  .hapus-item-modal__notice { display:flex; gap:9px; margin-bottom:16px; padding:10px 12px; border-radius:0; background:#fef2f2; color:#991b1b; font-size:12px; line-height:1.45; }
  .hapus-item-modal label { display:block; margin-bottom:6px; color:#334155; font-size:13px; font-weight:700; }
  .hapus-item-modal label span { color:#dc2626; }
  .hapus-item-modal input { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:0; outline:none; }
  .hapus-item-modal .btn { border-radius:0 !important; }
  .hapus-item-modal input:focus { border-color:#ef4444; box-shadow:0 0 0 2px rgba(239,68,68,.14); }
  .hapus-item-modal__actions { display:flex; justify-content:flex-end; gap:8px; margin-top:22px; }
</style>

<!-- SCRIPT -->

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
  // Configuration for view_load.js
  window.ViewLoadConfig = {
    baseUrl: '<?= URL::BASE_URL ?>',
    modeView: '<?= $data["modeView"] ?>',
    idPelanggan: '<?= $id_pelanggan ?>',
    namaPelanggan: <?= json_encode($nama_pelanggan) ?>,
    kodeCabang: '<?= $kodeCabang ?>',
    nonTunaiGuide: <?= json_encode(URL::NON_TUNAI_GUIDE) ?>,
    loadRekap: <?= json_encode($loadRekap) ?>,
    arrTuntas: <?= json_encode($arrTuntas) ?>,
    arrTuntasSerial: <?= json_encode(serialize($arrTuntas)) ?>,
    marginTop: <?= $this->mdl_setting["margin_printer_top"] ?? 0 ?>,
    feedLines: <?= $this->mdl_setting["margin_printer_bottom"] ?? 0 ?>
  };
</script>
<script src="<?= URL::IN_ASSETS ?>js/print_server.js?v=<?= time() ?>"></script>
<script src="<?= URL::IN_ASSETS ?>js/operasi/view_load.js?v=<?= time() ?>"></script>
<script>
  $(document).ready(function() {
      
      // Manual Modal Trigger to prevent Bootstrap 5 null reading hide error on spans
      $(document).on('click', '.gantiOperasi, .endLayanan, .addOperasi, .ambil, .editDurasi, .editMember, .editLayanan', function(e) {
          e.preventDefault();
          var target = $(this).attr('data-bs-target');
          if(target) {
              var modalEl = document.querySelector(target);
              if(modalEl) {
                  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                  modal.show();
              }
          }
      });
  });
</script>
