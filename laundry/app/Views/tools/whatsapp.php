<div class="content" id="wa-gateway-root">
  <style>
    #wa-gateway-root {
      --wg-ink: #0f172a;
      --wg-muted: #475569;
      --wg-line: #94a3b8;
      --wg-blue: #2563eb;
      --wg-green: #16a34a;
      --wg-yellow: #d97706;
      --wg-red: #dc2626;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #wa-gateway-root .wg-shell {
      max-width: 720px;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.1), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(22,163,74,.1), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f0fdf4 55%, #fff 100%);
      border: 1px solid #cbd5e1;
      border-radius: 0;
      padding: 16px;
    }
    #wa-gateway-root .wg-title {
      margin: 0 0 4px;
      font-weight: 900;
      font-size: 1.25rem;
      color: var(--wg-ink);
      letter-spacing: -0.02em;
    }
    #wa-gateway-root .wg-lead {
      margin: 0 0 16px;
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--wg-muted);
    }
    #wa-gateway-root .wg-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 14px;
    }
    @media (max-width: 560px) {
      #wa-gateway-root .wg-grid { grid-template-columns: 1fr; }
    }
    #wa-gateway-root .wg-card {
      border: 1px solid var(--wg-line);
      border-radius: 0;
      background: #fff;
      padding: 12px;
    }
    #wa-gateway-root .wg-card__label {
      font-size: 0.72rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--wg-muted);
      margin-bottom: 4px;
    }
    #wa-gateway-root .wg-card__value {
      font-size: 1rem;
      font-weight: 900;
      color: var(--wg-ink);
      word-break: break-word;
    }
    #wa-gateway-root .wg-badge {
      display: inline-block;
      border: 1px solid var(--wg-line);
      border-radius: 0;
      padding: 4px 10px;
      font-size: 0.78rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #wa-gateway-root .wg-badge--ok {
      background: #ecfdf5;
      border-color: #86efac;
      color: #15803d;
    }
    #wa-gateway-root .wg-badge--warn {
      background: #fffbeb;
      border-color: #fcd34d;
      color: #b45309;
    }
    #wa-gateway-root .wg-badge--err {
      background: #fef2f2;
      border-color: #fca5a5;
      color: #b91c1c;
    }
    #wa-gateway-root .wg-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 14px;
    }
    #wa-gateway-root .wg-btn {
      border: 1px solid var(--wg-line);
      border-radius: 0;
      background: #fff;
      color: var(--wg-ink);
      font-weight: 800;
      font-size: 0.85rem;
      padding: 8px 14px;
      cursor: pointer;
    }
    #wa-gateway-root .wg-btn:focus {
      outline: none;
      border-color: var(--wg-blue);
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
    }
    #wa-gateway-root .wg-btn--primary {
      background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
      border-color: #1d4ed8;
      color: #fff;
    }
    #wa-gateway-root .wg-btn--danger {
      background: #fff;
      border-color: #fca5a5;
      color: var(--wg-red);
    }
    #wa-gateway-root .wg-qr-panel {
      border: 1px solid var(--wg-line);
      border-radius: 0;
      background: #fff;
      padding: 16px;
      text-align: center;
    }
    #wa-gateway-root .wg-qr-panel.hidden { display: none; }
    #wa-gateway-root .wg-qr-wrap {
      display: inline-block;
      padding: 12px;
      border: 1px solid #e2e8f0;
      background: #fff;
      margin: 8px 0;
    }
    #wa-gateway-root #wg-qr-box {
      min-width: 280px;
      min-height: 280px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 12px;
    }
    #wa-gateway-root #wg-qr-box .wg-qr-placeholder {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--wg-muted);
      line-height: 1.45;
      max-width: 260px;
    }
    #wa-gateway-root #wg-qr-box img {
      display: block;
      width: 280px;
      height: 280px;
      image-rendering: pixelated;
    }
    #wa-gateway-root .wg-hint {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--wg-muted);
      margin: 0;
    }
    #wa-gateway-root .wg-error {
      border: 1px solid #fca5a5;
      background: #fef2f2;
      color: #991b1b;
      padding: 10px 12px;
      font-size: 0.85rem;
      font-weight: 700;
      border-radius: 0;
      margin-bottom: 12px;
      display: none;
    }
    #wa-gateway-root .wg-error.visible { display: block; }
    #wa-gateway-root .wg-connected-panel {
      border: 1px solid #86efac;
      background: #ecfdf5;
      padding: 14px;
      border-radius: 0;
      text-align: center;
    }
    #wa-gateway-root .wg-connected-panel.hidden { display: none; }
    #wa-gateway-root .wg-connected-panel i {
      font-size: 2rem;
      color: var(--wg-green);
      margin-bottom: 8px;
    }
    #wa-gateway-root .wg-connected-panel p {
      margin: 0;
      font-weight: 800;
      color: #166534;
    }
  </style>

  <div class="wg-shell">
    <h1 class="wg-title"><i class="fab fa-whatsapp text-success"></i> WhatsApp Gateway</h1>
    <p class="wg-lead">
      Login &amp; status <strong>fonnte_server</strong> (Baileys) — <strong>hanya kirim ke grup WA</strong>. Chat personal pelanggan lewat YCloud (line A/B).
    </p>

    <div id="wg-error" class="wg-error"></div>

    <div class="wg-grid">
      <div class="wg-card">
        <div class="wg-card__label">Status</div>
        <div class="wg-card__value"><span id="wg-status-badge" class="wg-badge wg-badge--warn">Memuat…</span></div>
      </div>
      <div class="wg-card">
        <div class="wg-card__label">Nomor device</div>
        <div class="wg-card__value" id="wg-device">—</div>
      </div>
      <div class="wg-card">
        <div class="wg-card__label">Gateway</div>
        <div class="wg-card__value" id="wg-gateway" style="font-size:0.82rem;font-weight:700;">—</div>
      </div>
      <div class="wg-card">
        <div class="wg-card__label">Webhook CRM</div>
        <div class="wg-card__value" id="wg-webhook">—</div>
      </div>
    </div>

    <div class="wg-actions">
      <button type="button" class="wg-btn wg-btn--primary" id="wg-btn-refresh"><i class="fas fa-sync-alt"></i> Refresh</button>
      <button type="button" class="wg-btn wg-btn--danger" id="wg-btn-logout"><i class="fas fa-sign-out-alt"></i> Logout / Scan ulang</button>
    </div>

    <div id="wg-connected" class="wg-connected-panel hidden">
      <i class="fab fa-whatsapp"></i>
      <p>WhatsApp terhubung</p>
      <p class="wg-hint mt-2" id="wg-connected-device"></p>
    </div>

    <div id="wg-qr-panel" class="wg-qr-panel">
      <p class="wg-hint mb-2"><strong>Scan QR</strong> dengan WhatsApp di HP (Linked Devices → Link a device)</p>
      <div class="wg-qr-wrap">
        <div id="wg-qr-box"></div>
      </div>
      <p class="wg-hint" id="wg-qr-hint">QR diperbarui otomatis setiap 5 detik selama belum terhubung.</p>
    </div>
  </div>
</div>

<script>
(function () {
  var statusUrl = '<?= URL::BASE_URL; ?>WaGateway/status';
  var logoutUrl = '<?= URL::BASE_URL; ?>WaGateway/logout';

  var pollTimer = null;
  var lastQrRendered = '';
  var $badge = $('#wg-status-badge');
  var $device = $('#wg-device');
  var $gateway = $('#wg-gateway');
  var $webhook = $('#wg-webhook');
  var $error = $('#wg-error');
  var $qrPanel = $('#wg-qr-panel');
  var $qrHint = $('#wg-qr-hint');
  var $connected = $('#wg-connected');
  var $connectedDevice = $('#wg-connected-device');

  function showError(msg) {
    if (!msg) {
      $error.removeClass('visible').text('');
      return;
    }
    $error.addClass('visible').text(msg);
  }

  function setBadge(state, connected) {
    $badge.removeClass('wg-badge--ok wg-badge--warn wg-badge--err');
    if (connected) {
      $badge.addClass('wg-badge--ok').text('Terhubung');
      return;
    }
    if (state === 'connecting') {
      $badge.addClass('wg-badge--warn').text('Menghubungkan…');
      return;
    }
    $badge.addClass('wg-badge--err').text('Offline');
  }

  function setQrPlaceholder(msg) {
    var box = document.getElementById('wg-qr-box');
    if (!box) return;
    box.innerHTML = '<div class="wg-qr-placeholder">' + msg + '</div>';
  }

  function renderQr(qrString) {
    if (!qrString) {
      setQrPlaceholder('Menunggu QR dari server…');
      return;
    }
    if (qrString === lastQrRendered) return;
    lastQrRendered = qrString;

    var box = document.getElementById('wg-qr-box');
    if (!box) return;
    box.innerHTML = '';

    var img = document.createElement('img');
    img.width = 280;
    img.height = 280;
    img.alt = 'WhatsApp QR Login';
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=1&data='
      + encodeURIComponent(qrString);
    img.onerror = function () {
      setQrPlaceholder('Gagal render QR. Klik Logout / Scan ulang lalu Refresh.');
    };
    box.appendChild(img);
    showError('');
  }

  function refreshStatus() {
    $.ajax({
      url: statusUrl,
      method: 'GET',
      dataType: 'json',
      timeout: 25000,
      cache: false
    }).done(function (res) {
      if (!res || !res.ok) {
        setBadge('close', false);
        $device.text('—');
        showError((res && res.message) || (res && res.error) || 'Gateway tidak merespons');
        $connected.addClass('hidden');
        $qrPanel.removeClass('hidden');
        setQrPlaceholder('Gateway offline atau fonnte_server belum jalan.');
        return;
      }

      var connected = !!res.connected;
      var state = res.state || 'unknown';
      setBadge(state, connected);
      $device.text(res.device || '—');
      $gateway.text(res.gateway || '—');
      $webhook.text(res.webhook ? 'Aktif' : 'Tidak dikonfigurasi');

      if (connected) {
        showError('');
        $connected.removeClass('hidden');
        $qrPanel.addClass('hidden');
        $connectedDevice.text(res.device ? ('Nomor: ' + res.device) : '');
        stopPoll();
        return;
      }

      $connected.addClass('hidden');
      $qrPanel.removeClass('hidden');

      if (res.qr) {
        $qrHint.text('Scan QR dengan WhatsApp → Perangkat Tertaut → Tautkan perangkat.');
        renderQr(res.qr);
        showError('');
      } else {
        lastQrRendered = '';
        var hint = res.qr_hint || 'QR belum tersedia. Klik Logout / Scan ulang jika tidak muncul.';
        $qrHint.text(hint);
        setQrPlaceholder(hint);
        if (state === 'connecting') {
          showError('');
        }
      }

      startPoll();
    }).fail(function (xhr) {
      setBadge('close', false);
      var msg = 'Gagal memuat status. Pastikan fonnte_server & API berjalan.';
      try {
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
      } catch (e) {}
      showError(msg);
      setQrPlaceholder(msg);
    });
  }

  function startPoll() {
    stopPoll();
    pollTimer = setInterval(function () {
      refreshStatus();
    }, 5000);
  }

  function stopPoll() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  $('#wg-btn-refresh').on('click', function () {
    refreshStatus();
  });

  $('#wg-btn-logout').on('click', function () {
    if (typeof OpModal !== 'undefined') {
      OpModal.confirm({
        title: 'Logout WhatsApp?',
        body: 'Sesi Baileys akan direset. Scan QR baru untuk hubungkan lagi.',
        confirmText: 'Logout',
        onConfirm: doLogout
      });
    } else if (window.confirm('Logout WhatsApp? Sesi akan direset dan perlu scan QR lagi.')) {
      doLogout();
    }
  });

  function wgToast(msg, type) {
    if (window.MdlToast) {
      if (type === 'ok' && MdlToast.ok) return MdlToast.ok(msg);
      if (type === 'error' && MdlToast.error) return MdlToast.error(msg);
      if (MdlToast.info) return MdlToast.info(msg);
    }
  }

  function doLogout() {
    lastQrRendered = '';
    setQrPlaceholder('Mereset sesi WhatsApp…');
    $.ajax({
      url: logoutUrl,
      method: 'POST',
      contentType: 'application/json',
      data: '{}',
      dataType: 'json',
      timeout: 30000
    }).done(function (res) {
      if (res && res.ok) {
        wgToast('Sesi direset — tunggu QR baru muncul', 'ok');
        setTimeout(function () {
          refreshStatus();
          startPoll();
        }, 2000);
      } else {
        showError((res && res.message) || 'Logout gagal — coba reset manual di VPS: rm -rf node/fonnte_server/auth/*');
      }
    }).fail(function () {
      showError('Logout gagal — cek API/fonnte_server. Manual: rm -rf node/fonnte_server/auth/* lalu restart pm2');
    });
  }

  refreshStatus();

  $(window).on('beforeunload', stopPoll);
})();
</script>
