<?php if (!empty($GLOBALS['_op_dlv_modals_loaded'])) {
  return;
}
$GLOBALS['_op_dlv_modals_loaded'] = true;
?>
<style>
  #dlvSelesaiModal .dlv-field-label,
  #dlvPendingModal .dlv-field-label,
  #dlvConfirmModal .dlv-field-label,
  #dlvLokasiPickModal .dlv-field-label {
    display: block;
    margin: 0 0 4px;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #64748b;
  }
  #dlvSelesaiModal .dlv-input,
  #dlvConfirmModal .dlv-input,
  #dlvSelesaiModal select.dlv-input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    background: #fff;
    font-family: inherit;
    font-size: 0.86rem;
    font-weight: 700;
    color: #0f172a;
    border-radius: 0;
  }
  #dlvSelesaiModal .dlv-sales-box {
    border: 1px solid #cbd5e1;
    background: #fff;
    max-height: min(42vh, 360px);
    overflow: auto;
    padding: 6px;
  }
  #dlvSelesaiModal .dlv-sales-empty {
    padding: 14px 10px;
    text-align: center;
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
  }
  #dlvSelesaiModal .dlv-sales-group__head {
    padding: 6px 8px;
    background: #f1f5f9;
    font-size: 0.72rem;
    font-weight: 900;
    color: #334155;
    margin-bottom: 4px;
  }
  #dlvSelesaiModal .dlv-sales-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    margin-bottom: 4px;
    cursor: pointer;
  }
  #dlvSelesaiModal .dlv-sales-item.is-locked { opacity: 0.55; cursor: not-allowed; }
  #dlvSelesaiModal .dlv-sales-item__text { font-size: 0.78rem; font-weight: 800; }
  #dlvSelesaiModal .dlv-sales-item__meta { font-size: 0.68rem; font-weight: 700; color: #64748b; margin-top: 2px; }
  #dlvSelesaiModal .dlv-surcas-input-row { display: flex; gap: 6px; align-items: stretch; }
  #dlvSelesaiModal .dlv-surcas-input-row .dlv-input { flex: 1; }
  #dlvSelesaiModal .dlv-surcas-tarif-btn,
  #dlvSelesaiModal .dlv-btn,
  #dlvPendingModal .dlv-btn,
  #dlvConfirmModal .dlv-btn,
  #dlvLokasiPickModal .dlv-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border: 1px solid transparent;
    font-size: 0.78rem;
    font-weight: 900;
    cursor: pointer;
    font-family: inherit;
    border-radius: 0;
  }
  #dlvSelesaiModal .dlv-btn--ghost,
  #dlvPendingModal .dlv-btn--ghost,
  #dlvConfirmModal .dlv-btn--ghost,
  #dlvLokasiPickModal .dlv-btn--ghost { background: #e2e8f0; color: #0f172a; }
  #dlvSelesaiModal .dlv-btn--submit { background: linear-gradient(180deg, #16a34a, #15803d); color: #fff; }
  #dlvSelesaiModal .dlv-btn--batal,
  #dlvConfirmModal .dlv-btn--batal { background: linear-gradient(180deg, #ef4444, #b91c1c); color: #fff; }
  #dlvPendingModal .dlv-btn--pending { background: linear-gradient(180deg, #f59e0b, #d97706); color: #fff; }
  #dlvSelesaiModal .dlv-jenis-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border: 1px solid #cbd5e1;
    font-size: 0.78rem;
    font-weight: 900;
  }
  #dlvSelesaiModal .dlv-jenis-pill--antar { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
  #dlvSelesaiModal .dlv-jenis-pill--jemput { background: #fffbeb; border-color: #fcd34d; color: #b45309; }
  #dlvSelesaiModal .dlv-hint { font-size: 0.72rem; font-weight: 700; color: #64748b; }
  #dlvLokasiPickModal .dlv-lokasi-pick {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  #dlvLokasiPickModal .dlv-lokasi-pick button {
    width: 100%;
    text-align: left;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    background: #fff;
    cursor: pointer;
    font-family: inherit;
  }
</style>

<div class="op-modal op-modal--confirm" id="dlvPendingModal" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="dlvPendingTitle">
    <div class="op-modal__head">
      <div>
        <h3 id="dlvPendingTitle">Pending Antar</h3>
        <small id="dlvPendingSub">Request #—</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <input type="hidden" id="dlvPendingRequestId" value="">
      <p class="op-modal__confirm-msg" id="dlvPendingMsg">
        Request Antar akan keluar dari board. Aktif lagi saat pelanggan chat minta antar.
      </p>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Tidak</button>
      <button type="button" class="dlv-btn dlv-btn--pending" id="dlvPendingYes">
        <i class="fas fa-pause"></i> Ya, Pending
      </button>
    </div>
  </div>
</div>

<div class="op-modal" id="dlvSelesaiModal" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--selesai" role="dialog" aria-modal="true" aria-labelledby="dlvSelesaiTitle">
    <div class="op-modal__head op-modal__head--blue">
      <div>
        <h3 id="dlvSelesaiTitle">Selesai Delivery Request</h3>
        <small id="dlvSelesaiSub">Pilih karyawan dan item</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <form id="dlvSelesaiForm">
      <div class="op-modal__body">
        <input type="hidden" id="dlvSelesaiMode" value="request">
        <input type="hidden" id="dlvSelesaiRequestId" name="id_request" value="">
        <input type="hidden" id="dlvSelesaiLayanan" value="sameday">
        <input type="hidden" id="dlvSelesaiPhone" name="phone_tail" value="">
        <input type="hidden" id="dlvSelesaiPelanggan" value="">
        <input type="hidden" id="dlvSelesaiPrefill" value="">
        <input type="hidden" id="dlvSelesaiJenisLocked" value="">
        <input type="hidden" id="dlvSelesaiSurcasBound" value="0">
        <div id="dlvSelesaiJenisFreeWrap" hidden>
          <label class="dlv-field-label" for="dlvSelesaiJenis">Jenis</label>
          <select id="dlvSelesaiJenis" name="jenis" class="dlv-input">
            <option value="">— Pilih —</option>
            <option value="jemput">Jemput</option>
            <option value="antar">Antar</option>
          </select>
        </div>
        <div id="dlvSelesaiJenisLockedWrap">
          <span class="dlv-field-label">Jenis</span>
          <div class="dlv-jenis-locked">
            <span id="dlvSelesaiJenisLockedPill" class="dlv-jenis-pill">—</span>
            <span class="dlv-hint mb-0" style="margin-top:4px;display:block">
              <i class="fas fa-lock me-1"></i>Dari request customer — tidak bisa diubah
            </span>
          </div>
        </div>
        <label class="dlv-field-label mt-2" for="dlvSelesaiKaryawan" id="dlvSelesaiKaryawanLabel">Petugas</label>
        <select id="dlvSelesaiKaryawan" name="id_karyawan" class="form-control tize" style="width:100%" required>
          <option value="" selected disabled></option>
          <optgroup label="<?= htmlspecialchars(($this->dCabang['nama'] ?? 'Cabang') . ' [' . ($this->dCabang['kode_cabang'] ?? '') . ']', ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach (($this->user ?? []) as $a) { ?>
              <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } ?>
          </optgroup>
          <?php if (!empty($this->userCabang)) { ?>
            <optgroup label="----- Cabang Lain -----">
              <?php foreach ($this->userCabang as $a) { ?>
                <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
              <?php } ?>
            </optgroup>
          <?php } ?>
        </select>
        <label class="dlv-field-label mt-2">Item penjualan</label>
        <div class="dlv-sales-box" id="dlvSelesaiSales">
          <div class="dlv-sales-empty">Memuat…</div>
        </div>
        <div id="dlvSurcasJemputRow" hidden>
          <label class="dlv-field-label mt-2" for="dlvSurcasJemputJumlah">Surcas Penjemputan</label>
          <div class="dlv-surcas-input-row">
            <input type="number" id="dlvSurcasJemputJumlah" name="jumlah_surcas_jemput" class="dlv-input" min="0" step="1000" placeholder="0 = gratis" inputmode="numeric">
            <button type="button" class="dlv-surcas-tarif-btn" data-surcas-target="dlvSurcasJemputJumlah" title="Isi dari rumus ongkir" aria-label="Isi surcas dari rumus ongkir">
              <i class="fas fa-route" aria-hidden="true"></i>
            </button>
          </div>
          <p class="dlv-hint mt-1 mb-0" id="dlvSurcasJemputHint">
            <i class="fas fa-info-circle me-1"></i>Wajib diisi. Isi nominal, atau 0 untuk gratis.
          </p>
        </div>
        <div id="dlvSurcasAntarRow" hidden>
          <label class="dlv-field-label mt-2" for="dlvSurcasAntarJumlah">Surcas Pengantaran</label>
          <div class="dlv-surcas-input-row">
            <input type="number" id="dlvSurcasAntarJumlah" name="jumlah_surcas_antar" class="dlv-input" min="0" step="1000" placeholder="0 = gratis" inputmode="numeric">
            <button type="button" class="dlv-surcas-tarif-btn" data-surcas-target="dlvSurcasAntarJumlah" title="Isi dari rumus ongkir" aria-label="Isi surcas dari rumus ongkir">
              <i class="fas fa-route" aria-hidden="true"></i>
            </button>
          </div>
          <p class="dlv-hint mt-1 mb-0" id="dlvSurcasAntarHint">
            <i class="fas fa-info-circle me-1"></i>Wajib diisi. Isi nominal, atau 0 untuk gratis.
          </p>
        </div>
      </div>
      <div class="op-modal__foot op-modal__foot--selesai">
        <button type="button" class="dlv-btn dlv-btn--batal" id="dlvSelesaiBatal">
          <i class="fas fa-ban"></i> Batalkan Delivery
        </button>
        <div class="op-modal__foot-right">
          <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Batal</button>
          <button type="submit" class="dlv-btn dlv-btn--submit" id="dlvSelesaiSubmit">
            <i class="fas fa-check"></i> Selesai
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="op-modal op-modal--confirm" id="dlvConfirmModal" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="dlvConfirmTitle">
    <div class="op-modal__head op-modal__head--red">
      <div>
        <h3 id="dlvConfirmTitle">Batalkan Delivery</h3>
        <small>Wajib karyawan dan catatan</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <p class="op-modal__confirm-msg" id="dlvConfirmMsg">
        Request customer akan dibatalkan tanpa menyimpan riwayat jemput/antar.
      </p>
      <label class="dlv-field-label mt-2" for="dlvBatalKaryawan">Karyawan yang membatalkan</label>
      <select id="dlvBatalKaryawan" class="form-control tize" style="width:100%" required>
        <option value="" selected disabled></option>
        <optgroup label="<?= htmlspecialchars(($this->dCabang['nama'] ?? 'Cabang') . ' [' . ($this->dCabang['kode_cabang'] ?? '') . ']', ENT_QUOTES, 'UTF-8') ?>">
          <?php foreach (($this->user ?? []) as $a) { ?>
            <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
          <?php } ?>
        </optgroup>
        <?php if (!empty($this->userCabang)) { ?>
          <optgroup label="----- Cabang Lain -----">
            <?php foreach ($this->userCabang as $a) { ?>
              <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . '-' . htmlspecialchars(strtoupper((string) ($a['nama_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } ?>
          </optgroup>
        <?php } ?>
      </select>
      <label class="dlv-field-label mt-2" for="dlvBatalCatatan">Catatan</label>
      <textarea id="dlvBatalCatatan" class="dlv-input" rows="3" placeholder="Alasan pembatalan" required></textarea>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Tidak</button>
      <button type="button" class="dlv-btn dlv-btn--batal" id="dlvConfirmYes">
        <i class="fas fa-ban"></i> Ya, Batalkan
      </button>
    </div>
  </div>
</div>

<div class="op-modal" id="dlvLokasiPickModal" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="dlvLokasiPickTitle">
    <div class="op-modal__head">
      <div>
        <h3 id="dlvLokasiPickTitle">Pilih Lokasi</h3>
        <small id="dlvLokasiPickSub">Request #—</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="op-modal__body">
      <input type="hidden" id="dlvLokasiPickRequestId" value="">
      <ul class="dlv-lokasi-pick" id="dlvLokasiPickList"></ul>
    </div>
    <div class="op-modal__foot">
      <button type="button" class="dlv-btn dlv-btn--ghost" data-op-close>Batal</button>
    </div>
  </div>
</div>
