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
                  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exampleModal">Pengeluaran</a>
                  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exampleModal3">Penarikan</a>
                  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exampleModal2">Kasbon</a>
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

<div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Pengeluaran</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <!-- ====================== FORM ========================= -->
        <form action="<?= URL::BASE_URL; ?>Kas/insert_pengeluaran" method="POST">
          <div class="card-body">
            <div class="form-group">
              <label class="small text-muted">Saldo Kas</label>
              <div class="form-control text-center text-bold saldoKas py-2" style="font-size:1.1rem;background:#f8f9fa;">Rp <?= number_format($kas); ?></div>
              <input type="hidden" name='kas' class="saldoKasHidden" value="<?= $kas ?>">
            </div>
            <div class="form-group" id="jenisKeluar">
              <label for="exampleInputEmail1">Jenis Pengeluaran</label>
              <select name="f1a" class="form-control form-control-sm jenisKeluar" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <optgroup label="💸 Biaya Operasional">
                  <?php foreach ($this->dItemPengeluaran as $ip) { 
                    if (isset($ip['is_expense']) && $ip['is_expense'] == 1) { ?>
                    <option value="<?= $ip['id_item_pengeluaran'] ?><explode><?= $ip['item_pengeluaran'] ?>"><?= $ip['item_pengeluaran'] ?></option>
                  <?php } 
                  } ?>
                </optgroup>
                <optgroup label="💰 Non-Biaya (Prive/Aset)">
                  <?php foreach ($this->dItemPengeluaran as $ip) { 
                    if (isset($ip['is_expense']) && $ip['is_expense'] == 0) { ?>
                    <option value="<?= $ip['id_item_pengeluaran'] ?><explode><?= $ip['item_pengeluaran'] ?>"><?= $ip['item_pengeluaran'] ?></option>
                  <?php } 
                  } ?>
                </optgroup>
              </select>
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Jumlah Rp</label>
              <input type="number" name="f2" min="1000" class="form-control jumlahTarik jumlahPengeluaran" placeholder="" required>
              <small class="text-success mt-1 d-block"><strong>Jumlah:</strong> <span class="liveAmount">Rp 0</span></small>
            </div>
            <div class="form-group">
              <?php require __DIR__ . '/_keterangan_pengeluaran.php'; ?>
            </div>
            <div class="form-group" id="userKeluar">
              <label for="exampleInputEmail1">Penarik Kas</label>
              <select name="f3" class="form-control form-control-sm userKeluar" style="width: 100%;" required>
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
          <div class="modal-footer">
            <button type="submit" class="btn w-100 btn-danger">Buat Pengeluaran</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="exampleModal3" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Penarikan Kas</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <!-- ====================== FORM ========================= -->
        <form action="<?= URL::BASE_URL; ?>Kas/insert" method="POST">
          <div class="card-body">
            <div class="form-group">
              <label class="small text-muted">Saldo Kas</label>
              <div class="form-control text-center text-bold saldoKas py-2" style="font-size:1.1rem;background:#f8f9fa;">Rp <?= number_format($kas); ?></div>
              <input type="hidden" name='kas' class="saldoKasHidden" value="<?= $kas ?>">
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Jumlah Rp</label>
              <input type="number" name="f2" min="1000" class="form-control jumlahTarik jumlahPenarikan" placeholder="" required>
              <small class="text-success mt-1 d-block"><strong>Jumlah:</strong> <span class="liveAmount">Rp 0</span></small>
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Keterangan</label>
              <input type="text" name="f1" class="form-control" id="exampleInputEmail1" placeholder="" required>
            </div>
            <label for="exampleInputEmail1">Penarik Kas</label>
            <select name="f3" class="tarik form-control form-control-sm" style="width: 100%;" required>
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
          <div class="modal-footer">
            <small class="text-danger">Penarikan Kas Laundry harus disetor kepada Admin sebagai Kas Utama</small>
            <button type="submit" class="btn w-100 btn-primary">Tarik Kas</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="exampleModal2" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Penginputan Kasbon</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <!-- ====================== FORM ========================= -->
        <form action="<?= URL::BASE_URL; ?>Kasbon/insert" method="POST">
          <div class="card-body">
            <div class="form-group">
              <label class="small text-muted">Saldo Kas</label>
              <div class="form-control text-center text-bold saldoKas py-2" style="font-size:1.1rem;background:#f8f9fa;">Rp <?= number_format($kas); ?></div>
              <input type="hidden" name='kas' class="saldoKasHidden" value="<?= $kas ?>">
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Karyawan Kasbon</label>
              <select name="f1" class="form-control form-control-sm" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                  <?php foreach ($this->user as $a) { ?>
                    <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
              </select>
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Jumlah</label>
              <input type="number" name="f2" min="1000" class="form-control jumlahTarik jumlahKasbon" placeholder="" required>
              <small class="text-success mt-1 d-block"><strong>Jumlah:</strong> <span class="liveAmount">Rp 0</span></small>
            </div>
            <div class="form-group">
              <label for="exampleInputEmail1">Metode</label>
              <select name="metode" class="form-control form-control-sm metodeBayar" style="width: 100%;" required>
                <?php foreach ($this->dMetodeMutasi as $a) {
                  if ($a['id_metode_mutasi'] <> 3) { ?>
                    <option value="<?= $a['id_metode_mutasi'] ?>"><?= $a['metode_mutasi'] ?></option>
                <?php }
                } ?>
              </select>
            </div>
            <label for="exampleInputEmail1">Penginput</label>
            <select name="f3" class="kasbon form-control form-control-sm userChange" style="width: 100%;" required>
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
            <div class="row mt-4" id="nTunai">
              <div class="col-sm-12">
                <div class="form-group">
                  <div class="form-group">
                    <label for="exampleInputEmail1" class="text-danger">Catatan Non Tunai <small>(Contoh: BRI)</small></label>
                    <input type="text" name="note" maxlength="10" class="form-control border-danger" id="exampleInputEmail1" placeholder="" style="text-transform:uppercase">
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn w-100 btn-warning">Buat Kasbon</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
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

    $('.modal button[type="submit"]').each(function() {
      $(this).data('original-text', $(this).html());
    });
  });

  $("form").on("submit", function(e) {
    e.preventDefault();
    var $form = $(this);
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
        alert(msg);
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
    var potong = parseInt($(this).val()) || 0;
    var sisaKas = saldoKas - potong;

    $(this).closest('form').find('.saldoKas').text(formatRupiah(sisaKas));
    $(this).closest('form').find('.saldoKasHidden').val(sisaKas);

    if (sisaKas < 0) {
      $(this).closest('form').find('.saldoKas').addClass('text-danger').removeClass('text-success');
    } else {
      $(this).closest('form').find('.saldoKas').removeClass('text-danger').addClass('text-success');
    }

    $(this).siblings('small').find('.liveAmount').text(formatRupiah(potong));
  });

  $("button.dropdown-toggle").on("click", function() {
    saldoKas = <?= $kas ?>;
    $('.saldoKas').each(function() {
      $(this).text(formatRupiah(saldoKas)).removeClass('text-danger').addClass('text-success');
    });
    $('.saldoKasHidden').val(saldoKas);
    $('.liveAmount').text('Rp 0');
    $('input.jumlahTarik').val('');
  });

  $('.modal').on('show.bs.modal', function() {
    saldoKas = <?= $kas ?>;
    $(this).find('.saldoKas').text(formatRupiah(saldoKas)).removeClass('text-danger').addClass('text-success');
    $(this).find('.saldoKasHidden').val(saldoKas);
    $(this).find('.liveAmount').text('Rp 0');
  });

  function selectList() {
    $('select.userKeluar').selectize({
      sortField: 'text'
    });
    $('select.jenisKeluar').selectize({
      sortField: []
    });
    $('select.tarik').selectize({
      sortField: 'text'
    });
    $('select.kasbon').selectize({
      sortField: 'text'
    });
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