<?php
$book = $_SESSION[URL::SESSID]['user']['book'];
$selisih_book = date("Y") - URL::DB_START;
$long_char = strlen($selisih_book);
?>

<!-- Kartu Saldo Tokopay -->
<div class="mb-3">
  <div class="card border-0 shadow-sm" style="max-width: 300px;">
    <div class="card-body p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0 fw-bold"><i class="fas fa-wallet text-primary me-2"></i>QRIS Tokopay</h6>
        <div>
            <button class="btn btn-sm btn-outline-danger me-1" data-bs-toggle="modal" data-bs-target="#modalTarikSaldo" title="Tarik Saldo">
              Tarik Saldo
            </button>
            <button class="btn btn-sm btn-outline-primary" id="btnRefreshSaldo" onclick="loadTokopayBalance()">
              <i class="fas fa-sync-alt"></i>
            </button>
        </div>
      </div>
      <div id="tokopayBalance" class="mt-4">
        <div class="text-center py-1">
          <span class="text-muted small">Klik <i class="fas fa-sync-alt"></i> untuk cek saldo</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tarik Saldo -->
<div class="modal fade" id="modalTarikSaldo" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Tarik Saldo Tokopay</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label small">Nominal Penarikan</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text">Rp</span>
                <input type="number" id="nominalTarik" class="form-control" placeholder="0" min="10000">
            </div>
            <div class="form-text small text-muted mt-3">Minimal Rp 10.000</div>
            <div class="form-text small text-muted">Penarikan dana ke SEABANK</div>
        </div>
        <div id="wdStatus" class="small text-center mb-2"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger btn-sm" id="btnProsesTarik">
            <i class="fas fa-paper-plane me-1"></i>Proses
        </button>
      </div>
    </div>
  </div>
</div>

<?php
if (count($data['cek']) == 0) { ?>
  <div class="text-center py-5">
    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
    <h5 class="text-muted">Semua transaksi sudah dikonfirmasi</h5>
  </div>
<?php } else { ?>

<div class="list-group mb-5">
  <?php foreach ($data['cek'] as $a) {
    $id = $a['ref_finance'];
    $f1 = substr($a['ref_finance'], $long_char + 2, 2) . "-" . substr($a['ref_finance'], $long_char, 2);
    $f2 = $a['note'];
    $f3 = $a['id_user'];
    $f4 = $a['total'];
    $f17 = $a['id_client'];
    $jenisT = $a['jenis_transaksi'];

    $karyawan = '';
    foreach ($this->userMerge as $c) {
      if ($c['id_user'] == $f3) {
        $karyawan = $c['nama_user'];
        break;
      }
    }

    $pelanggan = $f17;
    $jenis_bill = '';
    switch ($jenisT) {
      case 1:
        $jenis_bill = "Laundry";
        if(isset($this->pelanggan[$f17])) $pelanggan = $this->pelanggan[$f17]['nama_pelanggan'];
        break;
      case 3:
        $jenis_bill = "Member";
        if(isset($this->pelanggan[$f17])) $pelanggan = $this->pelanggan[$f17]['nama_pelanggan'];
        break;
      case 5:
        $jenis_bill = "Kasbon";
        if(isset($this->user[$f17])) $pelanggan = $this->user[$f17]['nama_user'];
        break;
      case 6:
        $jenis_bill = "Deposit";
        if(isset($this->pelanggan[$f17])) $pelanggan = $this->pelanggan[$f17]['nama_pelanggan'];
        break;
    }
  ?>
  <div class="list-group-item list-group-item-action px-3 py-2">
    <div class="d-flex justify-content-between align-items-center">
      <!-- Left: Info -->
      <div class="flex-grow-1">
        <a href="<?= URL::BASE_URL ?>I/i/<?= $f17 ?>" target="_blank" class="text-decoration-none">
          <strong class="text-dark"><?= strtoupper($pelanggan) ?></strong>
          <i class="fas fa-external-link-alt small text-muted ms-1"></i>
        </a>
        <div class="small text-muted">
          <?= $jenis_bill ?> • <?= strtoupper($f2) ?> • <?= $karyawan ?>
        </div>
      </div>
      
      <!-- Right: Amount & Actions -->
      <div class="d-flex align-items-center gap-2">
        <span class="fw-bold text-primary me-2"><?= number_format($f4) ?></span>
        <button class="btn btn-outline-danger btn-sm nTolak" data-id="<?= $id ?>" data-nama="<?= strtoupper($pelanggan) ?>" data-target="<?= URL::BASE_URL ?>NonTunai/operasi/4">
          <i class="fas fa-times"></i>
        </button>
        <button class="btn btn-success btn-sm nTerima" data-id="<?= $id ?>" data-target="<?= URL::BASE_URL ?>NonTunai/operasi/3">
          <i class="fas fa-check"></i>
        </button>
      </div>
    </div>
  </div>
  <?php } ?>
</div>

<?php } ?>

<!-- Modal Konfirmasi Tolak -->
<div class="modal fade" id="modalTolak" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Tolak</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-3">
        <p class="mb-0">Yakin ingin menolak transaksi dari <strong id="namaTolak"></strong>?</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger btn-sm" id="btnKonfirmasiTolak">
          <i class="fas fa-times me-1"></i>Ya, Tolak
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  var tolakData = { id: '', target: '', btn: null };

  // Tombol Tolak - tampilkan modal konfirmasi
  $(".nTolak").on("click", function(e) {
    e.preventDefault();
    tolakData.id = $(this).attr('data-id');
    tolakData.target = $(this).attr('data-target');
    tolakData.btn = $(this);
    $('#namaTolak').text($(this).attr('data-nama'));
    $('#modalTolak').modal('show');
  });

  // Konfirmasi Tolak
  $("#btnKonfirmasiTolak").on("click", function() {
    var btn = tolakData.btn;
    $('#modalTolak').modal('hide');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
      url: tolakData.target,
      data: { id: tolakData.id },
      type: "POST",
      success: function(response) {
        btn.closest('.list-group-item').fadeOut(200, function() {
          $(this).remove();
          if ($('.nTolak').length === 0 && $('.nTerima').length === 0) {
            location.reload(true);
          }
        });
      },
      error: function() {
        btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
      }
    });
  });

  // Tombol Terima - langsung eksekusi
  $(".nTerima").on("click", function(e) {
    e.preventDefault();
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
      url: $(this).attr("data-target"),
      data: { id: $(this).attr('data-id') },
      type: "POST",
      success: function(response) {
        btn.closest('.list-group-item').fadeOut(200, function() {
          $(this).remove();
          if ($('.nTolak').length === 0 && $('.nTerima').length === 0) {
            location.reload(true);
          }
        });
      },
      error: function() {
        btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
      }
    });
  });



  // Handler Tarik Saldo
  $('#btnProsesTarik').on('click', function() {
    var nominal = $('#nominalTarik').val();
    if (nominal < 10000) {
        $('#wdStatus').html('<span class="text-danger">Minimal Rp 10.000</span>');
        return;
    }

    var btn = $(this);
    var oldHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $('#wdStatus').html('');

    $.ajax({
      url: '<?= URL::BASE_URL ?>NonTunai/withdraw',
      method: 'POST',
      data: { nominal: nominal },
      dataType: 'json',
      success: function(res) {
        if (res.status == 'success' || (res.status === true)) {
           $('#wdStatus').html('<span class="text-success"><i class="fas fa-check-circle"></i> Berhasil!</span>');
           setTimeout(function() {
             $('#modalTarikSaldo').modal('hide');
             $('#nominalTarik').val('');
             $('#wdStatus').html('');
             loadTokopayBalance(); // Refresh saldo
           }, 1500);
        } else {
           $('#wdStatus').html('<span class="text-danger">' + (res.message || 'Gagal menarik saldo') + '</span>');
        }
      },
      error: function() {
         $('#wdStatus').html('<span class="text-danger">Koneksi Error</span>');
      },
      complete: function() {
         btn.prop('disabled', false).html(oldHtml);
      }
    });
  });

  function loadTokopayBalance() {
    $('#btnRefreshSaldo').prop('disabled', true).find('i').addClass('fa-spin');
    
    $.ajax({
      url: '<?= URL::BASE_URL ?>NonTunai/tokopayBalance',
      type: 'GET',
      dataType: 'json',
      success: function(res) {
        if ((res.status == 1 || res.rc == 200) && res.data) {
          var total = parseInt(res.data.saldo_tersedia) + parseInt(res.data.saldo_tertahan);
          var html = `
            <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
              <span class="text-dark small">Total:</span>
              <span class="fw-bold text-dark fs-6">${formatNumber(total)}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted small">Tersedia:</span>
              <span class="fw-bold text-success">${formatNumber(res.data.saldo_tersedia)}</span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-muted small">Tertahan:</span>
              <span class="fw-bold text-warning">${formatNumber(res.data.saldo_tertahan)}</span>
            </div>
          `;
          $('#tokopayBalance').html(html);
        } else {
          $('#tokopayBalance').html('<span class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i>Gagal</span>');
        }
      },
      error: function() {
        $('#tokopayBalance').html('<span class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i>Error</span>');
      },
      complete: function() {
        $('#btnRefreshSaldo').prop('disabled', false).find('i').removeClass('fa-spin');
      }
    });
  }

  function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
  }
</script>