<?php
$transfers = $data['transfers'] ?? [];
$customers = $data['customers'] ?? [];
$customerRequests = $data['customerRequests'] ?? [];
$customerGroups = $data['customerGroups'] ?? [];
$canCekDetail = !empty($data['canCekDetail']);
$isEmptyCabang = empty($transfers);
$isEmptyCustomer = empty($customerGroups);
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
     data-terima-pakai-url="<?= URL::BASE_URL ?>Delivery/terima_pakai"
     data-update-qty-url="<?= URL::BASE_URL ?>Delivery/update_qty"
     data-search-pelanggan-url="<?= URL::BASE_URL ?>Delivery/search_pelanggan"
     data-lokasi-options-url="<?= URL::BASE_URL ?>Delivery/lokasi_options"
     data-buat-manual-url="<?= URL::BASE_URL ?>Delivery/buat_manual">
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
    #dlv-root .dlv-item--group {
      flex-direction: column;
      align-items: stretch;
    }
    #dlv-root .dlv-item__head-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }
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
    #dlv-root .dlv-btn--cek.dlv-btn--icon {
      width: 32px;
      height: 32px;
      padding: 0;
      gap: 0;
      font-size: 0.85rem;
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
    #dlv-root .dlv-head-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }
    #dlv-root .dlv-btn--edit-head {
      width: 34px;
      height: 34px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255, 255, 255, 0.35);
      border-radius: 0;
      background: rgba(255, 255, 255, 0.18);
      color: #fff;
      cursor: pointer;
      flex-shrink: 0;
      font-size: 0.85rem;
    }
    #dlv-root .dlv-btn--edit-head:hover {
      background: rgba(255, 255, 255, 0.32);
    }
    #dlv-root .dlv-btn--edit-head[hidden] {
      display: none !important;
    }
    #dlv-root .dlv-edit-qty-list {
      border: 1px solid var(--dlv-line);
      background: #fff;
      max-height: min(48vh, 420px);
      overflow: auto;
    }
    #dlv-root .dlv-edit-qty-row {
      display: grid;
      grid-template-columns: 1fr 110px;
      gap: 10px;
      align-items: center;
      padding: 10px 12px;
      border-bottom: 1px solid #e2e8f0;
    }
    #dlv-root .dlv-edit-qty-row:last-child { border-bottom: 0; }
    #dlv-root .dlv-edit-qty-row__name {
      font-size: 0.84rem;
      font-weight: 800;
      color: var(--dlv-ink);
      line-height: 1.3;
    }
    #dlv-root .dlv-edit-qty-row__meta {
      font-size: 0.72rem;
      font-weight: 700;
      color: #64748b;
      margin-top: 2px;
    }
    #dlv-root .dlv-edit-qty-row__input {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid var(--dlv-line);
      border-radius: 0;
      font-size: 0.9rem;
      font-weight: 800;
      text-align: right;
      color: var(--dlv-ink);
      background: #fff;
    }
    #dlv-root .dlv-edit-qty-row__input:focus {
      outline: none;
      border-color: var(--dlv-blue);
      border-width: 2px;
      padding: 7px 9px;
    }
    #dlv-root .dlv-btn:disabled { opacity: 0.55; cursor: wait; }
    #dlv-root .dlv-btn--ghost {
      background: #e2e8f0;
      color: var(--dlv-ink);
      border-color: #cbd5e1;
    }
    #dlv-root .op-modal__panel--selesai {
      width: min(560px, 100%);
      max-height: min(92vh, 860px);
    }
    #dlv-root #dlvSelesaiForm {
      display: flex;
      flex-direction: column;
      flex: 1;
      min-height: 0;
      overflow: hidden;
    }
    #dlv-root #dlvSelesaiForm > .op-modal__body {
      flex: 1 1 auto;
      min-height: 0;
      overflow: auto;
      -webkit-overflow-scrolling: touch;
    }
    #dlv-root #dlvSelesaiForm > .op-modal__foot {
      flex-shrink: 0;
    }
    #dlv-root .dlv-sales-box {
      margin-top: 4px;
      max-height: min(42vh, 360px);
      overflow: auto;
      border: 1px solid var(--dlv-line);
      background: #fff;
    }
    #dlv-root .dlv-sales-item.is-locked {
      opacity: 0.55;
      cursor: not-allowed;
      background: #f8fafc;
    }
    #dlv-root .dlv-sales-item.is-locked:hover {
      background: #f8fafc;
    }
    #dlv-root .dlv-sales-item.is-locked input {
      pointer-events: none;
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
      display: inline-flex;
      align-items: center;
      gap: 4px;
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
    #dlv-root .dlv-jenis-locked {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 2px;
      margin-bottom: 2px;
    }
    #dlv-root .dlv-jenis-locked .dlv-jenis-pill {
      margin-left: 0;
      font-size: 0.8rem;
      padding: 4px 10px;
    }
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
    #dlv-root .dlv-sekalian[hidden] { display: none !important; }
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
    #dlv-root .dlv-suggest {
      list-style: none;
      margin: 4px 0 0;
      padding: 0;
      border: 1px solid var(--dlv-line);
      background: #fff;
      max-height: 220px;
      overflow: auto;
    }
    #dlv-root .dlv-suggest[hidden] { display: none !important; }
    #dlv-root .dlv-suggest li {
      padding: 8px 10px;
      cursor: pointer;
      border-bottom: 1px solid #e2e8f0;
      font-size: 0.86rem;
      font-weight: 700;
    }
    #dlv-root .dlv-suggest li:last-child { border-bottom: 0; }
    #dlv-root .dlv-suggest li:hover { background: #eff6ff; }
    #dlv-root .dlv-suggest li small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 600;
      color: #64748b;
    }
    #dlv-root .dlv-selected-pel {
      margin-top: 8px;
      padding: 8px 10px;
      border: 1px solid #93c5fd;
      background: #eff6ff;
      font-size: 0.84rem;
      font-weight: 700;
    }
    #dlv-root .dlv-selected-pel[hidden] { display: none !important; }
    #dlv-root .dlv-btn--add-head {
      margin-left: auto;
      flex-shrink: 0;
      padding: 6px 10px;
      border: 1px solid rgba(255,255,255,.45);
      background: rgba(255,255,255,.2);
      color: #fff;
      font-size: 0.78rem;
      font-weight: 800;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    #dlv-root .dlv-btn--add-head:hover {
      background: rgba(255,255,255,.32);
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
            <small>Request Jemput / Antar aktif</small>
          </div>
          <button type="button" class="dlv-btn--add-head" id="dlvTambahBtn" title="Tambah Delivery manual">
            <i class="fas fa-plus"></i> Tambah
          </button>
        </header>
        <div class="dlv-body">
          <?php if ($isEmptyCustomer) { ?>
            <div class="dlv-empty">
              <i class="fas fa-motorcycle" aria-hidden="true"></i>
              <strong>Belum ada order delivery</strong>
              <span>Request chat (YCloud/Fonnte) dan portal customer tampil di sini setelah jenis Jemput/Antar aktif.</span>
            </div>
          <?php } else { ?>
            <div class="dlv-group dlv-group--merged">
              <h3 class="dlv-group__title"><i class="fas fa-users"></i> Customer</h3>
              <div class="dlv-list">
                <?php foreach ($customerGroups as $grp) {
                  $tail = htmlspecialchars((string) ($grp['phone_tail'] ?? ''), ENT_QUOTES, 'UTF-8');
                  $nama = htmlspecialchars(strtoupper((string) ($grp['nama'] ?? 'Customer')), ENT_QUOTES, 'UTF-8');
                  $kode = htmlspecialchars((string) ($grp['kode_cabang'] ?? '00'), ENT_QUOTES, 'UTF-8');
                  $reqs = is_array($grp['requests'] ?? null) ? $grp['requests'] : [];
                  $reqCount = count($reqs);
                  if ($reqCount <= 0) {
                    continue;
                  }
                ?>
                  <div class="dlv-item dlv-item--customer dlv-item--group" data-phone-tail="<?= $tail ?>" data-source="merged">
                    <div class="dlv-item__head-row">
                      <div class="dlv-item__text">
                        <p class="dlv-item__title">
                          <?= $nama ?>
                          <span class="dlv-kode">· <?= $kode ?></span>
                          <span class="dlv-jenis-pill" style="background:#dcfce7;color:#166534"><?= (int) $reqCount ?> request</span>
                        </p>
                        <div class="dlv-item__meta">
                          <?= $tail ?>
                        </div>
                      </div>
                      <div class="dlv-item__actions">
                        <button type="button" class="dlv-btn dlv-btn--cek dlv-btn--icon" data-dlv-cek-customer="<?= $tail ?>" title="Cek" aria-label="Cek">
                          <i class="fas fa-search"></i>
                        </button>
                      </div>
                    </div>

                      <?php foreach ($reqs as $rq) {
                        $jenis = strtolower((string) ($rq['jenis'] ?? ''));
                        $layanan = strtolower((string) ($rq['layanan'] ?? 'sameday'));
                        $jenisOk = ($jenis === 'antar' || $jenis === 'jemput');
                        $jenisLbl = $jenis === 'antar' ? 'Antar' : ($jenis === 'jemput' ? 'Jemput' : '');
                        $idReq = (int) ($rq['id_request'] ?? 0);
                        $prefill = implode(',', array_map('intval', $rq['prefill_ids'] ?? []));
                        $dateRawR = $rq['insertTime'] ?? '';
                        $dateLblR = $dateRawR !== '' ? date('d/m/y H:i', strtotime($dateRawR)) : '-';
                        $pillClass = $jenis === 'antar' ? 'dlv-jenis-pill--antar' : ($jenis === 'jemput' ? 'dlv-jenis-pill--jemput' : '');
                        $jenisIcon = $jenis === 'jemput' ? 'fa-hand-holding' : 'fa-truck';
                        $lokNama = trim((string) ($rq['lokasi_nama'] ?? ''));
                        $lokDetail = trim((string) ($rq['lokasi_detail'] ?? ''));
                        $lokLatt = $rq['lokasi_latt'] ?? null;
                        $lokLongt = $rq['lokasi_longt'] ?? null;
                        $mapsHref = '';
                        if ($lokLatt !== null && $lokLongt !== null && (float) $lokLatt != 0.0 && (float) $lokLongt != 0.0) {
                          $mapsHref = 'https://www.google.com/maps?q=' . rawurlencode(((float) $lokLatt) . ',' . ((float) $lokLongt));
                        }
                        $hasLokasi = ($lokNama !== '' || $lokDetail !== '' || $mapsHref !== '');
                        $tarifSurcas = isset($rq['tarif_surcas']) && $rq['tarif_surcas'] !== null
                          ? (int) $rq['tarif_surcas']
                          : '';
                        $isInstant = $layanan === 'instant';
                        $canSelesai = !$isInstant || $jenis === 'jemput';
                        $courierName = trim((string) ($rq['courier_name'] ?? ''));
                        $bsStatus = trim((string) ($rq['biteship_status'] ?? ''));
                        $trackUrl = trim((string) ($rq['tracking_url'] ?? ''));
                        $driverName = trim((string) ($rq['driver_name'] ?? ''));
                        $ongkir = isset($rq['ongkir']) ? (int) $rq['ongkir'] : 0;
                        $catatanKurir = trim((string) ($rq['catatan_kurir'] ?? ''));
                      ?>
                        <div class="dlv-item dlv-item--customer dlv-item--request<?= $isInstant ? ' dlv-item--instant' : '' ?>"
                             style="margin-top:8px;border:1px solid rgba(15,23,42,.08)"
                             data-id-request="<?= $idReq ?>"
                             data-phone-tail="<?= $tail ?>"
                             data-source="customer"
                             data-layanan="<?= htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8') ?>"
                             data-tarif-surcas="<?= htmlspecialchars((string) $tarifSurcas, ENT_QUOTES, 'UTF-8') ?>">
                          <div class="dlv-item__text">
                            <p class="dlv-item__title">
                              <?php if ($jenisOk) { ?>
                                <span class="dlv-jenis-pill <?= $pillClass ?>">
                                  <i class="fas <?= $jenisIcon ?>" aria-hidden="true"></i>
                                  <?= htmlspecialchars($jenisLbl, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                              <?php } ?>
                              <?php if ($isInstant) { ?>
                                <span class="dlv-jenis-pill" style="background:#fff3cd;color:#856404">Instant</span>
                              <?php } ?>
                              <?php if (!$hasLokasi && !$isInstant) { ?>
                                <span class="dlv-jenis-pill" style="background:#fef3c7;color:#92400e">Lokasi menyusul</span>
                              <?php } ?>
                              <span class="dlv-kode">#<?= $idReq ?></span>
                            </p>
                            <div class="dlv-item__meta">
                              <?= htmlspecialchars($dateLblR, ENT_QUOTES, 'UTF-8') ?>
                              <?php if ($isInstant && $ongkir > 0) { ?>
                                · Ongkir Rp<?= number_format($ongkir, 0, ',', '.') ?>
                              <?php } elseif ($jenis === 'jemput' && $tarifSurcas !== '' && (int) $tarifSurcas > 0 && !$isInstant) { ?>
                                · Tarif Rp<?= number_format((int) $tarifSurcas, 0, ',', '.') ?>
                              <?php } ?>
                            </div>
                            <?php if ($isInstant && ($courierName !== '' || $bsStatus !== '' || $driverName !== '')) { ?>
                              <div class="dlv-item__meta">
                                <?php if ($courierName !== '') { ?>
                                  <i class="fas fa-motorcycle"></i> <?= htmlspecialchars($courierName, ENT_QUOTES, 'UTF-8') ?>
                                <?php } ?>
                                <?php if ($bsStatus !== '') { ?> · <?= htmlspecialchars($bsStatus, ENT_QUOTES, 'UTF-8') ?><?php } ?>
                                <?php if ($driverName !== '') { ?> · Driver <?= htmlspecialchars($driverName, ENT_QUOTES, 'UTF-8') ?><?php } ?>
                                <?php if ($trackUrl !== '') { ?> · <a href="<?= htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Track</a><?php } ?>
                              </div>
                            <?php } ?>
                            <?php if ($hasLokasi) { ?>
                              <div class="dlv-item__meta dlv-item__lokasi">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($lokNama !== '' ? $lokNama : 'Lokasi', ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($lokDetail !== '') { ?> · <?= htmlspecialchars($lokDetail, ENT_QUOTES, 'UTF-8') ?><?php } ?>
                                <?php if ($mapsHref !== '') { ?> · <a href="<?= htmlspecialchars($mapsHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Maps</a><?php } ?>
                              </div>
                            <?php } elseif (!$isInstant) { ?>
                              <div class="dlv-item__meta dlv-item__lokasi">
                                <i class="fas fa-map-marker-alt"></i>
                                Lokasi belum lengkap — driver tetap bisa selesaikan
                              </div>
                            <?php } ?>
                            <?php if ($catatanKurir !== '') { ?>
                              <div class="dlv-item__meta">
                                <i class="fas fa-sticky-note"></i>
                                Catatan: <?= htmlspecialchars($catatanKurir, ENT_QUOTES, 'UTF-8') ?>
                              </div>
                            <?php } ?>
                          </div>
                          <div class="dlv-item__actions">
                            <?php if ($canSelesai && $jenisOk) { ?>
                              <button type="button"
                                      class="dlv-btn dlv-btn--selesai"
                                      data-dlv-selesai-request="<?= $idReq ?>"
                                      data-phone-tail="<?= $tail ?>"
                                      data-jenis="<?= htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') ?>"
                                      data-layanan="<?= htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8') ?>"
                                      data-prefill="<?= htmlspecialchars($prefill, ENT_QUOTES, 'UTF-8') ?>"
                                      data-tarif-surcas="<?= htmlspecialchars($isInstant ? '' : (string) $tarifSurcas, ENT_QUOTES, 'UTF-8') ?>"
                                      data-nama="<?= $nama ?>">
                                <i class="fas fa-check"></i> Selesai <?= htmlspecialchars($jenisLbl, ENT_QUOTES, 'UTF-8') ?>
                              </button>
                            <?php } elseif ($canSelesai) { ?>
                              <button type="button"
                                      class="dlv-btn dlv-btn--selesai"
                                      data-dlv-selesai-request="<?= $idReq ?>"
                                      data-phone-tail="<?= $tail ?>"
                                      data-jenis="<?= htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') ?>"
                                      data-layanan="<?= htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8') ?>"
                                      data-prefill="<?= htmlspecialchars($prefill, ENT_QUOTES, 'UTF-8') ?>"
                                      data-tarif-surcas="<?= htmlspecialchars($isInstant ? '' : (string) $tarifSurcas, ENT_QUOTES, 'UTF-8') ?>"
                                      data-nama="<?= $nama ?>">
                                <i class="fas fa-check"></i> Selesai
                              </button>
                            <?php } else { ?>
                              <span class="dlv-item__meta" style="align-self:center;opacity:.75">Track only</span>
                            <?php } ?>
                          </div>
                        </div>
                      <?php } ?>
                  </div>
                <?php } ?>
              </div>
            </div>
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

  <div class="op-modal" id="dlvTambahModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="dlvTambahTitle">
      <div class="op-modal__head op-modal__head--blue">
        <div>
          <h3 id="dlvTambahTitle">Tambah Delivery</h3>
          <small>Manual · cabang session aktif</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <label class="dlv-field-label" for="dlvTambahCari">Cari pelanggan</label>
        <input type="text" id="dlvTambahCari" class="dlv-input" placeholder="Nama / nomor HP" autocomplete="off">
        <ul class="dlv-suggest" id="dlvTambahSuggest" hidden></ul>
        <div class="dlv-selected-pel" id="dlvTambahSelected" hidden></div>
        <input type="hidden" id="dlvTambahIdPelanggan" value="">

        <label class="dlv-field-label mt-2" for="dlvTambahJenis">Jenis</label>
        <select id="dlvTambahJenis" class="dlv-input" required>
          <option value="">— Pilih —</option>
          <option value="jemput">Jemput</option>
          <option value="antar">Antar</option>
        </select>

        <label class="dlv-field-label mt-2" for="dlvTambahLokasi">Lokasi (opsional)</label>
        <select id="dlvTambahLokasi" class="dlv-input" disabled>
          <option value="0">Tanpa lokasi — pilih pelanggan dulu</option>
        </select>
        <p class="dlv-hint mt-1 mb-0">
          <i class="fas fa-info-circle me-1"></i>
          Hanya memilih lokasi yang sudah tersimpan. Tidak membuat lokasi baru.
        </p>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Batal</button>
        <button type="button" class="dlv-btn dlv-btn--submit" id="dlvTambahConfirm">
          <i class="fas fa-plus"></i> Buat Request
        </button>
      </div>
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
          <small id="dlvSelesaiSub">Pilih jenis, karyawan, dan item</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <form id="dlvSelesaiForm">
        <div class="op-modal__body">
          <input type="hidden" id="dlvSelesaiMode" value="crm">
          <input type="hidden" id="dlvSelesaiRequestId" name="id_request" value="">
          <input type="hidden" id="dlvSelesaiLayanan" value="sameday">
          <input type="hidden" id="dlvSelesaiPhone" name="phone_tail" value="">
          <input type="hidden" id="dlvSelesaiPrefill" value="">
          <input type="hidden" id="dlvSelesaiJenisLocked" value="">
          <div id="dlvSelesaiJenisFreeWrap">
            <label class="dlv-field-label" for="dlvSelesaiJenis">Jenis</label>
            <select id="dlvSelesaiJenis" name="jenis" class="dlv-input" required>
              <option value="">— Pilih —</option>
              <option value="jemput">Jemput</option>
              <option value="antar">Antar</option>
            </select>
          </div>
          <div id="dlvSelesaiJenisLockedWrap" hidden>
            <span class="dlv-field-label">Jenis</span>
            <div class="dlv-jenis-locked">
              <span id="dlvSelesaiJenisLockedPill" class="dlv-jenis-pill">—</span>
              <span class="dlv-hint mb-0" style="margin-top:4px">
                <i class="fas fa-lock me-1"></i>Dari request customer — tidak bisa diubah
              </span>
            </div>
          </div>
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
          <label class="dlv-field-label mt-2">Item penjualan</label>
          <div class="dlv-sales-box" id="dlvSelesaiSales">
            <div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>
          </div>

          <div id="dlvSurcasJemputRow" hidden>
            <label class="dlv-field-label mt-2" for="dlvSurcasJemputJumlah">Surcas Penjemputan</label>
            <input type="number" id="dlvSurcasJemputJumlah" name="jumlah_surcas_jemput" class="dlv-input" min="0" step="1000" placeholder="0 = gratis" inputmode="numeric">
            <p class="dlv-hint mt-1 mb-0" id="dlvSurcasJemputHint">
              <i class="fas fa-info-circle me-1"></i>
              Wajib diisi. Isi nominal, atau 0 untuk gratis. Jika ref sudah punya surcas, nilai akan diupdate.
            </p>
          </div>

          <div id="dlvSurcasAntarRow" hidden>
            <label class="dlv-field-label mt-2" for="dlvSurcasAntarJumlah">Surcas Pengantaran</label>
            <input type="number" id="dlvSurcasAntarJumlah" name="jumlah_surcas_antar" class="dlv-input" min="0" step="1000" placeholder="0 = gratis" inputmode="numeric">
            <p class="dlv-hint mt-1 mb-0" id="dlvSurcasAntarHint">
              <i class="fas fa-info-circle me-1"></i>
              Wajib diisi. Isi nominal, atau 0 untuk gratis. Jika ref sudah punya surcas, nilai akan diupdate.
            </p>
          </div>

          <div id="dlvAntarKembaliBlock" hidden>
            <label class="dlv-sekalian" id="dlvAntarKembaliRow">
              <input type="checkbox" id="dlvAntarKembaliCheck" value="1">
              <span>Request Antar kembali?</span>
            </label>
            <p class="dlv-hint mt-1 mb-0">
              <i class="fas fa-info-circle me-1"></i>
              Setelah jemput selesai, request <strong>Antar</strong> baru dibuat (pakai Surcas Pengantaran di atas).
            </p>
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
          <small>Wajib karyawan dan catatan</small>
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

        <label class="dlv-field-label mt-2" for="dlvBatalCatatan">Catatan</label>
        <textarea id="dlvBatalCatatan" class="dlv-input" rows="3" placeholder="Alasan pembatalan" required></textarea>
        <p class="dlv-hint mt-2 mb-0">
          <i class="fas fa-info-circle me-1"></i>
          Pembatalan dicatat di log.
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
        <div class="dlv-head-actions">
          <button type="button" class="dlv-btn--edit-head" id="dlvEditQtyBtn" hidden title="Edit qty semua item" aria-label="Edit qty">
            <i class="fas fa-pen"></i>
          </button>
          <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
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

  <?php
  $editQtyUsers = [];
  foreach (($this->user ?? []) as $a) {
    $p = (int) ($a['id_privilege'] ?? 0);
    if ($p === 12 || $p === 100) {
      $editQtyUsers[] = $a;
    }
  }
  $editQtyUsersCabang = [];
  foreach (($this->userCabang ?? []) as $a) {
    $p = (int) ($a['id_privilege'] ?? 0);
    if ($p === 12 || $p === 100) {
      $editQtyUsersCabang[] = $a;
    }
  }
  ?>
  <div class="op-modal" id="dlvEditQtyModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--selesai" role="dialog" aria-modal="true" aria-labelledby="dlvEditQtyTitle">
      <div class="op-modal__head">
        <div>
          <h3 id="dlvEditQtyTitle">Edit Qty</h3>
          <small id="dlvEditQtySub">Ubah qty item transfer</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <input type="hidden" id="dlvEditQtyRef" value="">
        <div class="dlv-edit-qty-list" id="dlvEditQtyList">
          <div class="dlv-detail-loading">Memuat…</div>
        </div>

        <label class="dlv-field-label mt-2" for="dlvEditQtyKaryawan">Karyawan yang mengedit</label>
        <select id="dlvEditQtyKaryawan" class="tize" style="width:100%" required>
          <option value="" selected disabled></option>
          <?php if (!empty($editQtyUsers)) { ?>
            <optgroup label="<?= htmlspecialchars(($this->dCabang['nama'] ?? 'Cabang') . ' [' . ($this->dCabang['kode_cabang'] ?? '') . ']', ENT_QUOTES, 'UTF-8') ?>">
              <?php foreach ($editQtyUsers as $a) { ?>
                <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
              <?php } ?>
            </optgroup>
          <?php } ?>
          <?php if (!empty($editQtyUsersCabang)) { ?>
            <optgroup label="----- Cabang Lain -----">
              <?php foreach ($editQtyUsersCabang as $a) { ?>
                <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
              <?php } ?>
            </optgroup>
          <?php } ?>
        </select>

        <p class="dlv-hint mt-2 mb-0">
          <i class="fas fa-info-circle me-1"></i>
          Hanya Admin / Kurir yang boleh mengedit qty.
        </p>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Batal</button>
        <button type="button" class="dlv-btn dlv-btn--submit" id="dlvEditQtyConfirm">
          <i class="fas fa-check"></i> Update
        </button>
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

        <p class="dlv-hint mt-2 mb-0">
          <i class="fas fa-info-circle me-1"></i>
          Barang diterima lalu status Pakai (type=3).
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
  var updateQtyUrl = root.getAttribute('data-update-qty-url') || '';
  var searchPelangganUrl = root.getAttribute('data-search-pelanggan-url') || '';
  var lokasiOptionsUrl = root.getAttribute('data-lokasi-options-url') || '';
  var buatManualUrl = root.getAttribute('data-buat-manual-url') || '';
  var karyawanSelectize = null;
  var terimaPakaiKaryawanSelectize = null;
  var batalKaryawanSelectize = null;
  var editQtyKaryawanSelectize = null;
  var detailTerimaPakai = { ref: '', sourceKode: '', targetKode: '' };
  var detailEditQty = { ref: '', sourceKode: '', targetKode: '', items: [] };
  var tambahSearchTimer = null;
  var tambahPelanggan = { id: 0, nama: '', hp: '' };

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
    if (!editQtyKaryawanSelectize) {
      var $editQty = jQuery('#dlvEditQtyKaryawan');
      if ($editQty.length) {
        if ($editQty[0].selectize) editQtyKaryawanSelectize = $editQty[0].selectize;
        else editQtyKaryawanSelectize = $editQty.selectize()[0].selectize;
      }
    }
  }

  function setJenisLocked(jenis) {
    var freeWrap = document.getElementById('dlvSelesaiJenisFreeWrap');
    var lockedWrap = document.getElementById('dlvSelesaiJenisLockedWrap');
    var lockedVal = document.getElementById('dlvSelesaiJenisLocked');
    var pill = document.getElementById('dlvSelesaiJenisLockedPill');
    var jenisEl = document.getElementById('dlvSelesaiJenis');
    var j = String(jenis || '').toLowerCase();
    var ok = j === 'antar' || j === 'jemput';

    if (ok) {
      if (jenisEl) {
        jenisEl.value = j;
        jenisEl.disabled = true;
        jenisEl.required = false;
      }
      if (lockedVal) lockedVal.value = j;
      if (pill) {
        pill.textContent = j === 'antar' ? 'Antar' : 'Jemput';
        pill.className = 'dlv-jenis-pill dlv-jenis-pill--' + j;
      }
      if (freeWrap) freeWrap.hidden = true;
      if (lockedWrap) lockedWrap.hidden = false;
      return;
    }

    if (jenisEl) {
      jenisEl.disabled = false;
      jenisEl.required = true;
    }
    if (lockedVal) lockedVal.value = '';
    if (freeWrap) freeWrap.hidden = false;
    if (lockedWrap) lockedWrap.hidden = true;
  }

  function getSelesaiJenis() {
    var mode = (document.getElementById('dlvSelesaiMode') || {}).value || 'crm';
    var locked = (document.getElementById('dlvSelesaiJenisLocked') || {}).value || '';
    if (mode === 'request' && (locked === 'antar' || locked === 'jemput')) {
      return locked;
    }
    return ((document.getElementById('dlvSelesaiJenis') || {}).value || '').toLowerCase();
  }

  function resetSelesaiForm() {
    var jenis = document.getElementById('dlvSelesaiJenis');
    var phone = document.getElementById('dlvSelesaiPhone');
    var box = document.getElementById('dlvSelesaiSales');
    var modeEl = document.getElementById('dlvSelesaiMode');
    var reqEl = document.getElementById('dlvSelesaiRequestId');
    var layananEl = document.getElementById('dlvSelesaiLayanan');
    var prefillEl = document.getElementById('dlvSelesaiPrefill');
    var sekalianCheck = document.getElementById('dlvSekalianCheck');
    var sekalianRow = document.getElementById('dlvSekalianRow');
    var sekalianWrap = document.getElementById('dlvSekalianWrap');
    var sekalianSales = document.getElementById('dlvSekalianSales');
    var surcasRow = document.getElementById('dlvSurcasJemputRow');
    var surcasJumlah = document.getElementById('dlvSurcasJemputJumlah');
    var surcasHint = document.getElementById('dlvSurcasJemputHint');
    var batalBtn = document.getElementById('dlvSelesaiBatal');
    var antarKembaliBlock = document.getElementById('dlvAntarKembaliBlock');
    var antarKembaliCheck = document.getElementById('dlvAntarKembaliCheck');
    var surcasAntarRow = document.getElementById('dlvSurcasAntarRow');
    var surcasAntarJumlah = document.getElementById('dlvSurcasAntarJumlah');
    if (jenis) {
      jenis.value = '';
      jenis.disabled = false;
      jenis.required = true;
    }
    setJenisLocked('');
    if (phone) phone.value = '';
    if (modeEl) modeEl.value = 'crm';
    if (reqEl) reqEl.value = '';
    if (layananEl) layananEl.value = 'sameday';
    if (prefillEl) prefillEl.value = '';
    if (batalBtn) batalBtn.hidden = false;
    if (box) box.innerHTML = '<div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>';
    if (sekalianCheck) sekalianCheck.checked = false;
    if (sekalianRow) sekalianRow.hidden = true;
    if (sekalianWrap) sekalianWrap.hidden = true;
    if (sekalianSales) sekalianSales.innerHTML = '<div class="dlv-sales-empty">Centang sekalian untuk memuat item</div>';
    if (surcasRow) surcasRow.hidden = true;
    if (surcasJumlah) {
      surcasJumlah.value = '';
      surcasJumlah.readOnly = false;
      surcasJumlah.required = false;
      surcasJumlah.removeAttribute('data-tarif-fixed');
    }
    if (surcasHint) {
      surcasHint.innerHTML = '<i class="fas fa-info-circle me-1"></i>Wajib diisi. Isi nominal, atau 0 untuk gratis.';
    }
    if (antarKembaliBlock) antarKembaliBlock.hidden = true;
    if (antarKembaliCheck) antarKembaliCheck.checked = false;
    if (surcasAntarRow) surcasAntarRow.hidden = true;
    if (surcasAntarJumlah) {
      surcasAntarJumlah.value = '';
      surcasAntarJumlah.required = false;
      surcasAntarJumlah.readOnly = false;
      delete surcasAntarJumlah.dataset.userEdited;
    }
    var antarHint = document.getElementById('dlvSurcasAntarHint');
    if (antarHint) {
      antarHint.innerHTML = '<i class="fas fa-info-circle me-1"></i>Wajib diisi. Isi nominal, atau 0 untuk gratis.';
    }
    var antarKembaliCheckReset = document.getElementById('dlvAntarKembaliCheck');
    if (antarKembaliCheckReset) {
      antarKembaliCheckReset.checked = false;
      antarKembaliCheckReset.disabled = false;
    }
    window._dlvSurcasByRef = {};
    if (karyawanSelectize) {
      karyawanSelectize.clear(true);
    } else {
      var sel = document.getElementById('dlvSelesaiKaryawan');
      if (sel) sel.value = '';
    }
  }

  function syncAntarKembaliUi() {
    var block = document.getElementById('dlvAntarKembaliBlock');
    var check = document.getElementById('dlvAntarKembaliCheck');
    var jenis = getSelesaiJenis();
    var layanan = (document.getElementById('dlvSelesaiLayanan') || {}).value || 'sameday';
    if (!block) return;

    var show = jenis === 'jemput' && layanan !== 'instant';
    if (check) check.disabled = false;
    if (!show) {
      block.hidden = true;
      if (check) check.checked = false;
      return;
    }
    block.hidden = false;
  }

  function syncSurcasAntarUi() {
    var row = document.getElementById('dlvSurcasAntarRow');
    var input = document.getElementById('dlvSurcasAntarJumlah');
    var hint = document.getElementById('dlvSurcasAntarHint');
    var jenis = getSelesaiJenis();
    var layanan = (document.getElementById('dlvSelesaiLayanan') || {}).value || 'sameday';
    var antarKembaliOn = !!(document.getElementById('dlvAntarKembaliCheck') || {}).checked;
    if (!row || !input) return;

    // Tampil saat Antar, atau Jemput + Request Antar kembali
    var show = layanan !== 'instant' && (jenis === 'antar' || (jenis === 'jemput' && antarKembaliOn));
    if (!show) {
      row.hidden = true;
      input.required = false;
      return;
    }

    row.hidden = false;
    input.readOnly = false;
    input.required = true;

    var fixed = parseInt(
      (document.getElementById('dlvSurcasJemputJumlah') || {}).getAttribute('data-tarif-fixed') || '0',
      10
    ) || 0;
    if (fixed <= 0) {
      fixed = parseInt((document.getElementById('dlvSurcasJemputJumlah') || {}).value || '0', 10) || 0;
    }
    if ((input.value === '' || input.value == null) && !input.dataset.userEdited && fixed > 0) {
      input.value = String(fixed);
    }

    if (hint) {
      if (jenis === 'antar') {
        hint.innerHTML = '<i class="fas fa-info-circle me-1"></i>Wajib diisi untuk selesai Antar. Isi 0 untuk gratis.';
      } else {
        hint.innerHTML = '<i class="fas fa-info-circle me-1"></i>Wajib diisi untuk Request Antar kembali. Isi 0 untuk gratis.';
      }
    }
  }

  function syncSurcasJemputUi() {
    var row = document.getElementById('dlvSurcasJemputRow');
    var input = document.getElementById('dlvSurcasJemputJumlah');
    var hint = document.getElementById('dlvSurcasJemputHint');
    var jenis = getSelesaiJenis();
    var mode = (document.getElementById('dlvSelesaiMode') || {}).value || 'crm';
    var layanan = (document.getElementById('dlvSelesaiLayanan') || {}).value || 'sameday';
    if (!row || !input) return;

    if (jenis !== 'jemput' || layanan === 'instant') {
      row.hidden = true;
      input.required = false;
      syncAntarKembaliUi();
      syncSurcasAntarUi();
      return;
    }
    row.hidden = false;
    input.readOnly = false;
    input.required = true;

    var fixed = parseInt(input.getAttribute('data-tarif-fixed') || '0', 10) || 0;
    var checks = root.querySelectorAll('#dlvSelesaiSales input[name="ids[]"]:checked');
    var prefills = window._dlvSurcasByRef || {};
    var found = null;
    for (var i = 0; i < checks.length; i++) {
      var group = checks[i].closest('.dlv-sales-group');
      var ref = group ? (group.getAttribute('data-no-ref') || '') : '';
      if (ref && prefills[ref] != null && prefills[ref] !== '') {
        found = Number(prefills[ref]);
        break;
      }
    }

    if (!input.dataset.userEdited) {
      if (fixed > 0) {
        input.value = String(fixed);
      } else if (found != null && !isNaN(found) && found >= 0) {
        input.value = String(found);
      }
    }

    if (hint) {
      if (fixed > 0) {
        hint.innerHTML = '<i class="fas fa-info-circle me-1"></i>Default dari tarif request Rp' +
          Number(fixed).toLocaleString('id-ID') + '. Wajib diisi — boleh diubah (0 = gratis).';
      } else if (found != null && !isNaN(found) && found >= 0) {
        hint.innerHTML = '<i class="fas fa-info-circle me-1"></i>Ref sudah punya Surcas Penjemputan Rp' +
          Number(found).toLocaleString('id-ID') + '. Wajib diisi — boleh diubah (0 = gratis).';
      } else {
        hint.innerHTML = '<i class="fas fa-info-circle me-1"></i>Wajib diisi. Isi nominal, atau 0 untuk gratis.';
      }
    }

    syncAntarKembaliUi();
    syncSurcasAntarUi();
  }

  function updateSekalianUi() {
    var jenis = getSelesaiJenis();
    var layanan = (document.getElementById('dlvSelesaiLayanan') || {}).value || 'sameday';
    var row = document.getElementById('dlvSekalianRow');
    var label = document.getElementById('dlvSekalianLabel');
    var check = document.getElementById('dlvSekalianCheck');
    var wrap = document.getElementById('dlvSekalianWrap');
    var sales = document.getElementById('dlvSekalianSales');
    var antarKembaliOn = !!(document.getElementById('dlvAntarKembaliCheck') || {}).checked;
    if (!row || !label) return;
    // Antar kembali = antar nanti; jangan campur dengan sekalian antar sekarang
    if (!jenis || layanan === 'instant' || (jenis === 'jemput' && antarKembaliOn)) {
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

  /** Item utama vs sekalian tidak boleh sama (satu id_penjualan). */
  function syncExclusiveSalesSelection(changedName) {
    var mainBox = document.getElementById('dlvSelesaiSales');
    var sekBox = document.getElementById('dlvSekalianSales');
    if (!mainBox || !sekBox) return;

    var mainChecked = {};
    var sekChecked = {};
    mainBox.querySelectorAll('input[name="ids[]"]:checked').forEach(function (cb) {
      mainChecked[String(cb.value)] = true;
    });
    sekBox.querySelectorAll('input[name="ids_sekalian[]"]:checked').forEach(function (cb) {
      sekChecked[String(cb.value)] = true;
    });

    // Jika baru dicentang di satu sisi, uncheck sisi lain
    if (changedName === 'ids[]') {
      sekBox.querySelectorAll('input[name="ids_sekalian[]"]').forEach(function (cb) {
        if (mainChecked[String(cb.value)] && cb.checked) cb.checked = false;
      });
    } else if (changedName === 'ids_sekalian[]') {
      mainBox.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
        if (sekChecked[String(cb.value)] && cb.checked) cb.checked = false;
      });
    }

    // Refresh maps setelah uncheck
    mainChecked = {};
    sekChecked = {};
    mainBox.querySelectorAll('input[name="ids[]"]:checked').forEach(function (cb) {
      mainChecked[String(cb.value)] = true;
    });
    sekBox.querySelectorAll('input[name="ids_sekalian[]"]:checked').forEach(function (cb) {
      sekChecked[String(cb.value)] = true;
    });

    mainBox.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
      var label = cb.closest('.dlv-sales-item');
      if (label && label.getAttribute('data-belum-selesai') === '1') {
        cb.disabled = true;
        label.classList.add('is-locked');
        return;
      }
      var locked = !!sekChecked[String(cb.value)];
      cb.disabled = locked;
      if (label) label.classList.toggle('is-locked', locked);
    });
    sekBox.querySelectorAll('input[name="ids_sekalian[]"]').forEach(function (cb) {
      var label = cb.closest('.dlv-sales-item');
      if (label && label.getAttribute('data-belum-selesai') === '1') {
        cb.disabled = true;
        label.classList.add('is-locked');
        return;
      }
      var locked = !!mainChecked[String(cb.value)];
      cb.disabled = locked;
      if (label) label.classList.toggle('is-locked', locked);
    });
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
    if (name === 'ids[]') {
      window._dlvSurcasByRef = {};
    }
    if (!orders || !orders.length) {
      box.innerHTML = '<div class="dlv-sales-empty">Tidak ada item eligible</div>';
      if (name === 'ids[]') syncSurcasJemputUi();
      return;
    }
    var html = orders.map(function (ord) {
      var ref = ord.no_ref || '-';
      if (name === 'ids[]' && ord.surcas_penjemputan != null && ord.surcas_penjemputan !== '') {
        window._dlvSurcasByRef[String(ref)] = Number(ord.surcas_penjemputan);
      }
      var items = (ord.items || []).map(function (it) {
        var status = Number(it.tuntas) === 1 ? 'Tuntas' : 'Proses';
        var member = Number(it.member) === 1 ? ' · Member' : '';
        var belum = !!(it.belum_selesai === true || it.belum_selesai === 1 || it.belum_selesai === '1');
        var checked = !belum && pref[String(it.id)] ? ' checked' : '';
        if (belum) {
          return '<label class="dlv-sales-item is-locked" data-belum-selesai="1">' +
            '<input type="checkbox" disabled tabindex="-1">' +
            '<span class="dlv-sales-item__text">' +
              escapeHtml(it.kategori || '-') +
              (it.durasi ? ' · ' + escapeHtml(it.durasi) : '') +
              ' · ' + escapeHtml(it.qty_show || '') +
              '<div class="dlv-sales-item__meta">#' + escapeHtml(String(it.id)) + ' · ' + status + member +
                ' · <span class="dlv-jenis-pill" style="margin-left:4px;border-color:#fca5a5;background:#fef2f2;color:#b91c1c">Belum selesai</span>' +
              '</div>' +
            '</span>' +
          '</label>';
        }
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
      return '<div class="dlv-sales-group" data-no-ref="' + escapeHtml(String(ref)) + '">' +
        '<div class="dlv-sales-group__head">#' + escapeHtml(String(ref)) +
          (ord.insertTime ? ' · ' + escapeHtml(fmtTime(ord.insertTime)) : '') +
        '</div>' + items +
      '</div>';
    }).join('');
    box.innerHTML = html;
    box.querySelectorAll('input[name="' + name + '"]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        if (name === 'ids[]') {
          var input = document.getElementById('dlvSurcasJemputJumlah');
          if (input) delete input.dataset.userEdited;
          syncSurcasJemputUi();
        }
        syncExclusiveSalesSelection(name);
      });
    });
    syncExclusiveSalesSelection(name);
    if (name === 'ids[]') syncSurcasJemputUi();
  }

  function loadSalesOptions() {
    var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
    var jenis = getSelesaiJenis();
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
    var jenis = getSelesaiJenis();
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

  function resetTambahForm() {
    tambahPelanggan = { id: 0, nama: '', hp: '' };
    var cari = document.getElementById('dlvTambahCari');
    var suggest = document.getElementById('dlvTambahSuggest');
    var selected = document.getElementById('dlvTambahSelected');
    var idEl = document.getElementById('dlvTambahIdPelanggan');
    var jenis = document.getElementById('dlvTambahJenis');
    var lokasi = document.getElementById('dlvTambahLokasi');
    if (cari) cari.value = '';
    if (suggest) {
      suggest.innerHTML = '';
      suggest.hidden = true;
    }
    if (selected) {
      selected.hidden = true;
      selected.textContent = '';
    }
    if (idEl) idEl.value = '';
    if (jenis) jenis.value = '';
    if (lokasi) {
      lokasi.disabled = true;
      lokasi.innerHTML = '<option value="0">Tanpa lokasi — pilih pelanggan dulu</option>';
      lokasi.value = '0';
    }
  }

  function openTambahModal() {
    resetTambahForm();
    openModal('dlvTambahModal');
    var cari = document.getElementById('dlvTambahCari');
    if (cari) setTimeout(function () { cari.focus(); }, 50);
  }

  function selectTambahPelanggan(item) {
    tambahPelanggan = {
      id: parseInt(item.id_pelanggan, 10) || 0,
      nama: item.nama_pelanggan || '',
      hp: item.nomor_pelanggan || ''
    };
    var idEl = document.getElementById('dlvTambahIdPelanggan');
    var selected = document.getElementById('dlvTambahSelected');
    var suggest = document.getElementById('dlvTambahSuggest');
    var cari = document.getElementById('dlvTambahCari');
    if (idEl) idEl.value = String(tambahPelanggan.id);
    if (selected) {
      selected.hidden = false;
      selected.textContent = 'Dipilih: ' + tambahPelanggan.nama + (tambahPelanggan.hp ? ' — ' + tambahPelanggan.hp : '');
    }
    if (suggest) {
      suggest.innerHTML = '';
      suggest.hidden = true;
    }
    if (cari) cari.value = '';
    loadTambahLokasi(tambahPelanggan.id);
  }

  function loadTambahLokasi(idPelanggan) {
    var lokasi = document.getElementById('dlvTambahLokasi');
    if (!lokasi) return;
    lokasi.disabled = true;
    lokasi.innerHTML = '<option value="0">Memuat lokasi…</option>';
    if (!idPelanggan || !lokasiOptionsUrl) {
      lokasi.innerHTML = '<option value="0">Tanpa lokasi</option>';
      lokasi.disabled = false;
      return;
    }
    var url = lokasiOptionsUrl + (lokasiOptionsUrl.indexOf('?') >= 0 ? '&' : '?')
      + 'id_pelanggan=' + encodeURIComponent(idPelanggan);
    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var items = (res && res.status === 'success' && res.items) ? res.items : [];
        var html = '<option value="0">Tanpa lokasi</option>';
        items.forEach(function (it) {
          var label = (it.nama || 'Lokasi') + (it.detail ? ' — ' + it.detail : '');
          html += '<option value="' + escapeHtml(String(it.id_lokasi || 0)) + '">'
            + escapeHtml(label) + '</option>';
        });
        lokasi.innerHTML = html;
        lokasi.disabled = false;
        lokasi.value = '0';
        if (!items.length) {
          // tetap bisa buat tanpa lokasi
        }
      })
      .catch(function () {
        lokasi.innerHTML = '<option value="0">Tanpa lokasi</option>';
        lokasi.disabled = false;
        toast('Gagal memuat lokasi', 'warn');
      });
  }

  function searchTambahPelanggan(q) {
    var suggest = document.getElementById('dlvTambahSuggest');
    if (!suggest || !searchPelangganUrl) return;
    if (q.length < 2) {
      suggest.innerHTML = '';
      suggest.hidden = true;
      return;
    }
    var url = searchPelangganUrl + (searchPelangganUrl.indexOf('?') >= 0 ? '&' : '?')
      + 'q=' + encodeURIComponent(q);
    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var items = (res && res.items) ? res.items : [];
        if (!items.length) {
          suggest.innerHTML = '<li><small>Tidak ada hasil di cabang ini</small></li>';
          suggest.hidden = false;
          return;
        }
        suggest.innerHTML = items.map(function (it) {
          return '<li data-id="' + escapeHtml(String(it.id_pelanggan || '')) + '"'
            + ' data-nama="' + escapeHtml(it.nama_pelanggan || '') + '"'
            + ' data-hp="' + escapeHtml(it.nomor_pelanggan || '') + '">'
            + escapeHtml(it.nama_pelanggan || '-')
            + '<small>' + escapeHtml(it.nomor_pelanggan || '') + '</small></li>';
        }).join('');
        suggest.hidden = false;
      })
      .catch(function () {
        suggest.innerHTML = '<li><small>Gagal mencari</small></li>';
        suggest.hidden = false;
      });
  }

  function confirmTambahManual() {
    var idPelanggan = parseInt((document.getElementById('dlvTambahIdPelanggan') || {}).value || '0', 10) || 0;
    var jenis = String((document.getElementById('dlvTambahJenis') || {}).value || '').toLowerCase();
    var idLokasi = parseInt((document.getElementById('dlvTambahLokasi') || {}).value || '0', 10) || 0;
    if (!idPelanggan) {
      toast('Pilih pelanggan dulu', 'warn');
      return;
    }
    if (jenis !== 'jemput' && jenis !== 'antar') {
      toast('Pilih jenis jemput atau antar', 'warn');
      return;
    }
    if (!buatManualUrl) {
      toast('URL tidak tersedia', 'error');
      return;
    }
    var btn = document.getElementById('dlvTambahConfirm');
    var fd = new FormData();
    fd.append('id_pelanggan', String(idPelanggan));
    fd.append('jenis', jenis);
    fd.append('id_lokasi', String(idLokasi > 0 ? idLokasi : 0));
    if (btn) btn.disabled = true;
    fetch(buatManualUrl, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal membuat request', 'error');
          return;
        }
        toast(res.message || 'Request dibuat', 'success');
        closeModal('dlvTambahModal');
        window.location.reload();
      })
      .catch(function () { toast('Gagal membuat request', 'error'); })
      .finally(function () {
        if (btn) btn.disabled = false;
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
    var layanan = (btn.getAttribute('data-layanan') || 'sameday').toLowerCase();
    var prefill = btn.getAttribute('data-prefill') || '';
    var tarif = btn.getAttribute('data-tarif-surcas') || '';
    var nama = btn.getAttribute('data-nama') || 'Customer';
    ensureKaryawanSelectize();
    resetSelesaiForm();
    document.getElementById('dlvSelesaiMode').value = 'request';
    document.getElementById('dlvSelesaiRequestId').value = idReq;
    document.getElementById('dlvSelesaiPhone').value = phone;
    document.getElementById('dlvSelesaiPrefill').value = prefill;
    var layananEl = document.getElementById('dlvSelesaiLayanan');
    if (layananEl) layananEl.value = layanan;
    setJenisLocked(jenis);
    var surcasJumlah = document.getElementById('dlvSurcasJemputJumlah');
    if (surcasJumlah) {
      surcasJumlah.setAttribute('data-tarif-fixed', tarif || '0');
    }
    var sub = document.getElementById('dlvSelesaiSub');
    if (sub) {
      var jenisLbl = jenis === 'antar' ? 'Antar' : (jenis === 'jemput' ? 'Jemput' : jenis);
      sub.textContent = nama + ' · ' + phone + ' · ' + jenisLbl + ' · Request #' + idReq
        + (layanan === 'instant' ? ' · Instant' : '');
    }
    var title = document.getElementById('dlvSelesaiTitle');
    if (title) title.textContent = layanan === 'instant' ? 'Selesai Instant Jemput' : 'Selesai Request Customer';

    var batalBtn = document.getElementById('dlvSelesaiBatal');
    var sekalianRow = document.getElementById('dlvSekalianRow');
    if (layanan === 'instant') {
      if (batalBtn) batalBtn.hidden = true;
      if (sekalianRow) sekalianRow.hidden = true;
      if (surcasJumlah) {
        surcasJumlah.removeAttribute('data-tarif-fixed');
        surcasJumlah.value = '';
        surcasJumlah.required = false;
      }
      var surcasRow = document.getElementById('dlvSurcasJemputRow');
      if (surcasRow) surcasRow.hidden = true;
    } else if (batalBtn) {
      batalBtn.hidden = false;
    }

    openModal('dlvSelesaiModal');
    syncSurcasJemputUi();
    syncAntarKembaliUi();
    syncSurcasAntarUi();
    updateSekalianUi();
    if (layanan === 'instant') {
      var surcasRow2 = document.getElementById('dlvSurcasJemputRow');
      if (surcasRow2) surcasRow2.hidden = true;
      var surcasAntar2 = document.getElementById('dlvSurcasAntarRow');
      if (surcasAntar2) surcasAntar2.hidden = true;
      if (sekalianRow) sekalianRow.hidden = true;
      var antarBlock = document.getElementById('dlvAntarKembaliBlock');
      if (antarBlock) antarBlock.hidden = true;
    }
    loadSalesOptions();
  }

  function refreshCustomerEmptyState(body) {
    if (!body) return;
    if (body.querySelector('.dlv-item--customer')) return;
    body.innerHTML =
      '<div class="dlv-empty">' +
        '<i class="fas fa-motorcycle" aria-hidden="true"></i>' +
        '<strong>Belum ada order delivery</strong>' +
        '<span>Request chat (YCloud/Fonnte) dan portal customer tampil di sini setelah jenis Jemput/Antar aktif.</span>' +
      '</div>';
  }

  function removeCustomerItem(phoneTail) {
    var item = root.querySelector('.dlv-item--group[data-phone-tail="' + phoneTail + '"]');
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

  function removeRequestItem(idRequest, opts) {
    opts = opts || {};
    var item = root.querySelector('.dlv-item--request[data-id-request="' + idRequest + '"]');
    if (!item) return;
    var phoneTail = item.getAttribute('data-phone-tail') || '';
    var groupCard = item.closest('.dlv-item--group');
    var group = item.closest('.dlv-group');
    var list = item.closest('.dlv-list');
    var body = item.closest('.dlv-body');
    item.remove();

    if (opts.crmClosed && phoneTail) {
      removeCustomerItem(phoneTail);
      return;
    }

    if (groupCard) {
      var left = groupCard.querySelectorAll('.dlv-item--request');
      var countBadge = null;
      var pills = groupCard.querySelectorAll('.dlv-jenis-pill');
      for (var i = 0; i < pills.length; i++) {
        if (/\d+\s*request/i.test(pills[i].textContent || '')) {
          countBadge = pills[i];
          break;
        }
      }
      if (!left.length) {
        groupCard.remove();
      } else if (countBadge) {
        countBadge.textContent = left.length + ' request';
      }
    }

    if (list && !list.querySelector('.dlv-item--customer') && group) {
      group.remove();
    }
    refreshCustomerEmptyState(body);
  }

  function submitSelesai(e) {
    e.preventDefault();
    var mode = (document.getElementById('dlvSelesaiMode') || {}).value || 'crm';
    var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
    var jenis = getSelesaiJenis();
    var idRequest = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';
    var idKaryawan = '';
    if (karyawanSelectize) idKaryawan = karyawanSelectize.getValue();
    else {
      var sel = document.getElementById('dlvSelesaiKaryawan');
      idKaryawan = sel ? sel.value : '';
    }
    var checks = root.querySelectorAll('#dlvSelesaiSales input[name="ids[]"]:checked');
    var sekalianOn = !!(document.getElementById('dlvSekalianCheck') || {}).checked;
    var checksSekalian = root.querySelectorAll('#dlvSekalianSales input[name="ids_sekalian[]"]:checked');

    if (mode === 'crm' && !phone) { toast('Nomor tidak valid', 'error'); return; }
    if (mode === 'request' && !idRequest) { toast('Request tidak valid', 'error'); return; }
    if (!jenis) { toast('Pilih jenis jemput/antar', 'warn'); return; }
    if (!idKaryawan) { toast('Pilih karyawan yang menyelesaikan', 'warn'); return; }
    if (!checks.length) { toast('Pilih minimal satu item', 'warn'); return; }
    if (sekalianOn && !checksSekalian.length) {
      toast('Sekalian aktif: pilih minimal satu item lawan jenis', 'warn');
      return;
    }
    if (sekalianOn) {
      var overlap = false;
      var mainMap = {};
      Array.prototype.forEach.call(checks, function (cb) { mainMap[String(cb.value)] = true; });
      Array.prototype.forEach.call(checksSekalian, function (cb) {
        if (mainMap[String(cb.value)]) overlap = true;
      });
      if (overlap) {
        toast('Item jemput dan antar tidak boleh sama', 'warn');
        return;
      }
    }
    if (jenis === 'jemput') {
      var surcasRaw = String((document.getElementById('dlvSurcasJemputJumlah') || {}).value || '').trim();
      var jumlahSc = parseInt(surcasRaw, 10);
      if (surcasRaw === '' || isNaN(jumlahSc) || jumlahSc < 0) {
        toast('Isi Surcas Penjemputan (isi 0 untuk gratis)', 'warn');
        return;
      }
    }
    var antarKembaliOn = !!(document.getElementById('dlvAntarKembaliCheck') || {}).checked;
    var needSurcasAntar = jenis === 'antar' || (jenis === 'jemput' && antarKembaliOn);
    if (needSurcasAntar) {
      if (antarKembaliOn && jenis !== 'jemput') {
        toast('Request Antar kembali hanya untuk selesai Jemput', 'warn');
        return;
      }
      if (antarKembaliOn && sekalianOn) {
        toast('Pilih salah satu: Sekalian Antar atau Request Antar kembali', 'warn');
        return;
      }
      var antarRaw = String((document.getElementById('dlvSurcasAntarJumlah') || {}).value || '').trim();
      var jumlahAntar = parseInt(antarRaw, 10);
      if (antarRaw === '' || isNaN(jumlahAntar) || jumlahAntar < 0) {
        toast('Isi Surcas Pengantaran (isi 0 untuk gratis)', 'warn');
        return;
      }
    }

    var fd = new FormData();
    fd.append('jenis', jenis);
    fd.append('id_karyawan', idKaryawan);
    Array.prototype.forEach.call(checks, function (cb) {
      fd.append('ids[]', cb.value);
    });
    if (sekalianOn) {
      fd.append('sekalian', '1');
      Array.prototype.forEach.call(checksSekalian, function (cb) {
        fd.append('ids_sekalian[]', cb.value);
      });
    }
    if (jenis === 'jemput') {
      fd.append(
        'jumlah_surcas_jemput',
        String(parseInt((document.getElementById('dlvSurcasJemputJumlah') || {}).value || '0', 10) || 0)
      );
    }
    if (needSurcasAntar) {
      if (antarKembaliOn) fd.append('antar_kembali', '1');
      fd.append(
        'jumlah_surcas_antar',
        String(parseInt((document.getElementById('dlvSurcasAntarJumlah') || {}).value || '0', 10) || 0)
      );
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
        if (res.data && res.data.antar_kembali_id) {
          // Request Antar baru dibuat — refresh board
          window.location.reload();
          return;
        }
        if (mode === 'request') removeRequestItem(idRequest, { crmClosed: !!(res.data && res.data.crm_closed) });
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
    var catatan = String((document.getElementById('dlvBatalCatatan') || {}).value || '').trim();

    if (!idKaryawan) {
      toast('Pilih karyawan yang membatalkan', 'warn');
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
        if (mode === 'request') removeRequestItem(idRequest, { crmClosed: !!(res.data && res.data.crm_closed) });
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
    var editBtn = document.getElementById('dlvEditQtyBtn');
    detailTerimaPakai = {
      ref: (data && data.ref) ? String(data.ref) : '',
      sourceKode: (data && data.source_kode) ? String(data.source_kode) : '-',
      targetKode: (data && data.target_kode) ? String(data.target_kode) : '-'
    };
    detailEditQty = {
      ref: detailTerimaPakai.ref,
      sourceKode: detailTerimaPakai.sourceKode,
      targetKode: detailTerimaPakai.targetKode,
      items: (data && Array.isArray(data.items)) ? data.items : []
    };
    if (btn) {
      btn.hidden = !detailTerimaPakai.ref;
      btn.disabled = false;
    }
    if (editBtn) {
      editBtn.hidden = !detailEditQty.ref || !detailEditQty.items.length;
      editBtn.disabled = false;
    }
  }

  function fmtQtyInput(n) {
    var v = Number(n);
    if (!isFinite(v)) return '';
    if (Math.abs(v - Math.round(v)) < 0.00001) return String(Math.round(v));
    return String(Math.round(v * 100) / 100);
  }

  function renderEditQtyList(items) {
    if (!items || !items.length) {
      return '<div class="dlv-detail-error">Tidak ada item</div>';
    }
    return items.map(function (it) {
      var unit = it.unit ? ' ' + escapeHtml(it.unit) : '';
      var desc = it.deskripsi ? '<div class="dlv-edit-qty-row__meta">' + escapeHtml(it.deskripsi) + '</div>' : '';
      return '<div class="dlv-edit-qty-row" data-id="' + escapeHtml(String(it.id || 0)) + '">' +
        '<div>' +
          '<div class="dlv-edit-qty-row__name">' + escapeHtml(it.nama || '-') + '</div>' +
          desc +
          '<div class="dlv-edit-qty-row__meta">Qty lama: ' + escapeHtml(fmtQty(it.qty)) + unit + '</div>' +
        '</div>' +
        '<input type="number" class="dlv-edit-qty-row__input" min="0.01" step="any" inputmode="decimal" value="' + escapeHtml(fmtQtyInput(it.qty)) + '" aria-label="Qty ' + escapeHtml(it.nama || '') + '">' +
      '</div>';
    }).join('');
  }

  function openEditQtyModal() {
    if (!detailEditQty.ref || !detailEditQty.items.length) {
      toast('Data transfer tidak valid', 'error');
      return;
    }
    ensureKaryawanSelectize();
    if (editQtyKaryawanSelectize) {
      editQtyKaryawanSelectize.clear(true);
    }
    var refEl = document.getElementById('dlvEditQtyRef');
    if (refEl) refEl.value = detailEditQty.ref;
    var sub = document.getElementById('dlvEditQtySub');
    if (sub) {
      sub.textContent = '#' + detailEditQty.ref + ' · ' + (detailEditQty.sourceKode || '-') + ' → ' + (detailEditQty.targetKode || '-');
    }
    var list = document.getElementById('dlvEditQtyList');
    if (list) list.innerHTML = renderEditQtyList(detailEditQty.items);
    openModal('dlvEditQtyModal');
  }

  function confirmEditQty() {
    var ref = String((document.getElementById('dlvEditQtyRef') || {}).value || '').trim() || detailEditQty.ref;
    if (!ref || !updateQtyUrl) {
      toast('Ref tidak valid', 'error');
      return;
    }
    ensureKaryawanSelectize();
    var idKaryawan = '';
    if (editQtyKaryawanSelectize) idKaryawan = editQtyKaryawanSelectize.getValue();
    else {
      var sel = document.getElementById('dlvEditQtyKaryawan');
      if (sel) idKaryawan = sel.value;
    }
    if (!idKaryawan) {
      toast('Pilih karyawan yang mengedit', 'warn');
      return;
    }

    var items = [];
    var rows = document.querySelectorAll('#dlvEditQtyList .dlv-edit-qty-row');
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var id = parseInt(row.getAttribute('data-id') || '0', 10);
      var inp = row.querySelector('.dlv-edit-qty-row__input');
      var qty = inp ? parseFloat(String(inp.value || '').replace(',', '.')) : NaN;
      if (!id) {
        toast('Item tidak valid', 'error');
        return;
      }
      if (!isFinite(qty) || qty <= 0) {
        toast('Qty harus lebih dari 0', 'warn');
        if (inp) inp.focus();
        return;
      }
      items.push({ id: id, qty: qty });
    }
    if (!items.length) {
      toast('Tidak ada item', 'warn');
      return;
    }

    var btn = document.getElementById('dlvEditQtyConfirm');
    var fd = new FormData();
    fd.append('ref', ref);
    fd.append('id_karyawan', idKaryawan);
    fd.append('items', JSON.stringify(items));
    if (btn) btn.disabled = true;
    fetch(updateQtyUrl, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal update qty', 'error');
          return;
        }
        toast(res.message || 'Qty berhasil diupdate', 'success');
        closeModal('dlvEditQtyModal');
        loadDetail(ref, null);
      })
      .catch(function () { toast('Gagal update qty', 'error'); })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
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
    if (!idKaryawan) {
      toast('Pilih karyawan penerima', 'warn');
      return;
    }

    var btn = document.getElementById('dlvTerimaPakaiConfirm');
    var footBtn = document.getElementById('dlvTerimaPakaiBtn');
    var fd = new FormData();
    fd.append('ref', ref);
    fd.append('id_karyawan', idKaryawan);
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

    var tambahBtn = e.target.closest('#dlvTambahBtn');
    if (tambahBtn && root.contains(tambahBtn)) {
      e.preventDefault();
      openTambahModal();
      return;
    }

    var tambahConfirm = e.target.closest('#dlvTambahConfirm');
    if (tambahConfirm && root.contains(tambahConfirm)) {
      e.preventDefault();
      confirmTambahManual();
      return;
    }

    var suggestLi = e.target.closest('#dlvTambahSuggest li[data-id]');
    if (suggestLi && root.contains(suggestLi)) {
      e.preventDefault();
      selectTambahPelanggan({
        id_pelanggan: suggestLi.getAttribute('data-id'),
        nama_pelanggan: suggestLi.getAttribute('data-nama'),
        nomor_pelanggan: suggestLi.getAttribute('data-hp')
      });
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

  var tambahCari = document.getElementById('dlvTambahCari');
  if (tambahCari) {
    tambahCari.addEventListener('input', function () {
      var q = String(tambahCari.value || '').trim();
      clearTimeout(tambahSearchTimer);
      tambahSearchTimer = setTimeout(function () {
        searchTambahPelanggan(q);
      }, 250);
    });
  }

  var jenisEl = document.getElementById('dlvSelesaiJenis');
  if (jenisEl) {
    jenisEl.addEventListener('change', function () {
      var input = document.getElementById('dlvSurcasJemputJumlah');
      if (input) {
        delete input.dataset.userEdited;
        input.value = '';
      }
      syncSurcasJemputUi();
      loadSalesOptions();
    });
  }

  var surcasInput = document.getElementById('dlvSurcasJemputJumlah');
  if (surcasInput) {
    surcasInput.addEventListener('input', function () {
      this.dataset.userEdited = '1';
      syncSurcasAntarUi();
    });
  }

  var surcasAntarInput = document.getElementById('dlvSurcasAntarJumlah');
  if (surcasAntarInput) {
    surcasAntarInput.addEventListener('input', function () {
      this.dataset.userEdited = '1';
    });
  }

  var sekalianCheck = document.getElementById('dlvSekalianCheck');
  if (sekalianCheck) {
    sekalianCheck.addEventListener('change', function () {
      if (this.checked) {
        var ak = document.getElementById('dlvAntarKembaliCheck');
        if (ak && ak.checked) {
          ak.checked = false;
          syncAntarKembaliUi();
          syncSurcasAntarUi();
        }
      }
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

  var antarKembaliCheck = document.getElementById('dlvAntarKembaliCheck');
  if (antarKembaliCheck) {
    antarKembaliCheck.addEventListener('change', function () {
      if (this.checked) {
        var sk = document.getElementById('dlvSekalianCheck');
        if (sk && sk.checked) {
          sk.checked = false;
          var wrap = document.getElementById('dlvSekalianWrap');
          if (wrap) wrap.hidden = true;
        }
      }
      syncAntarKembaliUi();
      syncSurcasAntarUi();
      updateSekalianUi();
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

  var editQtyBtn = document.getElementById('dlvEditQtyBtn');
  if (editQtyBtn) editQtyBtn.addEventListener('click', openEditQtyModal);

  var editQtyConfirmBtn = document.getElementById('dlvEditQtyConfirm');
  if (editQtyConfirmBtn) editQtyConfirmBtn.addEventListener('click', confirmEditQty);

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
