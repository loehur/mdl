<?php
if (isset($data['data_operasi'])) {
    $title = $data['data_operasi']['title'];
} else {
    $title = "";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= URL::IN_ASSETS ?>icon/logo.png">
    <title><?= $title ?> | MDL</title>
    <meta name="viewport" content="width=460, user-scalable=no">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>css/ionicons.min.css">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/adminLTE-3.1.0/css/adminlte.min.css">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>css/selectize.bootstrap3.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>css/jquery-ui.css" rel="stylesheet" />

    <style>
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

        @media print {
            p div {
                font-family: 'fontku', sans-serif;
                font-size: 14px;
            }
        }

        .modal-backdrop {
            opacity: 0.1 !important;
        }

        .main-sidebar {
            height: 100vh;
            overflow-y: auto;
        }
    </style>
</head>

<?php

require_once('menu_kasir.php');
require_once('menu_admin.php');

$hideAdmin = "";
$hideKasir = "";
$classAdmin = "btn-danger";
$classKasir = "btn-success";

if ($this->id_privilege == 100) {
    $hideAdmin = "d-none";
} else {
    $hideAdmin = "";
}

if (isset($_SESSION['log_mode'])) {
    $log_mode = $_SESSION['log_mode'];
} else {
    $log_mode = 0;
}
if ($log_mode == 1) {
    $hideAdmin = "";
    $hideKasir = "d-none";
    $classKasir = "btn-secondary";
} else {
    $hideAdmin = "d-none";
    $hideKasir = "";
    $classAdmin = "btn-secondary";
}

?>

<body class="hold-transition sidebar-mini">
    <div class="loaderDiv" style="display: none;">
        <div class="loader"></div>
    </div>
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand bg-secondary-subtle bg-gradient sticky-top pb-1 pt-3">
            <div class="row w-100 mx-0 px-0 pb-1">
                <div class="col-auto ps-0 pe-1 text-nowrap">
                    <a class="nav-link p-0 ps-2" id="menu_utama" data-widget="pushmenu" href="#" role="button"> <span class="btn ><i class=" fas fa-bars"></i> Menu</span></a>
                </div>

                <?php if ($this->id_privilege == 100 or $this->id_privilege == 12) { ?>
                    <div class="col-auto ps-0 pe-1">
                        <select id="selectCabang" class="form-control bg-primary">
                            <?php foreach ($this->listCabang as $lcb) { ?>
                                <option class="font-weight-bold" value="<?= $lcb['id_cabang'] ?>" <?= ($this->id_cabang == $lcb['id_cabang']) ? "selected" : '' ?>><?= $lcb['kode_cabang'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-auto ps-0 pe-1">
                        <?php if ($this->id_privilege == 100) { ?>
                            <select id="userLog" class="form-control bg-success">
                                <option>------</option>
                                <?php foreach ($this->user as $a) {
                                    if ($a['id_user'] <> $_SESSION[URL::SESSID]['user']['id_user']) { ?>
                                        <option value="<?= $a['id_user'] ?>"><?= strtoupper($a['nama_user']) ?></option>
                                <?php }
                                } ?>
                            </select>
                        <?php } ?>
                    </div>

                <?php } ?>
                <div class="col-auto ps-0 me-auto pe-1">
                    <select id="selectBook" class="form-control bg-info">
                        <?php for ($y = URL::FIRST_YEAR; $y <= date('Y'); $y++) { ?>
                            <option class="font-weight-bold" value="<?= $y ?>" <?= ($_SESSION[URL::SESSID]['user']['book'] == $y) ? "selected" : '' ?>><?= $y ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-auto ps-0 pe-1">
                    <a class="refresh" href="#">
                        <span class="btn btn-outline-success"><i class="fas fa-sync"></i></span>
                    </a>
                </div>
                <div class="col-auto ps-0 pe-1">
                    <a class="" href="<?= URL::BASE_URL ?>Login/logout" role="button">
                        <span class="btn btn-outline-dark"><i class="fas fa-sign-out-alt"></i></span>
                    </a>
                </div>
            </div>
        </nav>

        <aside class="main-sidebar sidebar-dark-yellow shadow-sm position-fixed">
            <div class="sidebar px-0">
                <div class="row mx-0 py-2">
                    <div class="col py-2 text-center rounded-3">
                        <table class="text-secondary w-100">
                            <tr>
                                <td class="w-25 text-end pe-2"><i class="fas fa-user-circle"></i></td>
                                <td class="w-50 text-start ps-2"><?= $this->nama_user . " #" . $this->id_cabang ?></td>
                            </tr>
                            <tr>
                                <td class="w-25 text-end pe-2"><i class="fas fa-wifi"></i></td>
                                <td class="w-50 text-start ps-2"><?= $_SESSION[URL::SESSID]['data']['cabang']['wifi_pass'] ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if ($this->id_privilege == 100) { ?>
                    <div class="row mx-0 user-panel mb-2 pb-2 pt-1">
                        <div class="col text-end mb-1">
                            <span id="btnKasir" style="width: 42px;" class="btn <?= $classKasir ?> px-2"><i class="fas fa-cash-register"></i></span>
                        </div>
                        <div class="col text-start">
                            <span id="btnAdmin" style="width: 42px;" class="btn <?= $classAdmin ?> px-2"><i class="fas fa-user-shield"></i></span>
                        </div>
                    </div>
                <?php } ?>

                <!-- MENU KASIR --------------------------------->
                <nav class="ps-2 overflow-auto pb-5">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                        <?php foreach ($menu as $key => $m) { ?>
                            <ul id="nav_<?= $key ?>" class="nav nav-pills nav-sidebar flex-column <?= $key == 0 ? $hideKasir : $hideAdmin ?>">
                                <?php foreach ($m as $mk) { ?>
                                    <?php if (!isset($mk['submenu'])) { ?>
                                        <li class="nav-item ">
                                            <a href="<?= URL::BASE_URL . $mk['c'] ?>" class="nav-link <?= (strpos($title, $mk['title']) !== FALSE) ? 'active' : '' ?>">
                                                <i class="nav-icon <?= $mk['icon'] ?>"></i>
                                                <p>
                                                    <?= $mk['txt'] ?>
                                                </p>
                                            </a>
                                        </li>
                                    <?php } else { 
                                        // Check if any submenu is active
                                        $hasActiveSubmenu = false;
                                        foreach ($mk['submenu'] as $ms) {
                                            if ($title == $ms['title']) {
                                                $hasActiveSubmenu = true;
                                                break;
                                            }
                                        }
                                        $isParentActive = (strpos($title, $mk['title']) !== FALSE) || $hasActiveSubmenu;
                                    ?>
                                        <li class="nav-item <?= $isParentActive ? 'menu-is-opening menu-open' : '' ?>">
                                            <a href="#" class="nav-link <?= $isParentActive ? 'active' : '' ?>">
                                                <i class="nav-icon <?= $mk['icon'] ?>"></i>
                                                <p>
                                                    <?= $mk['txt'] ?>
                                                    <i class="fas fa-angle-left right"></i>
                                                </p>
                                            </a>
                                            <ul class="nav nav-treeview" style="display: <?= $isParentActive ? 'block' : 'none;'; ?>;">
                                                <?php foreach ($mk['submenu'] as $ms) { ?>
                                                    <li class="nav-item">
                                                        <?php 
                                                        // Check if submenu path is absolute (starts with @)
                                                        $subPath = $ms['c'];
                                                        if (substr($subPath, 0, 1) === '@') {
                                                            $fullPath = ltrim($subPath, '@');
                                                        } else {
                                                            $fullPath = $mk['c'] . $subPath;
                                                        }
                                                        ?>
                                                        <a href="<?= URL::BASE_URL . $fullPath ?>" class="nav-link <?= ($title == $ms['title']) ? 'active' : '' ?>">
                                                            <i class="far fa-circle nav-icon"></i>
                                                            <p>
                                                                <b> <?= $ms['txt'] ?></b>
                                                            </p>
                                                        </a>
                                                    </li>
                                                <?php } ?>
                                            </ul>
                                        </li>
                                    <?php } ?>
                                <?php } ?>
                            </ul>
                        <?php } ?>

                        <ul id="nav_2" class="nav nav-pills nav-sidebar flex-column <?= $hideKasir ?> ?>">
                            <?php if (count($this->listCabang) > 1) { ?>
                                <li class="nav-item ">
                                    <a href="<?= URL::BASE_URL ?>Operan" class="nav-link 
                  <?php if (strpos($title, 'Operan') !== FALSE) : echo 'active';
                                endif ?>">
                                        <i class="nav-icon fas fa-random"></i>
                                        <p>
                                            Operan
                                        </p>
                                    </a>
                                </li>
                            <?php } ?>

                            <li class="nav-item ">
                                <a href="<?= URL::BASE_URL ?>Kas" class="nav-link 
                <?php if (strpos($title, 'Kas') !== FALSE) : echo 'active';
                endif ?>">
                                    <i class="nav-icon fas fa-wallet"></i>
                                    <p>
                                        Kas
                                    </p>
                                </a>
                            </li>
                            <li class="nav-item ">
                                <a href="<?= URL::BASE_URL ?>PackLabel" class="nav-link 
                <?php if (strpos($title, 'PackLabel') !== FALSE) : echo 'active';
                endif ?>">
                                    <i class="nav-icon fas fa-tag"></i>
                                    <p>
                                        Pack Label
                                    </p>
                                </a>
                            </li>
                            <li class="nav-item ">
                                <a href="<?= URL::BASE_URL ?>Prepaid" class="nav-link 
                <?php if (strpos($title, 'Pre/Post Paid') !== FALSE) : echo 'active';
                endif ?>">
                                    <i class="nav-icon far fa-credit-card"></i>
                                    <p>
                                        Pre/Post Paid
                                    </p>
                                </a>
                            </li>
                        </ul>

                        <!-- INI MENU ADMIN ----------------------------------------->
                        <?php if ($this->id_privilege == 100) { ?>
                            <ul id="nav_3" class="nav nav-pills nav-sidebar flex-column <?= $hideAdmin ?>">






                                <li class="nav-item 
                <?php if (strpos($title, 'Produk') !== FALSE) {
                                echo 'menu-is-opening menu-open';
                            } ?>">
                                    <a href="#" class="nav-link 
                <?php if (strpos($title, 'Produk') !== FALSE) {
                                echo 'active';
                            } ?>">
                                        <i class="nav-icon fas fa-layer-group"></i>
                                        <p>
                                            Produk
                                            <i class="fas fa-angle-left right"></i>
                                        </p>
                                    </a>
                                    <ul class="nav nav-treeview" style="display: 
                <?php if (strpos($title, 'Produk') !== FALSE) {
                                echo 'block;';
                            } else {
                                echo 'none;';
                            } ?>;">
                                        <?php foreach ($this->dPenjualan as $a) {
                                            if ($a['id_penjualan_jenis'] < 5) { ?>
                                                <li class="nav-item">
                                                    <a href="<?= URL::BASE_URL ?>SetGroup/i/<?= $a['id_penjualan_jenis'] ?>" class="nav-link 
                    <?php if ($title == 'Produk ' . $a['penjualan_jenis']) {
                                                    echo 'active';
                                                } ?>">
                                                        <i class="far fa-circle nav-icon"></i>
                                                        <p>
                                                            <?= $a['penjualan_jenis'] ?>
                                                        </p>
                                                    </a>
                                                </li>
                                        <?php }
                                        } ?>
                                    </ul>
                                </li>

                                <li class="nav-item 
                <?php if (strpos($title, 'Harga') !== FALSE) {
                                echo 'menu-is-opening menu-open';
                            } ?>">
                                    <a href="#" class="nav-link 
                <?php if (strpos($title, 'Harga') !== FALSE) {
                                echo 'active';
                            } ?>">
                                        <i class="nav-icon fas fa-tags"></i>
                                        <p>
                                            Harga
                                            <i class="fas fa-angle-left right"></i>
                                        </p>
                                    </a>
                                    <ul class="nav nav-treeview" style="display: 
                <?php if (strpos($title, 'Harga') !== FALSE) {
                                echo 'block;';
                            } else {
                                echo 'none;';
                            } ?>;">
                                        <?php foreach ($this->dPenjualan as $a) {
                                            if ($a['id_penjualan_jenis'] < 5) { ?>
                                                <li class="nav-item">
                                                    <a href="<?= URL::BASE_URL ?>SetHarga/i/<?= $a['id_penjualan_jenis'] ?>" class="nav-link 
                    <?php if ($title == 'Harga ' . $a['penjualan_jenis']) {
                                                    echo 'active';
                                                } ?>">
                                                        <i class="far fa-circle nav-icon"></i>
                                                        <p>
                                                            <?= $a['penjualan_jenis'] ?>
                                                        </p>
                                                    </a>
                                                </li>
                                        <?php }
                                        } ?>
                                        <li class="nav-item">
                                            <a href="<?= URL::BASE_URL ?>SetHargaPaket" class="nav-link 
                    <?php if ($title == 'Harga Paket') {
                                echo 'active';
                            } ?>">
                                                <i class="far fa-circle nav-icon"></i>
                                                <p>
                                                    Paket Member
                                                </p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="<?= URL::BASE_URL ?>SetDiskon/i" class="nav-link <?= ($title == 'Harga Diskon Kuantitas') ? 'active' : '' ?>">
                                                <i class="far fa-circle nav-icon"></i>
                                                <p>
                                                    Diskon Kuantitas
                                                </p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="<?= URL::BASE_URL ?>SetDiskon_Khusus/i" class="nav-link <?= ($title == 'Harga Diskon Khusus') ? 'active' : '' ?>">
                                                <i class="far fa-circle nav-icon"></i>
                                                <p>
                                                    Diskon Khusus
                                                </p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                <?php
                                // JIKA SUDAH PUNYA CABANG
                                if ($this->id_cabang > 0) { ?>
                                    <li class="nav-item 
                <?php if (strpos($title, 'Karyawan') !== FALSE) {
                                        echo 'menu-is-opening menu-open';
                                    } ?>">
                                        <a href="#" class="nav-link 
                <?php if (strpos($title, 'Karyawan') !== FALSE) {
                                        echo 'active';
                                    } ?>">
                                            <i class="nav-icon fas fa-user-friends"></i>
                                            <p>
                                                Karyawan
                                                <i class="fas fa-angle-left right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview" style="display: 
                <?php if (strpos($title, 'Karyawan') !== FALSE) {
                                        echo 'block;';
                                    } else {
                                        echo 'none;';
                                    } ?>;">
                                            <li class="nav-item">
                                                <a href="<?= URL::BASE_URL ?>Data_List/i/user" class="nav-link 
                    <?php if ($title == 'Karyawan Aktif') {
                                        echo 'active';
                                    } ?>">
                                                    <i class="far fa-circle nav-icon"></i>
                                                    <p>
                                                        Aktif
                                                    </p>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="<?= URL::BASE_URL ?>Data_List/i/userDisable" class="nav-link 
                    <?php if ($title == 'Karyawan Non Aktif') {
                                        echo 'active';
                                    } ?>">
                                                    <i class="far fa-circle nav-icon"></i>
                                                    <p>
                                                        Non Aktif
                                                    </p>
                                                </a>
                                            </li>
                                        </ul>
                                    </li>

                                    <li class="nav-item 
                <?php if (strpos($title, 'Broadcast') !== FALSE) {
                                        echo 'menu-is-opening menu-open';
                                    } ?>">
                                        <a href="#" class="nav-link 
                <?php if (strpos($title, 'Broadcast') !== FALSE) {
                                        echo 'active';
                                    } ?>">
                                            <i class="nav-icon fas fa-bullhorn"></i>
                                            <p>
                                                Broadcast
                                                <i class="fas fa-angle-left right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview" style="display: 
                <?php if (strpos($title, 'Broadcast') !== FALSE) {
                                        echo 'block;';
                                    } else {
                                        echo 'none;';
                                    } ?>;">
                                            <li class="nav-item">
                                                <a href="<?= URL::BASE_URL ?>Broadcast/i/1" class="nav-link 
                    <?php if ($title == 'Broadcast PDP') {
                                        echo 'active';
                                    } ?>">
                                                    <i class="far fa-circle nav-icon"></i>
                                                    <p>
                                                        Pelanggan Dalam Proses
                                                    </p>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="<?= URL::BASE_URL ?>Broadcast/i/2" class="nav-link 
                    <?php if ($title == 'Broadcast PNP') {
                                        echo 'active';
                                    } ?>">
                                                    <i class="far fa-circle nav-icon"></i>
                                                    <p>
                                                        Pelanggan Non Proses
                                                    </p>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="<?= URL::BASE_URL ?>Broadcast/i/3" class="nav-link 
                    <?php if ($title == 'Broadcast Semua Pelanggan') {
                                        echo 'active';
                                    } ?>">
                                                    <i class="far fa-circle nav-icon"></i>
                                                    <p>
                                                        Pelanggan Semua
                                                    </p>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="<?= URL::BASE_URL ?>Broadcast/i/4" class="nav-link 
                    <?php if ($title == 'Broadcast List') {
                                        echo 'active';
                                    } ?>">
                                                    <i class="far fa-circle nav-icon"></i>
                                                    <p>
                                                        Broadcast List
                                                    </p>
                                                </a>
                                            </li>
                                        </ul>
                                    </li>


                            </ul>
                    <?php
                                }
                            } ?>
                    </ul>
                </nav>
            </div>
        </aside>

        <span data-bs-dismiss="modal"></span>
        <div class="content-wrapper px-2 pt-2" style="min-width: 400px;max-width: 100vw;">
            <script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
            <script src="<?= URL::EX_ASSETS ?>plugins/adminLTE-3.1.0/js/adminlte.js"></script>
            <script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

            <div id="content"></div>
            <script>
                let startX, startY;
                const threshold = 50; // Minimum swipe distance

                document.addEventListener('touchstart', (event) => {
                    startX = event.touches[0].clientX;
                    startY = event.touches[0].clientY;
                });

                document.addEventListener('touchend', (event) => {

                    if (!startX || !startY) {
                        return;
                    }
                    const endX = event.changedTouches[0].clientX;
                    const endY = event.changedTouches[0].clientY;

                    const distX = endX - startX;
                    const distY = endY - startY;

                    if (Math.abs(distX) > threshold || Math.abs(distY) > threshold) {
                        if (Math.abs(distX) > Math.abs(distY)) {
                            if (distX > 0) {
                                function buka_menu(boleh) {
                                    if (boleh == true) {
                                        $('.sidebar-closed').each(function() {
                                            $("#menu_utama").click();
                                        });
                                    }
                                }

                                function adaCanvas(boleh, callback) {
                                    boleh = true;
                                    $('.offcanvas.show').each(function() {
                                        $(this).offcanvas('hide');
                                        boleh = false;
                                    });

                                    callback(boleh);
                                }
                                adaCanvas(true, buka_menu);
                            } else {
                                $('.sidebar-open').each(function() {
                                    $("#menu_utama").click();
                                });
                            }
                        } else {
                            if (distY > 0) {} else {}
                        }
                    }

                    startX = null;
                    startY = null;
                });

                $("a.refresh").on('click', function() {
                    $.ajax('<?= URL::BASE_URL ?>Data_List/synchrone', {
                        beforeSend: function() {
                            $(".loaderDiv").fadeIn("fast");
                        },
                        success: function(data, status, xhr) {
                            location.reload(true);
                        }
                    });
                });

                $("span#btnKasir").click(function() {
                    $.ajax({
                        url: "<?= URL::BASE_URL ?>Login/log_mode",
                        data: {
                            mode: 0
                        },
                        type: "POST",
                        dataType: 'html',
                        success: function(res) {
                            $("#nav_0").removeClass('d-none');
                            $("#nav_2").removeClass('d-none');
                            $("#nav_1").addClass('d-none');
                            $("#nav_3").addClass('d-none');

                            $("span#btnKasir").removeClass("btn-secondary").addClass("btn-success");
                            $("span#btnAdmin").removeClass("btn-danger").addClass("btn-secondary");
                        },
                    });
                });

                $("span#btnAdmin").click(function() {
                    $.ajax({
                        url: '<?= URL::BASE_URL ?>Login/log_mode',
                        data: {
                            mode: 1
                        },
                        type: "POST",
                        dataType: 'html',
                        success: function(response) {
                            $("#nav_0").addClass('d-none');
                            $("#nav_2").addClass('d-none');
                            $("#nav_1").removeClass('d-none');
                            $("#nav_3").removeClass('d-none');

                            $("span#btnKasir").removeClass("btn-success").addClass("btn-secondary");
                            $("span#btnAdmin").removeClass("btn-secondary").addClass("btn-danger");
                        },
                    });
                })

                $("select#selectCabang").on("change", function() {
                    var idCabang = $(this).val();
                    $.ajax({
                        url: '<?= URL::BASE_URL ?>Cabang_List/selectCabang',
                        data: {
                            id: idCabang
                        },
                        beforeSend: function() {
                            $(".loaderDiv").fadeIn("fast");
                        },
                        type: "POST",
                        success: function(response) {
                            location.reload(true);
                        },
                    });
                });

                $("select#selectBook").on("change", function() {
                    var id = $(this).val();
                    $.ajax({
                        url: '<?= URL::BASE_URL ?>Cabang_List/selectBook',
                        data: {
                            book: id
                        },
                        beforeSend: function() {
                            $(".loaderDiv").fadeIn("fast");
                        },
                        type: "POST",
                        success: function(res) {
                            if (res == 0) {
                                location.reload(true);
                            } else {
                                console.log(res);
                            }
                        },
                    });
                });

                $("select#userLog").on("change", function() {
                    var id_user = $(this).val();
                    $.ajax({
                        url: '<?= URL::BASE_URL ?>Login/switchUser',
                        data: {
                            id: id_user
                        },
                        beforeSend: function() {
                            $(".loaderDiv").fadeIn("fast");
                        },
                        type: "POST",
                        success: function(res) {
                            location.reload(true);
                        },
                    });
                });

                function hide_modal() {
                    $(".modal").each(function() {
                        $(this).modal('hide');
                    });
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
            </script>