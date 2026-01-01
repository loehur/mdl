<?php
if (isset($data)) {
    $title = $data['title'];
} else {
    $title = "";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= URL::ASSET_URL ?>icon/logo.png">
    <title><?= $title ?></title>
    <meta name="viewport" content="width=<?= URL::MIN_WIDTH ?>, user-scalable=no">
    <link rel="stylesheet" href="<?= URL::ASSETS_URL ?>plugins/fontawesome-free-5.15.4-web/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= URL::ASSETS_URL ?>plugins/fontawesome-pro-6.7.2-web/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= URL::ASSETS_URL ?>plugins/bootstrap-5.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= URL::ASSETS_URL ?>plugins/adminLTE-3.1.0/css/adminlte.min.css">
    <link rel="stylesheet" href="<?= URL::ASSETS_URL ?>css/jquery-ui.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= URL::ASSET_URL ?>css/style.css" rel="stylesheet" />

    <style>
        @font-face {
            font-family: "fontku";
            src: url("<?= URL::ASSETS_URL ?>font/Titillium-Regular.otf");
        }

        html .table {
            font-family: "fontku", sans-serif;
        }

        html .content {
            font-family: "fontku", sans-serif;
        }

        html body {
            font-family: "fontku", sans-serif;
        }

        @media print {
            p div {
                font-family: "fontku", sans-serif;
                font-size: 14px;
            }
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

if ($_SESSION['resto_user']['id_privilege'] == 100) {
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
        <nav class="main-header navbar navbar-expand navbar-light sticky-top pb-0 pt-2">
            <div class="row w-100 mx-0 px-0 pb-1">
                <div class="col-auto ps-0 pe-1 text-nowrap">
                    <a class="nav-link p-0" id="menu_utama" data-widget="pushmenu" href="#" role="button"> <span class="btn"><i class="fas fa-bars"></i></span></a>
                </div>
                <?php if ($this->id_privilege == 100 or $this->id_privilege == 12) { ?>
                    <div class="col-auto ps-0 pe-1">
                        <select id="selectCabang" class="form-control bg-primary">
                            <?php foreach ($_SESSION['resto_cabangs'] as $lcb) { ?>
                                <option class="font-weight-bold" value="<?= $lcb['id_cabang'] ?>" <?= ($this->id_cabang == $lcb['id_cabang']) ? "selected" : '' ?>><?= $lcb['kode_cabang'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-auto ps-0 pe-1">
                        <?php if ($this->id_privilege == 100) { ?>
                            <select id="userLog" class="form-control bg-success">
                                <option>------</option>
                                <?php foreach ($_SESSION['resto_users'] as $a) {
                                    if ($a['id_user'] <> $_SESSION['resto_user']['id_user']) { ?>
                                        <option value="<?= $a['id_user'] ?>"><?= strtoupper($a['nama_user']) ?></option>
                                <?php }
                                } ?>
                            </select>
                        <?php } ?>
                    </div>
                <?php } ?>

                <div class="col-auto ps-0 me-auto">
                    <select id="selectBook" class="form-control bg-info">
                        <?php for ($y = URL::Y_START; $y <= date('Y'); $y++) { ?>
                            <option class="font-weight-bold" value="<?= $y ?>" <?= ($_SESSION['resto_user']['book'] == $y) ? "selected" : '' ?>><?= $y ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-auto ps-0">
                    <a class="refresh" href="#">
                        <span class="btn btn-outline-success"><i class="fas fa-sync"></i></span>
                    </a>
                </div>
                <div class="col-auto ps-0">
                    <a class="" href="<?= URL::BASE_URL ?>Login/logout" role="button">
                        <span class="btn btn-outline-dark"><i class="fas fa-sign-out-alt"></i></span>
                    </a>
                </div>
            </div>
        </nav>

        <aside class="main-sidebar sidebar-light-green border-end position-fixed">
            <div class="sidebar">
                <div class="user-panel mt-2 pb-2 mb-2 d-flex">
                    <div class="info">
                        <table class="text-secondary">
                            <tr>
                                <td><i class="fas fa-user-circle"></i></td>
                                <td><?= $this->nama_user . " #" . $this->id_cabang ?>#<span><?= date('d/m') ?> &nbsp;&nbsp;<b class="text-light"><i class="far fa-clock"></i> <span id="jam"><?= date('H') ?></span>:<span id="menit"><?= date('i') ?></span></span></b>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-wifi"></i></td>
                                <td><?= $_SESSION['resto_cabangs'][$_SESSION['resto_user']['id_cabang']]['wifi_pass']; ?></td>
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
                <nav>
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                        <?php foreach ($menu as $key => $m) { ?>
                            <ul id="nav_<?= $key ?>" class="nav nav-pills nav-sidebar flex-column <?= $key == 0 ? $hideKasir : $hideAdmin ?>">
                                <?php foreach ($m as $mk) { ?>
                                    <?php
                                    if ($this->id_privilege < $mk['p'])
                                        continue;
                                    ?>
                                    <?php if (!isset($mk['submenu'])) { ?>
                                        <li class="nav-item ">
                                            <a href="<?= URL::BASE_URL . $mk['c'] ?>" class="nav-link <?= (strpos($title, $mk['title']) !== FALSE) ? 'active' : '' ?>">
                                                <i class="nav-icon <?= $mk['icon'] ?>"></i>
                                                <p>
                                                    <?= $mk['txt'] ?>
                                                </p>
                                            </a>
                                        </li>
                                    <?php } else { ?>
                                        <li class="nav-item <?= (strpos($title, $mk['title']) !== FALSE) ? 'menu-is-opening menu-open' : '' ?>">
                                            <a href="#" class="nav-link <?= (strpos($title, $mk['title']) !== FALSE) ? 'active' : '' ?>">
                                                <i class="nav-icon <?= $mk['icon'] ?>"></i>
                                                <p>
                                                    <?= $mk['txt'] ?>
                                                    <i class="fas fa-angle-left right"></i>
                                                </p>
                                            </a>
                                            <ul class="nav nav-treeview" style="display: <?= (strpos($title, $mk['title']) !== FALSE) ? 'block' : 'none;'; ?>;">
                                                <?php foreach ($mk['submenu'] as $ms) { ?>
                                                    <li class="nav-item">
                                                        <a href="<?= URL::BASE_URL . $mk['c'] . $ms['c'] ?>" class="nav-link <?= ($title == $ms['title']) ? 'active' : '' ?>">
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

                        <!-- INI MENU ADMIN ----------------------------------------->
                        <?php if ($this->id_privilege == 100) { ?>
                            <ul id="nav_3" class="nav nav-pills nav-sidebar flex-column <?= $hideAdmin ?>">
                                <li class="nav-item ">
                                    <a href="<?= URL::BASE_URL ?>Cabang_List" class="nav-link 
                  <?php if ($title == 'Data Cabang') : echo 'active';
                            endif ?>">
                                        <i class="nav-icon fas fa-store"></i>
                                        <p>
                                            Cabang
                                        </p>
                                    </a>
                                </li>

                                <li class="nav-item 
                <?php if (strpos($title, 'Item') !== FALSE) {
                                echo 'menu-is-opening menu-open';
                            } ?>">
                                    <a href="#" class="nav-link 
                <?php if (strpos($title, 'Item') !== FALSE) {
                                echo 'active';
                            } ?>">
                                        <i class="nav-icon fas fa-list"></i>
                                        <p>
                                            Item List
                                            <i class="fas fa-angle-left right"></i>
                                        </p>
                                    </a>
                                    <ul class="nav nav-treeview" style="display: 
                <?php if (strpos($title, 'Item') !== FALSE) {
                                echo 'block;';
                            } else {
                                echo 'none;';
                            } ?>;">
                                        <li class="nav-item">
                                            <a href="#" class="nav-link 
              <?php if ($title == 'Item Laundry') {
                                echo 'active';
                            } ?>">
                                                <i class="far fa-circle nav-icon"></i>
                                                <p>
                                                    Menu dan Kategori
                                                </p>
                                            </a>
                                        </li>

                                        <li class="nav-item">
                                            <a href="<?= URL::BASE_URL ?>Data_List/i/item_pengeluaran" class="nav-link 
              <?php if ($title == 'Item Pengeluaran') {
                                echo 'active';
                            } ?>">
                                                <i class="far fa-circle nav-icon"></i>
                                                <p>
                                                    Pengeluaran
                                                </p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                <li class="nav-item ">
                                    <a href="<?= URL::BASE_URL ?>Troubleshoot" class="nav-link 
                  <?php if ($title == 'Troubleshoot') : echo 'active';
                            endif ?>">
                                        <i class="nav-icon fas fa-hammer"></i>
                                        <p>
                                            Troubleshoot
                                        </p>
                                    </a>
                                </li>
                            </ul>
                        <?php
                        } ?>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="content-wrapper pt-2 px-2 bg-white" id="content" style="min-width: <?= URL::MIN_WIDTH ?>px;max-width: <?= URL::MAX_WIDTH ?>px;">
            <script src="<?= URL::ASSETS_URL ?>plugins/adminLTE-3.1.0/jquery/jquery.min.js"></script>
            <script src="<?= URL::ASSETS_URL ?>plugins/adminLTE-3.1.0/bootstrap/js/bootstrap.bundle.min.js"></script>
            <script src="<?= URL::ASSETS_URL ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
            <script src="<?= URL::ASSETS_URL ?>plugins/adminLTE-3.1.0/js/adminlte.js"></script>

            <script>
                function buka_canvas(id) {
                    let same = false;
                    $(".offcanvas.show").each(function() {
                        if ($(this).attr('id') == id) {
                            same = true;
                        }
                    })

                    if (same == false) {
                        $(".offcanvas").each(function() {
                            $(this).offcanvas('hide');
                        })

                        const canvasElemen = document.getElementById(id)
                        const canvas = new bootstrap.Offcanvas(canvasElemen)
                        canvas.show()
                    }
                }

                function tutup_canvas(id) {
                    $(".offcanvas.show").each(function() {
                        if ($(this).attr('id') == id) {
                            $(this).offcanvas('hide');
                        }
                    })
                }

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
                                            return false;
                                        });
                                    }
                                }

                                function adaCanvas(boleh, callback) {
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
                                    return false;
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
            </script>