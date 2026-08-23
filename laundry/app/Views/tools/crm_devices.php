<link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/DataTables/datatables.min.css" />

<div class="content" id="crm-dev-root">
  <style>
    #crm-dev-root {
      --cd-ink: #0f172a;
      --cd-muted: #475569;
      --cd-line: #94a3b8;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #crm-dev-root .cd-shell {
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.10), transparent 50%),
        linear-gradient(180deg, #eef4ff 0%, #fff 70%);
      border: 1px solid #cbd5e1;
      border-radius: 0;
      padding: 14px;
    }
    #crm-dev-root .cd-title { font-weight: 900; font-size: 1.2rem; color: var(--cd-ink); margin: 0 0 4px; }
    #crm-dev-root .cd-lead { color: var(--cd-muted); font-weight: 600; font-size: .9rem; margin: 0 0 12px; }
    #crm-dev-root .cd-badge {
      display: inline-block;
      border: 1px solid #93c5fd;
      background: #eff6ff;
      color: #1d4ed8;
      font-weight: 800;
      font-size: .72rem;
      padding: 2px 8px;
      border-radius: 0;
    }
    #crm-dev-root .cd-badge--crew { border-color: #86efac; background: #f0fdf4; color: #15803d; }
    #crm-dev-root .cd-badge--driver { border-color: #fcd34d; background: #fffbeb; color: #b45309; }
    #crm-dev-root .cd-badge--admin { border-color: #c4b5fd; background: #f5f3ff; color: #6d28d9; }
    #crm-dev-root .btn { border-radius: 0 !important; font-weight: 800; }
    #crm-dev-root code { font-size: .8rem; word-break: break-all; }
    #crm-dev-root .table { color: var(--cd-ink); }
  </style>

  <div class="container-fluid">
    <div class="cd-shell">
      <h1 class="cd-title">CRM Devices</h1>
      <p class="cd-lead">
        Daftar login CRM yang terkunci ke satu device (<code>crm_device_locks</code>).
        Unbind agar user bisa login dari device/browser lain.
      </p>

      <?php if (empty($data['db_ready'])): ?>
        <div class="alert alert-danger">
          Tabel belum siap. Jalankan
          <code>api/database/crm/migrations/001_device_locks.sql</code>
          di database <strong>mdl_main</strong>.
        </div>
      <?php else: ?>
        <div id="cdInfo" class="mb-2"></div>

        <div class="table-responsive">
          <table class="table table-sm table-hover" id="cdTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Username</th>
                <th>Role</th>
                <th>Nama / Cabang</th>
                <th>Device ID</th>
                <th>Locked</th>
                <th>Last seen</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php
              $no = 0;
              foreach (($data['locks'] ?? []) as $lock) {
                  $no++;
                  $username = htmlspecialchars((string) ($lock['username'] ?? ''));
                  $role = htmlspecialchars((string) ($lock['role'] ?? '-'));
                  $label = htmlspecialchars((string) ($lock['label'] ?? '-'));
                  $deviceId = htmlspecialchars((string) ($lock['device_id'] ?? ''));
                  $lockedAt = htmlspecialchars((string) ($lock['locked_at'] ?? ''));
                  $lastSeen = htmlspecialchars((string) ($lock['last_seen'] ?? ''));
                  $roleClass = 'cd-badge';
                  if ($role === 'Crew') {
                      $roleClass .= ' cd-badge--crew';
                  } elseif ($role === 'Driver') {
                      $roleClass .= ' cd-badge--driver';
                  } elseif ($role === 'Admin') {
                      $roleClass .= ' cd-badge--admin';
                  }
                  echo '<tr data-username="' . $username . '">';
                  echo '<td>' . $no . '</td>';
                  echo '<td><strong>' . $username . '</strong></td>';
                  echo '<td><span class="' . $roleClass . '">' . $role . '</span></td>';
                  echo '<td>' . $label . '</td>';
                  echo '<td><code>' . $deviceId . '</code></td>';
                  echo '<td>' . $lockedAt . '</td>';
                  echo '<td>' . $lastSeen . '</td>';
                  echo '<td class="text-nowrap">';
                  echo '<button type="button" class="btn btn-sm btn-danger btn-unbind" data-username="' . $username . '">Unbind</button>';
                  echo '</td>';
                  echo '</tr>';
              }
              ?>
            </tbody>
          </table>
        </div>

        <?php if (($data['locks'] ?? []) === []): ?>
          <p class="text-muted mb-0">Belum ada device lock aktif.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal" id="confirmUnbindModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
      <div class="modal-content" style="border-radius:0">
        <div class="modal-header">
          <h5 class="modal-title">Unbind Device</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          Lepas kunci device untuk <strong id="unbindUsername"></strong>?
          User bisa login ulang dari device lain.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-sm btn-danger" id="btnConfirmUnbind">Unbind</button>
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
  var pendingUsername = '';

  if ($.fn.DataTable && $('#cdTable tbody tr').length) {
    $('#cdTable').DataTable({
      pageLength: 25,
      order: [[6, 'desc']],
      columnDefs: [{ orderable: false, targets: [7] }]
    });
  }

  function showInfo(msg, ok) {
    var cls = ok ? 'alert-success' : 'alert-danger';
    $('#cdInfo').html('<div class="alert ' + cls + ' py-2 mb-0">' + msg + '</div>');
    setTimeout(function () { $('#cdInfo').empty(); }, 5000);
  }

  $(document).on('click', '.btn-unbind', function () {
    pendingUsername = $(this).data('username') || '';
    $('#unbindUsername').text(pendingUsername);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmUnbindModal')).show();
  });

  $('#btnConfirmUnbind').on('click', function () {
    if (!pendingUsername) return;
    var $btn = $(this).prop('disabled', true);
    $.post(BASE + 'CrmDevices/unbind', { username: pendingUsername })
      .done(function (res) {
        if (res && res.ok) {
          showInfo(res.message || 'Unbind berhasil', true);
          $('tr[data-username="' + pendingUsername + '"]').fadeOut(200, function () {
            $(this).remove();
          });
          bootstrap.Modal.getInstance(document.getElementById('confirmUnbindModal')).hide();
        } else {
          showInfo((res && res.message) || 'Gagal unbind', false);
        }
      })
      .fail(function (xhr) {
        var msg = 'Gagal unbind';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.message) msg = j.message;
        } catch (e) {}
        showInfo(msg, false);
      })
      .always(function () {
        $btn.prop('disabled', false);
        pendingUsername = '';
      });
  });
})();
</script>
