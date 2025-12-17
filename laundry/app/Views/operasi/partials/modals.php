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
              <input type="hidden" class="textNotif" name="text" value="" required>
              <input type="hidden" name="inTotalNotif" value="" required>
              <input type="hidden" class="hpNotif" name="hp" value="" required>
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

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasPayment" aria-labelledby="offcanvasPaymentLabel" data-bs-backdrop="false" data-bs-scroll="true">
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
                    <td>Metode</td>
                    <td colspan="2" class="pb-2"><select name="metodeBill" id="metodeBill" class="form-control form-control-sm metodeBayarBill" style="width: 100%;" required>
                        <?php foreach ($this->dMetodeMutasi as $a) {
                          if ($data['saldoTunai'] <= 0 && $a['id_metode_mutasi'] == 3) {
                            continue;
                          } ?>
                          <option value="<?= $a['id_metode_mutasi'] ?>"><?= $a['metode_mutasi'] ?> <?= ($a['id_metode_mutasi'] == 3) ? "[ " . number_format($data['saldoTunai']) . " ]" : "" ?></option>
                        <?php } ?>
                      </select></td>
                    <td></td>
                  </tr>
                  <tr id="nTunaiBill" class="border-top">
                    <td style="vertical-align: bottom;" class="pr-2 pb-2" nowrap><br>Tujuan</td>
                    <td colspan="2" class="pb-2 pt-2">
                      <select name="noteBill" id="noteBill" class="form-control border-danger" required>
                        <option value="" selected>Pilih Pembayaran</option>
                        <?php foreach (URL::NON_TUNAI as $ntm) { ?>
                          <option value="<?= $ntm ?>"><?= $ntm ?></option>
                        <?php } ?>
                      </select>
                    </td>
                    <td></td>
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
                    <td colspan="2" class="pb-1"><input id="bayarBill" name="dibayarBill" class="text-right form form-control form-control-sm" type="number" min="1" value="" required /></td>
                  </tr>
                  <tr>
                    <td>Kembalian</td>
                    <td colspan="2"><input id='kembalianBill' name="kembalianBill" class="text-right form form-control form-control-sm" type="number" readonly /></td>
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