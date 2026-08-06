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
      width: 1%;
    }
    #cabang-root .cb-kode {
      font-weight: 900;
      white-space: nowrap;
      width: 1%;
    }
    #cabang-root .cb-alamat {
      font-weight: 750;
      max-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    #cabang-root .cb-actions {
      width: 1%;
      white-space: nowrap;
      text-align: right;
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
      #cabang-root .cb-table th:nth-child(3),
      #cabang-root .cb-table td.cb-alamat {
        max-width: 42vw;
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
            <th>ID</th>
            <th>Kode</th>
            <th>Alamat</th>
            <th></th>
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
            $wifi = (string) ($a['wifi_pass'] ?? '');
            $rent = (int) ($a['rent'] ?? 0);
            $isTraining = !empty($a['is_training']);
          ?>
            <tr class="<?= $isTraining ? 'is-training' : '' ?>">
              <td class="cb-id">
                <?= $id ?>
                <?php if ($isTraining) { ?><span class="cb-chip">TRAINING</span><?php } ?>
              </td>
              <td class="cb-kode"><?= htmlspecialchars($kode) ?></td>
              <td class="cb-alamat" title="<?= htmlspecialchars($alamat) ?>"><?= htmlspecialchars($alamat) ?></td>
              <td class="cb-actions">
                <button
                  type="button"
                  class="cb-btn cb-btn--blue cb-btn--sm btn-edit-cabang"
                  data-id="<?= $id ?>"
                  data-kode="<?= htmlspecialchars($kode, ENT_QUOTES) ?>"
                  data-nama="<?= htmlspecialchars($nama, ENT_QUOTES) ?>"
                  data-alamat="<?= htmlspecialchars($alamat, ENT_QUOTES) ?>"
                  data-kota="<?= htmlspecialchars($idKota, ENT_QUOTES) ?>"
                  data-phone="<?= htmlspecialchars($phone, ENT_QUOTES) ?>"
                  data-wifi="<?= htmlspecialchars($wifi, ENT_QUOTES) ?>"
                  data-rent="<?= $rent ?>"
                >Edit</button>
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
</div>

<script>
(function () {
  var BASE = '<?= URL::BASE_URL ?>';
  var root = document.getElementById('cabang-root');
  if (!root) return;

  var form = document.getElementById('formCabang');
  var head = document.getElementById('cabangFormHead');
  var title = document.getElementById('cabangFormTitle');
  var sub = document.getElementById('cabangFormSub');
  var submitBtn = document.getElementById('cabangSubmitBtn');
  var idField = document.getElementById('cabangIdField');
  var editMode = false;

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
})();
</script>
