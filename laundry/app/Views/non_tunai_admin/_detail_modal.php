<div class="modal fade" id="ntaDetailModal" tabindex="-1" aria-labelledby="ntaDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="ntaDetailModalLabel">Detail Binding</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-0">
        <dl class="nta-detail-list mb-0" id="ntaDetailBody"></dl>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  function escHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.nta-detail-btn');
    if (!btn) return;

    var raw = btn.getAttribute('data-detail');
    if (!raw) return;

    var payload;
    try {
      payload = JSON.parse(raw);
    } catch (err) {
      return;
    }

    var title = document.getElementById('ntaDetailModalLabel');
    var body = document.getElementById('ntaDetailBody');
    if (!title || !body) return;

    title.textContent = payload.title || 'Detail Binding';

    var html = '';
    (payload.fields || []).forEach(function (field) {
      if (!field || !field.label) return;
      var value = field.value == null || field.value === '' ? '—' : String(field.value);
      if (field.html) {
        value = field.html;
      } else {
        value = escHtml(value);
      }
      html += '<div class="nta-detail-row">';
      html += '<dt>' + escHtml(field.label) + '</dt>';
      html += '<dd>' + value + '</dd>';
      html += '</div>';
    });

    body.innerHTML = html;

    var modalEl = document.getElementById('ntaDetailModal');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  });
})();
</script>
