<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.css" />

<div class="content" id="ark-root">
  <style>
    #ark-root { --ark-ink:#0f172a; --ark-muted:#334155; --ark-line:#94a3b8; --ark-blue:#2563eb; font-family:'fontku','Segoe UI',sans-serif; }
    #ark-root .ark-shell {
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.10), transparent 50%),
        linear-gradient(180deg, #eef4ff 0%, #fff 70%);
      border: 1px solid #cbd5e1; border-radius: 0; padding: 14px;
    }
    #ark-root .ark-title { font-weight: 900; font-size: 1.2rem; color: var(--ark-ink); margin: 0 0 4px; }
    #ark-root .ark-lead { color: var(--ark-muted); font-weight: 600; font-size: .9rem; margin: 0 0 12px; }
    #ark-root .ark-badge { display:inline-block; border:1px solid #93c5fd; background:#eff6ff; color:#1d4ed8; font-weight:800; font-size:.72rem; padding:2px 8px; margin-right:6px; }
    #ark-root .ark-badge--warn { border-color:#fcd34d; background:#fffbeb; color:#b45309; }
    #ark-root .ark-badge--ok { border-color:#86efac; background:#f0fdf4; color:#15803d; }
    #ark-root .ark-badge--off { border-color:#cbd5e1; background:#f8fafc; color:#64748b; }
    #ark-root .btn { border-radius: 0 !important; font-weight: 800; }
    #ark-root .table { color: var(--ark-ink); }
    #ark-root code { font-size: .8rem; }
  </style>

  <div class="container-fluid">
    <div class="ark-shell">
      <h1 class="ark-title">Auto Reply Keywords</h1>
      <p class="ark-lead">
        Intent + regex + AI prompt untuk klasifikasi WA.
        Runtime API baca hanya dari DB <code>mdl_main</code> (cache version).
      </p>

      <?php if (empty($data['db_ready'])): ?>
        <div class="alert alert-danger">
          Tabel belum siap. Jalankan SQL
          <code>api/database/crm/migrations/014_wa_autoreply_keywords.sql</code>
          di database <strong>mdl_main</strong>.
        </div>
      <?php else: ?>
        <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
          <?php if (($data['source'] ?? '') === 'empty'): ?>
            <span class="ark-badge ark-badge--warn">DB kosong</span>
          <?php else: ?>
            <span class="ark-badge ark-badge--ok">Sumber: database</span>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addIntentModal">+ Intent</button>
          <button type="button" class="btn btn-sm btn-outline-success" id="btnCompactAll">Rapikan pattern</button>
          <a class="btn btn-sm btn-outline-primary" href="<?= URL::BASE_URL ?>IntentLab">Intent Lab</a>
        </div>

        <div id="arkInfo"></div>

        <div class="table-responsive">
          <table class="table table-sm table-hover" id="arkTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Code</th>
                <th>Sort</th>
                <th>Patterns</th>
                <th>AI</th>
                <th>Case</th>
                <th>Notify</th>
                <th>Aktif</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php
              $no = 0;
              foreach (($data['intents'] ?? []) as $a) {
                $no++;
                $id = (int) $a['id'];
                $code = htmlspecialchars($a['code']);
                $ai = !empty($a['ai_prompt']) ? 'ya' : '—';
                $case = ($a['case_value'] === null || $a['case_value'] === '') ? '—' : (int) $a['case_value'];
                $notify = ($a['notify'] === null || $a['notify'] === '') ? '—' : (((int)$a['notify'] === 1) ? 'true' : 'false');
                $active = ((int)$a['is_active'] === 1)
                  ? '<span class="ark-badge ark-badge--ok">on</span>'
                  : '<span class="ark-badge ark-badge--off">off</span>';
                $pc = (int) ($a['pattern_active'] ?? 0);
                $pt = (int) ($a['pattern_count'] ?? 0);
                echo '<tr>';
                echo '<td>' . $no . '</td>';
                echo '<td><strong>' . $code . '</strong></td>';
                echo '<td>' . (int)$a['sort_order'] . '</td>';
                echo '<td>' . $pc . ($pc !== $pt ? " / $pt" : '') . '</td>';
                echo '<td>' . $ai . '</td>';
                echo '<td>' . $case . '</td>';
                echo '<td>' . $notify . '</td>';
                echo '<td>' . $active . '</td>';
                echo '<td class="text-nowrap">';
                echo '<a class="btn btn-sm btn-primary" href="' . URL::BASE_URL . 'AutoReplyKeywords/detail/' . $id . '">Edit</a> ';
                echo '<button type="button" class="btn btn-sm btn-danger btn-del-intent" data-id="' . $id . '" data-code="' . $code . '">Hapus</button>';
                echo '</td>';
                echo '</tr>';
              }
              ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal" id="addIntentModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content" style="border-radius:0">
        <div class="modal-header">
          <h5 class="modal-title">Tambah Intent</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="formAddIntent" method="POST" action="<?= URL::BASE_URL ?>AutoReplyKeywords/insertIntent">
            <label class="form-label fw-bold">Code</label>
            <input type="text" name="code" class="form-control form-control-sm" placeholder="PENUTUP" required pattern="[A-Za-z0-9_]+">
            <label class="form-label fw-bold mt-2">Note (opsional)</label>
            <input type="text" name="note" class="form-control form-control-sm">
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" form="formAddIntent" class="btn btn-sm btn-primary">Simpan</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="compactModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content" style="border-radius:0">
        <div class="modal-header">
          <h5 class="modal-title">Rapikan pattern</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2" style="font-weight:700">Keyword sederhana (mis. <code>terimakash</code> + <code>mksh</code>) digabung jadi <b>satu</b> regex <code>(a|b|c)</code>. Pattern frasa tetap utuh.</p>
          <div id="compactPreview" style="max-height:320px;overflow:auto;font-size:.85rem"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-sm btn-success" id="btnCompactApply" disabled>Ya, rapikan</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="confirmDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
      <div class="modal-content" style="border-radius:0">
        <div class="modal-header">
          <h5 class="modal-title">Hapus Intent</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">Hapus <strong id="delCode"></strong> beserta semua pattern-nya?</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDel">Hapus</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.js"></script>
<script>
$(function() {
  if ($('#arkTable').length) {
    new DataTable('#arkTable', { order: [[2, 'asc']], pageLength: 50 });
  }

  $('#formAddIntent').on('submit', function(e) {
    e.preventDefault();
    $.post($(this).attr('action'), $(this).serialize()).done(function(r) {
      if (String(r).trim() === '0') location.reload(true);
      else $('#arkInfo').html('<div class="alert alert-danger">' + r + '</div>');
    });
  });

  var delId = 0;
  $('.btn-del-intent').on('click', function() {
    delId = $(this).data('id');
    $('#delCode').text($(this).data('code'));
    new bootstrap.Modal('#confirmDeleteModal').show();
  });
  $('#btnConfirmDel').on('click', function() {
    $.post('<?= URL::BASE_URL ?>AutoReplyKeywords/deleteIntent', { id: delId }).done(function(r) {
      if (String(r).trim() === '0') location.reload(true);
      else if (window.MdlToast) MdlToast.error(r);
      else $('#arkInfo').html('<div class="alert alert-danger">' + r + '</div>');
    });
  });

  var compactUrl = '<?= URL::BASE_URL ?>AutoReplyKeywords/compactPatterns';
  var compactModal = null;
  function toast(msg, kind) {
    if (window.MdlToast) {
      if (kind === 'err' && MdlToast.error) return MdlToast.error(msg);
      if (kind === 'warn' && MdlToast.warn) return MdlToast.warn(msg);
      if (MdlToast.info) return MdlToast.info(msg);
    }
    $('#arkInfo').html('<div class="alert alert-' + (kind === 'err' ? 'danger' : 'info') + '">' + msg + '</div>');
  }
  function renderCompactPlans(plans) {
    if (!plans || !plans.length) {
      $('#compactPreview').html('<p class="mb-0">Tidak ada yang perlu digabung.</p>');
      return;
    }
    var html = '';
    plans.forEach(function (p) {
      html += '<div class="mb-3 p-2" style="border:1px solid #cbd5e1;background:#fff">';
      html += '<div style="font-weight:900">' + (p.intent || '') + ' — hapus ' + ((p.delete_ids && p.delete_ids.length) || 0) + ' row</div>';
      html += '<code style="word-break:break-all">' + String(p.merged_pattern || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</code>';
      html += '</div>';
    });
    $('#compactPreview').html(html);
  }
  $('#btnCompactAll').on('click', function() {
    $('#compactPreview').html('Memeriksa…');
    $('#btnCompactApply').prop('disabled', true);
    compactModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('compactModal'));
    compactModal.show();
    $.ajax({
      url: compactUrl, type: 'POST',
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify({ all: 1 }), dataType: 'json'
    }).done(function (res) {
      if (!(res && (res.ok === 1 || res.ok === true))) {
        toast((res && res.message) || 'Gagal preview', 'err');
        return;
      }
      renderCompactPlans(res.plans || []);
      $('#btnCompactApply').prop('disabled', !((res.needed_count || 0) > 0));
      if (!(res.needed_count > 0)) toast(res.message || 'Sudah rapi', 'warn');
    }).fail(function () { toast('Request gagal', 'err'); });
  });
  $('#btnCompactApply').on('click', function() {
    $('#btnCompactApply').prop('disabled', true).text('Merapikan…');
    $.ajax({
      url: compactUrl, type: 'POST',
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify({ all: 1, apply: 1 }), dataType: 'json'
    }).done(function (res) {
      if (!(res && (res.ok === 1 || res.ok === true))) {
        toast((res && res.message) || 'Gagal merapikan', 'err');
        $('#btnCompactApply').prop('disabled', false).text('Ya, rapikan');
        return;
      }
      toast(res.message || 'Selesai', 'info');
      location.reload(true);
    }).fail(function () {
      toast('Request gagal', 'err');
      $('#btnCompactApply').prop('disabled', false).text('Ya, rapikan');
    });
  });
});
</script>
