<?php
if (count($data['cek']) == 0) { ?>
  <div class="aa-empty">
    <i class="fas fa-check-circle"></i>
    Semua transaksi non-tunai sudah dikonfirmasi
  </div>
<?php } else { ?>

<div class="aa-section-title">Menunggu konfirmasi</div>
<div class="aa-grid" style="grid-template-columns: 1fr;">
  <?php foreach ($data['cek'] as $a) {
    $id = $a['ref_finance'];
    $f1 = $a['ref_finance'];
    $f2 = $a['note'];
    $f3 = $a['id_user'];
    $f4 = $a['total'];
    $f17 = $a['id_client'];
    $jenisT = $a['jenis_transaksi'];
    $refTransaksi = $a['ref_transaksi'] ?? '';
    $tglBayar = '';
    if (!empty($a['insertTime'])) {
      $tglBayar = date('d/m/Y H:i', strtotime($a['insertTime']));
    }

    $karyawan = '';
    foreach ($this->userMerge as $c) {
      if ($c['id_user'] == $f3) {
        $karyawan = $c['nama_user'];
        break;
      }
    }

    $pelanggan = $f17;
    $jenis_bill = '';
    $hp = '';
    $pRow = null;
    if (isset($this->pelanggan[$f17]) && is_array($this->pelanggan[$f17])) {
      $pRow = $this->pelanggan[$f17];
    } elseif (isset($this->pelangganLaundry[$f17]) && is_array($this->pelangganLaundry[$f17])) {
      $pRow = $this->pelangganLaundry[$f17];
    }

    switch ($jenisT) {
      case 1:
        $jenis_bill = "Laundry";
        break;
      case 3:
        $jenis_bill = "Member";
        break;
      case 5:
        $jenis_bill = "Kasbon";
        if (isset($this->user[$f17])) $pelanggan = $this->user[$f17]['nama_user'];
        $pRow = null;
        break;
      case 6:
        $jenis_bill = "Deposit";
        break;
      case 7:
        $jenis_bill = "Jualan";
        $pelanggan = "Umum";
        $pRow = null;
        break;
    }

    if ($pRow) {
      $pelanggan = $pRow['nama_pelanggan'] ?? $pelanggan;
      $hp = trim((string) ($pRow['nomor_pelanggan'] ?? ''));
    }

    $invoiceUrl = URL::BASE_URL . 'I/' . $f17;
    $invoiceTitle = strtoupper($pelanggan);
    if ((int) $jenisT === 7 && $refTransaksi !== '') {
      $invoiceUrl = URL::BASE_URL . 'Sales/preview_nota/' . rawurlencode($refTransaksi);
      $invoiceTitle = 'Jualan #' . $refTransaksi;
    }
  ?>
  <div class="aa-card aa-card--pending nt-row">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div class="flex-grow-1" style="min-width:0">
        <a href="#" class="text-decoration-none nt-invoice-link" data-invoice-url="<?= htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8') ?>" data-invoice-title="<?= htmlspecialchars($invoiceTitle, ENT_QUOTES, 'UTF-8') ?>">
          <div class="aa-card__title" style="margin:0"><?= strtoupper($pelanggan) ?> <i class="fas fa-expand-alt small" title="Lihat tagihan"></i></div>
        </a>
        <div class="aa-card__meta" style="margin:4px 0 0">
          <?php if ($tglBayar !== '') { ?><span class="text-nowrap"><i class="far fa-clock me-1"></i><?= $tglBayar ?></span> · <?php } ?><?= $jenis_bill ?> · <?= strtoupper($f2) ?> · <?= $karyawan ?>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="aa-card__amount" style="margin:0"><?= number_format($f4) ?></span>
        <?php if ($hp !== '') { ?>
        <button type="button"
          class="nt-chat-btn nChat"
          title="Riwayat Chat"
          aria-label="Riwayat Chat"
          data-hp="<?= htmlspecialchars($hp, ENT_QUOTES, 'UTF-8') ?>"
          data-nama="<?= htmlspecialchars(strtoupper((string) $pelanggan), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fas fa-comments"></i>
        </button>
        <?php } ?>
        <button class="aa-btn aa-btn--danger nTolak" data-id="<?= $id ?>" data-nama="<?= strtoupper($pelanggan) ?>" data-target="<?= URL::BASE_URL ?>NonTunai/operasi/4">
          <i class="fas fa-times"></i>
        </button>
        <button class="aa-btn aa-btn--ok nTerima" data-id="<?= $id ?>" data-target="<?= URL::BASE_URL ?>NonTunai/operasi/3">
          <i class="fas fa-check"></i>
        </button>
      </div>
    </div>
  </div>
  <?php } ?>
</div>

<?php } ?>

<!-- Lebar mengikuti nota (~640px). Tinggi ~85vh -->
<style>
  .nt-chat-btn {
    box-sizing: border-box;
    width: 31px;
    height: 31px;
    min-width: 31px;
    padding: 0;
    border: 1px solid #93c5fd;
    border-radius: 0;
    background: linear-gradient(180deg, #eff6ff, #fff);
    color: #1d4ed8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
  }
  .nt-chat-btn:hover:not(:disabled) {
    background: linear-gradient(180deg, #2563eb, #1d4ed8);
    border-color: #1d4ed8;
    color: #fff;
  }
  .nt-chat-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
  #modalInvoicePelanggan .nt-modal-tagihan {
    max-width: min(640px, 96vw);
    width: 100%;
    margin-left: auto;
    margin-right: auto;
  }
  /* Wajib height + flex: kalau cuma max-height, iframe height:100% jadi 0 (ancur) */
  #modalInvoicePelanggan .modal-content {
    height: 85vh;
    max-height: 85dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  #modalInvoicePelanggan .nt-modal-iframe-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
  }
  #modalInvoicePelanggan #iframeInvoicePelanggan {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 240px;
    border: 0;
  }
</style>
<div class="modal fade" id="modalInvoicePelanggan" tabindex="-1" aria-labelledby="modalInvoicePelangganLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered nt-modal-tagihan">
    <div class="modal-content">
      <div class="modal-header flex-shrink-0 py-2 border-bottom">
        <h5 class="modal-title" id="modalInvoicePelangganLabel">Tagihan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-0 nt-modal-iframe-wrap d-flex flex-column flex-grow-1">
        <iframe id="iframeInvoicePelanggan" title="Tagihan pelanggan" src="about:blank"></iframe>
      </div>
    </div>
  </div>
</div>

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

  function ntToast(msg, type) {
    msg = String(msg || '').trim();
    if (!msg) return;
    if (window.MdlToast) {
      if (type === 'ok' || type === 'success') return MdlToast.ok(msg);
      if (type === 'error' || type === 'danger') return MdlToast.error(msg);
      if (type === 'warn' || type === 'warning') return MdlToast.warn(msg);
      return MdlToast.info(msg);
    }
  }

  // Modal di dalam #load (kolom sempit) membuat fixed terikat ke induk → tampil mepet kiri.
  // Pindahkan ke body agar lebar/posisi benar dan terpusat.
  $('body > #modalInvoicePelanggan').remove();
  $('body > #modalTolak').remove();
  $('#modalInvoicePelanggan').appendTo('body');
  $('#modalTolak').appendTo('body');

  $(document).on("click", ".nt-invoice-link", function (e) {
    e.preventDefault();
    var url = $(this).data("invoice-url");
    var title = $(this).data("invoice-title") || "Tagihan";
    $("#modalInvoicePelangganLabel").text(title);
    var $iframe = $("#iframeInvoicePelanggan");
    $iframe.attr("src", "about:blank");
    var modalEl = document.getElementById("modalInvoicePelanggan");
    if (modalEl && window.bootstrap && bootstrap.Modal) {
      $iframe.attr("src", url);
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  });

  $("#modalInvoicePelanggan").on("hidden.bs.modal", function () {
    $("#iframeInvoicePelanggan").attr("src", "about:blank");
  });

  $(".nChat").on("click", function(e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.prop('disabled')) return;
    var hp = String($btn.attr('data-hp') || '').trim();
    var nama = String($btn.attr('data-nama') || 'Pelanggan').trim();
    if (!hp) {
      ntToast('Nomor pelanggan tidak tersedia', 'warn');
      return;
    }
    if (window.MdlChatHistory && typeof MdlChatHistory.open === 'function') {
      MdlChatHistory.open(hp, nama, { showCloseCase: false });
    } else {
      ntToast('Modal chat belum siap', 'error');
    }
  });

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
        if (String(response).trim() !== '0') {
          ntToast(response || 'Gagal menolak transaksi', 'warn');
          btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
          return;
        }
        ntToast('Transaksi ditolak', 'ok');
        btn.closest('.nt-row, .list-group-item').fadeOut(200, function() {
          $(this).remove();
          if ($('.nTolak').length === 0 && $('.nTerima').length === 0) {
            location.reload(true);
          }
        });
      },
      error: function() {
        ntToast('Gagal menolak transaksi', 'error');
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
        if (String(response).trim() !== '0') {
          ntToast(response || 'Gagal konfirmasi transaksi', 'warn');
          btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
          return;
        }
        ntToast('Transaksi dikonfirmasi', 'ok');
        btn.closest('.nt-row, .list-group-item').fadeOut(200, function() {
          $(this).remove();
          if ($('.nTolak').length === 0 && $('.nTerima').length === 0) {
            location.reload(true);
          }
        });
      },
      error: function() {
        ntToast('Gagal konfirmasi transaksi', 'error');
        btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
      }
    });
  });
</script>