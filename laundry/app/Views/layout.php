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
    <link rel="icon" href="<?= URL::IN_ASSETS ?>icon/j-icon.svg" type="image/svg+xml">
    <?php require_once __DIR__ . '/pwa_head.php'; ?>
    <title><?= !empty($this->isTrainingMode) ? '[TRAINING] ' : '' ?><?= $title ?> | MDL</title>
    <meta name="viewport" content="width=430, user-scalable=no">
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

        html {
            font-size: 16px; /* Bootstrap rem scale */
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

        /* Konten: longgarkan small/table agar tidak terlalu kecil vs chrome */
        .content-wrapper small,
        .content-wrapper .small {
            font-size: 0.95em;
        }
        .content-wrapper .table {
            font-size: 15px;
        }
        .content-wrapper .table.table-sm {
            font-size: 14px;
        }

        /* Header warna nota Antrian/Operasi ? tema MDL (bukan Bootstrap) */
        .content-wrapper tr.mdl-nota-today > td,
        .content-wrapper tr.mdl-nota-past > td,
        .content-wrapper tr.mdl-nota-member > td,
        .content-wrapper .mdl-nota-head.mdl-nota-today,
        .content-wrapper .mdl-nota-head.mdl-nota-past,
        .content-wrapper .mdl-nota-head.mdl-nota-member {
            border-color: transparent !important;
            color: #0f172a !important;
            font-weight: 700;
            vertical-align: middle;
        }
        /* Today ? UI Theme blue */
        .content-wrapper tr.mdl-nota-today > td,
        .content-wrapper .mdl-nota-head.mdl-nota-today {
            background: linear-gradient(to bottom, #93c5fd 0%, #bfdbfe 45%, #eff6ff 100%) !important;
            box-shadow: inset 0 -1px 0 rgba(37, 99, 235, 0.35);
        }
        /* Past ? UI Theme green */
        .content-wrapper tr.mdl-nota-past > td,
        .content-wrapper .mdl-nota-head.mdl-nota-past {
            background: linear-gradient(to bottom, #86efac 0%, #bbf7d0 45%, #f0fdf4 100%) !important;
            box-shadow: inset 0 -1px 0 rgba(22, 163, 74, 0.35);
        }
        /* Member ? UI Theme blue (nuansa biru lama) */
        .content-wrapper tr.mdl-nota-member > td,
        .content-wrapper .mdl-nota-head.mdl-nota-member {
            background: linear-gradient(to bottom, #60a5fa 0%, #93c5fd 45%, #dbeafe 100%) !important;
            box-shadow: inset 0 -1px 0 rgba(29, 78, 216, 0.32);
        }
        .content-wrapper tr.mdl-nota-today a,
        .content-wrapper tr.mdl-nota-past a,
        .content-wrapper tr.mdl-nota-member a,
        .content-wrapper .mdl-nota-head a {
            color: #0f172a !important;
        }
        .content-wrapper tr.mdl-nota-today .text-dark,
        .content-wrapper tr.mdl-nota-past .text-dark,
        .content-wrapper tr.mdl-nota-member .text-dark,
        .content-wrapper tr.mdl-nota-today small,
        .content-wrapper tr.mdl-nota-past small,
        .content-wrapper tr.mdl-nota-member small,
        .content-wrapper tr.mdl-nota-today b,
        .content-wrapper tr.mdl-nota-past b,
        .content-wrapper tr.mdl-nota-member b {
            color: #0f172a !important;
            font-weight: 700 !important;
        }

        /* Head nota Operasi ? padding & alignment seimbang */
        .content-wrapper .mdl-nota-card {
            overflow: hidden;
            border-radius: 0;
            background: #fff;
            box-shadow: 0 6px 16px rgba(36, 48, 65, 0.08);
            margin-bottom: 0;
        }
        .content-wrapper .mdl-nota-head {
            padding: 6px 8px 5px;
        }
        .content-wrapper .mdl-nota-head__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }
        .content-wrapper .mdl-nota-head__left {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }
        .content-wrapper .mdl-nota-head__print {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            text-decoration: none;
        }
        .content-wrapper .mdl-nota-head__name {
            font-weight: 800;
            letter-spacing: 0;
            line-height: 1.2;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .content-wrapper .mdl-nota-head__right {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
            flex: 1 1 auto;
            min-width: 0;
            font-size: 0.82em;
            font-weight: 500 !important;
            color: #0f172a !important;
            white-space: nowrap;
            line-height: 1.2;
            margin-left: auto;
            text-align: right;
        }
        .content-wrapper .mdl-nota-head__right span {
            font-weight: 500 !important;
            color: #0f172a !important;
        }
        .content-wrapper .mdl-nota-head__right i {
            opacity: 1;
            color: #0f172a;
        }
        .content-wrapper .mdl-nota-head__wa {
            margin-left: 2px;
            flex-shrink: 0;
        }
        .content-wrapper .mdl-nota-head__actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
            padding-top: 0;
        }
        .content-wrapper .mdl-nota-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 24px;
            min-width: 24px;
            padding: 3px 8px;
            border: 1px solid transparent;
            border-radius: 0;
            box-shadow: 0 1px 2px rgba(36, 48, 65, 0.08);
            font-size: 0.76em;
            font-weight: 700;
            line-height: 1;
            letter-spacing: 0.01em;
            text-decoration: none;
            white-space: nowrap;
            transition: background-color .15s ease, box-shadow .15s ease, transform .12s ease;
            color: #0f172a;
            background: #fff;
        }
        .content-wrapper .mdl-nota-chip i {
            font-size: 0.95em;
            line-height: 1;
        }
        .content-wrapper .mdl-nota-chip:hover {
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(15, 23, 42, 0.12);
        }
        .content-wrapper .mdl-nota-chip--icon {
            padding: 0;
            width: 24px;
            height: 24px;
            min-width: 24px;
        }
        /* WA ? theme green */
        .content-wrapper .mdl-nota-chip--wa {
            color: #14532d;
            background: linear-gradient(180deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: #86efac;
        }
        .content-wrapper .mdl-nota-chip--wa i {
            color: #16a34a;
        }
        .content-wrapper .mdl-nota-chip--wa:hover {
            background: linear-gradient(180deg, #dcfce7 0%, #bbf7d0 100%);
            color: #14532d;
        }
        /* Pending ? theme yellow */
        .content-wrapper .mdl-nota-chip--pending {
            color: #78350f;
            background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #fcd34d;
        }
        .content-wrapper .mdl-nota-chip--pending i {
            color: #d97706;
        }
        /* OK ? theme green */
        .content-wrapper .mdl-nota-chip--ok {
            color: #14532d;
            background: linear-gradient(180deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: #86efac;
        }
        .content-wrapper .mdl-nota-chip--ok i {
            color: #15803d;
        }
        /* Label ? theme yellow */
        .content-wrapper .mdl-nota-chip--label {
            color: #78350f;
            background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #fcd34d;
        }
        .content-wrapper .mdl-nota-chip--label i {
            color: #d97706;
        }
        .content-wrapper .mdl-nota-chip--label:hover {
            color: #78350f;
            background: linear-gradient(180deg, #fef3c7 0%, #fde68a 100%);
        }
        /* Add ? theme blue */
        .content-wrapper .mdl-nota-chip--add {
            color: #1e3a8a;
            background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #93c5fd;
        }
        .content-wrapper .mdl-nota-chip--add i {
            color: #2563eb;
        }
        .content-wrapper .mdl-nota-chip--add:hover {
            color: #1e3a8a;
            background: linear-gradient(180deg, #dbeafe 0%, #bfdbfe 100%);
        }
        /* Bill ? theme yellow (bukan ungu) */
        .content-wrapper .mdl-nota-chip--bill {
            color: #78350f;
            background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #fcd34d;
        }
        .content-wrapper .mdl-nota-chip--bill i {
            color: #d97706;
        }
        .content-wrapper .mdl-nota-chip--bill:hover {
            color: #78350f;
            background: linear-gradient(180deg, #fef3c7 0%, #fde68a 100%);
        }
        .content-wrapper .mdl-nota-card > .table {
            margin-bottom: 0 !important;
        }

        /* Grid nota Antrian/Operasi ? jarak atas-bawah = kanan-kiri (8px) */
        .content-wrapper .mdl-nota-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            margin: 0;
            padding: 0;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box;
        }
        .content-wrapper .mdl-nota-grid__item {
            display: block;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box;
            float: none !important;
        }
        .content-wrapper .mdl-nota-grid__item > .mdl-nota-card,
        .content-wrapper .mdl-nota-card {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            box-sizing: border-box;
        }
        .content-wrapper .mdl-nota-card > .table,
        .content-wrapper .mdl-nota-grid__item .table {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            table-layout: auto;
        }
        @media (min-width: 1100px) {
            .content-wrapper .mdl-nota-grid {
                grid-template-columns: repeat(auto-fill, 500px);
                justify-content: start;
                gap: 8px;
            }
            .content-wrapper .mdl-nota-grid__item {
                width: 500px !important;
                max-width: 500px !important;
            }
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

        /*
         * AdminLTE full-close (bukan sidebar-mini icon rail).
         * body tanpa class sidebar-mini ? collapse = margin-left -250px.
         * Override eksplisit agar tidak pernah jatuh ke mode ikon 4.6rem.
         */
        body.sidebar-collapse .main-sidebar,
        body.sidebar-collapse .main-sidebar::before {
            margin-left: -250px !important;
            width: 250px !important;
            box-shadow: none !important;
        }
        body.sidebar-collapse .content-wrapper,
        body.sidebar-collapse .main-header,
        body.sidebar-collapse .main-footer {
            margin-left: 0 !important;
        }
        body.sidebar-collapse .main-sidebar:hover,
        body.sidebar-collapse .main-sidebar.sidebar-focused {
            width: 250px !important;
            margin-left: -250px !important;
        }
        @media (max-width: 767.98px) {
            body:not(.sidebar-open) .main-sidebar,
            body:not(.sidebar-open) .main-sidebar::before {
                margin-left: -250px !important;
            }
            body.sidebar-open .main-sidebar,
            body.sidebar-open .main-sidebar::before {
                margin-left: 0 !important;
            }
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
            /* di-override oleh blok MDL UI Theme di bawah */
        }
        body.mode-training .main-sidebar {
            /* di-override oleh blok MDL UI Theme di bawah */
        }

        /* ===== MDL UI Theme chrome (topnav + sidebar) ? lihat laundry/docs/UI_THEME.md ===== */
        :root {
            --mdl-ink: #0f172a;
            --mdl-ink-soft: #1e293b;
            --mdl-line: #94a3b8;
            --mdl-line-soft: #cbd5e1;
            --mdl-surface: #ffffff;
            --mdl-surface-2: #eff6ff;
            --mdl-shell: #dbeafe;
            --mdl-shell-deep: #bfdbfe;
            --mdl-accent: #2563eb;
            --mdl-accent-deep: #1d4ed8;
            --mdl-accent-soft: #dbeafe;
            --mdl-live: #16a34a;
            --mdl-live-deep: #15803d;
            --mdl-train: #f59e0b;
            --mdl-train-deep: #d97706;
            --mdl-train-shell: #fef3c7;
            --mdl-train-surface: #fffbeb;
            --mdl-admin: #dc2626;
            --mdl-admin-deep: #b91c1c;
            --mdl-yellow: #f59e0b;
            --mdl-shadow: 0 10px 24px rgba(15, 23, 42, 0.1);
            --mdl-radius: 0;
        }

        /* Wajib siku: tidak ada round di shell UI */
        .modal-content,
        .modal-header,
        .modal-body,
        .modal-footer,
        .offcanvas,
        .btn,
        .form-control,
        .form-select,
        .input-group-text,
        .badge,
        .card,
        .alert,
        .dropdown-menu,
        .selectize-input,
        .selectize-dropdown {
            border-radius: 0 !important;
        }

        /* ===== Top toolbar ===== */
        .main-header.mdl-topbar {
            display: block;
            padding: 8px 10px !important;
            background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%) !important;
            background-image: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%) !important;
            border-bottom: 1px solid #0f172a;
            min-height: 0;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.18);
        }
        /* Priv 100 (admin): merah */
        body.mode-priv-100 .main-header.mdl-topbar {
            background: linear-gradient(105deg, #991b1b 0%, #dc2626 100%) !important;
            background-image: linear-gradient(105deg, #991b1b 0%, #dc2626 100%) !important;
            border-bottom-color: #7f1d1d;
        }
        /* Priv 12 (kurir): cyan */
        body.mode-priv-12 .main-header.mdl-topbar {
            background: linear-gradient(105deg, #0e7490 0%, #0891b2 100%) !important;
            background-image: linear-gradient(105deg, #0e7490 0%, #0891b2 100%) !important;
            border-bottom-color: #155e75;
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
            border-radius: 0;
            font-family: 'fontku', sans-serif;
            font-size: 13px;
            font-weight: 900;
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
            border: 1px solid rgba(255,255,255,.55);
            background: rgba(15, 23, 42, 0.22);
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            text-shadow: 0 1px 0 rgba(0,0,0,.18);
            transition: background .15s ease, border-color .15s ease, color .15s ease, filter .15s ease;
        }
        .mdl-tbtn:hover {
            background: rgba(15, 23, 42, 0.38);
            border-color: #fff;
            color: #fff;
            text-decoration: none;
            filter: brightness(1.05);
        }
        .mdl-tbtn:active {
            transform: translateY(1px);
        }
        .mdl-tbtn--menu {
            padding: 0 12px 0 10px;
            background: #0f172a;
            border-color: #0f172a;
        }
        .mdl-tbtn--menu:hover {
            background: #1e293b;
            border-color: #1e293b;
        }
        .mdl-tbtn--icon {
            width: 34px;
            padding: 0;
            flex: 0 0 34px;
        }
        .mdl-tbtn--refresh {
            border-color: transparent;
            color: #fff;
            background: var(--mdl-live);
        }
        .mdl-tbtn--refresh:hover {
            background: var(--mdl-live-deep);
            border-color: transparent;
            color: #fff;
        }
        .mdl-tbtn--logout {
            border-color: transparent;
            color: #fff;
            background: var(--mdl-admin);
        }
        .mdl-tbtn--logout:hover {
            background: var(--mdl-admin-deep);
            border-color: transparent;
            color: #fff;
        }

        .mdl-topbar select.mdl-tctrl {
            padding: 0 28px 0 10px;
            border: 1px solid transparent;
            color: #fff;
            cursor: pointer;
            max-width: 88px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23ffffff' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 8px 5px;
        }
        .mdl-topbar select.mdl-tctrl:focus {
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.28);
        }
        .mdl-tbtn--cabang {
            min-width: 52px;
            padding: 0 12px;
            border: 1px solid transparent;
            background: #0f172a;
            color: #fff;
            gap: 6px;
        }
        .mdl-tbtn--cabang:hover {
            background: #1e293b;
            border-color: transparent;
            color: #fff;
        }
        .mdl-tbtn--cabang i {
            font-size: 10px;
            opacity: 0.95;
        }
        .mdl-tbtn--cabang.is-loading {
            pointer-events: none;
            opacity: 0.9;
            min-width: 52px;
        }
        .mdl-tbtn--cabang .mdl-cabang-spin {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: mdl-cabang-spin 0.7s linear infinite;
            vertical-align: middle;
            flex: 0 0 auto;
        }
        @keyframes mdl-cabang-spin {
            to { transform: rotate(360deg); }
        }
        /* Global toast (UI theme) */
        .mdl-toast-host {
            position: fixed;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%);
            z-index: 5400;
            display: flex;
            flex-direction: column-reverse;
            align-items: center;
            gap: 8px;
            width: min(420px, calc(100vw - 24px));
            pointer-events: none;
        }
        .mdl-toast {
            pointer-events: auto;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #93c5fd;
            border-radius: 0;
            background: linear-gradient(180deg, #eff6ff, #fff);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
            color: #0f172a;
            font-family: 'fontku', 'Segoe UI', sans-serif;
            font-size: 0.88rem;
            font-weight: 800;
            line-height: 1.35;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .18s ease, transform .18s ease;
        }
        .mdl-toast.is-show {
            opacity: 1;
            transform: translateY(0);
        }
        .mdl-toast__icon {
            width: 28px;
            height: 28px;
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: #2563eb;
            border-radius: 0;
        }
        .mdl-toast__msg {
            flex: 1 1 auto;
            min-width: 0;
            padding-top: 3px;
        }
        .mdl-toast__close {
            flex: 0 0 auto;
            width: 28px;
            height: 28px;
            border: 1px solid #cbd5e1;
            background: #e2e8f0;
            color: #0f172a;
            border-radius: 0;
            cursor: pointer;
            font-weight: 900;
            line-height: 1;
        }
        .mdl-toast--ok {
            border-color: #86efac;
            background: linear-gradient(180deg, #f0fdf4, #fff);
        }
        .mdl-toast--ok .mdl-toast__icon { background: #16a34a; }
        .mdl-toast--warn {
            border-color: #fcd34d;
            background: linear-gradient(180deg, #fffbeb, #fff);
        }
        .mdl-toast--warn .mdl-toast__icon { background: #f59e0b; color: #111; }
        .mdl-toast--error {
            border-color: #fca5a5;
            background: linear-gradient(180deg, #fef2f2, #fff);
        }
        .mdl-toast--error .mdl-toast__icon { background: #dc2626; }
        .mdl-toast--error .mdl-toast__msg { color: #b91c1c; }
        .mdl-toast--info {
            border-color: #93c5fd;
            background: linear-gradient(180deg, #eff6ff, #fff);
        }
        .mdl-toast--info .mdl-toast__icon { background: #2563eb; }
        .mdl-tbtn--user {
            min-width: 52px;
            padding: 0 12px;
            border: 1px solid transparent;
            background: var(--mdl-live);
            color: #fff;
            gap: 6px;
        }
        .mdl-tbtn--user:hover {
            background: var(--mdl-live-deep);
            border-color: transparent;
            color: #fff;
        }
        .mdl-tbtn--user i.fa-chevron-down {
            font-size: 10px;
            opacity: 0.95;
        }

        .mode-switch {
            display: inline-flex;
            align-items: stretch;
            overflow: hidden;
            padding: 3px;
            gap: 2px;
            border: 1px solid #0f172a;
            background: rgba(15, 23, 42, 0.28);
            box-shadow: none;
        }
        .mode-switch .mode-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-width: 64px;
            padding: 0 12px;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: rgba(255,255,255,.88);
            font-family: inherit;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.02em;
            cursor: pointer;
            user-select: none;
            transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        }
        .mode-switch .mode-btn:hover:not(.active-live):not(.active-training) {
            color: #fff;
            background: rgba(255,255,255,.18);
        }
        .mode-switch .mode-btn.active-live {
            background: var(--mdl-live);
            color: #fff;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.35);
        }
        .mode-switch .mode-btn.active-training {
            background: var(--mdl-train);
            color: #111;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.35);
        }

        body.mode-training .main-header.mdl-topbar {
            background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%) !important;
            background-image: linear-gradient(105deg, #d97706 0%, #f59e0b 100%) !important;
            border-bottom-color: #92400e;
        }
        body.mode-training .mode-switch {
            border-color: #0f172a;
            background: rgba(15, 23, 42, 0.28);
        }
        body.mode-training .main-sidebar {
            background:
                radial-gradient(90% 50% at 0% 0%, rgba(245,158,11,.18), transparent 50%),
                linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%) !important;
        }
        .mdl-training-chip {
            display: inline-flex;
            align-items: center;
            height: 34px;
            padding: 0 12px;
            border-radius: 0;
            background: #92400e;
            color: #fff;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.04em;
            border: 1px solid #0f172a;
        }

        /* ===== Sidebar shell ===== */
        .main-sidebar.sidebar-dark-yellow {
            background:
                radial-gradient(90% 50% at 0% 0%, rgba(37,99,235,.14), transparent 50%),
                radial-gradient(80% 40% at 100% 0%, rgba(245,158,11,.12), transparent 45%),
                linear-gradient(180deg, #eef4ff 0%, #f4fff8 55%, #fff8eb 100%) !important;
            border-right: 1px solid var(--mdl-line-soft);
        }

        /* ===== Sidebar profile card ===== */
        .mdl-side-card {
            margin: 10px 10px 8px;
            padding: 12px;
            border-radius: 0;
            background: linear-gradient(180deg, #eff6ff, #fff);
            border: 1px solid #93c5fd;
            box-shadow: var(--mdl-shadow);
        }
        .mdl-side-user {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }
        .mdl-side-user-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex: 1 1 auto;
            min-width: 0;
            max-width: 100%;
            height: 34px;
            padding: 0 12px;
            border-radius: 0;
            border: 1px solid var(--mdl-line);
            background: #fff;
            color: var(--mdl-ink);
            font-family: 'fontku', sans-serif;
            font-size: 13px;
            font-weight: 900;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mdl-side-user-btn > span {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mdl-side-user-btn i {
            color: var(--mdl-accent);
            flex: 0 0 auto;
        }
        .mdl-side-switch-user {
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            padding: 0;
            border-radius: 0;
            border: 1px solid transparent;
            background: var(--mdl-live);
            color: #fff;
            cursor: pointer;
            transition: background .15s ease, transform .12s ease;
        }
        .mdl-side-switch-user:hover {
            background: var(--mdl-live-deep);
            color: #fff;
        }
        .mdl-side-switch-user i {
            font-size: 13px;
        }
        /* Driver cart-block modal (UI theme / op-modal style) */
        .drv-modal {
            --drv-ink: #0f172a;
            --drv-yellow: #f59e0b;
            --drv-yellow-deep: #d97706;
            --drv-live: #16a34a;
            --drv-live-deep: #15803d;
            position: fixed;
            inset: 0;
            z-index: 5300;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .drv-modal.is-open { display: flex; }
        .drv-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            cursor: pointer;
        }
        .drv-modal__panel {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 0;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
            overflow: hidden;
        }
        .drv-modal__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 16px;
            background: linear-gradient(180deg, var(--drv-yellow), var(--drv-yellow-deep));
            color: #111;
        }
        .drv-modal__head h3 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .drv-modal__close {
            border: 0;
            background: rgba(0,0,0,.12);
            color: #111;
            width: 32px;
            height: 32px;
            border-radius: 0;
            cursor: pointer;
            flex: 0 0 auto;
        }
        .drv-modal__body {
            padding: 16px;
            font-size: 0.9rem;
            font-weight: 750;
            color: var(--drv-ink);
            line-height: 1.45;
        }
        .drv-modal__body p { margin: 0; }
        .drv-modal__icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            margin: 0 4px;
            vertical-align: middle;
            background: var(--drv-live);
            color: #fff;
            border: 1px solid transparent;
            border-radius: 0;
        }
        .drv-modal__icon-btn i { font-size: 12px; }
        .drv-modal__foot {
            display: flex;
            justify-content: flex-end;
            padding: 12px 16px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .drv-modal__ok {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: 1px solid transparent;
            border-radius: 0;
            background: linear-gradient(180deg, var(--drv-yellow), var(--drv-yellow-deep));
            color: #111;
            font-size: 0.9rem;
            font-weight: 900;
            cursor: pointer;
        }
        .mdl-side-wifi {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 28px;
            padding: 0 4px;
            margin-bottom: 10px;
            color: var(--mdl-ink-soft);
            font-size: 12px;
            font-weight: 800;
        }
        .mdl-side-wifi i {
            color: var(--mdl-accent);
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
            border-radius: 0;
            border: 1px solid var(--mdl-line);
            background: #f8fafc;
            box-shadow: none;
        }
        .role-switch .role-btn {
            flex: 1 1 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: var(--mdl-ink-soft);
            font-family: 'fontku', sans-serif;
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
            transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        }
        .role-switch .role-btn:hover:not(.is-active) {
            color: var(--mdl-ink);
            background: #e2e8f0;
        }
        .role-switch .role-btn.is-active[data-mode="0"] {
            background: var(--mdl-live);
            color: #fff;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.28);
        }
        .role-switch .role-btn.is-active[data-mode="1"] {
            background: var(--mdl-admin);
            color: #fff;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.28);
        }

        /* ===== Sidebar menu card ===== */
        .mdl-side-nav {
            margin: 0 10px 12px;
            padding: 8px;
            border-radius: 0;
            background: linear-gradient(180deg, #fffbeb, #fff);
            border: 1px solid #fcd34d;
            box-shadow: var(--mdl-shadow);
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
            padding: 0 8px;
            box-sizing: border-box;
            scrollbar-gutter: stable;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 transparent;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 0;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 0;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-thumb:hover {
            background: #64748b;
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
            border-radius: 0 !important;
            border: 1px solid transparent;
            background: transparent !important;
            color: var(--mdl-ink) !important;
            font-family: 'fontku', sans-serif;
            font-size: 13px;
            font-weight: 800;
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
            color: var(--mdl-accent) !important;
        }
        .mdl-side-nav .nav-link:hover {
            background: #dbeafe !important;
            color: var(--mdl-ink) !important;
            border-color: #60a5fa;
        }
        .mdl-side-nav .nav-link:hover > .nav-icon,
        .mdl-side-nav .nav-link:hover > i.nav-icon {
            color: var(--mdl-accent-deep) !important;
        }
        .mdl-side-nav .nav-link.active {
            background: var(--mdl-accent) !important;
            border-color: var(--mdl-accent-deep) !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            font-weight: 900;
        }
        .mdl-side-nav .nav-link.active > .nav-icon,
        .mdl-side-nav .nav-link.active > i.nav-icon {
            color: #fff !important;
        }
        .mdl-side-nav .nav-link .right,
        .mdl-side-nav .nav-link .fa-angle-left {
            margin-left: auto;
            font-size: 12px;
            opacity: 0.85;
            transition: transform .2s ease;
        }
        .mdl-side-nav .nav-item.menu-open > .nav-link .fa-angle-left,
        .mdl-side-nav .nav-item.menu-is-opening > .nav-link .fa-angle-left {
            transform: rotate(-90deg);
        }
        .mdl-side-nav .nav-item.menu-open > .nav-link:not(.active),
        .mdl-side-nav .nav-item.menu-is-opening > .nav-link:not(.active) {
            background: #fef3c7 !important;
            color: var(--mdl-ink) !important;
            border-color: #fbbf24;
        }
        .mdl-side-nav .nav-treeview {
            margin: 4px 0 6px !important;
            padding: 4px !important;
            border-radius: 0;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }
        .mdl-side-nav .nav-treeview .nav-item {
            margin: 0 0 2px;
        }
        .mdl-side-nav .nav-treeview .nav-link {
            min-height: 34px;
            padding: 6px 10px 6px 12px !important;
            font-size: 12.5px;
            font-weight: 800;
            color: var(--mdl-ink-soft) !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .mdl-side-nav .nav-treeview .nav-link > .nav-icon,
        .mdl-side-nav .nav-treeview .nav-link > i.nav-icon {
            font-size: 8px !important;
            color: var(--mdl-accent) !important;
        }
        .mdl-side-nav .nav-treeview .nav-link:hover {
            background: #fff !important;
            color: var(--mdl-ink) !important;
            border-color: #60a5fa;
        }
        .mdl-side-nav .nav-treeview .nav-link.active {
            background: #dcfce7 !important;
            border-color: #16a34a !important;
            color: #15803d !important;
            box-shadow: none !important;
            font-weight: 900;
        }
        .mdl-side-nav .nav-treeview .nav-link.active > .nav-icon,
        .mdl-side-nav .nav-treeview .nav-link.active > i.nav-icon {
            color: #16a34a !important;
        }
        .mdl-side-nav .nav-treeview .nav-link p b {
            font-weight: 900;
        }

        /* Modal accents (sudut runcing, warna tajam) */
        .mdl-cmodal__head {
            background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%) !important;
        }
        .mdl-cmodal--user .mdl-cmodal__head {
            background: linear-gradient(105deg, #15803d 0%, #16a34a 100%) !important;
        }
        .mdl-cmodal__item.is-active {
            border-color: var(--mdl-accent) !important;
            background: linear-gradient(180deg, #bfdbfe, #eff6ff) !important;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.28) !important;
        }
        .mdl-cmodal--user .mdl-cmodal__item.is-active {
            border-color: var(--mdl-live) !important;
            background: linear-gradient(180deg, #bbf7d0, #f0fdf4) !important;
            box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.28) !important;
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
            border-radius: 0;
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
            background: linear-gradient(135deg, #3f74d4, #5b8de0);
            color: #fff;
        }
        .mdl-cmodal__head h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .mdl-cmodal__head small {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            font-weight: 750;
            opacity: 0.95;
        }
        .mdl-cmodal__close {
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 0;
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
            border: 1px solid #e2e8f0;
            border-radius: 0;
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
            border-color: var(--mdl-accent);
            background: linear-gradient(180deg, #e8eef8, #f5f8fc);
            box-shadow: 0 0 0 2px rgba(107, 143, 212, 0.18);
        }
        .mdl-cmodal__kode {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: var(--mdl-accent-deep);
            line-height: 1;
        }
        .mdl-cmodal__item.is-active .mdl-cmodal__kode {
            color: var(--mdl-accent-deep);
        }
        .mdl-cmodal--user .mdl-cmodal__head {
            background: linear-gradient(135deg, #2f9e5f, #45b575);
        }
        .mdl-cmodal--user .mdl-cmodal__item:hover {
            border-color: #b7d4c2;
            background: #f3faf6;
            box-shadow: 0 4px 12px rgba(106, 170, 134, 0.1);
        }
        .mdl-cmodal--user .mdl-cmodal__item.is-active {
            border-color: var(--mdl-live);
            background: linear-gradient(180deg, #e8f3ec, #f5faf7);
            box-shadow: 0 0 0 2px rgba(106, 170, 134, 0.18);
        }
        .mdl-cmodal--user .mdl-cmodal__kode {
            color: var(--mdl-live-deep);
            font-size: 14px;
        }
        .mdl-cmodal--user .mdl-cmodal__item.is-active .mdl-cmodal__kode {
            color: var(--mdl-live-deep);
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

$adminIdleLimitSec = 600; // 10 menit
if (isset($_SESSION['log_mode'])) {
    $log_mode = (int) $_SESSION['log_mode'];
} else {
    $log_mode = 0;
}
// Idle mode Admin: paksa kembali ke Kasir
if ((int) ($this->id_privilege ?? 0) === 100 && $log_mode === 1) {
    $lastActive = (int) ($_SESSION['admin_mode_last_active'] ?? 0);
    if ($lastActive > 0 && (time() - $lastActive) > $adminIdleLimitSec) {
        $_SESSION['log_mode'] = 0;
        unset($_SESSION['admin_mode_last_active']);
        $log_mode = 0;
    } else {
        $_SESSION['admin_mode_last_active'] = time();
    }
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

<?php
$isTrainingUi = !empty($this->isTrainingMode);
$privUi = (int) ($this->id_privilege ?? 0);
$bodyPrivClass = '';
if ($privUi === 100) {
    $bodyPrivClass = ' mode-priv-100';
} elseif ($privUi === 12) {
    $bodyPrivClass = ' mode-priv-12';
}
?>
<body class="hold-transition<?= $isTrainingUi ? ' mode-training' : '' ?><?= $bodyPrivClass ?>">
    <div class="loaderDiv" style="display: none;">
        <div class="loader"></div>
    </div>
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand mdl-topbar sticky-top">
            <div class="mdl-topbar-row">
                <a href="#" id="menu_utama" class="mdl-tbtn mdl-tbtn--menu" role="button" title="Tutup / buka menu">
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
                                $alamat = substr($alamat, 0, 28) . '?';
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
        <?php } ?>

        <?php if ($this->id_privilege == 100 || $this->id_privilege == 12) {
            $userSwitchList = [];
            $currentUserId = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
            foreach ($this->user as $a) {
                if ((int) $a['id_user'] === $currentUserId) {
                    continue;
                }
                // Priv 12 (kurir): hanya boleh ganti ke user biasa (bukan admin / bukan priv 12)
                if ((int) $this->id_privilege === 12) {
                    $targetPriv = (int) ($a['id_privilege'] ?? 0);
                    if ($targetPriv === 100 || $targetPriv === 12) {
                        continue;
                    }
                }
                $userSwitchList[] = $a;
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

        <div class="drv-modal" id="drvCartBlockModal" aria-hidden="true">
            <div class="drv-modal__backdrop" data-drv-close></div>
            <div class="drv-modal__panel" role="dialog" aria-modal="true" aria-labelledby="drvCartBlockTitle">
                <div class="drv-modal__head">
                    <h3 id="drvCartBlockTitle">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Tidak diizinkan</span>
                    </h3>
                    <button type="button" class="drv-modal__close" data-drv-close aria-label="Tutup">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="drv-modal__body">
                    <p>
                        Driver tidak dapat membuka order baru, silahkan gunakan fitur ganti user pada tombol
                        <span class="drv-modal__icon-btn" title="Ganti user login" aria-hidden="true"><i class="fas fa-exchange-alt"></i></span>
                        di pojok kiri atas
                    </p>
                </div>
                <div class="drv-modal__foot">
                    <button type="button" class="drv-modal__ok" data-drv-close>OK</button>
                </div>
            </div>
        </div>

        <aside class="main-sidebar sidebar-dark-yellow shadow-sm position-fixed">
            <div class="sidebar px-0">
                <div class="mdl-side-card">
                    <div class="mdl-side-user">
                        <span class="mdl-side-user-btn" title="<?= htmlspecialchars((string) $this->nama_user) ?>">
                            <i class="fas fa-user-circle"></i>
                            <span><?= htmlspecialchars((string) $this->nama_user) ?></span>
                        </span>
                        <?php if ($this->id_privilege == 100 || $this->id_privilege == 12) { ?>
                        <button type="button" id="btnPilihUser" class="mdl-side-switch-user" title="Ganti user login">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                        <?php } ?>
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
        <div class="content-wrapper px-2 pt-2" style="min-width: 0; max-width: 100vw;">
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
                        body.classList.remove('sidebar-open');
                    }

                    function toggleSidebarMenu(event) {
                        if (event) {
                            event.preventDefault();
                        }
                        if (isSidebarMenuVisible()) {
                            closeSidebarMenu();
                        } else {
                            openSidebarMenu();
                        }
                    }

                    var menuUtama = document.getElementById('menu_utama');
                    if (menuUtama) {
                        menuUtama.addEventListener('click', toggleSidebarMenu);
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

                    var applyModeUi = function(mode) {
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
                        if (window.MDL_adminIdle) {
                            window.MDL_adminIdle.setMode(mode);
                        }
                    };

                    var postMode = function(key) {
                        $.ajax({
                            url: "<?= URL::BASE_URL ?>Login/log_mode",
                            data: { mode: mode, key: key || '' },
                            type: "POST",
                            dataType: "json",
                            success: function(res) {
                                if (res && res.ok == 1) {
                                    applyModeUi(mode);
                                    return;
                                }
                                alert((res && res.msg) || 'Gagal ganti mode');
                            },
                            error: function() {
                                alert('Gagal ganti mode');
                            }
                        });
                    };

                    if (mode === 1) {
                        var key = window.prompt('Masukkan Admin Key (4 digit):');
                        if (key === null) {
                            return;
                        }
                        key = String(key).trim();
                        if (!/^\d{4}$/.test(key)) {
                            alert('Key harus 4 digit angka');
                            return;
                        }
                        postMode(key);
                    } else {
                        postMode('');
                    }
                });

                <?php if ((int) ($this->id_privilege ?? 0) === 100) { ?>
                (function () {
                    var IDLE_MS = <?= (int) $adminIdleLimitSec ?> * 1000;
                    var PING_MS = 30000;
                    var mode = <?= (int) $log_mode ?>;
                    var idleTimer = null;
                    var lastPing = 0;

                    function applyKasirUi() {
                        $("#roleSwitch .role-btn").removeClass("is-active");
                        $('#roleSwitch .role-btn[data-mode="0"]').addClass("is-active");
                        $("#nav_0").removeClass("d-none");
                        $("#nav_2").removeClass("d-none");
                        $("#nav_1").addClass("d-none");
                        $("#nav_3").addClass("d-none");
                    }

                    function forceKasir(showAlert) {
                        mode = 0;
                        clearTimeout(idleTimer);
                        idleTimer = null;
                        $.ajax({
                            url: "<?= URL::BASE_URL ?>Login/log_mode",
                            data: { mode: 0 },
                            type: "POST",
                            dataType: "json",
                            complete: function () {
                                applyKasirUi();
                                if (showAlert) {
                                    alert('Mode Admin berakhir karena idle 10 menit. Kembali ke Kasir.');
                                }
                            }
                        });
                    }

                    function resetIdle() {
                        if (mode !== 1) return;
                        clearTimeout(idleTimer);
                        idleTimer = setTimeout(function () {
                            forceKasir(true);
                        }, IDLE_MS);

                        var now = Date.now();
                        if (now - lastPing >= PING_MS) {
                            lastPing = now;
                            $.ajax({
                                url: "<?= URL::BASE_URL ?>Login/admin_mode_ping",
                                type: "POST",
                                dataType: "json",
                                success: function (res) {
                                    if (res && res.expired == 1) {
                                        mode = 0;
                                        clearTimeout(idleTimer);
                                        applyKasirUi();
                                        alert('Mode Admin berakhir karena idle 10 menit. Kembali ke Kasir.');
                                    }
                                }
                            });
                        }
                    }

                    window.MDL_adminIdle = {
                        setMode: function (m) {
                            mode = parseInt(m, 10) || 0;
                            if (mode === 1) {
                                lastPing = 0;
                                resetIdle();
                            } else {
                                clearTimeout(idleTimer);
                                idleTimer = null;
                            }
                        }
                    };

                    if (mode === 1) {
                        resetIdle();
                    }

                    $(document).on('mousemove keydown click scroll touchstart', function () {
                        resetIdle();
                    });
                })();
                <?php } ?>

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
                    var $btn = $("#btnPilihCabang");
                    var btnIdleHtml = $btn.html();

                    function openCabangModal() {
                        if ($btn.hasClass("is-loading")) return;
                        $modal.addClass("is-open").attr("aria-hidden", "false");
                    }
                    function closeCabangModal() {
                        $modal.removeClass("is-open").attr("aria-hidden", "true");
                    }
                    function setCabangBtnLoading(on) {
                        if (!$btn.length) return;
                        if (on) {
                            $btn.addClass("is-loading").prop("disabled", true)
                                .attr("title", "Memuat cabang…")
                                .html('<span class="mdl-cabang-spin" aria-hidden="true"></span>');
                        } else {
                            $btn.removeClass("is-loading").prop("disabled", false)
                                .attr("title", "Pilih cabang")
                                .html(btnIdleHtml);
                        }
                    }
                    function switchCabang(idCabang) {
                        $.ajax({
                            url: '<?= URL::BASE_URL ?>Cabang_List/selectCabang',
                            data: { id: idCabang },
                            type: "POST",
                            beforeSend: function() {
                                closeCabangModal();
                                setCabangBtnLoading(true);
                            },
                            success: function() {
                                location.reload(true);
                            },
                            error: function(xhr) {
                                setCabangBtnLoading(false);
                                alert("Gagal pindah cabang: " + (xhr.responseText || xhr.status));
                            }
                        });
                    }

                    $btn.on("click", function(e) {
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

                (function initDriverCartBlock() {
                    window.MDL_PRIVILEGE = <?= (int) ($this->id_privilege ?? 0) ?>;
                    var $modal = $("#drvCartBlockModal");

                    function openDrvModal() {
                        if (!$modal.length) return;
                        $modal.addClass("is-open").attr("aria-hidden", "false");
                    }
                    function closeDrvModal() {
                        $modal.removeClass("is-open").attr("aria-hidden", "true");
                    }

                    /**
                     * @returns {boolean} true jika aksi harus dihentikan (driver)
                     * Dinonaktifkan: priv 12 boleh membuka order.
                     */
                    window.blockDriverNewOrder = function () {
                        return false;
                    };

                    $modal.on("click", "[data-drv-close]", function () {
                        closeDrvModal();
                    });
                    $(document).on("keydown.drvCart", function (e) {
                        if (e.key === "Escape" && $modal.hasClass("is-open")) {
                            closeDrvModal();
                        }
                    });
                })();

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

                (function initMdlToast() {
                    var host = document.getElementById("mdlToastHost");
                    if (!host) {
                        host = document.createElement("div");
                        host.id = "mdlToastHost";
                        host.className = "mdl-toast-host";
                        host.setAttribute("aria-live", "polite");
                        document.body.appendChild(host);
                    }

                    var ICONS = {
                        ok: "fa-check",
                        success: "fa-check",
                        warn: "fa-exclamation-triangle",
                        warning: "fa-exclamation-triangle",
                        error: "fa-times",
                        danger: "fa-times",
                        info: "fa-info-circle"
                    };
                    var CLASS = {
                        ok: "mdl-toast--ok",
                        success: "mdl-toast--ok",
                        warn: "mdl-toast--warn",
                        warning: "mdl-toast--warn",
                        error: "mdl-toast--error",
                        danger: "mdl-toast--error",
                        info: "mdl-toast--info"
                    };

                    function normalizeType(type) {
                        type = String(type || "info").toLowerCase();
                        if (type === "success") return "ok";
                        if (type === "warning") return "warn";
                        if (type === "danger") return "error";
                        if (type === "ok" || type === "warn" || type === "error" || type === "info") return type;
                        return "info";
                    }

                    function show(message, type, opts) {
                        opts = opts || {};
                        var t = normalizeType(type);
                        var delay = opts.delay != null ? opts.delay : (t === "error" ? 4000 : 2800);
                        var el = document.createElement("div");
                        el.className = "mdl-toast " + (CLASS[t] || CLASS.info);
                        el.setAttribute("role", t === "error" ? "alert" : "status");
                        el.innerHTML =
                            '<span class="mdl-toast__icon"><i class="fas ' + (ICONS[t] || ICONS.info) + '"></i></span>' +
                            '<div class="mdl-toast__msg"></div>' +
                            '<button type="button" class="mdl-toast__close" aria-label="Tutup">&times;</button>';
                        el.querySelector(".mdl-toast__msg").textContent = String(message || "");
                        host.appendChild(el);
                        requestAnimationFrame(function() { el.classList.add("is-show"); });

                        var timer = null;
                        function dismiss() {
                            if (timer) clearTimeout(timer);
                            el.classList.remove("is-show");
                            setTimeout(function() {
                                if (el.parentNode) el.parentNode.removeChild(el);
                            }, 200);
                        }
                        el.querySelector(".mdl-toast__close").addEventListener("click", dismiss);
                        if (delay > 0) timer = setTimeout(dismiss, delay);

                        while (host.children.length > 3) {
                            host.removeChild(host.firstChild);
                        }
                        return { dismiss: dismiss };
                    }

                    window.MdlToast = {
                        show: show,
                        ok: function(msg, opts) { return show(msg, "ok", opts); },
                        warn: function(msg, opts) { return show(msg, "warn", opts); },
                        error: function(msg, opts) { return show(msg, "error", opts); },
                        info: function(msg, opts) { return show(msg, "info", opts); }
                    };
                })();
            </script>
<?php require_once __DIR__ . '/pwa_register.php'; ?>
