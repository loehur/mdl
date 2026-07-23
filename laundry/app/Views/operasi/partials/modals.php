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
    --pay-accent: #0f9f6e;
    --pay-accent-soft: #e8f8f1;
    --pay-ink: #16302a;
    --pay-muted: #5f746d;
    --pay-line: #d7e5df;
    --pay-card: #ffffff;
  }
  #offcanvasPayment .pay-field-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--pay-muted);
    margin-bottom: 0.45rem;
  }
  #offcanvasPayment .pay-method-grid,
  #offcanvasPayment .pay-note-grid {
    display: grid;
    gap: 0.5rem;
  }
  #offcanvasPayment .pay-method-grid {
    grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
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
    min-height: 78px;
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
  }
  #offcanvasPayment .pay-opt__face:hover {
    border-color: #9fd4bf;
    transform: translateY(-1px);
  }
  #offcanvasPayment .pay-opt.is-selected .pay-opt__face,
  #offcanvasPayment .pay-opt input:checked + .pay-opt__face {
    border-color: var(--pay-accent);
    background:
      linear-gradient(180deg, #f3fcf8, var(--pay-accent-soft));
    box-shadow: 0 0 0 3px rgba(15,159,110,0.16), 0 8px 18px rgba(15,159,110,0.12);
  }
  #offcanvasPayment .pay-opt.is-selected .pay-opt__icon,
  #offcanvasPayment .pay-opt input:checked + .pay-opt__face .pay-opt__icon {
    background: var(--pay-accent);
    color: #fff;
  }
  #offcanvasPayment .pay-note-grid .pay-opt__face {
    min-height: 64px;
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
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasPayment" aria-labelledby="offcanvasPaymentLabel" data-bs-backdrop="true" data-bs-scroll="true" style="z-index: 1100;">
  <div class="offcanvas-header bg-success bg-gradient">
    <h5 class="offcanvas-title" id="offcanvasPaymentLabel">Pembayaran</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body bg-gradient">
    <div id="loadRekap" class="pb-0 w-100">
      <div class="row mx-0">
        <div class="col p-1">
            <form method="POST" class="ajax_json">
              <div class="p-2">
                <div id="alertRecap" class="alert alert-danger d-none py-2 mb-2 text-center" style="font-size: 0.9rem; line-height: 1.2;"></div>
                <table class="w-100">
                  <tr>
                    <td class="pb-1">Penerima</td>
                    <td colspan="2" class="pt-2"><select name="karyawanBill" id="karyawanBill" class="form-control form-control-sm tize" style="width: 100%;" required>
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
                      </select></td>
                  </tr>
                  <tr>
                    <td colspan="3" class="pb-2 pt-2">
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
                              <?php if ($mextra !== '') { ?>
                                <span class="pay-opt__extra"><?= htmlspecialchars($mextra) ?></span>
                              <?php } else { ?>
                                <span class="pay-opt__extra"></span>
                              <?php } ?>
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
                    </td>
                  </tr>
                  <tr id="rowTanggungBayar">
                    <td colspan="3" class="pb-2">
                      <button type="button" class="btn btn-warning btn-sm w-100" id="btnTanggungBayar">
                        <i class="fas fa-user-friends"></i> Tanggung Bayar
                      </button>
                    </td>
                  </tr>
                  <tr id="rowTanggungBayarInfo" class="d-none">
                    <td colspan="3" class="pb-2">
                      <div class="alert alert-warning py-2 mb-0 small">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <strong>Penanggung Bayar:</strong> <span id="tbNamaPenanggung"></span><br>
                            <strong>Saldo:</strong> Rp <span id="tbSaldoPenanggung"></span>
                          </div>
                          <button type="button" class="btn btn-link btn-sm p-0 text-danger" id="btnBatalTanggungBayar">Batal</button>
                        </div>
                      </div>
                      <input type="hidden" id="idPenanggungBayar" value="">
                    </td>
                  </tr>
                  <tr id="nTunaiBill" class="border-top">
                    <td colspan="3" class="pb-2 pt-2">
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
                    </td>
                  </tr>
                  <tr class="">
                    <td colspan="3" class="pb-1"></td>
                  </tr>
                  <tr>
                    <td colspan="3" class="pb-3">
                      <div class="shadow-sm" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm">
                          <?php
                          $totalTagihan = 0;
                          foreach ($loadRekap as $key => $value) {
                            echo "<tr>
                                  <td><span class='text-dark'>" . $key . "</span></td>
                                  <td class='text-end'><input class='cek mt-1' type='checkbox' data-jumlah='" . $value . "' data-ref='" . $key . "' checked></td>
                                  <td class='text-end' style='width: 70px;'>" . number_format($value) . "</td>
                                  </tr>";
                            $totalTagihan += $value;
                          } ?>
                        </table>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td class="pb-2 text-danger" nowrap>
                      <b>TOTAL TAGIHAN</b>
                    </td>
                    <td></td>
                    <td class="text-right text-danger">
                      <span data-total=''><b><span id="totalBill" data-total="<?= $totalTagihan ?>"><?= number_format($totalTagihan) ?></span></b></span>
                    </td>
                  </tr>
                  <tr class="">
                    <td></td>
                    <td colspan="2" class="pt-2 pb-1"><a class="btn bg-gradient btn-sm w-100 btn-info bayarPasMulti">Bayar Pas (Click)</a></td>
                  </tr>
                  <tr>
                    <td>Jumlah Bayar</td>
                    <td colspan="2" class="pb-1"><input id="bayarBill" name="dibayarBill" class="text-right form form-control form-control-sm input-jumlah-bayar" type="text" inputmode="numeric" placeholder="0" value="" required /></td>
                  </tr>
                  <tr>
                    <td>Kembalian</td>
                    <td colspan="2"><input id='kembalianBill' name="kembalianBill" class="text-right form form-control form-control-sm" type="text" readonly placeholder="0" /></td>
                  </tr>
                  <tr>
                    <td class="pe-3 pt-3"><button data-bs-dismiss="offcanvas" type="button" class="btn btn-secondary bg-gradient w-100">Batal</button></td>
                    <td colspan="2" class="pt-3">
                      <button type="submit" id="btnBayarBill" class='btn btn-success bg-gradient w-100 fw-bold'><i class="fas fa-wallet fa-lg"></i> Bayar</button>
                    </td>
                  </tr>
                </table>
            </form>
      </div>
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