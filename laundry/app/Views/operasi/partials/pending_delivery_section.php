<?php
$customerDeliveryRequests = (array) ($data['customer_delivery_requests'] ?? []);
if (empty($customerDeliveryRequests) || (int) ($data['modeView'] ?? 0) === 2) {
  return;
}
$reqCount = count($customerDeliveryRequests);
?>
<style>
  #op-dlv-root {
    --dlv-ink: #0f172a;
    --dlv-muted: #1e293b;
    --dlv-line: #cbd5e1;
    --dlv-blue: #2563eb;
    --dlv-blue-deep: #1d4ed8;
    --dlv-yellow: #f59e0b;
    --dlv-yellow-deep: #d97706;
    --dlv-green: #16a34a;
    --dlv-green-deep: #15803d;
    width: 100%;
    max-width: 100%;
    margin: 0 0 10px;
    box-sizing: border-box;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    color: var(--dlv-ink);
  }
  #op-dlv-root .op-dlv-panel {
    border: 1px solid #93c5fd;
    background: linear-gradient(180deg, #eff6ff, #fff);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
  }
  #op-dlv-root .op-dlv-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    font-weight: 900;
  }
  #op-dlv-root .op-dlv-head h2 {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 900;
    line-height: 1.2;
  }
  #op-dlv-root .op-dlv-head small {
    display: block;
    margin-top: 2px;
    font-size: 0.72rem;
    font-weight: 700;
    opacity: 0.92;
  }
  #op-dlv-root .op-dlv-count {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 7px;
    border: 1px solid rgba(255,255,255,.4);
    background: rgba(255,255,255,.2);
    font-size: 0.68rem;
    font-weight: 900;
  }
  #op-dlv-root .op-dlv-body {
    padding: 10px;
  }
  #op-dlv-root .dlv-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  #op-dlv-root .dlv-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border: 1px solid #93c5fd;
    background: #fff;
  }
  #op-dlv-root .dlv-item--request {
    border-color: rgba(15, 23, 42, 0.08);
  }
  #op-dlv-root .dlv-item__text { min-width: 0; flex: 1; }
  #op-dlv-root .dlv-item__title {
    margin: 0;
    font-size: 0.84rem;
    font-weight: 900;
    color: var(--dlv-ink);
    line-height: 1.25;
  }
  #op-dlv-root .dlv-item__title .dlv-kode { color: var(--dlv-blue-deep); }
  #op-dlv-root .dlv-item__meta {
    margin-top: 2px;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--dlv-muted);
  }
  #op-dlv-root .dlv-item__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
    flex-shrink: 0;
  }
  #op-dlv-root .dlv-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 10px;
    border: 1px solid transparent;
    font-size: 0.74rem;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
    font-family: inherit;
  }
  #op-dlv-root .dlv-btn--selesai {
    background: linear-gradient(180deg, var(--dlv-green), var(--dlv-green-deep));
    color: #fff;
  }
  #op-dlv-root .dlv-btn--pending {
    background: linear-gradient(180deg, var(--dlv-yellow), var(--dlv-yellow-deep));
    color: #fff;
  }
  #op-dlv-root .dlv-jenis-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 6px;
    margin-right: 4px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    font-size: 0.68rem;
    font-weight: 900;
    vertical-align: middle;
  }
  #op-dlv-root .dlv-jenis-pill--antar { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
  #op-dlv-root .dlv-jenis-pill--jemput { background: #fffbeb; border-color: #fcd34d; color: #b45309; }
  #op-dlv-root .dlv-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    margin-left: 4px;
    padding: 0;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: var(--dlv-blue-deep);
    cursor: pointer;
    vertical-align: middle;
  }
  #op-dlv-root .op-dlv-hint {
    margin: 0 0 8px;
    padding: 8px 10px;
    border: 1px solid #fde68a;
    background: #fffbeb;
    color: #92400e;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.4;
  }
</style>

<div id="op-dlv-root"
     data-sales-options-url="<?= URL::BASE_URL ?>Delivery/sales_options/"
     data-selesai-request-url="<?= URL::BASE_URL ?>Delivery/selesai_request"
     data-batal-request-url="<?= URL::BASE_URL ?>Delivery/batal_request"
     data-pending-request-url="<?= URL::BASE_URL ?>Delivery/pending_request"
     data-tarik-lokasi-url="<?= URL::BASE_URL ?>Delivery/tarik_lokasi_request"
     data-tarif-surcas-url="<?= URL::BASE_URL ?>Delivery/tarif_surcas">
  <section class="op-dlv-panel" aria-label="Delivery request pelanggan">
    <header class="op-dlv-head">
      <i class="fas fa-motorcycle" aria-hidden="true"></i>
      <div>
        <h2>Delivery Request <span class="op-dlv-count"><?= (int) $reqCount ?></span></h2>
        <small>Selesaikan request menggantung sebelum nota bisa dituntaskan</small>
      </div>
    </header>
    <div class="op-dlv-body">
      <p class="op-dlv-hint">
        <i class="fas fa-info-circle me-1"></i>
        Request ini tidak terikat nota di bawah. Gunakan tombol <strong>Selesai</strong> — alur sama dengan halaman Delivery.
      </p>
      <div class="dlv-list">
        <?php foreach ($customerDeliveryRequests as $rq) {
          include __DIR__ . '/delivery_request_row.php';
        } ?>
      </div>
    </div>
  </section>
</div>
