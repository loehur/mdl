<?php
$mode = (int) ($data['mode'] ?? 0);
$isSelesai = $mode === 1;
$jenisList = $data['jenisList'] ?? ['Perbaikan', 'Pergantian', 'Perawatan', 'Penambahan'];
$canSelesai = !empty($data['canSelesai']);
$isAdmin = !empty($data['isAdmin']);
$idUser = (int) ($data['idUser'] ?? 0);
$kodeCabangUi = strtoupper((string) ($this->dCabang['kode_cabang'] ?? ''));
$namaCabangUi = (string) ($this->dCabang['nama'] ?? ('MDL ' . $kodeCabangUi));
?>
<div id="tiket-root" data-mode="<?= $mode ?>">
  <style>
    #tiket-root {
      --tk-ink: #0f172a;
      --tk-muted: #1e293b;
      --tk-line: #94a3b8;
      --tk-blue: #2563eb;
      --tk-blue-deep: #1d4ed8;
      --tk-green: #16a34a;
      --tk-green-deep: #15803d;
      --tk-yellow: #f59e0b;
      --tk-yellow-deep: #d97706;
      --tk-red: #dc2626;
      --tk-red-deep: #b91c1c;
      --tk-radius: 0;
      --tk-border: 1px;
      max-width: 1100px;
      width: 100%;
      margin: 8px 0 24px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #tiket-root,
    #tiket-root .btn,
    #tiket-root button,
    #tiket-root input,
    #tiket-root select,
    #tiket-root textarea,
    #tiket-root .selectize-input,
    #tiket-root .selectize-dropdown,
    #tiket-root .tk-chip,
    #tiket-root .tk-card,
    #tiket-root .op-modal__panel {
      border-radius: 0 !important;
    }
    #tiket-root .tk-shell {
      min-width: 0;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.12), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f4fff8 55%, #fff8eb 100%);
      border: 1px solid #cbd5e1;
      padding: 14px;
    }
    #tiket-root .tk-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: -14px -14px 14px;
      padding: 14px 16px;
      color: #fff;
    }
    #tiket-root .tk-head--proses {
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    }
    #tiket-root .tk-head--selesai {
      background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
    }
    #tiket-root .tk-head h2 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #tiket-root .tk-head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      opacity: .9;
    }
    #tiket-root .tk-btn {
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
    #tiket-root .tk-btn--primary {
      background: linear-gradient(180deg, var(--tk-green), var(--tk-green-deep));
      color: #fff;
    }
    #tiket-root .tk-btn--blue {
      background: linear-gradient(180deg, var(--tk-blue), var(--tk-blue-deep));
      color: #fff;
    }
    #tiket-root .tk-btn--warn {
      background: linear-gradient(180deg, var(--tk-yellow), var(--tk-yellow-deep));
      color: #111;
    }
    #tiket-root .tk-btn--danger {
      background: linear-gradient(180deg, var(--tk-red), var(--tk-red-deep));
      color: #fff;
    }
    #tiket-root .tk-btn--ghost {
      background: #e2e8f0;
      color: var(--tk-ink);
      border-color: #cbd5e1;
    }
    #tiket-root .tk-btn--sm {
      padding: 6px 10px;
      font-size: 0.76rem;
    }
    #tiket-root .tk-btn:disabled {
      opacity: .55;
      cursor: not-allowed;
    }
    #tiket-root #load.tk-lists {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
      min-width: 0;
      margin-top: 0;
    }

    /* Modal (op-modal pattern) */
    #tiket-root .op-modal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 5200;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #tiket-root .op-modal.is-open { display: flex; }
    #tiket-root .op-modal__backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, .55);
      cursor: pointer;
    }
    #tiket-root .op-modal__panel {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 480px;
      max-height: calc(100vh - 32px);
      overflow: visible;
      background: #fff;
      border: 1px solid #cbd5e1;
      box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
      display: flex;
      flex-direction: column;
    }
    #tiket-root .op-modal__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 14px 16px;
      color: #fff;
      font-weight: 900;
    }
    #tiket-root .op-modal__head--blue {
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    }
    #tiket-root .op-modal__head--green {
      background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
    }
    #tiket-root .op-modal__head--red {
      background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
    }
    #tiket-root .op-modal__head h3 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #tiket-root .op-modal__head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      opacity: .9;
    }
    #tiket-root .op-modal__close {
      width: 32px;
      height: 32px;
      border: 0;
      background: rgba(255,255,255,.2);
      color: #fff;
      font-size: 1rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    #tiket-root .op-modal__close:hover { background: rgba(255,255,255,.32); }
    #tiket-root .op-modal__body {
      padding: 14px 16px;
      background: linear-gradient(180deg, #eff6ff, #fff);
    }
    #tiket-root .op-modal__foot {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding: 12px 16px;
      background: #fff;
      border-top: 1px solid #e2e8f0;
    }
    #tiket-root .tk-field { margin-bottom: 12px; }
    #tiket-root .tk-label {
      display: block;
      margin-bottom: 6px;
      font-size: 0.78rem;
      font-weight: 900;
      color: var(--tk-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #tiket-root .tk-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--tk-line);
      background: #fff;
      color: var(--tk-ink);
      font-size: 0.88rem;
      font-weight: 800;
      outline: none;
    }
    #tiket-root .tk-input:focus {
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
      border-color: var(--tk-blue);
    }
    #tiket-root select.tk-input {
      cursor: pointer;
      appearance: auto;
      -webkit-appearance: menulist;
      min-height: 42px;
    }
    #tiket-root textarea.tk-input {
      min-height: 88px;
      resize: vertical;
      font-weight: 750;
    }
    /* Selectize: satu border saja (Karyawan) */
    #tiket-root select.tize,
    #tiket-root select.selectized {
      border: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      padding: 0 !important;
    }
    #tiket-root .selectize-control,
    #tiket-root .selectize-control.single {
      border: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      margin: 0;
    }
    #tiket-root .selectize-control.single .selectize-input {
      border: 1px solid var(--tk-line) !important;
      border-radius: 0 !important;
      min-height: 42px;
      padding: 10px 12px !important;
      box-shadow: none !important;
      background: #fff !important;
      font-weight: 800;
      color: var(--tk-ink);
    }
    #tiket-root .selectize-control.single .selectize-input.focus {
      border-color: var(--tk-blue) !important;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
    }
    #tiket-root .selectize-control.single .selectize-input:after {
      border: 0 !important;
    }
    #tiket-root .selectize-control.single .selectize-input input {
      font-weight: 800;
      color: var(--tk-ink);
    }
    #tiket-root .selectize-dropdown {
      border: 1px solid var(--tk-line) !important;
      border-radius: 0 !important;
      z-index: 5300 !important;
    }
    #tiket-root .selectize-dropdown .option {
      font-weight: 750;
      color: var(--tk-ink);
    }
    #tiket-root .selectize-dropdown .option.active {
      background: #eff6ff;
      color: var(--tk-ink);
    }
    body.op-modal-open { overflow: hidden; }

    @media (max-width: 560px) {
      #tiket-root .tk-head {
        flex-direction: column;
        align-items: flex-start;
      }
      #tiket-root .tk-head .tk-btn { width: 100%; }
    }
  </style>

  <div class="tk-shell">
    <div class="tk-head <?= $isSelesai ? 'tk-head--selesai' : 'tk-head--proses' ?>">
      <div>
        <h2><?= $isSelesai ? 'Tiket Selesai' : 'Tiket Proses' ?></h2>
        <small><?= $isSelesai ? 'Dikelompokkan per bulan selesai — semua cabang' : 'Semua cabang · tambah tiket baru' ?></small>
      </div>
      <?php if (!$isSelesai) { ?>
        <button type="button" class="tk-btn tk-btn--primary" id="btnTambahTiket">
          <i class="fas fa-plus"></i> Tambah
        </button>
      <?php } ?>
    </div>

    <div id="load" class="tk-lists">
      <?php require __DIR__ . '/view_load.php'; ?>
    </div>
  </div>

  <?php if (!$isSelesai) { ?>
  <!-- Modal Tambah / Edit -->
  <div class="op-modal" id="modalTiketForm" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel" role="dialog" aria-modal="true">
      <div class="op-modal__head op-modal__head--blue">
        <div>
          <h3 id="tiketFormTitle">Tambah Tiket</h3>
          <small>Isi detail tiket</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <form id="formTiket" autocomplete="off">
        <div class="op-modal__body">
          <input type="hidden" name="id_tiket" id="tiketId" value="">
          <div class="tk-field">
            <label class="tk-label" for="tiketJudul">Judul</label>
            <input type="text" class="tk-input" name="judul" id="tiketJudul" maxlength="200" required>
          </div>
          <div class="tk-field">
            <label class="tk-label" for="tiketJenis">Jenis</label>
            <select name="jenis" id="tiketJenis" class="tk-input" required>
              <option value="" selected disabled>Pilih jenis</option>
              <?php foreach ($jenisList as $j) { ?>
                <option value="<?= htmlspecialchars($j) ?>"><?= htmlspecialchars($j) ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="tk-field">
            <label class="tk-label" for="tiketKeterangan">Keterangan</label>
            <textarea class="tk-input" name="keterangan" id="tiketKeterangan" rows="3"></textarea>
          </div>
          <div class="tk-field">
            <label class="tk-label" for="tiketKaryawan">Karyawan</label>
            <select name="karyawan" id="tiketKaryawan" class="tize" style="width:100%;" required>
              <option value="" selected disabled></option>
              <optgroup label="<?= htmlspecialchars($namaCabangUi) ?> [<?= htmlspecialchars($kodeCabangUi) ?>]">
                <?php foreach ($this->user as $a) { ?>
                  <option value="<?= htmlspecialchars($a['nama_user']) ?>"><?= (int) $a['id_user'] . '-' . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
              <?php if (count($this->userCabang) > 0) { ?>
                <optgroup label="----- Cabang Lain -----">
                  <?php foreach ($this->userCabang as $a) { ?>
                    <option value="<?= htmlspecialchars($a['nama_user']) ?>"><?= (int) $a['id_user'] . '-' . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
              <?php } ?>
            </select>
          </div>
        </div>
        <div class="op-modal__foot">
          <button type="button" class="tk-btn tk-btn--ghost" data-op-close>Batal</button>
          <button type="submit" class="tk-btn tk-btn--primary" id="btnSimpanTiket">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($canSelesai) { ?>
  <!-- Modal Selesai -->
  <div class="op-modal" id="modalTiketSelesai" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel" role="dialog" aria-modal="true">
      <div class="op-modal__head op-modal__head--green">
        <div>
          <h3>Tandai Selesai</h3>
          <small id="selesaiJudulPreview">—</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <form id="formTiketSelesai" autocomplete="off">
        <div class="op-modal__body">
          <input type="hidden" name="id_tiket" id="selesaiId" value="">
          <div class="tk-field">
            <label class="tk-label" for="selesaiCatatan">Catatan Selesai</label>
            <textarea class="tk-input" name="catatan_selesai" id="selesaiCatatan" rows="3" required></textarea>
          </div>
          <div class="tk-field">
            <label class="tk-label" for="selesaiKaryawan">Karyawan</label>
            <select name="karyawan_selesai" id="selesaiKaryawan" class="tize" style="width:100%;" required>
              <option value="" selected disabled></option>
              <optgroup label="<?= htmlspecialchars($namaCabangUi) ?> [<?= htmlspecialchars($kodeCabangUi) ?>]">
                <?php foreach ($this->user as $a) { ?>
                  <option value="<?= htmlspecialchars($a['nama_user']) ?>"><?= (int) $a['id_user'] . '-' . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
              <?php if (count($this->userCabang) > 0) { ?>
                <optgroup label="----- Cabang Lain -----">
                  <?php foreach ($this->userCabang as $a) { ?>
                    <option value="<?= htmlspecialchars($a['nama_user']) ?>"><?= (int) $a['id_user'] . '-' . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
              <?php } ?>
            </select>
          </div>
        </div>
        <div class="op-modal__foot">
          <button type="button" class="tk-btn tk-btn--ghost" data-op-close>Batal</button>
          <button type="submit" class="tk-btn tk-btn--primary" id="btnSubmitSelesai">Selesai</button>
        </div>
      </form>
    </div>
  </div>
  <?php } ?>

  <?php if ($isAdmin) { ?>
  <!-- Modal Hapus -->
  <div class="op-modal" id="modalTiketHapus" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel" role="dialog" aria-modal="true">
      <div class="op-modal__head op-modal__head--red">
        <div>
          <h3>Hapus Tiket</h3>
          <small>Tindakan ini tidak dapat dibatalkan</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <p style="margin:0;font-weight:750;color:#475569;font-size:0.88rem;">
          Hapus tiket <strong id="hapusJudulPreview" style="color:#0f172a;"></strong>?
        </p>
        <input type="hidden" id="hapusId" value="">
      </div>
      <div class="op-modal__foot">
        <button type="button" class="tk-btn tk-btn--ghost" data-op-close>Batal</button>
        <button type="button" class="tk-btn tk-btn--danger" id="btnConfirmHapus">Hapus</button>
      </div>
    </div>
  </div>
  <?php } ?>
  <?php } ?>
</div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
(function () {
  var BASE = '<?= URL::BASE_URL ?>';
  var MODE = <?= (int) $mode ?>;
  var root = document.getElementById('tiket-root');
  if (!root) return;

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
    var n = document.querySelectorAll('#tiket-root .op-modal.is-open').length;
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

  function closeAll() {
    root.querySelectorAll('.op-modal.is-open').forEach(function (m) {
      m.classList.remove('is-open');
      m.setAttribute('aria-hidden', 'true');
    });
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

  function reloadList() {
    $('#tiket-root #load').load(BASE + 'Tiket/load/' + MODE);
  }

  function initTize($el, $parent) {
    if (!$el.length || typeof $el.selectize !== 'function') return null;
    if ($el[0].selectize) return $el[0].selectize;
    var opts = {
      create: false,
      sortField: false,
      placeholder: 'Pilih karyawan'
    };
    if ($parent && $parent.length) {
      opts.dropdownParent = $parent;
    }
    $el.selectize(opts);
    return $el[0].selectize;
  }

  function clearTize(id) {
    var el = document.getElementById(id);
    if (el && el.selectize) el.selectize.clear();
    else $('#' + id).val('');
  }

  function setTizeValue(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    var sz = el.selectize;
    if (!sz) {
      $('#' + id).val(value);
      return;
    }
    if (value && !sz.options[value]) {
      sz.addOption({ value: value, text: value });
    }
    sz.setValue(value || '', true);
  }

  initTize($('#tiketKaryawan'), $('#modalTiketForm'));
  initTize($('#selesaiKaryawan'), $('#modalTiketSelesai'));

  function resetForm() {
    $('#tiketId').val('');
    $('#tiketJudul').val('');
    $('#tiketKeterangan').val('');
    $('#tiketJenis').val('');
    $('#tiketFormTitle').text('Tambah Tiket');
    clearTize('tiketKaryawan');
  }

  function openTambah() {
    resetForm();
    openModal('modalTiketForm');
  }

  function b64decode(v) {
    if (!v) return '';
    try {
      var bin = atob(v);
      if (typeof TextDecoder !== 'undefined') {
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return new TextDecoder('utf-8').decode(bytes);
      }
      return decodeURIComponent(escape(bin));
    } catch (e) {
      return '';
    }
  }

  function openEdit(btn) {
    resetForm();
    $('#tiketFormTitle').text('Edit Tiket');
    $('#tiketId').val(btn.getAttribute('data-id') || '');
    $('#tiketJudul').val(b64decode(btn.getAttribute('data-judul') || ''));
    $('#tiketKeterangan').val(b64decode(btn.getAttribute('data-keterangan') || ''));
    $('#tiketJenis').val(btn.getAttribute('data-jenis') || '');
    setTizeValue('tiketKaryawan', b64decode(btn.getAttribute('data-karyawan') || ''));
    openModal('modalTiketForm');
  }

  $('#btnTambahTiket').on('click', function () {
    openTambah();
  });

  $(document).on('click', '#tiket-root .btnEditTiket', function (e) {
    e.preventDefault();
    openEdit(this);
  });

  $(document).on('click', '#tiket-root .btnSelesaiTiket', function (e) {
    e.preventDefault();
    var id = this.getAttribute('data-id') || '';
    var judul = b64decode(this.getAttribute('data-judul') || '');
    $('#selesaiId').val(id);
    $('#selesaiJudulPreview').text(judul);
    $('#selesaiCatatan').val('');
    clearTize('selesaiKaryawan');
    openModal('modalTiketSelesai');
  });

  $(document).on('click', '#tiket-root .btnHapusTiket', function (e) {
    e.preventDefault();
    $('#hapusId').val(this.getAttribute('data-id') || '');
    $('#hapusJudulPreview').text(b64decode(this.getAttribute('data-judul') || ''));
    openModal('modalTiketHapus');
  });

  $('#formTiket').on('submit', function (e) {
    e.preventDefault();
    var id = $('#tiketId').val();
    var url = id ? (BASE + 'Tiket/update') : (BASE + 'Tiket/insert');
    var $btn = $('#btnSimpanTiket');
    var old = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.ajax({
      url: url,
      type: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        if (String(res).trim() === '0') {
          closeAll();
          toast(id ? 'Tiket diperbarui' : 'Tiket ditambahkan', 'ok');
          reloadList();
        } else {
          toast(res || 'Gagal menyimpan', 'error');
        }
      },
      error: function () {
        toast('Gagal menyimpan tiket', 'error');
      },
      complete: function () {
        $btn.prop('disabled', false).html(old);
      }
    });
  });

  $('#formTiketSelesai').on('submit', function (e) {
    e.preventDefault();
    var $btn = $('#btnSubmitSelesai');
    var old = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.ajax({
      url: BASE + 'Tiket/selesai',
      type: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        if (String(res).trim() === '0') {
          closeAll();
          toast('Tiket ditandai selesai', 'ok');
          reloadList();
        } else {
          toast(res || 'Gagal menandai selesai', 'error');
        }
      },
      error: function () {
        toast('Gagal menandai selesai', 'error');
      },
      complete: function () {
        $btn.prop('disabled', false).html(old);
      }
    });
  });

  $('#btnConfirmHapus').on('click', function () {
    var id = $('#hapusId').val();
    if (!id) return;
    var $btn = $(this);
    var old = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.ajax({
      url: BASE + 'Tiket/delete',
      type: 'POST',
      data: { id_tiket: id },
      success: function (res) {
        if (String(res).trim() === '0') {
          closeAll();
          toast('Tiket dihapus', 'ok');
          reloadList();
        } else {
          toast(res || 'Gagal menghapus', 'error');
        }
      },
      error: function () {
        toast('Gagal menghapus tiket', 'error');
      },
      complete: function () {
        $btn.prop('disabled', false).html(old);
      }
    });
  });
})();
</script>
