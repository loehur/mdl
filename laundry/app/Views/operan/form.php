<?php
$idOperan = $data['idOperan'] ?? '';
$idCabang = $data['idCabang'] ?? '';
$kodeCabang = strtoupper((string) ($this->dCabang['kode_cabang'] ?? ''));
?>
<div id="operan-root">
  <style>
    #operan-root {
      --opn-ink: #0f172a;
      --opn-muted: #1e293b;
      --opn-line: #94a3b8;
      --opn-blue: #2563eb;
      --opn-blue-deep: #1d4ed8;
      --opn-green: #16a34a;
      --opn-green-deep: #15803d;
      --opn-yellow: #f59e0b;
      --opn-yellow-deep: #d97706;
      --opn-red: #dc2626;
      --opn-radius: 0;
      --opn-border: 1px;
      max-width: 1100px;
      width: 100%;
      margin: 8px 0 24px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #operan-root,
    #operan-root .btn,
    #operan-root button,
    #operan-root input,
    #operan-root select,
    #operan-root .selectize-input,
    #operan-root .selectize-dropdown,
    #operan-root .opn-chip,
    #operan-root .opn-badge,
    #operan-root .opn-modal__panel,
    #operan-root .opn-card,
    #operan-root .opn-svc {
      border-radius: 0 !important;
    }
    #operan-root .opn-shell {
      min-width: 0;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.12), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f4fff8 55%, #fff8eb 100%);
      border: 1px solid #cbd5e1;
      padding: 14px;
    }
    #operan-root .opn-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: -14px -14px 14px;
      padding: 14px 16px;
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 35%, #16a34a 70%, #f59e0b 100%);
      color: #fff;
    }
    #operan-root .opn-head h2 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #operan-root .opn-head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 750;
      opacity: 0.95;
    }
    #operan-root .opn-cabang {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 52px;
      padding: 8px 10px;
      background: rgba(255,255,255,.2);
      color: #fff;
      font-weight: 900;
      font-size: 0.95rem;
      letter-spacing: 0.06em;
    }
    #operan-root .opn-panel {
      border: 1px solid #93c5fd;
      background: linear-gradient(180deg, #eff6ff, #fff);
      padding: 14px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
      margin-bottom: 12px;
    }
    #operan-root .opn-panel__title {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0 0 12px;
      font-size: 0.95rem;
      font-weight: 900;
      color: var(--opn-ink);
    }
    #operan-root .opn-panel__title .opn-ico {
      width: 30px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--opn-blue);
      color: #fff;
      flex: 0 0 auto;
    }
    #operan-root .opn-panel--guide {
      border-color: #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #operan-root .opn-panel--guide .opn-ico {
      background: var(--opn-yellow);
      color: #111;
    }
    #operan-root .opn-guide-list {
      margin: 0;
      padding: 0 0 0 18px;
      color: var(--opn-ink);
      font-size: 0.86rem;
      font-weight: 750;
      line-height: 1.5;
    }
    #operan-root .opn-guide-list li {
      margin-bottom: 6px;
    }
    #operan-root .opn-guide-list li:last-child {
      margin-bottom: 0;
    }
    #operan-root .opn-guide-list code {
      display: inline-block;
      padding: 1px 6px;
      border: 1px solid #fcd34d;
      background: #fff;
      color: #0f172a;
      font-family: 'fontku', 'Segoe UI', Consolas, monospace;
      font-size: 0.82rem;
      font-weight: 900;
      border-radius: 0;
    }
    #operan-root .opn-guide-list b {
      color: var(--opn-yellow-deep);
      font-weight: 900;
    }
    #operan-root .opn-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
      align-items: end;
    }
    @media (min-width: 720px) {
      #operan-root .opn-grid {
        grid-template-columns: 1fr 1fr auto;
      }
    }
    #operan-root .opn-label {
      display: block;
      margin-bottom: 6px;
      font-size: 0.78rem;
      font-weight: 900;
      color: var(--opn-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #operan-root .opn-hint {
      display: block;
      margin-top: 4px;
      font-size: 0.72rem;
      font-weight: 750;
      color: #334155;
    }
    #operan-root .opn-hint b { color: var(--opn-blue-deep); }
    #operan-root .opn-input {
      width: 100%;
      box-sizing: border-box;
      border: 1px solid var(--opn-line);
      background: #fff;
      color: var(--opn-ink);
      font-size: 0.92rem;
      font-weight: 800;
      padding: 10px 12px;
      min-height: 42px;
    }
    #operan-root .opn-input:focus {
      outline: none;
      border-color: var(--opn-blue);
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
    }
    #operan-root .opn-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 42px;
      padding: 12px 14px;
      border: 1px solid transparent;
      font-size: 0.95rem;
      font-weight: 900;
      cursor: pointer;
      line-height: 1.2;
      white-space: nowrap;
    }
    #operan-root .opn-btn--primary {
      background: linear-gradient(180deg, var(--opn-green), var(--opn-green-deep));
      color: #fff;
    }
    #operan-root .opn-btn--blue {
      background: linear-gradient(180deg, var(--opn-blue), var(--opn-blue-deep));
      color: #fff;
    }
    #operan-root .opn-btn--ghost {
      background: #e2e8f0;
      color: var(--opn-ink);
      border-color: #cbd5e1;
    }
    #operan-root .opn-btn:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }
    #operan-root .opn-btn .opn-spin {
      width: 14px;
      height: 14px;
      border: 2px solid rgba(255,255,255,.35);
      border-top-color: #fff;
      border-radius: 50%;
      animation: opn-spin 0.7s linear infinite;
    }
    @keyframes opn-spin { to { transform: rotate(360deg); } }
    #operan-root #load {
      min-width: 0;
    }
    #operan-root .opn-empty,
    #operan-root .opn-error {
      border: 1px solid #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
      color: var(--opn-ink);
      padding: 14px;
      font-size: 0.88rem;
      font-weight: 800;
    }
    #operan-root .opn-error {
      border-color: #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
      color: #b91c1c;
    }
  </style>

  <div class="opn-shell">
    <div class="opn-head">
      <div>
        <h2><i class="fas fa-random"></i> Operan</h2>
        <small>Cek order outlet lain, selesaikan operasi, &amp; cetak pack label</small>
      </div>
      <?php if ($kodeCabang !== '') { ?>
        <span class="opn-cabang"><?= htmlspecialchars($kodeCabang) ?></span>
      <?php } ?>
    </div>

    <div class="opn-panel opn-panel--guide">
      <h3 class="opn-panel__title">
        <span class="opn-ico"><i class="fas fa-lightbulb"></i></span>
        Panduan menemukan ID Outlet dan ID Item
      </h3>
      <ul class="opn-guide-list">
        <li>Lihat pada nota laundry</li>
        <li>ID Outlet berada pada tulisan <code>REFXX#012345</code>, 1–2 digit kode outlet <b>XX</b></li>
        <li>ID Item berada pada tulisan <code>ID123-XXX</code>, 3 digit terakhir <b>XXX</b></li>
      </ul>
    </div>

    <div class="opn-panel">
      <h3 class="opn-panel__title">
        <span class="opn-ico"><i class="fas fa-search"></i></span>
        Cari Order
      </h3>
      <div class="opn-grid">
        <div>
          <label class="opn-label" for="opnIdCabang">ID Outlet</label>
          <input id="opnIdCabang" name="idCabang" class="opn-input" style="text-transform:uppercase"
            value="<?= htmlspecialchars((string) $idCabang) ?>" required autocomplete="off" />
        </div>
        <div>
          <label class="opn-label" for="opnIdOperan">ID Item</label>
          <input id="opnIdOperan" name="idOperan" class="opn-input"
            value="<?= htmlspecialchars((string) $idOperan) ?>" required autocomplete="off" inputmode="numeric" />
        </div>
        <div>
          <label class="opn-label">&nbsp;</label>
          <button type="button" id="opnBtnCek" class="opn-btn opn-btn--blue" onclick="loadDiv()">
            <i class="fas fa-search"></i> Cek
          </button>
        </div>
      </div>
    </div>

    <div id="load"></div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script src="<?= URL::IN_ASSETS ?>js/print_server.js?v=<?= time() ?>"></script>
<script>
  $(document).ready(function() {
    $("input[name=idCabang]").focus();
  });

  function loadDiv() {
    var idOperan = $("input[name=idOperan]").val().trim();
    var idCabang = $("input[name=idCabang]").val().trim();
    var $load = $("div#load");
    var $btn = $("#opnBtnCek");
    var prev = $btn.html();

    if (!idOperan || !idCabang) {
      $load.html('<div class="opn-error">Pengecekan ditolak — lengkapi ID Outlet dan ID Item.</div>');
      return;
    }

    $btn.prop("disabled", true).html('<span class="opn-spin" aria-hidden="true"></span> Memuat…');
    $load.html('<div class="opn-empty"><i class="fas fa-spinner fa-spin"></i> Memuat data…</div>');

    $load.load("<?= URL::BASE_URL ?>Operan/load/" + encodeURIComponent(idOperan) + "/" + encodeURIComponent(idCabang), function(response, status) {
      $btn.prop("disabled", false).html(prev);
      if (status === "error") {
        $load.html('<div class="opn-error">Gagal memuat data. Coba lagi.</div>');
        return;
      }
      var text = (typeof response === "string") ? $.trim(response) : "";
      if (text && text.indexOf("opn-result") === -1) {
        $load.html('<div class="opn-error">' + $("<div>").text(text).html() + "</div>");
      }
    });
  }

  $("input[name=idOperan], input[name=idCabang]").on("keypress", function(event) {
    if (event.keyCode == 13) {
      event.preventDefault();
      loadDiv();
    }
  });
</script>
