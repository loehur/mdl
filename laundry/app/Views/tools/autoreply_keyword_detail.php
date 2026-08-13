<div class="content" id="arkd-root">
  <style>
    #arkd-root { --ink:#0f172a; --muted:#334155; --line:#94a3b8; --blue:#2563eb; font-family:'fontku','Segoe UI',sans-serif; }
    #arkd-root .shell {
      background: linear-gradient(180deg, #eef4ff 0%, #fff 65%);
      border: 1px solid #cbd5e1; border-radius: 0; padding: 14px; max-width: 1100px;
    }
    #arkd-root .title { font-weight: 900; font-size: 1.15rem; margin: 0 0 10px; color: var(--ink); }
    #arkd-root label { font-weight: 800; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); }
    #arkd-root .form-control, #arkd-root textarea, #arkd-root .form-select {
      border-radius: 0; border: 1px solid var(--line); font-weight: 600;
    }
    #arkd-root .form-control:focus, #arkd-root textarea:focus, #arkd-root .form-select:focus {
      border-color: var(--blue); box-shadow: 0 0 0 2px rgba(37,99,235,.22); outline: none;
    }
    #arkd-root .btn { border-radius: 0 !important; font-weight: 800; }
    #arkd-root .pat-box {
      font-family: ui-monospace, Consolas, monospace; font-size: .78rem; white-space: pre-wrap; word-break: break-all;
      border: 1px solid #cbd5e1; background: #f8fafc; padding: 8px; cursor: pointer; min-height: 42px;
    }
    #arkd-root .pat-box:hover { border-color: var(--blue); }
    #arkd-root .section-h { font-weight: 900; margin: 18px 0 8px; color: var(--ink); }
  </style>

  <?php
  $intent = $data['intent'];
  $patterns = $data['patterns'] ?? [];
  $id = (int) $intent['id'];
  $notifyVal = ($intent['notify'] === null || $intent['notify'] === '') ? '' : (string) (int) $intent['notify'];
  $caseVal = ($intent['case_value'] === null || $intent['case_value'] === '') ? '' : (string) (int) $intent['case_value'];
  ?>

  <div class="container-fluid">
    <div class="shell">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h1 class="title mb-0">Intent: <?= htmlspecialchars($intent['code']) ?></h1>
        <a class="btn btn-sm btn-outline-secondary" href="<?= URL::BASE_URL ?>AutoReplyKeywords">← Daftar</a>
      </div>
      <div id="arkdInfo"></div>

      <form id="formIntent" method="POST" action="<?= URL::BASE_URL ?>AutoReplyKeywords/updateIntent">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Code</label>
            <input type="text" name="code" class="form-control form-control-sm" value="<?= htmlspecialchars($intent['code']) ?>" required pattern="[A-Za-z0-9_]+">
          </div>
          <div class="col-md-2">
            <label class="form-label">Sort</label>
            <input type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$intent['sort_order'] ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Case</label>
            <input type="number" name="case_value" class="form-control form-control-sm" value="<?= htmlspecialchars($caseVal) ?>" placeholder="kosong = unset">
          </div>
          <div class="col-md-2">
            <label class="form-label">Notify</label>
            <select name="notify" class="form-select form-select-sm">
              <option value="" <?= $notifyVal === '' ? 'selected' : '' ?>>unset</option>
              <option value="1" <?= $notifyVal === '1' ? 'selected' : '' ?>>true</option>
              <option value="0" <?= $notifyVal === '0' ? 'selected' : '' ?>>false</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?= ((int)$intent['is_active'] === 1) ? 'checked' : '' ?>>
              <label class="form-check-label fw-bold" for="isActive">Aktif</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Note</label>
            <input type="text" name="note" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($intent['note'] ?? '')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">AI Prompt</label>
            <textarea name="ai_prompt" class="form-control" rows="10"><?= htmlspecialchars((string)($intent['ai_prompt'] ?? '')) ?></textarea>
          </div>
        </div>
        <div class="mt-2">
          <button type="submit" class="btn btn-sm btn-primary">Simpan Intent</button>
        </div>
      </form>

      <h2 class="section-h">Patterns (<?= count($patterns) ?>)</h2>
      <p class="text-muted small mb-2">Klik pattern untuk edit. Regex wajib valid (delimiter PHP, contoh <code>/pola/i</code>).</p>

      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle" id="patTable">
          <thead>
            <tr>
              <th style="width:60px">Sort</th>
              <th>Pattern</th>
              <th style="width:70px">Aktif</th>
              <th style="width:90px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($patterns as $p):
              $pid = (int) $p['id'];
              $pat = htmlspecialchars($p['pattern'], ENT_QUOTES);
            ?>
            <tr data-id="<?= $pid ?>">
              <td>
                <input type="number" class="form-control form-control-sm pat-sort" value="<?= (int)$p['sort_order'] ?>" style="width:70px">
              </td>
              <td>
                <div class="pat-box pat-edit" data-id="<?= $pid ?>" title="Klik untuk edit"><?= $pat ?></div>
              </td>
              <td>
                <input type="checkbox" class="form-check-input pat-active" <?= ((int)$p['is_active'] === 1) ? 'checked' : '' ?>>
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-outline-primary btn-test" data-pattern="<?= $pat ?>">Test</button>
                <button type="button" class="btn btn-sm btn-danger btn-del-pat">Hapus</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="section-h">Tambah Pattern</h2>
      <form id="formAddPat" method="POST" action="<?= URL::BASE_URL ?>AutoReplyKeywords/insertPattern">
        <input type="hidden" name="intent_id" value="<?= $id ?>">
        <label class="form-label">Regex</label>
        <textarea name="pattern" class="form-control mb-2" rows="3" required placeholder="/\bmksh\b/i"></textarea>
        <label class="form-label">Note</label>
        <input type="text" name="note" class="form-control form-control-sm mb-2">
        <button type="submit" class="btn btn-sm btn-primary">Tambah Pattern</button>
      </form>

      <h2 class="section-h">Test Regex</h2>
      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label">Pattern</label>
          <textarea id="testPat" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Teks contoh</label>
          <textarea id="testText" class="form-control" rows="2" placeholder="mksh byk"></textarea>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnRunTest">Jalankan Test</button>
      <div id="testResult" class="mt-2 fw-bold"></div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script>
$(function() {
  function info(msg, ok) {
    $('#arkdInfo').html('<div class="alert alert-' + (ok ? 'success' : 'danger') + ' py-2">' + msg + '</div>');
  }

  $('#formIntent').on('submit', function(e) {
    e.preventDefault();
    $.post($(this).attr('action'), $(this).serialize()).done(function(r) {
      if (String(r).trim() === '0') info('Intent tersimpan. Cache dibump.', true);
      else info(r, false);
    });
  });

  $('#formAddPat').on('submit', function(e) {
    e.preventDefault();
    $.post($(this).attr('action'), $(this).serialize()).done(function(r) {
      if (String(r).trim() === '0') location.reload(true);
      else info(r, false);
    });
  });

  $(document).on('click', '.pat-edit', function() {
    var $el = $(this);
    var id = $el.data('id');
    var cur = $el.text();
    var next = prompt('Edit pattern:', cur);
    if (next === null) return;
    next = String(next).trim();
    if (!next) return;
    $.post('<?= URL::BASE_URL ?>AutoReplyKeywords/updatePattern', {
      id: id, mode: 'pattern', value: next
    }).done(function(r) {
      if (String(r).trim() === '0') location.reload(true);
      else info(r, false);
    });
  });

  $(document).on('change', '.pat-active', function() {
    var id = $(this).closest('tr').data('id');
    var val = $(this).is(':checked') ? 1 : 0;
    $.post('<?= URL::BASE_URL ?>AutoReplyKeywords/updatePattern', {
      id: id, mode: 'is_active', value: val
    }).done(function(r) {
      if (String(r).trim() !== '0') info(r, false);
    });
  });

  $(document).on('change', '.pat-sort', function() {
    var id = $(this).closest('tr').data('id');
    $.post('<?= URL::BASE_URL ?>AutoReplyKeywords/updatePattern', {
      id: id, mode: 'sort_order', value: $(this).val()
    }).done(function(r) {
      if (String(r).trim() !== '0') info(r, false);
    });
  });

  $(document).on('click', '.btn-del-pat', function() {
    if (!confirm('Hapus pattern ini?')) return;
    var id = $(this).closest('tr').data('id');
    $.post('<?= URL::BASE_URL ?>AutoReplyKeywords/deletePattern', { id: id }).done(function(r) {
      if (String(r).trim() === '0') location.reload(true);
      else info(r, false);
    });
  });

  $(document).on('click', '.btn-test', function() {
    $('#testPat').val($(this).attr('data-pattern'));
    $('#testText').focus();
  });

  $('#btnRunTest').on('click', function() {
    $.post('<?= URL::BASE_URL ?>AutoReplyKeywords/testPattern', {
      pattern: $('#testPat').val(),
      text: $('#testText').val()
    }).done(function(res) {
      try { if (typeof res === 'string') res = JSON.parse(res); } catch (e) {}
      var ok = res && res.ok;
      var match = res && res.match;
      $('#testResult').css('color', match ? '#15803d' : '#b45309')
        .text((res && res.message) ? res.message : (ok ? 'ok' : 'error'));
    });
  });
});
</script>
