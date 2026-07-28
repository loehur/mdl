<?php
$kodeCabang = strtoupper((string) ($this->dCabang['kode_cabang'] ?? ''));
?>
<div id="pl-root">
  <style>
    #pl-root {
      --pl-ink: #0f172a;
      --pl-muted: #1e293b;
      --pl-line: #94a3b8;
      --pl-blue: #2563eb;
      --pl-blue-deep: #1d4ed8;
      --pl-green: #16a34a;
      --pl-green-deep: #15803d;
      --pl-yellow: #f59e0b;
      --pl-yellow-deep: #d97706;
      --pl-red: #dc2626;
      max-width: 1100px;
      width: 100%;
      margin: 8px 0 24px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #pl-root,
    #pl-root button,
    #pl-root input,
    #pl-root .pl-panel,
    #pl-root .pl-card,
    #pl-root code {
      border-radius: 0 !important;
    }
    #pl-root .pl-shell {
      min-width: 0;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.12), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f4fff8 55%, #fff8eb 100%);
      border: 1px solid #cbd5e1;
      padding: 14px;
    }
    #pl-root .pl-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: -14px -14px 14px;
      padding: 14px 16px;
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 35%, #16a34a 70%, #f59e0b 100%);
      color: #fff;
    }
    #pl-root .pl-head h2 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #pl-root .pl-head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 750;
      opacity: 0.95;
    }
    #pl-root .pl-cabang {
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
    #pl-root .pl-panel {
      border: 1px solid #93c5fd;
      background: linear-gradient(180deg, #eff6ff, #fff);
      padding: 14px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
      margin-bottom: 12px;
    }
    #pl-root .pl-panel--guide {
      border-color: #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #pl-root .pl-panel__title {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0 0 12px;
      font-size: 0.95rem;
      font-weight: 900;
      color: var(--pl-ink);
    }
    #pl-root .pl-ico {
      width: 30px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--pl-blue);
      color: #fff;
      flex: 0 0 auto;
    }
    #pl-root .pl-panel--guide .pl-ico {
      background: var(--pl-yellow);
      color: #111;
    }
    #pl-root .pl-guide-list {
      margin: 0;
      padding: 0 0 0 18px;
      color: var(--pl-ink);
      font-size: 0.86rem;
      font-weight: 750;
      line-height: 1.5;
    }
    #pl-root .pl-guide-list li { margin-bottom: 6px; }
    #pl-root .pl-guide-list li:last-child { margin-bottom: 0; }
    #pl-root .pl-guide-list code {
      display: inline-block;
      padding: 1px 6px;
      border: 1px solid #fcd34d;
      background: #fff;
      color: #0f172a;
      font-family: 'fontku', 'Segoe UI', Consolas, monospace;
      font-size: 0.82rem;
      font-weight: 900;
    }
    #pl-root .pl-guide-list b { color: var(--pl-yellow-deep); font-weight: 900; }
    #pl-root .pl-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
      align-items: end;
    }
    @media (min-width: 720px) {
      #pl-root .pl-grid { grid-template-columns: 1fr 1fr auto; }
    }
    #pl-root .pl-label {
      display: block;
      margin-bottom: 6px;
      font-size: 0.78rem;
      font-weight: 900;
      color: var(--pl-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #pl-root .pl-input {
      width: 100%;
      box-sizing: border-box;
      border: 1px solid var(--pl-line);
      background: #fff;
      color: var(--pl-ink);
      font-size: 0.92rem;
      font-weight: 800;
      padding: 10px 12px;
      min-height: 42px;
    }
    #pl-root .pl-input:focus {
      outline: none;
      border-color: var(--pl-blue);
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
    }
    #pl-root .pl-btn {
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
    #pl-root .pl-btn--blue {
      background: linear-gradient(180deg, var(--pl-blue), var(--pl-blue-deep));
      color: #fff;
    }
    #pl-root .pl-btn--primary {
      background: linear-gradient(180deg, var(--pl-green), var(--pl-green-deep));
      color: #fff;
    }
    #pl-root .pl-btn:disabled { opacity: 0.55; cursor: not-allowed; }
    #pl-root .pl-spin {
      width: 14px;
      height: 14px;
      border: 2px solid rgba(255,255,255,.35);
      border-top-color: #fff;
      border-radius: 50%;
      animation: pl-spin 0.7s linear infinite;
    }
    @keyframes pl-spin { to { transform: rotate(360deg); } }
    #pl-root .pl-empty,
    #pl-root .pl-error {
      border: 1px solid #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
      color: var(--pl-ink);
      padding: 14px;
      font-size: 0.88rem;
      font-weight: 800;
      margin-bottom: 12px;
    }
    #pl-root .pl-error {
      border-color: #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
      color: #b91c1c;
    }
  </style>

  <div class="pl-shell">
    <div class="pl-head">
      <div>
        <h2><i class="fas fa-tag"></i> Pack Label</h2>
        <small>Cari order lewat ID Outlet + ID Item, lalu cetak label pelanggan</small>
      </div>
      <?php if ($kodeCabang !== '') { ?>
        <span class="pl-cabang"><?= htmlspecialchars($kodeCabang) ?></span>
      <?php } ?>
    </div>

    <div class="pl-panel pl-panel--guide">
      <h3 class="pl-panel__title">
        <span class="pl-ico"><i class="fas fa-lightbulb"></i></span>
        Panduan menemukan ID Outlet dan ID Item
      </h3>
      <ul class="pl-guide-list">
        <li>Lihat pada nota laundry</li>
        <li>ID Outlet berada pada tulisan <code>REFXX#012345</code>, 1–2 digit kode outlet <b>XX</b></li>
        <li>ID Item berada pada tulisan <code>ID123-XXX</code>, 3 digit terakhir <b>XXX</b></li>
      </ul>
    </div>

    <div class="pl-panel">
      <h3 class="pl-panel__title">
        <span class="pl-ico"><i class="fas fa-search"></i></span>
        Cari Order
      </h3>
      <div class="pl-grid">
        <div>
          <label class="pl-label" for="plIdOutlet">ID Outlet</label>
          <input id="plIdOutlet" name="idOutlet" class="pl-input" style="text-transform:uppercase" autocomplete="off" required />
        </div>
        <div>
          <label class="pl-label" for="plIdItem">ID Item</label>
          <input id="plIdItem" name="idItem" class="pl-input" inputmode="numeric" autocomplete="off" required />
        </div>
        <div>
          <label class="pl-label">&nbsp;</label>
          <button type="button" id="plBtnCek" class="pl-btn pl-btn--blue" onclick="plLoad()">
            <i class="fas fa-search"></i> Cek
          </button>
        </div>
      </div>
    </div>

    <div id="plLoad"></div>
  </div>
</div>

<script src="<?= URL::IN_ASSETS ?>js/print_server.js?v=<?= time() ?>"></script>
<script>
  $(document).ready(function() {
    $("#plIdOutlet").focus();
  });

  function plLoad() {
    var idItem = $("input[name=idItem]").val().trim();
    var idOutlet = $("input[name=idOutlet]").val().trim();
    var $load = $("#plLoad");
    var $btn = $("#plBtnCek");
    var prev = $btn.html();

    if (!idItem || !idOutlet) {
      if (window.MdlToast) MdlToast.warn("Lengkapi ID Outlet dan ID Item");
      $load.html('<div class="pl-error">Lengkapi ID Outlet dan ID Item.</div>');
      return;
    }

    $btn.prop("disabled", true).html('<span class="pl-spin" aria-hidden="true"></span> Memuat…');
    $load.html('<div class="pl-empty"><i class="fas fa-spinner fa-spin"></i> Memuat data…</div>');

    $load.load(
      "<?= URL::BASE_URL ?>PackLabel/load/" + encodeURIComponent(idItem) + "/" + encodeURIComponent(idOutlet),
      function(response, status) {
        $btn.prop("disabled", false).html(prev);
        if (status === "error") {
          $load.html('<div class="pl-error">Gagal memuat data. Coba lagi.</div>');
          return;
        }
        var text = (typeof response === "string") ? $.trim(response) : "";
        if (text && text.indexOf("pl-result") === -1) {
          $load.html('<div class="pl-error">' + $("<div>").text(text).html() + "</div>");
          if (window.MdlToast) MdlToast.error(text);
        }
      }
    );
  }

  $("input[name=idItem], input[name=idOutlet]").on("keypress", function(e) {
    if (e.keyCode == 13) {
      e.preventDefault();
      plLoad();
    }
  });
</script>
