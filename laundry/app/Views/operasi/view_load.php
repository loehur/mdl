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
$canUnbindKurir = ((int) ($modeView ?? 0) === 1);

$renderKurirUnbindBadges = static function ($id, $ref, $code, $kind, $canClick, $surcasUnbindable = []) {
  if ($code === '') {
    return '';
  }
  $parts = [];
  if ($code === 'JA' || $code === 'J') {
    $parts[] = 'J';
  }
  if ($code === 'JA' || $code === 'A') {
    $parts[] = 'A';
  }
  if ($parts === []) {
    return '';
  }

  $out = '';
  foreach ($parts as $part) {
    $jenis = $part === 'J' ? 'jemput' : 'antar';
    $clickable = $canClick;
    if ($kind === 'riwayat') {
      $title = $part === 'J'
        ? 'Sudah dijemput (riwayat) — klik untuk lepas'
        : 'Sudah diantar (riwayat) — klik untuk lepas';
      $class = 'mdl-dlv-badge mdl-dlv-badge--' . strtolower($part);
      $label = $part;
    } else {
      $canUnbindSurcas = !empty($surcasUnbindable[$jenis]);
      $clickable = $canClick && $canUnbindSurcas;
      $title = $canUnbindSurcas
        ? ($part === 'J'
          ? 'Surcas jemput terikat — klik untuk lepas binding item ini saja'
          : 'Surcas antar terikat — klik untuk lepas binding item ini saja')
        : ($part === 'J'
          ? 'Surcas jemput terikat — item tunggal, gunakan Hapus surcas di baris nota'
          : 'Surcas antar terikat — item tunggal, gunakan Hapus surcas di baris nota');
      $class = 'mdl-kurir-bind-badge mdl-kurir-bind-badge--' . strtolower($part);
      $label = '$' . $part;
    }
    if ($clickable) {
      $class .= ' mdl-kurir-unbind-badge';
    }
    $out .= " <span class='" . $class . "'"
      . " title='" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "'"
      . ($clickable
        ? (" data-kind='" . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') . "'"
          . " data-jenis='" . htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') . "'"
          . " data-id='" . htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') . "'"
          . " data-ref='" . htmlspecialchars((string) $ref, ENT_QUOTES, 'UTF-8') . "'"
          . " role='button' tabindex='0'")
        : '')
      . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
  }

  return $out;
};
?>

<div class="mdl-nota-grid">

  <?php include __DIR__ . '/partials/pending_delivery_section.php'; ?>

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

    // Overpay = pembayaran Cek/Berhasil > tagihan (item + surcas)
    $dibayarRefGate = 0;
    foreach ((array) ($data['kas'] ?? []) as $byrGate) {
      if (($byrGate['ref_transaksi'] ?? '') != $ref) {
        continue;
      }
      $stGate = (int) ($byrGate['status_mutasi'] ?? 0);
      if ($stGate === 2 || $stGate === 3) {
        $dibayarRefGate += (int) ($byrGate['jumlah'] ?? 0);
      }
    }
    $tagihanRefGate = 0;
    foreach ($c_list as $itemGate) {
      if ((int) ($itemGate['member'] ?? 0) !== 0) {
        continue;
      }
      $qGate = round((float) ($itemGate['qty'] ?? 0), 2);
      $minGate = round((float) ($itemGate['min_order'] ?? 0), 2);
      $qRealGate = ($qGate < $minGate) ? $minGate : $qGate;
      $totGate = (float) ($itemGate['harga'] ?? 0) * $qRealGate;
      $dqGate = (float) ($itemGate['diskon_qty'] ?? 0);
      $dpGate = (float) ($itemGate['diskon_partner'] ?? 0);
      if ($dqGate > 0) {
        $totGate -= $totGate * ($dqGate / 100);
      }
      if ($dpGate > 0) {
        $totGate -= $totGate * ($dpGate / 100);
      }
      $tagihanRefGate += (int) round($totGate);
    }
    foreach ((array) ($data['surcas'] ?? []) as $scGate) {
      if (($scGate['no_ref'] ?? '') == $ref) {
        $tagihanRefGate += (int) ($scGate['jumlah'] ?? 0);
      }
    }
    $refIsOverpay = ($dibayarRefGate > $tagihanRefGate);

    // Hapus per item: multi-item + tidak overpay (boleh sudah ada bayar / sudah ada operasi)
    $canDeleteItemFromRef = ($modeView != 2 && $countItem[$ref] > 1 && !$refIsOverpay);

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
            <a href="#" class="mdl-nota-chip mdl-nota-chip--add mdl-nota-chip--icon tambahCas" data-ref="<?= $ref ?>" data-tr="id_transaksi" data-op-target="#exampleModalSurcas" title="Tambah Surcharge"><i class="fas fa-plus"></i></a>
            <a href="#" class="mdl-nota-chip mdl-nota-chip--detail mdl-nota-chip--icon btnNotaDetail" data-ref="<?= htmlspecialchars((string) $ref, ENT_QUOTES, 'UTF-8') ?>" title="Detail nota"><i class="fas fa-stream"></i></a>
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
            $canEditQty = ($modeView != 2 && $tuntas === 0 && !$refIsOverpay);
            $canDeleteThisItem = ($canDeleteItemFromRef && $tuntas === 0);
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

                      $bulanOrder = date('Y-m', strtotime($a['insertTime'] ?? ''));
                      $list_layanan .= "<span style='cursor:pointer' data-awal='" . $user . "' data-id='" . $o['id_operasi'] . "' data-tuntas='" . $tuntas . "' data-bulan='" . $bulanOrder . "' class='gantiOperasi' data-op-target='#modalGanti'><b><i class='fas fa-check-circle text-success'></i> <span class='fw-bold text-purple'>" . $c['kode'] . "</span> " . $user . "</b> <span style='white-space: pre;'>" . date('d/m H:i', strtotime($o['insertTime'])) . "</span></span><br>" . $buttonNotifSelesai;

                      $doneLayanan++;
                      $enHapus = false;
                    }
                  }
                  if ($check == 0) {
                    if ($b == $endLayanan) {
                      $list_layanan .= "<span style='cursor:pointer' id='" . $id . $b . "' data-layanan='" . $c['layanan'] . "' data-value='" . $c['id_layanan'] . "' data-id='" . $id . "' data-ref='" . $ref . "' data-op-target='#exampleModal' class='endLayanan'><i class='far fa-circle text-info'></i> " . $c['layanan'] . "</span><br><span class='d-none ambilAfterSelesai" . $id . $b . "'><a href='#' data-id='" . $id . "' data-ref='" . $ref . "' data-op-target='#exampleModal4' class='ambil text-dark ambil" . $id . "'><i class='far fa-circle'></i> Ambil</a></span>";
                    } else {
                      $list_layanan .= "<span style='cursor:pointer' id='" . $id . $b . "' data-layanan='" . $c['layanan'] . "' data-value='" . $c['id_layanan'] . "' data-id='" . $id . "' data-ref='" . $ref . "' data-op-target='#exampleModal' class='addOperasi'><i class='far fa-circle text-info'></i> " . $c['layanan'] . "</span><br>";
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
              $buttonAmbil = "<a href='#' data-id='" . $id . "' data-ref='" . $ref . "' data-op-target='#exampleModal4' class='ambil text-dark ambil" . $id . "'><i class='far fa-circle'></i> Ambil</a>";
            }

            $buttonUbahLayanan = "";
            if ($canEditItem && $doneLayanan == 0 && $id_ambil == 0 && $buttonAmbil === "") {
              $buttonUbahLayanan = "<span style='cursor:pointer' data-id='" . $id . "' data-ref='" . $ref . "' data-op-target='#modalUbahLayanan' class='editLayanan text-purple'><i class='fas fa-circle text-purple'></i> Ganti</span>";
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
              $show_total_cell = "<span class='editMember' style='cursor:pointer;' data-id='" . $id . "' data-ref='" . $ref . "' data-op-target='#modalUbahMember' title='Klik untuk ubah ke member'>" . $show_total . "</span>";
            }

            $showNote = "";
            if (strlen($f8) > 0) {
              $showNote = $f8;
            }

            $classDurasi = "";
            if (strpos($durasi, "EKSPRES") !== false || strpos($durasi, "KILAT") !== false || strpos($durasi, "PREMIUM") !== false) {
              $classDurasi = "fw-bold text-danger";
            }

            $kategoriHtml = "<span style='white-space: nowrap;'>" . htmlspecialchars((string) $kategori, ENT_QUOTES, 'UTF-8') . "</span>";
            if ($canEditItem) {
              $kategoriHtml = "<span class='editKategori' style='cursor:pointer; white-space: nowrap;' data-id='" . htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') . "' data-ref='" . htmlspecialchars((string) $ref, ENT_QUOTES, 'UTF-8') . "' data-op-target='#modalUbahKategori' title='Klik untuk ubah laundry'>" . htmlspecialchars((string) $kategori, ENT_QUOTES, 'UTF-8') . "</span>";
            }

            $durasiHtml = "<span class='" . $classDurasi . "' style='white-space: pre;'>" . $durasi . $f12 . "h " . $f13 . "j</span>";
            if ($canEditItem) {
              $durasiHtml = "<span class='" . $classDurasi . " editDurasi' style='cursor:pointer; white-space: pre;' data-id='" . $id . "' data-ref='" . $ref . "' data-op-target='#modalUbahDurasi' title='Klik untuk ubah durasi'>" . $durasi . $f12 . "h " . $f13 . "j</span>";
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
                <?php if ($canDeleteThisItem) { ?>
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
                <small><?= $id ?><?php
                  $surcasUnbindMap = $data['kurir_surcas_unbindable'][$id]
                    ?? ($data['kurir_surcas_unbindable'][(string) $id] ?? []);
                  $dlvBadge = $data['delivery_badge'][$id] ?? ($data['delivery_badge'][(string) $id] ?? '');
                  echo $renderKurirUnbindBadges($id, $ref, $dlvBadge, 'riwayat', $canUnbindKurir, $surcasUnbindMap);
                  $bindBadge = $data['kurir_bind_badge'][$id] ?? ($data['kurir_bind_badge'][(string) $id] ?? '');
                  echo $renderKurirUnbindBadges($id, $ref, $bindBadge, 'surcas', $canUnbindKurir, $surcasUnbindMap);
                ?></small><br><b><?= $kategoriHtml ?></b><span class='badge badge-light'></span><br><?= $durasiHtml ?><br>
                <?php if ($canEditQty) { ?>
                  <b><span class="editQty" style="cursor:pointer;color:#1d4ed8;text-decoration:underline;text-decoration-style:dotted;"
                    data-id="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>"
                    data-ref="<?= htmlspecialchars((string) $ref, ENT_QUOTES, 'UTF-8') ?>"
                    data-op-target="#modalUbahQty"
                    title="Klik untuk ubah quantity"><?= $show_qty ?></span></b>
                <?php } else { ?>
                  <b><?= $show_qty ?></b>
                <?php } ?>
                <?= $tampilDiskon ?><br><?= $itemList ?>
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

              $id_surcas = $sca['id_surcas'];
              $jumlahCas = $sca['jumlah'];
              $jidCas = (int) ($sca['id_jenis_surcas'] ?? 0);
              $jenisDlvCas = $jidCas === 3 ? 'jemput' : ($jidCas === 2 ? 'antar' : '');

              if ($jenisDlvCas !== '') {
                $doneCas = null;
                if (isset($data['delivery_done'][$ref][$jenisDlvCas]) && is_array($data['delivery_done'][$ref][$jenisDlvCas])) {
                  $doneCas = $data['delivery_done'][$ref][$jenisDlvCas];
                } elseif (isset($data['delivery_done'][(string) $ref][$jenisDlvCas]) && is_array($data['delivery_done'][(string) $ref][$jenisDlvCas])) {
                  $doneCas = $data['delivery_done'][(string) $ref][$jenisDlvCas];
                }
                $doneIdsCas = (is_array($doneCas) && isset($doneCas['ids']) && is_array($doneCas['ids'])) ? $doneCas['ids'] : [];
                $allDlvDone = ($countItem[$ref] ?? 0) > 0 && count($doneIdsCas) >= (int) $countItem[$ref];
                $labelDlvCas = $jenisDlvCas === 'jemput' ? 'Jemput' : 'Antar';
                if ($allDlvDone) {
                  $namaDlvCas = htmlspecialchars((string) ($doneCas['nama'] ?? 'Crew'), ENT_QUOTES, 'UTF-8');
                  $timeDlvCas = (string) ($doneCas['time'] ?? '');
                  $timeShowCas = $timeDlvCas !== '' ? date('d/m H:i', strtotime($timeDlvCas)) : '';
                  $tglCas = "<b><i class='fas fa-check-circle text-success'></i> " . $namaDlvCas . "</b> " . $labelDlvCas
                    . ($timeShowCas !== '' ? " <span style='white-space: pre;'>" . $timeShowCas . "</span>" : '')
                    . "<br>";
                } else {
                  $tglCas = "<a href='#' class='selesaiKurirSurcas text-dark'"
                    . " data-jenis='" . htmlspecialchars($jenisDlvCas, ENT_QUOTES, 'UTF-8') . "'"
                    . " data-ref='" . htmlspecialchars((string) $ref, ENT_QUOTES, 'UTF-8') . "'"
                    . " data-jumlah='" . htmlspecialchars((string) $jumlahCas, ENT_QUOTES, 'UTF-8') . "'"
                    . " data-id-surcas='" . htmlspecialchars((string) $id_surcas, ENT_QUOTES, 'UTF-8') . "'"
                    . "><i class='far fa-circle'></i> " . $labelDlvCas . "</a><br>";
                }
              } else {
                $tglCas = "<b><i class='fas fa-check-circle text-success'></i> Input <span style='white-space: pre;'>" . date('d/m H:i', strtotime($sca['insertTime'])) . "</span></b><br>";
              }

              $boundDeliveryReq = (int) ($sca['id_delivery_request'] ?? 0) > 0;
              $refTuntas = (int) ($first_item['tuntas'] ?? 0) !== 0;
              $canHapusSurcasKurir = (
                $jenisDlvCas !== ''
                && !$boundDeliveryReq
                && !$refIsOverpay
                && !$refTuntas
                && $modeView != 2
              );
              $canEditSurcasKurir = (
                $jenisDlvCas !== ''
                && !$refIsOverpay
                && !$refTuntas
                && $modeView != 2
              );
              $btnHapusSurcas = '';
              if ($canHapusSurcasKurir) {
                $btnHapusSurcas = "<a href='#' class='hapusSurcasKurir text-danger'"
                  . " data-id='" . (int) $id_surcas . "'"
                  . " data-ref='" . htmlspecialchars((string) $ref, ENT_QUOTES, 'UTF-8') . "'"
                  . " data-nama='" . htmlspecialchars((string) ($surcasNya ?? 'Surcas'), ENT_QUOTES, 'UTF-8') . "'"
                  . " title='Hapus surcas " . htmlspecialchars($labelDlvCas ?? $jenisDlvCas, ENT_QUOTES, 'UTF-8') . "'"
                  . " role='button'><i class='fas fa-trash-alt'></i></a> ";
              }

              $jumlahShow = number_format($jumlahCas);
              if ($canEditSurcasKurir) {
                $jumlahCell = "<span class='editSurcasKurir' style='cursor:pointer;color:#1d4ed8;text-decoration:underline;text-decoration-style:dotted;'"
                  . " data-id='" . (int) $id_surcas . "'"
                  . " data-ref='" . htmlspecialchars((string) $ref, ENT_QUOTES, 'UTF-8') . "'"
                  . " data-jumlah='" . (int) $jumlahCas . "'"
                  . " data-jenis='" . htmlspecialchars($jenisDlvCas, ENT_QUOTES, 'UTF-8') . "'"
                  . " data-nama='" . htmlspecialchars((string) ($surcasNya ?? 'Surcas'), ENT_QUOTES, 'UTF-8') . "'"
                  . " title='Klik untuk ubah jumlah surcas " . htmlspecialchars($labelDlvCas ?? $jenisDlvCas, ENT_QUOTES, 'UTF-8') . "'"
                  . " role='button'>" . $jumlahShow . "</span>";
              } else {
                $jumlahCell = $jumlahShow;
              }

              echo "<tr>
              <td class='text-center align-middle' style='vertical-align:middle;width:2rem;'>" . $btnHapusSurcas . "</td>
              <td class='align-middle'>" . $surcasNya . "</td>
              <td class='align-middle'>" . $tglCas . "</td>
              <td align='right' class='align-middle'>" . $jumlahCell . "</td>
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
            $deliveryCheck = $this->refDeliveryTuntasCheck((string) $ref);
            $deliverySelesai = (bool) ($deliveryCheck['ok'] ?? false);
            $deliveryBlockMsg = trim((string) ($deliveryCheck['message'] ?? ''));

            if ($lunas[$ref] == true && $countEndLayananDone[$ref] == $countItem[$ref] && $countAmbil[$ref] == $countItem[$ref] && $modeView <> 2 && $deliverySelesai) {
              array_push($arrTuntas, $ref);
            }

            if ($lunas[$ref] == false) {
              echo "<td nowrap colspan='3' class='text-right'><span class='showLunas" . $ref . "'></span><b> " . number_format($subTotal) . "</b><br>";
            } else {
              echo "<td nowrap colspan='3' class='text-right'><b><i class='fas fa-check-circle text-success'></i> " . number_format($subTotal) . "</b><br>";
              if (!$deliverySelesai && $countEndLayananDone[$ref] == $countItem[$ref] && $countAmbil[$ref] == $countItem[$ref] && $modeView <> 2 && $deliveryBlockMsg !== '') {
                echo "<small class='text-warning'>" . htmlspecialchars($deliveryBlockMsg, ENT_QUOTES, 'UTF-8') . "</small><br>";
              }
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

<!-- Modal Hapus Order -->
<div class="op-modal" id="modalHapusOrderInline" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="opHapusOrderTitle">
    <div class="op-modal__head op-modal__head--red">
      <div>
        <h3 id="opHapusOrderTitle"><i class="fas fa-trash-alt"></i> Hapus Order</h3>
        <small>Data tidak dapat dikembalikan</small>
      </div>
      <button type="button" class="op-modal__close tutupModalHapusBtn" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <p style="margin:0 0 12px;">Yakin ingin menghapus order <strong id="hapusRefText"></strong>?</p>
      <div class="op-field">
        <label class="op-label" for="inputAlasanHapus">Alasan Hapus <span style="color:#dc2626;">*</span></label>
        <input type="text" id="inputAlasanHapus" class="op-input" autocomplete="off" placeholder="Masukkan alasan hapus...">
      </div>
      <div id="hapusRefFeedback" class="d-none" style="margin:10px 0 0;padding:10px 12px;border:1px solid #fca5a5;background:#fef2f2;color:#7f1d1d;font-size:0.86rem;line-height:1.45;border-radius:8px;">
        <div id="hapusRefFeedbackTitle" style="font-weight:900;margin-bottom:6px;"></div>
        <div id="hapusRefFeedbackMessage"></div>
        <ul id="hapusRefFeedbackAlternatives" style="margin:8px 0 0;padding-left:18px;display:none;"></ul>
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost tutupModalHapusBtn" data-op-close>Batal</button>
      <button type="button" id="btnHapusKonfirm" class="op-btn op-btn--danger">
        <i class="fas fa-trash-alt"></i> Hapus
      </button>
    </div>
  </div>
</div>

<!-- Modal konfirmasi hapus satu item nota -->
<div class="op-modal" id="modalHapusItemNota" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close data-close-hapus-item></div>
  <div class="op-modal__panel" role="dialog" aria-modal="true" aria-labelledby="hapusItemModalTitle">
    <div class="op-modal__head op-modal__head--red">
      <div>
        <h3 id="hapusItemModalTitle">Hapus item permanen?</h3>
        <small>Syarat: belum tuntas, belum ada penyelesai, tidak overpay</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close data-close-hapus-item aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <p style="margin:0 0 12px;">Item <strong id="hapusItemNama"></strong> akan dihapus permanen dari nota <strong id="hapusItemRef"></strong> (tidak masuk antrean hapus).</p>
      <div class="op-alert" style="margin-top:0;margin-bottom:12px;">
        <i class="fas fa-shield-alt"></i>
        Total nota setelah hapus tidak boleh lebih kecil dari pembayaran Cek/Berhasil.
      </div>
      <div class="op-field">
        <label class="op-label" for="hapusItemNote">Alasan hapus <span style="color:#dc2626;">*</span></label>
        <input type="text" id="hapusItemNote" class="op-input" autocomplete="off" maxlength="255" placeholder="Contoh: salah input item">
      </div>
      <div id="hapusItemFeedback" class="d-none" style="margin:10px 0 0;padding:10px 12px;border:1px solid #fca5a5;background:#fef2f2;color:#7f1d1d;font-size:0.86rem;line-height:1.45;border-radius:8px;">
        <div id="hapusItemFeedbackTitle" style="font-weight:900;margin-bottom:6px;"></div>
        <div id="hapusItemFeedbackMessage"></div>
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close data-close-hapus-item>Batal</button>
      <button type="button" class="op-btn op-btn--danger" id="btnKonfirmasiHapusItem"><i class="fas fa-trash-alt"></i> Hapus item</button>
    </div>
  </div>
</div>

<!-- Modal konfirmasi unbind badge kurir (riwayat / surcas) -->
<div class="op-modal" id="modalUnbindKurir" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close data-close-unbind-kurir></div>
  <div class="op-modal__panel" role="dialog" aria-modal="true" aria-labelledby="unbindKurirModalTitle">
    <div class="op-modal__head op-modal__head--red">
      <div>
        <h3 id="unbindKurirModalTitle">Lepas binding kurir?</h3>
        <small id="unbindKurirModalSub">Order harus belum tuntas</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close data-close-unbind-kurir aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <p id="unbindKurirModalDesc" style="margin:0 0 12px;"></p>
      <div class="op-alert" style="margin-top:0;margin-bottom:12px;">
        <i class="fas fa-shield-alt"></i>
        <span id="unbindKurirModalHint">Tindakan ini dicatat di log sistem.</span>
      </div>
      <div class="op-field">
        <label class="op-label" for="unbindKurirNote">Alasan unbind <span style="color:#dc2626;">*</span></label>
        <input type="text" id="unbindKurirNote" class="op-input" autocomplete="off" maxlength="255" placeholder="Contoh: salah input jemput">
      </div>
      <div id="unbindKurirFeedback" class="d-none" style="margin:10px 0 0;padding:10px 12px;border:1px solid #fca5a5;background:#fef2f2;color:#7f1d1d;font-size:0.86rem;line-height:1.45;border-radius:8px;">
        <div id="unbindKurirFeedbackMessage"></div>
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close data-close-unbind-kurir>Batal</button>
      <button type="button" class="op-btn op-btn--danger" id="btnKonfirmasiUnbindKurir"><i class="fas fa-unlink"></i> Lepas binding</button>
    </div>
  </div>
</div>

<!-- Modal ubah jumlah surcas Antar/Jemput -->
<div class="op-modal" id="modalEditSurcasKurir" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close data-close-edit-surcas></div>
  <div class="op-modal__panel op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="editSurcasModalTitle">
    <div class="op-modal__head op-modal__head--blue">
      <div>
        <h3 id="editSurcasModalTitle">Ubah jumlah surcas</h3>
        <small>Syarat: belum tuntas, tidak overpay</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close data-close-edit-surcas aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <p style="margin:0 0 12px;">Nota <strong id="editSurcasRef"></strong> · <strong id="editSurcasNama"></strong></p>
      <div class="op-field">
        <label class="op-label" for="editSurcasJumlah">Jumlah baru (Rp)</label>
        <input type="number" id="editSurcasJumlah" class="op-input" min="0" step="1000" inputmode="numeric" placeholder="0 = gratis">
      </div>
      <div class="op-alert" style="margin-top:0;margin-bottom:0;">
        <i class="fas fa-shield-alt"></i>
        Total nota setelah ubah tidak boleh lebih kecil dari pembayaran Cek/Berhasil.
      </div>
      <div id="editSurcasFeedback" class="d-none" style="margin:10px 0 0;padding:10px 12px;border:1px solid #fca5a5;background:#fef2f2;color:#7f1d1d;font-size:0.86rem;line-height:1.45;border-radius:8px;">
        <div id="editSurcasFeedbackMessage"></div>
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close data-close-edit-surcas>Batal</button>
      <button type="button" class="op-btn op-btn--primary" id="btnKonfirmasiEditSurcas"><i class="fas fa-save"></i> Simpan</button>
    </div>
  </div>
</div>

<!-- Modal konfirmasi hapus surcas Antar/Jemput -->
<div class="op-modal" id="modalHapusSurcasKurir" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close data-close-hapus-surcas></div>
  <div class="op-modal__panel" role="dialog" aria-modal="true" aria-labelledby="hapusSurcasModalTitle">
    <div class="op-modal__head op-modal__head--red">
      <div>
        <h3 id="hapusSurcasModalTitle">Hapus surcas dari nota?</h3>
        <small>Syarat: belum tuntas, tidak terikat delivery request, tidak overpay</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close data-close-hapus-surcas aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <p style="margin:0 0 12px;">Surcas <strong id="hapusSurcasNama"></strong> akan dihapus dari nota <strong id="hapusSurcasRef"></strong>.</p>
      <div class="op-alert" style="margin-top:0;margin-bottom:12px;">
        <i class="fas fa-shield-alt"></i>
        Total nota setelah hapus tidak boleh lebih kecil dari pembayaran Cek/Berhasil.
      </div>
      <div class="op-field">
        <label class="op-label" for="hapusSurcasNote">Alasan hapus <span style="color:#dc2626;">*</span></label>
        <input type="text" id="hapusSurcasNote" class="op-input" autocomplete="off" maxlength="255" placeholder="Contoh: salah input surcas antar">
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close data-close-hapus-surcas>Batal</button>
      <button type="button" class="op-btn op-btn--danger" id="btnKonfirmasiHapusSurcas"><i class="fas fa-trash-alt"></i> Hapus surcas</button>
    </div>
  </div>
</div>

<!-- SCRIPT -->

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script src="<?= URL::IN_ASSETS ?>js/html2canvas.min.js"></script>
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
  if (window.OpDlvSelesai && typeof OpDlvSelesai.ensureKaryawanSelectize === 'function') {
    OpDlvSelesai.ensureKaryawanSelectize();
  }
  $(document).ready(function() {
      $(document).off('click.opModalTrigger').on('click.opModalTrigger', '.gantiOperasi, .endLayanan, .addOperasi, .ambil, .editDurasi, .editKategori, .editQty, .editMember, .editLayanan, .tambahCas', function(e) {
          e.preventDefault();
          var target = $(this).attr('data-op-target');
          if (target && window.OpModal) {
              window.OpModal.open(target.replace(/^#/, ''));
          }
      });
      $(document).off('click.selesaiKurirSurcas').on('click.selesaiKurirSurcas', 'a.selesaiKurirSurcas', function(e) {
          e.preventDefault();
          if (typeof window.openKurirDelivery !== 'function') {
              if (window.MdlToast) MdlToast.warn('Panel Kurir belum siap');
              return;
          }
          window.openKurirDelivery(
              $(this).attr('data-jenis') || '',
              $(this).attr('data-ref') || '',
              $(this).attr('data-jumlah'),
              0,
              $(this).attr('data-id-surcas')
          );
      });
  });
</script>
