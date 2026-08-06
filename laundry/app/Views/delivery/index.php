<?php
$transfers = $data['transfers'] ?? [];
$customers = $data['customers'] ?? [];
$canCekDetail = !empty($data['canCekDetail']);
$listCabang = $data['listCabang'] ?? [];
$isEmptyCabang = empty($transfers);
$isEmptyCustomer = empty($customers);
?>
<div id="dlv-root" class="px-1 mt-2"
     data-can-cek="<?= $canCekDetail ? '1' : '0' ?>"
     data-detail-url="<?= URL::BASE_URL ?>Delivery/detail/"
     data-customer-detail-url="<?= URL::BASE_URL ?>Delivery/customer_detail/"
     data-sales-options-url="<?= URL::BASE_URL ?>Delivery/sales_options/"
     data-selesai-url="<?= URL::BASE_URL ?>Delivery/selesai_customer"
     data-ubah-sumber-url="<?= URL::BASE_URL ?>Delivery/ubah_sumber">
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
    #dlv-root .dlv-btn--sumber {
      background: linear-gradient(180deg, var(--dlv-yellow), var(--dlv-yellow-deep));
      color: #111;
    }
    #dlv-root .dlv-btn--submit {
      background: linear-gradient(180deg, var(--dlv-green), var(--dlv-green-deep));
      color: #fff;
    }
    #dlv-root .dlv-btn--selesai {
      background: linear-gradient(180deg, var(--dlv-green), var(--dlv-green-deep));
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
      font-weight: 750;
      color: var(--dlv-ink);
      white-space: pre-wrap;
      word-break: break-word;
      line-height: 1.4;
    }
    #dlv-root .dlv-detail-loading,
    #dlv-root .dlv-detail-error {
      padding: 24px 12px;
      text-align: center;
      font-weight: 800;
      color: var(--dlv-muted);
    }
    #dlv-root .dlv-detail-error { color: #b91c1c; }
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
              <span>Permintaan jemput/antar (case kuning CRM) akan tampil di sini.</span>
            </div>
          <?php } else { ?>
            <div class="dlv-list">
              <?php foreach ($customers as $cu) {
                $nama = htmlspecialchars(strtoupper((string) ($cu['nama'] ?? 'Customer')), ENT_QUOTES, 'UTF-8');
                $tail = htmlspecialchars((string) ($cu['phone_tail'] ?? ''), ENT_QUOTES, 'UTF-8');
                $kode = htmlspecialchars((string) ($cu['kode_cabang'] ?? '00'), ENT_QUOTES, 'UTF-8');
                $dateRaw = $cu['last_message_at'] ?? '';
                $dateLbl = $dateRaw !== '' ? date('d/m/y H:i', strtotime($dateRaw)) : '-';
              ?>
                <div class="dlv-item dlv-item--customer" data-phone-tail="<?= $tail ?>">
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
                  <?php if ($canCekDetail) { ?>
                    <div class="dlv-item__actions">
                      <button type="button" class="dlv-btn dlv-btn--cek" data-dlv-cek="<?= $ref ?>">
                        <i class="fas fa-search"></i> Cek
                      </button>
                      <button type="button" class="dlv-btn dlv-btn--sumber" data-dlv-ubah-sumber="<?= $ref ?>">
                        <i class="fas fa-exchange-alt"></i> Ubah Sumber
                      </button>
                    </div>
                  <?php } ?>
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
          <small id="dlvSelesaiSub">Pilih jenis, karyawan, dan item</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <form id="dlvSelesaiForm">
        <div class="op-modal__body">
          <input type="hidden" id="dlvSelesaiPhone" name="phone_tail" value="">
          <label class="dlv-field-label" for="dlvSelesaiJenis">Jenis</label>
          <select id="dlvSelesaiJenis" name="jenis" class="dlv-input" required>
            <option value="">— Pilih —</option>
            <option value="jemput">Jemput</option>
            <option value="antar">Antar</option>
          </select>
          <label class="dlv-field-label mt-2" for="dlvSelesaiKaryawan">Karyawan</label>
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
        </div>
        <div class="op-modal__foot">
          <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Batal</button>
          <button type="submit" class="dlv-btn dlv-btn--submit" id="dlvSelesaiSubmit">
            <i class="fas fa-check"></i> Selesai
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($canCekDetail) { ?>
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
      <div class="op-modal__foot">
        <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Tutup</button>
      </div>
    </div>
  </div>

  <div class="op-modal" id="dlvSumberModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="dlvSumberTitle">
      <div class="op-modal__head op-modal__head--blue">
        <div>
          <h3 id="dlvSumberTitle">Ubah Sumber</h3>
          <small id="dlvSumberSub">Pilih cabang sumber baru</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <form id="dlvSumberForm">
        <div class="op-modal__body">
          <div class="dlv-ref-box">
            <small>No. Ref</small>
            <strong id="dlvSumberRefLabel">—</strong>
          </div>
          <input type="hidden" id="dlvSumberRef" name="ref" value="">
          <input type="hidden" id="dlvSumberTargetId" value="">
          <label class="dlv-field-label" for="dlvSumberCabang">Cabang Sumber</label>
          <select id="dlvSumberCabang" name="source_id" class="dlv-input" required>
            <option value="">— Pilih cabang —</option>
            <?php foreach ($listCabang as $cabang) {
              $cid = (int) ($cabang['id_cabang'] ?? 0);
              if ($cid <= 0) continue;
              $kode = htmlspecialchars(strtoupper((string) ($cabang['kode_cabang'] ?? $cid)), ENT_QUOTES, 'UTF-8');
              $namaCb = htmlspecialchars((string) ($cabang['nama'] ?? ''), ENT_QUOTES, 'UTF-8');
            ?>
              <option value="<?= $cid ?>" data-kode="<?= $kode ?>"><?= $kode ?><?= $namaCb !== '' ? ' — ' . $namaCb : '' ?></option>
            <?php } ?>
          </select>
          <p class="dlv-hint">
            <i class="fas fa-info-circle me-1"></i>
            Semua item pada nota ini akan memakai cabang sumber yang dipilih.
          </p>
        </div>
        <div class="op-modal__foot">
          <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Batal</button>
          <button type="submit" class="dlv-btn dlv-btn--submit" id="dlvSumberSubmit">
            <i class="fas fa-save"></i> Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php } ?>
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
  var ubahSumberUrl = root.getAttribute('data-ubah-sumber-url') || '';
  var karyawanSelectize = null;

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
    if (karyawanSelectize) return;
    if (!window.jQuery || !jQuery.fn.selectize) return;
    var $el = jQuery('#dlv-root .tize');
    if (!$el.length) return;
    $el.selectize();
    karyawanSelectize = $el[0].selectize || null;
  }

  function resetSelesaiForm() {
    var jenis = document.getElementById('dlvSelesaiJenis');
    var phone = document.getElementById('dlvSelesaiPhone');
    var box = document.getElementById('dlvSelesaiSales');
    if (jenis) jenis.value = '';
    if (phone) phone.value = '';
    if (box) box.innerHTML = '<div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>';
    if (karyawanSelectize) {
      karyawanSelectize.clear(true);
    } else {
      var sel = document.getElementById('dlvSelesaiKaryawan');
      if (sel) sel.value = '';
    }
  }

  function renderSalesOptions(orders) {
    var box = document.getElementById('dlvSelesaiSales');
    if (!box) return;
    if (!orders || !orders.length) {
      box.innerHTML = '<div class="dlv-sales-empty">Tidak ada item eligible</div>';
      return;
    }
    var html = orders.map(function (ord) {
      var items = (ord.items || []).map(function (it) {
        var status = Number(it.tuntas) === 1 ? 'Tuntas' : 'Proses';
        var member = Number(it.member) === 1 ? ' · Member' : '';
        return '<label class="dlv-sales-item">' +
          '<input type="checkbox" name="ids[]" value="' + escapeHtml(String(it.id)) + '">' +
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
    if (!box) return;
    if (!phone || !jenis) {
      box.innerHTML = '<div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>';
      return;
    }
    box.innerHTML = '<div class="dlv-sales-empty"><i class="fas fa-spinner fa-spin me-1"></i>Memuat…</div>';
    fetch(salesOptionsUrl + encodeURIComponent(phone) + '?jenis=' + encodeURIComponent(jenis), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          box.innerHTML = '<div class="dlv-sales-empty">' + escapeHtml((res && res.message) || 'Gagal memuat item') + '</div>';
          return;
        }
        renderSalesOptions((res.data && res.data.orders) || []);
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
    document.getElementById('dlvSelesaiPhone').value = phone;
    var sub = document.getElementById('dlvSelesaiSub');
    if (sub) sub.textContent = nama + ' · ' + phone;
    openModal('dlvSelesaiModal');
  }

  function removeCustomerItem(phoneTail) {
    var item = root.querySelector('.dlv-item--customer[data-phone-tail="' + phoneTail + '"]');
    if (!item) return;
    var list = item.closest('.dlv-list');
    var body = item.closest('.dlv-body');
    item.remove();
    if (list && !list.querySelector('.dlv-item--customer') && body) {
      body.innerHTML =
        '<div class="dlv-empty">' +
          '<i class="fas fa-motorcycle" aria-hidden="true"></i>' +
          '<strong>Belum ada order delivery</strong>' +
          '<span>Permintaan jemput/antar (case kuning CRM) akan tampil di sini.</span>' +
        '</div>';
    }
  }

  function submitSelesai(e) {
    e.preventDefault();
    var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
    var jenis = (document.getElementById('dlvSelesaiJenis') || {}).value || '';
    var idKaryawan = '';
    if (karyawanSelectize) idKaryawan = karyawanSelectize.getValue();
    else {
      var sel = document.getElementById('dlvSelesaiKaryawan');
      idKaryawan = sel ? sel.value : '';
    }
    var checks = root.querySelectorAll('#dlvSelesaiSales input[name="ids[]"]:checked');
    if (!phone) { toast('Nomor tidak valid', 'error'); return; }
    if (!jenis) { toast('Pilih jenis jemput/antar', 'warn'); return; }
    if (!idKaryawan) { toast('Pilih karyawan', 'warn'); return; }
    if (!checks.length) { toast('Pilih minimal satu item', 'warn'); return; }

    var fd = new FormData();
    fd.append('phone_tail', phone);
    fd.append('jenis', jenis);
    fd.append('id_karyawan', idKaryawan);
    Array.prototype.forEach.call(checks, function (cb) {
      fd.append('ids[]', cb.value);
    });

    var submitBtn = document.getElementById('dlvSelesaiSubmit');
    if (submitBtn) submitBtn.disabled = true;
    fetch(selesaiUrl, {
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
        removeCustomerItem(phone);
      })
      .catch(function () { toast('Gagal menyelesaikan', 'error'); })
      .finally(function () {
        if (submitBtn) submitBtn.disabled = false;
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

  function renderChat(data) {
    var msgs = data.messages || [];
    if (!msgs.length) {
      return '<div class="dlv-detail-loading">Belum ada pesan</div>';
    }
    var html = msgs.map(function (m) {
      var isMe = m.sender === 'me';
      var who = isMe ? 'Laundry' : 'Customer';
      var cls = isMe ? 'dlv-chat__bubble--me' : 'dlv-chat__bubble--customer';
      var text = (m.text && String(m.text).trim()) ? String(m.text) : '[' + (m.type || 'pesan') + ']';
      return '<div class="dlv-chat__bubble ' + cls + '">' +
        '<div class="dlv-chat__meta"><span>' + who + '</span><span>' + escapeHtml(fmtTime(m.time)) + '</span></div>' +
        '<div class="dlv-chat__text">' + escapeHtml(text) + '</div>' +
      '</div>';
    }).join('');
    return '<div class="dlv-chat">' + html + '</div>';
  }

  function loadDetail(ref, btn) {
    var body = document.getElementById('dlvDetailBody');
    var sub = document.getElementById('dlvDetailSub');
    if (!body) return;

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
          return;
        }
        var d = res.data;
        if (sub) sub.textContent = '#' + d.ref + ' · ' + d.source_kode + ' → ' + d.target_kode;
        body.innerHTML = renderDetail(d);
      })
      .catch(function () {
        body.innerHTML = '<div class="dlv-detail-error">Gagal memuat detail</div>';
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

  function openUbahSumber(btn) {
    var item = btn.closest('.dlv-item');
    if (!item) return;
    var ref = item.getAttribute('data-ref') || '';
    var sourceId = item.getAttribute('data-source-id') || '';
    var targetId = item.getAttribute('data-target-id') || '';
    var sourceKode = item.getAttribute('data-source-kode') || '';
    var targetKode = item.getAttribute('data-target-kode') || '';

    document.getElementById('dlvSumberRef').value = ref;
    document.getElementById('dlvSumberTargetId').value = targetId;
    document.getElementById('dlvSumberRefLabel').textContent = '#' + ref;
    document.getElementById('dlvSumberSub').textContent =
      (sourceKode || '-') + ' → ' + (targetKode || '-');

    var select = document.getElementById('dlvSumberCabang');
    Array.prototype.forEach.call(select.options, function (opt) {
      if (!opt.value) {
        opt.disabled = false;
        opt.hidden = false;
        return;
      }
      var hide = String(opt.value) === String(targetId);
      opt.disabled = hide;
      opt.hidden = hide;
    });
    select.value = sourceId && String(sourceId) !== String(targetId) ? sourceId : '';

    openModal('dlvSumberModal');
  }

  function submitUbahSumber(e) {
    e.preventDefault();
    var ref = document.getElementById('dlvSumberRef').value;
    var sourceId = document.getElementById('dlvSumberCabang').value;
    var targetId = document.getElementById('dlvSumberTargetId').value;
    var submitBtn = document.getElementById('dlvSumberSubmit');

    if (!ref) { toast('Ref tidak valid', 'error'); return; }
    if (!sourceId) { toast('Pilih cabang sumber', 'warn'); return; }
    if (String(sourceId) === String(targetId)) {
      toast('Cabang sumber tidak boleh sama dengan tujuan', 'warn');
      return;
    }

    var fd = new FormData();
    fd.append('ref', ref);
    fd.append('source_id', sourceId);

    submitBtn.disabled = true;
    fetch(ubahSumberUrl, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal mengubah sumber', 'error');
          return;
        }
        toast(res.message || 'Sumber berhasil diubah', 'success');
        closeModal('dlvSumberModal');

        var item = null;
        root.querySelectorAll('.dlv-item').forEach(function (el) {
          if (el.getAttribute('data-ref') === ref) item = el;
        });
        if (item && res.data) {
          var kode = res.data.source_kode || '-';
          item.setAttribute('data-source-id', String(res.data.source_id || sourceId));
          item.setAttribute('data-source-kode', kode);
          var kodeEl = item.querySelector('.dlv-kode-source');
          if (kodeEl) kodeEl.textContent = kode;
        }
      })
      .catch(function () { toast('Gagal mengubah sumber', 'error'); })
      .finally(function () { submitBtn.disabled = false; });
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

    if (!canCek) return;

    var cekBtn = e.target.closest('[data-dlv-cek]');
    if (cekBtn && root.contains(cekBtn)) {
      e.preventDefault();
      loadDetail(cekBtn.getAttribute('data-dlv-cek'), cekBtn);
      return;
    }

    var sumberBtn = e.target.closest('[data-dlv-ubah-sumber]');
    if (sumberBtn && root.contains(sumberBtn)) {
      e.preventDefault();
      openUbahSumber(sumberBtn);
    }
  });

  var jenisEl = document.getElementById('dlvSelesaiJenis');
  if (jenisEl) jenisEl.addEventListener('change', loadSalesOptions);

  var selesaiForm = document.getElementById('dlvSelesaiForm');
  if (selesaiForm) selesaiForm.addEventListener('submit', submitSelesai);

  var form = document.getElementById('dlvSumberForm');
  if (form) form.addEventListener('submit', submitUbahSumber);

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
