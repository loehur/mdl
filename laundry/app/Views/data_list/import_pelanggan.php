<div class="content" id="import-pelanggan-root">
  <style>
    #import-pelanggan-root {
      --ip-ink: #0f172a;
      --ip-muted: #1e293b;
      --ip-line: #94a3b8;
      --ip-blue: #2563eb;
      --ip-blue-deep: #1d4ed8;
      --ip-green: #16a34a;
      --ip-green-deep: #15803d;
      --ip-yellow: #f59e0b;
      --ip-red: #dc2626;
      --ip-radius: 0;
      --ip-border: 1px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #import-pelanggan-root .ip-shell {
      max-width: 720px;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.12), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f4fff8 55%, #fff8eb 100%);
      border: 1px solid #cbd5e1;
      border-radius: 0;
      padding: 16px;
    }
    #import-pelanggan-root .ip-title {
      color: var(--ip-ink);
      font-weight: 900;
      font-size: 1.25rem;
      letter-spacing: -0.02em;
      margin: 0 0 6px;
    }
    #import-pelanggan-root .ip-lead {
      color: var(--ip-muted);
      font-size: 0.9rem;
      font-weight: 600;
      margin: 0 0 14px;
    }
    #import-pelanggan-root .ip-panel {
      background: linear-gradient(180deg, #eff6ff, #fff);
      border: 1px solid #93c5fd;
      border-radius: 0;
      padding: 12px 14px;
      margin-bottom: 12px;
    }
    #import-pelanggan-root .ip-panel h5 {
      color: var(--ip-blue-deep);
      font-weight: 800;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin: 0 0 8px;
    }
    #import-pelanggan-root .ip-panel code,
    #import-pelanggan-root .ip-panel pre {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 0;
      color: var(--ip-ink);
      font-size: 0.85rem;
    }
    #import-pelanggan-root .ip-panel pre {
      padding: 8px 10px;
      margin: 0;
      overflow-x: auto;
    }
    #import-pelanggan-root .ip-panel ul {
      margin: 0;
      padding-left: 1.2rem;
      color: var(--ip-ink);
      font-weight: 600;
      font-size: 0.9rem;
    }
    #import-pelanggan-root .ip-label {
      display: block;
      color: var(--ip-muted);
      font-weight: 800;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 6px;
    }
    #import-pelanggan-root .ip-file {
      display: block;
      width: 100%;
      border: 1px solid var(--ip-line);
      border-radius: 0;
      background: #fff;
      color: var(--ip-ink);
      font-weight: 600;
      padding: 8px 10px;
    }
    #import-pelanggan-root .ip-file:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.35);
      border-color: var(--ip-blue);
    }
    #import-pelanggan-root .ip-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
    }
    #import-pelanggan-root .ip-btn {
      border: 1px solid transparent;
      border-radius: 0;
      font-weight: 800;
      padding: 8px 14px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    #import-pelanggan-root .ip-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    #import-pelanggan-root .ip-btn-primary {
      background: linear-gradient(180deg, var(--ip-green), var(--ip-green-deep));
      border-color: var(--ip-green-deep);
      color: #fff;
    }
    #import-pelanggan-root .ip-btn-secondary {
      background: linear-gradient(180deg, var(--ip-blue), var(--ip-blue-deep));
      border-color: var(--ip-blue-deep);
      color: #fff;
    }
    #import-pelanggan-root .ip-result {
      margin-top: 14px;
      display: none;
      border: 1px solid #cbd5e1;
      border-radius: 0;
      background: #fff;
      padding: 12px 14px;
    }
    #import-pelanggan-root .ip-result.is-visible {
      display: block;
    }
    #import-pelanggan-root .ip-result.is-ok {
      border-color: #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
    }
    #import-pelanggan-root .ip-result.is-warn {
      border-color: #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #import-pelanggan-root .ip-result.is-err {
      border-color: #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
    }
    #import-pelanggan-root .ip-result-msg {
      font-weight: 800;
      color: var(--ip-ink);
      margin: 0 0 8px;
    }
    #import-pelanggan-root .ip-stats {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 8px;
      font-weight: 700;
      font-size: 0.9rem;
    }
    #import-pelanggan-root .ip-stat-ok { color: var(--ip-green-deep); }
    #import-pelanggan-root .ip-stat-skip { color: var(--ip-yellow); }
    #import-pelanggan-root .ip-errors {
      margin: 0;
      padding-left: 1.2rem;
      max-height: 220px;
      overflow-y: auto;
      color: var(--ip-red);
      font-weight: 600;
      font-size: 0.85rem;
    }
  </style>

  <div class="container-fluid">
    <div class="ip-shell">
      <h4 class="ip-title">Import Pelanggan</h4>
      <p class="ip-lead">Impor data pelanggan dari file CSV untuk cabang aktif. Unduh sample terlebih dahulu, isi data, lalu upload.</p>

      <div class="ip-panel">
        <h5>Format CSV</h5>
        <ul>
          <li><strong>nama_pelanggan</strong> — wajib, unik per cabang</li>
          <li><strong>nomor_pelanggan</strong> — wajib (angka saja)</li>
          <li><strong>alamat</strong> — opsional</li>
        </ul>
        <pre class="mt-2">nama_pelanggan,nomor_pelanggan,alamat
Budi Santoso,081234567890,Jl. Merdeka 1
Siti Aminah,081298765432,</pre>
      </div>

      <div class="ip-actions" style="margin-top:0;margin-bottom:14px;">
        <a class="ip-btn ip-btn-secondary" href="<?= URL::BASE_URL; ?>ImportPelanggan/downloadSample">
          <i class="fas fa-download"></i> Download Sample CSV
        </a>
      </div>

      <form id="formImportPelanggan" enctype="multipart/form-data">
        <label class="ip-label" for="csvFile">File CSV</label>
        <input type="file" name="csv" id="csvFile" class="ip-file" accept=".csv,text/csv" required>
        <div class="ip-actions">
          <button type="submit" class="ip-btn ip-btn-primary" id="btnImport">
            <i class="fas fa-file-import"></i> Import
          </button>
        </div>
      </form>

      <div class="ip-result" id="importResult" aria-live="polite">
        <p class="ip-result-msg" id="importMessage"></p>
        <div class="ip-stats">
          <span class="ip-stat-ok" id="statImported"></span>
          <span class="ip-stat-skip" id="statSkipped"></span>
        </div>
        <ul class="ip-errors" id="importErrors"></ul>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('formImportPelanggan');
  var btn = document.getElementById('btnImport');
  var result = document.getElementById('importResult');
  var msgEl = document.getElementById('importMessage');
  var errEl = document.getElementById('importErrors');
  var statImported = document.getElementById('statImported');
  var statSkipped = document.getElementById('statSkipped');
  var fileInput = document.getElementById('csvFile');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!fileInput.files || !fileInput.files.length) {
      alert('Pilih file CSV terlebih dahulu');
      return;
    }

    var fd = new FormData();
    fd.append('csv', fileInput.files[0]);

    btn.disabled = true;
    result.classList.remove('is-visible', 'is-ok', 'is-warn', 'is-err');
    errEl.innerHTML = '';

    $.ajax({
      url: '<?= URL::BASE_URL; ?>ImportPelanggan/import',
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (res) {
        var imported = res.imported || 0;
        var skipped = res.skipped || 0;
        var errors = res.errors || [];

        msgEl.textContent = res.message || '';
        statImported.textContent = 'Berhasil: ' + imported;
        statSkipped.textContent = 'Dilewati: ' + skipped;

        errEl.innerHTML = '';
        errors.forEach(function (line) {
          var li = document.createElement('li');
          li.textContent = line;
          errEl.appendChild(li);
        });

        result.classList.add('is-visible');
        if (imported > 0 && errors.length === 0) {
          result.classList.add('is-ok');
        } else if (imported > 0) {
          result.classList.add('is-warn');
        } else {
          result.classList.add('is-err');
        }
      },
      error: function () {
        msgEl.textContent = 'Gagal menghubungi server';
        statImported.textContent = '';
        statSkipped.textContent = '';
        errEl.innerHTML = '';
        result.classList.add('is-visible', 'is-err');
      },
      complete: function () {
        btn.disabled = false;
      }
    });
  });
})();
</script>
