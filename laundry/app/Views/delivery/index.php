<?php
$transfers = $data['transfers'] ?? [];
$customers = $data['customers'] ?? [];
$customerRequests = $data['customerRequests'] ?? [];
$canCekDetail = !empty($data['canCekDetail']);
$isEmptyCabang = empty($transfers);
$isEmptyCustomer = empty($customers) && empty($customerRequests);
?>
<div id="dlv-root" class="px-1 mt-2"
     data-can-cek="<?= $canCekDetail ? '1' : '0' ?>"
     data-detail-url="<?= URL::BASE_URL ?>Delivery/detail/"
     data-customer-detail-url="<?= URL::BASE_URL ?>Delivery/customer_detail/"
     data-sales-options-url="<?= URL::BASE_URL ?>Delivery/sales_options/"
     data-selesai-url="<?= URL::BASE_URL ?>Delivery/selesai_customer"
     data-batal-url="<?= URL::BASE_URL ?>Delivery/batal_customer"
     data-selesai-request-url="<?= URL::BASE_URL ?>Delivery/selesai_request"
     data-batal-request-url="<?= URL::BASE_URL ?>Delivery/batal_request"
     data-terima-pakai-url="<?= URL::BASE_URL ?>Delivery/terima_pakai">
  <style>
    #dlv-root {
      --dlv-ink: #0f172a;
      --dlv-muted: #1e293b;
      --dlv-line: #cbd5e1;
      --dlv-blue: #2563eb;
      --dlv-blue-deep: #1d4ed8;
      --dlv-yellow: #f59e0b;
      --dlv-yellow-deep: #d97706;
      --dlv-green: #16a34a;
      --dlv-green-deep: #15803d;
      --dlv-radius: 0;
      --dlv-border: 1px;
      width: 100%;
      margin: 0 0 24px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
      color: var(--dlv-ink);
    }
    #dlv-root,
    #dlv-root .dlv-panel,
    #dlv-root .dlv-icon,
    #dlv-root .dlv-btn,
    #dlv-root .dlv-input,
    #dlv-root .op-modal__panel,
    #dlv-root .op-modal__close {
      border-radius: 0 !important;
    }
    #dlv-root .dlv-panel {
      min-width: 0;
      height: 100%;
      display: flex;
      flex-direction: column;
      background: #fff;
      border: var(--dlv-border) solid var(--dlv-line);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    #dlv-root .dlv-panel--customer {
      background: linear-gradient(180deg, #eff6ff, #fff);
      border-color: #93c5fd;
    }
    #dlv-root .dlv-panel--cabang {
      background: linear-gradient(180deg, #fffbeb, #fff);
      border-color: #fcd34d;
    }
    #dlv-root .dlv-head {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      color: #fff;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0, 0, 0, 0.18);
    }
    #dlv-root .dlv-panel--customer .dlv-head {
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    }
    #dlv-root .dlv-panel--cabang .dlv-head {
      background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
    }
    #dlv-root .dlv-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      flex-shrink: 0;
      background: rgba(255, 255, 255, 0.22);
      border: 1px solid rgba(255, 255, 255, 0.35);
      color: #fff;
      font-size: 0.9rem;
    }
    #dlv-root .dlv-head h2 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      line-height: 1.2;
    }
    #dlv-root .dlv-head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      opacity: 0.92;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #dlv-root .dlv-body {
      flex: 1;
      padding: 14px;
      min-height: 220px;
      max-height: min(70vh, 640px);
      overflow: auto;
    }
    #dlv-root .dlv-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 180px;
      padding: 18px 12px;
      border: 1px dashed #94a3b8;
      background: rgba(255, 255, 255, 0.72);
      text-align: center;
    }
    #dlv-root .dlv-empty i { font-size: 1.75rem; color: var(--dlv-muted); }
    #dlv-root .dlv-panel--customer .dlv-empty i { color: var(--dlv-blue); }
    #dlv-root .dlv-panel--cabang .dlv-empty i { color: var(--dlv-yellow-deep); }
    #dlv-root .dlv-empty strong {
      font-size: 0.88rem;
      font-weight: 900;
      color: var(--dlv-ink);
    }
    #dlv-root .dlv-empty span {
      font-size: 0.78rem;
      font-weight: 750;
      color: var(--dlv-muted);
      max-width: 260px;
    }
    #dlv-root .dlv-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    #dlv-root .dlv-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 12px;
      border: 1px solid #fcd34d;
      background: #fff;
    }
    #dlv-root .dlv-item--customer { border-color: #93c5fd; }
    #dlv-root .dlv-item__text { min-width: 0; flex: 1; }
    #dlv-root .dlv-item__title {
      margin: 0;
      font-size: 0.84rem;
      font-weight: 900;
      color: var(--dlv-ink);
      line-height: 1.35;
    }
    #dlv-root .dlv-item__title .dlv-kode { color: var(--dlv-yellow-deep); }
    #dlv-root .dlv-item--customer .dlv-item__title .dlv-kode { color: var(--dlv-blue-deep); }
    #dlv-root .dlv-item__meta {
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--dlv-muted);
    }
    #dlv-root .dlv-item__actions {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      justify-content: flex-end;
      flex-shrink: 0;
    }
    #dlv-root .dlv-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      flex-shrink: 0;
      padding: 8px 12px;
      border: 1px solid transparent;
      font-size: 0.78rem;
      font-weight: 900;
      cursor: pointer;
      white-space: nowrap;
    }
    #dlv-root .dlv-btn--cek {
      background: linear-gradient(180deg, var(--dlv-blue), var(--dlv-blue-deep));
      color: #fff;
    }
    #dlv-root .dlv-btn--submit {
      background: linear-gradient(180deg, var(--dlv-green), var(--dlv-green-deep));
      color: #fff;
    }
    #dlv-root .dlv-btn--selesai {
      background: linear-gradient(180deg, var(--dlv-green), var(--dlv-green-deep));
      color: #fff;
    }
    #dlv-root .dlv-btn--batal {
      background: linear-gradient(180deg, #ef4444, #b91c1c);
      color: #fff;
    }
    #dlv-root .dlv-btn--pakai {
      background: linear-gradient(135deg, #ff6b35 0%, #f7931e 50%, #ffc107 100%);
      color: #fff;
    }
    #dlv-root .dlv-btn:disabled { opacity: 0.55; cursor: wait; }
    #dlv-root .dlv-btn--ghost {
      background: #e2e8f0;
      color: var(--dlv-ink);
      border-color: #cbd5e1;
    }
    #dlv-root .op-modal__panel--selesai { width: min(520px, 100%); }
    #dlv-root .dlv-sales-box {
      margin-top: 4px;
      max-height: min(42vh, 360px);
      overflow: auto;
      border: 1px solid var(--dlv-line);
      background: #fff;
    }
    #dlv-root .dlv-sales-group {
      border-bottom: 1px solid #e2e8f0;
    }
    #dlv-root .dlv-sales-group:last-child { border-bottom: 0; }
    #dlv-root .dlv-sales-group__head {
      padding: 8px 10px;
      font-size: 0.72rem;
      font-weight: 900;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      color: var(--dlv-muted);
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
    }
    #dlv-root .dlv-sales-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 8px 10px;
      border-bottom: 1px dashed #e2e8f0;
      cursor: pointer;
    }
    #dlv-root .dlv-sales-item:last-child { border-bottom: 0; }
    #dlv-root .dlv-sales-item:hover { background: #eff6ff; }
    #dlv-root .dlv-sales-item input {
      margin-top: 3px;
      flex-shrink: 0;
    }
    #dlv-root .dlv-sales-item__text {
      min-width: 0;
      flex: 1;
      font-size: 0.82rem;
      font-weight: 750;
      color: var(--dlv-ink);
      line-height: 1.35;
    }
    #dlv-root .dlv-sales-item__meta {
      margin-top: 2px;
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--dlv-muted);
    }
    #dlv-root .dlv-sales-empty {
      padding: 18px 12px;
      text-align: center;
      font-weight: 800;
      color: var(--dlv-muted);
    }
    #dlv-root .selectize-control { width: 100%; }
    #dlv-root .selectize-input,
    #dlv-root .selectize-dropdown {
      border-radius: 0 !important;
      border-color: var(--dlv-line);
      font-weight: 750;
    }
    #dlv-root .selectize-input.focus {
      border-color: var(--dlv-blue);
      box-shadow: none;
    }
    #dlv-root .dlv-field-label {
      display: block;
      margin-bottom: 6px;
      font-size: 0.78rem;
      font-weight: 900;
      color: var(--dlv-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #dlv-root .dlv-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #94a3b8;
      background: #fff;
      color: var(--dlv-ink);
      font-size: 0.88rem;
      font-weight: 750;
      font-family: inherit;
    }
    #dlv-root .dlv-input:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
      border-color: var(--dlv-blue);
    }
    #dlv-root .dlv-hint {
      margin: 10px 0 0;
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--dlv-muted);
    }
    #dlv-root .dlv-ref-box {
      margin-bottom: 14px;
      padding: 10px 12px;
      border: 1px solid #fcd34d;
      background: #fffbeb;
      text-align: center;
    }
    #dlv-root .dlv-ref-box small {
      display: block;
      font-size: 0.7rem;
      font-weight: 800;
      color: var(--dlv-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #dlv-root .dlv-ref-box strong {
      font-size: 0.95rem;
      font-weight: 900;
      color: var(--dlv-ink);
    }
    #dlv-root .op-modal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1200;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #dlv-root .op-modal.is-open { display: flex; }
    #dlv-root .op-modal__backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      cursor: pointer;
    }
    #dlv-root .op-modal__panel {
      position: relative;
      z-index: 1;
      width: min(520px, 100%);
      max-height: min(86vh, 720px);
      display: flex;
      flex-direction: column;
      background: #fff;
      border: 1px solid #cbd5e1;
      box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
      overflow: hidden;
    }
    #dlv-root .op-modal__panel--sm { width: min(420px, 100%); }
    #dlv-root .op-modal__panel--chat { width: min(560px, 100%); }
    #dlv-root .op-modal--confirm {
      z-index: 1300;
    }
    #dlv-root .op-modal__head--red {
      background: linear-gradient(105deg, #b91c1c 0%, #ef4444 100%);
    }
    #dlv-root .op-modal__head--pakai {
      background: linear-gradient(105deg, #ea580c 0%, #f59e0b 100%);
    }
    #dlv-root .op-modal__confirm-msg {
      margin: 0;
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--dlv-ink);
      line-height: 1.45;
      text-align: center;
    }
    #dlv-root .op-modal__head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
      padding: 14px 16px;
      color: #fff;
      background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
    }
    #dlv-root .op-modal__head--blue {
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    }
    #dlv-root .op-modal__head h3 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0, 0, 0, 0.18);
    }
    #dlv-root .op-modal__head small {
      display: block;
      margin-top: 3px;
      font-size: 0.72rem;
      font-weight: 700;
      opacity: 0.92;
    }
    #dlv-root .op-modal__close {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border: 1px solid rgba(255, 255, 255, 0.35);
      background: rgba(255, 255, 255, 0.18);
      color: #fff;
      cursor: pointer;
      flex-shrink: 0;
    }
    #dlv-root .op-modal__close:hover { background: rgba(255, 255, 255, 0.32); }
    #dlv-root .op-modal__body {
      flex: 1;
      overflow: auto;
      padding: 14px;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(245,158,11,.10), transparent 50%),
        linear-gradient(180deg, #fffbeb 0%, #fff 100%);
    }
    #dlv-root .op-modal__body--chat {
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.10), transparent 50%),
        linear-gradient(180deg, #eff6ff 0%, #fff 100%);
    }
    #dlv-root .op-modal__foot {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding: 12px 14px;
      border-top: 1px solid #cbd5e1;
      background: #fff;
    }
    #dlv-root .op-modal__foot--selesai {
      justify-content: space-between;
      align-items: center;
    }
    #dlv-root .op-modal__foot-right {
      display: flex;
      gap: 8px;
      margin-left: auto;
    }
    #dlv-root .dlv-detail-table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border: 1px solid #cbd5e1;
    }
    #dlv-root .dlv-detail-table th,
    #dlv-root .dlv-detail-table td {
      padding: 8px 10px;
      border-bottom: 1px solid #e2e8f0;
      font-size: 0.82rem;
      vertical-align: top;
    }
    #dlv-root .dlv-detail-table th {
      font-weight: 900;
      color: var(--dlv-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
      font-size: 0.7rem;
      background: #f8fafc;
    }
    #dlv-root .dlv-detail-table td {
      font-weight: 750;
      color: var(--dlv-ink);
    }
    #dlv-root .dlv-detail-table .dlv-desc {
      display: block;
      margin-top: 2px;
      font-size: 0.7rem;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
    }
    #dlv-root .dlv-detail-table tfoot td {
      font-weight: 900;
      border-bottom: 0;
      background: #fffbeb;
    }
    #dlv-root .dlv-chat {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    #dlv-root .dlv-chat__bubble {
      max-width: 88%;
      padding: 8px 10px;
      border: 1px solid #cbd5e1;
      background: #fff;
    }
    #dlv-root .dlv-chat__bubble--me {
      align-self: flex-end;
      border-color: #93c5fd;
      background: #eff6ff;
    }
    #dlv-root .dlv-chat__bubble--customer {
      align-self: flex-start;
      border-color: #e2e8f0;
      background: #fff;
    }
    #dlv-root .dlv-chat__meta {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 4px;
      font-size: 0.68rem;
      font-weight: 800;
      color: var(--dlv-muted);
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    #dlv-root .dlv-chat__text {
      font-size: 0.84rem;
      font-weight: 400;
      color: var(--dlv-ink);
      white-space: pre-wrap;
      word-break: break-word;
      line-height: 1.45;
    }
    #dlv-root .dlv-chat__text strong { font-weight: 700; }
    #dlv-root .dlv-chat__text em { font-style: italic; }
    #dlv-root .dlv-chat__text del { text-decoration: line-through; opacity: 0.85; }
    #dlv-root .dlv-chat__text code {
      font-family: Consolas, 'Courier New', monospace;
      font-size: 0.8rem;
      padding: 0 3px;
      background: rgba(15, 23, 42, 0.06);
      border: 1px solid #e2e8f0;
    }
    #dlv-root .dlv-chat__text a {
      color: var(--dlv-blue-deep);
      font-weight: 600;
      text-decoration: underline;
      word-break: break-all;
    }
    #dlv-root .dlv-chat__img {
      display: block;
      max-width: 100%;
      max-height: 280px;
      width: auto;
      height: auto;
      object-fit: cover;
      border: 1px solid #cbd5e1;
      cursor: zoom-in;
      background: #f1f5f9;
    }
    #dlv-root .dlv-chat__img + .dlv-chat__text {
      margin-top: 6px;
    }
    #dlv-root .dlv-detail-loading,
    #dlv-root .dlv-detail-error {
      padding: 24px 12px;
      text-align: center;
      font-weight: 800;
      color: var(--dlv-muted);
    }
    #dlv-root .dlv-detail-error { color: #b91c1c; }
    #dlv-root .dlv-group {
      margin-bottom: 14px;
    }
    #dlv-root .dlv-group:last-child { margin-bottom: 0; }
    #dlv-root .dlv-group__title {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0 0 8px;
      padding: 0 2px;
      font-size: 0.78rem;
      font-weight: 900;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      color: #334155;
    }
    #dlv-root .dlv-group__title i { color: var(--dlv-yellow-deep); }
    #dlv-root .dlv-group--req .dlv-group__title i { color: var(--dlv-blue); }
    #dlv-root .dlv-jenis-pill {
      display: inline-block;
      margin-left: 6px;
      padding: 1px 6px;
      font-size: 0.68rem;
      font-weight: 900;
      letter-spacing: 0.02em;
      text-transform: uppercase;
      border: 1px solid #cbd5e1;
      background: #f8fafc;
      color: #0f172a;
      vertical-align: middle;
    }
    #dlv-root .dlv-jenis-pill--antar { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
    #dlv-root .dlv-jenis-pill--jemput { border-color: #fcd34d; background: #fffbeb; color: #b45309; }
    #dlv-root .dlv-sekalian {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 12px;
      padding: 10px 12px;
      border: 1px solid var(--dlv-line);
      background: #f8fafc;
      font-weight: 800;
      cursor: pointer;
      user-select: none;
    }
    #dlv-root .dlv-sekalian input { margin: 0; }
    #dlv-root .dlv-sekalian-wrap { margin-top: 8px; }
    #dlv-root .dlv-sekalian-wrap[hidden] { display: none !important; }
    #dlv-root .dlv-item__lokasi {
      margin-top: 4px;
      color: #0f172a;
    }
    #dlv-root .dlv-item__lokasi i {
      color: #dc2626;
      margin-right: 4px;
    }
    #dlv-root .dlv-item__lokasi a {
      color: var(--dlv-blue);
      font-weight: 800;
      text-decoration: none;
    }
    body.op-modal-open { overflow: hidden; }
  </style>

  <div class="row g-2">
    <div class="col-12 col-md-6">
      <section class="dlv-panel dlv-panel--customer" aria-label="Delivery Customer">
        <header class="dlv-head">
          <span class="dlv-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
          <div>
            <h2>Customer</h2>
            <small>Pickup / Delivery terbuka</small>
          </div>
        </header>
        <div class="dlv-body">
          <?php if ($isEmptyCustomer) { ?>
            <div class="dlv-empty">
              <i class="fas fa-motorcycle" aria-hidden="true"></i>
              <strong>Belum ada order delivery</strong>
              <span>Request CRM (case kuning) dan request Sameday dari portal customer tampil di sini.</span>
            </div>
          <?php } else { ?>
            <?php if (!empty($customers)) { ?>
              <div class="dlv-group dlv-group--crm">
                <h3 class="dlv-group__title"><i class="fas fa-headset"></i> Request by CRM</h3>
                <div class="dlv-list">
                  <?php foreach ($customers as $cu) {
                    $nama = htmlspecialchars(strtoupper((string) ($cu['nama'] ?? 'Customer')), ENT_QUOTES, 'UTF-8');
                    $tail = htmlspecialchars((string) ($cu['phone_tail'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $kode = htmlspecialchars((string) ($cu['kode_cabang'] ?? '00'), ENT_QUOTES, 'UTF-8');
                    $dateRaw = $cu['last_message_at'] ?? '';
                    $dateLbl = $dateRaw !== '' ? date('d/m/y H:i', strtotime($dateRaw)) : '-';
                  ?>
                    <div class="dlv-item dlv-item--customer" data-phone-tail="<?= $tail ?>" data-source="crm">
                      <div class="dlv-item__text">
                        <p class="dlv-item__title">
                          <?= $nama ?>
                          <span class="dlv-kode">· <?= $kode ?></span>
                        </p>
                        <div class="dlv-item__meta">
                          <?= $tail ?> · <?= htmlspecialchars($dateLbl, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                      </div>
                      <div class="dlv-item__actions">
                        <button type="button" class="dlv-btn dlv-btn--cek" data-dlv-cek-customer="<?= $tail ?>">
                          <i class="fas fa-search"></i> Cek
                        </button>
                        <button type="button" class="dlv-btn dlv-btn--selesai" data-dlv-selesai="<?= $tail ?>"
                                data-nama="<?= $nama ?>">
                          <i class="fas fa-check"></i> Selesai
                        </button>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              </div>
            <?php } ?>

            <?php if (!empty($customerRequests)) { ?>
              <div class="dlv-group dlv-group--req">
                <h3 class="dlv-group__title"><i class="fas fa-mobile-alt"></i> Request by Customer</h3>
                <div class="dlv-list">
                  <?php foreach ($customerRequests as $rq) {
                    $nama = htmlspecialchars(strtoupper((string) ($rq['nama'] ?? 'Customer')), ENT_QUOTES, 'UTF-8');
                    $tail = htmlspecialchars((string) ($rq['phone_tail'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $kode = htmlspecialchars((string) ($rq['kode_cabang'] ?? '00'), ENT_QUOTES, 'UTF-8');
                    $jenis = strtolower((string) ($rq['jenis'] ?? ''));
                    $jenisLbl = $jenis === 'antar' ? 'Antar' : ($jenis === 'jemput' ? 'Jemput' : strtoupper($jenis));
                    $idReq = (int) ($rq['id_request'] ?? 0);
                    $prefill = implode(',', array_map('intval', $rq['prefill_ids'] ?? []));
                    $dateRaw = $rq['insertTime'] ?? '';
                    $dateLbl = $dateRaw !== '' ? date('d/m/y H:i', strtotime($dateRaw)) : '-';
                    $pillClass = $jenis === 'antar' ? 'dlv-jenis-pill--antar' : 'dlv-jenis-pill--jemput';
                    $lokNama = trim((string) ($rq['lokasi_nama'] ?? ''));
                    $lokDetail = trim((string) ($rq['lokasi_detail'] ?? ''));
                    $lokLatt = $rq['lokasi_latt'] ?? null;
                    $lokLongt = $rq['lokasi_longt'] ?? null;
                    $mapsHref = '';
                    if ($lokLatt !== null && $lokLongt !== null && (float) $lokLatt != 0.0 && (float) $lokLongt != 0.0) {
                      $mapsHref = 'https://www.google.com/maps?q=' . rawurlencode(((float) $lokLatt) . ',' . ((float) $lokLongt));
                    }
                  ?>
                    <div class="dlv-item dlv-item--customer dlv-item--request"
                         data-id-request="<?= $idReq ?>"
                         data-phone-tail="<?= $tail ?>"
                         data-source="customer">
                      <div class="dlv-item__text">
                        <p class="dlv-item__title">
                          <?= $nama ?>
                          <span class="dlv-jenis-pill <?= $pillClass ?>"><?= htmlspecialchars($jenisLbl, ENT_QUOTES, 'UTF-8') ?></span>
                          <span class="dlv-kode">· <?= $kode ?></span>
                        </p>
                        <div class="dlv-item__meta">
                          #<?= $idReq ?> · <?= $tail ?> · <?= htmlspecialchars($dateLbl, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php if ($lokNama !== '' || $lokDetail !== '') { ?>
                          <div class="dlv-item__meta dlv-item__lokasi">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($lokNama !== '' ? $lokNama : 'Lokasi', ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($lokDetail !== '') { ?>
                              · <?= htmlspecialchars($lokDetail, ENT_QUOTES, 'UTF-8') ?>
                            <?php } ?>
                            <?php if ($mapsHref !== '') { ?>
                              · <a href="<?= htmlspecialchars($mapsHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Maps</a>
                            <?php } ?>
                          </div>
                        <?php } ?>
                      </div>
                      <div class="dlv-item__actions">
                        <button type="button"
                                class="dlv-btn dlv-btn--selesai"
                                data-dlv-selesai-request="<?= $idReq ?>"
                                data-phone-tail="<?= $tail ?>"
                                data-jenis="<?= htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') ?>"
                                data-prefill="<?= htmlspecialchars($prefill, ENT_QUOTES, 'UTF-8') ?>"
                                data-nama="<?= $nama ?>">
                          <i class="fas fa-check"></i> Selesai
                        </button>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              </div>
            <?php } ?>
          <?php } ?>
        </div>
      </section>
    </div>

    <div class="col-12 col-md-6">
      <section class="dlv-panel dlv-panel--cabang" aria-label="Delivery Cabang">
        <header class="dlv-head">
          <span class="dlv-icon" aria-hidden="true"><i class="fas fa-store"></i></span>
          <div>
            <h2>Cabang</h2>
            <small>Transfer barang belum diterima</small>
          </div>
        </header>
        <div class="dlv-body">
          <?php if ($isEmptyCabang) { ?>
            <div class="dlv-empty">
              <i class="fas fa-truck" aria-hidden="true"></i>
              <strong>Belum ada order delivery</strong>
              <span>Transfer barang antar cabang yang belum diterima akan tampil di sini.</span>
            </div>
          <?php } else { ?>
            <div class="dlv-list">
              <?php foreach ($transfers as $tr) {
                $ref = htmlspecialchars((string) ($tr['ref'] ?? ''), ENT_QUOTES, 'UTF-8');
                $src = htmlspecialchars((string) ($tr['source_kode'] ?? '-'), ENT_QUOTES, 'UTF-8');
                $tgt = htmlspecialchars((string) ($tr['target_kode'] ?? '-'), ENT_QUOTES, 'UTF-8');
                $sourceId = (int) ($tr['source_id'] ?? 0);
                $targetId = (int) ($tr['target_id'] ?? 0);
                $dateRaw = $tr['date'] ?? '';
                $dateLbl = $dateRaw !== '' ? date('d/m/y H:i', strtotime($dateRaw)) : '-';
                $count = (int) ($tr['item_count'] ?? 0);
              ?>
                <div class="dlv-item"
                     data-ref="<?= $ref ?>"
                     data-source-id="<?= $sourceId ?>"
                     data-target-id="<?= $targetId ?>"
                     data-source-kode="<?= $src ?>"
                     data-target-kode="<?= $tgt ?>">
                  <div class="dlv-item__text">
                    <p class="dlv-item__title">
                      Delivery <span class="dlv-kode dlv-kode-source"><?= $src ?></span>
                      → <span class="dlv-kode"><?= $tgt ?></span>
                    </p>
                    <div class="dlv-item__meta">
                      #<?= $ref ?> · <?= htmlspecialchars($dateLbl, ENT_QUOTES, 'UTF-8') ?>
                      · <?= $count ?> item
                    </div>
                  </div>
                  <div class="dlv-item__actions">
                    <button type="button" class="dlv-btn dlv-btn--cek" data-dlv-cek="<?= $ref ?>">
                      <i class="fas fa-search"></i> Cek
                    </button>
                  </div>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </section>
    </div>
  </div>

  <div class="op-modal" id="dlvCustomerModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--chat" role="dialog" aria-modal="true" aria-labelledby="dlvCustomerTitle">
      <div class="op-modal__head op-modal__head--blue">
        <div>
          <h3 id="dlvCustomerTitle">Riwayat Chat</h3>
          <small id="dlvCustomerSub">50 pesan terakhir</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body op-modal__body--chat" id="dlvCustomerBody">
        <div class="dlv-detail-loading">Memuat…</div>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Tutup</button>
      </div>
    </div>
  </div>

  <div class="op-modal" id="dlvSelesaiModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--selesai" role="dialog" aria-modal="true" aria-labelledby="dlvSelesaiTitle">
      <div class="op-modal__head op-modal__head--blue">
        <div>
          <h3 id="dlvSelesaiTitle">Selesai Delivery</h3>
          <small id="dlvSelesaiSub">Pilih jenis, karyawan + Access Key, dan item</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <form id="dlvSelesaiForm">
        <div class="op-modal__body">
          <input type="hidden" id="dlvSelesaiMode" value="crm">
          <input type="hidden" id="dlvSelesaiRequestId" name="id_request" value="">
          <input type="hidden" id="dlvSelesaiPhone" name="phone_tail" value="">
          <input type="hidden" id="dlvSelesaiPrefill" value="">
          <label class="dlv-field-label" for="dlvSelesaiJenis">Jenis</label>
          <select id="dlvSelesaiJenis" name="jenis" class="dlv-input" required>
            <option value="">— Pilih —</option>
            <option value="jemput">Jemput</option>
            <option value="antar">Antar</option>
          </select>
          <label class="dlv-field-label mt-2" for="dlvSelesaiKaryawan">Karyawan yang menyelesaikan</label>
          <select id="dlvSelesaiKaryawan" name="id_karyawan" class="form-control tize" style="width:100%" required>
            <option value="" selected disabled></option>
            <optgroup label="<?= htmlspecialchars(($this->dCabang['nama'] ?? 'Cabang') . ' [' . ($this->dCabang['kode_cabang'] ?? '') . ']', ENT_QUOTES, 'UTF-8') ?>">
              <?php foreach (($this->user ?? []) as $a) { ?>
                <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
              <?php } ?>
            </optgroup>
            <?php if (!empty($this->userCabang)) { ?>
              <optgroup label="----- Cabang Lain -----">
                <?php foreach ($this->userCabang as $a) { ?>
                  <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
                <?php } ?>
              </optgroup>
            <?php } ?>
          </select>
          <label class="dlv-field-label mt-2" for="dlvSelesaiKey">Access Key karyawan</label>
          <input type="password" id="dlvSelesaiKey" name="access_key" class="dlv-input" inputmode="numeric" maxlength="4" autocomplete="one-time-code" placeholder="4 digit" required>
          <p class="dlv-hint mt-1 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            Access Key harus milik karyawan yang dipilih.
          </p>
          <label class="dlv-field-label mt-2">Item penjualan</label>
          <div class="dlv-sales-box" id="dlvSelesaiSales">
            <div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>
          </div>

          <label class="dlv-sekalian" id="dlvSekalianRow" hidden>
            <input type="checkbox" id="dlvSekalianCheck" value="1">
            <span id="dlvSekalianLabel">Sekalian Jemput?</span>
          </label>
          <div class="dlv-sekalian-wrap" id="dlvSekalianWrap" hidden>
            <label class="dlv-field-label">Item sekalian</label>
            <div class="dlv-sales-box" id="dlvSekalianSales">
              <div class="dlv-sales-empty">Centang sekalian untuk memuat item</div>
            </div>
          </div>
        </div>
        <div class="op-modal__foot op-modal__foot--selesai">
          <button type="button" class="dlv-btn dlv-btn--batal" id="dlvSelesaiBatal">
            <i class="fas fa-ban"></i> Batalkan Delivery
          </button>
          <div class="op-modal__foot-right">
            <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Batal</button>
            <button type="submit" class="dlv-btn dlv-btn--submit" id="dlvSelesaiSubmit">
              <i class="fas fa-check"></i> Selesai
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="op-modal op-modal--confirm" id="dlvConfirmModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="dlvConfirmTitle">
      <div class="op-modal__head op-modal__head--red">
        <div>
          <h3 id="dlvConfirmTitle">Batalkan Delivery</h3>
          <small>Wajib karyawan, Access Key, dan catatan</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <p class="op-modal__confirm-msg" id="dlvConfirmMsg">
          Case akan ditutup tanpa menyimpan riwayat jemput/antar.
        </p>

        <label class="dlv-field-label mt-2" for="dlvBatalKaryawan">Karyawan yang membatalkan</label>
        <select id="dlvBatalKaryawan" class="form-control tize" style="width:100%" required>
          <option value="" selected disabled></option>
          <optgroup label="<?= htmlspecialchars(($this->dCabang['nama'] ?? 'Cabang') . ' [' . ($this->dCabang['kode_cabang'] ?? '') . ']', ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach (($this->user ?? []) as $a) { ?>
              <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } ?>
          </optgroup>
          <?php if (!empty($this->userCabang)) { ?>
            <optgroup label="----- Cabang Lain -----">
              <?php foreach ($this->userCabang as $a) { ?>
                <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
              <?php } ?>
            </optgroup>
          <?php } ?>
        </select>

        <label class="dlv-field-label mt-2" for="dlvBatalKey">Access Key karyawan</label>
        <input type="password" id="dlvBatalKey" class="dlv-input" inputmode="numeric" maxlength="4" autocomplete="one-time-code" placeholder="4 digit">

        <label class="dlv-field-label mt-2" for="dlvBatalCatatan">Catatan</label>
        <textarea id="dlvBatalCatatan" class="dlv-input" rows="3" placeholder="Alasan pembatalan" required></textarea>
        <p class="dlv-hint mt-2 mb-0">
          <i class="fas fa-info-circle me-1"></i>
          Access Key harus milik karyawan yang dipilih. Pembatalan dicatat di log.
        </p>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Tidak</button>
        <button type="button" class="dlv-btn dlv-btn--batal" id="dlvConfirmYes">
          <i class="fas fa-ban"></i> Ya, Batalkan
        </button>
      </div>
    </div>
  </div>

  <div class="op-modal" id="dlvDetailModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel" role="dialog" aria-modal="true" aria-labelledby="dlvDetailTitle">
      <div class="op-modal__head">
        <div>
          <h3 id="dlvDetailTitle">Detail Transfer</h3>
          <small id="dlvDetailSub">—</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body" id="dlvDetailBody">
        <div class="dlv-detail-loading">Memuat…</div>
      </div>
      <div class="op-modal__foot op-modal__foot--selesai">
        <button type="button" class="dlv-btn dlv-btn--pakai" id="dlvTerimaPakaiBtn" hidden>
          <i class="fas fa-bolt"></i> Terima Pakai
        </button>
        <div class="op-modal__foot-right">
          <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Tutup</button>
        </div>
      </div>
    </div>
  </div>

  <div class="op-modal op-modal--confirm" id="dlvTerimaPakaiModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="dlvTerimaPakaiTitle">
      <div class="op-modal__head op-modal__head--pakai">
        <div>
          <h3 id="dlvTerimaPakaiTitle">Terima Pakai</h3>
          <small id="dlvTerimaPakaiSub">Konfirmasi</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <p class="op-modal__confirm-msg">
          Terima dan langsung pakai semua barang dari
          <strong id="dlvTerimaPakaiSource">—</strong>?
        </p>
        <div class="dlv-ref-box mt-2">
          <small>No. Ref</small>
          <strong id="dlvTerimaPakaiRef">—</strong>
        </div>

        <label class="dlv-field-label mt-2" for="dlvTerimaPakaiKaryawan">Karyawan penerima</label>
        <select id="dlvTerimaPakaiKaryawan" class="form-control tize" style="width:100%" required>
          <option value="" selected disabled></option>
          <optgroup label="<?= htmlspecialchars(($this->dCabang['nama'] ?? 'Cabang') . ' [' . ($this->dCabang['kode_cabang'] ?? '') . ']', ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach (($this->user ?? []) as $a) { ?>
              <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } ?>
          </optgroup>
          <?php if (!empty($this->userCabang)) { ?>
            <optgroup label="----- Cabang Lain -----">
              <?php foreach ($this->userCabang as $a) { ?>
                <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
              <?php } ?>
            </optgroup>
          <?php } ?>
        </select>

        <label class="dlv-field-label mt-2" for="dlvTerimaPakaiKey">Access Key karyawan</label>
        <input type="password" id="dlvTerimaPakaiKey" class="dlv-input" inputmode="numeric" maxlength="4" autocomplete="one-time-code" placeholder="4 digit">

        <p class="dlv-hint mt-2 mb-0">
          <i class="fas fa-info-circle me-1"></i>
          Access Key harus milik karyawan yang dipilih. Barang diterima lalu status Pakai (type=3).
        </p>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Batal</button>
        <button type="button" class="dlv-btn dlv-btn--pakai" id="dlvTerimaPakaiConfirm">
          <i class="fas fa-bolt"></i> Terima Pakai
        </button>
      </div>
    </div>
  </div>

</div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
(function () {
  var root = document.getElementById('dlv-root');
  if (!root) return;

  var canCek = root.getAttribute('data-can-cek') === '1';
  var detailUrl = root.getAttribute('data-detail-url') || '';
  var customerDetailUrl = root.getAttribute('data-customer-detail-url') || '';
  var salesOptionsUrl = root.getAttribute('data-sales-options-url') || '';
  var selesaiUrl = root.getAttribute('data-selesai-url') || '';
  var batalUrl = root.getAttribute('data-batal-url') || '';
  var selesaiRequestUrl = root.getAttribute('data-selesai-request-url') || '';
  var batalRequestUrl = root.getAttribute('data-batal-request-url') || '';
  var terimaPakaiUrl = root.getAttribute('data-terima-pakai-url') || '';
  var karyawanSelectize = null;
  var terimaPakaiKaryawanSelectize = null;
  var batalKaryawanSelectize = null;
  var detailTerimaPakai = { ref: '', sourceKode: '', targetKode: '' };

  function toast(msg, type) {
    if (window.MdlToast) {
      if (type === 'error' || type === 'danger') MdlToast.error(msg);
      else if (type === 'warn' || type === 'warning') MdlToast.warn(msg);
      else if (type === 'ok' || type === 'success') MdlToast.ok(msg);
      else MdlToast.info(msg);
      return;
    }
    alert(msg);
  }

  function syncLock() {
    var n = document.querySelectorAll('#dlv-root .op-modal.is-open').length;
    if (n === 0) document.body.classList.remove('op-modal-open');
    else document.body.classList.add('op-modal-open');
  }

  function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    syncLock();
  }

  function closeModal(el) {
    var modal = typeof el === 'string' ? document.getElementById(el) : el;
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    syncLock();
  }

  function fmtQty(q) {
    var n = Number(q) || 0;
    var s = n.toFixed(1).replace('.', ',');
    return s.replace(/,0$/, '');
  }

  function fmtRp(n) {
    return 'Rp' + Math.round(Number(n) || 0).toLocaleString('id-ID');
  }

  function fmtTime(t) {
    if (!t) return '';
    var d = new Date(String(t).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(t);
    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
    return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function ensureKaryawanSelectize() {
    if (!window.jQuery || !jQuery.fn.selectize) return;
    if (!karyawanSelectize) {
      var $selesai = jQuery('#dlvSelesaiKaryawan');
      if ($selesai.length) {
        if ($selesai[0].selectize) karyawanSelectize = $selesai[0].selectize;
        else karyawanSelectize = $selesai.selectize()[0].selectize;
      }
    }
    if (!terimaPakaiKaryawanSelectize) {
      var $terima = jQuery('#dlvTerimaPakaiKaryawan');
      if ($terima.length) {
        if ($terima[0].selectize) terimaPakaiKaryawanSelectize = $terima[0].selectize;
        else terimaPakaiKaryawanSelectize = $terima.selectize()[0].selectize;
      }
    }
    if (!batalKaryawanSelectize) {
      var $batal = jQuery('#dlvBatalKaryawan');
      if ($batal.length) {
        if ($batal[0].selectize) batalKaryawanSelectize = $batal[0].selectize;
        else batalKaryawanSelectize = $batal.selectize()[0].selectize;
      }
    }
  }

  function resetSelesaiForm() {
    var jenis = document.getElementById('dlvSelesaiJenis');
    var phone = document.getElementById('dlvSelesaiPhone');
    var box = document.getElementById('dlvSelesaiSales');
    var keyEl = document.getElementById('dlvSelesaiKey');
    var modeEl = document.getElementById('dlvSelesaiMode');
    var reqEl = document.getElementById('dlvSelesaiRequestId');
    var prefillEl = document.getElementById('dlvSelesaiPrefill');
    var sekalianCheck = document.getElementById('dlvSekalianCheck');
    var sekalianRow = document.getElementById('dlvSekalianRow');
    var sekalianWrap = document.getElementById('dlvSekalianWrap');
    var sekalianSales = document.getElementById('dlvSekalianSales');
    if (jenis) {
      jenis.value = '';
      jenis.disabled = false;
    }
    if (phone) phone.value = '';
    if (keyEl) keyEl.value = '';
    if (modeEl) modeEl.value = 'crm';
    if (reqEl) reqEl.value = '';
    if (prefillEl) prefillEl.value = '';
    if (box) box.innerHTML = '<div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>';
    if (sekalianCheck) sekalianCheck.checked = false;
    if (sekalianRow) sekalianRow.hidden = true;
    if (sekalianWrap) sekalianWrap.hidden = true;
    if (sekalianSales) sekalianSales.innerHTML = '<div class="dlv-sales-empty">Centang sekalian untuk memuat item</div>';
    if (karyawanSelectize) {
      karyawanSelectize.clear(true);
    } else {
      var sel = document.getElementById('dlvSelesaiKaryawan');
      if (sel) sel.value = '';
    }
  }

  function updateSekalianUi() {
    var jenis = (document.getElementById('dlvSelesaiJenis') || {}).value || '';
    var row = document.getElementById('dlvSekalianRow');
    var label = document.getElementById('dlvSekalianLabel');
    var check = document.getElementById('dlvSekalianCheck');
    var wrap = document.getElementById('dlvSekalianWrap');
    var sales = document.getElementById('dlvSekalianSales');
    if (!row || !label) return;
    if (!jenis) {
      row.hidden = true;
      if (check) check.checked = false;
      if (wrap) wrap.hidden = true;
      return;
    }
    row.hidden = false;
    label.textContent = jenis === 'antar' ? 'Sekalian Jemput?' : 'Sekalian Antar?';
    if (check && !check.checked) {
      if (wrap) wrap.hidden = true;
      if (sales) sales.innerHTML = '<div class="dlv-sales-empty">Centang sekalian untuk memuat item</div>';
    }
  }

  function oppositeJenis(jenis) {
    return jenis === 'antar' ? 'jemput' : (jenis === 'jemput' ? 'antar' : '');
  }

  function renderSalesOptions(orders, boxEl, inputName, prefillIds) {
    var box = boxEl || document.getElementById('dlvSelesaiSales');
    if (!box) return;
    var name = inputName || 'ids[]';
    var pref = {};
    (prefillIds || []).forEach(function (id) {
      pref[String(id)] = true;
    });
    if (!orders || !orders.length) {
      box.innerHTML = '<div class="dlv-sales-empty">Tidak ada item eligible</div>';
      return;
    }
    var html = orders.map(function (ord) {
      var items = (ord.items || []).map(function (it) {
        var status = Number(it.tuntas) === 1 ? 'Tuntas' : 'Proses';
        var member = Number(it.member) === 1 ? ' · Member' : '';
        var checked = pref[String(it.id)] ? ' checked' : '';
        return '<label class="dlv-sales-item">' +
          '<input type="checkbox" name="' + name + '" value="' + escapeHtml(String(it.id)) + '"' + checked + '>' +
          '<span class="dlv-sales-item__text">' +
            escapeHtml(it.kategori || '-') +
            (it.durasi ? ' · ' + escapeHtml(it.durasi) : '') +
            ' · ' + escapeHtml(it.qty_show || '') +
            '<div class="dlv-sales-item__meta">#' + escapeHtml(String(it.id)) + ' · ' + status + member + '</div>' +
          '</span>' +
        '</label>';
      }).join('');
      return '<div class="dlv-sales-group">' +
        '<div class="dlv-sales-group__head">#' + escapeHtml(ord.no_ref || '-') +
          (ord.insertTime ? ' · ' + escapeHtml(fmtTime(ord.insertTime)) : '') +
        '</div>' + items +
      '</div>';
    }).join('');
    box.innerHTML = html;
  }

  function loadSalesOptions() {
    var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
    var jenis = (document.getElementById('dlvSelesaiJenis') || {}).value || '';
    var box = document.getElementById('dlvSelesaiSales');
    var reqId = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';
    var prefillRaw = (document.getElementById('dlvSelesaiPrefill') || {}).value || '';
    var prefillIds = prefillRaw
      ? prefillRaw.split(',').map(function (s) { return parseInt(s, 10); }).filter(function (n) { return n > 0; })
      : [];
    if (!box) return;
    updateSekalianUi();
    if (!phone || !jenis) {
      box.innerHTML = '<div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>';
      return;
    }
    box.innerHTML = '<div class="dlv-sales-empty"><i class="fas fa-spinner fa-spin me-1"></i>Memuat…</div>';
    var url = salesOptionsUrl + encodeURIComponent(phone) + '?jenis=' + encodeURIComponent(jenis);
    if (reqId) url += '&id_request=' + encodeURIComponent(reqId);
    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          box.innerHTML = '<div class="dlv-sales-empty">' + escapeHtml((res && res.message) || 'Gagal memuat item') + '</div>';
          return;
        }
        renderSalesOptions((res.data && res.data.orders) || [], box, 'ids[]', prefillIds);
      })
      .catch(function () {
        box.innerHTML = '<div class="dlv-sales-empty">Gagal memuat item</div>';
      });

    var sekalianCheck = document.getElementById('dlvSekalianCheck');
    if (sekalianCheck && sekalianCheck.checked) {
      loadSekalianSalesOptions();
    }
  }

  function loadSekalianSalesOptions() {
    var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
    var jenis = (document.getElementById('dlvSelesaiJenis') || {}).value || '';
    var box = document.getElementById('dlvSekalianSales');
    var wrap = document.getElementById('dlvSekalianWrap');
    var reqId = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';
    var lawan = oppositeJenis(jenis);
    if (!box || !wrap) return;
    if (!phone || !lawan) {
      box.innerHTML = '<div class="dlv-sales-empty">Pilih jenis utama dulu</div>';
      return;
    }
    wrap.hidden = false;
    box.innerHTML = '<div class="dlv-sales-empty"><i class="fas fa-spinner fa-spin me-1"></i>Memuat…</div>';
    var url = salesOptionsUrl + encodeURIComponent(phone) + '?jenis=' + encodeURIComponent(lawan);
    if (reqId) url += '&id_request=' + encodeURIComponent(reqId);
    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          box.innerHTML = '<div class="dlv-sales-empty">' + escapeHtml((res && res.message) || 'Gagal memuat item') + '</div>';
          return;
        }
        renderSalesOptions((res.data && res.data.orders) || [], box, 'ids_sekalian[]', []);
      })
      .catch(function () {
        box.innerHTML = '<div class="dlv-sales-empty">Gagal memuat item</div>';
      });
  }

  function openSelesai(btn) {
    var phone = btn.getAttribute('data-dlv-selesai') || '';
    var nama = btn.getAttribute('data-nama') || 'Customer';
    ensureKaryawanSelectize();
    resetSelesaiForm();
    document.getElementById('dlvSelesaiMode').value = 'crm';
    document.getElementById('dlvSelesaiPhone').value = phone;
    var sub = document.getElementById('dlvSelesaiSub');
    if (sub) sub.textContent = nama + ' · ' + phone + ' · CRM';
    var title = document.getElementById('dlvSelesaiTitle');
    if (title) title.textContent = 'Selesai Delivery';
    openModal('dlvSelesaiModal');
  }

  function openSelesaiRequest(btn) {
    var idReq = btn.getAttribute('data-dlv-selesai-request') || '';
    var phone = btn.getAttribute('data-phone-tail') || '';
    var jenis = (btn.getAttribute('data-jenis') || '').toLowerCase();
    var prefill = btn.getAttribute('data-prefill') || '';
    var nama = btn.getAttribute('data-nama') || 'Customer';
    ensureKaryawanSelectize();
    resetSelesaiForm();
    document.getElementById('dlvSelesaiMode').value = 'request';
    document.getElementById('dlvSelesaiRequestId').value = idReq;
    document.getElementById('dlvSelesaiPhone').value = phone;
    document.getElementById('dlvSelesaiPrefill').value = prefill;
    var jenisEl = document.getElementById('dlvSelesaiJenis');
    if (jenisEl) {
      jenisEl.value = jenis;
      jenisEl.disabled = true;
    }
    var sub = document.getElementById('dlvSelesaiSub');
    if (sub) sub.textContent = nama + ' · ' + phone + ' · Request #' + idReq;
    var title = document.getElementById('dlvSelesaiTitle');
    if (title) title.textContent = 'Selesai Request Customer';
    openModal('dlvSelesaiModal');
    loadSalesOptions();
  }

  function refreshCustomerEmptyState(body) {
    if (!body) return;
    if (body.querySelector('.dlv-item--customer')) return;
    body.innerHTML =
      '<div class="dlv-empty">' +
        '<i class="fas fa-motorcycle" aria-hidden="true"></i>' +
        '<strong>Belum ada order delivery</strong>' +
        '<span>Request CRM (case kuning) dan request Sameday dari portal customer tampil di sini.</span>' +
      '</div>';
  }

  function removeCustomerItem(phoneTail) {
    var item = root.querySelector('.dlv-item--customer[data-source="crm"][data-phone-tail="' + phoneTail + '"]');
    if (!item) {
      item = root.querySelector('.dlv-item--customer[data-phone-tail="' + phoneTail + '"]:not([data-id-request])');
    }
    if (!item) return;
    var group = item.closest('.dlv-group');
    var list = item.closest('.dlv-list');
    var body = item.closest('.dlv-body');
    item.remove();
    if (list && !list.querySelector('.dlv-item--customer') && group) {
      group.remove();
    }
    refreshCustomerEmptyState(body);
  }

  function removeRequestItem(idRequest) {
    var item = root.querySelector('.dlv-item--request[data-id-request="' + idRequest + '"]');
    if (!item) return;
    var group = item.closest('.dlv-group');
    var list = item.closest('.dlv-list');
    var body = item.closest('.dlv-body');
    item.remove();
    if (list && !list.querySelector('.dlv-item--customer') && group) {
      group.remove();
    }
    refreshCustomerEmptyState(body);
  }

  function submitSelesai(e) {
    e.preventDefault();
    var mode = (document.getElementById('dlvSelesaiMode') || {}).value || 'crm';
    var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
    var jenisEl = document.getElementById('dlvSelesaiJenis');
    var jenis = jenisEl ? jenisEl.value : '';
    var idRequest = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';
    var idKaryawan = '';
    if (karyawanSelectize) idKaryawan = karyawanSelectize.getValue();
    else {
      var sel = document.getElementById('dlvSelesaiKaryawan');
      idKaryawan = sel ? sel.value : '';
    }
    var accessKey = String((document.getElementById('dlvSelesaiKey') || {}).value || '').trim();
    var checks = root.querySelectorAll('#dlvSelesaiSales input[name="ids[]"]:checked');
    var sekalianOn = !!(document.getElementById('dlvSekalianCheck') || {}).checked;
    var checksSekalian = root.querySelectorAll('#dlvSekalianSales input[name="ids_sekalian[]"]:checked');

    if (mode === 'crm' && !phone) { toast('Nomor tidak valid', 'error'); return; }
    if (mode === 'request' && !idRequest) { toast('Request tidak valid', 'error'); return; }
    if (!jenis) { toast('Pilih jenis jemput/antar', 'warn'); return; }
    if (!idKaryawan) { toast('Pilih karyawan yang menyelesaikan', 'warn'); return; }
    if (!/^\d{4}$/.test(accessKey)) { toast('Access Key harus 4 digit', 'warn'); return; }
    if (!checks.length) { toast('Pilih minimal satu item', 'warn'); return; }
    if (sekalianOn && !checksSekalian.length) {
      toast('Sekalian aktif: pilih minimal satu item lawan jenis', 'warn');
      return;
    }

    var fd = new FormData();
    fd.append('jenis', jenis);
    fd.append('id_karyawan', idKaryawan);
    fd.append('access_key', accessKey);
    Array.prototype.forEach.call(checks, function (cb) {
      fd.append('ids[]', cb.value);
    });
    if (sekalianOn) {
      fd.append('sekalian', '1');
      Array.prototype.forEach.call(checksSekalian, function (cb) {
        fd.append('ids_sekalian[]', cb.value);
      });
    }

    var endpoint = selesaiUrl;
    if (mode === 'request') {
      endpoint = selesaiRequestUrl;
      fd.append('id_request', idRequest);
    } else {
      fd.append('phone_tail', phone);
    }

    var submitBtn = document.getElementById('dlvSelesaiSubmit');
    if (submitBtn) submitBtn.disabled = true;
    fetch(endpoint, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal menyelesaikan', 'error');
          return;
        }
        toast(res.message || 'Delivery selesai', 'success');
        closeModal('dlvSelesaiModal');
        if (mode === 'request') removeRequestItem(idRequest);
        else removeCustomerItem(phone);
      })
      .catch(function () { toast('Gagal menyelesaikan', 'error'); })
      .finally(function () {
        if (submitBtn) submitBtn.disabled = false;
      });
  }

  function batalDelivery() {
    var mode = (document.getElementById('dlvSelesaiMode') || {}).value || 'crm';
    var endpoint = mode === 'request' ? batalRequestUrl : batalUrl;
    if (!endpoint) {
      toast('Endpoint batal tidak tersedia', 'error');
      return;
    }
    if (mode === 'request') {
      var idReq = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';
      if (!idReq) { toast('Request tidak valid', 'error'); return; }
    } else {
      var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
      if (!phone) { toast('Nomor tidak valid', 'error'); return; }
    }
    ensureKaryawanSelectize();
    if (batalKaryawanSelectize) batalKaryawanSelectize.clear(true);
    else {
      var sel = document.getElementById('dlvBatalKaryawan');
      if (sel) sel.value = '';
    }
    var keyEl = document.getElementById('dlvBatalKey');
    if (keyEl) keyEl.value = '';
    var catatanEl = document.getElementById('dlvBatalCatatan');
    if (catatanEl) catatanEl.value = '';
    var msg = document.getElementById('dlvConfirmMsg');
    if (msg) {
      msg.textContent = mode === 'request'
        ? 'Request customer akan dibatalkan tanpa menyimpan riwayat jemput/antar.'
        : 'Case akan ditutup tanpa menyimpan riwayat jemput/antar.';
    }
    openModal('dlvConfirmModal');
  }

  function confirmBatalDelivery() {
    var mode = (document.getElementById('dlvSelesaiMode') || {}).value || 'crm';
    var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
    var idRequest = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';

    if (mode === 'request' && !idRequest) {
      toast('Request tidak valid', 'error');
      closeModal('dlvConfirmModal');
      return;
    }
    if (mode !== 'request' && !phone) {
      toast('Nomor tidak valid', 'error');
      closeModal('dlvConfirmModal');
      return;
    }

    ensureKaryawanSelectize();
    var idKaryawan = '';
    if (batalKaryawanSelectize) idKaryawan = batalKaryawanSelectize.getValue();
    else {
      var sel = document.getElementById('dlvBatalKaryawan');
      if (sel) idKaryawan = sel.value;
    }
    var accessKey = String((document.getElementById('dlvBatalKey') || {}).value || '').trim();
    var catatan = String((document.getElementById('dlvBatalCatatan') || {}).value || '').trim();

    if (!idKaryawan) {
      toast('Pilih karyawan yang membatalkan', 'warn');
      return;
    }
    if (!/^\d{4}$/.test(accessKey)) {
      toast('Access Key harus 4 digit', 'warn');
      return;
    }
    if (!catatan) {
      toast('Catatan wajib diisi', 'warn');
      return;
    }

    var btn = document.getElementById('dlvSelesaiBatal');
    var yesBtn = document.getElementById('dlvConfirmYes');
    var fd = new FormData();
    fd.append('id_karyawan', idKaryawan);
    fd.append('access_key', accessKey);
    fd.append('catatan', catatan);
    var endpoint = batalUrl;
    if (mode === 'request') {
      endpoint = batalRequestUrl;
      fd.append('id_request', idRequest);
    } else {
      fd.append('phone_tail', phone);
    }
    if (btn) btn.disabled = true;
    if (yesBtn) yesBtn.disabled = true;
    fetch(endpoint, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal membatalkan', 'error');
          return;
        }
        toast(res.message || 'Delivery dibatalkan', 'success');
        closeModal('dlvConfirmModal');
        closeModal('dlvSelesaiModal');
        if (mode === 'request') removeRequestItem(idRequest);
        else removeCustomerItem(phone);
      })
      .catch(function () { toast('Gagal membatalkan', 'error'); })
      .finally(function () {
        if (btn) btn.disabled = false;
        if (yesBtn) yesBtn.disabled = false;
      });
  }

  function renderDetail(data) {
    var rows = (data.items || []).map(function (it) {
      var desc = it.deskripsi ? '<span class="dlv-desc">' + escapeHtml(it.deskripsi) + '</span>' : '';
      var unit = it.unit ? ' ' + escapeHtml(it.unit) : '';
      var denom = (Number(it.denom) !== 1 && it.denom) ? ' <span style="color:#64748b">@' + escapeHtml(String(it.denom)) + '</span>' : '';
      return '<tr>' +
        '<td>' + escapeHtml(it.nama || '-') + desc + denom +
          '<div style="font-size:0.72rem;font-weight:700;color:#64748b;margin-top:2px">' +
            fmtQty(it.qty) + unit + ' × ' + fmtRp(it.price) +
          '</div></td>' +
        '<td style="text-align:right;white-space:nowrap">' + fmtRp(it.total) + '</td>' +
      '</tr>';
    }).join('');

    return '<table class="dlv-detail-table">' +
      '<thead><tr><th>Barang</th><th style="text-align:right">Subtotal</th></tr></thead>' +
      '<tbody>' + (rows || '<tr><td colspan="2">Tidak ada item</td></tr>') + '</tbody>' +
      '<tfoot><tr><td>TOTAL</td><td style="text-align:right">' + fmtRp(data.total) + '</td></tr></tfoot>' +
      '</table>';
  }

  var MEDIA_PROXY = 'https://api.nalju.com/CRM/Chat/media?id=';

  function mediaSrc(m) {
    if (m && m.media_url) return String(m.media_url);
    if (m && m.media_id) return MEDIA_PROXY + encodeURIComponent(String(m.media_id));
    return '';
  }

  /** WhatsApp formatting: *bold* _italic_ ~strike~ ```mono``` + links (sama pola CRM) */
  function parseWhatsAppFormatting(text) {
    if (!text) return '';
    var f = String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\s+\|\s+\|\s+/g, '\n')
      .replace(/```([^`]+)```/g, '<code>$1</code>')
      .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
      .replace(/_([^_]+)_/g, '<em>$1</em>')
      .replace(/~([^~]+)~/g, '<del>$1</del>')
      .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
    return f;
  }

  function renderChat(data) {
    var msgs = data.messages || [];
    if (!msgs.length) {
      return '<div class="dlv-detail-loading">Belum ada pesan</div>';
    }
    var html = msgs.map(function (m) {
      var isMe = m.sender === 'me';
      var who = isMe ? 'Laundry' : 'Customer';
      var cls = isMe ? 'dlv-chat__bubble--me' : 'dlv-chat__bubble--customer';
      var caption = (m.text && String(m.text).trim()) ? String(m.text) : '';
      var src = mediaSrc(m);
      var body = '';
      if (m.type === 'image' && src) {
        body = '<img class="dlv-chat__img" src="' + escapeHtml(src) + '" alt="Gambar" loading="lazy"' +
          ' onclick="window.open(this.src,\'_blank\')">' +
          (caption ? '<div class="dlv-chat__text">' + parseWhatsAppFormatting(caption) + '</div>' : '');
      } else if (m.type === 'sticker' && src) {
        body = '<img class="dlv-chat__img" src="' + escapeHtml(src) + '" alt="Sticker" loading="lazy">';
      } else {
        body = '<div class="dlv-chat__text">' +
          parseWhatsAppFormatting(caption || ('[' + (m.type || 'pesan') + ']')) +
          '</div>';
      }
      return '<div class="dlv-chat__bubble ' + cls + '">' +
        '<div class="dlv-chat__meta"><span>' + who + '</span><span>' + escapeHtml(fmtTime(m.time)) + '</span></div>' +
        body +
      '</div>';
    }).join('');
    return '<div class="dlv-chat">' + html + '</div>';
  }

  function setTerimaPakaiBtn(data) {
    var btn = document.getElementById('dlvTerimaPakaiBtn');
    if (!btn) return;
    detailTerimaPakai = {
      ref: (data && data.ref) ? String(data.ref) : '',
      sourceKode: (data && data.source_kode) ? String(data.source_kode) : '-',
      targetKode: (data && data.target_kode) ? String(data.target_kode) : '-'
    };
    btn.hidden = !detailTerimaPakai.ref;
    btn.disabled = false;
  }

  function openTerimaPakaiConfirm() {
    if (!detailTerimaPakai.ref) {
      toast('Ref tidak valid', 'error');
      return;
    }
    ensureKaryawanSelectize();
    if (terimaPakaiKaryawanSelectize) {
      terimaPakaiKaryawanSelectize.clear(true);
    }
    var keyEl = document.getElementById('dlvTerimaPakaiKey');
    if (keyEl) keyEl.value = '';

    var refEl = document.getElementById('dlvTerimaPakaiRef');
    var srcEl = document.getElementById('dlvTerimaPakaiSource');
    var sub = document.getElementById('dlvTerimaPakaiSub');
    if (refEl) refEl.textContent = '#' + detailTerimaPakai.ref;
    if (srcEl) srcEl.textContent = detailTerimaPakai.sourceKode || '-';
    if (sub) {
      sub.textContent = (detailTerimaPakai.sourceKode || '-') + ' → ' + (detailTerimaPakai.targetKode || '-');
    }
    openModal('dlvTerimaPakaiModal');
  }

  function confirmTerimaPakai() {
    var ref = detailTerimaPakai.ref;
    if (!ref || !terimaPakaiUrl) {
      toast('Ref tidak valid', 'error');
      return;
    }
    ensureKaryawanSelectize();
    var idKaryawan = '';
    if (terimaPakaiKaryawanSelectize) idKaryawan = terimaPakaiKaryawanSelectize.getValue();
    else {
      var sel = document.getElementById('dlvTerimaPakaiKaryawan');
      if (sel) idKaryawan = sel.value;
    }
    var accessKey = String((document.getElementById('dlvTerimaPakaiKey') || {}).value || '').trim();
    if (!idKaryawan) {
      toast('Pilih karyawan penerima', 'warn');
      return;
    }
    if (!/^\d{4}$/.test(accessKey)) {
      toast('Access Key harus 4 digit', 'warn');
      return;
    }

    var btn = document.getElementById('dlvTerimaPakaiConfirm');
    var footBtn = document.getElementById('dlvTerimaPakaiBtn');
    var fd = new FormData();
    fd.append('ref', ref);
    fd.append('id_karyawan', idKaryawan);
    fd.append('access_key', accessKey);
    if (btn) btn.disabled = true;
    if (footBtn) footBtn.disabled = true;

    fetch(terimaPakaiUrl, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal Terima Pakai', 'error');
          return;
        }
        toast(res.message || 'Barang berhasil diterima dan dipakai', 'success');
        closeModal('dlvTerimaPakaiModal');
        closeModal('dlvDetailModal');
        removeTransferItem(ref);
      })
      .catch(function () { toast('Gagal Terima Pakai', 'error'); })
      .finally(function () {
        if (btn) btn.disabled = false;
        if (footBtn) footBtn.disabled = false;
      });
  }

  function removeTransferItem(ref) {
    var item = null;
    root.querySelectorAll('.dlv-item[data-ref]').forEach(function (el) {
      if (el.getAttribute('data-ref') === String(ref)) item = el;
    });
    if (!item) return;
    var list = item.closest('.dlv-list');
    var body = item.closest('.dlv-body');
    item.remove();
    if (list && !list.querySelector('.dlv-item') && body) {
      body.innerHTML =
        '<div class="dlv-empty">' +
          '<i class="fas fa-truck" aria-hidden="true"></i>' +
          '<strong>Belum ada order delivery</strong>' +
          '<span>Transfer barang antar cabang yang belum diterima akan tampil di sini.</span>' +
        '</div>';
    }
  }

  function loadDetail(ref, btn) {
    var body = document.getElementById('dlvDetailBody');
    var sub = document.getElementById('dlvDetailSub');
    if (!body) return;

    setTerimaPakaiBtn(null);
    body.innerHTML = '<div class="dlv-detail-loading"><i class="fas fa-spinner fa-spin me-1"></i>Memuat…</div>';
    if (sub) sub.textContent = '#' + ref;
    openModal('dlvDetailModal');
    if (btn) btn.disabled = true;

    fetch(detailUrl + encodeURIComponent(ref), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success' || !res.data) {
          body.innerHTML = '<div class="dlv-detail-error">' + escapeHtml((res && res.message) || 'Gagal memuat detail') + '</div>';
          setTerimaPakaiBtn(null);
          return;
        }
        var d = res.data;
        if (sub) sub.textContent = '#' + d.ref + ' · ' + d.source_kode + ' → ' + d.target_kode;
        body.innerHTML = renderDetail(d);
        setTerimaPakaiBtn(d);
      })
      .catch(function () {
        body.innerHTML = '<div class="dlv-detail-error">Gagal memuat detail</div>';
        setTerimaPakaiBtn(null);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function loadCustomerDetail(phoneTail, btn) {
    var body = document.getElementById('dlvCustomerBody');
    var sub = document.getElementById('dlvCustomerSub');
    if (!body) return;

    body.innerHTML = '<div class="dlv-detail-loading"><i class="fas fa-spinner fa-spin me-1"></i>Memuat…</div>';
    if (sub) sub.textContent = phoneTail;
    openModal('dlvCustomerModal');
    if (btn) btn.disabled = true;

    fetch(customerDetailUrl + encodeURIComponent(phoneTail), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success' || !res.data) {
          body.innerHTML = '<div class="dlv-detail-error">' + escapeHtml((res && res.message) || 'Gagal memuat chat') + '</div>';
          return;
        }
        var d = res.data;
        if (sub) {
          sub.textContent = (d.nama || 'Customer') + ' · ' + (d.phone_tail || phoneTail) + ' · ' + (d.kode_cabang || '');
        }
        body.innerHTML = renderChat(d);
        body.scrollTop = body.scrollHeight;
      })
      .catch(function () {
        body.innerHTML = '<div class="dlv-detail-error">Gagal memuat chat</div>';
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  root.addEventListener('click', function (e) {
    var closeBtn = e.target.closest('[data-op-close]');
    if (closeBtn) {
      var modal = closeBtn.closest('.op-modal');
      if (modal && root.contains(modal)) {
        e.preventDefault();
        closeModal(modal);
      }
      return;
    }

    var custBtn = e.target.closest('[data-dlv-cek-customer]');
    if (custBtn && root.contains(custBtn)) {
      e.preventDefault();
      loadCustomerDetail(custBtn.getAttribute('data-dlv-cek-customer'), custBtn);
      return;
    }

    var selesaiBtn = e.target.closest('[data-dlv-selesai]');
    if (selesaiBtn && root.contains(selesaiBtn)) {
      e.preventDefault();
      openSelesai(selesaiBtn);
      return;
    }

    var selesaiReqBtn = e.target.closest('[data-dlv-selesai-request]');
    if (selesaiReqBtn && root.contains(selesaiReqBtn)) {
      e.preventDefault();
      openSelesaiRequest(selesaiReqBtn);
      return;
    }

    var cekBtn = e.target.closest('[data-dlv-cek]');
    if (cekBtn && root.contains(cekBtn)) {
      e.preventDefault();
      loadDetail(cekBtn.getAttribute('data-dlv-cek'), cekBtn);
      return;
    }
  });

  var jenisEl = document.getElementById('dlvSelesaiJenis');
  if (jenisEl) jenisEl.addEventListener('change', loadSalesOptions);

  var sekalianCheck = document.getElementById('dlvSekalianCheck');
  if (sekalianCheck) {
    sekalianCheck.addEventListener('change', function () {
      var wrap = document.getElementById('dlvSekalianWrap');
      if (!this.checked) {
        if (wrap) wrap.hidden = true;
        var sales = document.getElementById('dlvSekalianSales');
        if (sales) sales.innerHTML = '<div class="dlv-sales-empty">Centang sekalian untuk memuat item</div>';
        return;
      }
      loadSekalianSalesOptions();
    });
  }

  var selesaiForm = document.getElementById('dlvSelesaiForm');
  if (selesaiForm) selesaiForm.addEventListener('submit', submitSelesai);

  var batalBtn = document.getElementById('dlvSelesaiBatal');
  if (batalBtn) batalBtn.addEventListener('click', batalDelivery);

  var confirmYesBtn = document.getElementById('dlvConfirmYes');
  if (confirmYesBtn) confirmYesBtn.addEventListener('click', confirmBatalDelivery);

  var terimaPakaiBtn = document.getElementById('dlvTerimaPakaiBtn');
  if (terimaPakaiBtn) terimaPakaiBtn.addEventListener('click', openTerimaPakaiConfirm);

  var terimaPakaiConfirmBtn = document.getElementById('dlvTerimaPakaiConfirm');
  if (terimaPakaiConfirmBtn) terimaPakaiConfirmBtn.addEventListener('click', confirmTerimaPakai);

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var open = root.querySelectorAll('.op-modal.is-open');
    if (!open.length) return;
    closeModal(open[open.length - 1]);
  });

  if (window.jQuery) {
    jQuery(function () { ensureKaryawanSelectize(); });
  }
})();
</script>
