<?php if (!isset($this)) { /* ensure scope remains same as parent view */
} ?>

<style>
  /* ===== Operasi custom modal (MDL theme, no Bootstrap Modal) ===== */
  .op-modal {
    --op-ink: #0f172a;
    --op-muted: #1e293b;
    --op-line: #94a3b8;
    --op-blue: #2563eb;
    --op-blue-deep: #1d4ed8;
    --op-green: #16a34a;
    --op-green-deep: #15803d;
    --op-yellow: #f59e0b;
    --op-yellow-deep: #d97706;
    --op-red: #dc2626;
    --op-red-deep: #b91c1c;
    --op-radius: 0;
    --op-border: 1px;
    position: fixed;
    inset: 0;
    z-index: 5200;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  .op-modal.is-open { display: flex; }
  .op-modal.is-static .op-modal__backdrop { cursor: default; }
  .op-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.58);
    backdrop-filter: blur(3px);
    cursor: pointer;
  }
  .op-modal__panel {
    position: relative;
    z-index: 1;
    width: min(440px, 100%);
    max-height: min(94vh, 920px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: var(--op-radius);
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
    overflow: visible;
    animation: opModalIn .18s ease-out;
  }
  /* Form + select: panel tinggi supaya dropdown selectize tidak memaksa scroll body */
  .op-modal__panel--form {
    min-height: min(78vh, 640px);
  }
  .op-modal__panel--sm {
    width: min(400px, 100%);
  }
  .op-modal__panel--sm.op-modal__panel--form {
    min-height: min(72vh, 580px);
  }
  .op-modal__panel--md {
    width: min(520px, 100%);
  }
  .op-modal__panel--md.op-modal__panel--form {
    min-height: min(82vh, 720px);
  }
  @keyframes opModalIn {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: none; }
  }
  .op-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    color: #fff;
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    flex-shrink: 0;
  }
  .op-modal__head--blue {
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
  }
  .op-modal__head--green {
    background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
  }
  .op-modal__head--yellow {
    background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
    color: #111;
  }
  .op-modal__head--red {
    background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
  }
  .op-modal__head h3,
  .op-modal__head h5,
  .op-modal__head h6 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  .op-modal__head--yellow h3,
  .op-modal__head--yellow h5,
  .op-modal__head--yellow h6 {
    text-shadow: none;
  }
  .op-modal__head small {
    display: block;
    margin-top: 2px;
    font-size: 0.72rem;
    font-weight: 750;
    opacity: 0.95;
  }
  .op-modal__close {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 0;
    background: rgba(255,255,255,.2);
    color: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .op-modal__close:hover { background: rgba(255,255,255,.32); }
  .op-modal__body {
    padding: 14px 16px;
    overflow-y: auto;
    flex: 1 1 auto;
    background:
      radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.10), transparent 50%),
      radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.10), transparent 45%),
      linear-gradient(180deg, #eef4ff 0%, #f4fff8 50%, #fff8eb 100%);
    color: var(--op-ink);
    font-weight: 750;
    font-size: 0.88rem;
  }
  /* Form modal: body mengisi tinggi panel + overflow visible agar dropdown select tidak memicu scroll */
  .op-modal__panel--form .op-modal__body {
    overflow: visible;
    padding-bottom: 28px;
  }
  .op-modal__panel--form .op-modal__foot {
    margin-top: auto;
    flex-shrink: 0;
  }
  .op-modal__body--plain {
    background: linear-gradient(180deg, #fff, #f8fafc);
  }
  .op-modal__foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
  }
  .op-modal__foot--center { justify-content: center; }
  .op-label {
    display: block;
    margin: 0 0 5px;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--op-muted);
  }
  .op-field { margin-bottom: 12px; }
  .op-field:last-child { margin-bottom: 0; }
  .op-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  /* Input native: satu border saja */
  .op-input,
  .op-modal select.op-input:not(.tize):not(.selectized),
  .op-modal .form-control:not(.tize):not(.selectized) {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--op-line);
    border-radius: 0 !important;
    background: #fff;
    color: var(--op-ink);
    font-weight: 800;
    font-size: 0.88rem;
    outline: none;
  }
  .op-input:focus,
  .op-modal .form-control:not(.tize):not(.selectized):focus {
    border-color: var(--op-blue);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  /* Selectize: jangan double border (select/form-control + selectize-control + selectize-input) */
  .op-modal select.tize,
  .op-modal select.selectized,
  .op-modal select.op-input.tize,
  .op-modal select.op-input.selectized {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0 !important;
  }
  .op-modal .selectize-control,
  .op-modal .selectize-control.single {
    margin: 0;
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
  }
  .op-modal .selectize-control.single .selectize-input {
    border: 1px solid var(--op-line) !important;
    border-radius: 0 !important;
    padding: 10px 12px !important;
    min-height: 42px;
    box-shadow: none !important;
    font-weight: 800;
    background: #fff !important;
  }
  .op-modal .selectize-control.single .selectize-input.focus {
    border-color: var(--op-blue) !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
  }
  .op-modal .selectize-control.single .selectize-input:after {
    border: 0 !important;
  }
  .op-modal .selectize-dropdown {
    border: 1px solid var(--op-line) !important;
    border-radius: 0 !important;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
    z-index: 30 !important;
  }
  .op-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 14px;
    border: 1px solid transparent;
    border-radius: 0 !important;
    font-size: 0.9rem;
    font-weight: 900;
    cursor: pointer;
    line-height: 1.2;
  }
  .op-btn:disabled { opacity: 0.55; cursor: not-allowed; }
  .op-btn.is-loading {
    pointer-events: none;
    min-width: 132px;
    opacity: 0.9;
  }
  .op-btn--ghost {
    background: #e2e8f0;
    color: var(--op-ink);
    border-color: #cbd5e1;
  }
  .op-btn--primary {
    background: linear-gradient(180deg, var(--op-green), var(--op-green-deep));
    color: #fff;
  }
  .op-btn--blue {
    background: linear-gradient(180deg, var(--op-blue), var(--op-blue-deep));
    color: #fff;
  }
  .op-btn--warn {
    background: linear-gradient(180deg, var(--op-yellow), var(--op-yellow-deep));
    color: #111;
  }
  .op-btn--danger {
    background: linear-gradient(180deg, var(--op-red), var(--op-red-deep));
    color: #fff;
  }
  .op-btn--block { width: 100%; }
  .op-summary {
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    background: linear-gradient(180deg, #f8fafc, #fff);
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--op-ink);
  }
  .op-summary > div + div { margin-top: 4px; }
  .op-alert {
    margin-top: 10px;
    padding: 8px 10px;
    border: 1px solid #fca5a5;
    background: linear-gradient(180deg, #fef2f2, #fff);
    color: var(--op-red-deep);
    font-size: 0.8rem;
    font-weight: 750;
  }
  .op-alert--warn {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
    color: #92400e;
  }
  .op-muted { color: #475569; font-weight: 700; font-size: 0.82rem; }
  .op-loading {
    text-align: center;
    padding: 18px 8px;
    color: var(--op-muted);
    font-weight: 800;
  }
  .op-modal .d-none { display: none !important; }
  .op-qr-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 12px;
  }
  .op-cancel-icon {
    width: 52px;
    height: 52px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fef3c7;
    color: #d97706;
    font-size: 1.6rem;
  }
  body.op-modal-open {
    overflow: hidden;
  }
</style>

<!-- Ambil Laundry -->
<form class="ajax" data-operasi="" action="<?= URL::BASE_URL; ?>Antrian/ambil" method="POST">
  <div class="op-modal" id="exampleModal4" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--sm op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="opAmbilTitle">
      <div class="op-modal__head op-modal__head--green">
        <div>
          <h3 id="opAmbilTitle">Ambil Laundry</h3>
          <small>Pilih pengembali</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <div class="op-field">
          <label class="op-label">Pengembali</label>
          <select name="f1" class="ambil tize userChange" style="width: 100%;" required>
            <option value="" selected disabled></option>
            <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
              <?php foreach ($this->user as $a) { ?>
                <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
              <?php } ?>
            </optgroup>
            <?php if (count($this->userCabang) > 0) { ?>
              <optgroup label="----- Cabang Lain -----">
                <?php foreach ($this->userCabang as $a) { ?>
                  <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
            <?php } ?>
          </select>
          <input type="hidden" class="idItem" name="f2" value="" required>
        </div>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="op-btn op-btn--ghost" data-op-close>Batal</button>
        <button type="submit" class="op-btn op-btn--primary">Submit</button>
      </div>
    </div>
  </div>
</form>

<!-- Selesai Operasi -->
<form data-operasi="" class="operasi ajax" action="<?= URL::BASE_URL; ?>Antrian/operasi" method="POST">
  <div class="op-modal" id="exampleModal" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="opSelesaiTitle">
      <div class="op-modal__head op-modal__head--blue">
        <div>
          <h3 id="opSelesaiTitle">Selesai <b class="operasi"></b>!</h3>
          <small id="opSelesaiHint">Catat karyawan &amp; letak</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <div class="op-field">
          <label class="op-label">Karyawan</label>
          <select name="f1" class="operasi tize userChange" style="width: 100%;" required>
            <option value="" selected disabled></option>
            <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
              <?php foreach ($this->user as $a) { ?>
                <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
              <?php } ?>
            </optgroup>
            <?php if (count($this->userCabang) > 0) { ?>
              <optgroup label="----- Cabang Lain -----">
                <?php foreach ($this->userCabang as $a) { ?>
                  <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
            <?php } ?>
          </select>
        </div>
        <input type="hidden" class="idItem" name="f2" value="" required>
        <input type="hidden" class="valueItem" name="f3" value="" required>
        <input type="hidden" name="inTotalNotif" value="" required>
        <!-- Rak/Pack/Hanger hanya untuk layanan terakhir (endLayanan); disembunyikan di addOperasi -->
        <div class="letakRAK">
          <div class="op-field">
            <label class="op-label">Letak / Rak</label>
            <input id="letakRAK" type="text" maxlength="2" name="rak" style="text-transform: uppercase" class="op-input">
          </div>
          <div class="op-field">
            <div class="op-row">
              <div>
                <label class="op-label">Pack</label>
                <input type="number" min="0" value="1" name="pack" class="op-input" id="letakPack">
              </div>
              <div>
                <label class="op-label">Hanger</label>
                <input type="number" min="0" value="0" name="hanger" class="op-input" id="letakHanger">
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="op-btn op-btn--ghost" data-op-close>Batal</button>
        <button type="submit" class="op-btn op-btn--primary">Submit</button>
      </div>
    </div>
  </div>
</form>

<!-- Ubah Penyelesai (semua user; wajib Access Key penyelesai sebelumnya) -->
<form class="operasi ajax" action="<?= URL::BASE_URL; ?>Operasi/ganti_operasi" method="POST">
  <div class="op-modal" id="modalGanti" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--sm op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="opGantiTitle">
      <div class="op-modal__head op-modal__head--red">
        <div>
          <h3 id="opGantiTitle">Ubah Penyelesai</h3>
          <small>Ganti karyawan (hanya order bulan ini), atau Kosong jika belum tuntas. Wajib Access Key penyelesai sebelumnya.</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <div class="op-field">
          <label class="op-label">Ubah dari <span style="color:#dc2626;text-transform:none;letter-spacing:0;" id="awalOP"></span> menjadi</label>
          <select name="f1" class="operasi tize userChange" style="width: 100%;" required>
            <option value="" selected disabled></option>
            <option value="0">— Kosong (hapus penyelesai &amp; notif selesai) —</option>
            <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
              <?php foreach ($this->user as $a) { ?>
                <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
              <?php } ?>
            </optgroup>
            <?php if (count($this->userCabang) > 0) { ?>
              <optgroup label="----- Cabang Lain -----">
                <?php foreach ($this->userCabang as $a) { ?>
                  <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
            <?php } ?>
          </select>
          <input type="hidden" id="id_ganti" name="id" required>
        </div>
        <div class="op-field">
          <label class="op-label" for="gantiAccessKey">Access Key penyelesai sebelumnya</label>
          <input type="password" class="op-input" name="access_key" id="gantiAccessKey" inputmode="numeric" maxlength="4" autocomplete="one-time-code" placeholder="4 digit" required>
          <small style="display:block;margin-top:4px;color:#64748b;font-size:0.78rem;">Harus milik <span id="gantiAccessKeyHint" style="color:#dc2626;font-weight:600;"></span>.</small>
        </div>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="op-btn op-btn--ghost" data-op-close>Batal</button>
        <button type="submit" class="op-btn op-btn--primary">Submit</button>
      </div>
    </div>
  </div>
</form>

<!-- Ubah ke Member -->
<div class="op-modal" id="modalUbahMember" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--sm op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="opMemberTitle">
    <div class="op-modal__head op-modal__head--blue">
      <div>
        <h3 id="opMemberTitle">Ubah ke Member</h3>
        <small>Konversi item ke paket member</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <div id="ubahMemberLoading" class="op-loading">
        <i class="fas fa-spinner fa-spin"></i> Memuat...
      </div>
      <div id="ubahMemberContent" class="d-none">
        <p class="op-muted" id="ubahMemberInfo"></p>
        <div class="op-summary">
          <div>Paket: <strong id="ubahMemberPaket">-</strong></div>
          <div>Qty order: <strong id="ubahMemberQty">-</strong></div>
          <div>Saldo member: <strong id="ubahMemberSaldo">-</strong></div>
          <div>Total order: <strong id="ubahMemberRefTotal">-</strong></div>
          <div id="ubahMemberBayarInfo" class="d-none" style="color:#d97706;margin-top:6px;">Pembayaran Cek/Berhasil: <strong id="ubahMemberDibayar">-</strong></div>
        </div>
        <div id="ubahMemberAlert" class="op-alert d-none"></div>
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close>Batal</button>
      <button type="button" id="btnSimpanMember" class="op-btn op-btn--primary" disabled>Simpan</button>
    </div>
  </div>
</div>

<!-- Ubah Durasi -->
<div class="op-modal" id="modalUbahDurasi" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="opDurasiTitle">
    <div class="op-modal__head op-modal__head--blue">
      <div>
        <h3 id="opDurasiTitle">Ubah Durasi</h3>
        <small>Ganti durasi item</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <div id="ubahDurasiLoading" class="op-loading">
        <i class="fas fa-spinner fa-spin"></i> Memuat...
      </div>
      <div id="ubahDurasiContent" class="d-none">
        <p class="op-muted" id="ubahDurasiInfo"></p>
        <div class="op-field"><strong>Item:</strong> <span id="ubahDurasiItem"></span></div>
        <div class="op-field">
          <label class="op-label" for="ubahDurasiSelect">Pilih Durasi</label>
          <select id="ubahDurasiSelect" class="op-input"></select>
        </div>
        <div class="op-summary">
          <div>Harga item: <strong id="ubahDurasiItemHarga">-</strong></div>
          <div>Total order: <strong id="ubahDurasiRefTotal">-</strong></div>
          <div id="ubahDurasiBayarInfo" class="d-none" style="color:#d97706;margin-top:6px;">Pembayaran Cek/Berhasil: <strong id="ubahDurasiDibayar">-</strong></div>
        </div>
        <div id="ubahDurasiAlert" class="op-alert d-none"></div>
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close>Batal</button>
      <button type="button" id="btnSimpanDurasi" class="op-btn op-btn--primary" disabled>Simpan</button>
    </div>
  </div>
</div>

<!-- Ubah Layanan -->
<div class="op-modal" id="modalUbahLayanan" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="opLayananTitle">
    <div class="op-modal__head op-modal__head--blue">
      <div>
        <h3 id="opLayananTitle">Ubah Layanan</h3>
        <small>Ganti layanan item</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <div id="ubahLayananLoading" class="op-loading">
        <i class="fas fa-spinner fa-spin"></i> Memuat...
      </div>
      <div id="ubahLayananContent" class="d-none">
        <p class="op-muted" id="ubahLayananInfo"></p>
        <div class="op-field"><strong>Item:</strong> <span id="ubahLayananItem"></span></div>
        <div class="op-field">
          <label class="op-label" for="ubahLayananSelect">Pilih Layanan</label>
          <select id="ubahLayananSelect" class="op-input"></select>
        </div>
        <div class="op-summary">
          <div>Harga item: <strong id="ubahLayananItemHarga">-</strong></div>
          <div>Total order: <strong id="ubahLayananRefTotal">-</strong></div>
          <div id="ubahLayananBayarInfo" class="d-none" style="color:#d97706;margin-top:6px;">Pembayaran Cek/Berhasil: <strong id="ubahLayananDibayar">-</strong></div>
        </div>
        <div id="ubahLayananAlert" class="op-alert d-none"></div>
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close>Batal</button>
      <button type="button" id="btnSimpanLayanan" class="op-btn op-btn--primary" disabled>Simpan</button>
    </div>
  </div>
</div>

<!-- Surcharge -->
<form class="ajax" action="<?= URL::BASE_URL; ?>Antrian/surcas" method="POST">
  <div class="op-modal" id="exampleModalSurcas" aria-hidden="true">
    <div class="op-modal__backdrop" data-op-close></div>
    <div class="op-modal__panel op-modal__panel--sm op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="opSurcasTitle">
      <div class="op-modal__head op-modal__head--yellow">
        <div>
          <h3 id="opSurcasTitle">Surcharge / Biaya Tambahan</h3>
          <small>Tambah biaya di luar layanan</small>
        </div>
        <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="op-modal__body">
        <div class="op-field">
          <label class="op-label">Jenis Surcharge</label>
          <select name="surcas" class="op-input" style="width: 100%;" required>
            <option value="" selected disabled></option>
            <?php foreach ($this->surcas as $sc) {
              $idJenis = (int) ($sc['id_surcas_jenis'] ?? 0);
              if (in_array($idJenis, [1, 2, 3], true)) {
                continue;
              }
            ?>
              <option value="<?= $sc['id_surcas_jenis'] ?>"><?= $sc['surcas_jenis'] ?></option>
            <?php } ?>
          </select>
        </div>
        <input type="hidden" name="no_ref" id="id_transaksi">
        <div class="op-field">
          <label class="op-label">Jumlah Biaya</label>
          <input type="number" name="jumlah" class="op-input">
        </div>
        <div class="op-field">
          <label class="op-label">Di input Oleh</label>
          <select name="user" class="tize userSurcas" style="width: 100%;" required>
            <option value="" selected disabled></option>
            <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
              <?php foreach ($this->user as $a) { ?>
                <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
              <?php } ?>
            </optgroup>
            <?php if (count($this->userCabang) > 0) { ?>
              <optgroup label="---- Cabang Lain ----">
                <?php foreach ($this->userCabang as $a) { ?>
                  <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
            <?php } ?>
          </select>
        </div>
      </div>
      <div class="op-modal__foot">
        <button type="button" class="op-btn op-btn--ghost" data-op-close>Batal</button>
        <button type="submit" class="op-btn op-btn--primary">Submit</button>
      </div>
    </div>
  </div>
</form>

<style>
  #offcanvasPayment {
    --bs-offcanvas-width: min(820px, 100vw);
    --pay-ink: #0f172a;
    --pay-muted: #1e293b;
    --pay-line: #cbd5e1;
    --pay-blue: #2563eb;
    --pay-blue-deep: #1d4ed8;
    --pay-green: #16a34a;
    --pay-green-deep: #15803d;
    --pay-yellow: #f59e0b;
    --pay-yellow-deep: #d97706;
    --pay-red: #dc2626;
    --pay-red-deep: #b91c1c;
    --pay-radius: 0;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    color: var(--pay-ink);
  }
  #offcanvasPayment,
  #offcanvasPayment .offcanvas-header,
  #offcanvasPayment .offcanvas-body,
  #offcanvasPayment .btn,
  #offcanvasPayment button,
  #offcanvasPayment input,
  #offcanvasPayment select,
  #offcanvasPayment textarea,
  #offcanvasPayment .form-control,
  #offcanvasPayment .selectize-input,
  #offcanvasPayment .selectize-dropdown,
  #offcanvasPayment .badge {
    border-radius: 0 !important;
  }
  #offcanvasPayment .offcanvas-header {
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    border-bottom: 0;
    padding: 1rem 1.15rem;
  }
  #offcanvasPayment .offcanvas-title {
    font-family: 'fontku', sans-serif;
    font-weight: 900;
    letter-spacing: -0.02em;
    margin: 0;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #offcanvasPayment .offcanvas-body {
    background:
      radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.14), transparent 50%),
      radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.14), transparent 45%),
      linear-gradient(180deg, #eef4ff 0%, #f4fff8 50%, #fff8eb 100%);
    padding: 0;
  }
  #offcanvasPayment .pay-shell {
    padding: 14px 14px 22px;
  }
  #offcanvasPayment .pay-layout {
    display: grid;
    grid-template-columns: 1.05fr 0.95fr;
    gap: 12px;
    align-items: start;
  }
  #offcanvasPayment .pay-panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: var(--pay-radius);
    padding: 14px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  }
  #offcanvasPayment .pay-panel--blue {
    border-color: #93c5fd;
    background: linear-gradient(180deg, #eff6ff, #fff);
  }
  #offcanvasPayment .pay-panel--yellow {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
  }
  #offcanvasPayment .pay-panel + .pay-panel {
    position: sticky;
    top: 8px;
  }
  #offcanvasPayment .pay-panel__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
    color: var(--pay-ink);
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
  }
  #offcanvasPayment .pay-panel__title i {
    width: 30px;
    height: 30px;
    border-radius: 0;
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    color: #fff;
  }
  #offcanvasPayment .pay-panel__title i.is-blue { background: var(--pay-blue); }
  #offcanvasPayment .pay-panel__title i.is-yellow { background: var(--pay-yellow); color: #111; }
  #offcanvasPayment .pay-field {
    margin-bottom: 12px;
  }
  #offcanvasPayment .pay-field:last-child {
    margin-bottom: 0;
  }
  #offcanvasPayment .pay-field-label {
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--pay-muted);
    margin-bottom: 6px;
  }
  #offcanvasPayment .pay-input {
    width: 100%;
    border: 1px solid #94a3b8;
    border-radius: 0;
    background: #fff;
    color: var(--pay-ink);
    padding: 0.55rem 0.75rem;
    font-size: 0.92rem;
    font-weight: 800;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  #offcanvasPayment .pay-input:focus {
    border-color: var(--pay-blue);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  #offcanvasPayment .pay-input.is-readonly {
    background: #fef3c7;
    border-color: #f59e0b;
    color: #92400e;
  }
  /* Penerima: satu border saja (hindari double form-control + selectize) */
  #offcanvasPayment #karyawanBill.form-control,
  #offcanvasPayment #karyawanBill.pay-select {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0 !important;
  }
  #offcanvasPayment .selectize-control {
    border: 0 !important;
    background: transparent !important;
  }
  #offcanvasPayment .selectize-control.single .selectize-input {
    border: 1px solid #94a3b8 !important;
    border-radius: 0 !important;
    background: #fff !important;
    min-height: 42px;
    padding: 8px 12px !important;
    box-shadow: none !important;
    font-weight: 800 !important;
    color: var(--pay-ink) !important;
  }
  #offcanvasPayment .selectize-control.single .selectize-input.focus {
    border-color: var(--pay-blue) !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
  }
  #offcanvasPayment .selectize-control.single .selectize-input:after {
    border: 0 !important;
  }
  #offcanvasPayment .pay-method-grid,
  #offcanvasPayment .pay-note-grid {
    display: grid;
    gap: 8px;
  }
  #offcanvasPayment .pay-method-grid {
    grid-template-columns: repeat(auto-fit, minmax(104px, 1fr));
  }
  #offcanvasPayment .pay-note-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  #offcanvasPayment .pay-opt {
    position: relative;
    margin: 0;
    cursor: pointer;
    user-select: none;
  }
  #offcanvasPayment .pay-opt input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }
  #offcanvasPayment .pay-opt__face {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 86px;
    padding: 10px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 0;
    background: #f8fafc;
    color: #64748b;
    opacity: 0.72;
    transition: border-color .15s ease, background .15s ease, transform .12s ease, box-shadow .15s ease, opacity .15s ease, color .15s ease;
  }
  #offcanvasPayment .pay-opt__face::after {
    content: '';
    position: absolute;
    top: 6px;
    right: 6px;
    width: 18px;
    height: 18px;
    border-radius: 0;
    border: 1px solid #cbd5e1;
    background: #fff;
  }
  #offcanvasPayment .pay-opt__icon {
    width: 36px;
    height: 36px;
    border-radius: 0;
    display: grid;
    place-items: center;
    font-size: 1rem;
    color: #fff;
    background: #94a3b8;
  }
  #offcanvasPayment .pay-opt__name {
    font-size: 0.78rem;
    font-weight: 900;
    line-height: 1.15;
    text-align: center;
  }
  #offcanvasPayment .pay-opt__extra {
    font-size: 0.68rem;
    font-weight: 800;
    color: #64748b;
    line-height: 1.1;
    text-align: center;
    min-height: 0.85em;
  }
  #offcanvasPayment .pay-opt__face:hover {
    opacity: 0.92;
    transform: translateY(-1px);
  }
  /* Selected: sangat jelas */
  #offcanvasPayment .pay-opt.is-selected .pay-opt__face,
  #offcanvasPayment .pay-opt input:checked + .pay-opt__face {
    opacity: 1;
    color: var(--pay-ink);
    border-width: 2px;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.14);
  }
  #offcanvasPayment .pay-opt.is-selected .pay-opt__face::after,
  #offcanvasPayment .pay-opt input:checked + .pay-opt__face::after {
    border: 0;
    background: var(--pay-green);
    box-shadow: 0 0 0 2px #fff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round' d='M3.5 8.5l3 3 6-6'/%3E%3C/svg%3E");
    background-size: 12px 12px;
    background-repeat: no-repeat;
    background-position: center;
  }
  #offcanvasPayment .pay-opt[data-metode-id="1"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-opt[data-metode-id="1"] input:checked + .pay-opt__face {
    border-color: var(--pay-green);
    background: linear-gradient(180deg, #bbf7d0, #dcfce7);
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.28), 0 10px 20px rgba(22, 163, 74, 0.18);
  }
  #offcanvasPayment .pay-opt[data-metode-id="1"].is-selected .pay-opt__icon,
  #offcanvasPayment .pay-opt[data-metode-id="1"] input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-green);
  }
  #offcanvasPayment .pay-opt[data-metode-id="2"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-opt[data-metode-id="2"] input:checked + .pay-opt__face {
    border-color: var(--pay-blue);
    background: linear-gradient(180deg, #bfdbfe, #dbeafe);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.28), 0 10px 20px rgba(37, 99, 235, 0.18);
  }
  #offcanvasPayment .pay-opt[data-metode-id="2"].is-selected .pay-opt__icon,
  #offcanvasPayment .pay-opt[data-metode-id="2"] input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-blue);
  }
  #offcanvasPayment .pay-opt[data-metode-id="3"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-opt[data-metode-id="3"] input:checked + .pay-opt__face {
    border-color: var(--pay-yellow-deep);
    background: linear-gradient(180deg, #fde68a, #fef3c7);
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.35), 0 10px 20px rgba(217, 119, 6, 0.18);
  }
  #offcanvasPayment .pay-opt[data-metode-id="3"].is-selected .pay-opt__icon,
  #offcanvasPayment .pay-opt[data-metode-id="3"] input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-yellow);
    color: #111;
  }
  #offcanvasPayment .pay-opt[data-metode-id="1"].is-selected .pay-opt__face::after,
  #offcanvasPayment .pay-opt[data-metode-id="1"] input:checked + .pay-opt__face::after {
    background-color: var(--pay-green);
  }
  #offcanvasPayment .pay-opt[data-metode-id="2"].is-selected .pay-opt__face::after,
  #offcanvasPayment .pay-opt[data-metode-id="2"] input:checked + .pay-opt__face::after {
    background-color: var(--pay-blue);
  }
  #offcanvasPayment .pay-opt[data-metode-id="3"].is-selected .pay-opt__face::after,
  #offcanvasPayment .pay-opt[data-metode-id="3"] input:checked + .pay-opt__face::after {
    background-color: var(--pay-yellow-deep);
  }
  #offcanvasPayment .pay-note-grid .pay-opt__face {
    min-height: 58px;
    flex-direction: row;
    gap: 0.45rem;
    padding: 0.55rem 0.5rem;
  }
  #offcanvasPayment .pay-note-grid .pay-opt__icon {
    width: 28px;
    height: 28px;
    border-radius: 0;
    font-size: 0.82rem;
    flex-shrink: 0;
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="QRIS"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="QRIS"] input:checked + .pay-opt__face {
    border-color: var(--pay-blue);
    background: linear-gradient(180deg, #bfdbfe, #dbeafe);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.28), 0 8px 16px rgba(37, 99, 235, 0.16);
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="QRIS"].is-selected .pay-opt__icon,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="QRIS"] input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-blue);
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="QRIS"].is-selected .pay-opt__face::after,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="QRIS"] input:checked + .pay-opt__face::after {
    background-color: var(--pay-blue);
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BCA"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BCA"] input:checked + .pay-opt__face {
    border-color: var(--pay-green);
    background: linear-gradient(180deg, #bbf7d0, #dcfce7);
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.28), 0 8px 16px rgba(22, 163, 74, 0.16);
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BCA"].is-selected .pay-opt__icon,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BCA"] input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-green);
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BCA"].is-selected .pay-opt__face::after,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BCA"] input:checked + .pay-opt__face::after {
    background-color: var(--pay-green);
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BRI"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BRI"] input:checked + .pay-opt__face {
    border-color: var(--pay-yellow-deep);
    background: linear-gradient(180deg, #fde68a, #fef3c7);
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.35), 0 8px 16px rgba(217, 119, 6, 0.16);
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BRI"].is-selected .pay-opt__icon,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BRI"] input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-yellow);
    color: #111;
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BRI"].is-selected .pay-opt__face::after,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="BRI"] input:checked + .pay-opt__face::after {
    background-color: var(--pay-yellow-deep);
  }
  #offcanvasPayment .pay-note-grid .pay-opt__name {
    font-size: 0.82rem;
    font-weight: 900;
  }
  #offcanvasPayment .pay-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    border: 0;
    border-radius: 0;
    padding: 12px 14px;
    font-size: 0.95rem;
    font-weight: 900;
    cursor: pointer;
    text-decoration: none;
    transition: transform .12s ease, filter .12s ease;
  }
  #offcanvasPayment .pay-btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.05);
  }
  #offcanvasPayment .pay-btn--warn {
    background: linear-gradient(135deg, var(--pay-yellow-deep), var(--pay-yellow));
    color: #111;
    box-shadow: 0 10px 22px rgba(217, 119, 6, 0.28);
  }
  #offcanvasPayment .pay-btn--ghost {
    background: #e2e8f0;
    color: var(--pay-ink);
  }
  #offcanvasPayment .pay-btn--pass {
    background: linear-gradient(135deg, var(--pay-blue-deep), var(--pay-blue));
    color: #fff;
    box-shadow: 0 10px 22px rgba(37, 99, 235, 0.28);
  }
  #offcanvasPayment .pay-btn--primary {
    background: linear-gradient(135deg, var(--pay-green-deep), var(--pay-green));
    color: #fff;
    box-shadow: 0 10px 22px rgba(22, 163, 74, 0.32);
  }
  #offcanvasPayment .pay-tb-info {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.85rem;
    border-radius: 0;
    background: #fff7ed;
    border: 1px solid #fbbf24;
    color: #92400e;
    font-size: 0.84rem;
    font-weight: 750;
    line-height: 1.35;
  }
  #offcanvasPayment .pay-tb-info button {
    border: 0;
    background: transparent;
    color: var(--pay-red);
    font-weight: 900;
    padding: 0;
    cursor: pointer;
  }
  #offcanvasPayment .pay-bill-list {
    max-height: 240px;
    overflow-y: auto;
    display: grid;
    gap: 8px;
    padding-right: 0.15rem;
  }
  #offcanvasPayment .pay-bill-item {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 0.65rem;
    align-items: center;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 0;
    background: #fff;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
    cursor: pointer;
  }
  #offcanvasPayment .pay-bill-item__ref {
    font-size: 0.88rem;
    font-weight: 900;
    color: var(--pay-ink);
    word-break: break-all;
  }
  #offcanvasPayment .pay-bill-item__amt {
    font-size: 0.88rem;
    font-weight: 900;
    color: var(--pay-green);
    min-width: 4.5rem;
    text-align: right;
  }
  #offcanvasPayment .pay-bill-item input.cek {
    width: 1.1rem;
    height: 1.1rem;
    accent-color: var(--pay-blue);
    cursor: pointer;
  }
  #offcanvasPayment .pay-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 12px 0 10px;
    padding: 12px 14px;
    border-radius: 0;
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border: 1px solid #f87171;
  }
  #offcanvasPayment .pay-total__label {
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--pay-red-deep);
  }
  #offcanvasPayment .pay-total__value {
    font-size: 1.25rem;
    font-weight: 900;
    color: var(--pay-red);
    letter-spacing: -0.02em;
  }
  #offcanvasPayment .pay-money-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 10px;
  }
  #offcanvasPayment .pay-actions {
    display: grid;
    grid-template-columns: 0.85fr 1.35fr;
    gap: 8px;
    margin-top: 12px;
  }
  #offcanvasPayment #alertRecap {
    border: 1px solid #f87171;
    background: #fef2f2;
    color: var(--pay-red-deep);
    border-radius: 0;
    padding: 0.7rem 0.85rem;
    margin-bottom: 12px;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.25;
  }
  #offcanvasPayment .pay-sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
  }
  @media (max-width: 720px) {
    #offcanvasPayment .pay-layout {
      grid-template-columns: 1fr;
    }
    #offcanvasPayment .pay-panel + .pay-panel {
      position: static;
    }
    #offcanvasPayment .pay-money-row,
    #offcanvasPayment .pay-actions {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasPayment" aria-labelledby="offcanvasPaymentLabel" data-bs-backdrop="true" data-bs-scroll="true" style="z-index: 1100; --bs-offcanvas-width: min(820px, 100vw);">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasPaymentLabel">
      <i class="fas fa-wallet me-2"></i>Pembayaran
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div id="loadRekap" class="pay-shell w-100">
      <form method="POST" class="ajax_json">
        <div id="alertRecap" class="d-none"></div>

        <div class="pay-layout">
          <section class="pay-panel pay-panel--blue">
            <h6 class="pay-panel__title"><i class="fas fa-sliders-h is-blue"></i> Metode & Penerima</h6>

            <div class="pay-field">
              <div class="pay-field-label">Penerima</div>
              <select name="karyawanBill" id="karyawanBill" class="tize" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                  <?php foreach ($this->user as $a) { ?>
                    <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
                <?php if (count($this->userCabang) > 0) { ?>
                  <optgroup label="----- Cabang Lain -----">
                    <?php foreach ($this->userCabang as $a) { ?>
                      <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                    <?php } ?>
                  </optgroup>
                <?php } ?>
              </select>
            </div>

            <div class="pay-field">
              <div class="pay-field-label">Metode Pembayaran</div>
              <div class="pay-method-grid" id="metodeBillRadios">
                <?php
                $metodeFirst = true;
                foreach ($this->dMetodeMutasi as $a) {
                  if ($data['saldoTunai'] <= 0 && $a['id_metode_mutasi'] == 3) {
                    continue;
                  }
                  $mid = (int)$a['id_metode_mutasi'];
                  $mname = $a['metode_mutasi'];
                  $mextra = ($mid == 3) ? ('[ ' . number_format($data['saldoTunai']) . ' ]') : '';
                  $micon = 'fa-coins';
                  if ($mid == 1) $micon = 'fa-money-bill-wave';
                  elseif ($mid == 2) $micon = 'fa-credit-card';
                  elseif ($mid == 3) $micon = 'fa-wallet';
                  ?>
                  <label class="pay-opt<?= $metodeFirst ? ' is-selected' : '' ?>" data-metode-id="<?= $mid ?>">
                    <input type="radio" name="metodeBillRadio" value="<?= $mid ?>"<?= $metodeFirst ? ' checked' : '' ?>>
                    <span class="pay-opt__face">
                      <span class="pay-opt__icon"><i class="fas <?= $micon ?>"></i></span>
                      <span class="pay-opt__name"><?= htmlspecialchars($mname) ?></span>
                      <span class="pay-opt__extra"><?= htmlspecialchars($mextra) ?></span>
                    </span>
                  </label>
                <?php
                  $metodeFirst = false;
                } ?>
              </div>
              <select name="metodeBill" id="metodeBill" class="metodeBayarBill pay-sr-only" required tabindex="-1" aria-hidden="true">
                <?php
                $metodeFirstOpt = true;
                foreach ($this->dMetodeMutasi as $a) {
                  if ($data['saldoTunai'] <= 0 && $a['id_metode_mutasi'] == 3) {
                    continue;
                  } ?>
                  <option value="<?= $a['id_metode_mutasi'] ?>"<?= $metodeFirstOpt ? ' selected' : '' ?>><?= $a['metode_mutasi'] ?> <?= ($a['id_metode_mutasi'] == 3) ? "[ " . number_format($data['saldoTunai']) . " ]" : "" ?></option>
                <?php
                  $metodeFirstOpt = false;
                } ?>
              </select>
            </div>

            <div class="pay-field" id="rowTanggungBayar">
              <button type="button" class="pay-btn pay-btn--warn" id="btnTanggungBayar">
                <i class="fas fa-user-friends"></i> Tanggung Bayar
              </button>
            </div>

            <div class="pay-field d-none" id="rowTanggungBayarInfo">
              <div class="pay-tb-info">
                <div>
                  <strong>Penanggung Bayar:</strong> <span id="tbNamaPenanggung"></span><br>
                  <strong>Saldo:</strong> Rp <span id="tbSaldoPenanggung"></span>
                </div>
                <button type="button" id="btnBatalTanggungBayar">Batal</button>
              </div>
              <input type="hidden" id="idPenanggungBayar" value="">
            </div>

            <div class="pay-field" id="nTunaiBill" style="display:none;">
              <div class="pay-field-label">Tujuan Non Tunai</div>
              <div class="pay-note-grid" id="noteBillRadios">
                <?php foreach (URL::NON_TUNAI as $ntm) {
                  $isQris = strtoupper($ntm) === 'QRIS';
                  $nicon = $isQris ? 'fa-qrcode' : 'fa-university';
                  ?>
                  <label class="pay-opt<?= $isQris ? ' is-selected' : '' ?>" data-note="<?= htmlspecialchars($ntm) ?>">
                    <input type="radio" name="noteBillRadio" value="<?= htmlspecialchars($ntm) ?>"<?= $isQris ? ' checked' : '' ?>>
                    <span class="pay-opt__face">
                      <span class="pay-opt__icon"><i class="fas <?= $nicon ?>"></i></span>
                      <span class="pay-opt__name"><?= htmlspecialchars($ntm) ?></span>
                    </span>
                  </label>
                <?php } ?>
              </div>
              <select name="noteBill" id="noteBill" class="pay-sr-only" tabindex="-1" aria-hidden="true">
                <?php foreach (URL::NON_TUNAI as $ntm) {
                  $isQris = strtoupper($ntm) === 'QRIS';
                  ?>
                  <option value="<?= $ntm ?>"<?= $isQris ? ' selected' : '' ?>><?= $ntm ?></option>
                <?php } ?>
              </select>
            </div>
          </section>

          <section class="pay-panel pay-panel--yellow">
            <h6 class="pay-panel__title"><i class="fas fa-receipt is-yellow"></i> Tagihan & Bayar</h6>

            <div class="pay-bill-list">
              <?php
              $totalTagihan = 0;
              foreach ($loadRekap as $key => $value) {
                $totalTagihan += $value;
                ?>
                <label class="pay-bill-item">
                  <span class="pay-bill-item__ref"><?= htmlspecialchars($key) ?></span>
                  <span class="pay-bill-item__amt"><?= number_format($value) ?></span>
                  <input class="cek" type="checkbox" data-jumlah="<?= $value ?>" data-ref="<?= htmlspecialchars($key) ?>" checked>
                </label>
              <?php } ?>
            </div>

            <div class="pay-total">
              <span class="pay-total__label">Total Tagihan</span>
              <span class="pay-total__value" data-total="">
                <span id="totalBill" data-total="<?= $totalTagihan ?>"><?= number_format($totalTagihan) ?></span>
              </span>
            </div>

            <a href="javascript:void(0)" class="pay-btn pay-btn--pass bayarPasMulti">
              <i class="fas fa-bolt"></i> Bayar Pas
            </a>

            <div class="pay-money-row">
              <div class="pay-field">
                <div class="pay-field-label">Jumlah Bayar</div>
                <input id="bayarBill" name="dibayarBill" class="pay-input text-end input-jumlah-bayar" type="text" inputmode="numeric" placeholder="0" value="" required />
              </div>
              <div class="pay-field">
                <div class="pay-field-label">Kembalian</div>
                <input id="kembalianBill" name="kembalianBill" class="pay-input text-end is-readonly" type="text" readonly placeholder="0" />
              </div>
            </div>

            <div class="pay-actions">
              <button data-bs-dismiss="offcanvas" type="button" class="pay-btn pay-btn--ghost">Batal</button>
              <button type="submit" id="btnBayarBill" class="pay-btn pay-btn--primary">
                <i class="fas fa-wallet"></i> Bayar
              </button>
            </div>
          </section>
        </div>
      </form>
    </div>
  </div>
</div>



<!-- Modal Tanggung Bayar -->
<div class="op-modal" id="modalTanggungBayar" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--md op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="opTbTitle">
    <div class="op-modal__head op-modal__head--yellow">
      <div>
        <h3 id="opTbTitle"><i class="fas fa-user-friends"></i> Tanggung Bayar</h3>
        <small>Pilih penanggung dengan saldo</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <div class="op-alert" style="margin-top:0;margin-bottom:12px;">
        <i class="fas fa-exclamation-triangle"></i>
        Penggunaan saldo untuk tanggung bayar diawasi ketat oleh Admin. Mohon hati-hati dalam memilih penanggung bayar.
      </div>
      <p class="op-muted" style="margin-bottom:8px;">Order atas nama: <strong><?= strtoupper($nama_pelanggan ?? '') ?></strong></p>
      <div class="op-field">
        <label class="op-label" for="searchPenanggungBayar">Cari</label>
        <input type="text" id="searchPenanggungBayar" class="op-input" placeholder="Cari nama atau nomor HP..." autocomplete="off">
      </div>
      <div id="listPenanggungBayar" style="max-height: 280px; overflow-y: auto; border: 1px solid #cbd5e1; background:#fff;">
        <div class="op-loading"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>
      </div>
      <div id="tbKonfirmasi" class="d-none" style="margin-top:12px;padding:10px 12px;border:1px solid #fcd34d;background:linear-gradient(180deg,#fffbeb,#fff);">
        <p class="op-muted" style="margin-bottom:8px;">
          Bayar tagihan <strong id="tbKonfirmasiOrder"></strong> menggunakan saldo:
        </p>
        <p style="margin-bottom:10px;">
          <strong id="tbKonfirmasiNama" style="color:#2563eb;"></strong><br>
          <span class="op-muted">Saldo: Rp <span id="tbKonfirmasiSaldo"></span></span>
        </p>
        <button type="button" class="op-btn op-btn--primary op-btn--block" id="btnKonfirmasiTanggungBayar">
          <i class="fas fa-check"></i> Gunakan Saldo Ini
        </button>
      </div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close>Tutup</button>
    </div>
  </div>
</div>

<!-- Modal Alert -->
<div class="op-modal" id="modalAlert" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="modalAlertTitle">
    <div class="op-modal__head op-modal__head--blue" id="modalAlertHead">
      <div>
        <h3>
          <i class="fas fa-info-circle" id="modalAlertIcon"></i>
          <span id="modalAlertTitle">Informasi</span>
        </h3>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body op-modal__body--plain">
      <p id="modalAlertMessage" style="margin:0;white-space:pre-wrap;"></p>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--blue" data-op-close>OK</button>
    </div>
  </div>
</div>

<!-- Modal QR Code -->
<div class="op-modal" id="modalQR" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="opQrTitle">
    <div class="op-modal__head op-modal__head--blue">
      <div>
        <h3 id="opQrTitle">Scan QRIS</h3>
        <small>Tunggu konfirmasi pembayaran</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body" style="text-align:center;">
      <div id="qrcode" class="op-qr-wrap"></div>
      <p style="margin:0;font-weight:900;" id="qrTotal"></p>
      <p style="margin:4px 0 0;" id="qrNama"></p>
      <div id="devModeLabel" class="d-none" style="margin-top:10px;">
        <span style="display:inline-block;padding:4px 8px;background:#f59e0b;color:#111;font-weight:900;font-size:0.72rem;">DEV MODE - FAKE QR</span>
        <div class="op-summary" style="margin-top:8px;text-align:left;font-size:0.7rem;overflow-wrap:break-word;" id="devApiRes"></div>
      </div>
    </div>
    <div class="op-modal__foot op-modal__foot--center">
      <button type="button" class="op-btn op-btn--warn" id="btnCekStatusQR"><i class="fas fa-sync"></i> Cek Status</button>
      <button type="button" class="op-btn op-btn--blue" id="btnPrintQR"><i class="fas fa-print"></i> Print</button>
      <button type="button" class="op-btn op-btn--ghost" data-op-close>Tutup</button>
    </div>
  </div>
</div>

<!-- Modal Cancel Payment -->
<div class="op-modal" id="modalCancelPayment" aria-hidden="true" data-op-static="1">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="opCancelPayTitle">
    <div class="op-modal__head op-modal__head--red">
      <div>
        <h3 id="opCancelPayTitle">Batalkan Pembayaran?</h3>
        <small>Data akan dihapus permanen</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body" style="text-align:center;">
      <div class="op-cancel-icon"><i class="fas fa-exclamation-triangle"></i></div>
      <p style="margin:0 0 8px;font-weight:800;" id="cancelPaymentInfo"></p>
      <p class="op-muted" style="color:#b91c1c;margin:0;">Data pembayaran akan dihapus permanen.</p>
    </div>
    <div class="op-modal__foot op-modal__foot--center">
      <button type="button" class="op-btn op-btn--ghost" data-op-close>Batal</button>
      <button type="button" class="op-btn op-btn--danger" id="btnConfirmCancel">
        <i class="fas fa-trash-alt"></i> Hapus
      </button>
    </div>
  </div>
</div>

<!-- Modal Detail Nota Timeline -->
<style>
  #modalNotaDetail .op-modal__panel {
    width: min(480px, 100%);
  }
  #modalNotaDetail .op-modal__body {
    padding: 10px 12px;
    background:
      radial-gradient(90% 50% at 0% 0%, rgba(37,99,235,.10), transparent 55%),
      radial-gradient(80% 40% at 100% 0%, rgba(22,163,74,.08), transparent 50%),
      linear-gradient(180deg, #f8fafc 0%, #fff 100%);
  }
  #modalNotaDetail .op-modal__foot {
    padding: 8px 12px;
  }
  .ndt-loading {
    text-align: center;
    padding: 18px 8px;
    color: #1e293b;
    font-weight: 800;
    font-size: 0.84rem;
  }
  .ndt-loading i { color: #2563eb; margin-right: 6px; }
  .ndt-error {
    border: 1px solid #fca5a5;
    background: #fef2f2;
    color: #b91c1c;
    padding: 7px 9px;
    font-weight: 800;
    font-size: 0.82rem;
  }
  .ndt-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 5px;
    margin-bottom: 8px;
  }
  .ndt-summary__box {
    border: 1px solid #cbd5e1;
    background: #fff;
    padding: 5px 7px;
  }
  .ndt-summary__box--full { grid-column: 1 / -1; }
  .ndt-summary__label {
    display: block;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #1e293b;
    margin-bottom: 1px;
    line-height: 1.2;
  }
  .ndt-summary__value {
    font-size: 0.82rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1.25;
  }
  .ndt-summary__meta {
    display: block;
    margin-top: 1px;
    font-size: 0.7rem;
    font-weight: 750;
    color: #334155;
    line-height: 1.25;
  }
  .ndt-badge {
    display: inline-block;
    padding: 0 5px;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    color: #0f172a;
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.02em;
    vertical-align: middle;
    line-height: 1.35;
  }
  .ndt-badge--ok {
    background: #f0fdf4;
    border-color: #86efac;
    color: #15803d;
  }
  .ndt-badge--warn {
    background: #fffbeb;
    border-color: #fcd34d;
    color: #b45309;
  }
  .ndt-badge--info {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
  }
  .ndt-section {
    margin-top: 8px;
  }
  .ndt-section__title {
    margin: 0 0 5px;
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #1e293b;
  }
  .ndt-pay {
    border: 1px solid #cbd5e1;
    background: #fff;
    margin-bottom: 4px;
    padding: 5px 7px;
  }
  .ndt-pay__top {
    display: flex;
    justify-content: space-between;
    gap: 6px;
    align-items: baseline;
  }
  .ndt-pay__amount {
    font-weight: 900;
    font-size: 0.82rem;
    color: #0f172a;
    white-space: nowrap;
  }
  .ndt-pay__amount.is-cancel {
    color: #94a3b8;
    text-decoration: line-through;
  }
  .ndt-pay__meta {
    margin-top: 1px;
    font-size: 0.7rem;
    font-weight: 750;
    color: #334155;
    line-height: 1.25;
  }
  .ndt-item {
    border: 1px solid #cbd5e1;
    background: #fff;
    margin-bottom: 6px;
  }
  .ndt-item__head {
    display: flex;
    justify-content: space-between;
    gap: 6px;
    align-items: flex-start;
    padding: 5px 7px;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #eff6ff, #fff);
  }
  .ndt-item__title {
    font-weight: 900;
    color: #0f172a;
    font-size: 0.8rem;
    line-height: 1.25;
  }
  .ndt-item__sub {
    display: block;
    margin-top: 1px;
    font-size: 0.68rem;
    font-weight: 750;
    color: #334155;
    line-height: 1.25;
  }
  .ndt-item__total {
    font-weight: 900;
    font-size: 0.8rem;
    color: #0f172a;
    white-space: nowrap;
  }
  .ndt-tl {
    list-style: none;
    margin: 0;
    padding: 5px 6px 4px 8px;
  }
  .ndt-tl__row {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 0 0 3px 0;
    min-height: 0;
  }
  .ndt-tl__row:last-child { padding-bottom: 0; }
  .ndt-tl__row:last-child::before { display: none; }
  .ndt-tl__row::before {
    content: "";
    position: absolute;
    left: 4px;
    top: 11px;
    bottom: -1px;
    width: 2px;
    background: #cbd5e1;
  }
  .ndt-tl__dot {
    position: relative;
    left: auto;
    top: auto;
    flex: 0 0 10px;
    width: 10px;
    height: 10px;
    margin-top: 0.2em;
    border: 2px solid #94a3b8;
    background: #fff;
    box-sizing: border-box;
  }
  .ndt-tl__content {
    flex: 1 1 auto;
    min-width: 0;
    line-height: 1.15;
  }
  .ndt-tl__row.is-done .ndt-tl__dot {
    border-color: #16a34a;
    background: #16a34a;
  }
  .ndt-tl__row.is-pending .ndt-tl__dot {
    border-color: #94a3b8;
    background: #f8fafc;
  }
  .ndt-tl__row.is-jemput .ndt-tl__dot {
    border-color: #d97706;
    background: #f59e0b;
  }
  .ndt-tl__row.is-antar .ndt-tl__dot {
    border-color: #1d4ed8;
    background: #2563eb;
  }
  .ndt-tl__row.is-ambil .ndt-tl__dot {
    border-color: #15803d;
    background: #16a34a;
  }
  .ndt-tl__row.is-inferred .ndt-tl__dot {
    border-color: #86efac;
    background: #f0fdf4;
  }
  .ndt-tl__label {
    display: block;
    font-weight: 900;
    color: #0f172a;
    font-size: 0.74rem;
    line-height: 1.15;
  }
  .ndt-tl__meta {
    display: block;
    margin-top: 0;
    font-size: 0.64rem;
    font-weight: 750;
    color: #334155;
    line-height: 1.15;
  }
  .ndt-tl__chip {
    display: inline-block;
    margin-left: 3px;
    padding: 0 4px;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    color: #0f172a;
    font-size: 0.56rem;
    font-weight: 900;
    line-height: 1.2;
    vertical-align: middle;
  }
  .ndt-tl__chip--inferred {
    background: #f0fdf4;
    border-color: #86efac;
    color: #15803d;
  }
  .ndt-empty {
    padding: 5px 7px;
    font-size: 0.72rem;
    font-weight: 750;
    color: #64748b;
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
  }
  #notaDetailCapture {
    background: #fff;
    padding: 14px 14px 16px;
    box-sizing: border-box;
  }
</style>
<div class="op-modal" id="modalNotaDetail" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel" role="dialog" aria-modal="true" aria-labelledby="opNotaDetailTitle">
    <div class="op-modal__head op-modal__head--blue">
      <div>
        <h3 id="opNotaDetailTitle">Detail Nota</h3>
        <small id="opNotaDetailSub">Timeline order</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body" id="notaDetailBody">
      <div class="ndt-loading"><i class="fas fa-spinner fa-spin"></i> Memuat detail...</div>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="op-btn op-btn--ghost" data-op-close>Tutup</button>
      <button type="button" class="op-btn op-btn--blue" id="btnDownloadNotaDetail" disabled title="Download gambar">
        <i class="fas fa-download"></i> Gambar
      </button>
    </div>
  </div>
</div>

<?php
$this->helper('PelangganByPhone');
$kurirPhoneTail = PelangganByPhone::key($no_pelanggan ?? '');
?>
<style>
  #offcanvasKurir {
    --bs-offcanvas-width: min(520px, 100vw);
    font-family: 'fontku', 'Segoe UI', sans-serif;
  }
  #offcanvasKurir,
  #offcanvasKurir .offcanvas-header,
  #offcanvasKurir .btn,
  #offcanvasKurir button,
  #offcanvasKurir input,
  #offcanvasKurir select,
  #offcanvasKurir .form-control,
  #offcanvasKurir .selectize-input {
    border-radius: 0 !important;
  }
  #offcanvasKurir .offcanvas-header {
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    border-bottom: 0;
    padding: 1rem 1.15rem;
  }
  #offcanvasKurir .offcanvas-title {
    font-weight: 900;
    letter-spacing: -0.02em;
    margin: 0;
  }
  #offcanvasKurir .offcanvas-body {
    background: linear-gradient(180deg, #eef4ff 0%, #f8fafc 55%, #fff 100%);
  }
  #offcanvasKurir .kurir-field { margin-bottom: 12px; }
  #offcanvasKurir .kurir-label {
    display: block;
    margin: 0 0 4px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
  }
  #offcanvasKurir .kurir-hint {
    margin: 0 0 10px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
  }
  #offcanvasKurir .kurir-sales {
    max-height: 280px;
    overflow: auto;
    border: 1px solid #cbd5e1;
    background: #fff;
  }
  #offcanvasKurir .kurir-sales-empty {
    padding: 12px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
  }
  #offcanvasKurir .kurir-group-head {
    padding: 6px 10px;
    font-size: 0.72rem;
    font-weight: 900;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
  }
  #offcanvasKurir .kurir-item {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
    margin: 0;
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 700;
  }
  #offcanvasKurir .kurir-item:hover { background: #eff6ff; }
  #offcanvasKurir .kurir-item.is-locked { opacity: 0.55; cursor: not-allowed; }
  #offcanvasKurir .kurir-item__meta { font-size: 0.68rem; color: #64748b; font-weight: 700; }
  #offcanvasKurir .kurir-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 14px;
  }
  #offcanvasKurir .kurir-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border: 0;
    font-size: 0.82rem;
    font-weight: 900;
    cursor: pointer;
  }
  #offcanvasKurir .kurir-btn--ghost { background: #e2e8f0; color: #0f172a; }
  #offcanvasKurir .kurir-btn--go {
    background: linear-gradient(180deg, #2563eb, #1d4ed8);
    color: #fff;
  }
  #offcanvasKurir .kurir-btn:disabled { opacity: 0.55; cursor: wait; }
  #offcanvasKurir .kurir-surcas-input-row {
    display: flex;
    gap: 6px;
    align-items: stretch;
  }
  #offcanvasKurir .kurir-surcas-input-row .form-control {
    flex: 1;
    min-width: 0;
  }
  #offcanvasKurir .kurir-surcas-tarif-btn {
    flex-shrink: 0;
    width: 40px;
    padding: 0;
    border: 1px solid #94a3b8;
    border-radius: 0;
    background: #f8fafc;
    color: #2563eb;
    cursor: pointer;
    font-size: 0.95rem;
  }
  #offcanvasKurir .kurir-surcas-tarif-btn:hover:not(:disabled) {
    background: #eff6ff;
    border-color: #2563eb;
  }
  #offcanvasKurir .kurir-surcas-tarif-btn:disabled { opacity: 0.55; cursor: not-allowed; }
  #offcanvasKurir .form-control[readonly] {
    background: #f1f5f9;
    color: #0f172a;
    cursor: default;
  }
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasKurir"
     aria-labelledby="offcanvasKurirLabel"
     data-bs-backdrop="true" data-bs-scroll="true"
     data-id-pelanggan="<?= (int) $id_pelanggan ?>"
     data-phone-tail="<?= htmlspecialchars($kurirPhoneTail, ENT_QUOTES, 'UTF-8') ?>"
     data-sales-url="<?= URL::BASE_URL ?>Delivery/sales_options/"
     data-tarif-url="<?= URL::BASE_URL ?>Delivery/tarif_surcas"
     data-submit-url="<?= URL::BASE_URL ?>Delivery/kurir_dari_operasi"
     style="z-index: 1100;">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasKurirLabel">
      <i class="fas fa-motorcycle me-2"></i>Kurir Delivery
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <p class="kurir-hint" id="kurirHint">
      Operasi selalu menulis surcas ke nota (wajib pilih item).
      Jemput / Jemput &amp; Antar wajib penyelesai jemput.
      Antar tanpa penyelesai → request di board; Delivery tinggal isi penyelesai.
      Item terikat request dengan tarif tidak bisa diubah nominal surcas-nya.
    </p>
    <div class="kurir-field">
      <label class="kurir-label" for="kurirJenis">Jenis</label>
      <select id="kurirJenis" class="form-control">
        <option value="">Pilih…</option>
        <option value="jemput">Jemput</option>
        <option value="antar">Antar</option>
        <option value="jemput_antar">Jemput &amp; Antar</option>
      </select>
    </div>
    <div class="kurir-field" id="kurirSurcasJemputWrap" hidden>
      <label class="kurir-label" for="kurirSurcasJemput">Surcas Penjemputan</label>
      <div class="kurir-surcas-input-row">
        <input type="number" id="kurirSurcasJemput" class="form-control" min="0" step="1000" placeholder="0 = gratis" inputmode="numeric">
        <button type="button" class="kurir-surcas-tarif-btn" data-surcas-target="kurirSurcasJemput" title="Isi dari rumus ongkir" aria-label="Isi surcas dari rumus ongkir">
          <i class="fas fa-route" aria-hidden="true"></i>
        </button>
      </div>
      <small id="kurirSurcasJemputHint" class="kurir-hint" style="margin:4px 0 0;display:none"></small>
    </div>
    <div class="kurir-field" id="kurirSurcasAntarWrap" hidden>
      <label class="kurir-label" for="kurirSurcasAntar">Surcas Pengantaran</label>
      <div class="kurir-surcas-input-row">
        <input type="number" id="kurirSurcasAntar" class="form-control" min="0" step="1000" placeholder="0 = gratis" inputmode="numeric">
        <button type="button" class="kurir-surcas-tarif-btn" data-surcas-target="kurirSurcasAntar" title="Isi dari rumus ongkir" aria-label="Isi surcas dari rumus ongkir">
          <i class="fas fa-route" aria-hidden="true"></i>
        </button>
      </div>
      <small id="kurirSurcasAntarHint" class="kurir-hint" style="margin:4px 0 0;display:none">
        Wajib. Langsung ke nota.
      </small>
    </div>
    <div class="kurir-field" id="kurirSalesWrap">
      <label class="kurir-label" id="kurirSalesLabel">Item (wajib — untuk surcas ke nota)</label>
      <div class="kurir-sales" id="kurirSales">
        <div class="kurir-sales-empty">Pilih jenis terlebih dahulu</div>
      </div>
    </div>
    <div class="kurir-field" id="kurirKaryawanWrap">
      <label class="kurir-label" for="kurirKaryawan" id="kurirKaryawanLabel">Penyelesai (opsional)</label>
      <select id="kurirKaryawan" class="tize" style="width:100%">
        <option value="">— Request saja —</option>
        <optgroup label="<?= htmlspecialchars(($this->dCabang['nama'] ?? '') . ' [' . ($this->dCabang['kode_cabang'] ?? '') . ']', ENT_QUOTES, 'UTF-8') ?>">
          <?php foreach ($this->user as $a) { ?>
            <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . strtoupper($a['nama_user']) ?></option>
          <?php } ?>
        </optgroup>
        <?php if (!empty($this->userCabang)) { ?>
          <optgroup label="---- Cabang Lain ----">
            <?php foreach ($this->userCabang as $a) { ?>
              <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . strtoupper($a['nama_user']) ?></option>
            <?php } ?>
          </optgroup>
        <?php } ?>
      </select>
    </div>
    <div class="kurir-actions">
      <button type="button" class="kurir-btn kurir-btn--ghost" data-bs-dismiss="offcanvas">Batal</button>
      <button type="button" class="kurir-btn kurir-btn--go" id="kurirSubmit">
        <i class="fas fa-paper-plane"></i> <span id="kurirSubmitLabel">Buat Request</span>
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  var root = document.getElementById('offcanvasKurir');
  if (!root) return;

  var salesUrl = String(root.getAttribute('data-sales-url') || '');
  var tarifUrl = String(root.getAttribute('data-tarif-url') || '');
  var submitUrl = String(root.getAttribute('data-submit-url') || '');
  var kurirSelectize = null;
  var tarifLoading = false;

  function currentPelanggan() {
    var fromRoot = parseInt(root.getAttribute('data-id-pelanggan') || '0', 10) || 0;
    if (fromRoot > 0) return fromRoot;
    return parseInt((window.ViewLoadConfig && window.ViewLoadConfig.idPelanggan) || '0', 10) || 0;
  }
  function currentPhoneTail() {
    return String(root.getAttribute('data-phone-tail') || '');
  }
  function currentPenyelesai() {
    var sel = root.querySelector('#kurirKaryawan');
    if (sel && sel.selectize) {
      return parseInt(sel.selectize.getValue() || '0', 10) || 0;
    }
    if (kurirSelectize) {
      return parseInt(kurirSelectize.getValue() || '0', 10) || 0;
    }
    if (sel) {
      return parseInt(sel.value || '0', 10) || 0;
    }
    return 0;
  }

  function toast(msg, type) {
    type = type || 'info';
    if (window.MdlToast) {
      if (type === 'error' || type === 'danger') return MdlToast.error(msg);
      if (type === 'warn' || type === 'warning') return MdlToast.warn(msg);
      if (type === 'ok' || type === 'success') return MdlToast.ok(msg);
      return MdlToast.info(msg);
    }
    // fallback sangat jarang — tetap non-blocking
    console.log('[toast:' + type + ']', msg);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fillSurcasFromTarif(inputId, btn) {
    if (tarifLoading) return;
    var input = document.getElementById(inputId);
    if (!input || input.readOnly) return;
    var idPel = parseInt(root.getAttribute('data-id-pelanggan') || '0', 10) || 0;
    if (!tarifUrl || idPel <= 0) {
      toast('Pelanggan tidak valid', 'warn');
      return;
    }
    tarifLoading = true;
    if (btn) btn.disabled = true;
    fetch(tarifUrl + '?id_pelanggan=' + encodeURIComponent(String(idPel)), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Lokasi pelanggan belum ada', 'warn');
          return;
        }
        var tarif = parseInt((res.data && res.data.tarif) || 0, 10) || 0;
        input.value = String(tarif);
        var km = res.data && res.data.km;
        var lok = res.data && res.data.lokasi_nama;
        var msg = 'Surcas diisi Rp' + tarif.toLocaleString('id-ID');
        if (km != null && km !== '') msg += ' · ' + km + ' km';
        if (lok) msg += ' · ' + lok;
        toast(msg, 'ok');
      })
      .catch(function () { toast('Gagal hitung tarif', 'error'); })
      .finally(function () {
        tarifLoading = false;
        syncSurcasLock();
      });
  }

  function syncJenisUi() {
    var jenis = String(document.getElementById('kurirJenis').value || '').toLowerCase();
    var isJemput = jenis === 'jemput';
    var isAntar = jenis === 'antar';
    var isCombo = jenis === 'jemput_antar';

    document.getElementById('kurirSurcasJemputWrap').hidden = !(isJemput || isCombo);
    document.getElementById('kurirSurcasAntarWrap').hidden = !(isAntar || isCombo);
    var antarHint = document.getElementById('kurirSurcasAntarHint');
    if (antarHint) {
      antarHint.style.display = (isAntar || isCombo) ? 'block' : 'none';
      antarHint.textContent = isCombo
        ? 'Wajib. Langsung ke nota (ref item jemput). Request Antar di board.'
        : 'Wajib. Langsung ke nota.';
    }

    var salesWrap = document.getElementById('kurirSalesWrap');
    var karyWrap = document.getElementById('kurirKaryawanWrap');
    if (salesWrap) salesWrap.hidden = false;
    if (karyWrap) karyWrap.hidden = false;

    var salesLabel = document.getElementById('kurirSalesLabel');
    var karyLabel = document.getElementById('kurirKaryawanLabel');
    if (salesLabel) {
      salesLabel.textContent = isAntar
        ? 'Item Antar (wajib — surcas ke nota)'
        : 'Item Jemput (wajib — surcas ke nota)';
    }
    if (karyLabel) {
      karyLabel.textContent = (isJemput || isCombo)
        ? 'Penyelesai Jemput (wajib)'
        : 'Penyelesai Antar (opsional)';
    }

    loadSales(isCombo ? 'jemput' : (isJemput || isAntar ? jenis : ''));
    syncSubmitLabel();
    syncSurcasLock();
  }

  function syncSubmitLabel() {
    var jenis = String(document.getElementById('kurirJenis').value || '').toLowerCase();
    var lab = document.getElementById('kurirSubmitLabel');
    if (!lab) return;
    if (jenis === 'jemput_antar') {
      lab.textContent = 'Selesai Jemput + Request Antar';
      return;
    }
    if (jenis === 'jemput') {
      lab.textContent = 'Selesai Jemput';
      return;
    }
    var checked = root.querySelectorAll('input[name="kurir_ids"]:checked');
    var allDelivered = checked.length > 0;
    checked.forEach(function (cb) {
      if (cb.getAttribute('data-sudah-delivered') !== '1') allDelivered = false;
    });
    if (allDelivered) {
      lab.textContent = 'Tambah Surcas Antar';
      return;
    }
    var hasKaryawan = currentPenyelesai() > 0;
    lab.textContent = hasKaryawan ? 'Selesai Sekarang' : 'Buat Request';
  }

  function lockedTarifFromChecked(attr, terikatAttr) {
    var locked = null;
    var found = false;
    root.querySelectorAll('input[name="kurir_ids"]:checked').forEach(function (cb) {
      if (cb.getAttribute(terikatAttr) !== '1') return;
      var raw = cb.getAttribute(attr);
      if (raw === null || raw === '') return;
      var n = parseInt(raw, 10);
      if (isNaN(n) || n < 0) return;
      if (!found) {
        locked = n;
        found = true;
      }
    });
    return found ? locked : null;
  }

  function setSurcasLocked(inputId, lockedVal, hintId, lockedText, unlockedText) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var btn = root.querySelector('.kurir-surcas-tarif-btn[data-surcas-target="' + inputId + '"]');
    var hint = hintId ? document.getElementById(hintId) : null;
    if (lockedVal === null) {
      input.readOnly = false;
      input.removeAttribute('data-tarif-locked');
      if (btn) btn.disabled = false;
      if (hint) {
        if (unlockedText) {
          hint.style.display = 'block';
          hint.textContent = unlockedText;
        } else {
          hint.style.display = 'none';
        }
      }
      return;
    }
    input.value = String(lockedVal);
    input.readOnly = true;
    input.setAttribute('data-tarif-locked', '1');
    if (btn) btn.disabled = true;
    if (hint) {
      hint.style.display = 'block';
      hint.textContent = lockedText || ('Terkunci dari tarif request Rp' + Number(lockedVal).toLocaleString('id-ID'));
    }
  }

  function syncSurcasLock() {
    var jenis = String((document.getElementById('kurirJenis') || {}).value || '').toLowerCase();
    var isJemput = jenis === 'jemput';
    var isAntar = jenis === 'antar';
    var isCombo = jenis === 'jemput_antar';
    var lockJemput = (isJemput || isCombo)
      ? lockedTarifFromChecked('data-tarif-surcas', 'data-terikat')
      : null;
    var lockAntar = isAntar
      ? lockedTarifFromChecked('data-tarif-surcas', 'data-terikat')
      : (isCombo ? lockedTarifFromChecked('data-tarif-surcas-antar', 'data-terikat-antar') : null);

    setSurcasLocked(
      'kurirSurcasJemput',
      lockJemput,
      'kurirSurcasJemputHint',
      lockJemput === null ? '' : ('Terkunci dari tarif request Rp' + Number(lockJemput).toLocaleString('id-ID')),
      ''
    );
    var antarUnlocked = isCombo
      ? 'Wajib. Langsung ke nota (ref item jemput). Request Antar di board.'
      : (isAntar ? 'Wajib. Langsung ke nota.' : '');
    setSurcasLocked(
      'kurirSurcasAntar',
      lockAntar,
      'kurirSurcasAntarHint',
      lockAntar === null ? '' : ('Terkunci dari tarif request Rp' + Number(lockAntar).toLocaleString('id-ID')),
      (isAntar || isCombo) ? antarUnlocked : ''
    );
  }

  function renderSales(orders) {
    var box = root.querySelector('#kurirSales') || document.getElementById('kurirSales');
    if (!box) return;
    if (!orders || !orders.length) {
      box.innerHTML = '<div class="kurir-sales-empty">Tidak ada item eligible</div>';
      syncSurcasLock();
      return;
    }
    box.innerHTML = orders.map(function (ord) {
      var items = (ord.items || []).map(function (it) {
        var belum = !!it.belum_selesai;
        var delivered = !!it.sudah_delivered;
        var terikat = !!it.terikat;
        var terikatAntar = !!it.terikat_antar;
        var tarif = (it.tarif_surcas === 0 || it.tarif_surcas) ? String(it.tarif_surcas) : '';
        var tarifAntar = (it.tarif_surcas_antar === 0 || it.tarif_surcas_antar) ? String(it.tarif_surcas_antar) : '';
        var meta = '#' + it.id + (it.member ? ' · member' : '')
          + (delivered ? ' · sudah delivered · isi surcas saja' : '')
          + (terikat && !delivered ? ' · terikat request' : '')
          + (tarif !== '' && terikat ? ' · tarif Rp' + Number(tarif).toLocaleString('id-ID') : '')
          + (belum && !delivered ? ' · belum selesai laundry' : '');
        return '<label class="kurir-item">' +
          '<input type="checkbox" name="kurir_ids" value="' + esc(it.id) + '" checked' +
          (belum && !delivered ? ' data-belum-selesai="1"' : '') +
          (delivered ? ' data-sudah-delivered="1"' : '') +
          (terikat ? ' data-terikat="1"' : '') +
          (terikatAntar ? ' data-terikat-antar="1"' : '') +
          (tarif !== '' ? ' data-tarif-surcas="' + esc(tarif) + '"' : '') +
          (tarifAntar !== '' ? ' data-tarif-surcas-antar="' + esc(tarifAntar) + '"' : '') + '>' +
          '<span><div>' + esc(it.kategori || '') + ' · ' + esc(it.durasi || '') + ' · ' + esc(it.qty_show || '') + '</div>' +
          '<div class="kurir-item__meta">' + esc(meta) + '</div></span></label>';
      }).join('');
      var scHint = '';
      if (ord.surcas_penjemputan != null) scHint += ' · jemput Rp' + Number(ord.surcas_penjemputan).toLocaleString('id-ID');
      if (ord.surcas_pengantaran != null) scHint += ' · antar Rp' + Number(ord.surcas_pengantaran).toLocaleString('id-ID');
      return '<div class="kurir-group" data-no-ref="' + esc(ord.no_ref) + '">' +
        '<div class="kurir-group-head">#' + esc(ord.no_ref) + esc(scHint) + '</div>' + items + '</div>';
    }).join('');
    box.querySelectorAll('input[name="kurir_ids"]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        syncSubmitLabel();
        syncSurcasLock();
      });
    });
    syncSubmitLabel();
    syncSurcasLock();
  }

  function loadSales(jenis) {
    var box = root.querySelector('#kurirSales') || document.getElementById('kurirSales');
    if (!box) return;
    if (!jenis || !salesUrl) {
      box.innerHTML = '<div class="kurir-sales-empty">Pilih jenis terlebih dahulu</div>';
      syncSurcasLock();
      return;
    }
    var idPelanggan = currentPelanggan();
    var phoneTail = currentPhoneTail();
    if (!phoneTail && !idPelanggan) {
      box.innerHTML = '<div class="kurir-sales-empty">Pelanggan tidak valid</div>';
      return;
    }
    box.innerHTML = '<div class="kurir-sales-empty"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>';
    var q = '?jenis=' + encodeURIComponent(jenis) + '&operasi=1';
    if (idPelanggan > 0) q += '&id_pelanggan=' + encodeURIComponent(String(idPelanggan));
    var pathTail = phoneTail || ('p' + idPelanggan);
    fetch(salesUrl + encodeURIComponent(pathTail) + q, {
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          box.innerHTML = '<div class="kurir-sales-empty">' + esc((res && res.message) || 'Gagal memuat') + '</div>';
          return;
        }
        renderSales((res.data && res.data.orders) || []);
      })
      .catch(function () {
        box.innerHTML = '<div class="kurir-sales-empty">Gagal memuat item</div>';
      });
  }

  function initSelectize() {
    if (typeof jQuery === 'undefined' || !jQuery.fn.selectize) return;
    var el = root.querySelector('#kurirKaryawan');
    if (!el) return;
    var $el = jQuery(el);
    if (el.selectize) kurirSelectize = el.selectize;
    else kurirSelectize = $el.selectize({ allowEmptyOption: true })[0].selectize;
    if (kurirSelectize && typeof kurirSelectize.off === 'function') {
      kurirSelectize.off('change');
    }
    kurirSelectize.on('change', syncSubmitLabel);
  }

  root.addEventListener('shown.bs.offcanvas', function () {
    initSelectize();
    syncSubmitLabel();
  });

  var jenisEl = root.querySelector('#kurirJenis');
  if (jenisEl) jenisEl.addEventListener('change', syncJenisUi);
  var karyEl = root.querySelector('#kurirKaryawan');
  if (karyEl) karyEl.addEventListener('change', syncSubmitLabel);

  root.querySelectorAll('.kurir-surcas-tarif-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = btn.getAttribute('data-surcas-target') || '';
      if (target) fillSurcasFromTarif(target, btn);
    });
  });

  document.getElementById('kurirSubmit').addEventListener('click', function () {
    var btn = document.getElementById('kurirSubmit');
    var jenis = String(document.getElementById('kurirJenis').value || '').toLowerCase();
    if (!jenis) {
      toast('Pilih jenis jemput/antar', 'warn');
      return;
    }
    var idKaryawan = currentPenyelesai();

    var ids = [];
    root.querySelectorAll('input[name="kurir_ids"]:checked').forEach(function (cb) {
      var v = parseInt(cb.value, 10);
      if (v > 0) ids.push(v);
    });

    if (jenis === 'jemput' || jenis === 'jemput_antar') {
      if (idKaryawan <= 0) {
        toast('Wajib pilih penyelesai jemput', 'warn');
        return;
      }
      if (!ids.length) {
        toast('Wajib pilih minimal satu item jemput', 'warn');
        return;
      }
      var jRaw = String(document.getElementById('kurirSurcasJemput').value || '').trim();
      if (jRaw === '' || isNaN(parseInt(jRaw, 10)) || parseInt(jRaw, 10) < 0) {
        toast('Isi Surcas Penjemputan (isi 0 untuk gratis)', 'warn');
        return;
      }
    }

    if (jenis === 'antar') {
      if (!ids.length) {
        toast('Wajib pilih minimal satu item antar (untuk surcas ke nota)', 'warn');
        return;
      }
      var aAntar = String(document.getElementById('kurirSurcasAntar').value || '').trim();
      if (aAntar === '' || isNaN(parseInt(aAntar, 10)) || parseInt(aAntar, 10) < 0) {
        toast('Isi Surcas Pengantaran (isi 0 untuk gratis)', 'warn');
        return;
      }
      if (idKaryawan > 0) {
        var adaBelum = false;
        root.querySelectorAll('input[name="kurir_ids"]:checked').forEach(function (cb) {
          if (cb.getAttribute('data-belum-selesai') === '1') adaBelum = true;
        });
        if (adaBelum) {
          toast('Item belum selesai laundry. Kosongkan penyelesai untuk buat request, atau pilih item yang sudah selesai.', 'warn');
          return;
        }
      }
    }

    if (jenis === 'jemput_antar') {
      var aRaw = String(document.getElementById('kurirSurcasAntar').value || '').trim();
      if (aRaw === '' || isNaN(parseInt(aRaw, 10)) || parseInt(aRaw, 10) < 0) {
        toast('Isi Surcas Pengantaran (isi 0 untuk gratis)', 'warn');
        return;
      }
    }

    var fd = new FormData();
    fd.append('id_pelanggan', String(currentPelanggan()));
    fd.append('jenis', jenis);
    fd.append('id_karyawan', String(idKaryawan));
    ids.forEach(function (id) { fd.append('ids[]', String(id)); });
    if (jenis === 'jemput' || jenis === 'jemput_antar') {
      var j = document.getElementById('kurirSurcasJemput').value;
      if (j !== '') fd.append('jumlah_surcas_jemput', j);
    }
    if (jenis === 'antar' || jenis === 'jemput_antar') {
      var a = document.getElementById('kurirSurcasAntar').value;
      if (a !== '') fd.append('jumlah_surcas_antar', a);
    }

    btn.disabled = true;
    fetch(submitUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal', 'error');
          return;
        }
        toast(res.message || 'Berhasil', 'ok');
        var oc = bootstrap.Offcanvas.getInstance(root);
        if (oc) oc.hide();
        if (typeof cekData === 'function') cekData();
        else if (window.cekData) window.cekData();
      })
      .catch(function () { toast('Gagal menyimpan', 'error'); })
      .finally(function () { btn.disabled = false; });
  });
})();
</script>
