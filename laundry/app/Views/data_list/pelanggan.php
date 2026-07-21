<?php
$page = $data['z']['page'];
$rows = $data['data_main'] ?? [];
$total = is_array($rows) ? count($rows) : 0;
$canEditPartner = ((int) $this->id_privilege === 100);
?>

<style>
  #plg-root {
    font-family: 'fontku', sans-serif;
    color: var(--mdl-ink);
    padding: 0 4px 32px;
  }
  #plg-root * { box-sizing: border-box; }

  #plg-root .plg-panel {
    margin-bottom: 12px;
    overflow: hidden;
    border-radius: 16px;
    border: 1px solid rgba(184, 196, 210, 0.7);
    background: linear-gradient(135deg, #fff 0%, var(--mdl-surface) 55%, rgba(217, 230, 250, 0.45) 100%);
    box-shadow: 0 8px 24px rgba(36, 48, 65, 0.08);
  }
  #plg-root .plg-panel-search {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(184, 196, 210, 0.5);
  }
  #plg-root .plg-label {
    display: block;
    margin-bottom: 6px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--mdl-ink-soft);
  }
  #plg-root .plg-search-wrap {
    position: relative;
  }
  #plg-root .plg-search-wrap i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--mdl-ink-soft);
    pointer-events: none;
  }
  #plg-root .plg-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--mdl-line);
    border-radius: 12px;
    background: #fff;
    font-family: inherit;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--mdl-ink);
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  #plg-root .plg-input--search {
    padding-left: 40px;
  }
  #plg-root .plg-input:focus {
    border-color: var(--mdl-accent);
    box-shadow: 0 0 0 3px rgba(63, 116, 212, 0.22);
  }

  #plg-root .plg-form {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    padding: 12px 16px;
    align-items: end;
  }
  #plg-root .plg-field { min-width: 0; }
  #plg-root .plg-btn-wrap {
    display: flex;
    align-items: flex-end;
  }
  #plg-root .plg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 42px;
    width: 100%;
    padding: 0 20px;
    border: 0;
    border-radius: 12px;
    background: var(--mdl-accent);
    color: #fff;
    font-family: inherit;
    font-size: 0.875rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(63, 116, 212, 0.35);
    transition: background .15s ease, transform .1s ease;
  }
  #plg-root .plg-btn:hover { background: var(--mdl-accent-deep); }
  #plg-root .plg-btn:active { transform: scale(0.98); }
  #plg-root .plg-btn:disabled { opacity: 0.65; cursor: not-allowed; }

  #plg-root .plg-list-panel {
    overflow: hidden;
    border-radius: 16px;
    border: 1px solid rgba(184, 196, 210, 0.7);
    background: #fff;
    box-shadow: 0 8px 24px rgba(36, 48, 65, 0.06);
  }
  #plg-root .plg-list-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(184, 196, 210, 0.5);
    background: rgba(244, 247, 251, 0.9);
  }
  #plg-root .plg-list-bar span {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--mdl-ink-soft);
  }
  #plg-root .plg-list-bar .plg-list-tip {
    display: none;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
  }

  #plg-root #plg-list {
    max-height: min(70vh, 640px);
    overflow-y: auto;
  }

  /* Default = compact (viewport app ~430px) */
  #plg-root .plg-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    grid-template-areas:
      "id nama partner"
      "id hp partner";
    align-items: center;
    column-gap: 10px;
    row-gap: 2px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(184, 196, 210, 0.4);
    transition: background .15s ease;
  }
  #plg-root .plg-row:last-child { border-bottom: 0; }
  #plg-root .plg-row:hover { background: rgba(217, 230, 250, 0.4); }

  #plg-root .plg-col-id { grid-area: id; }
  #plg-root .plg-col-nama {
    grid-area: nama;
    min-width: 0;
    overflow: hidden;
  }
  #plg-root .plg-col-hp {
    grid-area: hp;
    min-width: 0;
    overflow: hidden;
  }
  #plg-root .plg-col-partner {
    grid-area: partner;
    text-align: right;
    white-space: nowrap;
  }

  #plg-root .plg-k {
    display: none;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--mdl-ink-soft);
  }
  #plg-root .plg-id-val {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--mdl-ink-soft);
  }
  #plg-root .plg-partner-val {
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--mdl-ink);
  }
  #plg-root .plg-edit {
    cursor: text;
    border-radius: 4px;
    padding: 0 2px;
    color: var(--mdl-ink);
    transition: color .15s ease;
  }
  #plg-root .plg-edit--nama {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    vertical-align: bottom;
  }
  #plg-root .plg-row:hover .plg-edit--nama { color: var(--mdl-accent-deep); }
  #plg-root .plg-edit--hp {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--mdl-ink-soft);
  }

  #plg-root .plg-inline {
    width: auto;
    min-width: 8rem;
    max-width: 100%;
    padding: 4px 8px;
    border: 1px solid var(--mdl-accent);
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--mdl-ink);
    outline: none;
    box-shadow: 0 0 0 3px rgba(63, 116, 212, 0.25);
  }
  #plg-root .plg-inline--sm {
    min-width: 3.5rem;
    width: 3.5rem;
  }

  #plg-root .plg-empty {
    padding: 56px 16px;
    text-align: center;
  }
  #plg-root .plg-empty--filter {
    padding: 48px 16px;
  }
  #plg-root .plg-empty.is-hidden { display: none; }
  #plg-root .plg-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    border-radius: 16px;
    background: var(--mdl-accent-soft);
    color: var(--mdl-accent);
    font-size: 1.125rem;
  }
  #plg-root .plg-empty-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--mdl-ink);
  }
  #plg-root .plg-empty-sub {
    margin: 6px 0 0;
    font-size: 0.875rem;
    color: var(--mdl-ink-soft);
  }

  @media (min-width: 768px) {
    #plg-root .plg-form {
      grid-template-columns: 1fr 1.2fr auto;
    }
    #plg-root .plg-btn { min-width: 120px; }
    #plg-root .plg-list-bar .plg-list-tip { display: inline; }
    #plg-root .plg-k { display: block; }
    #plg-root .plg-row {
      display: flex;
      flex-wrap: nowrap;
      align-items: center;
      gap: 8px 16px;
      padding: 12px 16px;
    }
    #plg-root .plg-col-id {
      display: flex;
      flex-direction: column;
      min-width: 3.5rem;
    }
    #plg-root .plg-col-nama {
      flex: 1 1 auto;
      min-width: 0;
    }
    #plg-root .plg-col-hp { min-width: 9rem; }
    #plg-root .plg-col-partner {
      margin-left: auto;
      min-width: 5.5rem;
    }
    #plg-root .plg-id-val { font-size: 0.875rem; }
    #plg-root .plg-edit--nama { font-size: 1rem; white-space: normal; }
    #plg-root .plg-edit--hp {
      font-size: 0.875rem;
      color: var(--mdl-ink);
    }
    #plg-root .plg-partner-val { font-size: 0.875rem; }
  }
</style>

<div id="plg-root">
  <div class="plg-panel">
    <div class="plg-panel-search">
      <label class="plg-label" for="plg-filter">Cari</label>
      <div class="plg-search-wrap">
        <i class="fas fa-search"></i>
        <input
          type="text"
          id="plg-filter"
          class="plg-input plg-input--search"
          autocomplete="off"
          placeholder="Cari nama, nomor HP, atau ID…"
        >
      </div>
    </div>

    <form id="plg-form" class="plg-form" action="<?= URL::BASE_URL; ?>Data_List/insert/<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" method="POST">
      <div class="plg-field">
        <label class="plg-label" for="plg-hp">Nomor HP</label>
        <input type="text" id="plg-hp" name="f2" class="plg-input" required autocomplete="off" placeholder="08…">
      </div>
      <div class="plg-field">
        <label class="plg-label" for="plg-nama">Nama pelanggan</label>
        <input type="text" id="plg-nama" name="f1" class="plg-input" required autocomplete="off" placeholder="Nama lengkap">
      </div>
      <div class="plg-btn-wrap">
        <button type="submit" id="plg-submit" class="plg-btn">
          <i class="fas fa-plus"></i>
          Tambah
        </button>
      </div>
    </form>
  </div>

  <div class="plg-list-panel">
    <div class="plg-list-bar">
      <span>Daftar</span>
      <span class="plg-list-tip">Double-click nama / HP<?= $canEditPartner ? ' / partner' : '' ?> untuk edit</span>
    </div>

    <div id="plg-list">
      <?php if ($total === 0) { ?>
        <div class="plg-empty" id="plg-empty-all">
          <div class="plg-empty-icon"><i class="fas fa-user-plus"></i></div>
          <p class="plg-empty-title">Belum ada pelanggan</p>
          <p class="plg-empty-sub">Tambah pelanggan baru lewat form di atas.</p>
        </div>
      <?php } else { ?>
        <?php foreach ($rows as $a) {
          $id = (int) $a['id_pelanggan'];
          $f1 = $a['nama_pelanggan'];
          $f2 = $a['nomor_pelanggan'];
          $f5 = $a['disc'];

          if ($f1 === '' || $f1 === null) {
            $f1 = '[ ]';
          }
          if ($f2 === '' || $f2 === null) {
            $f2 = '08';
          }

          $f1Show = strtoupper($f1);
          $f1Attr = htmlspecialchars($f1, ENT_QUOTES, 'UTF-8');
          $f1Html = htmlspecialchars($f1Show, ENT_QUOTES, 'UTF-8');
          $f2Attr = htmlspecialchars($f2, ENT_QUOTES, 'UTF-8');
          $f2Html = htmlspecialchars($f2, ENT_QUOTES, 'UTF-8');
          $f5Attr = htmlspecialchars((string) $f5, ENT_QUOTES, 'UTF-8');
          $searchBlob = strtolower($id . ' ' . $f1Show . ' ' . $f2 . ' ' . $f5);
        ?>
          <div class="plg-row" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
            <div class="plg-col-id">
              <span class="plg-k">ID</span>
              <span class="plg-id-val">#<?= $id ?></span>
            </div>
            <div class="plg-col-nama">
              <span class="plg-k">Nama</span>
              <div>
                <span
                  class="plg-edit plg-edit--nama"
                  data-mode="1"
                  data-id_value="<?= $id ?>"
                  data-value="<?= $f1Attr ?>"
                  title="Double-click untuk edit"
                ><?= $f1Html ?></span>
              </div>
            </div>
            <div class="plg-col-hp">
              <span class="plg-k">HP</span>
              <div>
                <span
                  class="plg-edit plg-edit--hp"
                  data-mode="2"
                  data-id_value="<?= $id ?>"
                  data-value="<?= $f2Attr ?>"
                  title="Double-click untuk edit"
                ><?= $f2Html ?></span>
              </div>
            </div>
            <div class="plg-col-partner">
              <span class="plg-k">Partner</span>
              <div class="plg-partner-val">
                <?php if ($canEditPartner) { ?>
                  <span
                    class="plg-edit"
                    data-mode="5"
                    data-id_value="<?= $id ?>"
                    data-value="<?= $f5Attr ?>"
                    title="Double-click untuk edit"
                  ><?= $f5Attr ?></span>%
                <?php } else { ?>
                  <?= $f5Attr ?>%
                <?php } ?>
              </div>
            </div>
          </div>
        <?php } ?>

        <div id="plg-empty-filter" class="plg-empty plg-empty--filter is-hidden">
          <p class="plg-empty-title">Tidak ada hasil</p>
          <p class="plg-empty-sub">Coba kata kunci lain.</p>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<script>
(function ($) {
  var $root = $('#plg-root');
  if (!$root.length) return;

  var editing = false;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function applyFilter() {
    var q = ($('#plg-filter').val() || '').toLowerCase().trim();
    var hp = ($('#plg-hp').val() || '').toLowerCase();
    var hpTail = hp.length >= 8 ? hp.substring(hp.length - 8) : hp;
    var nama = ($('#plg-nama').val() || '').toLowerCase().trim();
    var visible = 0;

    $root.find('.plg-row').each(function () {
      var blob = ($(this).attr('data-search') || '').toLowerCase();
      var ok = true;
      if (q && blob.indexOf(q) === -1) ok = false;
      if (ok && nama && blob.indexOf(nama) === -1) ok = false;
      if (ok && hpTail && blob.indexOf(hpTail) === -1) ok = false;
      $(this).toggle(ok);
      if (ok) visible++;
    });

    var $emptyFilter = $('#plg-empty-filter');
    if ($emptyFilter.length) {
      $emptyFilter.toggleClass('is-hidden', visible > 0 || $root.find('.plg-row').length === 0);
    }
  }

  $('#plg-form').on('submit', function (e) {
    e.preventDefault();
    var $btn = $('#plg-submit');
    $btn.prop('disabled', true);
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr('method'),
      success: function (response) {
        if (String(response).trim() === '1') {
          location.reload(true);
        } else {
          alert(response);
          $btn.prop('disabled', false);
        }
      },
      error: function () {
        alert('Gagal menambah pelanggan');
        $btn.prop('disabled', false);
      }
    });
  });

  $root.on('dblclick', '.plg-edit', function () {
    if (editing) return;
    editing = true;

    var $span = $(this);
    var id_value = $span.attr('data-id_value');
    var value = $span.attr('data-value');
    var mode = $span.attr('data-mode');
    var value_before = value;

    var inputClass = mode === '5' ? 'plg-inline plg-inline--sm' : 'plg-inline';
    var inputType = mode === '5' ? 'number' : 'text';
    $span.html(
      "<input type='" + inputType + "' id='plg-value' class='" + inputClass + "' value='" + escapeHtml(value) + "'>"
    );

    var $input = $('#plg-value');
    $input.focus().select();

    function finish(restore) {
      if (restore) {
        if (mode === '1') {
          $span.html(escapeHtml(String(value_before).toUpperCase()));
        } else {
          $span.html(escapeHtml(value_before));
        }
      }
      editing = false;
    }

    $input.on('keydown', function (ev) {
      if (ev.key === 'Escape') {
        finish(true);
      }
      if (ev.key === 'Enter') {
        $(this).blur();
      }
    });

    $input.on('focusout', function () {
      var value_after = $(this).val();
      if (value_after === value_before || value_after.length === 0) {
        finish(true);
        return;
      }

      $.ajax({
        url: '<?= URL::BASE_URL ?>Data_List/updateCell/<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>',
        data: {
          id: id_value,
          value: value_after,
          mode: mode
        },
        type: 'POST',
        dataType: 'html',
        success: function () {
          $span.attr('data-value', value_after);
          if (mode === '1') {
            $span.html(escapeHtml(String(value_after).toUpperCase()));
          } else {
            $span.html(escapeHtml(value_after));
          }
          var $row = $span.closest('.plg-row');
          var blob = ($row.find('.plg-edit').map(function () {
            return $(this).attr('data-value');
          }).get().join(' ') + ' ' + id_value).toLowerCase();
          $row.attr('data-search', blob);
          editing = false;
        },
        error: function () {
          finish(true);
          alert('Gagal menyimpan');
        }
      });
    });
  });

  $('#plg-filter, #plg-nama, #plg-hp').on('keyup input', applyFilter);
})(jQuery);
</script>
