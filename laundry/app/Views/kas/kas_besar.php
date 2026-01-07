<?php $kas = $data['saldo']; ?>
<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto p-1">
        <div class="p-0">
          <div class="d-flex flex-row align-items-center gap-3 mb-3">
            <div class="mr-auto">
              <small class="text-muted">Saldo Kas Besar</small><br>
              <span class="text-bold fs-4 <?= $kas >= 0 ? 'text-primary' : 'text-danger' ?>">Rp. <?= number_format($kas); ?></span>
            </div>
            <div class="p-0">
              <div class="btn-group dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fas fa-plus-circle me-1"></i> Menu Kas Besar
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalPengeluaran">
                    <i class="fas fa-minus-circle text-danger me-2"></i> Pengeluaran
                  </a>
                  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalModal">
                    <i class="fas fa-plus-circle text-success me-2"></i> Tambah Modal
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col">
            <div class="card">
              <div class="card-header py-2">
                <b><i class="fas fa-history me-2"></i>Riwayat Pengeluaran Kas Besar</b>
              </div>
              <div class="card-body p-0">
                <table class="table table-sm table-hover w-100 m-0" style="min-width: 350px;">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center" style="width: 80px;">ID/Tgl</th>
                      <th>Keterangan</th>
                      <th class="text-end" style="width: 120px;">Jumlah</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    foreach ($data['transaksi_list'] as $a) {
                      $id = $a['id_kas'];
                      $f1 = substr($a['insertTime'], 5, 11);
                      $f2 = $a['note'];
                      $f2b = $a['note_primary'];
                      $f3 = $a['id_user'];
                      $f4 = $a['jumlah'];
                      $jenis_mutasi = $a['jenis_mutasi'];
                      $jenis_transaksi = $a['jenis_transaksi'];
                      $st = $a['status_mutasi'];
                      $metod = $a['metode_mutasi'];

                      // Nama karyawan
                      $karyawan = 'Admin';
                      if ($f3 > 0) {
                        foreach ($this->userMerge as $c) {
                          if ($c['id_user'] == $f3) {
                            $karyawan = $c['nama_user'];
                          }
                        }
                      }

                      // Status mutasi
                      $statusNya = '';
                      foreach ($this->dStatusMutasi as $c) {
                        if ($c['id_status_mutasi'] == $st) {
                          if ($st == 3) {
                            $statusNya = "<span class='badge bg-success'>Selesai</span>";
                          } else if ($st == 2) {
                            $statusNya = "<span class='badge bg-warning'>Pending</span>";
                          } else if ($st == 4) {
                            $statusNya = "<span class='badge bg-secondary'>Batal</span>";
                          } else {
                            $statusNya = "<span class='badge bg-danger'>Proses</span>";
                          }
                        }
                      }

                      // Metode mutasi
                      $metode = "";
                      foreach ($this->dMetodeMutasi as $mm) {
                        if ($mm['id_metode_mutasi'] == $metod) {
                          $metode = $mm['metode_mutasi'];
                        }
                      }

                      // Warna berdasarkan jenis
                      $rowClass = '';
                      $amountClass = 'text-success';
                      $prefix = '+';
                      if ($jenis_mutasi == 2) {
                        $amountClass = 'text-danger';
                        $prefix = '-';
                      }

                      // Jenis transaksi label
                      $jenisLabel = '';
                      switch ($jenis_transaksi) {
                        case 2: $jenisLabel = "<span class='badge bg-info'>Penarikan Kasir</span>"; break;
                        case 4: $jenisLabel = "<span class='badge bg-danger'>Pengeluaran</span>"; break;
                        case 6: $jenisLabel = "<span class='badge bg-success'>Modal</span>"; break;
                        default: $jenisLabel = "<span class='badge bg-secondary'>Lainnya</span>"; break;
                      }

                      echo "<tr>";
                      echo "<td class='text-center'><small class='text-muted'>#" . $id . "<br>" . $f1 . "</small></td>";
                      echo "<td>
                              <div><b>" . htmlspecialchars($f2b) . "</b> " . $jenisLabel . "</div>
                              <small class='text-muted'>" . htmlspecialchars($f2) . "</small><br>
                              <small>By: " . htmlspecialchars($karyawan) . " | " . $metode . "</small>
                            </td>";
                      echo "<td class='text-end align-middle'>
                              <b class='" . $amountClass . "'>" . $prefix . " " . number_format($f4) . "</b><br>
                              " . $statusNya . "
                            </td>";
                      echo "</tr>";
                    }
                    
                    if (count($data['transaksi_list']) == 0) {
                      echo "<tr><td colspan='3' class='text-center text-muted py-4'>Belum ada transaksi</td></tr>";
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Pengeluaran -->
<div class="modal" id="modalPengeluaran" tabindex="-1" aria-labelledby="modalPengeluaranLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="modalPengeluaranLabel"><i class="fas fa-minus-circle me-2"></i>Pengeluaran Kas Besar</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="formPengeluaran" action="<?= URL::BASE_URL; ?>Kas_Besar/insert_pengeluaran" method="POST">
          <div class="mb-3">
            <label class="form-label fw-bold">Saldo Saat Ini</label>
            <input type="text" class="form-control text-center text-bold saldoKas bg-light" readonly>
          </div>
          <div class="mb-3" id="jenisKeluarBesar">
            <label class="form-label fw-bold">Jenis Pengeluaran</label>
            <select name="f1a" class="form-control form-control-sm jenisKeluarBesar" style="width: 100%;" required>
              <option value="" selected disabled>-- Pilih Jenis --</option>
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
          <div class="mb-3">
            <label class="form-label fw-bold">Metode</label>
            <select name="metode" class="form-control form-control-sm" required>
              <?php foreach ($this->dMetodeMutasi as $a) {
                if ($a['id_metode_mutasi'] <> 1) { ?>
                  <option value="<?= $a['id_metode_mutasi'] ?>"><?= $a['metode_mutasi'] ?></option>
              <?php }
              } ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Jumlah Rp</label>
            <input type="number" name="f2" min="1000" class="form-control jumlahTarik" placeholder="0" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Keterangan</label>
            <input type="text" name="f1" class="form-control" placeholder="Keterangan tambahan">
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-danger"><i class="fas fa-save me-2"></i>Simpan Pengeluaran</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tambah Modal -->
<div class="modal" id="modalModal" tabindex="-1" aria-labelledby="modalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="modalModalLabel"><i class="fas fa-plus-circle me-2"></i>Tambah Modal</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="formModal" action="<?= URL::BASE_URL; ?>Kas_Besar/insert_modal" method="POST">
          <div class="mb-3">
            <label class="form-label fw-bold">Saldo Saat Ini</label>
            <input type="text" class="form-control text-center text-bold saldoKas bg-light" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Metode</label>
            <select name="metode" class="form-control form-control-sm" required>
              <?php foreach ($this->dMetodeMutasi as $a) {
                if ($a['id_metode_mutasi'] <> 1) { ?>
                  <option value="<?= $a['id_metode_mutasi'] ?>"><?= $a['metode_mutasi'] ?></option>
              <?php }
              } ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Jumlah Rp</label>
            <input type="number" name="f2" min="1000" class="form-control" placeholder="0" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Keterangan</label>
            <input type="text" name="f1" class="form-control" placeholder="Contoh: Modal dari Owner" required>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Simpan Modal</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
  var saldoKas = <?= $kas ?>;

  $(document).ready(function() {
    $('input.saldoKas').val(formatter.format(saldoKas));
    
    // Initialize selectize for jenis pengeluaran
    $('select.jenisKeluarBesar').selectize({
      sortField: 'text'
    });
  });

  $("form").on("submit", function(e) {
    e.preventDefault();
    var form = $(this);
    $.ajax({
      url: form.attr('action'),
      data: form.serialize(),
      type: form.attr("method"),
      success: function(res) {
        if (res == 1 || res == '') {
          location.reload(true);
        } else {
          alert('Error: ' + res);
        }
      },
      error: function() {
        alert('Terjadi kesalahan');
      }
    });
  });

  $("input.jumlahTarik").on("keyup change", function() {
    if ($(this).val() > 0) {
      var potong = $(this).val();
      var sisaKas = parseInt(saldoKas) - parseInt(potong);
      $('input.saldoKas').val(formatter.format(sisaKas));
      if (sisaKas < 0) {
        $('input.saldoKas').addClass('text-danger');
      } else {
        $('input.saldoKas').removeClass('text-danger');
      }
    } else {
      $('input.saldoKas').val(formatter.format(saldoKas));
      $('input.saldoKas').removeClass('text-danger');
    }
  });

  $("button.dropdown-toggle").focus(function() {
    $('input.saldoKas').val(formatter.format(saldoKas));
    $('input.saldoKas').removeClass('text-danger');
  });

  var formatter = new Intl.NumberFormat('en-ID', {
    style: 'currency',
    currency: 'IDR',
  });
</script>
