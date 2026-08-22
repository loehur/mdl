<?php
$pending = is_array($data['list'] ?? null) ? $data['list'] : [];
?>
<?php if (count($pending) > 0) { ?>
  <div class="aa-section-title">Menunggu konfirmasi</div>
  <div class="aa-grid">
    <?php foreach ($pending as $a) {
      $id = (string) ($a['id_kas'] ?? '');
      $idAttr = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      $f1 = $a['insertTime'];
      $f2 = $a['note'];
      $f3 = $a['id_user'];
      $f4 = $a['jumlah'];
      $note = $a['note_primary'];
      $idCabang = (int) ($a['id_cabang'] ?? 0);
      $karyawan = '';
      foreach ($this->userMerge as $c) {
        if ($c['id_user'] == $f3) {
          $karyawan = $c['nama_user'];
        }
      }
      if ($karyawan === '' && isset($this->userAll[$f3]['nama_user'])) {
        $karyawan = (string) $this->userAll[$f3]['nama_user'];
      }
    ?>
      <div class="aa-card aa-card--pending" data-id-cabang="<?= $idCabang ?>">
        <?php if ($idCabang > 0) { ?>
          <span class="aa-cabang-badge"><?= htmlspecialchars($this->cabangKodeById($idCabang), ENT_QUOTES, 'UTF-8') ?></span>
        <?php } ?>
        <div class="aa-card__meta">#<?= $idAttr ?> · <?= htmlspecialchars($karyawan, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $f1, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__title"><?= htmlspecialchars(strtoupper((string) $note), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__meta"><?= htmlspecialchars(ucwords((string) $f2), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="aa-card__amount">Rp<?= number_format((float) $f4) ?></div>
        <div class="aa-actions">
          <span class="aa-btn aa-btn--danger nTunai" role="button" data-id="<?= $idAttr ?>" data-id-cabang="<?= $idCabang ?>" data-target="<?= URL::BASE_URL ?>Pengeluaran/operasi/4">Tolak</span>
          <div class="d-flex gap-2">
            <span class="aa-btn aa-btn--ghost nTunai nTunaiAnalisa" role="button"
              data-id="<?= $idAttr ?>"
              data-id-cabang="<?= $idCabang ?>"
              data-analisa-url="<?= URL::BASE_URL ?>Pengeluaran/analisaAi"
              data-pg-jenis="<?= htmlspecialchars(strtoupper((string) $note), ENT_QUOTES, 'UTF-8') ?>"
              data-pg-ket="<?= htmlspecialchars(ucwords((string) $f2), ENT_QUOTES, 'UTF-8') ?>"
              data-pg-jumlah="<?= number_format((float) $f4, 0, ',', '.') ?>"
              data-pg-kode="<?= htmlspecialchars($this->cabangKodeById($idCabang), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-history"></i> Riwayat</span>
            <span class="aa-btn aa-btn--ok nTunai nTunaiKonfirm" role="button"
              data-id="<?= $idAttr ?>"
              data-id-cabang="<?= $idCabang ?>"
              data-target="<?= URL::BASE_URL ?>Pengeluaran/operasi/3"
              data-pg-jenis="<?= htmlspecialchars(strtoupper((string) $note), ENT_QUOTES, 'UTF-8') ?>"
              data-pg-ket="<?= htmlspecialchars(ucwords((string) $f2), ENT_QUOTES, 'UTF-8') ?>"
              data-pg-jumlah="<?= number_format((float) $f4, 0, ',', '.') ?>"
              data-pg-kode="<?= htmlspecialchars($this->cabangKodeById($idCabang), ENT_QUOTES, 'UTF-8') ?>">Konfirmasi</span>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
<?php } else { ?>
  <div class="aa-empty" style="margin-bottom:14px"><i class="fas fa-check-circle"></i>Tidak ada pengeluaran pending</div>
<?php } ?>

<!-- Modal riwayat pengeluaran (informatif, terpisah dari konfirmasi) -->
<div class="modal fade" id="modalPengeluaranAi" tabindex="-1" aria-labelledby="modalPengeluaranAiLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:0;border:1px solid #cbd5e1;">
      <div class="modal-header py-2 border-bottom" style="background:linear-gradient(105deg,#1d4ed8,#2563eb);color:#fff;">
        <div>
          <h6 class="modal-title fw-bold mb-0" id="modalPengeluaranAiLabel"><i class="fas fa-history me-2"></i>Riwayat Pengeluaran</h6>
          <small id="pgAiSub" style="opacity:.9;">Memuat…</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-3">
        <div id="pgAiPending" class="border p-2 mb-3 small" style="background:#f8fafc;border-color:#cbd5e1 !important;"></div>
        <div id="pgAiLoading" class="text-center text-muted py-4">
          <i class="fas fa-spinner fa-spin fa-lg d-block mb-2"></i>
          Memuat riwayat 30 hari…
        </div>
        <div id="pgAiResult" class="d-none">
          <div class="fw-bold mb-2" style="font-size:.82rem;color:#1e40af;"><i class="fas fa-stream"></i> 3 pengeluaran terakhir</div>
          <div id="pgAiAnalysis" style="font-size:.88rem;line-height:1.55;color:#0f172a;"></div>
        </div>
        <div id="pgAiError" class="d-none alert alert-warning mb-0 py-2 small"></div>
      </div>
      <div class="modal-footer py-2 border-top">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal peringatan sebelum konfirmasi (tanpa AI) -->
<div class="modal fade" id="modalPengeluaranKonfirmasi" tabindex="-1" aria-labelledby="modalPengeluaranKonfirmasiLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:0;border:1px solid #cbd5e1;">
      <div class="modal-header py-2 border-bottom" style="background:linear-gradient(105deg,#15803d,#16a34a);color:#fff;">
        <h6 class="modal-title fw-bold mb-0" id="modalPengeluaranKonfirmasiLabel"><i class="fas fa-check-circle me-2"></i>Konfirmasi Pengeluaran</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-3">
        <div id="pgConfirmPending" class="border p-2 mb-3 small" style="background:#f8fafc;border-color:#cbd5e1 !important;"></div>
        <div class="alert alert-warning mb-0 py-2 small">
          <i class="fas fa-exclamation-triangle me-1"></i>
          <strong>Peringatan:</strong> Pengeluaran ini akan langsung dikonfirmasi. Pastikan jenis, nominal, dan keterangan sudah benar. Tindakan ini tidak dapat dibatalkan.
        </div>
      </div>
      <div class="modal-footer py-2 border-top">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success btn-sm" id="btnPgConfirmYa">
          <i class="fas fa-check me-1"></i>Ya, Konfirmasi
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var pgConfirmState = { btn: null };

  $('body > #modalPengeluaranAi').remove();
  $('body > #modalPengeluaranKonfirmasi').remove();
  $('#modalPengeluaranAi').appendTo('body');
  $('#modalPengeluaranKonfirmasi').appendTo('body');

  function pgAiEsc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function pgAiResetModal() {
    $('#pgAiLoading').removeClass('d-none');
    $('#pgAiResult').addClass('d-none');
    $('#pgAiError').addClass('d-none').text('');
    $('#pgAiAnalysis').html('');
  }

  function pgPendingHtml(p) {
    if (!p) return '';
    var html = '<div><strong>' + pgAiEsc(p.jenis_pengeluaran || '-') + '</strong></div>';
    html += '<div class="text-muted">' + pgAiEsc(p.kode_cabang || '-') + ' · #' + pgAiEsc(p.id_kas || '') + '</div>';
    html += '<div class="mt-1">' + pgAiEsc(p.keterangan || '-') + '</div>';
    html += '<div class="fw-bold mt-1">Rp ' + pgAiEsc(p.jumlah_fmt || '0') + '</div>';
    return html;
  }

  function pgPendingFromBtn($btn) {
    return {
      id_kas: $btn.attr('data-id') || '',
      kode_cabang: $btn.attr('data-pg-kode') || '-',
      jenis_pengeluaran: $btn.attr('data-pg-jenis') || '-',
      keterangan: $btn.attr('data-pg-ket') || '-',
      jumlah_fmt: $btn.attr('data-pg-jumlah') || '0'
    };
  }

  function pgAiShowPending(p) {
    if (!p) return;
    $('#pgAiPending').html(pgPendingHtml(p));
    var sub = 'Riwayat 30 hari · cabang ini + cabang lain';
    $('#pgAiSub').text(sub);
  }

  function pgAiShowPendingFromBtn($btn) {
    pgAiShowPending(pgPendingFromBtn($btn));
  }

  function pgAiSetAnalysis(text) {
    var raw = String(text == null ? '' : text);
    if (/<[a-z][\s\S]*>/i.test(raw)) {
      $('#pgAiAnalysis').html(raw);
      return;
    }
    $('#pgAiAnalysis').html(pgAiEsc(raw).replace(/\n/g, '<br>'));
  }

  function pgAiRenderResult(res) {
    $('#pgAiLoading').addClass('d-none');
    if (res && res.pending) {
      pgAiShowPending(res.pending);
    }
    if (res && res.analysis) {
      $('#pgAiResult').removeClass('d-none');
      pgAiSetAnalysis(res.analysis);
      return;
    }
    var msg = (res && res.message) ? res.message : 'Gagal memuat riwayat.';
    if (res && res.req_id) {
      msg += ' (log: ' + res.req_id + ')';
    }
    $('#pgAiError').removeClass('d-none').text(msg);
  }

  function pgAiOpenAnalisa($btn) {
    var url = $btn.attr('data-analisa-url');
    var id = $btn.attr('data-id');
    var $card = $btn.closest('.aa-card');
    if (!url || !id || !$card.length) {
      if (typeof aaToast === 'function') aaToast('Data riwayat tidak lengkap', 'error');
      return;
    }

    pgAiResetModal();
    pgAiShowPendingFromBtn($btn);
    $('#pgAiSub').text('Memuat riwayat…');

    var modalEl = document.getElementById('modalPengeluaranAi');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    $.ajax({
      url: url,
      type: 'POST',
      dataType: 'json',
      timeout: 65000,
      data: {
        id: id,
        id_cabang: $btn.attr('data-id-cabang') || $card.attr('data-id-cabang') || ''
      }
    }).done(function (res) {
      pgAiRenderResult(res);
    }).fail(function (xhr, textStatus) {
      $('#pgAiLoading').addClass('d-none');
      var res = null;
      if (xhr && xhr.responseText) {
        try {
          res = JSON.parse(xhr.responseText);
        } catch (err) { }
      }
      if (res) {
        pgAiRenderResult(res);
        return;
      }
      var msg = 'Gagal memuat riwayat';
      if (textStatus === 'timeout') {
        msg = 'Timeout — silakan coba lagi';
      } else if (xhr && xhr.status >= 500) {
        msg = 'Error server saat memuat riwayat';
      }
      $('#pgAiError').removeClass('d-none').text(msg);
    });
  }

  function pgConfirmOpen($btn) {
    var $card = $btn.closest('.aa-card');
    if (!$btn.attr('data-id') || !$btn.attr('data-target') || !$card.length) {
      if (typeof aaToast === 'function') aaToast('Data konfirmasi tidak lengkap', 'error');
      return;
    }
    pgConfirmState.btn = $btn;
    $('#pgConfirmPending').html(pgPendingHtml(pgPendingFromBtn($btn)));
    $('#btnPgConfirmYa').prop('disabled', false);
    var modalEl = document.getElementById('modalPengeluaranKonfirmasi');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  $('#btnPgConfirmYa').on('click', function () {
    var $btn = pgConfirmState.btn;
    if (!$btn || !$btn.length) return;
    $(this).prop('disabled', true);
    var modalEl = document.getElementById('modalPengeluaranKonfirmasi');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getInstance(modalEl)?.hide();
    }
    aaApproveAjax($btn, {
      tabKey: 'Pengeluaran',
      okMsg: 'Pengeluaran dikonfirmasi',
      failMsg: 'Gagal konfirmasi pengeluaran',
      emptyHtml: '<div class="aa-empty" style="margin-bottom:14px"><i class="fas fa-check-circle"></i>Tidak ada pengeluaran pending</div>'
    });
  });

  $('#modalPengeluaranKonfirmasi').on('hidden.bs.modal', function () {
    pgConfirmState.btn = null;
    $('#btnPgConfirmYa').prop('disabled', false);
  });

  $("#load").off("click.aaPengeluaran").on("click.aaPengeluaran", "span.nTunai", function (e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.hasClass('nTunaiAnalisa')) {
      pgAiOpenAnalisa($btn);
      return;
    }
    if ($btn.hasClass('nTunaiKonfirm')) {
      pgConfirmOpen($btn);
      return;
    }
    aaApproveAjax($btn, {
      tabKey: "Pengeluaran",
      okMsg: "Pengeluaran ditolak",
      failMsg: "Gagal menolak pengeluaran",
      emptyHtml: '<div class="aa-empty" style="margin-bottom:14px"><i class="fas fa-check-circle"></i>Tidak ada pengeluaran pending</div>'
    });
  });
})();
</script>
