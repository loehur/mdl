<?php
$pelangganById = is_array($data['pelangganById'] ?? null) ? $data['pelangganById'] : [];
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
    $idCabang = (int) ($a['id_cabang'] ?? 0);
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
    if ($karyawan === '' && isset($this->userAll[$f3]['nama_user'])) {
      $karyawan = (string) $this->userAll[$f3]['nama_user'];
    }

    $pelanggan = $f17;
    $jenis_bill = '';
    $hp = '';
    $pRow = null;
    if (isset($pelangganById[$f17]) && is_array($pelangganById[$f17])) {
      $pRow = $pelangganById[$f17];
    } elseif (isset($this->pelanggan[$f17]) && is_array($this->pelanggan[$f17])) {
      $pRow = $this->pelanggan[$f17];
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
        if (isset($this->userAll[$f17]['nama_user'])) {
          $pelanggan = $this->userAll[$f17]['nama_user'];
        } elseif (isset($this->user[$f17]['nama_user'])) {
          $pelanggan = $this->user[$f17]['nama_user'];
        }
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
  <div class="aa-card aa-card--pending nt-row" data-id-cabang="<?= $idCabang ?>">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div class="flex-grow-1" style="min-width:0">
        <?php if ($idCabang > 0) { ?>
          <span class="aa-cabang-badge"><?= htmlspecialchars($this->cabangKodeById($idCabang), ENT_QUOTES, 'UTF-8') ?></span>
        <?php } ?>
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
        <button class="aa-btn aa-btn--danger nTolak" data-id="<?= $id ?>" data-id-cabang="<?= $idCabang ?>" data-nama="<?= strtoupper($pelanggan) ?>" data-target="<?= URL::BASE_URL ?>NonTunai/operasi/4">
          <i class="fas fa-times"></i>
        </button>
        <?php
          $isBca = strtoupper(trim((string) $f2)) === 'BCA';
          $isQrisStatic = strtoupper(trim((string) $f2)) === 'QRIS'
            && trim((string) ($a['payment_trx_id'] ?? '')) === '';
          $needsBind = $isBca || $isQrisStatic;
          $bindMode = $isBca ? 'bca' : ($isQrisStatic ? 'qris' : '');
          $bindListUrl = $isBca
            ? (URL::BASE_URL . 'NonTunai/mutasiList')
            : ($isQrisStatic ? (URL::BASE_URL . 'NonTunai/qrisList') : '');
        ?>
        <button class="aa-btn aa-btn--ok nTerima"
          data-id="<?= $id ?>"
          data-id-cabang="<?= $idCabang ?>"
          data-nama="<?= strtoupper($pelanggan) ?>"
          data-note="<?= htmlspecialchars(strtoupper((string) $f2), ENT_QUOTES, 'UTF-8') ?>"
          data-nominal="<?= (float) $f4 ?>"
          data-bind="<?= $needsBind ? '1' : '0' ?>"
          data-bind-mode="<?= htmlspecialchars($bindMode, ENT_QUOTES, 'UTF-8') ?>"
          data-target="<?= URL::BASE_URL ?>NonTunai/operasi/3"
          data-list-url="<?= htmlspecialchars($bindListUrl, ENT_QUOTES, 'UTF-8') ?>">
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
  .nt-bca-offcanvas {
    width: min(420px, 96vw);
  }
  .nt-bca-offcanvas .offcanvas-header,
  .nt-bca-offcanvas .offcanvas-body,
  .nt-bca-offcanvas .nt-bca-item {
    border-radius: 0 !important;
  }
  .nt-bca-item {
    border: 1px solid #cbd5e1;
    background: #fff;
    padding: 10px 12px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
  }
  .nt-bca-item:hover {
    border-color: #2563eb;
    background: #eff6ff;
  }
  .nt-bca-item.is-match {
    border-color: #16a34a;
    background: linear-gradient(180deg, #f0fdf4, #fff);
  }
  .nt-bca-item.is-selected {
    border-color: #1d4ed8;
    box-shadow: inset 0 0 0 1px #1d4ed8;
    background: #eff6ff;
  }
  .nt-bca-item__row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    align-items: flex-start;
  }
  .nt-bca-item__amt {
    font-weight: 800;
    white-space: nowrap;
    color: #0f172a;
  }
  .nt-bca-item__date {
    font-size: 0.78rem;
    color: #64748b;
  }
  .nt-bca-item__ket {
    font-size: 0.75rem;
    color: #334155;
    margin-top: 6px;
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 4.5em;
    overflow: hidden;
  }
  .nt-bca-badge-match {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 6px;
    border: 1px solid #86efac;
    background: #dcfce7;
    color: #15803d;
    margin-left: 6px;
  }
  .nt-bca-badge-pend {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 6px;
    border: 1px solid #fcd34d;
    background: #fef3c7;
    color: #92400e;
    margin-left: 6px;
  }
  .nt-bca-item.is-pend {
    border-color: #f59e0b;
    background: linear-gradient(180deg, #fffbeb, #fff);
  }
  .nt-bca-badge-selisih {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 6px;
    border: 1px solid #93c5fd;
    background: #dbeafe;
    color: #1d4ed8;
    margin-left: 6px;
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

<!-- Offcanvas pilih mutasi BCA / transaksi QRIS merchant -->
<div class="offcanvas offcanvas-end nt-bca-offcanvas" tabindex="-1" id="offcanvasBcaMutasi" aria-labelledby="offcanvasBcaMutasiLabel">
  <div class="offcanvas-header border-bottom py-2">
    <div>
      <h6 class="offcanvas-title fw-bold mb-0" id="offcanvasBcaMutasiLabel">Pilih Mutasi</h6>
      <small class="text-muted" id="ntBcaOffcanvasSub"></small>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column">
    <div class="px-3 py-2 border-bottom bg-light small" id="ntBcaKasInfo"></div>
    <div class="flex-grow-1 overflow-auto p-2" id="ntBcaMutasiList">
      <div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>
    </div>
  </div>
</div>

<!-- Modal konfirmasi terima + bind BCA / QRIS -->
<div class="modal fade" id="modalTerimaBca" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title text-success"><i class="fas fa-link me-2"></i><span id="ntTerimaModalTitle">Konfirmasi Bind & Terima</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-3">
        <p class="mb-2">Yakin bind <span id="ntTerimaBindLabel">mutasi</span> berikut ke transaksi <strong id="ntTerimaNama"></strong>?</p>
        <div class="border p-2 mb-2 small bg-light" id="ntTerimaMutasiDetail"></div>
        <div class="small text-muted mb-0" id="ntTerimaNominalCompare"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success btn-sm" id="btnKonfirmasiTerimaBca">
          <i class="fas fa-check me-1"></i>Ya, Bind & Terima
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  var tolakData = { id: '', target: '', btn: null };
  var terimaBindData = {
    id: '',
    target: '',
    btn: null,
    nama: '',
    kasNominalFmt: '',
    bindMode: '',
    item: null
  };
  var ntBindItemCache = {};

  function ntBindLabels(mode) {
    if (mode === 'qris') {
      return {
        offcanvasTitle: 'Pilih Transaksi QRIS',
        offcanvasSub: '6 hari terakhir',
        bindLabel: 'transaksi QRIS',
        emptyHint: 'Belum ada transaksi QRIS yang cocok.',
        emptyTail: 'Pastikan data sudah di-sync ke bca_qris_transaksi.',
        loadFail: 'Gagal memuat daftar transaksi QRIS',
        okMsg: 'QRIS bind & dikonfirmasi',
        compareMutasi: 'QRIS'
      };
    }
    return {
      offcanvasTitle: 'Pilih Mutasi BCA',
      offcanvasSub: 'posted + PEND',
      bindLabel: 'mutasi',
      emptyHint: 'Belum ada mutasi CR yang cocok.',
      emptyTail: 'Pastikan data sudah di-sync ke bca_mutasi (posted atau PEND).',
      loadFail: 'Gagal memuat daftar mutasi BCA',
      okMsg: 'BCA bind & dikonfirmasi',
      compareMutasi: 'Mutasi'
    };
  }

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

  $('body > #modalInvoicePelanggan').remove();
  $('body > #modalTolak').remove();
  $('body > #modalTerimaBca').remove();
  $('body > #offcanvasBcaMutasi').remove();
  $('#modalInvoicePelanggan').appendTo('body');
  $('#modalTolak').appendTo('body');
  $('#modalTerimaBca').appendTo('body');
  $('#offcanvasBcaMutasi').appendTo('body');

  function ntRemoveRow(btn) {
    btn.closest('.nt-row, .list-group-item').fadeOut(200, function() {
      $(this).remove();
      if ($('.nTolak').length === 0 && $('.nTerima').length === 0) {
        location.reload(true);
      }
    });
  }

  function ntPostOperasi(target, id, extra, btn, okMsg) {
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    var payload = $.extend({
      id: id,
      id_cabang: btn.attr('data-id-cabang') || btn.closest('.nt-row').attr('data-id-cabang') || ''
    }, extra || {});
    $.ajax({
      url: target,
      data: payload,
      type: 'POST',
      success: function(response) {
        if (String(response).trim() !== '0') {
          ntToast(response || 'Gagal memproses transaksi', 'warn');
          btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
          return;
        }
        ntToast(okMsg || 'Transaksi dikonfirmasi', 'ok');
        ntRemoveRow(btn);
      },
      error: function() {
        ntToast('Gagal memproses transaksi', 'error');
        btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
      }
    });
  }

  function ntRenderBindList(items, kasNominalFmt, range, toleranceFmt, mode) {
    var $list = $('#ntBcaMutasiList');
    var labels = ntBindLabels(mode);
    ntBindItemCache = {};
    toleranceFmt = toleranceFmt || '10.000';
    if (!items || !items.length) {
      $list.html('<div class="text-center text-muted py-4 px-2"><i class="fas fa-inbox d-block mb-2"></i>'
        + labels.emptyHint + '<br><small>' + labels.emptyTail + '</small></div>');
      return;
    }
    var html = '';
    items.forEach(function(it) {
      ntBindItemCache[String(it.id)] = it;
      var cls = 'nt-bca-item';
      if (it.is_pend) cls += ' is-pend';
      if (it.nominal_match) cls += ' is-match';
      var badge = it.nominal_match ? '<span class="nt-bca-badge-match">NOMINAL COCOK</span>' : '';
      if (!it.nominal_match && it.selisih > 0) {
        badge += '<span class="nt-bca-badge-selisih">± Rp ' + (it.selisih_fmt || it.selisih) + '</span>';
      }
      if (it.is_pend) badge += '<span class="nt-bca-badge-pend">PEND</span>';
      var dateLabel = it.date_label || it.tanggal || it.tanggal_iso || '-';
      if (it.is_pend && it.created_at) {
        var pendDate = String(it.created_at).substring(0, 10);
        if (pendDate) dateLabel += ' · sync ' + pendDate;
      }
      html += '<div class="' + cls + '" data-bind-id="' + it.id + '">';
      html += '<div class="nt-bca-item__row"><div><span class="nt-bca-item__date">' + dateLabel + badge + '</span></div>';
      html += '<div class="nt-bca-item__amt">Rp ' + (it.nominal_fmt || it.nominal) + '</div></div>';
      if (it.rrn) {
        html += '<div class="small text-muted">RRN: ' + $('<div>').text(it.rrn).html() + '</div>';
      }
      html += '<div class="nt-bca-item__ket">' + $('<div>').text(it.keterangan || '').html() + '</div>';
      html += '</div>';
    });
    $list.html(html);
    if (range && range.start && range.end) {
      var sub = range.start + ' s/d ' + range.end;
      if (range.pend_start) sub += ' · PEND dari ' + range.pend_start;
      $('#ntBcaOffcanvasSub').text(sub);
    } else {
      $('#ntBcaOffcanvasSub').text(labels.offcanvasSub);
    }
  }

  function ntOpenBindOffcanvas($btn) {
    var mode = String($btn.attr('data-bind-mode') || 'bca');
    var labels = ntBindLabels(mode);
    terimaBindData.id = $btn.attr('data-id');
    terimaBindData.id_cabang = $btn.attr('data-id-cabang') || $btn.closest('.nt-row').attr('data-id-cabang') || '';
    terimaBindData.target = $btn.attr('data-target');
    terimaBindData.btn = $btn;
    terimaBindData.nama = $btn.attr('data-nama') || '';
    terimaBindData.bindMode = mode;
    terimaBindData.item = null;

    $('#offcanvasBcaMutasiLabel').text(labels.offcanvasTitle);
    $('#ntBcaOffcanvasSub').text(labels.offcanvasSub);
    $('#ntBcaKasInfo').html(
      '<strong>' + terimaBindData.nama + '</strong> · Ref #' + terimaBindData.id +
      '<br>Nominal kas: <strong>Rp ' + Number($btn.attr('data-nominal') || 0).toLocaleString('id-ID') + '</strong>'
    );
    $('#ntBcaMutasiList').html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>');

    var ocEl = document.getElementById('offcanvasBcaMutasi');
    if (ocEl && window.bootstrap && bootstrap.Offcanvas) {
      bootstrap.Offcanvas.getOrCreateInstance(ocEl).show();
    }

    $.ajax({
      url: $btn.attr('data-list-url'),
      type: 'POST',
      data: { id: terimaBindData.id, id_cabang: terimaBindData.id_cabang || '' },
      dataType: 'json',
      success: function(res) {
        if (!res || !res.ok) {
          $('#ntBcaMutasiList').html('<div class="text-danger small p-3">' + (res && res.message ? res.message : 'Gagal memuat data') + '</div>');
          return;
        }
        terimaBindData.kasNominalFmt = res.kas_nominal_fmt || '';
        ntRenderBindList(
          res.items || [],
          res.kas_nominal_fmt,
          res.range,
          res.nominal_tolerance_fmt || '10.000',
          mode
        );
      },
      error: function() {
        $('#ntBcaMutasiList').html('<div class="text-danger small p-3">' + labels.loadFail + '</div>');
      }
    });
  }

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
    tolakData.id_cabang = $(this).attr('data-id-cabang') || $(this).closest('.nt-row').attr('data-id-cabang') || '';
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
      data: { id: tolakData.id, id_cabang: tolakData.id_cabang || '' },
      type: "POST",
      success: function(response) {
        if (String(response).trim() !== '0') {
          ntToast(response || 'Gagal menolak transaksi', 'warn');
          btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
          return;
        }
        ntToast('Transaksi ditolak', 'ok');
        ntRemoveRow(btn);
      },
      error: function() {
        ntToast('Gagal menolak transaksi', 'error');
        btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
      }
    });
  });

  // Pilih item di offcanvas → modal konfirmasi
  $(document).on('click', '.nt-bca-item', function() {
    var bindId = String($(this).attr('data-bind-id') || '');
    var item = ntBindItemCache[bindId];
    if (!item) return;

    var labels = ntBindLabels(terimaBindData.bindMode || 'bca');
    terimaBindData.item = item;
    $('.nt-bca-item').removeClass('is-selected');
    $(this).addClass('is-selected');

    var ocEl = document.getElementById('offcanvasBcaMutasi');
    if (ocEl && window.bootstrap && bootstrap.Offcanvas) {
      bootstrap.Offcanvas.getInstance(ocEl)?.hide();
    }

    $('#ntTerimaModalTitle').text('Konfirmasi Bind & Terima');
    $('#ntTerimaBindLabel').text(labels.bindLabel);
    $('#ntTerimaNama').text(terimaBindData.nama || '-');
    var warn = '';
    if (!item.nominal_match && item.selisih > 0) {
      warn = '<span class="text-info">Selisih nominal Rp ' + (item.selisih_fmt || item.selisih) + '</span><br>';
    }
    var dateDetail = item.date_label || item.tanggal || item.tanggal_iso || '-';
    if (item.is_pend && item.created_at) {
      var pendSync = String(item.created_at).substring(0, 10);
      if (pendSync) dateDetail += ' (sync ' + pendSync + ')';
    }
    var detailHtml = warn +
      '<div><strong>Tanggal:</strong> ' + dateDetail + (item.is_pend ? ' <span class="nt-bca-badge-pend">PEND</span>' : '') + '</div>' +
      '<div><strong>Nominal:</strong> Rp ' + (item.nominal_fmt || item.nominal) + '</div>';
    if (item.rrn) {
      detailHtml += '<div><strong>RRN:</strong> ' + $('<div>').text(item.rrn).html() + '</div>';
    }
    detailHtml += '<div class="mt-1"><strong>Keterangan:</strong><br>' + $('<div>').text(item.keterangan_full || item.keterangan || '').html().replace(/\n/g, '<br>') + '</div>';
    $('#ntTerimaMutasiDetail').html(detailHtml);
    $('#ntTerimaNominalCompare').html(
      'Kas: Rp ' + (terimaBindData.kasNominalFmt || Number(terimaBindData.btn.attr('data-nominal') || 0).toLocaleString('id-ID')) +
      ' · ' + labels.compareMutasi + ': Rp ' + (item.nominal_fmt || item.nominal) +
      (item.selisih > 0 ? ' · selisih Rp ' + (item.selisih_fmt || item.selisih) : '')
    );
    $('#modalTerimaBca').modal('show');
  });

  $('#btnKonfirmasiTerimaBca').on('click', function() {
    if (!terimaBindData.item || !terimaBindData.item.id) {
      ntToast('Pilih transaksi terlebih dahulu', 'warn');
      return;
    }
    var labels = ntBindLabels(terimaBindData.bindMode || 'bca');
    var extra = terimaBindData.bindMode === 'qris'
      ? { qris_id: terimaBindData.item.id }
      : { mutasi_id: terimaBindData.item.id };
    $('#modalTerimaBca').modal('hide');
    var btn = terimaBindData.btn;
    ntPostOperasi(
      terimaBindData.target,
      terimaBindData.id,
      extra,
      btn,
      labels.okMsg
    );
  });

  // Tombol Terima
  $(document).on("click", ".nTerima", function(e) {
    e.preventDefault();
    var btn = $(this);
    if (String(btn.attr('data-bind')) === '1') {
      ntOpenBindOffcanvas(btn);
      return;
    }
    ntPostOperasi(btn.attr('data-target'), btn.attr('data-id'), {}, btn, 'Transaksi dikonfirmasi');
  });
</script>