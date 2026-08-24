<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.css" />

<div class="content" id="cqr-root">
  <style>
    #cqr-root {
      --cqr-ink: #0f172a;
      --cqr-muted: #475569;
      --cqr-line: #94a3b8;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #cqr-root .cqr-shell {
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(16,185,129,.10), transparent 50%),
        linear-gradient(180deg, #ecfdf5 0%, #fff 70%);
      border: 1px solid #cbd5e1;
      border-radius: 0;
      padding: 14px;
    }
    #cqr-root .cqr-title { font-weight: 900; font-size: 1.2rem; color: var(--cqr-ink); margin: 0 0 4px; }
    #cqr-root .cqr-lead { color: var(--cqr-muted); font-weight: 600; font-size: .9rem; margin: 0 0 12px; }
    #cqr-root .cqr-note {
      border: 1px solid #bbf7d0;
      background: #f0fdf4;
      color: #166534;
      font-size: .82rem;
      font-weight: 600;
      padding: 8px 10px;
      margin-bottom: 12px;
    }
    #cqr-root .cqr-badge {
      display: inline-block;
      border: 1px solid #86efac;
      background: #f0fdf4;
      color: #15803d;
      font-weight: 800;
      font-size: .72rem;
      padding: 2px 8px;
      border-radius: 0;
    }
    #cqr-root .cqr-badge--off { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; }
    #cqr-root .btn { border-radius: 0 !important; font-weight: 800; }
    #cqr-root code { font-size: .8rem; }
    #cqr-root .cell { cursor: pointer; border-bottom: 1px dashed #cbd5e1; }
    #cqr-root .cell:hover { background: #f8fafc; }
    #cqr-root .msg-preview {
      max-width: 360px;
      white-space: pre-wrap;
      word-break: break-word;
      font-size: .82rem;
      color: var(--cqr-muted);
    }
    #cqr-root .table { color: var(--cqr-ink); }
  </style>

  <div class="container-fluid">
    <div class="cqr-shell">
      <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
        <div>
          <h1 class="cqr-title">Quick Replies</h1>
          <p class="cqr-lead">
            Balas cepat kustom untuk chat CRM. Admin ketik <code>/</code> di chat untuk memilih shortcut.
          </p>
        </div>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#cqrAddModal" <?= empty($data['db_ready']) ? 'disabled' : '' ?>>
          + Tambah Balas Cepat
        </button>
      </div>

      <div class="cqr-note">
        Otomatis dari sistem: <code>/rekening</code> dan <code>/{kode_cabang}-location</code> (dari data cabang).
        Shortcut kustom tidak boleh bentrok dengan itu.
      </div>

      <?php if (empty($data['db_ready'])): ?>
        <div class="alert alert-danger">
          Tabel belum siap. Jalankan
          <code>api/database/crm/migrations/046_crm_quick_replies.sql</code>
          di database <strong>mdl_main</strong>.
        </div>
      <?php else: ?>
        <div id="cqrInfo" class="mb-2"></div>

        <div class="table-responsive">
          <table class="table table-sm table-hover" id="cqrTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Shortcut</th>
                <th>Judul</th>
                <th>Pesan</th>
                <th>Urut</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php
              $no = 0;
              foreach (($data['rows'] ?? []) as $row) {
                  $no++;
                  $id = (int) ($row['id'] ?? 0);
                  $shortcut = htmlspecialchars((string) ($row['shortcut'] ?? ''));
                  $title = htmlspecialchars((string) ($row['title'] ?? ''));
                  $message = (string) ($row['message'] ?? '');
                  $preview = htmlspecialchars(mb_strlen($message) > 120 ? (mb_substr($message, 0, 120) . '…') : $message);
                  $sort = (int) ($row['sort_order'] ?? 0);
                  $active = (int) ($row['is_active'] ?? 1) === 1;
                  $badgeClass = $active ? 'cqr-badge' : 'cqr-badge cqr-badge--off';
                  $badgeText = $active ? 'Aktif' : 'Nonaktif';
                  echo '<tr data-id="' . $id . '">';
                  echo '<td>' . $no . '</td>';
                  echo '<td><code class="cell" data-mode="shortcut" data-id="' . $id . '" data-value="' . $shortcut . '">' . $shortcut . '</code></td>';
                  echo '<td><span class="cell" data-mode="title" data-id="' . $id . '" data-value="' . $title . '">' . $title . '</span></td>';
                  echo '<td><span class="cell msg-preview" data-mode="message" data-id="' . $id . '" data-value="' . htmlspecialchars($message, ENT_QUOTES) . '">' . $preview . '</span></td>';
                  echo '<td><span class="cell" data-mode="sort_order" data-id="' . $id . '" data-value="' . $sort . '">' . $sort . '</span></td>';
                  echo '<td><button type="button" class="btn btn-sm btn-toggle ' . ($active ? 'btn-outline-success' : 'btn-outline-secondary') . '" data-id="' . $id . '" data-active="' . ($active ? '1' : '0') . '"><span class="' . $badgeClass . '">' . $badgeText . '</span></button></td>';
                  echo '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-danger btn-delete" data-id="' . $id . '" data-title="' . $title . '">Hapus</button></td>';
                  echo '</tr>';
              }
              ?>
            </tbody>
          </table>
        </div>

        <?php if (($data['rows'] ?? []) === []): ?>
          <p class="text-muted mb-0">Belum ada balas cepat kustom.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal" id="cqrAddModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content" style="border-radius:0">
        <div class="modal-header">
          <h5 class="modal-title">Tambah Balas Cepat</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="cqrAddInfo"></div>
          <form id="cqrAddForm">
            <div class="mb-2">
              <label class="form-label">Shortcut</label>
              <input type="text" name="shortcut" class="form-control form-control-sm" placeholder="/promo" required>
              <div class="form-text">Diawali <code>/</code>, huruf kecil, angka, strip, underscore.</div>
            </div>
            <div class="mb-2">
              <label class="form-label">Judul</label>
              <input type="text" name="title" class="form-control form-control-sm" placeholder="Promo Bulan Ini" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Pesan</label>
              <textarea name="message" class="form-control form-control-sm" rows="8" placeholder="Teks yang akan diisi ke chat CRM…" required></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" form="cqrAddForm" class="btn btn-sm btn-success">Simpan</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="cqrEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content" style="border-radius:0">
        <div class="modal-header">
          <h5 class="modal-title" id="cqrEditTitle">Edit</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="cqrEditInfo"></div>
          <input type="hidden" id="cqrEditId">
          <input type="hidden" id="cqrEditMode">
          <div class="mb-2" id="cqrEditFieldWrap">
            <label class="form-label" id="cqrEditLabel"></label>
            <input type="text" id="cqrEditInput" class="form-control form-control-sm">
            <textarea id="cqrEditTextarea" class="form-control form-control-sm" rows="8" hidden></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-sm btn-primary" id="cqrEditSave">Simpan</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="cqrDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
      <div class="modal-content" style="border-radius:0">
        <div class="modal-header">
          <h5 class="modal-title">Hapus Balas Cepat</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          Hapus <strong id="cqrDeleteTitle"></strong>?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-sm btn-danger" id="cqrDeleteConfirm">Hapus</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.js"></script>
<script>
(function () {
  var BASE = '<?= URL::BASE_URL ?>';
  var pendingDeleteId = 0;

  if ($.fn.DataTable && $('#cqrTable tbody tr').length) {
    $('#cqrTable').DataTable({
      pageLength: 25,
      order: [[4, 'asc']],
      columnDefs: [{ orderable: false, targets: [6] }]
    });
  }

  function showInfo(sel, msg, ok) {
    var cls = ok ? 'alert-success' : 'alert-danger';
    $(sel).html('<div class="alert ' + cls + ' py-2 mb-0">' + msg + '</div>');
    setTimeout(function () { $(sel).empty(); }, 5000);
  }

  $('#cqrAddForm').on('submit', function (e) {
    e.preventDefault();
    $.post(BASE + 'CrmQuickReplies/insert', $(this).serialize())
      .done(function (res) {
        if (String(res).trim() === '0') {
          location.reload(true);
          return;
        }
        showInfo('#cqrAddInfo', res || 'Gagal menyimpan', false);
      })
      .fail(function () {
        showInfo('#cqrAddInfo', 'Gagal menyimpan', false);
      });
  });

  $(document).on('click', '.cell', function () {
    var mode = $(this).data('mode');
    var id = $(this).data('id');
    var value = $(this).attr('data-value') || '';
    $('#cqrEditId').val(id);
    $('#cqrEditMode').val(mode);
    $('#cqrEditInfo').empty();

    var labels = {
      shortcut: 'Shortcut',
      title: 'Judul',
      message: 'Pesan',
      sort_order: 'Urutan'
    };
    $('#cqrEditTitle').text('Edit ' + (labels[mode] || mode));
    $('#cqrEditLabel').text(labels[mode] || mode);

    if (mode === 'message') {
      $('#cqrEditInput').prop('hidden', true);
      $('#cqrEditTextarea').prop('hidden', false).val(value);
    } else {
      $('#cqrEditTextarea').prop('hidden', true);
      $('#cqrEditInput').prop('hidden', false).val(value);
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('cqrEditModal')).show();
  });

  $('#cqrEditSave').on('click', function () {
    var id = $('#cqrEditId').val();
    var mode = $('#cqrEditMode').val();
    var value = mode === 'message' ? $('#cqrEditTextarea').val() : $('#cqrEditInput').val();
    var $btn = $(this).prop('disabled', true);
    $.post(BASE + 'CrmQuickReplies/update', { id: id, mode: mode, value: value })
      .done(function (res) {
        if (String(res).trim() === '0') {
          location.reload(true);
          return;
        }
        showInfo('#cqrEditInfo', res || 'Gagal update', false);
      })
      .fail(function () {
        showInfo('#cqrEditInfo', 'Gagal update', false);
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  $(document).on('click', '.btn-toggle', function () {
    var id = $(this).data('id');
    var active = String($(this).data('active')) === '1' ? 0 : 1;
    $.post(BASE + 'CrmQuickReplies/update', { id: id, mode: 'is_active', value: active })
      .done(function (res) {
        if (String(res).trim() === '0') {
          location.reload(true);
          return;
        }
        showInfo('#cqrInfo', res || 'Gagal update status', false);
      })
      .fail(function () {
        showInfo('#cqrInfo', 'Gagal update status', false);
      });
  });

  $(document).on('click', '.btn-delete', function () {
    pendingDeleteId = $(this).data('id') || 0;
    $('#cqrDeleteTitle').text($(this).data('title') || '');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('cqrDeleteModal')).show();
  });

  $('#cqrDeleteConfirm').on('click', function () {
    if (!pendingDeleteId) return;
    var $btn = $(this).prop('disabled', true);
    $.post(BASE + 'CrmQuickReplies/delete', { id: pendingDeleteId })
      .done(function (res) {
        if (String(res).trim() === '0') {
          location.reload(true);
          return;
        }
        showInfo('#cqrInfo', res || 'Gagal hapus', false);
      })
      .fail(function () {
        showInfo('#cqrInfo', 'Gagal hapus', false);
      })
      .always(function () {
        $btn.prop('disabled', false);
        pendingDeleteId = 0;
      });
  });
})();
</script>
