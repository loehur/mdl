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
        .main-sidebar.sidebar-dark-yellow {
            background: #0f172a !important;
        }
        body.mode-training .main-sidebar {
            background: #431407 !important;
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
        .mdl-tbtn--cabang {
            min-width: 52px;
            padding: 0 12px;
            border: 1.5px solid transparent;
            background: #2563eb;
            color: #fff;
            gap: 6px;
        }
        .mdl-tbtn--cabang:hover {
            background: #1d4ed8;
            border-color: transparent;
            color: #fff;
        }
        .mdl-tbtn--cabang i {
            font-size: 10px;
            opacity: 0.85;
        }
        .mdl-tbtn--user {
            min-width: 52px;
            padding: 0 12px;
            border: 1.5px solid transparent;
            background: #16a34a;
            color: #fff;
            gap: 6px;
        }
        .mdl-tbtn--user:hover {
            background: #15803d;
            border-color: transparent;
            color: #fff;
        }
        .mdl-tbtn--user i.fa-chevron-down {
            font-size: 10px;
            opacity: 0.85;
        }

        /* ===== Custom cabang picker modal ===== */
        .mdl-cmodal {
            position: fixed;
            inset: 0;
            z-index: 4000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .mdl-cmodal.is-open {
            display: flex;
        }
        .mdl-cmodal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(3px);
        }
        .mdl-cmodal__panel {
            position: relative;
            z-index: 1;
            width: min(420px, 100%);
            max-height: min(78vh, 560px);
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.28);
            overflow: hidden;
            animation: mdlCmodalIn .18s ease-out;
        }
        @keyframes mdlCmodalIn {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: none; }
        }
        .mdl-cmodal__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
        }
        .mdl-cmodal__head h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .mdl-cmodal__head small {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            font-weight: 600;
            opacity: 0.8;
        }
        .mdl-cmodal__close {
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 8px;
            background: rgba(255,255,255,.15);
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .mdl-cmodal__close:hover {
            background: rgba(255,255,255,.28);
        }
        .mdl-cmodal__body {
            padding: 12px;
            overflow-y: auto;
            background: #f1f5f9;
        }
        .mdl-cmodal__grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }
        .mdl-cmodal__item {
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 72px;
            padding: 10px 8px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            color: #0f172a;
            cursor: pointer;
            text-align: center;
            transition: transform .12s ease, border-color .12s ease, box-shadow .12s ease, background .12s ease;
        }
        .mdl-cmodal__item:hover {
            border-color: #93c5fd;
            background: #eff6ff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
            transform: translateY(-1px);
        }
        .mdl-cmodal__item.is-active {
            border-color: #2563eb;
            background: linear-gradient(180deg, #dbeafe, #eff6ff);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }
        .mdl-cmodal__kode {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #1e40af;
            line-height: 1;
        }
        .mdl-cmodal__item.is-active .mdl-cmodal__kode {
            color: #1d4ed8;
        }
        .mdl-cmodal--user .mdl-cmodal__head {
            background: linear-gradient(135deg, #14532d, #16a34a);
        }
        .mdl-cmodal--user .mdl-cmodal__item:hover {
            border-color: #86efac;
            background: #f0fdf4;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.12);
        }
        .mdl-cmodal--user .mdl-cmodal__item.is-active {
            border-color: #16a34a;
            background: linear-gradient(180deg, #dcfce7, #f0fdf4);
            box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.2);
        }
        .mdl-cmodal--user .mdl-cmodal__kode {
            color: #166534;
            font-size: 14px;
        }
        .mdl-cmodal--user .mdl-cmodal__item.is-active .mdl-cmodal__kode {
            color: #15803d;
        }
        .mdl-cmodal__grid--user {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .mdl-cmodal__meta {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            line-height: 1.25;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mdl-cmodal__foot {
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            background: #fff;
            border-top: 1px solid #e2e8f0;
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
        body.mode-training .mode-switch {
            border-color: #c2410c;
            background: #fed7aa;
        }

        /* ===== Sidebar profile card ===== */
        .mdl-side-card {
            margin: 10px 10px 8px;
            padding: 12px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid rgba(255,255,255,.12);
            box-shadow: 0 6px 16px rgba(0,0,0,.18);
        }
        .mdl-side-user {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .mdl-side-user-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            max-width: 100%;
            height: 34px;
            padding: 0 12px;
            border-radius: 9px;
            border: 1.5px solid #d5dde6;
            background: #fff;
            color: #0f172a;
            font-family: 'fontku', sans-serif;
            font-size: 13px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mdl-side-user-btn i {
            color: #2563eb;
            flex: 0 0 auto;
        }
        .mdl-side-wifi {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 28px;
            padding: 0 4px;
            margin-bottom: 10px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }
        .mdl-side-wifi i {
            color: #64748b;
            width: 16px;
            text-align: center;
        }
        .mdl-side-wifi span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .role-switch {
            display: flex;
            align-items: stretch;
            width: 100%;
            height: 36px;
            padding: 3px;
            gap: 2px;
            border-radius: 10px;
            border: 1.5px solid #d5dde6;
            background: #e2e8f0;
            box-shadow: inset 0 1px 2px rgba(0,0,0,.06);
        }
        .role-switch .role-btn {
            flex: 1 1 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #64748b;
            font-family: 'fontku', sans-serif;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        }
        .role-switch .role-btn:hover:not(.is-active) {
            color: #334155;
            background: rgba(255,255,255,.45);
        }
        .role-switch .role-btn.is-active[data-mode="0"] {
            background: #16a34a;
            color: #fff;
            box-shadow: 0 1px 2px rgba(22, 163, 74, 0.35);
        }
        .role-switch .role-btn.is-active[data-mode="1"] {
            background: #dc2626;
            color: #fff;
            box-shadow: 0 1px 2px rgba(220, 38, 38, 0.35);
        }

        /* ===== Sidebar menu card ===== */
        .mdl-side-nav {
            margin: 0 10px 12px;
            padding: 8px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid rgba(255,255,255,.08);
            box-shadow: 0 6px 16px rgba(0,0,0,.16);
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .mdl-side-nav-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding-right: 6px;
            margin-right: 2px;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 #eef2f7;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-track {
            background: #eef2f7;
            border-radius: 99px;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 99px;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .mdl-side-nav > .nav,
        .mdl-side-nav-scroll > .nav {
            padding: 0 !important;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
        }
        .mdl-side-nav .nav-sidebar > .nav,
        .mdl-side-nav .nav-sidebar {
            gap: 2px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .mdl-side-nav .nav-item {
            margin: 0 0 2px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .mdl-side-nav .nav-link {
            display: flex !important;
            align-items: center;
            gap: 10px;
            min-height: 40px;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 8px 12px !important;
            border-radius: 10px !important;
            border: 1.5px solid transparent;
            background: transparent !important;
            color: #334155 !important;
            font-family: 'fontku', sans-serif;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
            transition: background .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease;
        }
        .mdl-side-nav .nav-link p {
            margin: 0 !important;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex: 1 1 auto;
            width: auto !important;
            white-space: nowrap;
        }
        .mdl-side-nav .nav-link > .nav-icon,
        .mdl-side-nav .nav-link > i.nav-icon {
            width: 18px !important;
            margin-right: 0 !important;
            font-size: 14px !important;
            text-align: center;
            color: #64748b !important;
        }
        .mdl-side-nav .nav-link:hover {
            background: #eef2f7 !important;
            color: #0f172a !important;
            border-color: #e2e8f0;
        }
        .mdl-side-nav .nav-link:hover > .nav-icon,
        .mdl-side-nav .nav-link:hover > i.nav-icon {
            color: #2563eb !important;
        }
        .mdl-side-nav .nav-link.active {
            background: #2563eb !important;
            border-color: #2563eb !important;
            color: #fff !important;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.28);
        }
        .mdl-side-nav .nav-link.active > .nav-icon,
        .mdl-side-nav .nav-link.active > i.nav-icon {
            color: #fff !important;
        }
        .mdl-side-nav .nav-link .right,
        .mdl-side-nav .nav-link .fa-angle-left {
            margin-left: auto;
            font-size: 12px;
            opacity: 0.7;
            transition: transform .2s ease;
        }
        .mdl-side-nav .nav-item.menu-open > .nav-link .fa-angle-left,
        .mdl-side-nav .nav-item.menu-is-opening > .nav-link .fa-angle-left {
            transform: rotate(-90deg);
        }
        .mdl-side-nav .nav-item.menu-open > .nav-link:not(.active),
        .mdl-side-nav .nav-item.menu-is-opening > .nav-link:not(.active) {
            background: #e2e8f0 !important;
            color: #0f172a !important;
            border-color: #cbd5e1;
        }
        .mdl-side-nav .nav-treeview {
            margin: 4px 0 6px !important;
            padding: 4px !important;
            border-radius: 10px;
            background: #eef2f7;
        }
        .mdl-side-nav .nav-treeview .nav-item {
            margin: 0 0 2px;
        }
        .mdl-side-nav .nav-treeview .nav-link {
            min-height: 34px;
            padding: 6px 10px 6px 12px !important;
            font-size: 12.5px;
            font-weight: 700;
            color: #475569 !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .mdl-side-nav .nav-treeview .nav-link > .nav-icon,
        .mdl-side-nav .nav-treeview .nav-link > i.nav-icon {
            font-size: 8px !important;
            color: #94a3b8 !important;
        }
        .mdl-side-nav .nav-treeview .nav-link:hover {
            background: #fff !important;
            color: #0f172a !important;
            border-color: #e2e8f0;
        }
        .mdl-side-nav .nav-treeview .nav-link.active {
            background: #fff !important;
            border-color: #93c5fd !important;
            color: #1d4ed8 !important;
            box-shadow: 0 1px 4px rgba(37, 99, 235, 0.12) !important;
        }
        .mdl-side-nav .nav-treeview .nav-link.active > .nav-icon,
        .mdl-side-nav .nav-treeview .nav-link.active > i.nav-icon {
            color: #2563eb !important;
        }
        .mdl-side-nav .nav-treeview .nav-link p b {
            font-weight: 700;
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

                <?php if (!$isTrainingUi && ($this->id_privilege == 100 or $this->id_privilege == 12)) {
                    $kodeCabangAktif = $this->dCabang['kode_cabang'] ?? $this->id_cabang;
                ?>
                    <button type="button" id="btnPilihCabang" class="mdl-tbtn mdl-tbtn--cabang" title="Pilih cabang">
                        <span id="btnPilihCabangLabel"><?= htmlspecialchars((string) $kodeCabangAktif) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <?php if ($this->id_privilege == 100) { ?>
                        <button type="button" id="btnPilihUser" class="mdl-tbtn mdl-tbtn--user" title="Login sebagai user">
                            <i class="fas fa-user"></i>
                            <i class="fas fa-chevron-down"></i>
                        </button>
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

        <?php if (!$isTrainingUi && ($this->id_privilege == 100 or $this->id_privilege == 12)) { ?>
        <div class="mdl-cmodal" id="mdlCabangModal" aria-hidden="true">
            <div class="mdl-cmodal__backdrop" data-cmodal-close></div>
            <div class="mdl-cmodal__panel" role="dialog" aria-modal="true" aria-labelledby="mdlCabangTitle">
                <div class="mdl-cmodal__head">
                    <div>
                        <h3 id="mdlCabangTitle">Pilih Cabang</h3>
                        <small>Tap kode untuk pindah outlet</small>
                    </div>
                    <button type="button" class="mdl-cmodal__close" data-cmodal-close aria-label="Tutup">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mdl-cmodal__body">
                    <div class="mdl-cmodal__grid">
                        <?php foreach ($this->listCabang as $lcb) {
                            $cid = (int) $lcb['id_cabang'];
                            $isActive = ($cid === (int) $this->id_cabang);
                            $alamat = trim((string) ($lcb['alamat'] ?? ''));
                            if (strlen($alamat) > 28) {
                                $alamat = substr($alamat, 0, 28) . '…';
                            }
                        ?>
                            <button type="button"
                                class="mdl-cmodal__item<?= $isActive ? ' is-active' : '' ?>"
                                data-id-cabang="<?= $cid ?>"
                                data-kode="<?= htmlspecialchars((string) $lcb['kode_cabang']) ?>">
                                <span class="mdl-cmodal__kode"><?= htmlspecialchars((string) $lcb['kode_cabang']) ?></span>
                                <?php if ($alamat !== '') { ?>
                                    <span class="mdl-cmodal__meta"><?= htmlspecialchars($alamat) ?></span>
                                <?php } else { ?>
                                    <span class="mdl-cmodal__meta">ID <?= $cid ?></span>
                                <?php } ?>
                            </button>
                        <?php } ?>
                    </div>
                </div>
                <div class="mdl-cmodal__foot">
                    Cabang aktif: <b><?= htmlspecialchars((string) ($this->dCabang['kode_cabang'] ?? $this->id_cabang)) ?></b>
                </div>
            </div>
        </div>
        <?php if ($this->id_privilege == 100) {
            $userSwitchList = [];
            foreach ($this->user as $a) {
                if ($a['id_user'] <> $_SESSION[URL::SESSID]['user']['id_user']) {
                    $userSwitchList[] = $a;
                }
            }
        ?>
        <div class="mdl-cmodal mdl-cmodal--user" id="mdlUserModal" aria-hidden="true">
            <div class="mdl-cmodal__backdrop" data-umodal-close></div>
            <div class="mdl-cmodal__panel" role="dialog" aria-modal="true" aria-labelledby="mdlUserTitle">
                <div class="mdl-cmodal__head">
                    <div>
                        <h3 id="mdlUserTitle">Pilih User</h3>
                        <small>Login sebagai kasir lain di cabang ini</small>
                    </div>
                    <button type="button" class="mdl-cmodal__close" data-umodal-close aria-label="Tutup">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mdl-cmodal__body">
                    <?php if (count($userSwitchList) === 0) { ?>
                        <div style="padding:18px;text-align:center;color:#64748b;font-weight:600;font-size:13px;">
                            Tidak ada user lain di cabang ini
                        </div>
                    <?php } else { ?>
                    <div class="mdl-cmodal__grid mdl-cmodal__grid--user">
                        <?php foreach ($userSwitchList as $a) { ?>
                            <button type="button"
                                class="mdl-cmodal__item"
                                data-id-user="<?= (int) $a['id_user'] ?>">
                                <span class="mdl-cmodal__kode"><?= htmlspecialchars(strtoupper((string) $a['nama_user'])) ?></span>
                                <span class="mdl-cmodal__meta">ID <?= (int) $a['id_user'] ?></span>
                            </button>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
                <div class="mdl-cmodal__foot">
                    User aktif: <b><?= htmlspecialchars((string) $this->nama_user) ?></b>
                </div>
            </div>
        </div>
        <?php } ?>
        <?php } ?>

        <aside class="main-sidebar sidebar-dark-yellow shadow-sm position-fixed">
            <div class="sidebar px-0">
                <div class="mdl-side-card">
                    <div class="mdl-side-user">
                        <span class="mdl-side-user-btn" title="<?= htmlspecialchars((string) $this->nama_user) ?>">
                            <i class="fas fa-user-circle"></i>
                            <span><?= htmlspecialchars((string) $this->nama_user) ?></span>
                        </span>
                    </div>
                    <?php
                    $wifiPass = trim((string) ($_SESSION[URL::SESSID]['data']['cabang']['wifi_pass'] ?? ''));
                    if ($wifiPass !== '') {
                    ?>
                    <div class="mdl-side-wifi">
                        <i class="fas fa-wifi"></i>
                        <span><?= htmlspecialchars($wifiPass) ?></span>
                    </div>
                    <?php } ?>

                    <?php if ($this->id_privilege == 100) { ?>
                    <div class="role-switch" id="roleSwitch" title="Ganti mode Kasir / Admin">
                        <button type="button" id="btnKasir" class="role-btn<?= ($log_mode == 0) ? ' is-active' : '' ?>" data-mode="0">
                            <i class="fas fa-cash-register"></i> Kasir
                        </button>
                        <button type="button" id="btnAdmin" class="role-btn<?= ($log_mode == 1) ? ' is-active' : '' ?>" data-mode="1">
                            <i class="fas fa-user-shield"></i> Admin
                        </button>
                    </div>
                    <?php } ?>
                </div>

                <!-- MENU --------------------------------->
                <nav class="mdl-side-nav pb-3">
                    <div class="mdl-side-nav-scroll">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="true">
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
                    </div>
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

                // Hanya satu dropdown menu terbuka di sidebar
                (function initSidebarMenuAccordion() {
                    var $sidebar = $(".main-sidebar");
                    $sidebar.on("click", ".nav-item > a.nav-link", function() {
                        var $item = $(this).parent(".nav-item");
                        if (!$item.children(".nav-treeview").length) {
                            return;
                        }
                        // Tutup dropdown lain di seluruh sidebar (kecuali item yang diklik)
                        $sidebar.find(".nav-item.menu-open").not($item).each(function() {
                            var $other = $(this);
                            $other.removeClass("menu-open menu-is-opening");
                            $other.children(".nav-treeview").stop(true, true).slideUp(200);
                            $other.children("a.nav-link").removeClass("active");
                        });
                    });
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

                $("#roleSwitch .role-btn").on("click", function() {
                    var mode = parseInt($(this).data("mode"), 10);
                    if ($(this).hasClass("is-active")) {
                        return;
                    }
                    $.ajax({
                        url: "<?= URL::BASE_URL ?>Login/log_mode",
                        data: { mode: mode },
                        type: "POST",
                        dataType: "html",
                        success: function() {
                            $("#roleSwitch .role-btn").removeClass("is-active");
                            $('#roleSwitch .role-btn[data-mode="' + mode + '"]').addClass("is-active");
                            if (mode === 0) {
                                $("#nav_0").removeClass("d-none");
                                $("#nav_2").removeClass("d-none");
                                $("#nav_1").addClass("d-none");
                                $("#nav_3").addClass("d-none");
                            } else {
                                $("#nav_0").addClass("d-none");
                                $("#nav_2").addClass("d-none");
                                $("#nav_1").removeClass("d-none");
                                $("#nav_3").removeClass("d-none");
                            }
                        }
                    });
                });

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

                (function initCabangModal() {
                    var $modal = $("#mdlCabangModal");
                    if (!$modal.length) return;

                    function openCabangModal() {
                        $modal.addClass("is-open").attr("aria-hidden", "false");
                    }
                    function closeCabangModal() {
                        $modal.removeClass("is-open").attr("aria-hidden", "true");
                    }
                    function switchCabang(idCabang) {
                        $.ajax({
                            url: '<?= URL::BASE_URL ?>Cabang_List/selectCabang',
                            data: { id: idCabang },
                            type: "POST",
                            beforeSend: function() {
                                closeCabangModal();
                                $(".loaderDiv").fadeIn("fast");
                            },
                            success: function() {
                                location.reload(true);
                            },
                            error: function(xhr) {
                                $(".loaderDiv").fadeOut("fast");
                                alert("Gagal pindah cabang: " + (xhr.responseText || xhr.status));
                            }
                        });
                    }

                    $("#btnPilihCabang").on("click", function(e) {
                        e.preventDefault();
                        openCabangModal();
                    });
                    $modal.on("click", "[data-cmodal-close]", function() {
                        closeCabangModal();
                    });
                    $modal.on("click", ".mdl-cmodal__item", function() {
                        var id = $(this).data("id-cabang");
                        var current = <?= (int) $this->id_cabang ?>;
                        if (!id || parseInt(id, 10) === current) {
                            closeCabangModal();
                            return;
                        }
                        switchCabang(id);
                    });
                    $(document).on("keydown", function(e) {
                        if (e.key === "Escape" && $modal.hasClass("is-open")) {
                            closeCabangModal();
                        }
                    });
                })();

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

                (function initUserModal() {
                    var $modal = $("#mdlUserModal");
                    if (!$modal.length) return;

                    function openUserModal() {
                        $modal.addClass("is-open").attr("aria-hidden", "false");
                    }
                    function closeUserModal() {
                        $modal.removeClass("is-open").attr("aria-hidden", "true");
                    }
                    function switchUser(idUser) {
                        $.ajax({
                            url: '<?= URL::BASE_URL ?>Login/switchUser',
                            data: { id: idUser },
                            type: "POST",
                            beforeSend: function() {
                                closeUserModal();
                                $(".loaderDiv").fadeIn("fast");
                            },
                            success: function() {
                                location.reload(true);
                            },
                            error: function(xhr) {
                                $(".loaderDiv").fadeOut("fast");
                                alert("Gagal ganti user: " + (xhr.responseText || xhr.status));
                            }
                        });
                    }

                    $("#btnPilihUser").on("click", function(e) {
                        e.preventDefault();
                        openUserModal();
                    });
                    $modal.on("click", "[data-umodal-close]", function() {
                        closeUserModal();
                    });
                    $modal.on("click", ".mdl-cmodal__item", function() {
                        var id = $(this).data("id-user");
                        if (!id) {
                            closeUserModal();
                            return;
                        }
                        switchUser(id);
                    });
                    $(document).on("keydown", function(e) {
                        if (e.key === "Escape" && $modal.hasClass("is-open")) {
                            closeUserModal();
                        }
                    });
                })();

                function hide_modal() {
                    $(".modal").each(function() {
                        $(this).modal('hide');
                    });
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
            </script>
<?php require_once __DIR__ . '/pwa_register.php'; ?>