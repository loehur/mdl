<?php
$dPelanggan = $data['data_pelanggan'];

if (isset($data['dataTanggal']) && count($data['dataTanggal']) > 0) {
    $currentMonth = $data['dataTanggal']['bulan'];
    $currentYear = $data['dataTanggal']['tahun'];

    $dateObj = DateTime::createFromFormat('!m', $currentMonth);
    $monthName = $dateObj->format('F'); // March

    $periode =
        '<br><small>Periode <b>' .
        $monthName .
        ' ' .
        $currentYear .
        '</b></small>';
} else {
    $currentMonth = date('m');
    $currentYear = date('Y');

    $dateObj = DateTime::createFromFormat('!m', $currentMonth);
    $monthName = $dateObj->format('F'); // March
    $periode = '';
}
?>

<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= URL::IN_ASSETS ?>icon/logo.png">
    <title><?= strtoupper($dPelanggan['nama_pelanggan']) ?> | MDL</title>
    <meta name="viewport" content="width=410, user-scalable=no">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/css/bootstrap.min.css">
    <style>
        .force-transparent, .force-transparent td, .force-transparent th, .force-transparent tr {
            background-color: transparent !important;
        }
        @font-face {
            font-family: "fontku";
            src: url("<?= URL::EX_ASSETS ?>font/Titillium-Regular.otf");
        }

        html .table {
            font-family: 'fontku', sans-serif;
        }

        html .content {
            font-family: 'fontku', sans-serif;
        }

        html body {
            font-family: 'fontku', sans-serif;
        }
    </style>
    <script>
        var unpaidInvoices = [];
    </script>
</head>

<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Filter Periode</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= URL::BASE_URL ?>I/i/<?= $dPelanggan['id_pelanggan'] ?>" method="POST">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">Bulan</label>
                            <select name="m" class="form-select form-select-sm" required>
                                <option class="text-end" value="01" <?php if ($currentMonth == '01') echo 'selected'; ?>>01</option>
                                <option class="text-end" value="02" <?php if ($currentMonth == '02') echo 'selected'; ?>>02</option>
                                <option class="text-end" value="03" <?php if ($currentMonth == '03') echo 'selected'; ?>>03</option>
                                <option class="text-end" value="04" <?php if ($currentMonth == '04') echo 'selected'; ?>>04</option>
                                <option class="text-end" value="05" <?php if ($currentMonth == '05') echo 'selected'; ?>>05</option>
                                <option class="text-end" value="06" <?php if ($currentMonth == '06') echo 'selected'; ?>>06</option>
                                <option class="text-end" value="07" <?php if ($currentMonth == '07') echo 'selected'; ?>>07</option>
                                <option class="text-end" value="08" <?php if ($currentMonth == '08') echo 'selected'; ?>>08</option>
                                <option class="text-end" value="09" <?php if ($currentMonth == '09') echo 'selected'; ?>>09</option>
                                <option class="text-end" value="10" <?php if ($currentMonth == '10') echo 'selected'; ?>>10</option>
                                <option class="text-end" value="11" <?php if ($currentMonth == '11') echo 'selected'; ?>>11</option>
                                <option class="text-end" value="12" <?php if ($currentMonth == '12') echo 'selected'; ?>>12</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Tahun</label>
                            <select name="Y" class="form-select form-select-sm" required>
                                <?php
                                $thisMonth = date('Y');
                                for ($x = $thisMonth - 1; $x <= $thisMonth; $x++) { ?>
                                    <option class="text-end" value="<?= $x ?>" <?php if ($currentYear == $x) echo 'selected'; ?>><?= $x ?></option>
                                <?php }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <a href="" class="btn btn-sm btn-outline-success">Tampilkan Semua</a>
                    <button type="submit" class="btn btn-sm btn-primary">Terapkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="content bg-light">
    <div class="mb-2 pt-2 pb-1 mx-0 bg-gradient bg-warning-subtle shadow-sm" style="position: sticky; top:0px; background-color:white;z-index:2">
        <div class="row p-1 mx-1"> <!-- header -->
            <div class="col m-auto" style="max-width: 480px;">
                <h5>Bpk/Ibu. <span class="text-purple"><b><?= strtoupper($dPelanggan['nama_pelanggan']) ?></b></span></h5><span><?php echo $periode; ?></span>
                <a href="#" data-bs-toggle="modal" class="btn btn-dark float-end" data-bs-target="#exampleModal"><i class="fas fa-filter"></i></a>
                <?php
                // saldo deposit
                if ($data['saldoTunai'] > 0) {
                    echo "<a class='mr-1' href='" . URL::BASE_URL . 'I/s/' . $dPelanggan['id_pelanggan'] . "'><span class='btn btn-sm btn-outline-primary'>Saldo Deposit</span></a>";
                }

                // paket
                $paket_count = count($data['listPaket']);
                if ($paket_count > 0) { ?>
                <?php foreach ($data['listPaket'] as $lp) {
                        $id_harga = $lp['id_harga'];
                        echo "<a class='mr-1' href='" . URL::BASE_URL . 'I/m/' .  $dPelanggan['id_pelanggan'] . '/' . $id_harga . "'><span class='btn btn-sm btn-outline-success'>Paket M" . $id_harga . '</span></a> ';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <?php
    $prevRef = '';
    $prevPoin = 0;

    $arrRef = [];
    $countRef = 0;

    $arrPoin = [];
    $jumlahRef = 0;

    foreach ($data['data_main'] as $a) {
        $ref = $a['no_ref'];

        if ($prevRef != $a['no_ref']) {
            $countRef = 0;
            $countRef++;
            $arrRef[$ref] = $countRef;
        } else {
            $countRef++;
            $arrRef[$ref] = $countRef;
        }
        $prevRef = $ref;
    }

    $no = 0;
    $urutRef = 0;
    $arrCount = 0;
    $arrPoiny = [];
    $arrGetPoin = [];
    $arrTotalPoin = [];
    $arrBayar = [];
    $arrTuntas = [];

    $Rtotal_tagihan = 0;
    $Rtotal_dibayar = 0;
    $Rsisa_tagihan = 0;

    if (count($data['data_main']) == 0 && count($data['data_member']) == 0) { ?>
        <div class="row mx-1 p-1">
            <div class='col m-auto w-100 p-0 m-1 ' style='max-width:460;'>
                <div class='bg-white  border border-success'>
                    <table class='table table-sm m-0  w-100'>
                        <td class="pl-2">
                            Tidak ada Tagihan Berjalan pada Bulan <b><?= $monthName . " " . $currentYear ?></b>
                        </td>
                    </table>
                </div>
            </div>
        </div>
        <?php } else {
        foreach ($data['data_main'] as $a) {
            $no++;
            $id = $a['id_penjualan'];
            $f10 = $a['id_penjualan_jenis'];
            $f3 = $a['id_item_group'];
            $f4 = $a['list_item'];
            $f5 = $a['list_layanan'];
            $f11 = $a['id_durasi'];
            $f6 = $a['qty'];
            $f7 = $a['harga'];
            $f8 = $a['note'];
            $f9 = $a['id_user'];
            $f1 = $a['insertTime'];
            $f12 = $a['hari'];
            $f13 = $a['jam'];
            $f14 = $a['diskon_qty'];
            $f15 = $a['diskon_partner'];
            $f16 = $a['min_order'];
            $f17 = $a['id_pelanggan'];
            $f18 = $a['id_user'];
            $noref = $a['no_ref'];
            $letak = $a['letak'];
            $id_ambil = $a['id_user_ambil'];
            $tgl_ambil = $a['tgl_ambil'];
            $timeRef = $f1;
            $member = $a['member'];
            $showMember = '';
            $cekDisable = '';

            $penjualan = '';
            $satuan = '';
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

            $show_qty = '';
            $qty_real = 0;
            if ($f6 < $f16) {
                $qty_real = $f16;
                $show_qty = $f6 . $satuan . ' (Min. ' . $f16 . $satuan . ')';
            } else {
                $qty_real = $f6;
                $show_qty = $f6 . $satuan;
            }

            if ($no == 1) {
                $adaBayar = false;
                echo '<div class="row p-1 mx-1">';
                echo "<div class='col m-auto w-100 p-0 m-1' style='max-width:460;'><div class=' bg-white shadow-sm border border-info'>";
                echo "<table class='table table-sm m-0 w-100 bg-transparent force-transparent'>";
                $lunas = false;
                $totalBayar = 0;
                $subTotal = 0;
                $urutRef++;
            }

            foreach ($data['kas'] as $byr) {
                if ($byr['ref_transaksi'] == $noref && $byr['status_mutasi'] <> 4) {
                    $idKas = $byr['id_kas'];
                    $arrBayar[$noref][$idKas] = $byr['jumlah'];
                    $totalBayar = array_sum($arrBayar[$noref]);
                }
                if ($byr['ref_transaksi'] == $noref) {
                    $adaBayar = true;
                }
            }

            $kategori = '';
            foreach ($this->itemGroup as $b) {
                if ($b['id_item_group'] == $f3) {
                    $kategori = $b['item_kategori'];
                }
            }

            $durasi = '';
            foreach ($this->dDurasi as $b) {
                if ($b['id_durasi'] == $f11) {
                    $durasi = '<small>' . ucwords($b['durasi']) . '</small>';
                }
            }

            $userAmbil = '';
            $endLayananDone = false;
            $list_layanan =
                "<small><b><i class='fas fa-check text-success'></i></b> Receipt <span style='white-space: pre;'>" .
                date('d/m H:i', strtotime($f1)) .
                '</span></small><br>';
            $list_layanan_print = '';
            $arrList_layanan = unserialize($f5);
            $endLayanan = end($arrList_layanan);
            $doneLayanan = 0;
            $countLayanan = count($arrList_layanan);
            foreach ($arrList_layanan as $b) {
                $check = 0;
                foreach ($this->dLayanan as $c) {
                    if ($c['id_layanan'] == $b) {
                        foreach ($data['operasi'] as $o) {
                            if (
                                $o['id_penjualan'] == $id &&
                                $o['jenis_operasi'] == $b
                            ) {
                                $check++;
                                if ($b == $endLayanan) {
                                    $endLayananDone = true;
                                }
                                $list_layanan =
                                    $list_layanan .
                                    "<small><b><i class='fas fa-check text-success'></i></b> " .
                                    $c['layanan'] .
                                    " <span style='white-space: pre;'>" .
                                    date('d/m H:i', strtotime($o['insertTime'])) .
                                    '</span></small><br>';
                                $doneLayanan++;
                                $enHapus = false;
                            }
                        }
                        if ($check == 0) {
                            if ($b == $endLayanan) {
                                $list_layanan =
                                    $list_layanan .
                                    "<span class=''><small><i class='far fa-circle text-info'></i> " .
                                    $c['layanan'] .
                                    '</small></span><br>';
                            } else {
                                $list_layanan =
                                    $list_layanan .
                                    "<span class=''><small><i class='far fa-circle text-info'></i> " .
                                    $c['layanan'] .
                                    '</small></span><br>';
                            }
                        }
                        $list_layanan_print =
                            $list_layanan_print . $c['layanan'] . ' ';
                    }
                }
            }

            $ambilDone = false;
            if ($id_ambil > 0) {
                $list_layanan =
                    $list_layanan .
                    "<small><b><i class='fas fa-check text-success'></i></b> Ambil <span style='white-space: pre;'>" .
                    date('d/m H:i', strtotime($tgl_ambil)) .
                    '</span></small><br>';
                $ambilDone = true;
            }

            $buttonAmbil = '';

            $list_layanan =
                $list_layanan . "<span class='operasiAmbil" . $id . "'></span>";

            $diskon_qty = $f14;
            $diskon_partner = $f15;

            $show_diskon_qty = '';
            if ($diskon_qty > 0) {
                $show_diskon_qty = $diskon_qty . '%';
            }
            $show_diskon_partner = '';
            if ($diskon_partner > 0) {
                $show_diskon_partner = $diskon_partner . '%';
            }
            $plus = '';
            if ($diskon_qty > 0 && $diskon_partner > 0) {
                $plus = ' + ';
            }
            $show_diskon = $show_diskon_qty . $plus . $show_diskon_partner;

            $total = $f7 * $qty_real;

            if ($member == 0) {
                if ($diskon_qty > 0 && $diskon_partner == 0) {
                    $total = $total - $total * ($diskon_qty / 100);
                } else if ($diskon_qty == 0 && $diskon_partner > 0) {
                    $total = $total - $total * ($diskon_partner / 100);
                } else if ($diskon_qty > 0 && $diskon_partner > 0) {
                    $total = $total - $total * ($diskon_qty / 100);
                    $total = $total - $total * ($diskon_partner / 100);
                } else {
                    $total = $f7 * $qty_real;
                }
            } else {
                $total = 0;
            }

            $subTotal = $subTotal + $total;
            $Rtotal_tagihan = $Rtotal_tagihan + $total;

            foreach ($arrRef as $key => $m) {
                if ($key == $noref) {
                    $arrCount = $m;
                }
            }

            $show_total = '';
            $show_total = '';

            if ($member == 0) {
                if (strlen($show_diskon) > 0) {
                    $tampilDiskon = '<small><br>(Disc. ' . $show_diskon . ')</small>';
                    $show_total =
                        '<del>Rp' .
                        number_format($f7 * $qty_real) .
                        '</del><br>Rp' .
                        number_format($total);
                } else {
                    $tampilDiskon = '';
                    $show_total = 'Rp' . number_format($total);
                }
            } else {
                $show_total =
                    "<span class='text-nowrap text-sm text-success'><small><b>Member</b></small></span><br><span>-" .
                    $show_qty .
                    '&nbsp;</span>';
                $tampilDiskon = '';
            }

            $showNote = '';
            if (strlen($f8) > 0) {
                $showNote = $f8;
            }

            $classTRDurasi = '';
            if ($f11 != 11) {
                $classTRDurasi = 'table-warning';
            }

            if ($totalBayar > 0) {
                $cekDisable = 'disabled';
            } else {
                $cekDisable = '';
            }
            echo '<tr>';
            echo "<td class='pt-0 pb-0'><span style='white-space: nowrap;'></span><small> 
                $id 
                </small> <span style='white-space: pre;'>" .
                $durasi .
                ' <small>(' .
                $f12 .
                'h ' .
                $f13 .
                'j)</span><br><b>' .
                $kategori .
                "</b><span class='badge badge-light'></span><br><b>" .
                $show_qty .
                '</b> ' .
                '</td>';
            echo "<td nowrap class='pt-1'>" . $list_layanan . '</td>';
            echo "<td class='text-end text-sm'>" .
                $show_total . $tampilDiskon .
                '</td>';
            echo '</tr>';
            echo '<tr>';
            if (strlen($f8) > 0) {
                echo "<td style='border-top:0' colspan='5' class='m-0 pt-0'><span class='badge badge-warning'>" .
                    $f8 .
                    '</span></td>';
            }
            echo ' </tr>';

            $showMutasi = '';
            $userKas = '';
            foreach ($data['kas'] as $ka) {
                if ($ka['ref_transaksi'] == $noref) {
                    $stBayar = '';
                    foreach ($this->dStatusMutasi as $st) {
                        if ($ka['status_mutasi'] == $st['id_status_mutasi']) {
                            $stBayar = $st['status_mutasi'];
                        }
                    }

                    $notenya = strtoupper($ka['note']);

                    switch ($ka['status_mutasi']) {
                        case '2':
                            $statusM =
                                "<span class='text-info'>" .
                                $stBayar .
                                ' <b>(' .
                                $notenya .
                                ')</b></span> ';
                            break;
                        case '3':
                            $statusM =
                                "<b><i class='fas fa-check text-success'></i></b> " .
                                $notenya .
                                ' ';
                            break;
                        case '4':
                            $statusM =
                                "<span class='text-danger text-bold'><i class='fas fa-times-circle'></i> " .
                                $stBayar .
                                ' <b>(' .
                                $notenya .
                                ')</b></span> ';
                            break;
                        default:
                            $statusM = 'Non Status - ';
                            break;
                    }

                    if ($ka['status_mutasi'] == 4) {
                        $nominal = '<s>-Rp' . number_format($ka['jumlah']) . '</s>';
                    } else {
                        $nominal = '-Rp' . number_format($ka['jumlah']);
                    }

                    $showMutasi =
                        $showMutasi .
                        '<small>' .
                        $statusM .
                        '#' .
                        $ka['id_kas'] .
                        ' ' .
                        date('d/m H:i', strtotime($ka['insertTime'])) .
                        ' ' .
                        $nominal .
                        '</small><br>';
                }
            }

            if ($arrCount == $no) {

                //SURCAS
                foreach ($data['surcas'] as $sca) {
                    if ($sca['no_ref'] == $noref) {
                        foreach ($this->surcasPublic as $sc) {
                            if ($sc['id_surcas_jenis'] == $sca['id_jenis_surcas']) {
                                $surcasNya = $sc['surcas_jenis'];
                            }
                        }

                        $jumlahCas = $sca['jumlah'];
                        $Rtotal_tagihan += $jumlahCas;
                       
                        $tglCas =
                            "<small><i class='fas fa-check text-success'></i> Surcharged <span style='white-space: pre;'>" .
                            date('d/m H:i', strtotime($sca['insertTime'])) .
                            '</span></small><br>';
                        echo '<tr><td><small>' .
                            $surcasNya .
                            '</small></td><td>' .
                            $tglCas .
                            "</td><td align='right'>" .
                            ' Rp' .
                            number_format($jumlahCas) .
                            '</td></tr>';
                        $subTotal += $jumlahCas;
                    }
                }

                $Rtotal_dibayar = $Rtotal_dibayar + $totalBayar;
                $sisaTagihan = intval($subTotal) - $totalBayar;
                if ($sisaTagihan < 1) {
                    $lunas = true;
                }
                echo "<tr class='row" . $noref . " table-borderless'>";
                if (
                    $lunas == true &&
                    $endLayananDone == true &&
                    $ambilDone == true
                ) {
                    array_push($arrTuntas, $noref);
                }
                if ($lunas == false) {
                    echo "<td nowrap colspan='3' class='text-end pt-0 pb-0'><span class='showLunas" . $noref . "'></span><b> Rp" . number_format($subTotal) . '</b><br>';
                    // Push to unpaidInvoices if no prior payment but still unpaid
                    if ($adaBayar == false) {
                        echo "<script>unpaidInvoices.push({ref: 'T_" . $noref . "', amount: " . intval($subTotal) . "});</script>";
                    }
                } else {
                    echo "<td nowrap colspan='3' class='text-end pt-0 pb-0'><b><i class='fas fa-check text-success'></i> Rp" . number_format($subTotal) . '</b><br>';
                }
                echo '</td></tr>';

                if ($adaBayar == true) {
                    echo "<tr class='row" . $noref . " table-borderless'>";
                    echo "<td nowrap colspan='4' class='text-end pt-0 pb-0'>";
                    echo $showMutasi;
                    echo "<span class='text-danger sisaTagihan" . $noref . "'>";
                    if (
                        $sisaTagihan < intval($subTotal) &&
                        intval($sisaTagihan) > 0
                    ) {
                        echo "<b><i class='fas fa-exclamation-circle'></i> Sisa Rp" .
                            number_format($sisaTagihan) .
                            '</b>';
                        echo "<script>unpaidInvoices.push({ref: 'T_" . $noref . "', amount: " . $sisaTagihan . "});</script>";
                    }
                    echo '</span>';
                    echo '</td>';
                    echo '</tr>';
                }
        ?>
            <?php
                $totalBayar = 0;
                $sisaTagihan = 0;
                $no = 0;
                $subTotal = 0;

                echo '</tbody></table>';
                echo '</div></div></div>';
            }
        }

        //DEPOSIT MEMBER
        foreach ($data['data_member'] as $z) { ?>
            <?php
            $id = $z['id_member'];
            $id_harga = $z['id_harga'];
            $harga = $z['harga'];
            $id_user = $z['id_user'];
            $kategori = '';
            $layanan = '';
            $durasi = '';
            $unit = '';
            $cekDisable = '';

            $showMutasi = '';
            $userKas = '';
            foreach ($data['kasM'] as $ka) {
                if ($ka['ref_transaksi'] == $id) {
                    $stBayar = '';
                    foreach ($this->dStatusMutasi as $st) {
                        if (
                            $ka['status_mutasi'] == $st['id_status_mutasi']
                        ) {
                            $stBayar = $st['status_mutasi'];
                        }
                    }

                    $notenya = strtoupper($ka['note']);
                    $st_mutasi = $ka['status_mutasi'];

                    switch ($st_mutasi) {
                        case '2':
                            $statusM =
                                "<span class='text-info'>" .
                                $stBayar .
                                ' <b>(' .
                                $notenya .
                                ')</b></span> - ';
                            break;
                        case '3':
                            $statusM =
                                "<b><i class='fas fa-check text-success'></i></b> " .
                                $notenya .
                                ' ';
                            break;
                        case '4':
                            $statusM =
                                "<span class='text-danger text-bold'><i class='fas fa-times-circle'></i> " .
                                $stBayar .
                                ' <b>(' .
                                $notenya .
                                ')</b></span> - ';
                            break;
                        default:
                            $statusM = 'Non Status - ';
                            break;
                    }

                    if ($st_mutasi == 4) {
                        $nominal =
                            '<s>-Rp' .
                            number_format($ka['jumlah']) .
                            '</s>';
                    } else {
                        $nominal = '-Rp' . number_format($ka['jumlah']);
                    }

                    $showMutasi =
                        $showMutasi .
                        '<small>' .
                        $statusM .
                        '<b>#' .
                        $ka['id_kas'] .
                        ' </b> [' .
                        date('d/m H:i', strtotime($ka['insertTime'])) .
                        '] ' .
                        $nominal .
                        '</small><br>';
                }
            }

            foreach ($this->harga as $a) {
                if ($a['id_harga'] == $z['id_harga']) {
                    foreach ($this->dPenjualan as $dp) {
                        if (
                            $dp['id_penjualan_jenis'] ==
                            $a['id_penjualan_jenis']
                        ) {
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
                                $layanan = $layanan . ' ' . $c['layanan'];
                            }
                        }
                    }
                    foreach ($this->dDurasi as $c) {
                        if ($a['id_durasi'] == $c['id_durasi']) {
                            $durasi = $durasi . ' ' . $c['durasi'];
                        }
                    }

                    foreach ($this->itemGroup as $c) {
                        if ($a['id_item_group'] == $c['id_item_group']) {
                            $kategori =
                                $kategori . ' ' . $c['item_kategori'];
                        }
                    }
                }
            }
            $adaBayar = false;
            $historyBayar = [];
            foreach ($data['kasM'] as $k) {
                if (
                    $k['ref_transaksi'] == $id &&
                    $k['status_mutasi'] == 3
                ) {
                    array_push($historyBayar, $k['jumlah']);
                }
                if ($k['ref_transaksi'] == $id) {
                    $adaBayar = true;
                }
            }

            $statusBayar = '';
            $totalBayar = array_sum($historyBayar);
            $showSisa = '';
            $sisa = $harga;
            $lunas = false;
            $enHapus = true;
            if ($totalBayar > 0) {
                $enHapus = false;
                if ($totalBayar >= $harga) {
                    $lunas = true;
                    $statusBayar =
                        "<b><i class='fas fa-check text-success'></i></b>";
                    $sisa = 0;
                } else {
                    $sisa = $harga - $totalBayar;
                    $showSisa =
                        "<b><i class='fas fa-exclamation-circle'></i> Sisa Rp" .
                        number_format($sisa) .
                        '</b>';
                    $lunas = false;
                    echo "<script>unpaidInvoices.push({ref: 'M_" . $id . "', amount: " . $sisa . "});</script>";
                }
            } else {
                $lunas = false;
            }

            $Rtotal_tagihan = $Rtotal_tagihan + $sisa;

            if ($lunas == false) { ?>
                <div class="row p-1 mx-0">
                    <div class='col m-auto w-100 backShow " . strtoupper($pelanggan) . " p-0 m-1 ' style='max-width:460;'>
                        <div class='bg-white  border border-primary'>
                            <table class='table table-sm m-0  w-100'>
                                <tbody>
                                    <tr>
                                        <td nowrap>
                                            <small><?= '[' .
                                                        $id .
                                                        '] <b>Topup Paket Member</b> [' .
                                                        date('d/m H:i', strtotime($z['insertTime'])) .
                                                        ']' ?>
                                                <br><b><?= $z['qty'] .
                                                            $unit .
                                                            '</b> | ' .
                                                            $kategori ?> * <?= $layanan ?> * <?= $durasi ?></small>
                                        </td>
                                        <td nowrap class="text-end"><span id="statusBayar<?= $id ?>"><?= $statusBayar ?></span>&nbsp;
                                            <span class="float-end"><b>Rp<?= number_format(
                                                                                                    $harga
                                                                                                ) ?></b></span>
                                        </td>
                                    </tr>
                                    <?php if ($adaBayar == true) { ?>
                                        <tr>
                                            <td colspan="2" align="right"><span id="historyBayar<?= $id ?>"><?= $showMutasi ?></span>
                                                </span><span id="sisa<?= $id ?>" class="text-danger"><?= $showSisa ?></span></td>
                                        </tr>
                                    <?php }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
    <?php }
        }
    }
    ?>

    <div class="row p-1 mx-1">
        <div class="col m-auto w-100  border border-danger bg-danger-subtle" style="max-width: 460;">
            <div class="row">
                <div class="col px-1">
                    <b>Sisa Tagihan</b>
                </div>
                <div class="col px-1 text-end">
                    <b>Rp<span id='sisa'><?= number_format(
                                                $Rtotal_tagihan - $Rtotal_dibayar
                                            ) ?></span></b>
                </div>
            </div>
        </div>
    </div>

  <?php if (isset($data['finance_history']) && count($data['finance_history']) > 0) { ?>
    <div class="row p-1 mx-1">
    <div class="col px-0 m-auto w-100 mb-5 border" style="max-width: 460;">
      <table class='table table-sm m-0 w-100'>
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
            <td class=''>
              <button class="btn btn-sm btn-white"><?= $fh['note']; ?></button>
            </td>
            <td class='text-end'><button class="btn btn-sm btn-white">Rp<?= number_format($fh['total']) ?></button></td>
            <td class="text-end">
                <button type='button' class='btn btn-light btn-sm tokopayOrder text-primary' data-ref='<?= $fh['ref_finance'] ?>'
                  data-total='<?= (int) $fh['total'] ?>'
                  data-note='<?= $fh['note'] ?>'>Cek Status</button>
                <?php if ($fh['status'] != 3) { ?>
                <button type='button' class='btn btn-sm btn-link text-danger cancelPayment p-0 ms-1' 
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

   <div class="pb-5 pt-5"></div>
    <?php $bill_final = $Rtotal_tagihan - $Rtotal_dibayar;
    if ($bill_final > 0) { ?>
        <div style="position: fixed; bottom: 0; left: 0; width: 100%; background: white; padding: 20px 0; box-shadow: 0 -4px 10px rgba(0,0,0,0.1); z-index: 1000; text-align: center;">
            <button class="btn fw-bold btn-warning rounded-3 shadow-lg py-2 px-3 " id="btnBayarFloat">
                <i class="fas fa-wallet"></i> &nbsp;Bayar
            </button>
        </div>

        <!-- Modal Bayar -->
        <div class="modal fade" id="modalBayar" tabindex="-1" aria-labelledby="modalBayarLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalBayarLabel">Pembayaran Tagihan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="formBayar">
                            <input type="hidden" name="id_pelanggan" value="<?= $dPelanggan['id_pelanggan'] ?>">
                            <div class="mb-3">
                                <label for="metodePembayaran" class="form-label">Metode Pembayaran</label>
                                <select class="form-select" id="metodePembayaran" name="metode">
                                    <?php foreach (URL::NON_TUNAI as $nt) { ?>
                                        <option value="<?= $nt ?>"><?= $nt ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Pilih Tagihan:</label>
                                <div class="list-group" id="listTagihanBayar">
                                    <!-- Tagihan items will be loaded here by JS -->
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <h5>Total Bayar:</h5>
                                <h5 id="totalBayarModal">Rp0</h5>
                            </div>
                            <div class="text-danger" id="bayarStatus"></div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btnSubmitBayar">Bayar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>plugins/jquery/jquery.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    var unpaidInvoices = unpaidInvoices || []; // Defined in inline PHP

    $(document).ready(function() {
        if(unpaidInvoices.length === 0) {
            $('#btnBayarFloat').hide();
        }
    });

    $('#btnBayarFloat').click(function() {
        renderTagihanList();
        var myModal = new bootstrap.Modal(document.getElementById('modalBayar'));
        myModal.show();
    });

    function renderTagihanList() {
        var html = '';
        var total = 0;
        if(unpaidInvoices.length > 0) {
             unpaidInvoices.forEach(function(item) {
                var refDisplay = item.ref.substring(2);
                var type = item.ref.substring(0, 1) == 'T' ? 'Transaksi' : 'Member';
                html += '<label class="list-group-item d-flex justify-content-between align-items-center action-list-item" style="cursor:pointer">';
                html += '<div>';
                html += '<input class="form-check-input me-2 checkTagihan" type="checkbox" value="' + item.amount + '" data-ref="' + item.ref + '" checked>';
                html += '<small class="text-muted">' + type + ' #' + refDisplay + '</small>';
                html += '</div>';
                html += '<span class="fw-bold">Rp' + parseInt(item.amount).toLocaleString('id-ID') + '</span>';
                html += '</label>';
                total += parseInt(item.amount);
            });
        } else {
            html = '<div class="alert alert-success m-2">Tidak ada tagihan.</div>';
        }
       
        $('#listTagihanBayar').html(html);
        calculateTotal();
        
        $('.checkTagihan').change(function() {
            calculateTotal();
        });
        
        // Allow clicking row to toggle checkbox
        $('.list-group-item').click(function(e) {
            if(e.target.type !== 'checkbox') {
                var cb = $(this).find('input[type="checkbox"]');
                cb.prop('checked', !cb.prop('checked')).trigger('change');
            }
        });
    }

    function calculateTotal() {
        var total = 0;
        $('.checkTagihan:checked').each(function() {
            total += parseInt($(this).val());
        });
        $('#totalBayarModal').text('Rp' + total.toLocaleString('id-ID'));
    }

    $('#btnSubmitBayar').click(function() {
        var rekap = {};
        var count = 0;
        
        $('.checkTagihan:checked').each(function() {
            var ref = $(this).data('ref');
            var amount = $(this).val();
            rekap[ref] = amount; 
            count++;
        });

        if(count == 0) {
            $('#bayarStatus').text('Pilih minimal satu tagihan.');
            return;
        }

        var postData = {
            id_pelanggan: $('input[name="id_pelanggan"]').val(),
            metode: $('select[name="metode"]').val(),
            rekap: rekap
        };
        
        $('#bayarStatus').text('Memproses pembayaran...');
        var originalBtnText = $(this).text();
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');

        $.post('<?= URL::BASE_URL ?>I/bayar', postData, function(response) {
            if(response == 0) {
                location.reload();
            } else {
                $('#bayarStatus').text('Error: ' + response);
                $('#btnSubmitBayar').prop('disabled', false).html(originalBtnText);
            }
        }).fail(function() {
             $('#bayarStatus').text('Terjadi kesalahan jaringan.');
             $('#btnSubmitBayar').prop('disabled', false).html(originalBtnText);
        });
    });

    </script>
    <!-- Modal Info Status -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Status Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" id="statusModalBody">
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    </script>

    <!-- Modal QR Code (Simple Version) -->
    <div class="modal fade" id="modalQR" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title">Scan QRIS</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center">
             <!-- Placeholder for QR IF we have it, otherwise just status info -->
            <div id="qrcode" class="d-flex justify-content-center mb-3"></div>
            <p class="mb-0 fw-bold" id="qrTotal"></p>
            <p class="mb-0" id="qrNama"></p>
            <div id="devModeLabel" class="mt-2 d-none">
              <span class="badge bg-warning text-dark">DEV MODE - FAKE QR</span>
              <div class="alert alert-secondary mt-1 p-1 small text-start" style="font-size: 0.7rem; overflow-wrap: break-word; max-height: 100px; overflow-y: auto;" id="devApiRes"></div>
            </div>
          </div>
          <div class="modal-footer justify-content-center">
            <button type="button" class="btn btn-warning btn-sm" id="btnCekStatusQR"><i class="fas fa-sync"></i> Cek Status</button>
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Cancel Payment Confirmation -->
    <div class="modal fade" id="modalCancelPayment" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
          <div class="modal-body text-center p-4">
            <div class="mb-3">
              <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
            </div>
            <h5 class="mb-2">Batalkan Pembayaran?</h5>
            <p class="text-muted mb-2" id="cancelPaymentInfo"></p>
            <p class="small text-danger mb-3">Data pembayaran akan dihapus permanen.</p>
            <div class="d-flex gap-2 justify-content-center">
              <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
              <button type="button" class="btn btn-danger px-4" id="btnConfirmCancel">
                <i class="fas fa-trash-alt"></i> Hapus
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
    var nonTunaiGuide = <?= json_encode($data['nonTunaiGuide'] ?? []) ?>;
    var customerName = '<?= addslashes($dPelanggan['nama_pelanggan']) ?>';
    var currentQRData = { qrString: '', total: 0, nama: '', ref_id: '' };

    // Copy to clipboard helper
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(function() {
            var originalHtml = $(btn).html();
            $(btn).html('<i class="fas fa-check"></i>');
            $(btn).removeClass('btn-outline-secondary btn-outline-danger').addClass('btn-success');
            setTimeout(function() {
                $(btn).html(originalHtml);
                $(btn).removeClass('btn-success').addClass(originalHtml.includes('danger') ? 'btn-outline-danger' : 'btn-outline-secondary');
            }, 1500);
        }).catch(function() {
            alert('Gagal menyalin. Silakan copy manual.');
        });
    }

    // Function to show QR code in modal
    function showQR(qrString, total, nama, isDev, devRes, ref_id) {
        console.log('Calling showQR with real QR'); // DEBUG
        console.log('showQR called:', qrString.substring(0, 50) + '...', total); // DEBUG
        console.log('Customer:', nama); // DEBUG
        
        var modalEl = document.getElementById('modalQR');
        console.log('modalQR element:', modalEl); // DEBUG
        if (!modalEl) return;

        // Store QR data for later use
        currentQRData = {
            qrString: qrString,
            total: total,
            nama: nama,
            ref_id: ref_id
        };

        // Clear previous QR completely
        console.log('Clearing previous QR'); // DEBUG
        var qrEl = document.getElementById('qrcode');
        if (qrEl) {
            qrEl.innerHTML = '';
            // Also remove any canvas/img that QRCode library might have created
            while (qrEl.firstChild) {
                qrEl.removeChild(qrEl.firstChild);
            }
        }

        // Generate QR using library
        console.log('QRCode library exists:', typeof QRCode !== 'undefined'); // DEBUG
        if (typeof QRCode !== 'undefined') {
            try {
                new QRCode(qrEl, {
                    text: qrString,
                    width: 200,
                    height: 200,
                    correctLevel : QRCode.CorrectLevel.L
                });
                console.log('QRCode generated successfully'); // DEBUG
            } catch (e) {
                console.error('QR Code error:', e);
                qrEl.innerHTML = '<i class="fas fa-qrcode fa-5x text-dark"></i>';
            }
        } else {
            console.error('QRCode library not loaded!');
            qrEl.innerHTML = '<i class="fas fa-qrcode fa-5x text-dark"></i>';
        }

        // Set text
        console.log('Setting text values'); // DEBUG
        var fmtTotal = new Intl.NumberFormat('id-ID').format(total);
        $('#qrTotal').text('Rp ' + fmtTotal);
        $('#qrNama').text(nama.toUpperCase());
        
        // Dev Mode Handling - show debug info if isDev
        var devLabel = document.getElementById('devModeLabel');
        var devApiRes = document.getElementById('devApiRes');
        if (devLabel && devApiRes) {
            if (isDev) {
                devLabel.classList.remove('d-none');
                var apiResText = typeof devRes === 'object' ? JSON.stringify(devRes, null, 2) : devRes;
                devApiRes.textContent = apiResText;
            } else {
                devLabel.classList.add('d-none');
            }
        }

        // Show modal
        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }

    // Cek Status QR button handler
    $('#btnCekStatusQR').off('click').on('click', function() {
        var btn = $(this);
        var data = currentQRData;

        if (!data || !data.ref_id) {
            alert('Data transaksi tidak ditemukan.');
            return;
        }

        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Checking...');

        $.ajax({
            url: '<?= URL::BASE_URL ?>I/payment_gateway_check_status/' + data.ref_id,
            type: 'GET',
            dataType: 'JSON',
            success: function(response) {
                if (response.status == 'PAID') {
                    $('#qrcode').html('<div class="text-success text-center"><i class="fas fa-check-circle fa-5x"></i><h3 class="mt-2">LUNAS/PAID</h3></div>');
                    btn.removeClass('btn-warning').addClass('btn-success').html('<i class="fas fa-check"></i> PAID');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    alert('Status: ' + (response.status || 'Unknown') + '\nSilahkan cek ulang beberapa saat lagi.');
                    btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function() {
                alert('Gagal mengecek status.');
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Cek Status Pembayaran (Mirroring Operasi logic)
    $('.tokopayOrder').on('click', function(e) {
        e.preventDefault();
        var ref = $(this).data('ref');
        var note = $(this).data('note');
        var total = $(this).data('total');
        var btn = $(this);
        var originalText = btn.text();
        
        btn.prop('disabled', true).text('Checking...');

        if (note && note.toUpperCase() === 'QRIS') {
            // QRIS: Call payment_gateway_order to get QR string (same as Operasi view)
            $.ajax({
                url: '<?= URL::BASE_URL ?>I/payment_gateway_order/' + ref + '?nominal=' + total + '&metode=' + encodeURIComponent(note),
                type: 'GET',
                dataType: 'JSON',
                success: function(response) {
                    console.log('payment_gateway_order response:', response); // DEBUG
                    btn.prop('disabled', false).text(originalText);

                    if (response.status === 'paid') {
                        // Already paid, reload
                        location.reload();
                        return;
                    }

                    var qrString = response.qr_string;
                    console.log('qr_string:', qrString); // DEBUG
                    
                    if (qrString) {
                        // Show real QR
                        showQR(qrString, total, customerName, false, null, ref);
                    } else {
                        // Fallback: Generate random QR for dev mode
                        var randomQR = Array(241).join((Math.random().toString(36) + '00000000000000000').slice(2, 18)).slice(0, 240);
                        showQR(randomQR, total, customerName, true, response, ref);
                    }
                },
                error: function(xhr, status, error) {
                    btn.prop('disabled', false).text(originalText);
                    // Fallback on error
                    var randomQR = Array(241).join((Math.random().toString(36) + '00000000000000000').slice(2, 18)).slice(0, 240);
                    showQR(randomQR, total, customerName, true, { error: error }, ref);
                }
            });
        } else {
            // Non-QRIS: Check status and show payment guide
            $.ajax({
                url: '<?= URL::BASE_URL ?>I/payment_gateway_check_status/' + ref,
                type: 'GET',
                dataType: 'JSON',
                success: function(response) {
                    btn.prop('disabled', false).text(originalText);

                    if (response.status == 'PAID') {
                        btn.removeClass('btn-warning').addClass('btn-outline-success').html('<i class="fas fa-check-circle"></i> Paid');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else if (response.status == 'PENDING') {
                        // Non-QRIS Logic - Display detailed payment guide
                        var guideData = nonTunaiGuide[note];
                        var totalFmt = new Intl.NumberFormat('id-ID').format(total);
                        
                        var html = '<div class="text-center">';
                        
                        if (guideData && typeof guideData === 'object') {
                            html += '<h5 class="text-primary fw-bold mb-3">' + (guideData.label || note) + '</h5>';
                            html += '<div class="bg-light rounded p-3 mb-3">';
                            html += '<p class="mb-1 text-muted small">Nomor Rekening:</p>';
                            html += '<div class="d-flex align-items-center justify-content-center gap-2">';
                            html += '<h4 class="fw-bold mb-0" style="letter-spacing: 2px;" id="copyNumber">' + (guideData.number || '-') + '</h4>';
                            html += '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard(\'' + (guideData.number || '') + '\', this)"><i class="fas fa-copy"></i></button>';
                            html += '</div>';
                            html += '<p class="mb-0 text-muted mt-2">a.n. <strong>' + (guideData.name || '-') + '</strong></p>';
                            html += '</div>';
                        } else {
                            html += '<p class="mb-3">' + (guideData || 'Silakan lakukan pembayaran ke rekening terkait.') + '</p>';
                        }
                        
                        html += '<div class="d-flex align-items-center justify-content-center gap-2 my-3">';
                        html += '<h3 class="fw-bold text-danger mb-0">Rp' + totalFmt + '</h3>';
                        html += '<button type="button" class="btn btn-sm btn-outline-danger" onclick="copyToClipboard(\'' + total + '\', this)"><i class="fas fa-copy"></i></button>';
                        html += '</div>';
                        html += '<div class="alert alert-warning small mb-0"><i class="fas fa-exclamation-triangle"></i> Pastikan nominal transfer sama dan tepat hingga digit terakhir</div>';
                        html += '</div>';

                        $('#statusModalBody').html(html);
                        new bootstrap.Modal(document.getElementById('statusModal')).show();
                    } else {
                        $('#statusModalBody').html('Status: <b>' + response.status + '</b><br>' + (response.msg || ''));
                        new bootstrap.Modal(document.getElementById('statusModal')).show();
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text(originalText);
                    $('#statusModalBody').text('Gagal memeriksa status.');
                    new bootstrap.Modal(document.getElementById('statusModal')).show();
                }
            });
        }
    });

    // Cancel Payment Handler
    var cancelPaymentRef = '';
    $('.cancelPayment').on('click', function(e) {
        e.preventDefault();
        var ref = $(this).data('ref');
        var note = $(this).data('note');
        var total = $(this).data('total');
        
        cancelPaymentRef = ref;
        $('#cancelPaymentInfo').html('<strong>' + note + '</strong> - Rp' + total);
        
        var modal = new bootstrap.Modal(document.getElementById('modalCancelPayment'));
        modal.show();
    });

    $('#btnConfirmCancel').on('click', function() {
        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        var cancelUrl = '<?= URL::BASE_URL ?>I/cancel_payment/' + cancelPaymentRef;
        console.log('Cancelling payment:', cancelUrl);
        
        $.ajax({
            url: cancelUrl,
            type: 'GET',
            dataType: 'JSON',
            success: function(response) {
                console.log('Cancel response:', response);
                btn.prop('disabled', false).html(originalHtml);
                bootstrap.Modal.getInstance(document.getElementById('modalCancelPayment')).hide();
                
                if (response.status === 'success') {
                    console.log('Payment cancelled successfully');
                    location.reload();
                } else {
                    console.log('Cancel failed:', response.msg);
                }
            },
            error: function(xhr, status, error) {
                console.log('Cancel error:', status, error);
                console.log('Response text:', xhr.responseText);
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
</script>
</content>