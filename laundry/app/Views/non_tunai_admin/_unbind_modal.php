<?php
$unbindUrl = (string) ($data['unbindUrl'] ?? '');
?>
<div class="modal fade" id="ntaUnbindModal" tabindex="-1" aria-labelledby="ntaUnbindModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="ntaUnbindModalLabel">Unbind Mutasi BCA</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2 small text-muted">
          Mutasi akan dilepas dari entity. <strong>Referensi transaksi</strong> (entity type + ref) diblokir agar tidak bisa di-bind ulang.
          Mutasi BCA itu sendiri tetap bebas di-bind ke transaksi lain.
          Status pembayaran dikembalikan (laundry: tidak tuntas, invoice: belum bayar, salon: subscription sebelumnya).
        </p>
        <dl class="nta-detail-list mb-3">
          <div class="nta-detail-row">
            <dt>ID Link</dt>
            <dd id="ntaUnbindLinkId">—</dd>
          </div>
          <div class="nta-detail-row">
            <dt>Tipe</dt>
            <dd id="ntaUnbindEntityType">—</dd>
          </div>
          <div class="nta-detail-row">
            <dt>Referensi</dt>
            <dd id="ntaUnbindEntityRef">—</dd>
          </div>
        </dl>
        <label class="form-label small fw-bold mb-1" for="ntaUnbindReason">Alasan (opsional)</label>
        <input type="text" class="form-control form-control-sm" id="ntaUnbindReason" maxlength="255"
          placeholder="Mis. salah bind / nominal tidak cocok">
        <div id="ntaUnbindError" class="alert alert-danger small py-2 px-3 mt-3 mb-0 d-none" role="alert"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger btn-sm" id="ntaUnbindConfirmBtn">
          <i class="fas fa-unlink me-1"></i>Unbind &amp; Blokir
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  #nta-root .nta-unbind-btn {
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
  }
</style>

<script>
(function () {
  var unbindUrl = <?= json_encode($unbindUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var pendingLinkId = 0;
  var modalEl = document.getElementById('ntaUnbindModal');
  var confirmBtn = document.getElementById('ntaUnbindConfirmBtn');
  var errBox = document.getElementById('ntaUnbindError');

  function showError(msg) {
    if (!errBox) return;
    errBox.textContent = msg || 'Unbind gagal';
    errBox.classList.remove('d-none');
  }

  function clearError() {
    if (!errBox) return;
    errBox.textContent = '';
    errBox.classList.add('d-none');
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.nta-unbind-btn');
    if (!btn) return;

    pendingLinkId = parseInt(btn.getAttribute('data-link-id') || '0', 10);
    if (pendingLinkId < 1) return;

    var linkEl = document.getElementById('ntaUnbindLinkId');
    var typeEl = document.getElementById('ntaUnbindEntityType');
    var refEl = document.getElementById('ntaUnbindEntityRef');
    var reasonEl = document.getElementById('ntaUnbindReason');

    if (linkEl) linkEl.textContent = String(pendingLinkId);
    if (typeEl) typeEl.textContent = btn.getAttribute('data-entity-type') || '—';
    if (refEl) refEl.textContent = btn.getAttribute('data-entity-ref') || '—';
    if (reasonEl) reasonEl.value = '';
    clearError();

    if (modalEl && window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  });

  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      if (pendingLinkId < 1 || !unbindUrl) return;

      clearError();
      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses…';

      var reasonEl = document.getElementById('ntaUnbindReason');
      var body = new URLSearchParams();
      body.set('link_id', String(pendingLinkId));
      body.set('reason', reasonEl ? reasonEl.value.trim() : '');

      fetch(unbindUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        credentials: 'same-origin'
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.ok) {
            window.location.reload();
            return;
          }
          showError((data && data.message) ? data.message : 'Unbind gagal');
        })
        .catch(function () {
          showError('Gagal menghubungi server');
        })
        .finally(function () {
          confirmBtn.disabled = false;
          confirmBtn.innerHTML = '<i class="fas fa-unlink me-1"></i>Unbind &amp; Blokir';
        });
    });
  }
})();
</script>
