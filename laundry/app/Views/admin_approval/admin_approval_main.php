<?php
$tabs = [
  'Setoran' => ['label' => 'Setoran', 'icon' => 'fa-university'],
  'NonTunai' => ['label' => 'Non Tunai', 'icon' => 'fa-credit-card'],
  'HapusOrder' => ['label' => 'Hapus Order', 'icon' => 'fa-trash-alt'],
  'HapusDeposit' => ['label' => 'Hapus Deposit', 'icon' => 'fa-box-open'],
  'Pengeluaran' => ['label' => 'Pengeluaran', 'icon' => 'fa-money-bill-wave'],
];
$counts = $data['counts'] ?? [];
$mode = (string) ($data['mode'] ?? 'Setoran');
$kodeCabang = strtoupper((string) ($this->dCabang['kode_cabang'] ?? ''));
$totalPending = 0;
foreach ($tabs as $key => $_meta) {
  $totalPending += (int) ($counts[$key] ?? 0);
}
?>
<div id="aa-root" data-mode="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
  <style>
    #aa-root {
      --aa-ink: #0f172a;
      --aa-muted: #1e293b;
      --aa-line: #94a3b8;
      --aa-line-soft: #cbd5e1;
      --aa-blue: #2563eb;
      --aa-blue-deep: #1d4ed8;
      --aa-green: #16a34a;
      --aa-green-deep: #15803d;
      --aa-yellow: #f59e0b;
      --aa-yellow-deep: #d97706;
      --aa-red: #dc2626;
      --aa-red-deep: #b91c1c;
      --aa-radius: 0;
      --aa-border: 1px;
      --aa-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
      max-width: 1100px;
      width: 100%;
      margin: 8px 0 24px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
      color: var(--aa-ink);
    }
    #aa-root[data-mode="HapusOrder"],
    #aa-root[data-mode="HapusDeposit"] {
      max-width: 980px;
    }
    #aa-root,
    #aa-root .btn,
    #aa-root button,
    #aa-root a.aa-tab,
    #aa-root .aa-card,
    #aa-root .aa-badge,
    #aa-root .aa-btn,
    #aa-root .aa-chip,
    #aa-root input,
    #aa-root select,
    #aa-root textarea,
    #aa-root .badge,
    #aa-root .list-group-item,
    #aa-root .modal-content,
    #aa-root .rounded,
    #aa-root .rounded-2 {
      border-radius: 0 !important;
    }
    #aa-root .aa-shell {
      min-width: 0;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(217,119,6,.14), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(220,38,38,.10), transparent 45%),
        linear-gradient(180deg, #fffbeb 0%, #fff8f1 45%, #f8fafc 100%);
      border: 1px solid var(--aa-line-soft);
      box-shadow: var(--aa-shadow);
      padding: 12px;
    }
    #aa-root .aa-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: -12px -12px 12px;
      padding: 12px 14px;
      background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
      color: #fff;
      border-bottom: 1px solid rgba(15, 23, 42, 0.12);
    }
    #aa-root .aa-head h2 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    #aa-root .aa-head h2 .aa-ico {
      width: 30px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(15, 23, 42, 0.22);
      border: 1px solid rgba(255,255,255,.35);
      color: #fff;
      flex: 0 0 30px;
    }
    #aa-root .aa-head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      opacity: .92;
    }
    #aa-root .aa-head-meta {
      text-align: right;
      font-size: 0.78rem;
      font-weight: 900;
      line-height: 1.25;
      text-shadow: 0 1px 0 rgba(0,0,0,.12);
    }
    #aa-root .aa-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin: 0 0 12px;
    }
    #aa-root .aa-tab {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border: 1px solid #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
      color: var(--aa-ink);
      text-decoration: none;
      font-size: 0.82rem;
      font-weight: 900;
      line-height: 1;
      white-space: nowrap;
      transition: background .15s ease, border-color .15s ease, color .15s ease;
    }
    #aa-root .aa-tab:hover {
      border-color: var(--aa-yellow-deep);
      color: var(--aa-ink);
      text-decoration: none;
      background: #fff7ed;
    }
    #aa-root .aa-tab.is-active {
      border-width: 2px;
      border-color: var(--aa-yellow-deep);
      background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
      color: #111;
      box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.22);
    }
    #aa-root .aa-tab.is-active .aa-tab__label { color: #111; }
    #aa-root .aa-tab.is-done {
      border-color: #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
      color: var(--aa-muted);
    }
    #aa-root .aa-tab__label { font-weight: 900; }
    #aa-root .aa-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 22px;
      height: 20px;
      padding: 0 6px;
      border: 1px solid #7f1d1d;
      background: var(--aa-red);
      color: #fff;
      font-size: 0.72rem;
      font-weight: 900;
      line-height: 1;
    }
    #aa-root .aa-tab.is-active .aa-badge {
      border-color: #7f1d1d;
      background: #fff;
      color: var(--aa-red-deep);
    }
    #aa-root .aa-ok {
      color: var(--aa-green);
      font-size: 0.9rem;
    }
    #aa-root .aa-body {
      min-width: 0;
    }
    #aa-root #load {
      min-width: 0;
      max-width: none;
    }
    #aa-root #load:empty::before {
      content: 'Memuat…';
      display: block;
      padding: 18px;
      font-weight: 900;
      color: var(--aa-muted);
      border: 1px dashed var(--aa-line-soft);
      background: #fff;
    }

    /* ===== Shared content cards (AJAX tabs) ===== */
    #aa-root #load .aa-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 10px;
      margin: 0 0 14px;
    }
    #aa-root #load .aa-section-title {
      margin: 0 0 8px;
      font-size: 0.78rem;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--aa-muted);
    }
    #aa-root #load .aa-card {
      background: #fff;
      border: 1px solid var(--aa-line-soft);
      box-shadow: var(--aa-shadow);
      padding: 12px 14px;
      min-width: 0;
    }
    #aa-root #load .aa-card--pending {
      border-color: #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #aa-root #load .aa-card--ok {
      border-color: #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
    }
    #aa-root #load .aa-card--fail {
      border-color: #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
    }
    #aa-root #load .aa-card__meta {
      font-size: 0.78rem;
      font-weight: 750;
      color: var(--aa-muted);
      margin-bottom: 4px;
    }
    #aa-root #load .aa-card__title {
      font-size: 0.92rem;
      font-weight: 900;
      color: var(--aa-ink);
      margin: 0 0 4px;
    }
    #aa-root #load .aa-card__amount {
      font-size: 1rem;
      font-weight: 900;
      color: var(--aa-blue-deep);
      margin: 6px 0 10px;
    }
    #aa-root #load .aa-card__status {
      margin-top: 8px;
      padding: 8px 10px;
      text-align: center;
      font-weight: 900;
      font-size: 0.84rem;
      border: 1px solid var(--aa-line-soft);
      background: #f8fafc;
    }
    #aa-root #load .aa-card__status.is-ok {
      border-color: #86efac;
      background: #f0fdf4;
      color: var(--aa-green-deep);
    }
    #aa-root #load .aa-card__status.is-fail {
      border-color: #fca5a5;
      background: #fef2f2;
      color: var(--aa-red-deep);
    }
    #aa-root #load .aa-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    #aa-root #load .aa-btn,
    #aa-root #load .btn.aa-btn {
      box-sizing: border-box;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      min-height: 34px;
      padding: 8px 12px;
      border: 1px solid transparent;
      font-family: inherit;
      font-size: 0.84rem;
      font-weight: 900;
      line-height: 1;
      cursor: pointer;
      text-decoration: none;
    }
    #aa-root #load .aa-btn--danger {
      background: var(--aa-red);
      border-color: var(--aa-red-deep);
      color: #fff;
    }
    #aa-root #load .aa-btn--danger:hover { background: var(--aa-red-deep); color: #fff; }
    #aa-root #load .aa-btn--ok {
      background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
      border-color: var(--aa-green-deep);
      color: #fff;
    }
    #aa-root #load .aa-btn--ok:hover { filter: brightness(1.05); color: #fff; }
    #aa-root #load .aa-btn--ghost {
      background: #e2e8f0;
      border-color: var(--aa-line);
      color: var(--aa-ink);
    }
    #aa-root #load .aa-card.is-busy .aa-btn,
    #aa-root #load .aa-card.is-leaving {
      pointer-events: none;
    }
    #aa-root #load .aa-card.is-busy .aa-btn { opacity: .55; }
    #aa-root #load .aa-empty {
      text-align: center;
      padding: 28px 16px;
      border: 1px solid #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
      font-weight: 900;
      color: var(--aa-muted);
    }
    #aa-root #load .aa-empty i {
      display: block;
      font-size: 2rem;
      color: var(--aa-green);
      margin-bottom: 8px;
    }

    /* Soft-theme legacy AJAX content (HapusOrder / HapusDeposit / NonTunai) */
    #aa-root #load .list-group-item {
      border: 1px solid var(--aa-line-soft) !important;
      background: #fff;
      margin-bottom: 8px;
      box-shadow: var(--aa-shadow);
    }
    #aa-root #load .btn-success {
      background: linear-gradient(105deg, #15803d 0%, #16a34a 100%) !important;
      border-color: var(--aa-green-deep) !important;
      font-weight: 900;
    }
    #aa-root #load .btn-danger,
    #aa-root #load .btn-outline-danger {
      background: var(--aa-red) !important;
      border-color: var(--aa-red-deep) !important;
      color: #fff !important;
      font-weight: 900;
    }
    #aa-root #load .btn-outline-danger:hover { filter: brightness(0.95); }
    #aa-root #load .bg-white.border,
    #aa-root #load .bg-white.rounded,
    #aa-root #load .bg-white.rounded.border {
      border: 1px solid var(--aa-line-soft) !important;
      box-shadow: var(--aa-shadow);
    }
    #aa-root #load .table { color: var(--aa-ink); font-weight: 750; }
    #aa-root #load .text-primary { color: var(--aa-blue-deep) !important; font-weight: 900; }
    #aa-root #load .badge-danger,
    #aa-root #load .badge-success {
      border-radius: 0 !important;
      font-weight: 900;
    }
  </style>

  <div class="aa-shell">
    <div class="aa-head">
      <div>
        <h2>
          <span class="aa-ico" aria-hidden="true"><i class="fas fa-clipboard-check"></i></span>
          Admin Approval
        </h2>
        <small>Konfirmasi antrean cabang <?= htmlspecialchars($kodeCabang !== '' ? $kodeCabang : '-', ENT_QUOTES, 'UTF-8') ?></small>
      </div>
      <div class="aa-head-meta">
        <?php if ($totalPending > 0) { ?>
          <?= (int) $totalPending ?> pending
        <?php } else { ?>
          Semua bersih
        <?php } ?>
      </div>
    </div>

    <nav class="aa-tabs" aria-label="Tab approval">
      <?php foreach ($tabs as $key => $meta) {
        $count = (int) ($counts[$key] ?? 0);
        $isActive = ($key === $mode);
        $cls = 'aa-tab';
        if ($isActive) {
          $cls .= ' is-active';
        } elseif ($count === 0) {
          $cls .= ' is-done';
        }
      ?>
        <a class="<?= $cls ?>" href="<?= URL::BASE_URL ?>AdminApproval/index/<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
          <i class="fas <?= htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
          <span class="aa-tab__label"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php if ($count > 0) { ?>
            <span class="aa-badge"><?= $count ?></span>
          <?php } else { ?>
            <i class="aa-ok fas fa-check-circle" aria-hidden="true"></i>
          <?php } ?>
        </a>
      <?php } ?>
    </nav>

    <div class="aa-body">
      <div id="load"></div>
    </div>
  </div>
</div>

<script>
  $(document).ready(function() {
    loadContent(<?= json_encode($mode) ?>);
  });

  function loadContent(mode) {
    $(".loaderDiv").fadeIn("fast");
    $("div#load").load(<?= json_encode(URL::BASE_URL) ?> + mode, function() {
      $(".loaderDiv").fadeOut("slow");
    });
  }

  function aaToast(msg, type) {
    msg = String(msg || '').trim();
    if (!msg) return;
    if (window.MdlToast) {
      if (type === 'ok' || type === 'success') return MdlToast.ok(msg);
      if (type === 'error' || type === 'danger') return MdlToast.error(msg);
      if (type === 'warn' || type === 'warning') return MdlToast.warn(msg);
      return MdlToast.info(msg);
    }
  }

  function aaUpdateTabCount(tabKey, remaining) {
    var $tab = $('#aa-root a.aa-tab[href$="/' + tabKey + '"]');
    if (!$tab.length) return;
    remaining = Math.max(0, parseInt(remaining, 10) || 0);
    $tab.find('.aa-badge, .aa-ok').remove();
    if (remaining > 0) {
      $tab.removeClass('is-done');
      $tab.append('<span class="aa-badge">' + remaining + '</span>');
    } else {
      if (!$tab.hasClass('is-active')) $tab.addClass('is-done');
      $tab.append('<i class="aa-ok fas fa-check-circle" aria-hidden="true"></i>');
    }
    var total = 0;
    $('#aa-root a.aa-tab .aa-badge').each(function() {
      total += parseInt($(this).text(), 10) || 0;
    });
    $('#aa-root .aa-head-meta').text(total > 0 ? (total + ' pending') : 'Semua bersih');
  }

  function aaFadeApproveCard($card, tabKey, emptyHtml) {
    var $grid = $card.closest('.aa-grid');
    $card.addClass('is-leaving').stop(true, true).fadeOut(320, function() {
      $(this).remove();
      var left = $grid.children('.aa-card').length;
      if (typeof aaUpdateTabCount === 'function') aaUpdateTabCount(tabKey, left);
      if (left === 0) {
        $grid.prev('.aa-section-title').remove();
        $grid.replaceWith(emptyHtml);
      }
    });
  }

  function aaApproveAjax($btn, opts) {
    opts = opts || {};
    var $card = $btn.closest('.aa-card');
    if (!$card.length || $card.hasClass('is-busy') || $card.hasClass('is-leaving')) return;
    var url = $btn.attr('data-target');
    var id = $btn.attr('data-id');
    if (!url || !id) {
      aaToast('Data aksi tidak lengkap', 'error');
      return;
    }
    $card.addClass('is-busy');
    $.ajax({
      url: url,
      type: 'POST',
      data: { id: id }
    }).done(function(res) {
      var txt = String(res == null ? '' : res).trim();
      if (txt === '0' || txt === 'success' || txt === '') {
        aaToast(opts.okMsg || 'Berhasil', 'ok');
        aaFadeApproveCard($card, opts.tabKey, opts.emptyHtml);
        return;
      }
      $card.removeClass('is-busy');
      aaToast(txt || (opts.failMsg || 'Gagal memproses'), 'warn');
    }).fail(function() {
      $card.removeClass('is-busy');
      aaToast(opts.failMsg || 'Gagal memproses', 'error');
    });
  }
</script>
