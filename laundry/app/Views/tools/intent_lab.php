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
      font-weight: 900;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 6px;
    }
    #intent-lab-root .il-textarea {
      width: 100%;
      border: 1px solid var(--il-line);
      border-radius: 0;
      padding: 10px 12px;
      font-weight: 800;
      color: var(--il-ink);
      background: #fff;
      min-height: 110px;
      resize: vertical;
    }
    #intent-lab-root .il-textarea:focus {
      outline: none;
      border-color: var(--il-blue);
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
    }
    /* Selectize Intent target — satu border saja (UI_THEME) */
    #intent-lab-root select.tize,
    #intent-lab-root select.selectized {
      border: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      padding: 0 !important;
    }
    #intent-lab-root .selectize-control,
    #intent-lab-root .selectize-control.single {
      border: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      margin: 0;
      width: 100%;
    }
    #intent-lab-root .selectize-control.single .selectize-input {
      border: 1px solid #94a3b8 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      background: #fff !important;
      color: #0f172a;
      font-weight: 800;
      min-height: 42px;
      padding: 8px 12px !important;
    }
    #intent-lab-root .selectize-control.single .selectize-input.focus {
      border-color: #2563eb !important;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
    }
    #intent-lab-root .selectize-control.single .selectize-input:after {
      border: 0 !important;
    }
    #intent-lab-root .selectize-dropdown {
      border: 1px solid #94a3b8 !important;
      border-radius: 0 !important;
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.16) !important;
      z-index: 30 !important;
    }
    #intent-lab-root .selectize-dropdown .option {
      font-weight: 700;
      color: #0f172a;
    }
    #intent-lab-root .selectize-dropdown .option.active {
      background: #eff6ff;
      color: #1d4ed8;
    }
    #intent-lab-root .il-teach-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: stretch;
      width: 100%;
    }
    #intent-lab-root .il-teach-actions .il-btn {
      flex: 1 1 auto;
      min-height: 42px;
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
    #intent-lab-root .il-btn--untouch {
      background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
      border-color: #b91c1c;
    }
    #intent-lab-root .il-btn--ghost {
      background: #fff;
      color: var(--il-ink);
      border-color: #cbd5e1;
    }
    #intent-lab-root .il-btn--bump {
      background: linear-gradient(105deg, #b45309 0%, #f59e0b 100%);
      border-color: #b45309;
    }
    #intent-lab-root .il-cache-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin: 0 0 14px;
      padding: 10px 12px;
      border: 1px solid #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #intent-lab-root .il-cache-bar__label {
      font-weight: 900;
      color: var(--il-yellow-deep);
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #intent-lab-root .il-cache-bar__ver {
      font-weight: 900;
      font-size: 1.1rem;
      color: var(--il-ink);
      font-variant-numeric: tabular-nums;
    }
    #intent-lab-root .il-cache-bar__hint {
      flex: 1 1 180px;
      font-weight: 600;
      color: var(--il-muted);
      font-size: 0.78rem;
      line-height: 1.35;
    }
    #intent-lab-root .il-cache-bar__msg {
      width: 100%;
      font-weight: 800;
      font-size: 0.82rem;
      color: #15803d;
      margin: 0;
    }
    #intent-lab-root .il-result,
    #intent-lab-root .il-teach-box,
    #intent-lab-root .il-untouch-box { margin-top: 14px; display: none; }
    #intent-lab-root .il-result.is-show,
    #intent-lab-root .il-teach-box.is-show,
    #intent-lab-root .il-untouch-box.is-show { display: block; }
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
    #intent-lab-root .il-card--untouch {
      border-color: #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
    }
    #intent-lab-root .il-pat-list {
      margin: 0 0 10px;
      padding: 0;
      list-style: none;
      border: 1px solid #fecaca;
      background: #fff;
    }
    #intent-lab-root .il-pat-list li {
      display: flex;
      gap: 8px;
      align-items: flex-start;
      padding: 8px 10px;
      border-bottom: 1px solid #fecaca;
      font-weight: 700;
      color: #334155;
      font-size: 0.82rem;
      word-break: break-all;
    }
    #intent-lab-root .il-pat-list li:last-child { border-bottom: 0; }
    #intent-lab-root .il-pat-list input { margin-top: 2px; width: 16px; height: 16px; flex-shrink: 0; }
    #intent-lab-root .il-pat-empty {
      margin: 0 0 10px;
      font-weight: 700;
      color: #92400e;
      font-size: 0.85rem;
    }
    #intent-lab-root .il-existing-box {
      margin: 0 0 10px;
      padding: 8px 10px;
      border: 1px solid #67e8f9;
      background: #fff;
    }
    #intent-lab-root .il-existing-box code {
      display: block;
      font-weight: 700;
      font-size: 0.85rem;
      color: #0e7490;
      word-break: break-all;
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
    #intent-lab-root .il-reply {
      margin: 0 0 12px;
      padding: 10px 12px;
      background: #f0fdf4;
      color: #14532d;
      border: 1px solid #86efac;
      font-size: 0.9rem;
      line-height: 1.45;
      white-space: pre-wrap;
      word-break: break-word;
      min-height: 2.4em;
    }
    #intent-lab-root .il-reply.is-empty {
      background: #fff7ed;
      color: #9a3412;
      border-color: #fdba74;
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
  <?php $cacheVersion = htmlspecialchars((string) ($data['cache_version'] ?? '0')); ?>

  <div class="il-shell">
    <div class="il-loading" id="ilLoading" aria-live="polite" aria-busy="false">
      <div class="il-loading__box">
        <div class="il-loading__spin" aria-hidden="true"></div>
        <p class="il-loading__title" id="ilLoadingTitle">Menganalisis intent…</p>
        <p class="il-loading__sub" id="ilLoadingSub">Regex + AI, mohon tunggu sebentar</p>
      </div>
    </div>

    <h3 class="il-title"><i class="fas fa-flask"></i> Intent Lab</h3>
    <p class="il-lead">Tempel pesan customer — cek intent, lalu ajarin masuk intent ATAU keluarkan dari intent (AI).</p>

    <div class="il-cache-bar" id="ilCacheBar">
      <span class="il-cache-bar__label">cache_version</span>
      <span class="il-cache-bar__ver" id="ilCacheVersion" title="wa_autoreply_meta.cache_version"><?= $cacheVersion ?></span>
      <button type="button" class="il-btn il-btn--bump" id="ilBtnBump" title="Paksa API reload config intent dari DB">
        <i class="fas fa-sync-alt"></i> BUMP +1
      </button>
      <span class="il-cache-bar__hint">Setelah edit prompt/pattern (SQL atau UI), tekan BUMP supaya worker PHP API baca ulang config. Otomatis saat Aktifkan/Terapkan — tombol ini untuk verifikasi manual.</span>
      <p class="il-cache-bar__msg" id="ilCacheMsg" style="display:none"></p>
    </div>

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
      <label class="il-label">Balasan autoreply</label>
      <pre class="il-reply is-empty" id="ilReply">(tidak ada)</pre>
      <label class="il-label">Trace</label>
      <pre class="il-trace" id="ilTrace"></pre>
    </div>

    <div class="il-section">
      <h4 class="il-section-title"><i class="fas fa-graduation-cap"></i> Ajarkan / Keluarkan Intent</h4>
      <p class="il-lead" style="margin-bottom:10px">Pilih intent. <b>Usulkan</b> = masukkan ke intent (lebih suka melebarkan / menggabung pattern yang sudah ada, mis. <code>cek</code> → <code>cek+</code> atau <code>terimakash|mksh</code>, daripada menambah row baru). <b>Keluarkan</b> = buang dari intent (nonaktifkan pattern match + pengecualian prompt).</p>

      <div class="il-row il-row--2">
        <div>
          <label class="il-label" for="ilTeachIntent">Intent target</label>
          <select id="ilTeachIntent" class="tize" style="width:100%;">
            <option value="">— pilih intent —</option>
            <?php foreach ($intentOptions as $opt): ?>
              <option value="<?= htmlspecialchars($opt['code']) ?>"><?= htmlspecialchars($opt['code']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-flex align-items-end">
          <div class="il-teach-actions">
            <button type="button" class="il-btn il-btn--teach" id="ilBtnPropose">
              <i class="fas fa-magic"></i> Usulkan (AI)
            </button>
            <button type="button" class="il-btn il-btn--untouch" id="ilBtnProposeUntouch">
              <i class="fas fa-unlink"></i> Keluarkan (AI)
            </button>
          </div>
        </div>
      </div>

      <div class="il-teach-box" id="ilTeachBox">
        <div class="il-card il-card--teach">
          <div class="il-meta" id="ilTeachMeta" style="margin-bottom:10px"></div>
          <p id="ilTeachReason" style="font-weight:700;color:#334155;margin:0 0 10px"></p>

          <div id="ilTeachExistingWrap" style="display:none">
            <label class="il-label">Pattern lama (akan diubah)</label>
            <div class="il-existing-box"><code id="ilTeachExisting"></code></div>
          </div>
          <input type="hidden" id="ilTeachPatternId" value="">

          <label class="il-label" for="ilTeachPattern" id="ilTeachPatternLabel">Pattern (PHP regex)</label>
          <textarea id="ilTeachPattern" class="il-textarea" style="min-height:70px;font-family:ui-monospace,Consolas,monospace;font-size:.85rem"></textarea>

          <label class="il-label" for="ilTeachPrompt" style="margin-top:10px">AI prompt (lengkap)</label>
          <textarea id="ilTeachPrompt" class="il-textarea" style="min-height:180px"></textarea>

          <label class="il-check">
            <input type="checkbox" id="ilAddPattern" checked>
            <span id="ilAddPatternLabel">Tambah pattern ke DB</span>
          </label>
          <label class="il-check">
            <input type="checkbox" id="ilUpdatePrompt" checked>
            Update ai_prompt
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

      <div class="il-untouch-box" id="ilUntouchBox">
        <div class="il-card il-card--untouch">
          <div class="il-meta" id="ilUntouchMeta" style="margin-bottom:10px"></div>
          <p id="ilUntouchReason" style="font-weight:700;color:#334155;margin:0 0 10px"></p>

          <label class="il-label">Pattern aktif yang match (akan dinonaktifkan)</label>
          <p class="il-lead" style="margin:0 0 8px;font-size:0.82rem">Kalau Cek Intent <code>source: regex</code> tapi list ini tadinya kosong, biasanya pattern yang match ada di intent ASAL (lihat TRACE remap, mis. ESTIMASI_SELESAI→PERMINTAAN), bukan di intent hasil remap.</p>
          <div id="ilUntouchPatterns"></div>

          <label class="il-label" for="ilUntouchPrompt" style="margin-top:10px">AI prompt (lengkap)</label>
          <textarea id="ilUntouchPrompt" class="il-textarea" style="min-height:180px"></textarea>

          <label class="il-check">
            <input type="checkbox" id="ilDeactivatePatterns" checked>
            Nonaktifkan pattern tercentang
          </label>
          <label class="il-check">
            <input type="checkbox" id="ilUntouchUpdatePrompt" checked>
            Update ai_prompt
          </label>

          <div class="il-actions">
            <button type="button" class="il-btn il-btn--untouch" id="ilBtnApplyUntouch">
              <i class="fas fa-ban"></i> Terapkan keluarkan
            </button>
            <button type="button" class="il-btn il-btn--ghost" id="ilBtnRecheckUntouch">Cek ulang</button>
          </div>
          <div id="ilUntouchMsg" style="margin-top:10px;font-weight:800"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
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
    var $reply = $('#ilReply');
    var $btn = $('#ilBtnRun');
    var $loading = $('#ilLoading');
    var $teachIntent = $('#ilTeachIntent');
    var $teachBox = $('#ilTeachBox');
    var $teachMeta = $('#ilTeachMeta');
    var $teachReason = $('#ilTeachReason');
    var $teachPattern = $('#ilTeachPattern');
    var $teachPrompt = $('#ilTeachPrompt');
    var $applyMsg = $('#ilApplyMsg');
    var $untouchBox = $('#ilUntouchBox');
    var $untouchMeta = $('#ilUntouchMeta');
    var $untouchReason = $('#ilUntouchReason');
    var $untouchPatterns = $('#ilUntouchPatterns');
    var $untouchPrompt = $('#ilUntouchPrompt');
    var $untouchMsg = $('#ilUntouchMsg');
    var teachSelectize = null;

    if ($.fn.selectize && !$teachIntent[0].selectize) {
      teachSelectize = $root.find('select.tize').selectize({
        allowEmptyOption: true
      })[0].selectize;
    } else if ($teachIntent[0] && $teachIntent[0].selectize) {
      teachSelectize = $teachIntent[0].selectize;
    }

    function getTeachIntent() {
      if (teachSelectize) return $.trim(teachSelectize.getValue() || '');
      return $.trim($teachIntent.val() || '');
    }

    function setTeachIntentIfEmpty(code) {
      if (!code) return;
      var cur = getTeachIntent();
      if (cur) return;
      if (teachSelectize) {
        if (teachSelectize.options[code]) teachSelectize.setValue(code, true);
        return;
      }
      if ($teachIntent.find('option[value="' + code + '"]').length) {
        $teachIntent.val(code);
      }
    }

    /** PCRE: bersihkan \\b setelah ? dan \\?/ korupsi AI sebelum delimiter. */
    function fixPatternBoundaries(pattern) {
      pattern = String(pattern || '').trim();
      pattern = pattern.replace(/\s+(?=\/[a-z]*$)/i, '');
      pattern = pattern.replace(/\\([?!.,;:])\\\/(?=\/[a-z]*$)/i, '\\$1');
      return pattern.replace(/\\([?!.,;:])\s*\\b(?=\/[a-z]*$)/i, '\\$1');
    }

    var checkUrl = '<?= URL::BASE_URL; ?>IntentLab/check';
    var proposeUrl = '<?= URL::BASE_URL; ?>IntentLab/proposeTeach';
    var applyUrl = '<?= URL::BASE_URL; ?>IntentLab/applyTeach';
    var proposeUntouchUrl = '<?= URL::BASE_URL; ?>IntentLab/proposeUntouch';
    var applyUntouchUrl = '<?= URL::BASE_URL; ?>IntentLab/applyUntouch';
    var bumpCacheUrl = '<?= URL::BASE_URL; ?>IntentLab/bumpCache';
    var cacheVersionUrl = '<?= URL::BASE_URL; ?>IntentLab/cacheVersion';
    var running = false;
    var $cacheVersion = $('#ilCacheVersion');
    var $cacheMsg = $('#ilCacheMsg');

    function setCacheVersionDisplay(ver, msg) {
      ver = String(ver == null ? '' : ver);
      if (ver !== '') $cacheVersion.text(ver);
      if (msg) {
        $cacheMsg.text(msg).show();
      } else {
        $cacheMsg.hide().text('');
      }
    }

    function applyCacheFromResponse(res) {
      if (!res) return;
      if (res.cache_version != null && res.cache_version !== '') {
        var line = 'cache_version: ' + res.cache_version;
        if (res.cache_version_bumped && res.cache_version_before != null) {
          line = 'BUMP otomatis ' + res.cache_version_before + ' → ' + res.cache_version;
        }
        setCacheVersionDisplay(res.cache_version, line);
      }
    }

    function runBumpCache() {
      if (running) return;
      setLoading(true, 'Bump cache_version…', 'Naikkan counter supaya API reload config intent');
      $('#ilBtnBump').html('<i class="fas fa-spinner fa-spin"></i> Bumping…');
      $.ajax({
        url: bumpCacheUrl,
        type: 'POST',
        dataType: 'json',
        timeout: 30000
      }).done(function (res) {
        if (!(res && (res.ok === 1 || res.ok === true))) {
          toast((res && res.message) || 'Bump gagal', 'err');
          return;
        }
        var msg = res.message || ('cache_version → ' + (res.cache_version || '?'));
        setCacheVersionDisplay(res.cache_version, msg);
        toast(msg, 'info');
      }).fail(function (xhr) {
        var msg = 'Bump gagal';
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (j.message) msg = j.message;
        } catch (e) {}
        toast(msg, 'err');
      }).always(function () {
        setLoading(false);
        $('#ilBtnBump').html('<i class="fas fa-sync-alt"></i> BUMP +1');
      });
    }

    function refreshCacheVersionQuiet() {
      $.ajax({
        url: cacheVersionUrl,
        type: 'GET',
        dataType: 'json',
        timeout: 15000
      }).done(function (res) {
        if (res && res.cache_version != null) {
          setCacheVersionDisplay(res.cache_version, '');
        }
      });
    }

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
      $('#ilBtnRun, #ilBtnPropose, #ilBtnProposeUntouch, #ilBtnApply, #ilBtnApplyUntouch, #ilBtnBump').prop('disabled', running);
      if (title) $('#ilLoadingTitle').text(title);
      if (sub) $('#ilLoadingSub').text(sub);
      if (!running) {
        $btn.html('<i class="fas fa-search"></i> Cek Intent');
        $('#ilBtnPropose').html('<i class="fas fa-magic"></i> Usulkan (AI)');
        $('#ilBtnProposeUntouch').html('<i class="fas fa-unlink"></i> Keluarkan (AI)');
        $('#ilBtnApply').html('<i class="fas fa-check"></i> Aktifkan');
        $('#ilBtnApplyUntouch').html('<i class="fas fa-ban"></i> Terapkan keluarkan');
      }
    }

    function escapeHtml(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
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
        if ($reply && $reply.length) $reply.addClass('is-empty').text('(tidak ada balasan)');
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
      var replies = data.replies;
      if (Array.isArray(replies) && replies.length) {
        $reply.removeClass('is-empty').text(replies.join('\n---\n'));
      } else {
        $reply.addClass('is-empty').text('(tidak ada balasan)');
      }
      var tr = data.trace;
      if (Array.isArray(tr)) $trace.text(tr.length ? tr.join('\n') : '(kosong)');
      else $trace.text(tr ? String(tr) : '(kosong)');

      var got = String(data.intent || '').toUpperCase();
      if (got && got !== 'FALSE' && got !== 'NONE') {
        setTeachIntentIfEmpty(got);
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
        if (!(res && (res.ok === 1 || res.ok === true)) && res && res.message) {
          toast(res.message, 'err');
        }
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
      var intent = getTeachIntent();
      if (!text) { toast('Isi teks pesan dulu', 'warn'); return; }
      if (!intent) { toast('Pilih intent target', 'warn'); return; }
      setLoading(true, 'AI menyusun usulan…', 'Lebarkan pattern existing bila bisa, baru pattern baru');
      $('#ilBtnPropose').html('<i class="fas fa-spinner fa-spin"></i> Menyusun…');
      $applyMsg.text('');
      $untouchBox.removeClass('is-show');
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
        var pid = Number(res.pattern_id || 0);
        var action = String(res.action || (pid ? 'update' : 'insert'));
        if (res.already_covered) {
          bits.push('<span class="il-badge il-badge--warn">pattern sudah ada / tercakup</span>');
        } else if (action === 'update' && pid) {
          bits.push('<span class="il-badge il-badge--ok">ubah pattern #' + pid + '</span>');
        } else if (res.pattern_exists) {
          bits.push('<span class="il-badge il-badge--warn">pattern sudah ada</span>');
        } else {
          bits.push('<span class="il-badge">pattern baru</span>');
        }
        $teachMeta.html(bits.join(''));
        $teachReason.text(res.reason || '');
        $teachPattern.val(fixPatternBoundaries(res.pattern || ''));
        var teachFull = fullProposedPrompt(res);
        $teachPrompt.val(teachFull);
        $('#ilTeachPatternId').val(pid > 0 && action === 'update' ? String(pid) : '');
        var promptChanged = teachFull !== String(res.current_prompt || '');
        if (action === 'update' && !res.already_covered && (res.existing_pattern || pid)) {
          $('#ilTeachExistingWrap').show();
          $('#ilTeachExisting').text(res.existing_pattern || '');
          $('#ilTeachPatternLabel').text('Pattern baru (usulan)');
          $('#ilAddPatternLabel').text(pid ? ('Ubah pattern yang sudah ada (#' + pid + ')') : 'Ubah pattern yang sudah ada');
          $('#ilAddPattern').prop('checked', true);
          $('#ilUpdatePrompt').prop('checked', promptChanged);
        } else {
          $('#ilTeachExistingWrap').hide();
          $('#ilTeachExisting').text('');
          $('#ilTeachPatternLabel').text('Pattern (PHP regex)');
          $('#ilAddPatternLabel').text('Tambah pattern ke DB');
          if (res.already_covered) {
            $('#ilAddPattern').prop('checked', false);
            $('#ilUpdatePrompt').prop('checked', promptChanged);
          } else {
            $('#ilAddPattern').prop('checked', true);
            $('#ilUpdatePrompt').prop('checked', promptChanged || action === 'insert');
          }
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

    function mergePromptDraft(current, append) {
      current = String(current == null ? '' : current).replace(/\s+$/, '');
      append = $.trim(append || '');
      if (!append) return current;
      if (current && current.toLowerCase().indexOf(append.toLowerCase()) !== -1) return current;
      if (!current) return append;
      return current + '\n' + append;
    }

    function fullProposedPrompt(res) {
      if (res && typeof res.proposed_prompt === 'string' && res.proposed_prompt !== '') {
        return res.proposed_prompt;
      }
      return mergePromptDraft(res && res.current_prompt, res && res.prompt_append);
    }

    function renderUntouchPatterns(list, targetIntent) {
      targetIntent = String(targetIntent || '').toUpperCase();
      if (!Array.isArray(list) || !list.length) {
        $untouchPatterns.html('<p class="il-pat-empty">Tidak ada pattern aktif yang match teks ini. Jika Cek Intent source regex, cek TRACE remap (pattern bisa milik intent lain). Edit ai_prompt hanya menolong jalur AI, bukan regex remap PHP.</p>');
        $('#ilDeactivatePatterns').prop('checked', false);
        return;
      }
      var html = '<ul class="il-pat-list">';
      list.forEach(function (p) {
        var id = Number(p.id || 0);
        var pat = escapeHtml(p.pattern || '');
        var src = String(p.source_intent || '').toUpperCase();
        var badge = '';
        if (src && src !== targetIntent) {
          badge = ' <span class="il-badge il-badge--warn">dari ' + escapeHtml(src) + '</span>';
        } else if (src) {
          badge = ' <span class="il-badge il-badge--src">' + escapeHtml(src) + '</span>';
        }
        html += '<li><input type="checkbox" class="il-untouch-pat" value="' + id + '" checked> <code>' + pat + '</code>' + badge + '</li>';
      });
      html += '</ul>';
      $untouchPatterns.html(html);
      $('#ilDeactivatePatterns').prop('checked', true);
    }

    function runProposeUntouch() {
      if (running) return;
      var text = $.trim($text.val() || '');
      var intent = getTeachIntent();
      if (!text) { toast('Isi teks pesan dulu', 'warn'); return; }
      if (!intent) { toast('Pilih intent yang akan dikeluarkan', 'warn'); return; }
      setLoading(true, 'AI merevisi prompt…', 'Susun ulang aturan TRUE/FALSE agar teks keluar dari intent');
      $('#ilBtnProposeUntouch').html('<i class="fas fa-spinner fa-spin"></i> Menyusun…');
      $untouchMsg.text('');
      $teachBox.removeClass('is-show');
      $.ajax({
        url: proposeUntouchUrl,
        type: 'POST',
        contentType: 'application/json; charset=utf-8',
        data: JSON.stringify({ text: text, intent: intent }),
        dataType: 'json',
        timeout: 90000
      }).done(function (res) {
        if (!(res && (res.ok === 1 || res.ok === true))) {
          toast((res && (res.message || res.error)) || 'Usulan keluarkan gagal', 'err');
          return;
        }
        $untouchBox.addClass('is-show');
        var bits = [];
        bits.push('<span class="il-badge il-badge--src">keluarkan dari: ' + (res.intent || intent) + '</span>');
        var n = (res.matching_patterns && res.matching_patterns.length) || 0;
        bits.push(n
          ? '<span class="il-badge il-badge--warn">pattern match: ' + n + '</span>'
          : '<span class="il-badge il-badge--ok">pattern match: 0</span>');
        var other = res.matching_other_intents;
        if (Array.isArray(other) && other.length) {
          bits.push('<span class="il-badge il-badge--warn">regex sumber: ' + other.join(', ') + '</span>');
        }
        $untouchMeta.html(bits.join(''));
        $untouchReason.text(res.reason || '');
        var untouchFull = fullProposedPrompt(res);
        $untouchPrompt.val(untouchFull);
        renderUntouchPatterns(res.matching_patterns || [], res.intent || intent);
        $('#ilUntouchUpdatePrompt').prop('checked', untouchFull !== String(res.current_prompt || ''));
      }).fail(function (xhr) {
        var msg = 'Usulan keluarkan gagal';
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
      var intent = getTeachIntent();
      var pattern = fixPatternBoundaries($.trim($teachPattern.val() || ''));
      $teachPattern.val(pattern);
      var promptAppend = $.trim($teachPrompt.val() || '');
      var addPattern = $('#ilAddPattern').is(':checked') ? 1 : 0;
      var updatePrompt = $('#ilUpdatePrompt').is(':checked') ? 1 : 0;
      if (!text || !intent) { toast('Teks dan intent wajib', 'warn'); return; }
      if (addPattern && !pattern) { toast('Isi pattern atau matikan centang pattern', 'warn'); return; }
      if (!addPattern && !updatePrompt) { toast('Centang minimal satu aksi', 'warn'); return; }
      var patternId = parseInt($('#ilTeachPatternId').val() || '0', 10) || 0;
      setLoading(true, 'Mengaktifkan ke DB…', patternId ? 'Ubah pattern existing lalu verifikasi' : 'Simpan pattern/prompt lalu verifikasi');
      $('#ilBtnApply').html('<i class="fas fa-spinner fa-spin"></i> Mengaktifkan…');
      $.ajax({
        url: applyUrl,
        type: 'POST',
        contentType: 'application/json; charset=utf-8',
        data: JSON.stringify({
          text: text,
          intent: intent,
          pattern: pattern,
          pattern_id: patternId,
          ai_prompt: $teachPrompt.val() || '',
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
        if (res.saved_pattern) msg += ' DB: ' + res.saved_pattern;
        if (res.pattern_updated) msg += ' Pattern diubah.';
        if (res.pattern_added) msg += ' Pattern ditambah.';
        if (res.pattern_dup_skipped) msg += ' Pattern sudah ada (reaktivasi).';
        if (res.prompt_updated) msg += ' Prompt diupdate.';
        if (res.verify_ok) {
          var src = (res.verify && res.verify.source) ? res.verify.source : '';
          msg += ' Verifikasi: intent = ' + (res.verify_intent || intent) + (src ? ' (' + src + ')' : '');
        } else {
          msg += ' Verifikasi: dapat ' + (res.verify_intent || '—') + ' (target ' + intent + ').';
          if (res.verify && res.verify.source === 'ai') {
            msg += ' Regex belum menang — cek urutan pattern intent lain atau perkuat ai_prompt.';
          }
        }
        $applyMsg.css('color', res.verify_ok ? '#15803d' : '#b45309').text(msg);
        applyCacheFromResponse(res);
        if (res.verify) showResult(res.verify);
        toast(res.verify_ok ? 'Berhasil diajarkan' : 'Tersimpan — cek hasil Cek Intent di bawah', res.verify_ok ? 'info' : 'warn');
      }).fail(function () {
        toast('Request aktifkan gagal', 'err');
      }).always(function () { setLoading(false); });
    }

    function runApplyUntouch() {
      if (running) return;
      var text = $.trim($text.val() || '');
      var intent = getTeachIntent();
      var promptText = $untouchPrompt.val() || '';
      var deactivatePatterns = $('#ilDeactivatePatterns').is(':checked') ? 1 : 0;
      var updatePrompt = $('#ilUntouchUpdatePrompt').is(':checked') ? 1 : 0;
      var patternIds = [];
      $('.il-untouch-pat:checked').each(function () {
        var id = parseInt($(this).val(), 10);
        if (id > 0) patternIds.push(id);
      });
      if (!text || !intent) { toast('Teks dan intent wajib', 'warn'); return; }
      if (!deactivatePatterns && !updatePrompt) { toast('Centang minimal satu aksi', 'warn'); return; }
      if (deactivatePatterns && !patternIds.length && !updatePrompt) {
        toast('Tidak ada pattern tercentang. Centang Update ai_prompt atau pilih pattern.', 'warn');
        return;
      }
      setLoading(true, 'Mengeluarkan dari intent…', 'Nonaktifkan pattern / update ai_prompt');
      $('#ilBtnApplyUntouch').html('<i class="fas fa-spinner fa-spin"></i> Menerapkan…');
      $.ajax({
        url: applyUntouchUrl,
        type: 'POST',
        contentType: 'application/json; charset=utf-8',
        data: JSON.stringify({
          text: text,
          intent: intent,
          ai_prompt: promptText,
          pattern_ids: patternIds,
          deactivate_patterns: deactivatePatterns,
          update_prompt: updatePrompt
        }),
        dataType: 'json',
        timeout: 90000
      }).done(function (res) {
        if (!(res && (res.ok === 1 || res.ok === true))) {
          $untouchMsg.css('color', '#dc2626').text((res && res.message) || 'Gagal keluarkan');
          toast((res && res.message) || 'Gagal keluarkan', 'err');
          return;
        }
        var msg = 'Dikeluarkan.';
        if (res.patterns_deactivated) msg += ' Pattern nonaktif: ' + res.patterns_deactivated + '.';
        if (res.prompt_updated) msg += ' Prompt diupdate.';
        if (res.verify_ok) msg += ' Verifikasi: bukan ' + intent + ' (dapat ' + (res.verify_intent || '—') + ').';
        else msg += ' Verifikasi: masih ' + (res.verify_intent || intent) + '.';
        $untouchMsg.css('color', res.verify_ok ? '#15803d' : '#b45309').text(msg);
        applyCacheFromResponse(res);
        if (res.verify) showResult(res.verify);
        toast(res.verify_ok ? 'Berhasil dikeluarkan' : 'Tersimpan, verifikasi masih di intent ini', res.verify_ok ? 'info' : 'warn');
      }).fail(function () {
        toast('Request keluarkan gagal', 'err');
      }).always(function () { setLoading(false); });
    }

    $btn.off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runCheck(); });
    $('#ilBtnBump').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runBumpCache(); });
    $('#ilBtnPropose').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runPropose(); });
    $('#ilBtnProposeUntouch').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runProposeUntouch(); });
    $('#ilBtnApply').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runApply(); });
    $('#ilBtnApplyUntouch').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runApplyUntouch(); });
    $('#ilBtnRecheck, #ilBtnRecheckUntouch').off('click.intentLab').on('click.intentLab', function (e) { e.preventDefault(); runCheck(); });
    $('#ilBtnClear').off('click.intentLab').on('click.intentLab', function (e) {
      e.preventDefault();
      $text.val('');
      $result.removeClass('is-show');
      $teachBox.removeClass('is-show');
      $untouchBox.removeClass('is-show');
      $applyMsg.text('');
      $untouchMsg.text('');
    });
    $text.off('keydown.intentLab').on('keydown.intentLab', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'Enter' || e.keyCode === 13)) {
        e.preventDefault();
        runCheck();
      }
    });
    refreshCacheVersionQuiet();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>
