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
          <span class="aa-btn aa-btn--ok nTunai nTunaiKonfirm" role="button"
            data-id="<?= $idAttr ?>"
            data-id-cabang="<?= $idCabang ?>"
            data-target="<?= URL::BASE_URL ?>Pengeluaran/operasi/3"
            data-analisa-url="<?= URL::BASE_URL ?>Pengeluaran/analisaAi"
            data-pg-jenis="<?= htmlspecialchars(strtoupper((string) $note), ENT_QUOTES, 'UTF-8') ?>"
            data-pg-ket="<?= htmlspecialchars(ucwords((string) $f2), ENT_QUOTES, 'UTF-8') ?>"
            data-pg-jumlah="<?= number_format((float) $f4, 0, ',', '.') ?>"
            data-pg-kode="<?= htmlspecialchars($this->cabangKodeById($idCabang), ENT_QUOTES, 'UTF-8') ?>">Konfirmasi</span>
        </div>
      </div>
    <?php } ?>
  </div>
<?php } else { ?>
  <div class="aa-empty" style="margin-bottom:14px"><i class="fas fa-check-circle"></i>Tidak ada pengeluaran pending</div>
<?php } ?>

<!-- Modal analisa AI sebelum konfirmasi pengeluaran -->
<div class="modal fade" id="modalPengeluaranAi" tabindex="-1" aria-labelledby="modalPengeluaranAiLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:0;border:1px solid #cbd5e1;">
      <div class="modal-header py-2 border-bottom" style="background:linear-gradient(105deg,#1d4ed8,#2563eb);color:#fff;">
        <div>
          <h6 class="modal-title fw-bold mb-0" id="modalPengeluaranAiLabel"><i class="fas fa-robot me-2"></i>Analisa AI Pengeluaran</h6>
          <small id="pgAiSub" style="opacity:.9;">Memuat…</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-3">
        <div id="pgAiPending" class="border p-2 mb-3 small" style="background:#f8fafc;border-color:#cbd5e1 !important;"></div>
        <div id="pgAiLoading" class="text-center text-muted py-4">
          <i class="fas fa-spinner fa-spin fa-lg d-block mb-2"></i>
          AI sedang menganalisa riwayat 30 hari…
        </div>
        <div id="pgAiResult" class="d-none">
          <div class="fw-bold mb-2" style="font-size:.82rem;color:#1e40af;"><i class="fas fa-comment-dots"></i> Komentar AI untuk Admin</div>
          <div id="pgAiAnalysis" style="font-size:.88rem;line-height:1.55;white-space:pre-wrap;color:#0f172a;"></div>
          <div id="pgAiMeta" class="text-muted mt-2" style="font-size:.75rem;"></div>
        </div>
        <div id="pgAiError" class="d-none alert alert-warning mb-0 py-2 small"></div>
      </div>
      <div class="modal-footer py-2 border-top">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success btn-sm d-none" id="btnPgAiKonfirmasi">
          <i class="fas fa-check me-1"></i>Lanjut Konfirmasi
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var pgAiState = { btn: null, card: null };

  $('body > #modalPengeluaranAi').remove();
  $('#modalPengeluaranAi').appendTo('body');

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
    $('#pgAiAnalysis').text('');
    $('#pgAiMeta').text('');
    $('#btnPgAiKonfirmasi').addClass('d-none').prop('disabled', false);
  }

  function pgAiShowPending(p) {
    if (!p) return;
    var html = '<div><strong>' + pgAiEsc(p.jenis_pengeluaran || '-') + '</strong></div>';
    html += '<div class="text-muted">' + pgAiEsc(p.kode_cabang || '-') + ' · #' + pgAiEsc(p.id_kas || '') + '</div>';
    html += '<div class="mt-1">' + pgAiEsc(p.keterangan || '-') + '</div>';
    html += '<div class="fw-bold mt-1">Rp ' + pgAiEsc(p.jumlah_fmt || '0') + '</div>';
    $('#pgAiPending').html(html);
    var sub = 'Riwayat 30 hari · jenis sama · status berhasil';
    if (p.jenis_pengeluaran) {
      sub += ' (' + p.jenis_pengeluaran + ')';
    }
    $('#pgAiSub').text(sub);
  }

  function pgAiShowPendingFromBtn($btn) {
    pgAiShowPending({
      id_kas: $btn.attr('data-id') || '',
      kode_cabang: $btn.attr('data-pg-kode') || '-',
      jenis_pengeluaran: $btn.attr('data-pg-jenis') || '-',
      keterangan: $btn.attr('data-pg-ket') || '-',
      jumlah_fmt: $btn.attr('data-pg-jumlah') || '0'
    });
  }

  function pgAiRenderResult(res) {
    $('#pgAiLoading').addClass('d-none');
    if (res && res.pending) {
      pgAiShowPending(res.pending);
    }
    if (res && res.ok && res.analysis) {
      $('#pgAiResult').removeClass('d-none');
      $('#pgAiAnalysis').text(res.analysis);
      var meta = '';
      if (res.ai_source === 'local') {
        meta = 'Analisa otomatis (AI tidak tersedia)';
      } else {
        meta = 'Analisa AI';
      }
      if (res.history_count != null) {
        meta += (meta ? ' · ' : '') + 'Riwayat jenis sama: ' + res.history_count + ' baris (30 hari, berhasil)';
      }
      if (res.jenis_filter) {
        meta += (meta ? ' · ' : '') + 'Jenis: ' + res.jenis_filter;
      }
      $('#pgAiMeta').text(meta);
      if (res.ai_source === 'local' && res.message) {
        $('#pgAiError').removeClass('d-none').text(res.message);
      }
      $('#btnPgAiKonfirmasi').removeClass('d-none');
      return;
    }
    var msg = (res && res.message) ? res.message : 'Analisa gagal.';
    $('#pgAiError').removeClass('d-none').text(msg + ' Anda tetap bisa konfirmasi jika sudah yakin.');
    $('#btnPgAiKonfirmasi').removeClass('d-none');
  }

  function pgAiOpenAnalisa($btn) {
    var url = $btn.attr('data-analisa-url');
    var id = $btn.attr('data-id');
    var $card = $btn.closest('.aa-card');
    if (!url || !id || !$card.length) {
      if (typeof aaToast === 'function') aaToast('Data konfirmasi tidak lengkap', 'error');
      return;
    }

    pgAiState.btn = $btn;
    pgAiState.card = $card;
    pgAiResetModal();
    pgAiShowPendingFromBtn($btn);
    $('#pgAiSub').text('Memuat analisa…');

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
      var msg = 'Gagal memuat analisa';
      if (textStatus === 'timeout') {
        msg = 'Analisa timeout — coba lagi atau lanjut konfirmasi manual';
      } else if (xhr && xhr.status >= 500) {
        msg = 'Error server saat analisa — pastikan file PengeluaranAiReview sudah ter-deploy';
      }
      $('#pgAiError').removeClass('d-none').text(msg + '. Anda tetap bisa konfirmasi jika sudah yakin.');
      $('#btnPgAiKonfirmasi').removeClass('d-none');
    });
  }

  $('#btnPgAiKonfirmasi').on('click', function () {
    var $btn = pgAiState.btn;
    if (!$btn || !$btn.length) return;
    $(this).prop('disabled', true);
    var modalEl = document.getElementById('modalPengeluaranAi');
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

  $('#modalPengeluaranAi').on('hidden.bs.modal', function () {
    pgAiState.btn = null;
    pgAiState.card = null;
    $('#btnPgAiKonfirmasi').prop('disabled', false);
  });

  $("#load").off("click.aaPengeluaran").on("click.aaPengeluaran", "span.nTunai", function (e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.hasClass('nTunaiKonfirm')) {
      pgAiOpenAnalisa($btn);
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
