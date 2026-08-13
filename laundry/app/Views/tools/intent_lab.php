<div class="content" id="intent-lab-root">
  <style>
    #intent-lab-root {
      --il-ink: #0f172a;
      --il-muted: #1e293b;
      --il-line: #94a3b8;
      --il-blue: #2563eb;
      --il-blue-deep: #1d4ed8;
      --il-green: #16a34a;
      --il-green-deep: #15803d;
      --il-yellow: #f59e0b;
      --il-yellow-deep: #d97706;
      --il-red: #dc2626;
      --il-cyan: #0891b2;
      font-family: 'fontku', 'Segoe UI', sans-serif;
      position: relative;
    }
    #intent-lab-root .il-shell {
      max-width: 860px;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(8,145,178,.12), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f0fdfa 55%, #fff 100%);
      border: 1px solid #cbd5e1;
      border-radius: 0;
      padding: 16px;
      position: relative;
    }
    #intent-lab-root .il-title {
      color: var(--il-ink);
      font-weight: 900;
      font-size: 1.25rem;
      letter-spacing: -0.02em;
      margin: 0 0 6px;
    }
    #intent-lab-root .il-lead {
      color: var(--il-muted);
      font-size: 0.9rem;
      font-weight: 600;
      margin: 0 0 14px;
    }
    #intent-lab-root .il-label {
      display: block;
      color: var(--il-muted);
      font-weight: 800;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 6px;
    }
    #intent-lab-root .il-textarea,
    #intent-lab-root .il-select {
      width: 100%;
      border: 1px solid var(--il-line);
      border-radius: 0;
      padding: 10px 12px;
      font-weight: 600;
      color: var(--il-ink);
      background: #fff;
    }
    #intent-lab-root .il-textarea {
      min-height: 110px;
      resize: vertical;
    }
    #intent-lab-root .il-textarea:focus,
    #intent-lab-root .il-select:focus {
      outline: none;
      border-color: var(--il-blue);
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25);
    }
    #intent-lab-root .il-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
      align-items: center;
    }
    #intent-lab-root .il-btn {
      border: 1px solid transparent;
      border-radius: 0;
      font-weight: 800;
      padding: 9px 14px;
      cursor: pointer;
      color: #fff;
    }
    #intent-lab-root .il-btn:disabled { opacity: 0.55; cursor: not-allowed; }
    #intent-lab-root .il-btn--run {
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
      border-color: #1d4ed8;
    }
    #intent-lab-root .il-btn--teach {
      background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
      border-color: #15803d;
    }
    #intent-lab-root .il-btn--apply {
      background: linear-gradient(105deg, #0e7490 0%, #0891b2 100%);
      border-color: #0e7490;
    }
    #intent-lab-root .il-btn--ghost {
      background: #fff;
      color: var(--il-ink);
      border-color: #cbd5e1;
    }
    #intent-lab-root .il-result,
    #intent-lab-root .il-teach-box { margin-top: 14px; display: none; }
    #intent-lab-root .il-result.is-show,
    #intent-lab-root .il-teach-box.is-show { display: block; }
    #intent-lab-root .il-card {
      border: 1px solid #93c5fd;
      background: linear-gradient(180deg, #eff6ff, #fff);
      border-radius: 0;
      padding: 12px 14px;
      margin-bottom: 10px;
    }
    #intent-lab-root .il-card--ok {
      border-color: #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
    }
    #intent-lab-root .il-card--err {
      border-color: #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
    }
    #intent-lab-root .il-card--teach {
      border-color: #67e8f9;
      background: linear-gradient(180deg, #ecfeff, #fff);
    }
    #intent-lab-root .il-intent {
      font-size: 1.35rem;
      font-weight: 900;
      color: var(--il-ink);
      letter-spacing: -0.02em;
      margin: 0 0 8px;
    }
    #intent-lab-root .il-meta { display: flex; flex-wrap: wrap; gap: 6px; }
    #intent-lab-root .il-badge {
      border: 1px solid #cbd5e1;
      background: #f8fafc;
      color: var(--il-muted);
      font-weight: 800;
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 0;
    }
    #intent-lab-root .il-badge--case2 { border-color: #fcd34d; background: #fffbeb; color: var(--il-yellow-deep); }
    #intent-lab-root .il-badge--case3 { border-color: #93c5fd; background: #eff6ff; color: var(--il-blue-deep); }
    #intent-lab-root .il-badge--case4 { border-color: #fca5a5; background: #fef2f2; color: var(--il-red); }
    #intent-lab-root .il-badge--src { border-color: #67e8f9; background: #ecfeff; color: #0e7490; }
    #intent-lab-root .il-badge--ok { border-color: #86efac; background: #f0fdf4; color: var(--il-green-deep); }
    #intent-lab-root .il-badge--warn { border-color: #fcd34d; background: #fffbeb; color: var(--il-yellow-deep); }
    #intent-lab-root .il-trace {
      margin: 0;
      padding: 10px;
      background: #0f172a;
      color: #e2e8f0;
      border: 1px solid #1e293b;
      border-radius: 0;
      font-size: 0.78rem;
      line-height: 1.45;
      max-height: 280px;
      overflow: auto;
      white-space: pre-wrap;
      word-break: break-word;
    }
    #intent-lab-root .il-section {
      margin-top: 18px;
      padding-top: 14px;
      border-top: 1px solid #cbd5e1;
    }
    #intent-lab-root .il-section-title {
      font-weight: 900;
      color: var(--il-ink);
      margin: 0 0 6px;
      font-size: 1.05rem;
    }
    #intent-lab-root .il-check {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
      color: var(--il-muted);
      margin-top: 8px;
    }
    #intent-lab-root .il-check input { width: 16px; height: 16px; }
    #intent-lab-root .il-row { display: grid; grid-template-columns: 1fr; gap: 10px; }
    @media (min-width: 720px) {
      #intent-lab-root .il-row--2 { grid-template-columns: 1fr 1fr; }
    }
    #intent-lab-root .il-loading {
      display: none;
      position: absolute;
      inset: 0;
      z-index: 5;
      background: rgba(15, 23, 42, 0.42);
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #intent-lab-root.is-loading .il-loading { display: flex; }
    #intent-lab-root .il-loading__box {
      width: 100%;
      max-width: 320px;
      background: linear-gradient(180deg, #eff6ff, #fff);
      border: 1px solid #93c5fd;
      border-radius: 0;
      padding: 18px 16px;
      text-align: center;
      box-shadow: 0 10px 28px rgba(15, 23, 42, 0.22);
    }
    #intent-lab-root .il-loading__spin {
      width: 36px; height: 36px; margin: 0 auto 10px;
      border: 3px solid #bfdbfe; border-top-color: #2563eb; border-radius: 0;
      animation: il-spin 0.7s linear infinite;
    }
    #intent-lab-root .il-loading__title {
      margin: 0 0 4px; font-weight: 900; color: var(--il-blue-deep); font-size: 1rem;
    }
    #intent-lab-root .il-loading__sub {
      margin: 0; font-weight: 600; color: var(--il-muted); font-size: 0.82rem;
    }
    @keyframes il-spin { to { transform: rotate(360deg); } }
  </style>

  <?php $intentOptions = $data['intents'] ?? []; ?>

  <div class="il-shell">
    <div class="il-loading" id="ilLoading" aria-live="polite" aria-busy="false">
      <div class="il-loading__box">
        <div class="il-loading__spin" aria-hidden="true"></div>
        <p class="il-loading__title" id="ilLoadingTitle">Menganalisis intent…</p>
        <p class="il-loading__sub" id="ilLoadingSub">Regex + AI, mohon tunggu sebentar</p>
      </div>
    </div>

    <h3 class="il-title"><i class="fas fa-flask"></i> Intent Lab</h3>
    <p class="il-lead">Tempel pesan customer — cek intent, lalu ajarin intent target (AI usulkan pattern + prompt).</p>

    <label class="il-label" for="ilText">Pesan chat</label>
    <textarea id="ilText" class="il-textarea" placeholder="Contoh: mksh byk kak"></textarea>

    <div class="il-actions">
      <button type="button" class="il-btn il-btn--run" id="ilBtnRun">
        <i class="fas fa-search"></i> Cek Intent
      </button>
      <button type="button" class="il-btn il-btn--ghost" id="ilBtnClear">Hapus</button>
    </div>

    <div class="il-result" id="ilResult">
      <div class="il-card" id="ilCard">
        <div class="il-intent" id="ilIntent">—</div>
        <div class="il-meta" id="ilMeta"></div>
      </div>
      <label class="il-label">Trace</label>
      <pre class="il-trace" id="ilTrace"></pre>
    </div>

    <div class="il-section">
      <h4 class="il-section-title"><i class="fas fa-graduation-cap"></i> Ajarkan Intent</h4>
      <p class="il-lead" style="margin-bottom:10px">Pilih intent yang diinginkan untuk kalimat di atas. AI mengusulkan regex + contoh prompt; review lalu aktifkan ke DB.</p>

      <div class="il-row il-row--2">
        <div>
          <label class="il-label" for="ilTeachIntent">Intent target</label>
          <select id="ilTeachIntent" class="il-select">
            <option value="">— pilih —</option>
            <?php foreach ($intentOptions as $opt): ?>
              <option value="<?= htmlspecialchars($opt['code']) ?>"><?= htmlspecialchars($opt['code']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-flex align-items-end">
          <div class="il-actions" style="margin-top:0;width:100%">
            <button type="button" class="il-btn il-btn--teach" id="ilBtnPropose">
              <i class="fas fa-magic"></i> Usulkan (AI)
            </button>
          </div>
        </div>
      </div>

      <div class="il-teach-box" id="ilTeachBox">
        <div class="il-card il-card--teach">
          <div class="il-meta" id="ilTeachMeta" style="margin-bottom:10px"></div>
          <p id="ilTeachReason" style="font-weight:700;color:#334155;margin:0 0 10px"></p>

          <label class="il-label" for="ilTeachPattern">Pattern (PHP regex)</label>
          <textarea id="ilTeachPattern" class="il-textarea" style="min-height:70px;font-family:ui-monospace,Consolas,monospace;font-size:.85rem"></textarea>

          <label class="il-label" for="ilTeachPrompt" style="margin-top:10px">Tambahan AI prompt</label>
          <textarea id="ilTeachPrompt" class="il-textarea" style="min-height:60px"></textarea>

          <label class="il-check">
            <input type="checkbox" id="ilAddPattern" checked>
            Tambah pattern ke DB
          </label>
          <label class="il-check">
            <input type="checkbox" id="ilUpdatePrompt" checked>
            Append ke ai_prompt intent
          </label>

          <div class="il-actions">
            <button type="button" class="il-btn il-btn--apply" id="ilBtnApply">
              <i class="fas fa-check"></i> Aktifkan
            </button>
            <button type="button" class="il-btn il-btn--ghost" id="ilBtnRecheck">Cek ulang</button>
          </div>
          <div id="ilApplyMsg" style="margin-top:10px;font-weight:800"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  function boot() {
    if (typeof window.jQuery === 'undefined') {
      console.error('Intent Lab: jQuery belum siap');
      return;
    }
    var $ = window.jQuery;
    var $root = $('#intent-lab-root');
    if (!$root.length) return;

    var $text = $('#ilText');
    var $result = $('#ilResult');
    var $card = $('#ilCard');
    var $intent = $('#ilIntent');
    var $meta = $('#ilMeta');
    var $trace = $('#ilTrace');
    var $btn = $('#ilBtnRun');
    var $loading = $('#ilLoading');
    var $teachIntent = $('#ilTeachIntent');
    var $teachBox = $('#ilTeachBox');
    var $teachMeta = $('#ilTeachMeta');
    var $teachReason = $('#ilTeachReason');
    var $teachPattern = $('#ilTeachPattern');
    var $teachPrompt = $('#ilTeachPrompt');
    var $applyMsg = $('#ilApplyMsg');

    var checkUrl = '<?= URL::BASE_URL; ?>IntentLab/check';
    var proposeUrl = '<?= URL::BASE_URL; ?>IntentLab/proposeTeach';
    var applyUrl = '<?= URL::BASE_URL; ?>IntentLab/applyTeach';
    var running = false;

    function toast(msg, kind) {
      if (window.MdlToast) {
        if (kind === 'err' && MdlToast.error) return MdlToast.error(msg);
        if (kind === 'warn' && MdlToast.warn) return MdlToast.warn(msg);
        if (MdlToast.info) return MdlToast.info(msg);
      }
      alert(msg);
    }

    function setLoading(on, title, sub) {
      running = !!on;
      $root.toggleClass('is-loading', running);
      $loading.attr('aria-busy', running ? 'true' : 'false');
      $('#ilBtnRun, #ilBtnPropose, #ilBtnApply').prop('disabled', running);
      if (title) $('#ilLoadingTitle').text(title);
      if (sub) $('#ilLoadingSub').text(sub);
      if (!running) {
        $btn.html('<i class="fas fa-search"></i> Cek Intent');
        $('#ilBtnPropose').html('<i class="fas fa-magic"></i> Usulkan (AI)');
        $('#ilBtnApply').html('<i class="fas fa-check"></i> Aktifkan');
      }
    }

    function caseBadge(c) {
      if (c === null || c === undefined || c === '' || Number(c) === 0) {
        return '<span class="il-badge">case: null</span>';
      }
      var n = Number(c);
      var cls = 'il-badge';
      if (n === 2) cls += ' il-badge--case2';
      else if (n === 3) cls += ' il-badge--case3';
      else if (n === 4) cls += ' il-badge--case4';
      return '<span class="' + cls + '">case: ' + n + '</span>';
    }

    function showResult(data) {
      $result.addClass('is-show');
      $card.removeClass('il-card--ok il-card--err');
      if (!data || !(data.ok === 1 || data.ok === true)) {
        $card.addClass('il-card--err');
        $intent.text('Gagal');
        $meta.html('');
        $trace.text((data && (data.message || data.error)) ? (data.message || data.error) : 'Unknown error');
        return;
      }
      $card.addClass('il-card--ok');
      $intent.text(data.intent || '—');
      var bits = [];
      bits.push('<span class="il-badge il-badge--src">source: ' + (data.source || '—') + '</span>');
      bits.push(caseBadge(data.case));
      bits.push('<span class="il-badge">notify: ' + (data.notify ? 'true' : 'false') + '</span>');
      if (data.ask === true || data.ask === false) {
        bits.push('<span class="il-badge">ask: ' + (data.ask ? 'true' : 'false') + '</span>');
      }
      if (data.no_handler) bits.push('<span class="il-badge">no_handler</span>');
      $meta.html(bits.join(''));
      var tr = data.trace;
      if (Array.isArray(tr)) $trace.text(tr.length ? tr.join('\n') : '(kosong)');
      else $trace.text(tr ? String(tr) : '(kosong)');

      var got = String(data.intent || '').toUpperCase();
      if (got && got !== 'FALSE' && got !== 'NONE' && $teachIntent.find('option[value="' + got + '"]').length) {
        if (!$teachIntent.val()) $teachIntent.val(got);
      }
    }

    function runCheck() {
      if (running) return;
      var text = $.trim($text.val() || '');
      if (!text) { toast('Isi teks pesan dulu', 'warn'); $text.focus(); return; }
      setLoading(true, 'Menganalisis intent…', 'Regex + AI, mohon tunggu sebentar');
      $btn.html('<i class="fas fa-spinner fa-spin"></i> Mengecek…');
      $.ajax({
        url: checkUrl, type: 'POST', data: { text: text }, dataType: 'json', timeout: 70000
      }).done(function (res) {
        showResult(res || {});
      }).fail(function (xhr) {
        var msg = 'Request gagal';
        if (xhr.statusText === 'timeout') msg = 'Timeout — API terlalu lama merespons';
        else {
          try {
            var j = JSON.parse(xhr.responseText || '{}');
            if (j.message) msg = j.message;
            else if (j.error) msg = j.error;
          } catch (e) { if (xhr.status) msg = 'HTTP ' + xhr.status; }
        }
        showResult({ ok: 0, message: msg });
        toast(msg, 'err');
      }).always(function () { setLoading(false); });
    }

    function runPropose() {
      if (running) return;
      var text = $.trim($text.val() || '');
      var intent = $.trim($teachIntent.val() || '');
      if (!text) { toast('Isi teks pesan dulu', 'warn'); return; }
      if (!intent) { toast('Pilih intent target', 'warn'); return; }
      setLoading(true, 'AI menyusun usulan…', 'Pattern regex + potongan prompt');
      $('#ilBtnPropose').html('<i class="fas fa-spinner fa-spin"></i> Menyusun…');
      $applyMsg.text('');
      $.ajax({
        url: proposeUrl,
        type: 'POST',
        contentType: 'application/json; charset=utf-8',
        data: JSON.stringify({ text: text, intent: intent }),
        dataType: 'json',
        timeout: 90000
      }).done(function (res) {
        if (!(res && (res.ok === 1 || res.ok === true))) {
          toast((res && (res.message || res.error)) || 'Usulan gagal', 'err');
          return;
        }
        $teachBox.addClass('is-show');
        var bits = [];
        bits.push('<span class="il-badge il-badge--src">target: ' + (res.intent || intent) + '</span>');
        bits.push(res.matches_text
          ? '<span class="il-badge il-badge--ok">match contoh: ya</span>'
          : '<span class="il-badge il-badge--warn">match contoh: tidak</span>');
        if (res.pattern_exists || res.already_covered) {
          bits.push('<span class="il-badge il-badge--warn">pattern sudah ada / tercakup</span>');
        }
        $teachMeta.html(bits.join(''));
        $teachReason.text(res.reason || '');
        $teachPattern.val(res.pattern || '');
        $teachPrompt.val(res.prompt_append || '');
        if (res.already_covered) {
          $('#ilAddPattern').prop('checked', false);
          $('#ilUpdatePrompt').prop('checked', true);
        } else {
          $('#ilAddPattern').prop('checked', true);
          $('#ilUpdatePrompt').prop('checked', true);
        }
      }).fail(function (xhr) {
        var msg = 'Usulan gagal';
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (j.message) msg = j.message;
        } catch (e) {}
        toast(msg, 'err');
      }).always(function () { setLoading(false); });
    }

    function runApply() {
      if (running) return;
      var text = $.trim($text.val() || '');
      var intent = $.trim($teachIntent.val() || '');
      var pattern = $.trim($teachPattern.val() || '');
      var promptAppend = $.trim($teachPrompt.val() || '');
      var addPattern = $('#ilAddPattern').is(':checked') ? 1 : 0;
      var updatePrompt = $('#ilUpdatePrompt').is(':checked') ? 1 : 0;
      if (!text || !intent) { toast('Teks dan intent wajib', 'warn'); return; }
      if (addPattern && !pattern) { toast('Isi pattern atau matikan centang Tambah pattern', 'warn'); return; }
      if (!addPattern && !updatePrompt) { toast('Centang minimal satu aksi', 'warn'); return; }
      setLoading(true, 'Mengaktifkan ke DB…', 'Simpan pattern/prompt lalu verifikasi');
      $('#ilBtnApply').html('<i class="fas fa-spinner fa-spin"></i> Mengaktifkan…');
      $.ajax({
        url: applyUrl,
        type: 'POST',
        contentType: 'application/json; charset=utf-8',
        data: JSON.stringify({
          text: text,
          intent: intent,
          pattern: pattern,
          prompt_append: promptAppend,
          add_pattern: addPattern,
          update_prompt: updatePrompt
        }),
        dataType: 'json',
        timeout: 90000
      }).done(function (res) {
        if (!(res && (res.ok === 1 || res.ok === true))) {
          $applyMsg.css('color', '#dc2626').text((res && res.message) || 'Gagal aktifkan');
          toast((res && res.message) || 'Gagal aktifkan', 'err');
          return;
        }
        var msg = 'Aktif.';
        if (res.pattern_added) msg += ' Pattern ditambah.';
        if (res.prompt_updated) msg += ' Prompt diupdate.';
        if (res.verify_ok) msg += ' Verifikasi: intent = ' + (res.verify_intent || intent);
        else msg += ' Verifikasi: dapat ' + (res.verify_intent || '—') + ' (target ' + intent + ').';
        $applyMsg.css('color', res.verify_ok ? '#15803d' : '#b45309').text(msg);
        if (res.verify) showResult(res.verify);
        toast(res.verify_ok ? 'Berhasil diajarkan' : 'Tersimpan, verifikasi belum pas', res.verify_ok ? 'info' : 'warn');
      }).fail(function () {
        toast('Request aktifkan gagal', 'err');
      }).always(function () { setLoading(false); });
    }

    $btn.off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runCheck(); });
    $('#ilBtnPropose').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runPropose(); });
    $('#ilBtnApply').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runApply(); });
    $('#ilBtnRecheck').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runCheck(); });
    $('#ilBtnClear').off('click.intentLab').on('click.intentLab', function (e) {
      e.preventDefault();
      $text.val('');
      $result.removeClass('is-show');
      $teachBox.removeClass('is-show');
      $applyMsg.text('');
    });
    $text.off('keydown.intentLab').on('keydown.intentLab', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'Enter' || e.keyCode === 13)) {
        e.preventDefault();
        runCheck();
      }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>
