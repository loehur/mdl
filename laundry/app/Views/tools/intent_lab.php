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
    }
    #intent-lab-root .il-shell {
      max-width: 820px;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(8,145,178,.12), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f0fdfa 55%, #fff 100%);
      border: 1px solid #cbd5e1;
      border-radius: 0;
      padding: 16px;
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
    #intent-lab-root .il-textarea {
      width: 100%;
      min-height: 110px;
      border: 1px solid var(--il-line);
      border-radius: 0;
      padding: 10px 12px;
      font-weight: 600;
      color: var(--il-ink);
      background: #fff;
      resize: vertical;
    }
    #intent-lab-root .il-textarea:focus {
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
    #intent-lab-root .il-btn:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }
    #intent-lab-root .il-btn--run {
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
      border-color: #1d4ed8;
    }
    #intent-lab-root .il-btn--ghost {
      background: #fff;
      color: var(--il-ink);
      border-color: #cbd5e1;
    }
    #intent-lab-root .il-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin: 12px 0 0;
    }
    #intent-lab-root .il-chip {
      border: 1px solid #93c5fd;
      background: #eff6ff;
      color: var(--il-blue-deep);
      font-weight: 700;
      font-size: 0.78rem;
      padding: 5px 8px;
      border-radius: 0;
      cursor: pointer;
    }
    #intent-lab-root .il-chip:hover {
      border-color: var(--il-blue);
      background: #dbeafe;
    }
    #intent-lab-root .il-result {
      margin-top: 14px;
      display: none;
    }
    #intent-lab-root .il-result.is-show {
      display: block;
    }
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
    #intent-lab-root .il-intent {
      font-size: 1.35rem;
      font-weight: 900;
      color: var(--il-ink);
      letter-spacing: -0.02em;
      margin: 0 0 8px;
    }
    #intent-lab-root .il-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }
    #intent-lab-root .il-badge {
      border: 1px solid #cbd5e1;
      background: #f8fafc;
      color: var(--il-muted);
      font-weight: 800;
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 0;
    }
    #intent-lab-root .il-badge--case2 {
      border-color: #fcd34d;
      background: #fffbeb;
      color: var(--il-yellow-deep);
    }
    #intent-lab-root .il-badge--case3 {
      border-color: #93c5fd;
      background: #eff6ff;
      color: var(--il-blue-deep);
    }
    #intent-lab-root .il-badge--case4 {
      border-color: #fca5a5;
      background: #fef2f2;
      color: var(--il-red);
    }
    #intent-lab-root .il-badge--src {
      border-color: #67e8f9;
      background: #ecfeff;
      color: #0e7490;
    }
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
    #intent-lab-root .il-err {
      color: var(--il-red);
      font-weight: 700;
      margin: 0;
    }
  </style>

  <div class="il-shell">
    <h3 class="il-title"><i class="fas fa-flask"></i> Intent Lab</h3>
    <p class="il-lead">Tempel pesan customer — lihat intent, case CRM, dan jejak klasifikasi (dry-run, tanpa kirim WA).</p>

    <label class="il-label" for="ilText">Pesan chat</label>
    <textarea id="ilText" class="il-textarea" placeholder="Contoh: udah sm ongkir ni kak?"></textarea>

    <div class="il-chips" id="ilChips">
      <button type="button" class="il-chip" data-text="udah sm ongkir ni kak?">Ongkir?</button>
      <button type="button" class="il-chip" data-text="tolong jemput laundry ya">Jemput</button>
      <button type="button" class="il-chip" data-text="besok antar ya kak">Antar</button>
      <button type="button" class="il-chip" data-text="cek status laundry saya">Status</button>
      <button type="button" class="il-chip" data-text="halo kak">Halo</button>
      <button type="button" class="il-chip" data-text="📍 Shared Location -0.123,100.456">Shareloc</button>
      <button type="button" class="il-chip" data-text="berapa harga cuci setrika per kilo?">Harga</button>
    </div>

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
  </div>

  <script>
  (function () {
    var $text = $('#ilText');
    var $result = $('#ilResult');
    var $card = $('#ilCard');
    var $intent = $('#ilIntent');
    var $meta = $('#ilMeta');
    var $trace = $('#ilTrace');
    var $btn = $('#ilBtnRun');

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
      if (!data || !data.ok) {
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
      if (data.no_handler) {
        bits.push('<span class="il-badge">no_handler</span>');
      }
      $meta.html(bits.join(''));
      var tr = data.trace;
      if (Array.isArray(tr)) {
        $trace.text(tr.length ? tr.join('\n') : '(kosong)');
      } else {
        $trace.text(tr ? String(tr) : '(kosong)');
      }
    }

    function runCheck() {
      var text = $.trim($text.val() || '');
      if (!text) {
        if (window.MdlToast) MdlToast.warn('Isi teks pesan dulu');
        else alert('Isi teks pesan dulu');
        return;
      }
      $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Mengecek…');
      $.ajax({
        url: '<?= URL::Base() ?>/IntentLab/check',
        method: 'POST',
        contentType: 'application/json; charset=utf-8',
        dataType: 'json',
        data: JSON.stringify({ text: text }),
        timeout: 70000
      }).done(function (res) {
        showResult(res || {});
      }).fail(function (xhr) {
        var msg = 'Request gagal';
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (j.message) msg = j.message;
        } catch (e) {}
        showResult({ ok: 0, message: msg });
      }).always(function () {
        $btn.prop('disabled', false).html('<i class="fas fa-search"></i> Cek Intent');
      });
    }

    $btn.on('click', runCheck);
    $('#ilBtnClear').on('click', function () {
      $text.val('');
      $result.removeClass('is-show');
    });
    $('#ilChips').on('click', '.il-chip', function () {
      $text.val($(this).attr('data-text') || '');
      $text.focus();
    });
    $text.on('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        runCheck();
      }
    });
  })();
  </script>
</div>
