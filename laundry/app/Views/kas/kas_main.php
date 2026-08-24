<?php $kas = $data['saldo']; ?>
<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto p-1">
        <div class="p-0">
          <div class="d-flex flex-row">
            <div class="mr-auto">
              <small>Saldo Kas</small><br>
              <span class="text-bold text-success">Rp. <?= number_format($kas); ?></span>
            </div>
            <div class="p-0 pr-0 pb-2 pt-2">
              <div class="btn-group dropdown">
                <button class="btn btn-sm btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  Menu Kas
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-op-target="#exampleModal">Pengeluaran</a>
                  <a class="dropdown-item" href="#" data-op-target="#exampleModal3">Penarikan</a>
                  <a class="dropdown-item" href="#" data-op-target="#exampleModal2">Kasbon</a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col">
            <div class="card">
              <table class="table table-sm w-100 m-0" style="min-width: 300px;">
                <tr>
                  <th class="pt-2 text-center" colspan="4">
                    Cashflow History
                  </th>
                </tr>
                <tbody>
                  <?php
                  $no = 0;
                  foreach ($data['debit_list'] as $a) {
                    $id = $a['id_kas'];
                    $f1 = substr($a['insertTime'], 5, 11);
                    $f2 = $a['note'];
                    $f2b = $a['note_primary'];
                    $f3 = $a['id_user'];
                    $f4 = $a['jumlah'];
                    $f5 = $a['status_mutasi'];
                    $f6 = $a['jenis_transaksi'];
                    $st = $a['status_mutasi'];
                    $cl = $a['id_client'];
                    $metod = $a['metode_mutasi'];

                    $karyawan = '';
                    if ($f3 == 0) {
                      $karyawan = "Admin";
                    } else {
                      foreach ($this->userMerge as $c) {
                        if ($c['id_user'] == $f3) {
                          $karyawan = $c['nama_user'];
                        }
                      }
                    }

                    $statusNya = '';
                    foreach ($this->dStatusMutasi as $c) {
                      if ($c['id_status_mutasi'] == $st) {
                        if ($st == 3) {
                          $statusNya = "<small class='text-success'>" . $c['status_mutasi'] . "</small>";
                        } else if ($st == 2) {
                          $statusNya = "<small class='text-warning'>" . $c['status_mutasi'] . "</small>";
                        } else {
                          $statusNya = "<small class='text-danger'>" . $c['status_mutasi'] . "</small>";
                        }
                      }
                    }

                    $client = "";
                    if ($f6 == 5) {
                      foreach ($this->userMerge as $c) {
                        if ($c['id_user'] == $cl) {
                          $client = "" . $c['nama_user'] . "";
                        }
                      }
                    }

                    $classTR = '';
                    if ($f6 == 4) {
                      $classTR = 'text-danger';
                    }
                    if ($f6 == 5) {
                      $classTR = 'text-info';
                    }

                    $metode = "";
                    foreach ($this->dMetodeMutasi as $mm) {
                      if ($mm['id_metode_mutasi'] == $metod) {
                        $metode = $mm['metode_mutasi'];
                      }
                    }

                    echo "<tr>";
                    echo "<td nowrap class='text-right'><small>#" . $id . "<br>" . $f1 . "</small></td>";
                    echo "<td><span><small>Penarik: " . $karyawan . "</small><br><b class='" . $classTR . "'>" . $f2b . "</b> <small>" . $f2 . " " . $client . "</></small></span></td>";
                    echo "<td nowrap class='text-right'><small>" . $metode . "</small> <b><span>" . number_format($f4) . "</span><br>" . $statusNya . "</b></td>";
                    echo "</tr>";
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php if (count($data['kasbon']) > 0) { ?>
            <div class="col">
              <div class="card">
                <table class="table table-sm w-100 p-0 m-0">
                  <th class="pt-2 text-center" colspan="4">
                    Kasbon History
                  </th>
                  <tbody>
                    <?php
                    foreach ($data['kasbon'] as $a) {
                      $id = $a['id_kas'];
                      $st_trx = $a['status_mutasi'];

                      $f1 = substr($a['insertTime'], 5, 11);
                      $f2 = $a['note'];
                      $f2b = $a['note_primary'];
                      $f3 = $a['id_user'];
                      $f4 = $a['jumlah'];
                      $f6 = $a['jenis_transaksi'];
                      $st = $a['status_mutasi'];
                      $cl = $a['id_client'];
                      $metod = $a['metode_mutasi'];

                      $metode = "";
                      foreach ($this->dMetodeMutasi as $mm) {
                        if ($mm['id_metode_mutasi'] == $metod) {
                          $metode = $mm['metode_mutasi'];
                        }
                      }

                      $statusNya = '';
                      foreach ($this->dStatusMutasi as $c) {
                        if ($c['id_status_mutasi'] == $st) {
                          if ($st == 3) {
                            $statusNya = "<small class='text-success'>" . $c['status_mutasi'] . "</small>";
                          } else if ($st == 2) {
                            $statusNya = "<small class='text-warning'>" . $c['status_mutasi'] . "</small>";
                          } else {
                            $statusNya = "<small class='text-danger'>" . $c['status_mutasi'] . "</small>";
                          }
                        }
                      }

                      $stKasbon = $data['dataPotong'][$id];
                      $statusKasbon = "";
                      $trKasbon = "";
                      if ($stKasbon == 1) {
                        $statusKasbon = "<span class='text-success'>Lunas</span>";
                        $trKasbon = "table-success";
                      } else {
                        $statusKasbon = "";
                        $trKasbon = "table-light";
                      }

                      $karyawan = '';
                      $karyawan_tarik = '';

                      $id_kar = $a['id_client'];
                      $id_kar_tarik = $a['id_user'];

                      foreach ($this->userAll as $c) {
                        if ($c['id_user'] == $id_kar) {
                          $karyawan = $c['nama_user'];
                        }
                        if ($c['id_user'] == $id_kar_tarik) {
                          $karyawan_tarik = $c['nama_user'];
                        }
                      }

                      $st_trx_name = "";
                      foreach ($this->dStatusMutasi as $st) {
                        if ($st['id_status_mutasi'] == $st_trx) {
                          $st_trx_name = $st['status_mutasi'];
                        }
                      }

                      echo "<tr class='" . $trKasbon . "'>";
                      echo "<td class='text-right'><small>#" . $id . "<br>" . substr($a['insertTime'], 5, 11) . "</small></td>";
                      echo "<td><span><small>Penarik: " . $karyawan_tarik . "<br></small><b>" . $f2b . "</b> <small>" . " " . $karyawan . " " . $f2 . "</></small></span></td>";
                      echo "<td class='text-right'><small>" . $metode . "</small> <span>" . number_format($a['jumlah']) . "</span><br>" . $statusNya . " " . $statusKasbon . "</td>";
                      echo "</tr>";
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_kas_modal_theme.php'; ?>

<!-- Modal Pengeluaran -->
<div class="op-modal" id="exampleModal" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--kas op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="kasPgTitle">
    <div class="op-modal__head op-modal__head--red">
      <div>
        <h5 id="kasPgTitle"><i class="fas fa-minus-circle"></i> Pengeluaran</h5>
        <small>Catat pengeluaran kas cabang</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <form class="op-modal__form-wrap" action="<?= URL::BASE_URL; ?>Kas/insert_pengeluaran" method="POST">
      <div class="op-modal__body">
        <div class="kas-pg-modal-grid">
          <div class="op-field kas-pg-span-2">
            <label class="op-label">Saldo Kas</label>
            <div class="kas-saldo-box saldoKas">Rp <?= number_format($kas); ?></div>
            <input type="hidden" name="kas" class="saldoKasHidden" value="<?= $kas ?>">
          </div>
          <div class="op-field" id="jenisKeluar">
            <label class="op-label">Jenis Pengeluaran</label>
            <select name="f1a" class="tize jenisKeluar" style="width: 100%;" required>
              <option value="" selected disabled></option>
              <optgroup label="Biaya Operasional">
                <?php foreach ($this->dItemPengeluaran as $ip) {
                  if (isset($ip['is_expense']) && $ip['is_expense'] == 1) { ?>
                  <option value="<?= $ip['id_item_pengeluaran'] ?><explode><?= $ip['item_pengeluaran'] ?>"><?= $ip['item_pengeluaran'] ?></option>
                <?php }
                } ?>
              </optgroup>
              <optgroup label="Non-Biaya (Prive/Aset)">
                <?php foreach ($this->dItemPengeluaran as $ip) {
                  if (isset($ip['is_expense']) && $ip['is_expense'] == 0) { ?>
                  <option value="<?= $ip['id_item_pengeluaran'] ?><explode><?= $ip['item_pengeluaran'] ?>"><?= $ip['item_pengeluaran'] ?></option>
                <?php }
                } ?>
              </optgroup>
            </select>
          </div>
          <div class="op-field">
            <label class="op-label">Jumlah Rp</label>
            <input type="number" name="f2" min="1000" class="op-input jumlahTarik jumlahPengeluaran" required>
            <small class="kas-live-amt"><strong>Jumlah:</strong> <span class="liveAmount">Rp 0</span></small>
          </div>
          <div class="op-field">
            <?php require __DIR__ . '/_keterangan_pengeluaran.php'; ?>
          </div>
          <div class="op-field" id="userKeluar">
            <label class="op-label">Penarik Kas</label>
            <select name="f3" class="tize userKeluar" style="width: 100%;" required>
              <option value="" selected disabled></option>
              <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                <?php foreach ($this->user as $a) { ?>
                  <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
              <?php if (count($this->userCabang) > 0) { ?>
                <optgroup label="----- Cabang Lain -----">
                  <?php foreach ($this->userCabang as $a) { ?>
                    <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
              <?php } ?>
            </select>
          </div>
        </div>
      </div>
      <div class="op-modal__foot">
        <button type="submit" class="op-btn op-btn--danger op-btn--block">Buat Pengeluaran</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Penarikan -->
<div class="op-modal" id="exampleModal3" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--kas op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="kasTarikTitle">
    <div class="op-modal__head op-modal__head--blue">
      <div>
        <h5 id="kasTarikTitle"><i class="fas fa-hand-holding-usd"></i> Penarikan Kas</h5>
        <small>Tarik kas untuk disetor ke admin</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <form class="op-modal__form-wrap formPenarikan" action="<?= URL::BASE_URL; ?>Kas/insert_penarikan_tunai" method="POST" data-action-tunai="<?= URL::BASE_URL; ?>Kas/insert_penarikan_tunai" data-action-nontunai="<?= URL::BASE_URL; ?>Kas/insert_penarikan_nontunai">
      <div class="op-modal__body">
        <div class="kas-pg-modal-grid">
          <div class="op-field">
            <label class="op-label">Metode Penarikan</label>
            <select name="metode_penarikan" class="op-input metodePenarikan" required>
              <option value="1" selected>Tunai</option>
              <option value="2">Non Tunai</option>
            </select>
          </div>
          <div class="op-field">
            <label class="op-label saldoPenarikanLabel">Saldo Kas</label>
            <div class="kas-saldo-box saldoPenarikan">Rp <?= number_format($kas); ?></div>
            <input type="hidden" name="kas" class="saldoPenarikanHidden" value="<?= $kas ?>">
          </div>
          <div class="op-field kas-pg-span-2 penarikanNonTunaiField" style="display:none;">
            <label class="op-label">Tujuan Non Tunai</label>
            <div class="d-flex gap-3 flex-wrap">
              <label class="d-flex align-items-center gap-2 mb-0">
                <input type="radio" name="note" value="BCA" class="penarikanNoteRadio">
                <span>BCA</span>
              </label>
              <label class="d-flex align-items-center gap-2 mb-0">
                <input type="radio" name="note" value="QRIS" class="penarikanNoteRadio" checked>
                <span>QRIS</span>
              </label>
            </div>
          </div>
          <div class="op-field">
            <label class="op-label">Jumlah Rp</label>
            <input type="number" name="f2" min="1000" class="op-input jumlahTarik jumlahPenarikan" required>
            <small class="kas-live-amt"><strong>Jumlah:</strong> <span class="liveAmount">Rp 0</span></small>
          </div>
          <div class="op-field penarikanKeteranganField">
            <label class="op-label">Keterangan</label>
            <input type="text" name="f1" class="op-input keteranganPenarikan" required>
          </div>
          <div class="op-field kas-pg-span-2">
            <label class="op-label">Penarik Kas</label>
            <select name="f3" class="tize tarik" style="width: 100%;" required>
              <option value="" selected disabled></option>
              <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                <?php foreach ($this->user as $a) { ?>
                  <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
              <?php if (count($this->userCabang) > 0) { ?>
                <optgroup label="----- Cabang Lain -----">
                  <?php foreach ($this->userCabang as $a) { ?>
                    <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
              <?php } ?>
            </select>
          </div>
        </div>
      </div>
      <div class="op-modal__foot">
        <p class="kas-hint-warn penarikanHintTunai">Penarikan Kas Laundry harus disetor kepada Admin sebagai Kas Utama</p>
        <p class="kas-hint-warn penarikanHintNonTunai" style="display:none;">Menunggu konfirmasi admin atau cron. QRIS tidak perlu scan.</p>
        <button type="submit" class="op-btn op-btn--blue op-btn--block">Tarik Kas</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Kasbon -->
<div class="op-modal" id="exampleModal2" aria-hidden="true">
  <div class="op-modal__backdrop" data-op-close></div>
  <div class="op-modal__panel op-modal__panel--kas op-modal__panel--form" role="dialog" aria-modal="true" aria-labelledby="kasBonTitle">
    <div class="op-modal__head op-modal__head--yellow">
      <div>
        <h5 id="kasBonTitle"><i class="fas fa-file-invoice-dollar"></i> Penginputan Kasbon</h5>
        <small>Catat kasbon karyawan</small>
      </div>
      <button type="button" class="op-modal__close" data-op-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <form class="op-modal__form-wrap" action="<?= URL::BASE_URL; ?>Kasbon/insert" method="POST">
      <div class="op-modal__body">
        <div class="kas-pg-modal-grid">
          <div class="op-field kas-pg-span-2">
            <label class="op-label">Saldo Kas</label>
            <div class="kas-saldo-box saldoKas">Rp <?= number_format($kas); ?></div>
            <input type="hidden" name="kas" class="saldoKasHidden" value="<?= $kas ?>">
          </div>
          <div class="op-field">
            <label class="op-label">Karyawan Kasbon</label>
            <select name="f1" class="tize kasbonKaryawan" style="width: 100%;" required>
              <option value="" selected disabled></option>
              <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                <?php foreach ($this->user as $a) { ?>
                  <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
            </select>
          </div>
          <div class="op-field">
            <label class="op-label">Jumlah</label>
            <input type="number" name="f2" min="1000" class="op-input jumlahTarik jumlahKasbon" required>
            <small class="kas-live-amt"><strong>Jumlah:</strong> <span class="liveAmount">Rp 0</span></small>
          </div>
          <div class="op-field">
            <label class="op-label">Metode</label>
            <select name="metode" class="op-input metodeBayar" required>
              <?php foreach ($this->dMetodeMutasi as $a) {
                if ($a['id_metode_mutasi'] <> 3) { ?>
                  <option value="<?= $a['id_metode_mutasi'] ?>"><?= $a['metode_mutasi'] ?></option>
              <?php }
              } ?>
            </select>
          </div>
          <div class="op-field">
            <label class="op-label">Penginput</label>
            <select name="f3" class="tize kasbon userChange" style="width: 100%;" required>
              <option value="" selected disabled></option>
              <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                <?php foreach ($this->user as $a) { ?>
                  <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
              <?php if (count($this->userCabang) > 0) { ?>
                <optgroup label="----- Cabang Lain -----">
                  <?php foreach ($this->userCabang as $a) { ?>
                    <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
              <?php } ?>
            </select>
          </div>
          <div class="op-field kas-pg-span-2" id="nTunai">
            <label class="op-label op-label--danger">Catatan Non Tunai <span style="text-transform:none;letter-spacing:0;">(Contoh: BRI)</span></label>
            <input type="text" name="note" maxlength="10" class="op-input op-input--danger" style="text-transform:uppercase">
          </div>
        </div>
      </div>
      <div class="op-modal__foot">
        <button type="submit" class="op-btn op-btn--warn op-btn--block">Buat Kasbon</button>
      </div>
    </form>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script src="<?= URL::IN_ASSETS ?>js/kas/kas_modal.js"></script>
<script src="<?= URL::IN_ASSETS ?>js/kas/pengeluaran_kendaraan.js"></script>

<script>
  var saldoKas = <?= $kas ?>;

  function formatRupiah(num) {
    return 'Rp ' + new Intl.NumberFormat('id-ID', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(num || 0);
  }

  $(document).ready(function() {
    selectList();
    if (window.KasPengeluaranKendaraan) {
      KasPengeluaranKendaraan.init();
    }
    $("div#nTunai").hide();

    $('.saldoKas').each(function() {
      $(this).text(formatRupiah(saldoKas));
    });
    $('.saldoKasHidden').val(saldoKas);
    $('.liveAmount').text('Rp 0');

    $('.op-modal button[type="submit"]').each(function() {
      $(this).data('original-text', $(this).html());
    });
  });

  $("form").on("submit", function(e) {
    e.preventDefault();
    var $form = $(this);
    if ($form.hasClass('formPenarikan')) {
      var isNonTunai = $form.find('.metodePenarikan').val() === '2';
      if (isNonTunai && !$form.find('.penarikanNoteRadio:checked').length) {
        var msg = 'Pilih tujuan BCA atau QRIS';
        if (window.MdlToast) window.MdlToast.error(msg);
        else alert(msg);
        return;
      }
      var potong = parseInt($form.find('.jumlahPenarikan').val(), 10) || 0;
      var saldoNow = getSaldoPenarikanAktif($form);
      if (potong > saldoNow) {
        var msgOver = 'Jumlah melebihi saldo tersedia';
        if (window.MdlToast) window.MdlToast.error(msgOver);
        else alert(msgOver);
        return;
      }
    }
    if (window.KasPengeluaranKendaraan && !KasPengeluaranKendaraan.prepareSubmit($form)) {
      return;
    }
    var $btn = $form.find('button[type="submit"]');
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');

    $.ajax({
      url: $form.attr('action'),
      data: $form.serialize(),
      type: $form.attr("method"),
      success: function(res) {
        if (res == 1 || res === 1 || res === '1') {
          location.reload(true);
        } else {
          $btn.prop('disabled', false).html($btn.data('original-text') || 'Submit');
        }
      },
      error: function(xhr) {
        $btn.prop('disabled', false).html($btn.data('original-text') || $btn.text().replace('Memproses...', '').trim());
        var msg = 'Terjadi kesalahan. Coba lagi.';
        if (xhr.status === 409) {
          try {
            var r = JSON.parse(xhr.responseText);
            msg = r.error || msg;
          } catch (e) {}
        } else if (xhr.status === 422) {
          try {
            var r422 = JSON.parse(xhr.responseText);
            msg = r422.error || msg;
          } catch (e2) {}
        }
        if (window.MdlToast) {
          window.MdlToast.error(msg);
        } else {
          alert(msg);
        }
      }
    });
  });


  $("select.metodeBayar").on("keyup change", function() {
    if ($(this).val() == 2) {
      $("div#nTunai").show();
    } else {
      $("div#nTunai").hide();
    }
  });

  $("input.jumlahTarik").on("input keyup change", function() {
    var $form = $(this).closest('form');
    var potong = parseInt($(this).val(), 10) || 0;
    var saldoAwal = $form.hasClass('formPenarikan')
      ? getSaldoPenarikanAktif($form)
      : saldoKas;
    var sisaKas = saldoAwal - potong;

    if ($form.hasClass('formPenarikan')) {
      $form.find('.saldoPenarikan').text(formatRupiah(sisaKas));
      $form.find('.saldoPenarikanHidden').val(sisaKas);
      if (sisaKas < 0) {
        $form.find('.saldoPenarikan').addClass('kas-saldo--minus');
      } else {
        $form.find('.saldoPenarikan').removeClass('kas-saldo--minus');
      }
    } else {
      $form.find('.saldoKas').text(formatRupiah(sisaKas));
      $form.find('.saldoKasHidden').val(sisaKas);
      if (sisaKas < 0) {
        $form.find('.saldoKas').addClass('kas-saldo--minus');
      } else {
        $form.find('.saldoKas').removeClass('kas-saldo--minus');
      }
    }

    $(this).siblings('small').find('.liveAmount').text(formatRupiah(potong));
  });

  function getSaldoPenarikanAktif($form) {
    return saldoKas;
  }

  function syncPenarikanForm($form) {
    var isNonTunai = $form.find('.metodePenarikan').val() === '2';

    $form.attr('action', isNonTunai ? $form.data('action-nontunai') : $form.data('action-tunai'));
    $form.find('.penarikanNonTunaiField').toggle(isNonTunai);
    $form.find('.penarikanKeteranganField').toggle(!isNonTunai);
    $form.find('.penarikanHintTunai').toggle(!isNonTunai);
    $form.find('.penarikanHintNonTunai').toggle(isNonTunai);
    $form.find('.keteranganPenarikan').prop('required', !isNonTunai);
    if (isNonTunai) {
      $form.find('.keteranganPenarikan').val('');
    }
    $form.find('.saldoPenarikanLabel').text('Saldo Kas');
    $form.find('.saldoPenarikan').text(formatRupiah(saldoKas)).removeClass('kas-saldo--minus');
    $form.find('.saldoPenarikanHidden').val(saldoKas);
    $form.find('.jumlahPenarikan').attr('min', 1000);

    var potong = parseInt($form.find('.jumlahPenarikan').val(), 10) || 0;
    if (potong > 0) {
      var sisa = saldoKas - potong;
      $form.find('.saldoPenarikan').text(formatRupiah(sisa));
      $form.find('.saldoPenarikanHidden').val(sisa);
      if (sisa < 0) {
        $form.find('.saldoPenarikan').addClass('kas-saldo--minus');
      }
    }
  }

  $(document).on('change', '.metodePenarikan', function() {
    syncPenarikanForm($(this).closest('form.formPenarikan'));
  });

  $("button.dropdown-toggle").on("click", function() {
    saldoKas = <?= $kas ?>;
    $('.saldoKas').each(function() {
      $(this).text(formatRupiah(saldoKas)).removeClass('kas-saldo--minus');
    });
    $('.saldoKasHidden').val(saldoKas);
    $('.liveAmount').text('Rp 0');
    $('input.jumlahTarik').val('');
  });

  function resetKasModalSaldo($root) {
    saldoKas = <?= $kas ?>;
    if ($root.hasClass('formPenarikan') || $root.find('.formPenarikan').length) {
      var $penarikan = $root.hasClass('formPenarikan') ? $root : $root.find('.formPenarikan');
      $penarikan.find('.metodePenarikan').val('1');
      $penarikan.find('.penarikanNoteRadio[value="QRIS"]').prop('checked', true);
      syncPenarikanForm($penarikan);
      $penarikan.find('.liveAmount').text('Rp 0');
      $penarikan.find('input.jumlahTarik').val('');
      return;
    }
    $root.find('.saldoKas').text(formatRupiah(saldoKas)).removeClass('kas-saldo--minus');
    $root.find('.saldoKasHidden').val(saldoKas);
    $root.find('.liveAmount').text('Rp 0');
    $root.find('input.jumlahTarik').val('');
  }

  document.addEventListener('op-modal:open', function(e) {
    if (e.target && e.target.classList && e.target.classList.contains('op-modal')) {
      resetKasModalSaldo($(e.target));
    }
  });

  function selectList() {
    $('select.userKeluar').selectize({ sortField: 'text' });
    $('select.jenisKeluar').selectize({ sortField: [] });
    $('select.tarik').selectize({ sortField: 'text' });
    $('select.kasbon').selectize({ sortField: 'text' });
    $('select.kasbonKaryawan').selectize({ sortField: 'text' });
  }

  function tarik(idnya) {
    $.ajax({
      url: '<?= URL::BASE_URL ?>Kasbon/tarik_kasbon/',
      data: {
        id: idnya
      },
      type: "POST",
      success: function() {
        location.reload(true);
      },
    });
  }

  function batal(idnya) {
    $.ajax({
      url: '<?= URL::BASE_URL ?>Kasbon/batal_kasbon/',
      data: {
        id: idnya
      },
      type: "POST",
      success: function() {
        location.reload(true);
      },
    });
  }
</script>