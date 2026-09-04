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
    #pl-lokasi-root .pl-btn:disabled {
      opacity: 0.7;
      cursor: wait;
      pointer-events: none;
    }
    #pl-lokasi-root .pl-btn.is-loading {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    #pl-lokasi-root .pl-btn.is-loading::before {
      content: '';
      width: 14px;
      height: 14px;
      border: 2px solid #94a3b8;
      border-top-color: var(--pl-blue-deep);
      border-radius: 50%;
      animation: pl-spin 0.7s linear infinite;
      flex-shrink: 0;
    }
    @keyframes pl-spin {
      to { transform: rotate(360deg); }
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
    #pl-lokasi-root .pl-map-wrap {
      position: relative;
      border: 1px solid #cbd5e1;
      height: 280px;
      margin-top: 4px;
      background: #eef2f7;
    }
    #pl-lokasi-root .pl-map {
      width: 100%;
      height: 100%;
    }
    #pl-lokasi-root .pl-pin {
      position: absolute;
      left: 50%;
      top: 50%;
      z-index: 5;
      width: 30px;
      height: 30px;
      transform: translate(-50%, -100%);
      pointer-events: none;
    }
    #pl-lokasi-root .pl-search-wrap {
      position: relative;
    }
    #pl-lokasi-root .pl-suggest-map {
      list-style: none;
      margin: 4px 0 0;
      padding: 0;
      border: 1px solid #cbd5e1;
      background: #fff;
      max-height: 200px;
      overflow-y: auto;
    }
    #pl-lokasi-root .pl-suggest-map li button {
      width: 100%;
      text-align: left;
      padding: 8px 10px;
      border: 0;
      background: #fff;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.88rem;
      color: var(--pl-ink);
      border-bottom: 1px solid #e2e8f0;
    }
    #pl-lokasi-root .pl-suggest-map li button:hover { background: #eff6ff; }
    #pl-lokasi-root .pl-map-hint {
      margin: 4px 0 0;
      color: var(--pl-muted);
      font-size: 0.78rem;
      font-weight: 600;
    }
    #pl-lokasi-root .pl-map-hint.is-warn { color: var(--pl-red); }
    @media (max-width: 700px) {
      #pl-lokasi-root .pl-row { grid-template-columns: 1fr; }
    }
  </style>

  <div class="pl-shell">
    <h3 class="pl-title">Lokasi Pelanggan</h3>
    <p class="pl-lead">
      Cabang aktif: <strong><?= $namaCabang !== '' ? $namaCabang : ('#' . $idCabang) ?></strong>.
      Cari alamat atau geser peta untuk menentukan titik lokasi.
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

      <div class="pl-row" style="margin-top:10px;">
        <div>
          <label class="pl-label" for="plNama">Nama lokasi</label>
          <input type="text" id="plNama" class="pl-input" maxlength="50" placeholder="Rumah / Kos / Kantor…">
        </div>
      </div>
      <div style="margin-top:10px;">
        <label class="pl-label" for="plDetail">Detail alamat</label>
        <textarea id="plDetail" class="pl-textarea" rows="2" maxlength="255" placeholder="Ciri / patokan / nomor rumah…"></textarea>
      </div>

      <div style="margin-top:10px;">
        <label class="pl-label" for="plMapSearch">Cari alamat</label>
        <div class="pl-search-wrap">
          <input type="text" id="plMapSearch" class="pl-input" placeholder="Ketik nama jalan, tempat, atau alamat…" autocomplete="off">
          <ul id="plMapSuggest" class="pl-suggest-map" style="display:none;"></ul>
        </div>
        <div class="pl-map-wrap">
          <div id="plMap" class="pl-map"></div>
          <svg class="pl-pin" viewBox="0 0 24 36" aria-hidden="true">
            <path fill="#dc2626" stroke="#fff" stroke-width="1.5"
              d="M12 0C7.03 0 3 4.03 3 9c0 6.75 9 15 9 15s9-8.25 9-15c0-4.97-4.03-9-9-9zm0 12.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z" />
          </svg>
          <div id="plMapLoading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.15);color:#0f172a;font-weight:800;font-size:.85rem;">Memuat peta…</div>
        </div>
        <p class="pl-map-hint" id="plMapHint">Geser peta agar pin berada di titik yang tepat.</p>
      </div>

      <div class="pl-row" style="margin-top:10px;">
        <div>
          <label class="pl-label" for="plLatt">Latitude</label>
          <input type="text" id="plLatt" class="pl-input" readonly placeholder="otomatis dari peta">
        </div>
        <div>
          <label class="pl-label" for="plLongt">Longitude</label>
          <input type="text" id="plLongt" class="pl-input" readonly placeholder="otomatis dari peta">
        </div>
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

  // State Google Maps
  var mapInstance = null;
  var mapIdleListener = null;
  var mapSuppressIdle = false;
  var mapLastEmit = null;
  var mapScriptPromise = null;
  var mapSearchTimer = null;
  var mapSearchSeq = 0;
  var mapSelectingPlace = false;
  var mapDestroyed = false;
  var mapDefaultCenter = null; // {lat,lng}
  var mapSearchCenter = null;  // {lat,lng}
  var KOTA_SEARCH_RADIUS_KM = 30;
  var SELECT_ZOOM = 17;

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
    $('plMapSearch').value = '';
    $('plLatt').value = '';
    $('plLongt').value = '';
    $('plNama').value = '';
    $('plDetail').value = '';
    coordsReady = false;
    closeMapSuggestions();
    $('plFormTitle').textContent = isEdit ? 'Edit lokasi' : 'Tambah lokasi';
    setMapHint('Geser peta agar pin berada di titik yang tepat.');
    setMsg('', true);
  }
  function showForm(show) {
    $('plFormPanel').style.display = show ? '' : 'none';
    if (show && mapInstance && window.google && google.maps) {
      window.setTimeout(function () {
        google.maps.event.trigger(mapInstance, 'resize');
      }, 50);
    }
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
        coordsReady = row.latt != null && row.longt != null;
        var rlatt = parseFloat(row.latt);
        var rlng = parseFloat(row.longt);
        if (!isNaN(rlatt) && !isNaN(rlng) && !(rlatt === 0 && rlng === 0)) {
          focusMapTo(rlatt, rlng, SELECT_ZOOM);
        } else {
          focusMapToDefault();
        }
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
        var dm = res.default_map || {};
        var dlat = parseFloat(dm.latt);
        var dlng = parseFloat(dm.longt);
        if (!isNaN(dlat) && !isNaN(dlng) && !(dlat === 0 && dlng === 0)) {
          mapDefaultCenter = { lat: dlat, lng: dlng };
          mapSearchCenter = { lat: dlat, lng: dlng };
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

  // ===== Google Maps: load API key via proxy (api.nalju.com) =====
  function fetchMapsConfig() {
    return fetch(BASE + 'PelangganLokasi/mapsConfig', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (res) { return res.json(); });
  }

  function loadGoogleMapsApi(apiKey) {
    if (window.google && window.google.maps && window.google.maps.importLibrary) {
      return window.google.maps.importLibrary('maps');
    }
    if (mapScriptPromise) return mapScriptPromise;
    mapScriptPromise = new Promise(function (resolve, reject) {
      window.gm_authFailure = function () {
        reject(new Error('Google Maps menolak API key browser'));
      };
      var params = { key: apiKey, v: 'weekly', language: 'id', region: 'ID' };
      (function (g) {
        var h, a, k, p = 'The Google Maps JavaScript API', c = 'google', l = 'importLibrary', q = '__ib__';
        var m = document, b = window;
        b = b[c] || (b[c] = {});
        var d = b.maps || (b.maps = {});
        var r = new Set();
        var e = new URLSearchParams();
        var u = function () {
          return h || (h = new Promise(function (f, n) {
            a = m.createElement('script');
            e.set('libraries', Array.from(r).join(','));
            for (k in g) {
              if (Object.prototype.hasOwnProperty.call(g, k) && g[k] != null && g[k] !== '') {
                e.set(k.replace(/[A-Z]/g, function (t) { return '_' + t[0].toLowerCase(); }), g[k]);
              }
            }
            e.set('loading', 'async');
            e.set('callback', c + '.maps.' + q);
            a.src = 'https://maps.googleapis.com/maps/api/js?' + e.toString();
            d[q] = f;
            a.onerror = function () { h = n(new Error(p + ' could not load.')); };
            a.async = true;
            m.head.append(a);
          }));
        };
        d[l] = function (f) { return r.add(f) && u().then(function () { return d[l](f); }); };
      })(params);
      window.google.maps.importLibrary('maps').then(resolve).catch(reject);
    });
    return mapScriptPromise;
  }

  function roundCoord(v) { return Math.round(Number(v) * 1e7) / 1e7; }

  function emitMapCoords(lat, lng) {
    var rl = roundCoord(lat);
    var rn = roundCoord(lng);
    mapLastEmit = { lat: rl, lng: rn };
    $('plLatt').value = String(rl);
    $('plLongt').value = String(rn);
    coordsReady = true;
  }

  function readMapCenter() {
    if (!mapInstance) return;
    var c = mapInstance.getCenter();
    if (c) emitMapCoords(c.lat(), c.lng());
  }

  function focusMapTo(lat, lng, zoom) {
    if (!mapInstance || lat == null || lng == null) return;
    mapSuppressIdle = true;
    mapInstance.panTo({ lat: Number(lat), lng: Number(lng) });
    if (zoom != null) mapInstance.setZoom(zoom);
    window.setTimeout(function () {
      mapSuppressIdle = false;
      readMapCenter();
    }, 350);
  }

  function focusMapToDefault() {
    if (!mapInstance) return;
    var c = mapDefaultCenter || { lat: 0.507068, lng: 101.447779 };
    mapSuppressIdle = true;
    mapInstance.panTo(c);
    mapInstance.setZoom(15);
    window.setTimeout(function () {
      mapSuppressIdle = false;
      readMapCenter();
    }, 350);
  }

  function ensureMap() {
    if (mapInstance) return Promise.resolve(mapInstance);
    var loadingEl = $('plMapLoading');
    if (loadingEl) loadingEl.style.display = 'flex';
    return fetchMapsConfig()
      .then(function (cfg) {
        if (!cfg || (!cfg.ok && !cfg.status)) {
          throw new Error((cfg && cfg.message) || 'Gagal memuat konfigurasi Google Maps');
        }
        var apiKey = String(cfg.api_key || '').trim();
        if (!apiKey) throw new Error('Google Maps API key belum dikonfigurasi');
        return loadGoogleMapsApi(apiKey);
      })
      .then(function (mapsLib) {
        if (loadingEl) loadingEl.style.display = 'none';
        var el = $('plMap');
        if (!el) return;
        var center = mapDefaultCenter || { lat: 0.507068, lng: 101.447779 };
        mapInstance = new mapsLib.Map(el, {
          center: center,
          zoom: 15,
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: false,
          cameraControl: false,
          zoomControl: false
        });
        mapIdleListener = mapInstance.addListener('idle', function () {
          if (mapSuppressIdle) return;
          readMapCenter();
        });
        readMapCenter();
        return mapInstance;
      })
      .catch(function (err) {
        if (loadingEl) loadingEl.style.display = 'none';
        setMapHint((err && err.message) || 'Peta gagal dimuat', true);
        return null;
      });
  }

  function setMapHint(text, isWarn) {
    var hint = $('plMapHint');
    if (!hint) return;
    hint.textContent = text || '';
    hint.classList.toggle('is-warn', !!isWarn);
  }

  function getMapBias() {
    if (mapSearchCenter && mapSearchCenter.lat != null && mapSearchCenter.lng != null) {
      return mapSearchCenter;
    }
    return mapDefaultCenter;
  }

  // ===== Autocomplete (proxy ke api.nalju.com MapsConfig) =====
  function postMap(path, payload) {
    return fetch(BASE + 'PelangganLokasi/' + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); });
  }

  function closeMapSuggestions() {
    var list = $('plMapSuggest');
    if (list) { list.style.display = 'none'; list.innerHTML = ''; }
  }

  function fetchMapSuggestions(query) {
    var q = String(query || '').trim();
    if (q.length < 2) { closeMapSuggestions(); return; }
    var seq = ++mapSearchSeq;
    var payload = { input: q };
    var bias = getMapBias();
    if (bias && !isNaN(bias.lat) && !isNaN(bias.lng)) {
      payload.lat = bias.lat;
      payload.lng = bias.lng;
    }
    postMap('mapsAutocomplete', payload)
      .then(function (data) {
        if (seq !== mapSearchSeq || mapSelectingPlace) return;
        var list = $('plMapSuggest');
        if (!list) return;
        if (!data || (!data.ok && !data.status)) {
          closeMapSuggestions();
          setMapHint((data && data.message) || 'Gagal memuat saran alamat.', true);
          return;
        }
        var items = data && Array.isArray(data.items) ? data.items : [];
        if (!items.length) {
          closeMapSuggestions();
          setMapHint('Tidak ada hasil dalam radius ' + KOTA_SEARCH_RADIUS_KM + ' km dari pusat kota.', true);
          return;
        }
        list.innerHTML = items.map(function (item) {
          return '<li><button type="button" data-place-id="' + esc(item.place_id || '') + '">'
            + esc(item.label || '') + '</button></li>';
        }).join('');
        list.style.display = '';
        setMapHint('Geser peta agar pin berada di titik yang tepat.');
      })
      .catch(function () {
        if (seq !== mapSearchSeq) return;
        closeMapSuggestions();
        setMapHint('Gagal memuat saran alamat.', true);
      });
  }

  function selectMapPlace(placeId, label) {
    if (!placeId || mapSelectingPlace) return;
    mapSelectingPlace = true;
    closeMapSuggestions();
    $('plMapSearch').value = label || '';
    setMapHint('Memuat detail lokasi…');
    postMap('mapsPlaceDetails', { place_id: placeId })
      .then(function (data) {
        if (!data || (!data.ok && !data.status)) {
          setMapHint((data && data.message) || 'Gagal memuat detail lokasi.', true);
          return;
        }
        var lat = parseFloat(data.lat != null ? data.lat : data.latt);
        var lng = parseFloat(data.lng != null ? data.lng : (data.longt != null ? data.longt : data.long));
        if (!isNaN(lat) && !isNaN(lng)) {
          focusMapTo(lat, lng, SELECT_ZOOM);
          setMapHint('Geser peta agar pin berada di titik yang tepat.');
        } else {
          setMapHint('Koordinat lokasi tidak ditemukan.', true);
        }
      })
      .catch(function () { setMapHint('Gagal memuat detail lokasi.', true); })
      .finally(function () { mapSelectingPlace = false; });
  }

  function onMapSearchInput() {
    if (mapSearchTimer) clearTimeout(mapSearchTimer);
    var q = $('plMapSearch').value.trim();
    if (q.length < 2) { closeMapSuggestions(); return; }
    mapSearchTimer = setTimeout(function () { fetchMapSuggestions(q); }, 280);
  }

  $('plMapSearch').addEventListener('input', onMapSearchInput);
  $('plMapSearch').addEventListener('focus', onMapSearchInput);
  $('plMapSearch').addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeMapSuggestions();
      e.target.blur();
    }
  });
  document.addEventListener('click', function (e) {
    var wrap = $('plMapSearch').parentNode;
    if (wrap && !wrap.contains(e.target)) closeMapSuggestions();
  });

  $('plMapSuggest').addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-place-id]');
    if (!btn) return;
    selectMapPlace(btn.getAttribute('data-place-id'), btn.textContent);
  });

  $('plBtnAdd').addEventListener('click', function () {
    if (!idPelanggan) return;
    resetForm(false);
    showForm(true);
    ensureMap().then(function () { focusMapToDefault(); });
  });
  $('plBtnCancel').addEventListener('click', function () {
    showForm(false);
  });

  $('plBtnSave').addEventListener('click', function () {
    if (!idPelanggan) {
      setMsg('Pilih pelanggan dulu', false);
      return;
    }
    var idLokasi = parseInt($('plIdLokasi').value, 10) || 0;
    var nama = $('plNama').value.trim();
    var detail = $('plDetail').value.trim();
    var isNew = idLokasi <= 0;

    if (!nama || !detail) {
      setMsg('Nama dan detail wajib diisi', false);
      return;
    }
    if (!coordsReady) {
      setMsg('Tentukan titik di peta dulu (geser peta / pilih hasil pencarian)', false);
      return;
    }
    var latt = parseFloat($('plLatt').value || '');
    var longt = parseFloat($('plLongt').value || '');
    if (isNaN(latt) || isNaN(longt) || (latt === 0 && longt === 0)) {
      setMsg('Koordinat belum valid. Geser peta untuk menentukan titik.', false);
      return;
    }

    var payload = {
      id_pelanggan: idPelanggan,
      nama: nama,
      detail: detail,
      latt: latt,
      longt: longt
    };
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
})();
</script>
