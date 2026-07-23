<?php if (!isset($this)) { /* ensure scope remains same as parent view */
} ?>
<form class="ajax" data-operasi="" action="<?= URL::BASE_URL; ?>Antrian/ambil" method="POST">
  <div class="modal" id="exampleModal4">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Ambil Laundry</b></h5>
          <button data-bs-dismiss="modal" class="btn-close"></button>
        </div>
        <div class="modal-body">
          <div class="card-body">
            <div class="form-group">
              <label>Pengembali</label>
              <select name="f1" class="ambil form-control form-control-sm tize userChange" style="width: 100%;" required>
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
        </div>
        <div class="modal-footer">
          <button type="button" data-bs-dismiss="modal" class="btn btn-dark">Batal</button>
          <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </div>
    </div>
  </div>
</form>

<form data-operasi="" class="operasi ajax" action="<?= URL::BASE_URL; ?>Antrian/operasi" method="POST">
  <div class="modal" id="exampleModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Selesai <b class="operasi"></b>!</h5>
          <button data-bs-dismiss="modal" class="btn-close"></button>
        </div>
        <div class="modal-body">
          <div class="card-body">
            <div class="form-group">
              <div class="row">
                <div class="col">
                  <label>Karyawan</label>
                  <select name="f1" class="operasi form-control tize form-control-sm userChange" style="width: 100%;" required>
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
                <div class="col">
                  <label>Letak / Rak</label>
                  <input id='letakRAK' type="text" maxlength="2" name="rak" style="text-transform: uppercase" class="form-control">
                </div>
              </div>
              <input type="hidden" class="idItem" name="f2" value="" required>
              <input type="hidden" class="valueItem" name="f3" value="" required>
              <input type="hidden" name="inTotalNotif" value="" required>
            </div>
            <div class="form-group letakRAK">
              <div class="row">
                <div class="col">
                  <label>Pack</label>
                  <input type="number" min="0" value="1" name="pack" style="text-transform: uppercase" class="form-control" required>
                </div>
                <div class="col">
                  <label>Hanger</label>
                  <input type="number" min="0" value="0" name="hanger" style="text-transform: uppercase" class="form-control" required>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" data-bs-dismiss="modal" class="btn btn-dark">Batal</button>
          <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </div>
    </div>
  </div>
</form>

<form class="operasi ajax" action="<?= URL::BASE_URL; ?>Operasi/ganti_operasi" method="POST">
  <div class="modal" id="modalGanti">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header bg-danger">
          <h5 class="modal-title">Ubah Penyelesai</h5>
        </div>
        <div class="modal-body">
          <div class="card-body">
            <div class="form-group">
              <label>Ubah dari <span class="text-danger" id="awalOP"></span> menjadi:</label>
              <select name="f1" class="operasi form-control tize form-control-sm userChange" style="width: 100%;" required>
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
              <input type="hidden" id="id_ganti" name="id" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" data-bs-dismiss="modal" class="btn btn-dark">Batal</button>
          <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </div>
    </div>
  </div>
</form>

<div class="modal" id="modalUbahMember" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ubah ke Member</h5>
        <button type="button" data-bs-dismiss="modal" class="btn-close"></button>
      </div>
      <div class="modal-body">
        <div id="ubahMemberLoading" class="text-center py-3">
          <i class="fas fa-spinner fa-spin"></i> Memuat...
        </div>
        <div id="ubahMemberContent" class="d-none">
          <p class="small text-muted mb-2" id="ubahMemberInfo"></p>
          <div class="small border rounded p-2 bg-light">
            <div>Paket: <strong id="ubahMemberPaket">-</strong></div>
            <div>Qty order: <strong id="ubahMemberQty">-</strong></div>
            <div>Saldo member: <strong id="ubahMemberSaldo">-</strong></div>
            <div>Total order: <strong id="ubahMemberRefTotal">-</strong></div>
            <div id="ubahMemberBayarInfo" class="text-warning d-none mt-1">Pembayaran Cek/Berhasil: <strong id="ubahMemberDibayar">-</strong></div>
          </div>
          <div id="ubahMemberAlert" class="alert alert-danger d-none small mt-2 mb-0 py-2"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" data-bs-dismiss="modal" class="btn btn-dark btn-sm">Batal</button>
        <button type="button" id="btnSimpanMember" class="btn btn-success btn-sm" disabled>Simpan</button>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="modalUbahDurasi" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ubah Durasi</h5>
        <button type="button" data-bs-dismiss="modal" class="btn-close"></button>
      </div>
      <div class="modal-body">
        <div id="ubahDurasiLoading" class="text-center py-3">
          <i class="fas fa-spinner fa-spin"></i> Memuat...
        </div>
        <div id="ubahDurasiContent" class="d-none">
          <p class="small text-muted mb-2" id="ubahDurasiInfo"></p>
          <div class="mb-2"><strong>Item:</strong> <span id="ubahDurasiItem"></span></div>
          <div class="form-group mb-2">
            <label class="small mb-1">Pilih Durasi</label>
            <select id="ubahDurasiSelect" class="form-control form-control-sm"></select>
          </div>
          <div class="small border rounded p-2 bg-light">
            <div>Harga item: <strong id="ubahDurasiItemHarga">-</strong></div>
            <div>Total order: <strong id="ubahDurasiRefTotal">-</strong></div>
            <div id="ubahDurasiBayarInfo" class="text-warning d-none mt-1">Pembayaran Cek/Berhasil: <strong id="ubahDurasiDibayar">-</strong></div>
          </div>
          <div id="ubahDurasiAlert" class="alert alert-danger d-none small mt-2 mb-0 py-2"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" data-bs-dismiss="modal" class="btn btn-dark btn-sm">Batal</button>
        <button type="button" id="btnSimpanDurasi" class="btn btn-success btn-sm" disabled>Simpan</button>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="modalUbahLayanan" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ubah Layanan</h5>
        <button type="button" data-bs-dismiss="modal" class="btn-close"></button>
      </div>
      <div class="modal-body">
        <div id="ubahLayananLoading" class="text-center py-3">
          <i class="fas fa-spinner fa-spin"></i> Memuat...
        </div>
        <div id="ubahLayananContent" class="d-none">
          <p class="small text-muted mb-2" id="ubahLayananInfo"></p>
          <div class="mb-2"><strong>Item:</strong> <span id="ubahLayananItem"></span></div>
          <div class="form-group mb-2">
            <label class="small mb-1">Pilih Layanan</label>
            <select id="ubahLayananSelect" class="form-control form-control-sm"></select>
          </div>
          <div class="small border rounded p-2 bg-light">
            <div>Harga item: <strong id="ubahLayananItemHarga">-</strong></div>
            <div>Total order: <strong id="ubahLayananRefTotal">-</strong></div>
            <div id="ubahLayananBayarInfo" class="text-warning d-none mt-1">Pembayaran Cek/Berhasil: <strong id="ubahLayananDibayar">-</strong></div>
          </div>
          <div id="ubahLayananAlert" class="alert alert-danger d-none small mt-2 mb-0 py-2"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" data-bs-dismiss="modal" class="btn btn-dark btn-sm">Batal</button>
        <button type="button" id="btnSimpanLayanan" class="btn btn-success btn-sm" disabled>Simpan</button>
      </div>
    </div>
  </div>
</div>

<form class="ajax" action="<?= URL::BASE_URL; ?>Antrian/surcas" method="POST">
  <div class="modal" id="exampleModalSurcas">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Surcharge/Biaya Tambahan</h5>
        </div>
        <div class="modal-body">
          <div class="card-body">
            <div class="form-group">
              <label>Jenis Surcharge</label>
              <select name="surcas" class="form-control form-control-sm" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <?php foreach ($this->surcas as $sc) { ?>
                  <option value="<?= $sc['id_surcas_jenis'] ?>"><?= $sc['surcas_jenis'] ?></option>
                <?php } ?>
              </select>
            </div>
            <input type="hidden" name="no_ref" id="id_transaksi">
            <div class="form-group">
              <label>Jumlah Biaya</label>
              <input type="number" name="jumlah" class="form-control">
            </div>
            <div class="form-group">
              <label>Di input Oleh</label>
              <select name="user" class="form-control tize form-control-sm userSurcas" style="width: 100%;" required>
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
        </div>
        <div class="modal-footer">
          <button type="button" data-bs-dismiss="modal" class="btn btn-dark">Batal</button>
          <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </div>
    </div>
  </div>
</form>
</form>

<style>
  #offcanvasPayment {
    --bs-offcanvas-width: min(820px, 100vw);
    --pay-accent: #0f9f6e;
    --pay-accent-soft: #e8f8f1;
    --pay-accent-deep: #0b7a55;
    --pay-ink: #16302a;
    --pay-muted: #5f746d;
    --pay-line: #d7e5df;
    --pay-card: #ffffff;
    --pay-warn: #d97706;
    --pay-warn-soft: #fff7ed;
    --pay-danger: #dc2626;
    background: #f3f8f6;
  }
  #offcanvasPayment .offcanvas-header {
    background: linear-gradient(145deg, #0f9f6e, #17b981);
    color: #fff;
    border-bottom: 0;
    padding: 1rem 1.15rem;
  }
  #offcanvasPayment .offcanvas-title {
    font-weight: 800;
    letter-spacing: -0.01em;
    margin: 0;
  }
  #offcanvasPayment .offcanvas-body {
    background:
      radial-gradient(120% 80% at 100% 0%, rgba(15,159,110,0.10), transparent 45%),
      linear-gradient(180deg, #f7fbf9 0%, #eef5f2 100%);
    padding: 0;
  }
  #offcanvasPayment .pay-shell {
    padding: 1rem 1.1rem 1.2rem;
  }
  #offcanvasPayment .pay-layout {
    display: grid;
    grid-template-columns: 1.05fr 0.95fr;
    gap: 1rem;
    align-items: start;
  }
  #offcanvasPayment .pay-panel {
    background: var(--pay-card);
    border: 1px solid rgba(215, 229, 223, 0.95);
    border-radius: 18px;
    padding: 1rem;
    box-shadow: 0 10px 28px rgba(22, 48, 42, 0.05);
  }
  #offcanvasPayment .pay-panel + .pay-panel {
    position: sticky;
    top: 0.75rem;
  }
  #offcanvasPayment .pay-panel__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.85rem;
    color: var(--pay-ink);
    font-size: 0.95rem;
    font-weight: 800;
  }
  #offcanvasPayment .pay-panel__title i {
    width: 28px;
    height: 28px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    background: var(--pay-accent-soft);
    color: var(--pay-accent);
    font-size: 0.8rem;
  }
  #offcanvasPayment .pay-field {
    margin-bottom: 0.9rem;
  }
  #offcanvasPayment .pay-field:last-child {
    margin-bottom: 0;
  }
  #offcanvasPayment .pay-field-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--pay-muted);
    margin-bottom: 0.45rem;
  }
  #offcanvasPayment .pay-select,
  #offcanvasPayment .pay-input {
    width: 100%;
    border: 1.5px solid var(--pay-line);
    border-radius: 12px;
    background: #fbfffd;
    color: var(--pay-ink);
    padding: 0.55rem 0.75rem;
    font-size: 0.92rem;
    font-weight: 600;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
  }
  #offcanvasPayment .pay-input:focus,
  #offcanvasPayment .pay-select:focus {
    border-color: var(--pay-accent);
    box-shadow: 0 0 0 3px rgba(15,159,110,0.14);
  }
  #offcanvasPayment .pay-input.is-readonly {
    background: #f3f7f5;
    color: var(--pay-muted);
  }
  #offcanvasPayment .selectize-control.single .selectize-input {
    border: 1.5px solid var(--pay-line) !important;
    border-radius: 12px !important;
    background: #fbfffd !important;
    min-height: 42px;
    padding: 8px 12px !important;
    box-shadow: none !important;
  }
  #offcanvasPayment .selectize-control.single .selectize-input.focus {
    border-color: var(--pay-accent) !important;
    box-shadow: 0 0 0 3px rgba(15,159,110,0.14) !important;
  }
  #offcanvasPayment .pay-method-grid,
  #offcanvasPayment .pay-note-grid {
    display: grid;
    gap: 0.5rem;
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
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    min-height: 82px;
    padding: 0.7rem 0.45rem;
    border: 1.5px solid var(--pay-line);
    border-radius: 14px;
    background:
      linear-gradient(180deg, rgba(255,255,255,0.96), rgba(247,252,250,0.96)),
      radial-gradient(120% 80% at 50% 0%, rgba(15,159,110,0.08), transparent 55%);
    box-shadow: 0 1px 0 rgba(255,255,255,0.8) inset;
    color: var(--pay-ink);
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease, background .18s ease;
  }
  #offcanvasPayment .pay-opt__icon {
    width: 34px;
    height: 34px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: #edf6f2;
    color: var(--pay-accent);
    font-size: 0.95rem;
  }
  #offcanvasPayment .pay-opt__name {
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.15;
    text-align: center;
  }
  #offcanvasPayment .pay-opt__extra {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--pay-muted);
    line-height: 1.1;
    text-align: center;
    min-height: 0.85em;
  }
  #offcanvasPayment .pay-opt__face:hover {
    border-color: #9fd4bf;
    transform: translateY(-1px);
  }
  #offcanvasPayment .pay-opt.is-selected .pay-opt__face,
  #offcanvasPayment .pay-opt input:checked + .pay-opt__face {
    border-color: var(--pay-accent);
    background: linear-gradient(180deg, #f3fcf8, var(--pay-accent-soft));
    box-shadow: 0 0 0 3px rgba(15,159,110,0.16), 0 8px 18px rgba(15,159,110,0.12);
  }
  #offcanvasPayment .pay-opt.is-selected .pay-opt__icon,
  #offcanvasPayment .pay-opt input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-accent);
    color: #fff;
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
    border-radius: 9px;
    font-size: 0.82rem;
    flex-shrink: 0;
  }
  #offcanvasPayment .pay-note-grid .pay-opt__name {
    font-size: 0.82rem;
  }
  #offcanvasPayment .pay-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    width: 100%;
    border: 0;
    border-radius: 12px;
    padding: 0.7rem 0.9rem;
    font-size: 0.88rem;
    font-weight: 750;
    cursor: pointer;
    text-decoration: none;
    transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
  }
  #offcanvasPayment .pay-btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.02);
  }
  #offcanvasPayment .pay-btn--warn {
    background: linear-gradient(135deg, #f59e0b, #ea580c);
    color: #1a1208;
    box-shadow: 0 8px 18px rgba(234, 88, 12, 0.18);
  }
  #offcanvasPayment .pay-btn--ghost {
    background: #e8efec;
    color: #33443e;
  }
  #offcanvasPayment .pay-btn--pass {
    background: linear-gradient(135deg, #38bdf8, #0284c7);
    color: #fff;
    box-shadow: 0 8px 18px rgba(2, 132, 199, 0.18);
  }
  #offcanvasPayment .pay-btn--primary {
    background: linear-gradient(135deg, var(--pay-accent), var(--pay-accent-deep));
    color: #fff;
    box-shadow: 0 10px 22px rgba(15, 159, 110, 0.22);
  }
  #offcanvasPayment .pay-tb-info {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.75rem 0.85rem;
    border-radius: 12px;
    background: var(--pay-warn-soft);
    border: 1px solid #fdba74;
    color: #9a3412;
    font-size: 0.82rem;
    line-height: 1.35;
  }
  #offcanvasPayment .pay-tb-info button {
    border: 0;
    background: transparent;
    color: var(--pay-danger);
    font-weight: 700;
    padding: 0;
    cursor: pointer;
  }
  #offcanvasPayment .pay-bill-list {
    max-height: 240px;
    overflow-y: auto;
    display: grid;
    gap: 0.45rem;
    padding-right: 0.15rem;
  }
  #offcanvasPayment .pay-bill-item {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 0.65rem;
    align-items: center;
    padding: 0.7rem 0.8rem;
    border: 1.5px solid var(--pay-line);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff, #f7fbf9);
  }
  #offcanvasPayment .pay-bill-item__ref {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--pay-ink);
    word-break: break-all;
  }
  #offcanvasPayment .pay-bill-item__amt {
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--pay-ink);
    min-width: 4.5rem;
    text-align: right;
  }
  #offcanvasPayment .pay-bill-item input.cek {
    width: 1.05rem;
    height: 1.05rem;
    accent-color: var(--pay-accent);
    cursor: pointer;
  }
  #offcanvasPayment .pay-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.85rem 0 0.75rem;
    padding: 0.85rem 0.95rem;
    border-radius: 14px;
    background: linear-gradient(135deg, #fff1f2, #ffe4e6);
    border: 1px solid #fecdd3;
  }
  #offcanvasPayment .pay-total__label {
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9f1239;
  }
  #offcanvasPayment .pay-total__value {
    font-size: 1.2rem;
    font-weight: 850;
    color: var(--pay-danger);
    letter-spacing: -0.02em;
  }
  #offcanvasPayment .pay-money-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
    margin-top: 0.75rem;
  }
  #offcanvasPayment .pay-actions {
    display: grid;
    grid-template-columns: 0.85fr 1.35fr;
    gap: 0.65rem;
    margin-top: 0.95rem;
  }
  #offcanvasPayment #alertRecap {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
    border-radius: 12px;
    padding: 0.65rem 0.8rem;
    margin-bottom: 0.85rem;
    text-align: center;
    font-size: 0.9rem;
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
          <section class="pay-panel">
            <h6 class="pay-panel__title"><i class="fas fa-sliders-h"></i> Metode & Penerima</h6>

            <div class="pay-field">
              <div class="pay-field-label">Penerima</div>
              <select name="karyawanBill" id="karyawanBill" class="form-control form-control-sm tize pay-select" style="width: 100%;" required>
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

          <section class="pay-panel">
            <h6 class="pay-panel__title"><i class="fas fa-receipt"></i> Tagihan & Bayar</h6>

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
<div class="modal" id="modalTanggungBayar" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 10070 !important;">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning py-2">
        <h6 class="modal-title"><i class="fas fa-user-friends me-2"></i>Tanggung Bayar</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3">
        <div class="alert alert-danger py-2 small mb-3">
          <i class="fas fa-exclamation-triangle me-1"></i>
          Penggunaan saldo untuk tanggung bayar diawasi ketat oleh Admin. Mohon hati-hati dalam memilih penanggung bayar — kesalahan pilih dapat menimbulkan perselisihan di kemudian hari.
        </div>
        <p class="small text-muted mb-2">Order atas nama: <strong><?= strtoupper($nama_pelanggan ?? '') ?></strong></p>
        <input type="text" id="searchPenanggungBayar" class="form-control form-control-sm mb-2" placeholder="Cari nama atau nomor HP..." autocomplete="off">
        <div id="listPenanggungBayar" style="max-height: 280px; overflow-y: auto;">
          <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>
        </div>
        <div id="tbKonfirmasi" class="d-none mt-3 p-2 border rounded bg-light">
          <p class="small mb-2">
            Bayar tagihan <strong id="tbKonfirmasiOrder"></strong> menggunakan saldo:
          </p>
          <p class="mb-2">
            <strong id="tbKonfirmasiNama" class="text-primary"></strong><br>
            <span class="text-muted small">Saldo: Rp <span id="tbKonfirmasiSaldo"></span></span>
          </p>
          <button type="button" class="btn btn-success btn-sm w-100" id="btnKonfirmasiTanggungBayar">
            <i class="fas fa-check"></i> Gunakan Saldo Ini
          </button>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Alert Profesional -->
<div class="modal" id="modalAlert" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 10060 !important; background: rgba(0,0,0,0.5);">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">
          <i class="fas fa-info-circle text-primary" id="modalAlertIcon"></i>
          <span id="modalAlertTitle">Informasi</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="modalAlertMessage" class="mb-0"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal QR Code -->
<div class="modal" id="modalQR" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 10050 !important;">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="z-index: 10051 !important;">
      <div class="modal-header">
        <h6 class="modal-title">Scan QRIS</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div id="qrcode" class="d-flex justify-content-center mb-3"></div>
        <p class="mb-0 fw-bold" id="qrTotal"></p>
        <p class="mb-0" id="qrNama"></p>
        <div id="devModeLabel" class="mt-2 d-none">
          <span class="badge bg-warning text-dark">DEV MODE - FAKE QR</span>
          <div class="alert alert-secondary mt-1 p-1 small text-start" style="font-size: 0.7rem; overflow-wrap: break-word;" id="devApiRes"></div>
        </div>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-warning btn-sm" id="btnCekStatusQR"><i class="fas fa-sync"></i> Cek Status</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnPrintQR"><i class="fas fa-print"></i> Print</button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Cancel Payment Confirmation -->
<div class="modal" id="modalCancelPayment" tabindex="-1" data-bs-backdrop="static" style="z-index: 10060;">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow" style="z-index: 10061;">
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
        </div>
        <h5 class="mb-2">Batalkan Pembayaran?</h5>
        <p class="text-muted mb-2" id="cancelPaymentInfo"></p>
        <p class="small text-danger mb-3">Data pembayaran akan dihapus permanen.</p>
        <div class="d-flex gap-2 justify-content-center">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-danger px-4" id="btnConfirmCancel">
            <i class="fas fa-trash-alt"></i> Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Hapus Order -->
<div class="modal" id="modalHapusOrder" tabindex="-1" aria-hidden="true" style="z-index: 99999 !important;">
  <div class="modal-dialog modal-dialog-centered modal-sm" style="z-index: 100000 !important;">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Hapus Order</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <p class="mb-2">Yakin ingin menghapus order <strong id="hapusOrderRef"></strong>?</p>
        <div class="mb-2">
          <label class="form-label small mb-1">Alasan Hapus <span class="text-danger">*</span></label>
          <input type="text" class="form-control form-control-sm" id="hapusOrderNote" placeholder="Masukkan alasan..." required>
        </div>
        <p class="small text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Data tidak dapat dikembalikan.</p>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger btn-sm" id="btnKonfirmasiHapus">
          <i class="fas fa-trash-alt me-1"></i>Hapus
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Offcanvas Buka Order -->


<style>
  #modalHapusOrder + .modal-backdrop {
    z-index: 99998 !important;
  }
</style>