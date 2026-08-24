<?php
$kotaOptions = is_array($this->dKota ?? null) ? $this->dKota : [];
?>
<div id="cabang-root">
  <style>
    #cabang-root {
      --cb-ink: #0f172a;
      --cb-muted: #1e293b;
      --cb-line: #94a3b8;
      --cb-blue: #2563eb;
      --cb-blue-deep: #1d4ed8;
      --cb-green: #16a34a;
      --cb-green-deep: #15803d;
      --cb-yellow: #f59e0b;
      --cb-yellow-deep: #d97706;
      --cb-red: #dc2626;
      --cb-radius: 0;
      --cb-border: 1px;
      max-width: 720px;
      width: 100%;
      margin: 8px 0 24px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #cabang-root,
    #cabang-root .btn,
    #cabang-root button,
    #cabang-root input,
    #cabang-root select,
    #cabang-root .cb-chip,
    #cabang-root .op-modal__panel {
      border-radius: 0 !important;
    }
    #cabang-root .cb-shell {
      min-width: 0;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.12), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f4fff8 55%, #fff8eb 100%);
      border: 1px solid #cbd5e1;
      padding: 14px;
    }
    #cabang-root .cb-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: -14px -14px 14px;
      padding: 14px 16px;
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
      color: #fff;
    }
    #cabang-root .cb-head h2 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #cabang-root .cb-head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      opacity: .9;
    }
    #cabang-root .cb-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 10px 14px;
      border: 1px solid transparent;
      font-size: 0.88rem;
      font-weight: 900;
      cursor: pointer;
      white-space: nowrap;
    }
    #cabang-root .cb-btn--primary {
      background: linear-gradient(180deg, var(--cb-green), var(--cb-green-deep));
      color: #fff;
    }
    #cabang-root .cb-btn--blue {
      background: linear-gradient(180deg, var(--cb-blue), var(--cb-blue-deep));
      color: #fff;
    }
    #cabang-root .cb-btn--ghost {
      background: #e2e8f0;
      color: var(--cb-ink);
      border-color: #cbd5e1;
    }
    #cabang-root .cb-btn--sm {
      padding: 6px 10px;
      font-size: 0.76rem;
    }
    #cabang-root .cb-btn:disabled {
      opacity: .55;
      cursor: not-allowed;
    }
    #cabang-root .cb-table-wrap {
      overflow-x: auto;
      border: 1px solid #93c5fd;
      background: linear-gradient(180deg, #eff6ff, #fff);
    }
    #cabang-root .cb-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin: 0;
    }
    #cabang-root .cb-table th,
    #cabang-root .cb-table td {
      padding: 10px 12px;
      border-bottom: 1px solid #e2e8f0;
      color: var(--cb-ink);
      font-size: 0.84rem;
      vertical-align: middle;
    }
    #cabang-root .cb-table th {
      font-size: 0.72rem;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--cb-muted);
      background: rgba(255,255,255,.65);
      white-space: nowrap;
    }
    #cabang-root .cb-table tbody tr:last-child td {
      border-bottom: 0;
    }
    #cabang-root .cb-table tr.is-training {
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #cabang-root .cb-id {
      font-weight: 900;
      font-variant-numeric: tabular-nums;
      white-space: nowrap;
      width: 56px;
    }
    #cabang-root .cb-kode {
      font-weight: 900;
      white-space: nowrap;
      width: 52px;
    }
    #cabang-root .cb-alamat {
      font-weight: 750;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    #cabang-root .cb-actions {
      width: 152px;
      white-space: nowrap;
      text-align: right;
      padding-left: 8px;
    }
    #cabang-root .cb-actions__inner {
      display: inline-flex;
      gap: 6px;
      justify-content: flex-end;
      align-items: center;
    }
    #cabang-root .cb-chip {
      display: inline-block;
      margin-left: 6px;
      padding: 2px 6px;
      border: 1px solid #fcd34d;
      background: #fef3c7;
      color: #92400e;
      font-size: 0.64rem;
      font-weight: 900;
      letter-spacing: 0.03em;
      vertical-align: middle;
    }
    #cabang-root .cb-chip--fonnte {
      border-color: #86efac;
      background: #dcfce7;
      color: #166534;
    }

    /* Modal (op-modal pattern) */
    #cabang-root .op-modal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 5200;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #cabang-root .op-modal.is-open { display: flex; }
    #cabang-root .op-modal__backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, .55);
      cursor: pointer;
    }
    #cabang-root .op-modal__panel {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 480px;
      max-height: calc(100vh - 32px);
      overflow: hidden;
      background: #fff;
      border: 1px solid #cbd5e1;
      box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
      display: flex;
      flex-direction: column;
    }
    #cabang-root .op-modal__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 14px 16px;
      color: #fff;
      font-weight: 900;
      flex-shrink: 0;
    }
    #cabang-root .op-modal__head--blue {
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    }
    #cabang-root .op-modal__head--green {
      background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
    }
    #cabang-root .op-modal__head--cyan {
      background: linear-gradient(105deg, #0e7490 0%, #0891b2 100%);
    }
    #cabang-root .op-modal__panel--maps {
      max-width: 560px;
    }
    #cabang-root .cb-btn--cyan {
      background: linear-gradient(180deg, #0891b2, #0e7490);
      color: #fff;
    }
    #cabang-root #cabangMap {
      width: 100%;
      height: 260px;
      border: 1px solid var(--cb-line);
      background: #e2e8f0;
    }
    #cabang-root .cb-map-wrap {
      position: relative;
      border: 1px solid var(--cb-line);
      background: #e2e8f0;
    }
    #cabang-root .cb-map-wrap #cabangMap {
      border: 0;
    }
    #cabang-root .cb-map-pin {
      pointer-events: none;
      position: absolute;
      left: 50%;
      top: 50%;
      z-index: 2;
      transform: translate(-50%, -100%);
      color: #dc2626;
      font-size: 2rem;
      line-height: 1;
      filter: drop-shadow(0 2px 2px rgba(15, 23, 42, 0.35));
    }
    #cabang-root .cb-map-loading {
      position: absolute;
      inset: 0;
      z-index: 3;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(226, 232, 240, 0.85);
      font-size: 0.78rem;
      font-weight: 800;
      color: #475569;
    }
    #cabang-root .cb-map-loading[hidden] {
      display: none !important;
    }
    #cabang-root .cb-search-wrap {
      position: relative;
      z-index: 5;
    }
    #cabang-root .cb-search-list {
      position: absolute;
      left: 0;
      right: 0;
      top: calc(100% + 4px);
      z-index: 20;
      max-height: 180px;
      overflow-y: auto;
      margin: 0;
      padding: 0;
      list-style: none;
      border: 1px solid var(--cb-line);
      background: #fff;
      box-shadow: 0 12px 24px rgba(15, 23, 42, 0.15);
    }
    #cabang-root .cb-search-list[hidden] {
      display: none !important;
    }
    #cabang-root .cb-search-list button {
      display: block;
      width: 100%;
      padding: 10px 12px;
      border: 0;
      border-bottom: 1px solid #e2e8f0;
      background: #fff;
      color: var(--cb-ink);
      font-size: 0.82rem;
      font-weight: 750;
      text-align: left;
      cursor: pointer;
    }
    #cabang-root .cb-search-list li:last-child button {
      border-bottom: 0;
    }
    #cabang-root .cb-search-list button:hover {
      background: #eff6ff;
    }
    #cabang-root .cb-map-hint {
      margin: 0 0 10px;
      font-size: 0.78rem;
      font-weight: 750;
      color: #475569;
    }
    #cabang-root .cb-map-hint.is-warn {
      color: #b45309;
    }
    #cabang-root .op-modal__head h3 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #cabang-root .op-modal__head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      opacity: .9;
    }
    #cabang-root .op-modal__close {
      width: 36px;
      height: 36px;
      border: 0;
      background: rgba(255,255,255,.18);
      color: #fff;
      font-size: 1rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    #cabang-root .op-modal__close:hover { background: rgba(255,255,255,.32); }
    #cabang-root .op-modal__body {
      padding: 14px 16px;
      background: linear-gradient(180deg, #eff6ff, #fff);
      overflow-y: auto;
      flex: 1;
      min-height: 0;
    }
    #cabang-root .op-modal__foot {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding: 12px 16px;
      background: #fff;
      border-top: 1px solid #e2e8f0;
      flex-shrink: 0;
    }
    #cabang-root .cb-field { margin-bottom: 12px; }
    #cabang-root .cb-field:last-child { margin-bottom: 0; }
    #cabang-root .cb-label {
      display: block;
      margin-bottom: 6px;
      font-size: 0.78rem;
      font-weight: 900;
      color: var(--cb-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #cabang-root .cb-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--cb-line);
      background: #fff;
      color: var(--cb-ink);
      font-size: 0.88rem;
      font-weight: 800;
      outline: none;
      box-sizing: border-box;
    }
    #cabang-root .cb-input:focus {
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
      border-color: var(--cb-blue);
    }
    #cabang-root .cb-input:disabled,
    #cabang-root .cb-input[readonly] {
      background: #f1f5f9;
      color: #475569;
    }
    #cabang-root select.cb-input {
      cursor: pointer;
      appearance: auto;
      -webkit-appearance: menulist;
      min-height: 42px;
    }
    #cabang-root .cb-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    body.op-modal-open { overflow: hidden; }

    @media (max-width: 560px) {
      #cabang-root .cb-head {
        flex-direction: column;
        align-items: flex-start;
      }
      #cabang-root .cb-head .cb-btn { width: 100%; }
      #cabang-root .cb-row { grid-template-columns: 1fr; }
      #cabang-root .cb-id { width: 44px; }
      #cabang-root .cb-kode { width: 44px; }
      #cabang-root .cb-actions { width: 128px; }
      #cabang-root .cb-btn--sm {
        padding: 6px 8px;
        font-size: 0.72rem;
      }
    }
  </style>

  <div class="cb-shell">
    <div class="cb-head">
      <div>
        <h2>Data Cabang</h2>
        <small>ID · Kode · Alamat — edit lewat modal</small>
      </div>
      <button type="button" class="cb-btn cb-btn--primary" id="btnTambahCabang">
        <i class="fas fa-plus"></i> Tambah
      </button>
    </div>

    <div class="cb-table-wrap">
      <table class="cb-table">
        <thead>
          <tr>
            <th class="cb-id">ID</th>
            <th class="cb-kode">Kode</th>
            <th class="cb-alamat">Alamat</th>
            <th class="cb-actions"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data['data_cabang'] as $a) {
            $id = (int) $a['id_cabang'];
            $kode = (string) ($a['kode_cabang'] ?? '');
            $nama = (string) ($a['nama'] ?? '');
            $alamat = (string) ($a['alamat'] ?? '');
            $idKota = (string) ($a['id_kota'] ?? '');
            $phone = (string) ($a['phone_number'] ?? '');
            $idGroupFonnte = (string) ($a['id_group_fonnte'] ?? '');
            $wifi = (string) ($a['wifi_pass'] ?? '');
            $rent = (int) ($a['rent'] ?? 0);
            $latt = $a['latt'] ?? '';
            $long = $a['long'] ?? '';
            $gmaps = (string) ($a['gmaps'] ?? '');
            $isTraining = !empty($a['is_training']);
          ?>
            <tr class="<?= $isTraining ? 'is-training' : '' ?>">
              <td class="cb-id">
                <?= $id ?>
                <?php if ($isTraining) { ?><span class="cb-chip">TRAINING</span><?php } ?>
              </td>
              <td class="cb-kode"><?= htmlspecialchars($kode) ?></td>
              <td class="cb-alamat" title="<?= htmlspecialchars($alamat) ?>">
                <?= htmlspecialchars($alamat) ?>
                <?php if ($idGroupFonnte !== '') { ?>
                  <span class="cb-chip cb-chip--fonnte" title="<?= htmlspecialchars($idGroupFonnte) ?>">FONNTE</span>
                <?php } ?>
              </td>
              <td class="cb-actions">
                <div class="cb-actions__inner">
                  <button
                    type="button"
                    class="cb-btn cb-btn--cyan cb-btn--sm btn-maps-cabang"
                    data-id="<?= $id ?>"
                    data-kode="<?= htmlspecialchars($kode, ENT_QUOTES) ?>"
                    data-nama="<?= htmlspecialchars($nama, ENT_QUOTES) ?>"
                    data-latt="<?= htmlspecialchars((string) $latt, ENT_QUOTES) ?>"
                    data-long="<?= htmlspecialchars((string) $long, ENT_QUOTES) ?>"
                    data-gmaps="<?= htmlspecialchars($gmaps, ENT_QUOTES) ?>"
                  ><i class="fas fa-map-marker-alt"></i> Maps</button>
                  <button
                    type="button"
                    class="cb-btn cb-btn--blue cb-btn--sm btn-edit-cabang"
                    data-id="<?= $id ?>"
                    data-kode="<?= htmlspecialchars($kode, ENT_QUOTES) ?>"
                    data-nama="<?= htmlspecialchars($nama, ENT_QUOTES) ?>"
                    data-alamat="<?= htmlspecialchars($alamat, ENT_QUOTES) ?>"
                    data-kota="<?= htmlspecialchars($idKota, ENT_QUOTES) ?>"
                    data-phone="<?= htmlspecialchars($phone, ENT_QUOTES) ?>"
                    data-group-fonnte="<?= htmlspecialchars($idGroupFonnte, ENT_QUOTES) ?>"
                    data-wifi="<?= htmlspecialchars($wifi, ENT_QUOTES) ?>"
                    data-rent="<?= $rent ?>"
                  >Edit</button>
                </div>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal Tambah / Edit -->
  <div class="op-modal" id="modalCabangForm" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel" role="dialog" aria-modal="true">
      <div class="op-modal__head" id="cabangFormHead">
        <div>
          <h3 id="cabangFormTitle">Tambah Cabang</h3>
          <small id="cabangFormSub">Isi data cabang baru</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <form id="formCabang" autocomplete="off">
        <div class="op-modal__body">
          <input type="hidden" name="id" id="cabangId" value="">
          <div class="cb-field" id="cabangIdField" style="display:none;">
            <label class="cb-label" for="cabangIdDisp">ID Cabang</label>
            <input type="text" class="cb-input" id="cabangIdDisp" readonly>
          </div>
          <div class="cb-row">
            <div class="cb-field">
              <label class="cb-label" for="cabangKode">Kode</label>
              <input type="text" class="cb-input" name="kode_cabang" id="cabangKode" required>
            </div>
            <div class="cb-field">
              <label class="cb-label" for="cabangNama">Nama</label>
              <input type="text" class="cb-input" name="nama" id="cabangNama" maxlength="50" required>
            </div>
          </div>
          <div class="cb-field">
            <label class="cb-label" for="cabangKota">Kota / Area</label>
            <select name="kota" id="cabangKota" class="cb-input" required>
              <option value="" disabled selected>---</option>
              <?php foreach ($kotaOptions as $k) { ?>
                <option value="<?= htmlspecialchars($k['id_kota']) ?>"><?= htmlspecialchars($k['nama_kota']) ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="cb-field">
            <label class="cb-label" for="cabangAlamat">Alamat</label>
            <input type="text" class="cb-input" name="alamat" id="cabangAlamat" required>
          </div>
          <div class="cb-row">
            <div class="cb-field">
              <label class="cb-label" for="cabangPhone">Phone</label>
              <input type="text" class="cb-input" name="phone_number" id="cabangPhone" required>
            </div>
            <div class="cb-field">
              <label class="cb-label" for="cabangWifi">Wifi Pass</label>
              <input type="text" class="cb-input" name="wifi_pass" id="cabangWifi">
            </div>
          </div>
          <div class="cb-field">
            <label class="cb-label" for="cabangGroupFonnte">ID Group Fonnte</label>
            <input type="text" class="cb-input" name="id_group_fonnte" id="cabangGroupFonnte" placeholder="1203630…@g.us (opsional)">
          </div>
          <div class="cb-field">
            <label class="cb-label" for="cabangRent">Rent</label>
            <input type="number" class="cb-input" name="rent" id="cabangRent" min="0" step="1" value="0">
          </div>
        </div>
        <div class="op-modal__foot">
          <button type="button" class="cb-btn cb-btn--ghost" data-op-close>Batal</button>
          <button type="submit" class="cb-btn cb-btn--primary" id="cabangSubmitBtn">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Maps -->
  <div class="op-modal" id="modalCabangMaps" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--maps" role="dialog" aria-modal="true">
      <div class="op-modal__head op-modal__head--cyan">
        <div>
          <h3 id="cabangMapsTitle">Maps Cabang</h3>
          <small id="cabangMapsSub">Cari alamat untuk set koordinat</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <form id="formCabangMaps" autocomplete="off">
        <div class="op-modal__body">
          <input type="hidden" name="id" id="mapsCabangId" value="">
          <div class="cb-field">
            <label class="cb-label" for="mapsSearch">Cari alamat</label>
            <div class="cb-search-wrap">
              <input
                type="text"
                class="cb-input"
                id="mapsSearch"
                placeholder="Ketik nama jalan, tempat, atau alamat…"
                autocomplete="off"
              >
              <ul class="cb-search-list" id="mapsSearchList" hidden></ul>
            </div>
          </div>
          <p class="cb-map-hint" id="mapsHint">Pilih alamat dari hasil pencarian. Koordinat dan link Google Maps terisi otomatis.</p>
          <div class="cb-map-wrap">
            <div id="cabangMap"></div>
            <div class="cb-map-pin" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></div>
            <div class="cb-map-loading" id="cabangMapLoading">Memuat peta…</div>
          </div>
          <div class="cb-row" style="margin-top:12px;">
            <div class="cb-field">
              <label class="cb-label" for="mapsLatt">Latitude (latt)</label>
              <input type="text" class="cb-input" name="latt" id="mapsLatt" readonly required>
            </div>
            <div class="cb-field">
              <label class="cb-label" for="mapsLong">Longitude (long)</label>
              <input type="text" class="cb-input" name="long" id="mapsLong" readonly required>
            </div>
          </div>
          <div class="cb-field">
            <label class="cb-label" for="mapsGmaps">Link Google Maps</label>
            <input type="text" class="cb-input" name="gmaps" id="mapsGmaps" readonly required>
          </div>
        </div>
        <div class="op-modal__foot">
          <button type="button" class="cb-btn cb-btn--ghost" data-op-close>Batal</button>
          <button type="submit" class="cb-btn cb-btn--primary" id="mapsSubmitBtn">Simpan Maps</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var BASE = '<?= URL::BASE_URL ?>';
  var DEFAULT_LAT = 0.5071;
  var DEFAULT_LNG = 101.4478;
  var MAP_ZOOM = 17;
  var root = document.getElementById('cabang-root');
  if (!root) return;

  var form = document.getElementById('formCabang');
  var formMaps = document.getElementById('formCabangMaps');
  var head = document.getElementById('cabangFormHead');
  var title = document.getElementById('cabangFormTitle');
  var sub = document.getElementById('cabangFormSub');
  var submitBtn = document.getElementById('cabangSubmitBtn');
  var mapsSubmitBtn = document.getElementById('mapsSubmitBtn');
  var idField = document.getElementById('cabangIdField');
  var editMode = false;

  var mapInstance = null;
  var mapsScriptPromise = null;
  var mapsSearchTimer = null;
  var mapsSearchSeq = 0;
  var mapsSelectingPlace = false;

  function toast(msg, type) {
    type = type || 'info';
    if (window.MdlToast) {
      if (type === 'ok' || type === 'success') MdlToast.ok(msg);
      else if (type === 'error' || type === 'danger') MdlToast.error(msg);
      else if (type === 'warn' || type === 'warning') MdlToast.warn(msg);
      else MdlToast.info(msg);
      return;
    }
    alert(msg);
  }

  function syncLock() {
    var n = document.querySelectorAll('#cabang-root .op-modal.is-open').length;
    if (n === 0) document.body.classList.remove('op-modal-open');
    else document.body.classList.add('op-modal-open');
  }

  function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    syncLock();
  }

  function closeModal(el) {
    var modal = typeof el === 'string' ? document.getElementById(el) : el;
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    syncLock();
  }

  root.addEventListener('click', function (e) {
    var closeBtn = e.target.closest('[data-op-close]');
    if (!closeBtn) return;
    var modal = closeBtn.closest('.op-modal');
    if (!modal || !root.contains(modal)) return;
    e.preventDefault();
    closeModal(modal);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var open = root.querySelectorAll('.op-modal.is-open');
    if (!open.length) return;
    closeModal(open[open.length - 1]);
  });

  function resetForm() {
    form.reset();
    document.getElementById('cabangId').value = '';
    document.getElementById('cabangIdDisp').value = '';
    document.getElementById('cabangRent').value = '0';
    var kota = document.getElementById('cabangKota');
    if (kota) kota.selectedIndex = 0;
  }

  function setModeTambah() {
    editMode = false;
    resetForm();
    idField.style.display = 'none';
    head.className = 'op-modal__head op-modal__head--blue';
    title.textContent = 'Tambah Cabang';
    sub.textContent = 'Isi data cabang baru';
    submitBtn.textContent = 'Tambah';
  }

  function setModeEdit(btn) {
    editMode = true;
    var id = btn.getAttribute('data-id') || '';
    document.getElementById('cabangId').value = id;
    document.getElementById('cabangIdDisp').value = id;
    document.getElementById('cabangKode').value = btn.getAttribute('data-kode') || '';
    document.getElementById('cabangNama').value = btn.getAttribute('data-nama') || '';
    document.getElementById('cabangAlamat').value = btn.getAttribute('data-alamat') || '';
    document.getElementById('cabangPhone').value = btn.getAttribute('data-phone') || '';
    document.getElementById('cabangGroupFonnte').value = btn.getAttribute('data-group-fonnte') || '';
    document.getElementById('cabangWifi').value = btn.getAttribute('data-wifi') || '';
    document.getElementById('cabangRent').value = btn.getAttribute('data-rent') || '0';
    document.getElementById('cabangKota').value = btn.getAttribute('data-kota') || '';
    idField.style.display = '';
    head.className = 'op-modal__head op-modal__head--green';
    title.textContent = 'Edit Cabang';
    sub.textContent = 'Ubah semua data sekaligus';
    submitBtn.textContent = 'Simpan';
  }

  document.getElementById('btnTambahCabang').addEventListener('click', function () {
    setModeTambah();
    openModal('modalCabangForm');
  });

  root.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-edit-cabang');
    if (!btn || !root.contains(btn)) return;
    setModeEdit(btn);
    openModal('modalCabangForm');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    submitBtn.disabled = true;
    var url = BASE + 'Cabang_List/' + (editMode ? 'update' : 'insert');
    var fd = new FormData(form);
    var data = {};
    fd.forEach(function (v, k) { data[k] = v; });

    $.ajax({
      url: url,
      type: 'POST',
      data: data,
      dataType: 'html',
      success: function (response) {
        var res = String(response || '').trim();
        if (res === '0' || res === '') {
          location.reload(true);
          return;
        }
        toast(res, 'error');
        submitBtn.disabled = false;
      },
      error: function () {
        toast('Gagal menyimpan', 'error');
        submitBtn.disabled = false;
      }
    });
  });

  function parseCoord(val, fallback) {
    var n = parseFloat(val);
    return isNaN(n) ? fallback : n;
  }

  function roundCoord(value) {
    return Math.round(Number(value) * 1e7) / 1e7;
  }

  function buildGmapsUrl(lat, lng) {
    return 'https://www.google.com/maps?q=' + lat + ',' + lng;
  }

  function setMapsHint(text, isWarn) {
    var hint = document.getElementById('mapsHint');
    if (!hint) return;
    hint.textContent = text || 'Pilih alamat dari hasil pencarian. Koordinat dan link Google Maps terisi otomatis.';
    hint.classList.toggle('is-warn', !!isWarn);
  }

  function setMapCoords(lat, lng, gmapsUrl) {
    document.getElementById('mapsLatt').value = roundCoord(lat);
    document.getElementById('mapsLong').value = roundCoord(lng);
    document.getElementById('mapsGmaps').value = gmapsUrl || buildGmapsUrl(roundCoord(lat), roundCoord(lng));
  }

  function syncMapCenter(lat, lng) {
    if (!mapInstance) return;
    if (window.google && google.maps && google.maps.event) {
      google.maps.event.trigger(mapInstance, 'resize');
    }
    mapInstance.setCenter({ lat: lat, lng: lng });
    mapInstance.setZoom(MAP_ZOOM);
  }

  function fetchMapsConfig() {
    return fetch(BASE + 'Cabang_List/mapsConfig', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json();
    });
  }

  function loadGoogleMapsApi(apiKey) {
    if (window.google && window.google.maps && window.google.maps.importLibrary) {
      return window.google.maps.importLibrary('maps');
    }
    if (mapsScriptPromise) return mapsScriptPromise;
    mapsScriptPromise = new Promise(function (resolve, reject) {
      window.gm_authFailure = function () {
        reject(new Error('Google Maps menolak API key browser'));
      };

      var params = { key: apiKey, v: 'weekly', language: 'id', region: 'ID' };
      (function (g) {
        var h;
        var a;
        var k;
        var p = 'The Google Maps JavaScript API';
        var c = 'google';
        var l = 'importLibrary';
        var q = '__ib__';
        var m = document;
        var b = window;
        b = b[c] || (b[c] = {});
        var d = b.maps || (b.maps = {});
        var r = new Set();
        var e = new URLSearchParams();
        var u = function () {
          return (
            h ||
            (h = new Promise(function (f, n) {
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
              a.onerror = function () {
                h = n(new Error(p + ' could not load.'));
              };
              a.async = true;
              m.head.append(a);
            }))
          );
        };
        d[l] = function (f) {
          return r.add(f) && u().then(function () {
            return d[l](f);
          });
        };
      })(params);

      window.google.maps.importLibrary('maps').then(resolve).catch(reject);
    });
    return mapsScriptPromise;
  }

  function ensureGoogleMap(lat, lng) {
    var loadingEl = document.getElementById('cabangMapLoading');
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
        if (loadingEl) loadingEl.hidden = true;
        var el = document.getElementById('cabangMap');
        if (!el) return;
        if (!mapInstance) {
          mapInstance = new mapsLib.Map(el, {
            center: { lat: lat, lng: lng },
            zoom: MAP_ZOOM,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
            cameraControl: false,
            zoomControl: false,
            scrollwheel: false,
            disableDoubleClickZoom: true,
            draggable: false,
            gestureHandling: 'none',
            keyboardShortcuts: false
          });
        }
        syncMapCenter(lat, lng);
      })
      .catch(function (err) {
        if (loadingEl) loadingEl.hidden = true;
        setMapsHint((err && err.message) || 'Peta gagal dimuat', true);
        toast((err && err.message) || 'Peta gagal dimuat', 'error');
      });
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function closeMapsSearchSuggestions() {
    var list = document.getElementById('mapsSearchList');
    if (list) {
      list.hidden = true;
      list.innerHTML = '';
    }
  }

  function getMapBiasCoords() {
    var lat = parseFloat(document.getElementById('mapsLatt').value);
    var lng = parseFloat(document.getElementById('mapsLong').value);
    if (!isNaN(lat) && !isNaN(lng)) {
      return { lat: lat, lng: lng };
    }
    if (mapInstance && mapInstance.getCenter) {
      var center = mapInstance.getCenter();
      if (center) return { lat: center.lat(), lng: center.lng() };
    }
    return { lat: DEFAULT_LAT, lng: DEFAULT_LNG };
  }

  function fetchMapsSearchSuggestions(query) {
    var q = String(query || '').trim();
    if (q.length < 2) {
      closeMapsSearchSuggestions();
      return;
    }
    var seq = ++mapsSearchSeq;
    var payload = { input: q };
    var bias = getMapBiasCoords();
    payload.lat = bias.lat;
    payload.lng = bias.lng;

    fetch(BASE + 'Cabang_List/mapsAutocomplete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (seq !== mapsSearchSeq || mapsSelectingPlace) return;
        var list = document.getElementById('mapsSearchList');
        if (!list) return;
        var items = data && Array.isArray(data.items) ? data.items : [];
        if (!data || (!data.ok && !data.status)) {
          closeMapsSearchSuggestions();
          setMapsHint((data && data.message) || 'Gagal memuat saran alamat.', true);
          return;
        }
        if (!items.length) {
          closeMapsSearchSuggestions();
          setMapsHint('Tidak ada hasil untuk pencarian ini.', true);
          return;
        }
        list.innerHTML = items
          .map(function (item) {
            return (
              '<li><button type="button" data-place-id="' +
              escapeHtml(item.place_id || '') +
              '" data-label="' +
              escapeHtml(item.label || '') +
              '">' +
              escapeHtml(item.label || '') +
              '</button></li>'
            );
          })
          .join('');
        list.hidden = false;
        setMapsHint('Pilih salah satu hasil pencarian.');
      })
      .catch(function () {
        if (seq !== mapsSearchSeq) return;
        closeMapsSearchSuggestions();
        setMapsHint('Gagal memuat saran alamat.', true);
      });
  }

  function onMapsSearchInput() {
    if (mapsSearchTimer) clearTimeout(mapsSearchTimer);
    var input = document.getElementById('mapsSearch');
    var q = input ? String(input.value || '').trim() : '';
    if (q.length < 2) {
      closeMapsSearchSuggestions();
      return;
    }
    mapsSearchTimer = setTimeout(function () {
      fetchMapsSearchSuggestions(q);
    }, 280);
  }

  function selectMapsSearchSuggestion(placeId, label) {
    if (!placeId || mapsSelectingPlace) return;
    mapsSelectingPlace = true;
    closeMapsSearchSuggestions();
    var input = document.getElementById('mapsSearch');
    if (input) input.value = label || '';

    fetch(BASE + 'Cabang_List/mapsPlaceDetails', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify({ place_id: placeId })
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (!data || (!data.ok && !data.status)) {
          setMapsHint((data && data.message) || 'Gagal memuat detail lokasi.', true);
          return;
        }
        if (data.lat == null || data.lng == null) {
          setMapsHint('Koordinat lokasi tidak ditemukan.', true);
          return;
        }
        var lat = Number(data.lat);
        var lng = Number(data.lng);
        var gmaps = buildGmapsUrl(lat, lng);
        setMapCoords(lat, lng, gmaps);
        if (mapInstance) {
          syncMapCenter(lat, lng);
        } else {
          ensureGoogleMap(lat, lng);
        }
        setMapsHint('Koordinat dan link Google Maps sudah terisi.');
      })
      .catch(function () {
        setMapsHint('Gagal memuat detail lokasi.', true);
      })
      .finally(function () {
        mapsSelectingPlace = false;
      });
  }

  function openMapsModal(btn) {
    var id = btn.getAttribute('data-id') || '';
    var kode = btn.getAttribute('data-kode') || '';
    var nama = btn.getAttribute('data-nama') || '';
    var latRaw = btn.getAttribute('data-latt');
    var lngRaw = btn.getAttribute('data-long');
    var hasCoords = latRaw !== '' && lngRaw !== '' && !isNaN(parseFloat(latRaw)) && !isNaN(parseFloat(lngRaw));
    var lat = hasCoords ? parseCoord(latRaw, DEFAULT_LAT) : DEFAULT_LAT;
    var lng = hasCoords ? parseCoord(lngRaw, DEFAULT_LNG) : DEFAULT_LNG;
    var gmaps = btn.getAttribute('data-gmaps') || '';

    document.getElementById('mapsCabangId').value = id;
    document.getElementById('mapsSearch').value = '';
    closeMapsSearchSuggestions();
    setMapsHint('Pilih alamat dari hasil pencarian. Koordinat dan link Google Maps terisi otomatis.');

    if (hasCoords) {
      setMapCoords(lat, lng, gmaps || buildGmapsUrl(lat, lng));
    } else {
      document.getElementById('mapsLatt').value = '';
      document.getElementById('mapsLong').value = '';
      document.getElementById('mapsGmaps').value = '';
    }

    document.getElementById('cabangMapsTitle').textContent = 'Maps · ' + (kode || id);
    document.getElementById('cabangMapsSub').textContent = nama || 'Cari alamat cabang';

    var loadingEl = document.getElementById('cabangMapLoading');
    if (loadingEl) loadingEl.hidden = false;

    openModal('modalCabangMaps');
    ensureGoogleMap(lat, lng);
  }

  root.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-maps-cabang');
    if (!btn || !root.contains(btn)) return;
    openMapsModal(btn);
  });

  document.getElementById('mapsSearch').addEventListener('input', onMapsSearchInput);
  document.getElementById('mapsSearch').addEventListener('focus', onMapsSearchInput);

  document.getElementById('mapsSearchList').addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-place-id]');
    if (!btn) return;
    selectMapsSearchSuggestion(btn.getAttribute('data-place-id'), btn.getAttribute('data-label'));
  });

  document.addEventListener('click', function (e) {
    var wrap = document.querySelector('#cabang-root .cb-search-wrap');
    if (!wrap || wrap.contains(e.target)) return;
    closeMapsSearchSuggestions();
  });

  formMaps.addEventListener('submit', function (e) {
    e.preventDefault();
    mapsSubmitBtn.disabled = true;
    var fd = new FormData(formMaps);
    var data = {};
    fd.forEach(function (v, k) { data[k] = v; });

    $.ajax({
      url: BASE + 'Cabang_List/updateMaps',
      type: 'POST',
      data: data,
      dataType: 'html',
      success: function (response) {
        var res = String(response || '').trim();
        if (res === '0' || res === '') {
          location.reload(true);
          return;
        }
        toast(res, 'error');
        mapsSubmitBtn.disabled = false;
      },
      error: function () {
        toast('Gagal menyimpan maps', 'error');
        mapsSubmitBtn.disabled = false;
      }
    });
  });
})();
</script>
