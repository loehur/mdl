<?php
$hasKey = !empty($data['has_key']);
$createdAt = $data['created_at'] ?? null;
$pinOk = !empty($data['pin_ok']);
$hpMask = (string) ($data['hp_mask'] ?? '');
?>
<div class="content mt-3" id="generate-key-root" style="max-width: 480px;">
  <div class="card border-0 mb-3">
    <div class="card-body p-3">
      <h6 class="mb-2">Admin Access Key</h6>
      <p class="small text-muted mb-3">
        Key 4 digit untuk membuka mode Admin di layout.
        Request PIN dulu via WhatsApp (aktif 5 menit), lalu generate.
        Key hanya ditampilkan sekali. Jika lupa, generate ulang.
      </p>

      <?php if ($hasKey) { ?>
        <div class="alert alert-secondary py-2 small mb-3">
          Key aktif<?= $createdAt ? ' · dibuat ' . htmlspecialchars($createdAt) : '' ?>.
          Tidak bisa ditampilkan ulang.
        </div>
      <?php } else { ?>
        <div class="alert alert-warning py-2 small mb-3">Belum ada key. Generate dulu sebelum pakai tombol Admin.</div>
      <?php } ?>

      <div id="gkPinStep" class="<?= $pinOk ? 'd-none' : '' ?>">
        <div class="small text-muted mb-2">
          PIN dikirim ke WA<?= $hpMask !== '' ? ': <strong>' . htmlspecialchars($hpMask) . '</strong>' : '' ?>
        </div>
        <button type="button" class="btn btn-outline-dark btn-sm mb-3" id="gkBtnReqPin">
          Request PIN
        </button>
        <div id="gkReqMsg" class="small text-muted mb-3"></div>

        <label class="form-label small mb-1">Masukkan PIN dari WhatsApp</label>
        <div class="input-group input-group-sm mb-2">
          <input type="password" id="gkPin" class="form-control" inputmode="numeric" maxlength="4" autocomplete="one-time-code" placeholder="4 digit">
          <button type="button" class="btn btn-dark" id="gkBtnPin">Verifikasi PIN</button>
        </div>
        <div id="gkPinMsg" class="small text-muted"></div>
      </div>

      <div id="gkGenStep" class="<?= $pinOk ? '' : 'd-none' ?>">
        <button type="button" class="btn btn-primary btn-sm" id="gkBtnGenerate">Generate Key Baru</button>
        <div class="small text-muted mt-2">Generate akan mengganti key lama.</div>
      </div>

      <div id="gkReveal" class="d-none mt-3 p-3 border rounded text-center bg-light">
        <div class="small text-muted mb-1">Key (salin sekarang — tidak muncul lagi)</div>
        <div id="gkKeyValue" class="display-4 fw-bold" style="letter-spacing: 0.25em; font-family: monospace;"></div>
        <div id="gkRevealMsg" class="small text-danger mt-2"></div>
      </div>
    </div>
  </div>
</div>

<div class="mdl-key-modal" id="modalGkConfirm" aria-hidden="true">
  <div class="mdl-key-modal__backdrop" data-gk-confirm-close></div>
  <div class="mdl-key-modal__panel" role="dialog" aria-modal="true" aria-labelledby="modalGkConfirmLabel">
    <div class="mdl-key-modal__head">
      <h3 id="modalGkConfirmLabel"><i class="fas fa-key"></i> Generate Key</h3>
      <button type="button" class="mdl-key-modal__close" data-gk-confirm-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="mdl-key-modal__body">
      <p>Generate key baru? Key lama tidak berlaku lagi.</p>
    </div>
    <div class="mdl-key-modal__foot">
      <button type="button" class="mdl-key-modal__btn" data-gk-confirm-close>Batal</button>
      <button type="button" class="mdl-key-modal__btn mdl-key-modal__btn--primary" id="gkBtnConfirmYes">
        <i class="fas fa-check"></i> Ya, Generate
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  var root = document.getElementById('generate-key-root');
  if (!root) return;

  function notify(msg, type) {
    if (window.MdlToast) {
      if (type === 'warn' || type === 'warning') MdlToast.warn(msg);
      else if (type === 'error' || type === 'danger') MdlToast.error(msg);
      else if (type === 'ok' || type === 'success') MdlToast.ok(msg);
      else MdlToast.info(msg);
    } else {
      alert(msg);
    }
  }

  var $confirmModal = $('#modalGkConfirm');

  function openConfirmModal() {
    $confirmModal.addClass('is-open').attr('aria-hidden', 'false');
  }

  function closeConfirmModal() {
    $confirmModal.removeClass('is-open').attr('aria-hidden', 'true');
  }

  function doGenerate() {
    var $btn = $('#gkBtnGenerate');
    var $confirmBtn = $('#gkBtnConfirmYes');
    $btn.prop('disabled', true);
    $confirmBtn.prop('disabled', true);
    $.ajax({
      url: '<?= URL::BASE_URL ?>GenerateKey/generate',
      type: 'POST',
      dataType: 'json',
      success: function (res) {
        $btn.prop('disabled', false);
        $confirmBtn.prop('disabled', false);
        closeConfirmModal();
        if (res && res.ok == 1 && res.key) {
          $('#gkReveal').removeClass('d-none');
          $('#gkKeyValue').text(res.key);
          $('#gkRevealMsg').text(res.msg || '');
          $('#gkGenStep').addClass('d-none');
          $('#gkPinStep').removeClass('d-none');
          $('#gkPin').val('');
          $('#gkPinMsg').text('');
          $('#gkReqMsg').text('');
          notify('Key berhasil digenerate. Salin sekarang.', 'ok');
        } else {
          notify((res && res.msg) || 'Gagal generate', 'error');
          if (res && res.msg && String(res.msg).toLowerCase().indexOf('pin') >= 0) {
            $('#gkGenStep').addClass('d-none');
            $('#gkPinStep').removeClass('d-none');
          }
        }
      },
      error: function () {
        $btn.prop('disabled', false);
        $confirmBtn.prop('disabled', false);
        closeConfirmModal();
        notify('Gagal generate', 'error');
      }
    });
  }

  $confirmModal.on('click', '[data-gk-confirm-close]', function () {
    closeConfirmModal();
  });
  $(document).on('keydown.gkConfirm', function (e) {
    if (e.key === 'Escape' && $confirmModal.hasClass('is-open')) {
      closeConfirmModal();
    }
  });
  $('#gkBtnConfirmYes').on('click', function () {
    doGenerate();
  });

  $('#gkBtnReqPin').on('click', function () {
    var $btn = $(this);
    var $msg = $('#gkReqMsg');
    $btn.prop('disabled', true);
    $msg.text('Mengirim PIN...').removeClass('text-danger text-success');
    $.ajax({
      url: '<?= URL::BASE_URL ?>GenerateKey/reqPin',
      type: 'POST',
      dataType: 'json',
      success: function (res) {
        $btn.prop('disabled', false);
        if (res && res.ok == 1) {
          $msg.addClass('text-success').text(res.msg || 'PIN dikirim');
          $('#gkPin').focus();
        } else {
          $msg.addClass('text-danger').text((res && res.msg) || 'Gagal kirim PIN');
        }
      },
      error: function () {
        $btn.prop('disabled', false);
        $msg.addClass('text-danger').text('Gagal kirim PIN');
      }
    });
  });

  $('#gkBtnPin').on('click', function () {
    var pin = $('#gkPin').val().trim();
    var $msg = $('#gkPinMsg');
    $msg.text('...').removeClass('text-danger text-success');
    $.ajax({
      url: '<?= URL::BASE_URL ?>GenerateKey/verifyPin',
      type: 'POST',
      data: { pin: pin },
      dataType: 'json',
      success: function (res) {
        if (res && res.ok == 1) {
          $msg.addClass('text-success').text(res.msg || 'OK');
          $('#gkPinStep').addClass('d-none');
          $('#gkGenStep').removeClass('d-none');
        } else {
          $msg.addClass('text-danger').text((res && res.msg) || 'Gagal');
        }
      },
      error: function () {
        $msg.addClass('text-danger').text('Gagal verifikasi');
      }
    });
  });

  $('#gkPin').on('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
  });

  $('#gkPin').on('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      $('#gkBtnPin').click();
    }
  });

  $('#gkBtnGenerate').on('click', function () {
    openConfirmModal();
  });
})();
</script>
