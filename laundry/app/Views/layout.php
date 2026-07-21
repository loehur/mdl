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
    <?php require_once __DIR__ . '/pwa_head.php'; ?>
    <title><?= !empty($this->isTrainingMode) ? '[TRAINING] ' : '' ?><?= $title ?> | MDL</title>
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

        /* Sidebar: scroll only the menu nav so long dropdowns stay reachable */
        .main-sidebar {
            height: 100vh;
            max-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .main-sidebar .sidebar {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }

        .main-sidebar .sidebar > *:not(nav) {
            flex-shrink: 0;
        }

        .main-sidebar .sidebar > nav {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }

        /* ===== Top toolbar: tinggi seragam ===== */
        .main-header.mdl-topbar {
            display: block;
            padding: 8px 10px !important;
            background: #eceff3 !important;
            background-image: none !important;
            border-bottom: 1px solid #d5dbe3;
            min-height: 0;
        }
        .mdl-topbar-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            margin: 0;
            min-height: 34px;
        }
        .mdl-topbar-row .mdl-spacer {
            flex: 1 1 auto;
            min-width: 4px;
        }

        .mdl-tbtn,
        .mdl-topbar select.mdl-tctrl,
        .mode-switch {
            box-sizing: border-box;
            height: 34px;
            border-radius: 8px;
            font-family: 'fontku', sans-serif;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
            outline: none;
            -webkit-appearance: none;
            appearance: none;
        }

        .mdl-tbtn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 12px;
            border: 1.5px solid #c5ccd6;
            background: #fff;
            color: #2a3340;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        .mdl-tbtn:hover {
            background: #f4f6f9;
            border-color: #9aa6b5;
            color: #1a222c;
            text-decoration: none;
        }
        .mdl-tbtn:active {
            transform: translateY(1px);
        }
        .mdl-tbtn--menu {
            padding: 0 12px 0 10px;
        }
        .mdl-tbtn--icon {
            width: 34px;
            padding: 0;
            flex: 0 0 34px;
        }
        .mdl-tbtn--refresh {
            border-color: #7cbc8f;
            color: #1f7a3d;
            background: #f3faf5;
        }
        .mdl-tbtn--refresh:hover {
            background: #e4f5ea;
            border-color: #4fa86a;
            color: #166530;
        }
        .mdl-tbtn--logout {
            border-color: #b8c0ca;
            color: #3a4450;
        }

        .mdl-topbar select.mdl-tctrl {
            padding: 0 28px 0 10px;
            border: 1.5px solid transparent;
            color: #fff;
            cursor: pointer;
            max-width: 88px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23ffffff' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 8px 5px;
        }
        .mdl-topbar select.mdl-tctrl:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        .mdl-tctrl--cabang {
            background-color: #2563eb;
        }
        .mdl-tctrl--user {
            background-color: #16a34a;
            max-width: 120px;
        }

        .mode-switch {
            display: inline-flex;
            align-items: stretch;
            overflow: hidden;
            padding: 3px;
            gap: 2px;
            border: 1.5px solid #c5ccd6;
            background: #dfe5ec;
            box-shadow: inset 0 1px 2px rgba(0,0,0,.06);
        }
        .mode-switch .mode-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-width: 64px;
            padding: 0 12px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #5a6573;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.02em;
            cursor: pointer;
            user-select: none;
            transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        }
        .mode-switch .mode-btn:hover:not(.active-live):not(.active-training) {
            color: #2a3340;
            background: rgba(255,255,255,.45);
        }
        .mode-switch .mode-btn.active-live {
            background: #16a34a;
            color: #fff;
            box-shadow: 0 1px 2px rgba(22, 163, 74, 0.35);
        }
        .mode-switch .mode-btn.active-training {
            background: #ea580c;
            color: #fff;
            box-shadow: 0 1px 2px rgba(234, 88, 12, 0.35);
        }

        .mdl-training-chip {
            display: inline-flex;
            align-items: center;
            height: 34px;
            padding: 0 12px;
            border-radius: 8px;
            background: #1c1917;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .training-banner {
            background: linear-gradient(90deg, #ea580c, #f59e0b);
            color: #1a1a1a;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.02em;
            text-align: center;
            padding: 7px 12px;
            border-bottom: 1px solid rgba(0,0,0,.08);
        }
        body.mode-training .main-header.mdl-topbar {
            background: #ffedd5 !important;
            border-bottom-color: #fdba74;
        }
        body.mode-training .main-sidebar {
            background: #5c3200 !important;
        }
        body.mode-training .mode-switch {
            border-color: #c2410c;
            background: #fed7aa;
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

<?php $isTrainingUi = !empty($this->isTrainingMode); ?>
<body class="hold-transition sidebar-mini<?= $isTrainingUi ? ' mode-training' : '' ?>">
    <div class="loaderDiv" style="display: none;">
        <div class="loader"></div>
    </div>
    <div class="wrapper">
        <?php if ($isTrainingUi) { ?>
            <div class="training-banner sticky-top">
                MODE TRAINING — data latihan terpisah · WA &amp; payment tetap sungguhan · switch ke Live untuk operasional
            </div>
        <?php } ?>
        <nav class="main-header navbar navbar-expand mdl-topbar sticky-top">
            <div class="mdl-topbar-row">
                <a href="#" id="menu_utama" class="mdl-tbtn mdl-tbtn--menu" data-widget="pushmenu" role="button">
                    <i class="fas fa-bars"></i><span>Menu</span>
                </a>

                <div class="mode-switch" id="modeSwitch" title="Ganti Mode Live / Training">
                    <button type="button" class="mode-btn<?= !$isTrainingUi ? ' active-live' : '' ?>" data-mode="live">Live</button>
                    <button type="button" class="mode-btn<?= $isTrainingUi ? ' active-training' : '' ?>" data-mode="training">Training</button>
                </div>

                <?php if (!$isTrainingUi && ($this->id_privilege == 100 or $this->id_privilege == 12)) { ?>
                    <select id="selectCabang" class="mdl-tctrl mdl-tctrl--cabang" title="Pilih cabang">
                        <?php foreach ($this->listCabang as $lcb) { ?>
                            <option value="<?= $lcb['id_cabang'] ?>" <?= ($this->id_cabang == $lcb['id_cabang']) ? "selected" : '' ?>><?= $lcb['kode_cabang'] ?></option>
                        <?php } ?>
                    </select>
                    <?php if ($this->id_privilege == 100) { ?>
                        <select id="userLog" class="mdl-tctrl mdl-tctrl--user" title="Login sebagai user">
                            <option value="">——</option>
                            <?php foreach ($this->user as $a) {
                                if ($a['id_user'] <> $_SESSION[URL::SESSID]['user']['id_user']) { ?>
                                    <option value="<?= $a['id_user'] ?>"><?= strtoupper($a['nama_user']) ?></option>
                            <?php }
                            } ?>
                        </select>
                    <?php } ?>
                <?php } elseif ($isTrainingUi) { ?>
                    <span class="mdl-training-chip">TRAINING</span>
                <?php } ?>

                <div class="mdl-spacer"></div>

                <a class="mdl-tbtn mdl-tbtn--icon mdl-tbtn--refresh refresh" href="#" title="Refresh data">
                    <i class="fas fa-sync"></i>
                </a>
                <a class="mdl-tbtn mdl-tbtn--icon mdl-tbtn--logout" href="<?= URL::BASE_URL ?>Login/logout" role="button" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <aside class="main-sidebar sidebar-dark-yellow shadow-sm position-fixed">
            <div class="sidebar px-0">
                <div class="row mx-0 py-2">
                    <div class="col py-2 text-center rounded-3">
                        <table class="text-secondary w-100">
                            <tr>
                                <td class="w-25 text-end pe-2"><i class="fas fa-user-circle"></i></td>
                                <td class="w-50 text-start ps-2"><?= $this->nama_user . " #" . ($isTrainingUi ? 'TRAINING' : $this->id_cabang) ?></td>
                            </tr>
                            <tr>
                                <td class="w-25 text-end pe-2"><i class="fas fa-wifi"></i></td>
                                <td class="w-50 text-start ps-2"><?= $_SESSION[URL::SESSID]['data']['cabang']['wifi_pass'] ?? '' ?></td>
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
                <nav class="ps-2 pb-5">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                        <?php foreach ($menu as $key => $m) { ?>
                            <ul id="nav_<?= $key ?>" class="nav nav-pills nav-sidebar flex-column <?= $key == 0 ? $hideKasir : $hideAdmin ?>">
                                <?php foreach ($m as $mk) { ?>
                                    <?php 
                                    // Skip menu if show_if_multi_cabang is true but only 1 cabang exists
                                    if (isset($mk['show_if_multi_cabang']) && $mk['show_if_multi_cabang'] && count($this->listCabang) <= 1) {
                                        continue;
                                    }
                                    // Hide Operan (dan menu sejenis) saat Mode Training
                                    if (!empty($mk['hide_if_training']) && !empty($this->isTrainingMode)) {
                                        continue;
                                    }
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
                (function() {
                    var SWIPE_THRESHOLD = 50;
                    var MOBILE_BREAKPOINT = 992;
                    var startX = null;
                    var startY = null;

                    function ensureSidebarOverlay() {
                        if (document.getElementById('sidebar-overlay')) {
                            return;
                        }
                        var wrapper = document.querySelector('.wrapper');
                        if (!wrapper) {
                            return;
                        }
                        var overlay = document.createElement('div');
                        overlay.id = 'sidebar-overlay';
                        overlay.addEventListener('click', closeSidebarMenu);
                        wrapper.appendChild(overlay);
                    }

                    function closeOffcanvasPanels() {
                        var hadOpen = false;
                        document.querySelectorAll('.offcanvas.show').forEach(function(el) {
                            hadOpen = true;
                            if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
                                var inst = bootstrap.Offcanvas.getInstance(el) || bootstrap.Offcanvas.getOrCreateInstance(el);
                                inst.hide();
                            } else {
                                el.classList.remove('show');
                            }
                        });
                        return hadOpen;
                    }

                    function isMobileSidebar() {
                        return window.innerWidth <= MOBILE_BREAKPOINT;
                    }

                    function isSidebarMenuVisible() {
                        var body = document.body;
                        if (isMobileSidebar()) {
                            return body.classList.contains('sidebar-open');
                        }
                        return !body.classList.contains('sidebar-collapse');
                    }

                    function openSidebarMenu() {
                        if (isSidebarMenuVisible()) {
                            return;
                        }

                        var body = document.body;

                        if (isMobileSidebar()) {
                            ensureSidebarOverlay();
                            body.classList.add('sidebar-is-opening', 'sidebar-open');
                            body.classList.remove('sidebar-collapse', 'sidebar-closed');
                            window.setTimeout(function() {
                                body.classList.remove('sidebar-is-opening');
                            }, 50);
                            return;
                        }

                        body.classList.remove('sidebar-collapse', 'sidebar-closed');
                    }

                    function closeSidebarMenu() {
                        if (!isSidebarMenuVisible()) {
                            return;
                        }

                        var body = document.body;

                        if (isMobileSidebar()) {
                            body.classList.remove('sidebar-open');
                            body.classList.add('sidebar-closed', 'sidebar-collapse');
                            return;
                        }

                        body.classList.add('sidebar-collapse');
                    }

                    document.addEventListener('touchstart', function(event) {
                        if (!event.touches || !event.touches.length) {
                            return;
                        }
                        startX = event.touches[0].clientX;
                        startY = event.touches[0].clientY;
                    }, { passive: true });

                    document.addEventListener('touchend', function(event) {
                        if (startX === null || startY === null) {
                            return;
                        }
                        if (!event.changedTouches || !event.changedTouches.length) {
                            startX = null;
                            startY = null;
                            return;
                        }

                        var endX = event.changedTouches[0].clientX;
                        var endY = event.changedTouches[0].clientY;
                        var distX = endX - startX;
                        var distY = endY - startY;

                        if (Math.abs(distX) > SWIPE_THRESHOLD || Math.abs(distY) > SWIPE_THRESHOLD) {
                            if (Math.abs(distX) > Math.abs(distY)) {
                                if (distX > 0) {
                                    if (!closeOffcanvasPanels()) {
                                        openSidebarMenu();
                                    }
                                } else {
                                    closeSidebarMenu();
                                }
                            }
                        }

                        startX = null;
                        startY = null;
                    }, { passive: true });
                })();

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

                $("#modeSwitch .mode-btn").on("click", function() {
                    var mode = $(this).data("mode");
                    var current = <?= $isTrainingUi ? "'training'" : "'live'" ?>;
                    if (mode === current) {
                        return;
                    }
                    $.ajax({
                        url: '<?= URL::BASE_URL ?>Training/switchMode',
                        data: { mode: mode },
                        type: "POST",
                        dataType: "json",
                        beforeSend: function() {
                            $(".loaderDiv").fadeIn("fast");
                        },
                        success: function(res) {
                            if (res && res.code == 1) {
                                location.reload(true);
                            } else {
                                $(".loaderDiv").fadeOut("fast");
                                alert((res && res.msg) ? res.msg : "Gagal ganti mode");
                            }
                        },
                        error: function(xhr) {
                            $(".loaderDiv").fadeOut("fast");
                            alert("Gagal ganti mode: " + (xhr.responseText || xhr.status));
                        }
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
<?php require_once __DIR__ . '/pwa_register.php'; ?>