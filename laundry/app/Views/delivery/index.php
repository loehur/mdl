<?php
$transfers = $data['transfers'] ?? [];
$canCekDetail = !empty($data['canCekDetail']);
$isEmptyCabang = empty($transfers);
?>
<div id="dlv-root" class="px-1 mt-2"
     data-can-cek="<?= $canCekDetail ? '1' : '0' ?>"
     data-detail-url="<?= URL::BASE_URL ?>Delivery/detail/">
  <style>
    #dlv-root {
      --dlv-ink: #0f172a;
      --dlv-muted: #1e293b;
      --dlv-line: #cbd5e1;
      --dlv-blue: #2563eb;
      --dlv-blue-deep: #1d4ed8;
      --dlv-yellow: #f59e0b;
      --dlv-yellow-deep: #d97706;
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
    #dlv-root .dlv-empty i {
      font-size: 1.75rem;
      color: var(--dlv-muted);
    }
    #dlv-root .dlv-panel--customer .dlv-empty i {
      color: var(--dlv-blue);
    }
    #dlv-root .dlv-panel--cabang .dlv-empty i {
      color: var(--dlv-yellow-deep);
    }
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
    #dlv-root .dlv-item__text {
      min-width: 0;
      flex: 1;
    }
    #dlv-root .dlv-item__title {
      margin: 0;
      font-size: 0.84rem;
      font-weight: 900;
      color: var(--dlv-ink);
      line-height: 1.35;
    }
    #dlv-root .dlv-item__title .dlv-kode {
      color: var(--dlv-yellow-deep);
    }
    #dlv-root .dlv-item__meta {
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--dlv-muted);
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
    #dlv-root .dlv-btn--cek:disabled {
      opacity: 0.55;
      cursor: wait;
    }
    #dlv-root .dlv-btn--ghost {
      background: #e2e8f0;
      color: var(--dlv-ink);
      border-color: #cbd5e1;
    }

    /* Detail modal (op-modal pattern) */
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
    #dlv-root .op-modal__head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
      padding: 14px 16px;
      color: #fff;
      background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
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
            <small>Order delivery pelanggan</small>
          </div>
        </header>
        <div class="dlv-body">
          <div class="dlv-empty">
            <i class="fas fa-motorcycle" aria-hidden="true"></i>
            <strong>Belum ada order delivery</strong>
            <span>Order delivery customer akan tampil di sini.</span>
          </div>
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
                $dateRaw = $tr['date'] ?? '';
                $dateLbl = $dateRaw !== '' ? date('d/m/y H:i', strtotime($dateRaw)) : '-';
                $count = (int) ($tr['item_count'] ?? 0);
              ?>
                <div class="dlv-item" data-ref="<?= $ref ?>">
                  <div class="dlv-item__text">
                    <p class="dlv-item__title">
                      Kirim Barang dari <span class="dlv-kode"><?= $src ?></span>
                      ke <span class="dlv-kode"><?= $tgt ?></span>
                    </p>
                    <div class="dlv-item__meta">
                      #<?= $ref ?> · <?= htmlspecialchars($dateLbl, ENT_QUOTES, 'UTF-8') ?>
                      · <?= $count ?> item
                    </div>
                  </div>
                  <?php if ($canCekDetail) { ?>
                    <button type="button" class="dlv-btn dlv-btn--cek" data-dlv-cek="<?= $ref ?>">
                      <i class="fas fa-search"></i> Cek
                    </button>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </section>
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
  <?php } ?>
</div>

<script>
(function () {
  var root = document.getElementById('dlv-root');
  if (!root) return;

  var canCek = root.getAttribute('data-can-cek') === '1';
  var detailUrl = root.getAttribute('data-detail-url') || '';

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

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
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
        if (sub) {
          sub.textContent = '#' + d.ref + ' · ' + d.source_kode + ' → ' + d.target_kode;
        }
        body.innerHTML = renderDetail(d);
      })
      .catch(function () {
        body.innerHTML = '<div class="dlv-detail-error">Gagal memuat detail</div>';
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  if (canCek) {
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

      var cekBtn = e.target.closest('[data-dlv-cek]');
      if (cekBtn && root.contains(cekBtn)) {
        e.preventDefault();
        loadDetail(cekBtn.getAttribute('data-dlv-cek'), cekBtn);
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      var open = root.querySelectorAll('.op-modal.is-open');
      if (!open.length) return;
      closeModal(open[open.length - 1]);
    });
  }
})();
</script>
