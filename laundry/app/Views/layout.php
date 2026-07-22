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

        /* Header warna nota Antrian/Operasi — tema MDL (bukan Bootstrap) */
        .content-wrapper tr.mdl-nota-today > td,
        .content-wrapper tr.mdl-nota-past > td,
        .content-wrapper tr.mdl-nota-member > td,
        .content-wrapper .mdl-nota-head.mdl-nota-today,
        .content-wrapper .mdl-nota-head.mdl-nota-past,
        .content-wrapper .mdl-nota-head.mdl-nota-member {
            border-color: transparent !important;
            color: #0d1117 !important;
            font-weight: 700;
            vertical-align: middle;
        }
        .content-wrapper tr.mdl-nota-today > td,
        .content-wrapper .mdl-nota-head.mdl-nota-today {
            background: linear-gradient(to bottom, #9ec0f0 0%, #b6d0f5 45%, #cfe0f8 100%) !important;
            box-shadow: inset 0 -1px 0 rgba(47, 97, 188, 0.35);
        }
        .content-wrapper tr.mdl-nota-past > td,
        .content-wrapper .mdl-nota-head.mdl-nota-past {
            background: linear-gradient(to bottom, #8fd4ad 0%, #a8dfbf 45%, #c5ebd4 100%) !important;
            box-shadow: inset 0 -1px 0 rgba(38, 135, 80, 0.35);
        }
        .content-wrapper tr.mdl-nota-member > td,
        .content-wrapper .mdl-nota-head.mdl-nota-member {
            background: linear-gradient(to bottom, #8eb8ef 0%, #a9caf4 45%, #c5ddf8 100%) !important;
            box-shadow: inset 0 -1px 0 rgba(47, 97, 188, 0.32);
        }
        .content-wrapper tr.mdl-nota-today a,
        .content-wrapper tr.mdl-nota-past a,
        .content-wrapper tr.mdl-nota-member a,
        .content-wrapper .mdl-nota-head a {
            color: #0d1117 !important;
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
            color: #0d1117 !important;
            font-weight: 700 !important;
        }

        /* Head nota Operasi — padding & alignment seimbang */
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
            color: #0d1117;
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
            font-weight: 700;
            color: #0d1117;
            white-space: nowrap;
            line-height: 1.2;
            margin-left: auto;
            text-align: right;
        }
        .content-wrapper .mdl-nota-head__right i {
            opacity: 1;
            color: #0d1117;
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
            color: #1e3a5f;
            background: #fff;
        }
        .content-wrapper .mdl-nota-chip i {
            font-size: 0.95em;
            line-height: 1;
        }
        .content-wrapper .mdl-nota-chip:hover {
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(36, 48, 65, 0.12);
        }
        .content-wrapper .mdl-nota-chip--icon {
            padding: 0;
            width: 24px;
            height: 24px;
            min-width: 24px;
        }
        .content-wrapper .mdl-nota-chip--wa {
            color: #062e22;
            background: linear-gradient(180deg, #e8faf2 0%, #d4f3e5 100%);
            border-color: rgba(18, 140, 126, 0.22);
        }
        .content-wrapper .mdl-nota-chip--wa i {
            color: #0a7a68;
        }
        .content-wrapper .mdl-nota-chip--wa:hover {
            background: linear-gradient(180deg, #dff7eb 0%, #c5edd9 100%);
            color: #062e22;
        }
        .content-wrapper .mdl-nota-chip--pending {
            color: #3d2a00;
            background: linear-gradient(180deg, #fff8e8 0%, #ffefc8 100%);
            border-color: rgba(201, 148, 20, 0.28);
        }
        .content-wrapper .mdl-nota-chip--pending i {
            color: #a67a00;
        }
        .content-wrapper .mdl-nota-chip--ok {
            color: #0d3d22;
            background: linear-gradient(180deg, #eaf8ef 0%, #d4efde 100%);
            border-color: rgba(38, 135, 80, 0.25);
        }
        .content-wrapper .mdl-nota-chip--ok i {
            color: #1f7a45;
        }
        .content-wrapper .mdl-nota-chip--label {
            color: #3a2808;
            background: linear-gradient(180deg, #fff6e9 0%, #ffe8c8 100%);
            border-color: rgba(196, 130, 40, 0.28);
        }
        .content-wrapper .mdl-nota-chip--label i {
            color: #b87410;
        }
        .content-wrapper .mdl-nota-chip--label:hover {
            color: #3a2808;
            background: linear-gradient(180deg, #ffefd6 0%, #ffdfb0 100%);
        }
        .content-wrapper .mdl-nota-chip--add {
            color: #0f2d5c;
            background: linear-gradient(180deg, #eaf2fc 0%, #d6e6f8 100%);
            border-color: rgba(47, 97, 188, 0.28);
        }
        .content-wrapper .mdl-nota-chip--add i {
            color: #1f4fa0;
        }
        .content-wrapper .mdl-nota-chip--add:hover {
            color: #0f2d5c;
            background: linear-gradient(180deg, #ddebfa 0%, #c7dbf4 100%);
        }
        .content-wrapper .mdl-nota-chip--bill {
            color: #1f1848;
            background: linear-gradient(180deg, #f0edfb 0%, #e2dcf6 100%);
            border-color: rgba(90, 70, 170, 0.25);
        }
        .content-wrapper .mdl-nota-chip--bill i {
            color: #4d3aa8;
        }
        .content-wrapper .mdl-nota-chip--bill:hover {
            color: #1f1848;
            background: linear-gradient(180deg, #e7e2f8 0%, #d6cef2 100%);
        }
        .content-wrapper .mdl-nota-card > .table {
            margin-bottom: 0 !important;
        }

        /* Grid nota Antrian/Operasi — jarak atas-bawah = kanan-kiri (8px) */
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
            background: var(--mdl-shell) !important;
        }
        body.mode-training .main-sidebar {
            background: var(--mdl-train-shell) !important;
        }

        /* ===== Soft chrome theme (topnav + sidebar) ===== */
        :root {
            --mdl-shell: #c5d0dc;
            --mdl-shell-deep: #b3bfcd;
            --mdl-surface: #f4f7fb;
            --mdl-surface-2: #e5ecf4;
            --mdl-ink: #243041;
            --mdl-ink-soft: #5a6a7c;
            --mdl-line: #b8c4d2;
            --mdl-accent: #3f74d4;
            --mdl-accent-deep: #2f61bc;
            --mdl-accent-soft: #d9e6fa;
            --mdl-live: #2f9e5f;
            --mdl-live-deep: #268750;
            --mdl-train: #e08a35;
            --mdl-train-deep: #c97422;
            --mdl-train-shell: #f0c49a;
            --mdl-train-surface: #fbebe0;
            --mdl-admin: #d45363;
            --mdl-admin-deep: #bc3f4f;
            --mdl-shadow: 0 5px 16px rgba(36, 48, 65, 0.12);
        }

        /* ===== Top toolbar ===== */
        .main-header.mdl-topbar {
            display: block;
            padding: 8px 10px !important;
            background: var(--mdl-shell) !important;
            background-image: none !important;
            border-bottom: 1px solid var(--mdl-line);
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
            border-radius: 0;
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
            border: 1.5px solid var(--mdl-line);
            background: var(--mdl-surface);
            color: var(--mdl-ink);
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        .mdl-tbtn:hover {
            background: #fff;
            border-color: #c0cad6;
            color: #2c3642;
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
            border-color: #b7d4c2;
            color: var(--mdl-live-deep);
            background: #eef6f1;
        }
        .mdl-tbtn--refresh:hover {
            background: #e3f0e8;
            border-color: #9fc4ad;
            color: #4d8666;
        }
        .mdl-tbtn--logout {
            border-color: var(--mdl-line);
            color: var(--mdl-ink-soft);
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
            box-shadow: 0 0 0 3px rgba(107, 143, 212, 0.22);
        }
        .mdl-tbtn--cabang {
            min-width: 52px;
            padding: 0 12px;
            border: 1.5px solid transparent;
            background: var(--mdl-accent);
            color: #fff;
            gap: 6px;
        }
        .mdl-tbtn--cabang:hover {
            background: var(--mdl-accent-deep);
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
            opacity: 0.85;
        }

        .mode-switch {
            display: inline-flex;
            align-items: stretch;
            overflow: hidden;
            padding: 3px;
            gap: 2px;
            border: 1.5px solid var(--mdl-line);
            background: var(--mdl-shell-deep);
            box-shadow: inset 0 1px 2px rgba(58, 69, 83, 0.05);
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
            color: var(--mdl-ink-soft);
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.02em;
            cursor: pointer;
            user-select: none;
            transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        }
        .mode-switch .mode-btn:hover:not(.active-live):not(.active-training) {
            color: var(--mdl-ink);
            background: rgba(255,255,255,.45);
        }
        .mode-switch .mode-btn.active-live {
            background: var(--mdl-live);
            color: #fff;
            box-shadow: 0 1px 2px rgba(106, 170, 134, 0.3);
        }
        .mode-switch .mode-btn.active-training {
            background: var(--mdl-train);
            color: #fff;
            box-shadow: 0 1px 2px rgba(212, 160, 106, 0.3);
        }

        .training-banner {
            background: linear-gradient(90deg, #e08a35, #f0a85a);
            color: #4a2e12;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.02em;
            text-align: center;
            padding: 7px 12px;
            border-bottom: 1px solid rgba(74, 46, 18, 0.12);
        }
        body.mode-training .main-header.mdl-topbar {
            background: var(--mdl-train-shell) !important;
            border-bottom-color: #e0a86a;
        }
        body.mode-training .mode-switch {
            border-color: #e0a86a;
            background: #e8b888;
        }
        .mdl-training-chip {
            display: inline-flex;
            align-items: center;
            height: 34px;
            padding: 0 12px;
            border-radius: 0;
            background: #9a5a20;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        /* ===== Sidebar profile card ===== */
        .mdl-side-card {
            margin: 10px 10px 8px;
            padding: 12px;
            border-radius: 0;
            background: var(--mdl-surface);
            border: 1px solid rgba(255,255,255,.55);
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
            border: 1.5px solid var(--mdl-line);
            background: #fff;
            color: var(--mdl-ink);
            font-family: 'fontku', sans-serif;
            font-size: 13px;
            font-weight: 800;
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
            border: 1.5px solid transparent;
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
        .mdl-side-wifi {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 28px;
            padding: 0 4px;
            margin-bottom: 10px;
            color: var(--mdl-ink-soft);
            font-size: 12px;
            font-weight: 700;
        }
        .mdl-side-wifi i {
            color: #8a96a3;
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
            border: 1.5px solid var(--mdl-line);
            background: var(--mdl-surface-2);
            box-shadow: inset 0 1px 2px rgba(58, 69, 83, 0.04);
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
            font-weight: 800;
            cursor: pointer;
            transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        }
        .role-switch .role-btn:hover:not(.is-active) {
            color: var(--mdl-ink);
            background: rgba(255,255,255,.55);
        }
        .role-switch .role-btn.is-active[data-mode="0"] {
            background: var(--mdl-live);
            color: #fff;
            box-shadow: 0 1px 2px rgba(106, 170, 134, 0.28);
        }
        .role-switch .role-btn.is-active[data-mode="1"] {
            background: var(--mdl-admin);
            color: #fff;
            box-shadow: 0 1px 2px rgba(196, 135, 143, 0.28);
        }

        /* ===== Sidebar menu card ===== */
        .mdl-side-nav {
            margin: 0 10px 12px;
            padding: 8px;
            border-radius: 0;
            background: var(--mdl-surface);
            border: 1px solid rgba(255,255,255,.55);
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
            scrollbar-color: #b8c4d0 transparent;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 99px;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-thumb {
            background: #c5d0db;
            border-radius: 99px;
        }
        .mdl-side-nav-scroll::-webkit-scrollbar-thumb:hover {
            background: #aebccd;
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
            border: 1.5px solid transparent;
            background: transparent !important;
            color: var(--mdl-ink) !important;
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
            color: var(--mdl-ink-soft) !important;
        }
        .mdl-side-nav .nav-link:hover {
            background: var(--mdl-surface-2) !important;
            color: #2c3642 !important;
            border-color: var(--mdl-line);
        }
        .mdl-side-nav .nav-link:hover > .nav-icon,
        .mdl-side-nav .nav-link:hover > i.nav-icon {
            color: var(--mdl-accent) !important;
        }
        .mdl-side-nav .nav-link.active {
            background: var(--mdl-accent) !important;
            border-color: var(--mdl-accent) !important;
            color: #fff !important;
            box-shadow: 0 3px 8px rgba(107, 143, 212, 0.25);
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
            background: var(--mdl-surface-2) !important;
            color: var(--mdl-ink) !important;
            border-color: var(--mdl-line);
        }
        .mdl-side-nav .nav-treeview {
            margin: 4px 0 6px !important;
            padding: 4px !important;
            border-radius: 0;
            background: var(--mdl-surface-2);
        }
        .mdl-side-nav .nav-treeview .nav-item {
            margin: 0 0 2px;
        }
        .mdl-side-nav .nav-treeview .nav-link {
            min-height: 34px;
            padding: 6px 10px 6px 12px !important;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--mdl-ink-soft) !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .mdl-side-nav .nav-treeview .nav-link > .nav-icon,
        .mdl-side-nav .nav-treeview .nav-link > i.nav-icon {
            font-size: 8px !important;
            color: #a0aab6 !important;
        }
        .mdl-side-nav .nav-treeview .nav-link:hover {
            background: #fff !important;
            color: var(--mdl-ink) !important;
            border-color: var(--mdl-line);
        }
        .mdl-side-nav .nav-treeview .nav-link.active {
            background: #fff !important;
            border-color: #c5d4ec !important;
            color: var(--mdl-accent-deep) !important;
            box-shadow: 0 1px 4px rgba(107, 143, 212, 0.1) !important;
        }
        .mdl-side-nav .nav-treeview .nav-link.active > .nav-icon,
        .mdl-side-nav .nav-treeview .nav-link.active > i.nav-icon {
            color: var(--mdl-accent) !important;
        }
        .mdl-side-nav .nav-treeview .nav-link p b {
            font-weight: 700;
        }

        /* Soft modal accents */
        .mdl-cmodal__head {
            background: linear-gradient(135deg, #3f74d4, #5b8de0) !important;
        }
        .mdl-cmodal--user .mdl-cmodal__head {
            background: linear-gradient(135deg, #2f9e5f, #45b575) !important;
        }
        .mdl-cmodal__item.is-active {
            border-color: var(--mdl-accent) !important;
            background: linear-gradient(180deg, #d9e6fa, #eef4fc) !important;
            box-shadow: 0 0 0 2px rgba(63, 116, 212, 0.22) !important;
        }
        .mdl-cmodal--user .mdl-cmodal__item.is-active {
            border-color: var(--mdl-live) !important;
            background: linear-gradient(180deg, #d6f0e1, #eaf8f0) !important;
            box-shadow: 0 0 0 2px rgba(47, 158, 95, 0.22) !important;
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
            border: 1.5px solid #e2e8f0;
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
                        <?php if ($this->id_privilege == 100) { ?>
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
