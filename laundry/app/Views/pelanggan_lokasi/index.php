<?php
$idCabang = (int) ($data['id_cabang'] ?? 0);
$namaCabang = htmlspecialchars((string) ($data['nama_cabang'] ?? ''), ENT_QUOTES, 'UTF-8');
$base = htmlspecialchars(URL::BASE_URL, ENT_QUOTES, 'UTF-8');
?>
<div class="content" id="pl-lokasi-root">
  <style>
    #pl-lokasi-root {
      --pl-ink: #0f172a;
      --pl-muted: #475569;
      --pl-line: #94a3b8;
      --pl-blue: #2563eb;
      --pl-blue-deep: #1d4ed8;
      --pl-green: #16a34a;
      --pl-red: #dc2626;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #pl-lokasi-root .pl-shell {
      max-width: 920px;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.10), transparent 50%),
        linear-gradient(180deg, #eef4ff 0%, #f8fafc 60%, #fff 100%);
      border: 1px solid #cbd5e1;
      padding: 16px;
    }
    #pl-lokasi-root .pl-title {
      margin: 0 0 4px;
      font-weight: 900;
      font-size: 1.25rem;
      color: var(--pl-ink);
      letter-spacing: -0.02em;
    }
    #pl-lokasi-root .pl-lead {
      margin: 0 0 14px;
      color: var(--pl-muted);
      font-size: 0.9rem;
      font-weight: 600;
    }
    #pl-lokasi-root .pl-panel {
      background: #fff;
      border: 1px solid #93c5fd;
      padding: 12px 14px;
      margin-bottom: 12px;
    }
    #pl-lokasi-root .pl-panel h5 {
      margin: 0 0 8px;
      color: var(--pl-blue-deep);
      font-weight: 800;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #pl-lokasi-root .pl-label {
      display: block;
      margin-bottom: 4px;
      color: var(--pl-muted);
      font-weight: 800;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #pl-lokasi-root .pl-input,
    #pl-lokasi-root .pl-textarea {
      width: 100%;
      border: 1px solid var(--pl-line);
      background: #fff;
      color: var(--pl-ink);
      font-weight: 600;
      padding: 8px 10px;
      border-radius: 0;
    }
    #pl-lokasi-root .pl-input:focus,
    #pl-lokasi-root .pl-textarea:focus {
      outline: none;
      border-color: var(--pl-blue);
      box-shadow: 0 0 0 2px rgba(37,99,235,.25);
    }
    #pl-lokasi-root .pl-input[readonly] {
      background: #f1f5f9;
      color: #334155;
    }
    #pl-lokasi-root .pl-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    #pl-lokasi-root .pl-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
    }
    #pl-lokasi-root .pl-btn {
      border: 1px solid transparent;
      border-radius: 0;
      font-weight: 800;
      padding: 8px 12px;
      cursor: pointer;
    }
    #pl-lokasi-root .pl-btn-primary {
      background: var(--pl-blue);
      color: #fff;
    }
    #pl-lokasi-root .pl-btn-primary:hover { background: var(--pl-blue-deep); }
    #pl-lokasi-root .pl-btn-green {
      background: var(--pl-green);
      color: #fff;
    }
    #pl-lokasi-root .pl-btn-muted {
      background: #e2e8f0;
      color: var(--pl-ink);
    }
    #pl-lokasi-root .pl-btn-danger {
      background: #fff;
      border-color: #fecaca;
      color: var(--pl-red);
    }
    #pl-lokasi-root .pl-suggest {
      list-style: none;
      margin: 6px 0 0;
      padding: 0;
      border: 1px solid #cbd5e1;
      background: #fff;
      max-height: 220px;
      overflow-y: auto;
    }
    #pl-lokasi-root .pl-suggest li {
      padding: 8px 10px;
      cursor: pointer;
      border-bottom: 1px solid #e2e8f0;
      font-weight: 600;
      color: var(--pl-ink);
      font-size: 0.9rem;
    }
    #pl-lokasi-root .pl-suggest li:hover { background: #eff6ff; }
    #pl-lokasi-root .pl-suggest li small {
      display: block;
      color: var(--pl-muted);
      font-weight: 600;
    }
    #pl-lokasi-root .pl-selected {
      margin-top: 8px;
      padding: 8px 10px;
      background: #ecfdf5;
      border: 1px solid #86efac;
      font-weight: 700;
      color: #166534;
    }
    #pl-lokasi-root table.pl-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }
    #pl-lokasi-root table.pl-table th,
    #pl-lokasi-root table.pl-table td {
      border: 1px solid #cbd5e1;
      padding: 8px;
      text-align: left;
      vertical-align: top;
    }
    #pl-lokasi-root table.pl-table th {
      background: #eff6ff;
      color: var(--pl-blue-deep);
      font-weight: 800;
      font-size: 0.75rem;
      text-transform: uppercase;
    }
    #pl-lokasi-root .pl-msg {
      margin-top: 8px;
      font-weight: 700;
      font-size: 0.9rem;
    }
    #pl-lokasi-root .pl-msg.ok { color: var(--pl-green); }
    #pl-lokasi-root .pl-msg.err { color: var(--pl-red); }
    #pl-lokasi-root .pl-hint {
      margin: 4px 0 0;
      color: var(--pl-muted);
      font-size: 0.8rem;
      font-weight: 600;
    }
    @media (max-width: 700px) {
      #pl-lokasi-root .pl-row { grid-template-columns: 1fr; }
    }
  </style>

  <div class="pl-shell">
    <h3 class="pl-title">Lokasi Pelanggan</h3>
    <p class="pl-lead">
      Cabang aktif: <strong><?= $namaCabang !== '' ? $namaCabang : ('#' . $idCabang) ?></strong>.
      Koordinat hanya dari URL Google Maps (tidak bisa diisi manual).
    </p>

    <div class="pl-panel">
      <h5>1. Pilih pelanggan</h5>
      <label class="pl-label" for="plSearch">Cari nama / nomor HP</label>
      <input type="text" id="plSearch" class="pl-input" placeholder="Ketik minimal 2 huruf…" autocomplete="off">
      <ul id="plSuggest" class="pl-suggest" style="display:none;"></ul>
      <div id="plSelected" class="pl-selected" style="display:none;"></div>
    </div>

    <div class="pl-panel" id="plListPanel" style="display:none;">
      <h5>2. Lokasi tersimpan</h5>
      <div id="plListWrap"></div>
      <div class="pl-actions">
        <button type="button" class="pl-btn pl-btn-primary" id="plBtnAdd">+ Tambah lokasi</button>
      </div>
    </div>

    <div class="pl-panel" id="plFormPanel" style="display:none;">
      <h5 id="plFormTitle">Tambah lokasi</h5>
      <input type="hidden" id="plIdLokasi" value="">

      <label class="pl-label" for="plGmaps">URL Google Maps</label>
      <input type="text" id="plGmaps" class="pl-input" placeholder="https://maps.app.goo.gl/… atau https://www.google.com/maps/…">
      <p class="pl-hint" id="plGmapsHint">Wajib untuk lokasi baru. Saat edit, isi ulang URL hanya jika ingin mengubah titik.</p>
      <div class="pl-actions" style="margin-top:6px;">
        <button type="button" class="pl-btn pl-btn-muted" id="plBtnResolve">Ambil koordinat</button>
      </div>

      <div class="pl-row" style="margin-top:10px;">
        <div>
          <label class="pl-label" for="plLatt">Latitude</label>
          <input type="text" id="plLatt" class="pl-input" readonly placeholder="otomatis dari URL">
        </div>
        <div>
          <label class="pl-label" for="plLongt">Longitude</label>
          <input type="text" id="plLongt" class="pl-input" readonly placeholder="otomatis dari URL">
        </div>
      </div>

      <div style="margin-top:10px;">
        <label class="pl-label" for="plNama">Nama lokasi</label>
        <input type="text" id="plNama" class="pl-input" maxlength="50" placeholder="Rumah / Kos / Kantor…">
      </div>
      <div style="margin-top:10px;">
        <label class="pl-label" for="plDetail">Detail alamat</label>
        <textarea id="plDetail" class="pl-textarea" rows="3" maxlength="255" placeholder="Ciri / patokan / nomor rumah…"></textarea>
      </div>

      <div class="pl-actions">
        <button type="button" class="pl-btn pl-btn-green" id="plBtnSave">Simpan</button>
        <button type="button" class="pl-btn pl-btn-muted" id="plBtnCancel">Batal</button>
      </div>
      <div id="plFormMsg" class="pl-msg"></div>
    </div>
  </div>
</div>

<script>
(function () {
  var BASE = <?= json_encode(URL::BASE_URL, JSON_UNESCAPED_SLASHES) ?>;
  var idPelanggan = 0;
  var pelangganLabel = '';
  var searchTimer = null;
  var coordsReady = false;

  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function post(path, data) {
    var body = new FormData();
    Object.keys(data || {}).forEach(function (k) {
      if (data[k] !== undefined && data[k] !== null) body.append(k, data[k]);
    });
    return fetch(BASE + path, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }
  function setMsg(text, ok) {
    var el = $('plFormMsg');
    el.className = 'pl-msg ' + (ok ? 'ok' : 'err');
    el.textContent = text || '';
  }
  function resetForm(isEdit) {
    $('plIdLokasi').value = '';
    $('plGmaps').value = '';
    $('plLatt').value = '';
    $('plLongt').value = '';
    $('plNama').value = '';
    $('plDetail').value = '';
    coordsReady = false;
    $('plFormTitle').textContent = isEdit ? 'Edit lokasi' : 'Tambah lokasi';
    $('plGmapsHint').textContent = isEdit
      ? 'Isi ulang URL hanya jika ingin mengubah titik koordinat.'
      : 'Wajib untuk lokasi baru. Koordinat diambil dari URL.';
    setMsg('', true);
  }
  function showForm(show) {
    $('plFormPanel').style.display = show ? '' : 'none';
  }

  function renderList(items) {
    if (!items || !items.length) {
      $('plListWrap').innerHTML = '<p class="pl-hint">Belum ada lokasi untuk pelanggan ini.</p>';
      return;
    }
    var html = '<table class="pl-table"><thead><tr>'
      + '<th>Nama</th><th>Detail</th><th>Koordinat</th><th>Aksi</th>'
      + '</tr></thead><tbody>';
    items.forEach(function (it) {
      var maps = it.maps_url
        ? '<a href="' + esc(it.maps_url) + '" target="_blank" rel="noopener">Buka Maps</a>'
        : '-';
      html += '<tr data-id="' + esc(it.id_lokasi) + '">'
        + '<td>' + esc(it.nama) + '</td>'
        + '<td>' + esc(it.detail) + '</td>'
        + '<td><code>' + esc(it.latt) + ', ' + esc(it.longt) + '</code><br>' + maps + '</td>'
        + '<td>'
        + '<button type="button" class="pl-btn pl-btn-muted pl-edit" data-id="' + esc(it.id_lokasi) + '">Edit</button> '
        + '<button type="button" class="pl-btn pl-btn-danger pl-del" data-id="' + esc(it.id_lokasi) + '">Hapus</button>'
        + '</td></tr>';
    });
    html += '</tbody></table>';
    $('plListWrap').innerHTML = html;

    $('plListWrap').querySelectorAll('.pl-edit').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var row = (items || []).filter(function (x) { return parseInt(x.id_lokasi, 10) === id; })[0];
        if (!row) return;
        resetForm(true);
        $('plIdLokasi').value = String(row.id_lokasi);
        $('plNama').value = row.nama || '';
        $('plDetail').value = row.detail || '';
        $('plLatt').value = row.latt != null ? String(row.latt) : '';
        $('plLongt').value = row.longt != null ? String(row.longt) : '';
        coordsReady = true;
        showForm(true);
      });
    });
    $('plListWrap').querySelectorAll('.pl-del').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        if (!id || !confirm('Hapus lokasi ini?')) return;
        post('PelangganLokasi/delete', { id_pelanggan: idPelanggan, id_lokasi: id })
          .then(function (res) {
            if (!res || !res.ok) {
              alert((res && res.message) || 'Gagal menghapus');
              return;
            }
            loadLokasi();
            showForm(false);
          })
          .catch(function () { alert('Gagal menghapus'); });
      });
    });
  }

  function loadLokasi() {
    if (!idPelanggan) return;
    post('PelangganLokasi/listLokasi', { id_pelanggan: idPelanggan })
      .then(function (res) {
        if (!res || !res.ok) {
          $('plListWrap').innerHTML = '<p class="pl-msg err">' + esc((res && res.message) || 'Gagal memuat') + '</p>';
          return;
        }
        $('plListPanel').style.display = '';
        renderList(res.items || []);
      })
      .catch(function () {
        $('plListWrap').innerHTML = '<p class="pl-msg err">Gagal memuat lokasi</p>';
      });
  }

  function selectPelanggan(item) {
    idPelanggan = parseInt(item.id_pelanggan, 10) || 0;
    pelangganLabel = (item.nama_pelanggan || '') + ' — ' + (item.nomor_pelanggan || '');
    $('plSelected').style.display = '';
    $('plSelected').textContent = 'Dipilih: ' + pelangganLabel;
    $('plSuggest').style.display = 'none';
    $('plSearch').value = '';
    showForm(false);
    loadLokasi();
  }

  $('plSearch').addEventListener('input', function () {
    var q = $('plSearch').value.trim();
    clearTimeout(searchTimer);
    if (q.length < 2) {
      $('plSuggest').style.display = 'none';
      return;
    }
    searchTimer = setTimeout(function () {
      post('PelangganLokasi/searchPelanggan', { q: q })
        .then(function (res) {
          var items = (res && res.items) || [];
          if (!items.length) {
            $('plSuggest').innerHTML = '<li><small>Tidak ada hasil</small></li>';
            $('plSuggest').style.display = '';
            return;
          }
          $('plSuggest').innerHTML = items.map(function (it) {
            return '<li data-id="' + esc(it.id_pelanggan) + '"'
              + ' data-nama="' + esc(it.nama_pelanggan) + '"'
              + ' data-hp="' + esc(it.nomor_pelanggan) + '">'
              + esc(it.nama_pelanggan)
              + '<small>' + esc(it.nomor_pelanggan) + '</small></li>';
          }).join('');
          $('plSuggest').style.display = '';
          $('plSuggest').querySelectorAll('li[data-id]').forEach(function (li) {
            li.addEventListener('click', function () {
              selectPelanggan({
                id_pelanggan: li.getAttribute('data-id'),
                nama_pelanggan: li.getAttribute('data-nama'),
                nomor_pelanggan: li.getAttribute('data-hp')
              });
            });
          });
        });
    }, 250);
  });

  $('plBtnAdd').addEventListener('click', function () {
    if (!idPelanggan) return;
    resetForm(false);
    showForm(true);
  });
  $('plBtnCancel').addEventListener('click', function () {
    showForm(false);
  });

  $('plBtnResolve').addEventListener('click', function () {
    var url = $('plGmaps').value.trim();
    if (!url) {
      setMsg('Isi URL Google Maps dulu', false);
      return;
    }
    setMsg('Mengambil koordinat…', true);
    coordsReady = false;
    post('PelangganLokasi/resolveMaps', { url: url })
      .then(function (res) {
        if (!res || !res.ok) {
          setMsg((res && res.message) || 'Gagal membaca koordinat', false);
          return;
        }
        $('plLatt').value = String(res.latt);
        $('plLongt').value = String(res.longt);
        coordsReady = true;
        setMsg('Koordinat berhasil diambil' + (res.source ? ' (' + res.source + ')' : ''), true);
      })
      .catch(function () { setMsg('Gagal menghubungi server', false); });
  });

  $('plBtnSave').addEventListener('click', function () {
    if (!idPelanggan) {
      setMsg('Pilih pelanggan dulu', false);
      return;
    }
    var idLokasi = parseInt($('plIdLokasi').value, 10) || 0;
    var gmaps = $('plGmaps').value.trim();
    var nama = $('plNama').value.trim();
    var detail = $('plDetail').value.trim();
    var isNew = idLokasi <= 0;

    if (!nama || !detail) {
      setMsg('Nama dan detail wajib diisi', false);
      return;
    }
    if (isNew && !gmaps) {
      setMsg('URL Google Maps wajib untuk lokasi baru', false);
      return;
    }
    if (isNew && !coordsReady) {
      setMsg('Klik “Ambil koordinat” dulu, atau pastikan URL valid', false);
      return;
    }
    // Edit: jika ada URL baru, harus resolve dulu
    if (!isNew && gmaps && !coordsReady) {
      setMsg('Klik “Ambil koordinat” untuk URL baru', false);
      return;
    }

    var payload = {
      id_pelanggan: idPelanggan,
      nama: nama,
      detail: detail
    };
    if (gmaps) payload.gmaps_url = gmaps;
    if (!isNew) payload.id_lokasi = idLokasi;

    setMsg('Menyimpan…', true);
    var path = isNew ? 'PelangganLokasi/insert' : 'PelangganLokasi/update';
    post(path, payload)
      .then(function (res) {
        if (!res || !res.ok) {
          setMsg((res && res.message) || 'Gagal menyimpan', false);
          return;
        }
        setMsg(res.message || 'Tersimpan', true);
        loadLokasi();
        setTimeout(function () { showForm(false); }, 600);
      })
      .catch(function () { setMsg('Gagal menyimpan', false); });
  });

  // Jika URL diubah setelah resolve, wajib resolve ulang
  $('plGmaps').addEventListener('input', function () {
    if ($('plGmaps').value.trim() !== '') {
      coordsReady = false;
    }
  });
})();
</script>
