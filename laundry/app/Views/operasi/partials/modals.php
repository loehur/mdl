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
    --pay-radius: 16px;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    color: var(--pay-ink);
  }
  #offcanvasPayment .offcanvas-header {
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 35%, #16a34a 70%, #f59e0b 100%);
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
    border: 2px solid #e2e8f0;
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
    border-radius: 10px;
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
    border: 2px solid #94a3b8;
    border-radius: 12px;
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
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.22);
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
    border: 2px solid #94a3b8 !important;
    border-radius: 12px !important;
    background: #fff !important;
    min-height: 42px;
    padding: 8px 12px !important;
    box-shadow: none !important;
    font-weight: 800 !important;
    color: var(--pay-ink) !important;
  }
  #offcanvasPayment .selectize-control.single .selectize-input.focus {
    border-color: var(--pay-blue) !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.22) !important;
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
    border: 2px solid #cbd5e1;
    border-radius: 14px;
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
    border-radius: 50%;
    border: 2px solid #cbd5e1;
    background: #fff;
  }
  #offcanvasPayment .pay-opt__icon {
    width: 36px;
    height: 36px;
    border-radius: 11px;
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
    border-width: 3px;
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
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.28), 0 10px 20px rgba(22, 163, 74, 0.18);
  }
  #offcanvasPayment .pay-opt[data-metode-id="1"].is-selected .pay-opt__icon,
  #offcanvasPayment .pay-opt[data-metode-id="1"] input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-green);
  }
  #offcanvasPayment .pay-opt[data-metode-id="2"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-opt[data-metode-id="2"] input:checked + .pay-opt__face {
    border-color: var(--pay-blue);
    background: linear-gradient(180deg, #bfdbfe, #dbeafe);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.28), 0 10px 20px rgba(37, 99, 235, 0.18);
  }
  #offcanvasPayment .pay-opt[data-metode-id="2"].is-selected .pay-opt__icon,
  #offcanvasPayment .pay-opt[data-metode-id="2"] input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-blue);
  }
  #offcanvasPayment .pay-opt[data-metode-id="3"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-opt[data-metode-id="3"] input:checked + .pay-opt__face {
    border-color: var(--pay-yellow-deep);
    background: linear-gradient(180deg, #fde68a, #fef3c7);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.35), 0 10px 20px rgba(217, 119, 6, 0.18);
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
    border-radius: 9px;
    font-size: 0.82rem;
    flex-shrink: 0;
  }
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="QRIS"].is-selected .pay-opt__face,
  #offcanvasPayment .pay-note-grid .pay-opt[data-note="QRIS"] input:checked + .pay-opt__face {
    border-color: var(--pay-blue);
    background: linear-gradient(180deg, #bfdbfe, #dbeafe);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.28), 0 8px 16px rgba(37, 99, 235, 0.16);
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
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.28), 0 8px 16px rgba(22, 163, 74, 0.16);
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
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.35), 0 8px 16px rgba(217, 119, 6, 0.16);
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
    border-radius: 12px;
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
    border-radius: 12px;
    background: #fff7ed;
    border: 2px solid #fbbf24;
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
    border: 2px solid #cbd5e1;
    border-radius: 12px;
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
    border-radius: 14px;
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border: 2px solid #f87171;
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
    border: 2px solid #f87171;
    background: #fef2f2;
    color: var(--pay-red-deep);
    border-radius: 12px;
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